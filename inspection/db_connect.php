<?php
// Start session FIRST - before any output or other code
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters for proper sharing across subdirectories
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/ludeb/',  // Project root path
        'domain' => '',
        'secure' => false,    // Set to true if using HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

try {
    // Check if mysqli extension is loaded
    if (!extension_loaded('mysqli')) {
        throw new Exception("MySQLi extension is not enabled. Please enable it in php.ini.");
    }

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    // Set charset to handle special characters properly
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage(), 3, __DIR__ . '/logs/setup_errors.log');
    die("Database connection failed. Please try again later.");
}
?>