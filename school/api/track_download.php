<?php
require_once '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$resource_id = intval($input['resource_id'] ?? 0);

if ($resource_id) {
    try {
        $stmt = $conn->prepare("INSERT INTO download_logs (user_id, resource_id, action, downloaded_at) VALUES (?, ?, 'click', NOW())");
        $stmt->bind_param("ii", $_SESSION['user_id'], $resource_id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>