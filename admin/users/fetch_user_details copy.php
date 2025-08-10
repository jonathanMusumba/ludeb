<?php
session_start();
require_once '..//db_connect.php';

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

$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$type = $_POST['type'] ?? '';
$exam_year = filter_input(INPUT_POST, 'exam_year', FILTER_VALIDATE_INT) ?: null;

if (!$user_id || !in_array($type, ['entries', 'processed', 'actions'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request parameters']);
    exit;
}

$data = [];
try {
    if ($type === 'entries') {
        $sql = "SELECT m.id, m.candidate_id, s.name as subject, m.mark, m.submitted_at 
                FROM marks m 
                JOIN subjects s ON m.subject_id = s.id 
                WHERE m.submitted_by = ?";
        if ($exam_year) {
            $sql .= " AND YEAR(m.submitted_at) = ?";
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement for entries');
        }
        if ($exam_year) {
            $stmt->bind_param("ii", $user_id, $exam_year);
        } else {
            $stmt->bind_param("i", $user_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    } elseif ($type === 'processed') {
        $sql = "SELECT r.id, r.candidate_id, s.name as subject, r.mark, r.score, r.processed_at 
                FROM results r 
                JOIN subjects s ON r.subject_id = s.id 
                WHERE r.processed_by = ?";
        if ($exam_year) {
            $sql .= " AND YEAR(r.processed_at) = ?";
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement for processed results');
        }
        if ($exam_year) {
            $stmt->bind_param("ii", $user_id, $exam_year);
        } else {
            $stmt->bind_param("i", $user_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    } elseif ($type === 'actions') {
        $sql = "SELECT action, details, created_at 
                FROM audit_logs 
                WHERE user_id = ?";
        if ($exam_year) {
            $sql .= " AND YEAR(created_at) = ?";
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement for actions');
        }
        if ($exam_year) {
            $stmt->bind_param("ii", $user_id, $exam_year);
        } else {
            $stmt->bind_param("i", $user_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>