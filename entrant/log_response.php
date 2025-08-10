<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Data Entrant', 'System Admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$response = $_POST['response'] ?? '';
$candidate_id = isset($_POST['candidate_id']) ? intval($_POST['candidate_id']) : 0;
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("CALL log_action(?, ?, ?)");
$action = 'AJAX Response Log';
$details = "Raw response for candidate_id: $candidate_id: " . $response;
$stmt->bind_param("sis", $action, $user_id, $details);
$stmt->execute();
$stmt->close();

header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
exit();
?>