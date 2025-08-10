<?php
require_once '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$resource_id = intval($_GET['id']);

$sql = "SELECT r.*, u.username as uploader_name,
        CASE 
            WHEN ura.id IS NOT NULL THEN 1 
            ELSE 0 
        END as has_access,
        pt.status as payment_status,
        ROUND(LENGTH(r.file_path)/1024/1024, 2) as file_size_mb
        FROM resources r 
        LEFT JOIN system_users u ON r.uploader_id = u.id
        LEFT JOIN user_resource_access ura ON r.id = ura.resource_id AND ura.user_id = ?
        LEFT JOIN payment_transactions pt ON r.id = pt.resource_id AND pt.user_id = ? AND pt.status IN ('verified', 'pending')
        WHERE r.id = ? AND r.approved = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $resource_id);
$stmt->execute();
$resource = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($resource) {
    $resource['file_size'] = $resource['file_size_mb'] ? $resource['file_size_mb'] . ' MB' : 'Unknown';
    echo json_encode([
        'success' => true,
        'resource' => $resource,
        'has_access' => $resource['has_access'],
        'payment_status' => $resource['payment_status']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Resource not found']);
}
?>