<?php
header('Content-Type: application/json');

try {
    require_once '../db_connect.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception("Invalid CSRF token");
    }

    $candidate_id = filter_input(INPUT_POST, 'candidate_id', FILTER_VALIDATE_INT);
    $exam_year_id = filter_input(INPUT_POST, 'exam_year_id', FILTER_VALIDATE_INT);
    $user_id = $_SESSION['user_id'];

    if (!$candidate_id || !$exam_year_id) {
        throw new Exception("Invalid candidate or exam year");
    }

    $stmt = $conn->prepare("SELECT m.subject_id, s.name AS subject_name, s.code AS subject_code, 
                            m.mark, m.status, m.submitted_at, m.updated_at 
                            FROM marks m JOIN subjects s ON m.subject_id = s.id 
                            WHERE m.candidate_id = ? AND m.exam_year_id = ? 
                            ORDER BY s.name");
    $stmt->bind_param("ii", $candidate_id, $exam_year_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $marks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Log fetch action
    $stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, description, created_at) VALUES (?, ?, ?, NOW())");
    $log_action = 'Fetch Marks';
    $log_desc = "Fetched marks for candidate ID: $candidate_id, exam year ID: $exam_year_id";
    $stmt->bind_param("sis", $log_action, $user_id, $log_desc);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'marks' => $marks]);
} catch (Exception $e) {
    error_log("Fetch marks error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>