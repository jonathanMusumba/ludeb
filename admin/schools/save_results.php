<?php
session_start();
require_once '../../Common/db_connect.php';

// Restrict to System Admins and Data Entrants
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['System Admin', 'Data Entrant'])) {
    header("Location: ../../Common/login.php");
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed", 3, '../../../setup_errors.log');
    die("Invalid CSRF token");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $school_id = (int)($_POST['school_id'] ?? 0);
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $marks = $_POST['marks'] ?? [];
        $user_id = $_SESSION['user_id'];

        if ($school_id <= 0 || $subject_id <= 0 || empty($marks)) {
            throw new Exception("Invalid input data");
        }

        // Start transaction
        $conn->begin_transaction();

        // Prepare statements
        $stmt_check = $conn->prepare("SELECT id FROM marks WHERE candidate_id = ? AND subject_id = ?");
        $stmt_insert = $conn->prepare("INSERT INTO marks (candidate_id, subject_id, school_id, mark, submitted_at, submitted_by) 
                                      VALUES (?, ?, ?, ?, NOW(), ?)");
        $stmt_update = $conn->prepare("UPDATE marks SET mark = ?, updated_at = NOW(), edited_by = ? 
                                      WHERE candidate_id = ? AND subject_id = ?");
        $stmt_result = $conn->prepare("INSERT INTO results (candidate_id, subject_id, school_id, mark, score, division, processed_by) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)
                                      ON DUPLICATE KEY UPDATE mark = ?, score = ?, division = ?, processed_by = ?");

        foreach ($marks as $candidate_id => $mark) {
            $candidate_id = (int)$candidate_id;
            $mark = (int)$mark;

            if ($mark < 0 || $mark > 100) {
                continue; // Skip invalid marks
            }

            // Check if mark exists
            $stmt_check->bind_param("ii", $candidate_id, $subject_id);
            $stmt_check->execute();
            $exists = $stmt_check->get_result()->num_rows > 0;

            if ($exists) {
                // Update existing mark
                $stmt_update->bind_param("iiii", $mark, $user_id, $candidate_id, $subject_id);
                $stmt_update->execute();
            } else {
                // Insert new mark
                $stmt_insert->bind_param("iiiii", $candidate_id, $subject_id, $school_id, $mark, $user_id);
                $stmt_insert->execute();
            }

            // Update results (simplified scoring: score = mark, division based on mark)
            $score = $mark;
            $division = $mark >= 80 ? '1' : ($mark >= 60 ? '2' : ($mark >= 40 ? '3' : '4'));
            $stmt_result->bind_param("iiiiisiiiiis", 
                $candidate_id, $subject_id, $school_id, $mark, $score, $division, $user_id,
                $mark, $score, $division, $user_id);
            $stmt_result->execute();
        }

        // Update results_status
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT c.id) as total_candidates, 
                                COUNT(DISTINCT m.candidate_id) as marked_candidates
                                FROM candidates c
                                LEFT JOIN marks m ON c.id = m.candidate_id
                                WHERE c.school_id = ?");
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $total = $row['total_candidates'];
        $marked = $row['marked_candidates'];
        $status = $marked == 0 ? 'Not Declared' : ($marked < $total ? 'Partially Declared' : 'Declared');

        $stmt = $conn->prepare("UPDATE schools SET results_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $school_id);
        $stmt->execute();

        // Log action
        $conn->query("CALL log_action('Save Results', $user_id, 'Saved marks for school ID $school_id, subject ID $subject_id')");

        $conn->commit();
        header("Location: capture_results.php?center_no=" . urlencode($_POST['center_no']) . "&success=Marks saved successfully");
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Save Results Error: " . $e->getMessage(), 3, '../../../setup_errors.log');
        header("Location: capture_results.php?center_no=" . urlencode($_POST['center_no']) . "&error=" . urlencode($e->getMessage()));
    } finally {
        $conn->close();
    }
}
?>