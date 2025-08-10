<?php
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    error_log("Unauthorized restore attempt, User ID: " . ($_SESSION['user_id'] ?? 'Not set'), 3, __DIR__ . '../logs/setup_errors.log');
    die("Unauthorized access.");
}

$backup_dir = __DIR__ . '/../backup/';
$log_file = __DIR__ . '/../logs/setup_errors.log';
$mysql_bin = 'C:\\xampp\\mysql\\bin\\mysql.exe';
$db_user = 'root';
$db_pass = 'root_password';
$db_name = 'ludeb';
$encryption_key = 'secure_encryption_key_2025';
$backup_file = $_POST['backup_file'] ?? '';

if (empty($backup_file) || !file_exists($backup_dir . $backup_file)) {
    error_log("Invalid or missing backup file: $backup_file", 3, $log_file);
    die("Invalid backup file.");
}

// Decrypt backup
$encrypted_content = file_get_contents($backup_dir . $backup_file);
$decrypted_content = openssl_decrypt(
    $encrypted_content,
    'AES-256-CBC',
    $encryption_key,
    0,
    substr(hash('sha256', $encryption_key), 0, 16)
);

if ($decrypted_content === false) {
    error_log("Decryption failed for backup: $backup_file", 3, $log_file);
    die("Decryption failed.");
}

$temp_file = $backup_dir . 'temp_restore.sql';
$temp_gz = $temp_file . '.gz';
file_put_contents($temp_gz, $decrypted_content);

// Decompress
$command = "gzip -d \"$temp_gz\"";
exec($command, $output, $return_var);
if ($return_var !== 0) {
    error_log("Decompression failed: " . implode("\n", $output), 3, $log_file);
    unlink($temp_gz);
    die("Decompression failed.");
}

// Restore database
$command = "\"$mysql_bin\" --user=$db_user --password=$db_pass $db_name < \"$temp_file\"";
exec($command, $output, $return_var);
if ($return_var !== 0) {
    error_log("Restore failed: " . implode("\n", $output), 3, $log_file);
    unlink($temp_file);
    die("Restore failed.");
}

unlink($temp_file);
$conn->query("INSERT INTO audit_logs (action, user_id, details, created_at) 
              VALUES ('Database Restore', {$_SESSION['user_id']}, 'Restored database from: $backup_file', CURRENT_TIMESTAMP)");

echo "Database restored successfully from: $backup_file\n";
?>