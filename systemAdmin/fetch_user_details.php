<?php
session_start();
require_once '../../../php/db_connect.php';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
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

if (!$user_id || !in_array($type, ['entries', 'actions'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$data = [];
if ($type === 'entries') {
    $sql = "SELECT id, student_id, subject, marks, created_at FROM marks WHERE submitted_by = ?";
    if ($exam_year) {
        $sql .= " AND YEAR(created_at) = ?";
    }
    $stmt = $conn->prepare($sql);
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
    $sql = "SELECT action, description, created_at FROM audit_logs WHERE id = ?";
    if ($exam_year) {
        $sql .= " AND YEAR(created_at) = ?";
    }
    $stmt = $conn->prepare($sql);
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
$conn->close();
?>