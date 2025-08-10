<?php
session_start();
require_once '../db_connect.php';

// Restrict to authorized roles
$allowed_roles = ['System Admin', 'Examination Administrator'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Backup directory
$backup_dir = __DIR__ . '/../../backups/';
$file_name = isset($_GET['file']) ? basename($_GET['file']) : '';
$file_path = $backup_dir . $file_name;

// Validate file
if (!file_exists($file_path) || !preg_match('/^backup_\d{8}_\d{6}\.sql$/', $file_name)) {
    // Log unauthorized attempt
    $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
    $log_action = 'Download Backup Error';
    $log_description = "Attempted to download invalid or non-existent backup: $file_name";
    $stmt->bind_param("sis", $log_action, $user_id, $log_description);
    $stmt->execute();
    $stmt->close();
    header('HTTP/1.1 404 Not Found');
    exit('Backup file not found.');
}

// Log download action
$stmt = $conn->prepare("CALL log_action(?, ?, ?)");
$log_action = 'Download Backup';
$log_description = "Downloaded backup: $file_name";
$stmt->bind_param("sis", $log_action, $user_id, $log_description);
$stmt->execute();
$stmt->close();

// Serve the file
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit();
?>