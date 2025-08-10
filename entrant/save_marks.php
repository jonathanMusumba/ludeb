<?php
ob_start();
session_start();
require_once 'db_connect.php';

// Restrict to authorized users
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Data Entrant', 'System Admin'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:\xampp\htdocs\ludeb\setup_errors.log');

try {
    // Validate input
    $candidate_id = isset($_POST['candidate_id']) ? intval($_POST['candidate_id']) : 0;
    $subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
    $school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : 0;
    $mark = isset($_POST['mark']) && $_POST['mark'] !== '' ? intval($_POST['mark']) : null;
    $status = isset($_POST['status']) && $_POST['status'] === 'ABSENT' ? 'ABSENT' : 'PRESENT';

    if (!$candidate_id || !$subject_id || !$school_id) {
        throw new Exception('Invalid input parameters');
    }

    // Fetch active exam year
    $stmt = $conn->query("SELECT id FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC LIMIT 1");
    $row = $stmt->fetch_assoc();
    if (!$row) {
        throw new Exception('No active exam year found');
    }
    $exam_year_id = $row['id'];
    $stmt->close();

    if ($status === 'PRESENT' && ($mark === null || $mark < 0 || $mark > 100)) {
        throw new Exception('Mark must be between 0 and 100');
    }

    // Get candidate name
    $stmt = $conn->prepare("SELECT candidate_name FROM candidates WHERE id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result->fetch_assoc();
    $candidate_name = $candidate['candidate_name'] ?? 'Unknown Candidate';
    $stmt->close();

    $conn->begin_transaction();

    // Get subcounty_id
    $stmt = $conn->prepare("SELECT subcounty_id FROM schools WHERE id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $subcounty_id = $row['subcounty_id'] ?? 0;
    $stmt->close();

    // Check if mark exists
    $check_sql = "SELECT mark, status, submitted_at FROM marks WHERE candidate_id = ? AND subject_id = ? AND exam_year_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("iii", $candidate_id, $subject_id, $exam_year_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        // Insert new mark
        $insert_sql = "INSERT INTO marks (candidate_id, subject_id, school_id, exam_year_id, mark, status, submitted_by, submitted_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insert_sql);
        $mark_to_insert = $status === 'ABSENT' ? 0 : $mark;
        $stmt->bind_param("iiiisii", $candidate_id, $subject_id, $school_id, $exam_year_id, $mark_to_insert, $status, $user_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert mark: ' . $conn->error);
        }
    } else {
        // Update existing mark (no time-based lock)
        $update_sql = "UPDATE marks SET mark = ?, status = ?, updated_at = NOW(), edited_by = ? 
                       WHERE candidate_id = ? AND subject_id = ? AND exam_year_id = ?";
        $stmt = $conn->prepare($update_sql);
        $mark_to_update = $status === 'ABSENT' ? 0 : $mark;
        $stmt->bind_param("isiiii", $mark_to_update, $status, $user_id, $candidate_id, $subject_id, $exam_year_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update mark: ' . $conn->error);
        }
    }

    // Call stored procedures
    $stmt = $conn->prepare("CALL ComputeCandidateGrades(?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiii", $candidate_id, $school_id, $subcounty_id, $exam_year_id, $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to process grades for candidate ID $candidate_id: " . $conn->error);
    }
    $stmt->close();

    $stmt = $conn->prepare("CALL ComputeCandidateResults(?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiii", $candidate_id, $school_id, $subcounty_id, $exam_year_id, $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to process results for candidate ID $candidate_id: " . $conn->error);
    }
    $stmt->close();

    // Log successful mark entry
    $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
    $action = $existing ? 'Mark Update' : 'Mark Insert';
    $details = $existing ? "Updated mark for candidate: $candidate_name (ID: $candidate_id), subject_id: $subject_id, exam_year_id: $exam_year_id, old_mark: " . ($existing['status'] === 'ABSENT' ? 'ABSENT' : $existing['mark']) . ", new_mark: " . ($status === 'ABSENT' ? 'ABSENT' : $mark) : 
                         "Saved mark for candidate: $candidate_name (ID: $candidate_id), subject_id: $subject_id, exam_year_id: $exam_year_id, mark: " . ($status === 'ABSENT' ? 'ABSENT' : $mark);
    $stmt->bind_param("sis", $action, $user_id, $details);
    if (!$stmt->execute()) {
        throw new Exception('Failed to log action: ' . $conn->error);
    }
    $stmt->close();

    $conn->commit();
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    $message = $status === 'ABSENT' ? "$candidate_name submitted as absent" : "Mark saved for $candidate_name";
    echo json_encode(['status' => 'success', 'message' => $message]);
    exit();
} catch (Exception $e) {
    $conn->rollback();

    // Log error
    $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
    $action = 'Mark Save Error';
    $details = "Error saving mark for candidate_id: $candidate_id, subject_id: $subject_id, exam_year_id: $exam_year_id: " . $e->getMessage();
    $stmt->bind_param("sis", $action, $user_id, $details);
    $stmt->execute();
    $stmt->close();

    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
    ob_end_clean();
}
?>