<?php
// ===== API/RESOURCE_DETAILS.PHP =====

require_once '../db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$resource_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$resource_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Resource ID is required']);
    exit;
}

try {
    // Get resource details
    $stmt = $conn->prepare("
        SELECT r.*, u.username as uploader_name,
        CASE 
            WHEN ura.id IS NOT NULL THEN 1 
            ELSE 0 
        END as has_access,
        pt.status as payment_status,
        COALESCE(dl.download_count, 0) as download_count,
        ROUND(r.file_size / 1024, 2) as file_size_kb
        FROM resources r 
        LEFT JOIN system_users u ON r.uploader_id = u.id
        LEFT JOIN user_resource_access ura ON r.id = ura.resource_id AND ura.user_id = ?
        LEFT JOIN payment_transactions pt ON r.id = pt.resource_id AND pt.user_id = ? AND pt.status IN ('verified', 'pending')
        LEFT JOIN (
            SELECT resource_id, COUNT(*) as download_count 
            FROM download_logs 
            WHERE action = 'download'
            GROUP BY resource_id
        ) dl ON r.id = dl.resource_id
        WHERE r.id = ? AND r.approved = 1
    ");
    
    $stmt->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $resource_id);
    $stmt->execute();
    $resource = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$resource) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Resource not found']);
        exit;
    }
    
    // Format file size
    if ($resource['file_size_kb']) {
        if ($resource['file_size_kb'] > 1024) {
            $resource['file_size'] = round($resource['file_size_kb'] / 1024, 2) . ' MB';
        } else {
            $resource['file_size'] = $resource['file_size_kb'] . ' KB';
        }
    } else {
        $resource['file_size'] = 'Unknown';
    }
    
    // Remove sensitive data
    unset($resource['file_path']);
    unset($resource['file_size_kb']);
    
    echo json_encode([
        'success' => true,
        'resource' => $resource,
        'has_access' => (bool)$resource['has_access'],
        'payment_status' => $resource['payment_status']
    ]);
    
} catch (Exception $e) {
    error_log("Resource details API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>