<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:\xampp\htdocs\ludeb\logs\backup_errors.log');

try {
    // Database configuration
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = ''; // Update with secure password if set
    $db_name = 'ludeb';
    $backup_dir = 'C:\xampp\htdocs\ludeb\backups';
    $mysqldump_path = 'C:\xampp\mysql\bin\mysqldump.exe';
    $zip_path = 'C:\Program Files\7-Zip\7z.exe'; // Ensure 7-Zip is installed

    // Ensure backup directory exists
    if (!is_dir($backup_dir)) {
        if (!mkdir($backup_dir, 0755, true)) {
            throw new Exception('Failed to create backup directory: ' . $backup_dir);
        }
    }

    // Generate backup filename with timestamp
    $timestamp = date('Ymd_His');
    $backup_file = "$backup_dir/{$db_name}_backup_$timestamp.sql";
    $compressed_file = "$backup_file.gz";

    // Build mysqldump command
    $command = "\"$mysqldump_path\" --host=$db_host --user=$db_user";
    if ($db_pass) {
        $command .= " --password=$db_pass";
    }
    $command .= " --databases $db_name --single-transaction --quick --lock-tables=false > \"$backup_file\" 2>&1";

    // Execute backup
    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        throw new Exception('mysqldump failed: ' . implode("\n", $output));
    }

    // Verify backup file exists and is not empty
    if (!file_exists($backup_file) || filesize($backup_file) == 0) {
        throw new Exception('Backup file was not created or is empty: ' . $backup_file);
    }

    // Compress backup using 7-Zip
    if (file_exists($zip_path)) {
        $zip_command = "\"$zip_path\" a -tgzip \"$compressed_file\" \"$backup_file\" 2>&1";
        exec($zip_command, $zip_output, $zip_return_var);
        if ($zip_return_var !== 0) {
            throw new Exception('Compression failed: ' . implode("\n", $zip_output));
        }
        // Delete uncompressed file
        unlink($backup_file);
        $backup_file = $compressed_file;
    } else {
        error_log('Warning: 7-Zip not found, backup not compressed', 3, 'C:\xampp\htdocs\ludeb\logs\backup_errors.log');
    }

    // Retention policy: Keep last 168 hourly backups (7 days)
    $files = glob("$backup_dir/{$db_name}_backup_*.sql*");
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    if (count($files) > 168) {
        foreach (array_slice($files, 168) as $old_file) {
            unlink($old_file);
        }
    }

    // Log success
    $log_message = "Backup created successfully: $backup_file";
    error_log($log_message, 3, 'C:\xampp\htdocs\ludeb\logs\backup_errors.log');

    // Connect to database for audit logging
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
    $action = 'Database Backup';
    $user_id = null; // System-level action
    $stmt->bind_param("sis", $action, $user_id, $log_message);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    echo json_encode(['status' => 'success', 'message' => $log_message]);
} catch (Exception $e) {
    $error_message = 'Backup failed: ' . $e->getMessage();
    error_log($error_message, 3, 'C:\xampp\htdocs\ludeb\logs\backup_errors.log');

    // Log error to audit_logs if database is accessible
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        $action = 'Database Backup Error';
        $user_id = null;
        $stmt->bind_param("sis", $action, $user_id, $error_message);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }

    echo json_encode(['status' => 'error', 'message' => $error_message]);
}
?>