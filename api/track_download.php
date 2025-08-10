<?php
// ===== API/TRACK_DOWNLOAD.PHP =====

require_once '../db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$resource_id = isset($input['resource_id']) ? intval($input['resource_id']) : 0;
$action = isset($input['action']) ? $input['action'] : 'download';

if (!$resource_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Resource ID is required']);
    exit;
}

// Validate action
$valid_actions = ['click', 'download'];
if (!in_array($action, $valid_actions)) {
    $action = 'download';
}

try {
    // Get client IP
    $ip_address = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 
                  $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
                  $_SERVER['HTTP_X_REAL_IP'] ?? 
                  $_SERVER['REMOTE_ADDR'] ?? 
                  'unknown';
    
    // If forwarded IP contains multiple IPs, get the first one
    if (strpos($ip_address, ',') !== false) {
        $ip_address = trim(explode(',', $ip_address)[0]);
    }
    
    // Verify resource exists and user has access (for download action)
    if ($action === 'download') {
        $stmt = $conn->prepare("
            SELECT r.type,
            CASE 
                WHEN r.type = 'free' THEN 1
                WHEN ura.id IS NOT NULL THEN 1 
                ELSE 0 
            END as has_access
            FROM resources r 
            LEFT JOIN user_resource_access ura ON r.id = ura.resource_id AND ura.user_id = ?
            WHERE r.id = ? AND r.approved = 1
        ");
        
        $stmt->bind_param("ii", $_SESSION['user_id'], $resource_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result || !$result['has_access']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
    }
    
    // Prevent duplicate logs within short time frame (30 seconds)
    $stmt = $conn->prepare("
        SELECT id FROM download_logs 
        WHERE user_id = ? AND resource_id = ? AND action = ? 
        AND downloaded_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
    ");
    $stmt->bind_param("iis", $_SESSION['user_id'], $resource_id, $action);
    $stmt->execute();
    $recent_log = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($recent_log) {
        // Don't log duplicate within 30 seconds, but still return success
        echo json_encode(['success' => true, 'message' => 'Already logged recently']);
        exit;
    }
    
    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO download_logs (user_id, resource_id, action, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiss", $_SESSION['user_id'], $resource_id, $action, $ip_address);
    
    if ($stmt->execute()) {
        $log_id = $conn->insert_id;
        $stmt->close();
        
        // Update download count in user_resource_access for premium resources
        if ($action === 'download') {
            $stmt = $conn->prepare("
                UPDATE user_resource_access 
                SET download_count = download_count + 1 
                WHERE user_id = ? AND resource_id = ?
            ");
            $stmt->bind_param("ii", $_SESSION['user_id'], $resource_id);
            $stmt->execute();
            $stmt->close();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => ucfirst($action) . ' logged successfully',
            'log_id' => $log_id
        ]);
        
    } else {
        throw new Exception('Failed to log ' . $action);
    }
    
} catch (Exception $e) {
    error_log("Track download API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to log action']);
}
?>