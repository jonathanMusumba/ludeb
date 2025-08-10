<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db_connect.php';

// Check if database connection is valid
if (!$conn || $conn->connect_error) {
    error_log("Database connection invalid in fetch_user_details.php: " . ($conn ? $conn->connect_error : 'No connection object'), 3, __DIR__ . '/logs/setup_errors.log');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Verify CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Validate input parameters
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$type = $_POST['type'] ?? '';
$exam_year = filter_input(INPUT_POST, 'exam_year', FILTER_VALIDATE_INT) ?: null;

if (!$user_id || !in_array($type, ['entries', 'processed', 'actions'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request parameters']);
    exit;
}

$data = [];
$stmt = null;
try {
    if ($type === 'entries') {
        // Fetch marks entered by the user
        $sql = "SELECT m.id, m.candidate_id, s.name AS subject, m.mark, m.submitted_at 
                FROM marks m 
                JOIN subjects s ON m.subject_id = s.id 
                WHERE m.submitted_by = ?";
        if ($exam_year) {
            $sql .= " AND m.exam_year_id = ?";
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare query: " . $conn->error);
        }
        if ($exam_year) {
            $stmt->bind_param("ii", $user_id, $exam_year);
        } else {
            $stmt->bind_param("i", $user_id);
        }
    } elseif ($type === 'processed') {
        // Fetch results processed by the user
        $sql = "SELECT r.id, r.candidate_id, s.name AS subject, r.mark, r.score, r.processed_at 
                FROM results r 
                JOIN subjects s ON r.subject_id = s.id 
                WHERE r.processed_by = ?";
        if ($exam_year) {
            $sql .= " AND r.exam_year_id = ?";
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare query: " . $conn->error);
        }
        if ($exam_year) {
            $stmt->bind_param("ii", $user_id, $exam_year);
        } else {
            $stmt->bind_param("i", $user_id);
        }
    } elseif ($type === 'actions') {
        // Fetch audit logs for the user
        $sql = "SELECT id, action, details, created_at 
                FROM audit_logs 
                WHERE user_id = ?";
        if ($exam_year) {
            $sql .= " AND YEAR(created_at) = ?";
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare query: " . $conn->error);
        }
        if ($exam_year) {
            $stmt->bind_param("ii", $user_id, $exam_year);
        } else {
            $stmt->bind_param("i", $user_id);
        }
    }

    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    error_log("Error in fetch_user_details.php: " . $e->getMessage(), 3, __DIR__ . '/logs/setup_errors.log');
    echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
} finally {
    if ($stmt) {
        $stmt->close();
    }
    // Do not close $conn to avoid issues in other scripts
}
?>