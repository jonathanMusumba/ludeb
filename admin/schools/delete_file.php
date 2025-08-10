<?php
ob_start();
require_once '../db_connect.php';

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Debug session
error_log("delete_file.php: Starting, User ID: " . ($_SESSION['user_id'] ?? 'Not set') . ", Role: " . ($_SESSION['role'] ?? 'Not set'), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    error_log("Unauthorized access attempt", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed: Received " . ($_POST['csrf_token'] ?? 'none') . ", Expected " . ($_SESSION['csrf_token'] ?? 'none'), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit();
}

// Get upload ID
$upload_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($upload_id <= 0) {
    error_log("Invalid upload ID: $upload_id", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid upload ID']);
    exit();
}

// Fetch file details
$stmt = $conn->prepare("SELECT school_id, filename FROM uploads WHERE id = ?");
$stmt->bind_param("i", $upload_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $school_id = $row['school_id'];
    $filename = $row['filename'];
} else {
    error_log("Upload ID $upload_id not found", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit();
}
$stmt->close();

// Delete file from filesystem
$current_year = date('Y');
$file_path = "../../Uploads/$current_year/$filename";
if (file_exists($file_path)) {
    if (!unlink($file_path)) {
        error_log("Failed to delete file: $file_path", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to delete file from server']);
        exit();
    }
} else {
    error_log("File not found on server: $file_path", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
}

// Delete record from uploads table
$stmt = $conn->prepare("DELETE FROM uploads WHERE id = ?");
$stmt->bind_param("i", $upload_id);
if ($stmt->execute()) {
    $user_id = $_SESSION['user_id'];
    $escaped_filename = mysqli_real_escape_string($conn, $filename);
    $conn->query("CALL log_action('Delete File', $user_id, 'Deleted file: $escaped_filename (ID: $upload_id) for school ID $school_id')");
    error_log("File deleted successfully: $filename (ID: $upload_id)", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
} else {
    error_log("Failed to delete upload record ID $upload_id: " . $stmt->error, 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to delete file record: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
?>