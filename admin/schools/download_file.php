<?php
ob_start();
require_once '../db_connect.php';

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Debug session
error_log("download_file.php: Starting, User ID: " . ($_SESSION['user_id'] ?? 'Not set') . ", Role: " . ($_SESSION['role'] ?? 'Not set'), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    error_log("Unauthorized access attempt", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header("Location: $root_url" . "login.php");
    exit();
}

// Get upload ID
$upload_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($upload_id <= 0) {
    error_log("Invalid upload ID: $upload_id", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header("Location: schools/manage_schools.php?error=" . urlencode("Invalid upload ID"));
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
    header("Location: schools/manage_schools.php?error=" . urlencode("File not found"));
    exit();
}
$stmt->close();

// Get file path
$current_year = date('Y');
$file_path = "../../Uploads/$current_year/$filename";
if (!file_exists($file_path)) {
    error_log("File not found on server: $file_path", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header("Location: schools/manage_schools.php?error=" . urlencode("File not found on server"));
    exit();
}

// Log download action
$user_id = $_SESSION['user_id'];
$escaped_filename = mysqli_real_escape_string($conn, $filename);
$conn->query("CALL log_action('Download File', $user_id, 'Downloaded file: $escaped_filename (ID: $upload_id) for school ID $school_id')");

// Output file
ob_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');
header('Pragma: public');
readfile($file_path);
$conn->close();
exit();
?>