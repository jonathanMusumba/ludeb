<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LUDEB";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("DB Connection Error: " . $e->getMessage(), 3, '../setup_errors.log');
    header("Location: setup.html");
    exit();
}
?>