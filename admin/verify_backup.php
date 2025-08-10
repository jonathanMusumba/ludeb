<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:\xampp\htdocs\ludeb\logs\backup_verify.log');

try {
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $backup_dir = 'C:\xampp\htdocs\ludeb\backups';
    $zip_path = 'C:\Program Files\7-Zip\7z.exe';
    $mysql_path = 'C:\xampp\mysql\bin\mysql.exe';

    // Find the latest backup
    $files = glob("$backup_dir/ludeb_backup_*.sql.gz");
    if (empty($files)) {
        throw new Exception('No backup files found');
    }
    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
    $latest_backup = $files[0];

    // Create test database
    $conn = new mysqli($db_host, $db_user, $db_pass);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    $conn->query("DROP DATABASE IF EXISTS ludeb_test");
    $conn->query("CREATE DATABASE ludeb_test");
    $conn->close();

    // Decompress and restore
    $sql_file = str_replace('.gz', '', $latest_backup);
    $command = "\"$zip_path\" x \"$latest_backup\" -o$backup_dir -y";
    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        throw new Exception('Decompression failed: ' . implode("\n", $output));
    }

    $restore_command = "\"$mysql_path\" --host=$db_host --user=$db_user ludeb_test < \"$sql_file\" 2>&1";
    exec($restore_command, $restore_output, $restore_return_var);
    unlink($sql_file); // Clean up
    if ($restore_return_var !== 0) {
        throw new Exception('Restore failed: ' . implode("\n", $restore_output));
    }

    // Verify data
    $conn = new mysqli($db_host, $db_user, $db_pass, 'ludeb_test');
    $result = $conn->query("SELECT COUNT(*) AS count FROM candidates");
    $row = $result->fetch_assoc();
    $count = $row['count'];
    $conn->query("DROP DATABASE ludeb_test");
    $conn->close();

    $log_message = "Backup verification successful: $latest_backup, $count candidates restored";
    error_log($log_message, 3, 'C:\xampp\htdocs\ludeb\logs\backup_verify.log');

    $conn = new mysqli($db_host, $db_user, $db_pass, 'ludeb');
    $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
    $action = 'Backup Verification';
    $user_id = null;
    $stmt->bind_param("sis", $action, $user_id, $log_message);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    echo json_encode(['status' => 'success', 'message' => $log_message]);
} catch (Exception $e) {
    $error_message = 'Backup verification failed: ' . $e->getMessage();
    error_log($error_message, 3, 'C:\xampp\htdocs\ludeb\logs\backup_verify.log');

    $conn = @new mysqli($db_host, $db_user, $db_pass, 'ludeb');
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        $action = 'Backup Verification Error';
        $user_id = null;
        $stmt->bind_param("sis", $action, $user_id, $error_message);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }

    echo json_encode(['status' => 'error', 'message' => $error_message]);
}
?>