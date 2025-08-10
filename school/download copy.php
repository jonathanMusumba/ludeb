<?php

require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$resource_id = isset($_GET['resource_id']) ? intval($_GET['resource_id']) : 0;

if (!$resource_id) {
    header("Location: resources.php?error=invalid_resource");
    exit;
}

try {
    // Get resource details and check access
    $stmt = $conn->prepare("
        SELECT r.*, u.username as uploader_name,
        CASE 
            WHEN r.type = 'free' THEN 1
            WHEN ura.id IS NOT NULL THEN 1 
            ELSE 0 
        END as has_access,
        ura.download_count,
        ura.max_downloads
        FROM resources r 
        LEFT JOIN system_users u ON r.uploader_id = u.id
        LEFT JOIN user_resource_access ura ON r.id = ura.resource_id AND ura.user_id = ?
        WHERE r.id = ? AND r.approved = 1
    ");
    
    $stmt->bind_param("ii", $_SESSION['user_id'], $resource_id);
    $stmt->execute();
    $resource = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$resource) {
        header("Location: resources.php?error=resource_not_found");
        exit;
    }
    
    // Check if user has access
    if (!$resource['has_access']) {
        if ($resource['type'] === 'premium') {
            header("Location: payment.php?resource_id=" . $resource_id);
            exit;
        } else {
            header("Location: resources.php?error=access_denied");
            exit;
        }
    }
    
    // Check download limit for premium resources
    if ($resource['type'] === 'premium' && $resource['max_downloads'] > 0) {
        if ($resource['download_count'] >= $resource['max_downloads']) {
            header("Location: resources.php?error=download_limit_exceeded");
            exit;
        }
    }
    
    // Check if file exists
    $file_path = $resource['file_path'];
    $full_path = '../uploads/resources/' . $file_path;
    
    if (!file_exists($full_path)) {
        header("Location: resources.php?error=file_not_found");
        exit;
    }
    
    // Get client IP
    $ip_address = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 
                  $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
                  $_SERVER['HTTP_X_REAL_IP'] ?? 
                  $_SERVER['REMOTE_ADDR'] ?? 
                  'unknown';
    
    if (strpos($ip_address, ',') !== false) {
        $ip_address = trim(explode(',', $ip_address)[0]);
    }
    
    // Log the download
    $stmt = $conn->prepare("
        INSERT INTO download_logs (user_id, resource_id, action, ip_address) 
        VALUES (?, ?, 'download', ?)
    ");
    $stmt->bind_param("iis", $_SESSION['user_id'], $resource_id, $ip_address);
    $stmt->execute();
    $stmt->close();
    
    // Update download count for premium resources
    if ($resource['type'] === 'premium' && $resource['has_access']) {
        $stmt = $conn->prepare("
            UPDATE user_resource_access 
            SET download_count = download_count + 1 
            WHERE user_id = ? AND resource_id = ?
        ");
        $stmt->bind_param("ii", $_SESSION['user_id'], $resource_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Prepare file for download
    $file_size = filesize($full_path);
    $file_name = basename($resource['title']) . '.' . pathinfo($file_path, PATHINFO_EXTENSION);
    
    // Clean filename
    $file_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
    
    // Set appropriate headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    // Disable output buffering
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Read and output file in chunks to handle large files
    $handle = fopen($full_path, 'rb');
    if ($handle === false) {
        header("Location: resources.php?error=file_read_error");
        exit;
    }
    
    while (!feof($handle)) {
        $chunk = fread($handle, 8192); // 8KB chunks
        echo $chunk;
        flush();
        
        // Check if client disconnected
        if (connection_aborted()) {
            break;
        }
    }
    
    fclose($handle);
    
    // Log completion in audit log
    $conn->query("CALL log_action('Resource Downloaded', {$_SESSION['user_id']}, 'Downloaded resource: {$resource['title']} (ID: {$resource_id})')");
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    header("Location: resources.php?error=download_failed");
    exit;
}
?>