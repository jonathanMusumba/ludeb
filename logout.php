<?php
session_start();

try {
    require_once 'connections/db_connect.php';
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Unknown';
        
        $stmt = $conn->prepare("CALL log_action('Logout', ?, ?)");
        if ($stmt) {
            $logMessage = "User {$username} logged out";
            $stmt->bind_param("is", $userId, $logMessage);
            $stmt->execute();
            $stmt->close();
        }
    }
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage(), 3, 'setup_errors.log');
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

session_unset();
session_destroy();
header("Location: login.php");
exit();
?>