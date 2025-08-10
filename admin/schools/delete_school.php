<?php
ob_start(); // Start output buffering to prevent accidental output
session_start();
require_once '../../Common/db_connect.php';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    error_log("Unauthorized access attempt to delete_school.php", 3, '../../../setup_errors.log');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    ob_end_flush();
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed in delete_school.php", 3, '../../../setup_errors.log');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid CSRF token']);
    ob_end_flush();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in delete_school.php", 3, '../../../setup_errors.log');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method']);
    ob_end_flush();
    exit();
}

try {
    $center_no = isset($_POST['center_no']) ? trim($_POST['center_no']) : '';
    if (empty($center_no) || !preg_match('/^\d{6}$/', $center_no)) {
        throw new Exception("Invalid center number");
    }

    // Fetch school_id and details using center_no
    $stmt = $conn->prepare("SELECT id, school_name FROM schools WHERE center_no = ?");
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    $stmt->bind_param("s", $center_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $school = $result->fetch_assoc();
    if (!$school) {
        throw new Exception("School not found");
    }
    $school_id = $school['id'];
    $school_name = $school['school_name'];

    // Check for dependencies in related tables
    $dependencies = [];
    
    // Check candidates
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM candidates WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row['count'] > 0) {
        $dependencies[] = "candidates ({$row['count']} records)";
    }

    // Check marks
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM marks WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row['count'] > 0) {
        $dependencies[] = "marks ({$row['count']} records)";
    }

    // Check results
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM results WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row['count'] > 0) {
        $dependencies[] = "results ({$row['count']} records)";
    }

    // Check captured_subjects
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM captured_subjects WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row['count'] > 0) {
        $dependencies[] = "captured subjects ({$row['count']} records)";
    }

    // Check school_results
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM school_results WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row['count'] > 0) {
        $dependencies[] = "school results ({$row['count']} records)";
    }

    // Check uploads
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM uploads WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row['count'] > 0) {
        $dependencies[] = "uploads ({$row['count']} records)";
    }

    // Check system_users
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM system_users WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row['count'] > 0) {
        $dependencies[] = "system users ({$row['count']} records)";
    }

    // If dependencies exist, throw an error
    if (!empty($dependencies)) {
        throw new Exception("Cannot delete school due to dependencies in: " . implode(", ", $dependencies) . ". Deleting this school would cascade delete related candidates, marks, results, captured subjects, and school results, and set null for uploads and system users.");
    }

    // Delete school
    $stmt = $conn->prepare("DELETE FROM schools WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    $stmt->bind_param("i", $school_id);
    if (!$stmt->execute()) {
        throw new Exception("Delete failed: " . $stmt->error);
    }

    // Log action with escaped strings
    $user_id = $_SESSION['user_id'];
    $escaped_school_name = mysqli_real_escape_string($conn, $school_name);
    $escaped_center_no = mysqli_real_escape_string($conn, $center_no);
    if (!$conn->query("CALL log_action('Delete School', $user_id, 'Deleted school: $escaped_school_name (Center: $escaped_center_no)')")) {
        error_log("Failed to log action for school deletion: " . $conn->error, 3, '../../../setup_errors.log');
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Delete School Error: " . $e->getMessage(), 3, '../../../setup_errors.log');
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
    ob_end_flush(); // Flush output buffer
}
?>