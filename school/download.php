<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// Debug: Log session and request
error_log("Download.php - Session check: user_id=" . ($_SESSION['user_id'] ?? 'NOT_SET') . ", role=" . ($_SESSION['role'] ?? 'NOT_SET') . ", resource_id=" . ($_GET['resource_id'] ?? 'NOT_SET'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: ../login.php");
    exit;
}

$resource_id = isset($_GET['resource_id']) ? intval($_GET['resource_id']) : 0;

if (!$resource_id) {
    error_log("Download.php - Invalid resource_id");
    header("Location: resources.php?error=invalid_resource");
    exit;
}

try {
    // Verify database connection
    if ($conn->connect_error) {
        error_log("Download.php - Database connection error: " . $conn->connect_error);
        throw new Exception("Database connection failed");
    }

    // Get resource details and check access
    $query = "
        SELECT r.id, r.file_path, r.title, r.type, r.amount, r.class, r.category, r.approved, 
               u.username as uploader_name,
               CASE 
                   WHEN r.type = 'free' THEN 1
                   WHEN ura.id IS NOT NULL THEN 1 
                   ELSE 0 
               END as has_access,
               COALESCE(ura.download_count, 0) as download_count,
               COALESCE(ura.max_downloads, 0) as max_downloads
        FROM resources r 
        LEFT JOIN system_users u ON r.uploader_id = u.id
        LEFT JOIN user_resource_access ura ON r.id = ura.resource_id AND ura.user_id = ?
        WHERE r.id = ? AND r.approved = 1
    ";
    
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Download.php - Prepare failed: " . $conn->error . " | Query: " . $query);
        throw new Exception("Failed to prepare query");
    }
    
    $stmt->bind_param("ii", $_SESSION['user_id'], $resource_id);
    if (!$stmt->execute()) {
        error_log("Download.php - Execute failed: " . $stmt->error);
        throw new Exception("Query execution failed");
    }
    
    $resource = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$resource) {
        error_log("Download.php - Resource not found or not approved: resource_id=" . $resource_id);
        header("Location: resources.php?error=resource_not_found");
        exit;
    }
    
    // Check if user has access
    if (!$resource['has_access']) {
        error_log("Download.php - Access denied: user_id=" . $_SESSION['user_id'] . ", resource_id=" . $resource_id . ", type=" . $resource['type']);
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
            error_log("Download.php - Download limit exceeded: user_id=" . $_SESSION['user_id'] . ", resource_id=" . $resource_id);
            header("Location: resources.php?error=download_limit_exceeded");
            exit;
        }
    }
    
    // Check if uploads directory exists
    $upload_dir = '../uploads/resources/';
    $upload_dir = '../uploads/resources/';
    if (!is_dir($upload_dir)) {
        error_log("Download.php - Upload directory not found: " . realpath('../uploads/resources'));
        header("Location: resources.php?error=upload_directory_missing");
        exit;
    }
    
    // Check if file exists and is readable
    $file_path = preg_replace('/[^a-zA-Z0-9._-]/', '', $resource['file_path']); // Sanitize file path
    $full_path = $upload_dir . $file_path;
    
    // Log file path details for debugging
    error_log("Download.php - Attempting to access file: full_path=" . realpath($full_path) . ", exists=" . (file_exists($full_path) ? 'Yes' : 'No') . ", readable=" . (is_readable($full_path) ? 'Yes' : 'No'));
    
    if (!file_exists($full_path) || !is_readable($full_path)) {
        error_log("Download.php - File not found or not readable: " . $full_path . " (file_path from DB: " . $resource['file_path'] . ")");
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
    
    if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
        $ip_address = 'unknown';
    }
    
    // Begin transaction for database operations
    $conn->begin_transaction();
    
    try {
        // Log download attempt
        $stmt = $conn->prepare("
            INSERT INTO download_logs (user_id, resource_id, action, ip_address, created_at) 
            VALUES (?, ?, 'download', ?, NOW())
        ");
        if ($stmt === false) {
            error_log("Download.php - Download log prepare failed: " . $conn->error);
            throw new Exception("Failed to prepare download log query");
        }
        $stmt->bind_param("iis", $_SESSION['user_id'], $resource_id, $ip_address);
        $stmt->execute();
        $stmt->close();
        
        // Update download count for premium resources
        if ($resource['type'] === 'premium' && $resource['has_access']) {
            $stmt = $conn->prepare("
                UPDATE user_resource_access 
                SET download_count = download_count + 1, last_download_at = NOW()
                WHERE user_id = ? AND resource_id = ?
            ");
            if ($stmt === false) {
                error_log("Download.php - Update access prepare failed: " . $conn->error);
                throw new Exception("Failed to prepare update access query");
            }
            $stmt->bind_param("ii", $_SESSION['user_id'], $resource_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Log completion in audit log
        $log_message = "Downloaded resource: " . $resource['title'] . " (ID: " . $resource_id . ")";
        $stmt = $conn->prepare("CALL log_action('Resource Downloaded', ?, ?)");
        if ($stmt === false) {
            error_log("Download.php - Log action prepare failed: " . $conn->error);
            throw new Exception("Failed to prepare log action query");
        }
        $stmt->bind_param("is", $_SESSION['user_id'], $log_message);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Download.php - Transaction error: " . $e->getMessage());
        throw $e;
    }
    
    // Prepare file for download
    $file_size = filesize($full_path);
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $file_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $resource['title'] . '.' . $file_extension);
    
    // Content types
    $content_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'rtf' => 'application/rtf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'avi' => 'video/x-msvideo'
    ];
    
    $content_type = $content_types[$file_extension] ?? 'application/octet-stream';
    
    // Clear output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Prevent script timeout
    set_time_limit(0);
    
    // Set headers
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . addslashes($file_name) . '"');
    header('Content-Length: ' . $file_size);
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Stream file
    $handle = fopen($full_path, 'rb');
    if ($handle === false) {
        error_log("Download.php - Failed to open file: " . $full_path);
        header("Location: resources.php?error=file_read_error");
        exit;
    }
    
    $chunk_size = 8192; // 8KB chunks
    while (!feof($handle) && !connection_aborted()) {
        $chunk = fread($handle, $chunk_size);
        if ($chunk === false) {
            error_log("Download.php - Failed to read chunk from file: " . $full_path);
            break;
        }
        echo $chunk;
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    fclose($handle);
    exit;
    
} catch (Exception $e) {
    error_log("Download.php - Error for resource {$resource_id}, user {$_SESSION['user_id']}: " . $e->getMessage());
    header("Location: resources.php?error=download_failed");
    exit;
}
?>