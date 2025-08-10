<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'System Admin') {
    header('Location: login.php');
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userId = intval($_GET['id']);

$deleteQuery = "DELETE FROM system_users WHERE id = $userId";
if ($conn->query($deleteQuery) === TRUE) {
    header('Location: manage_users.php');
    exit();
} else {
    echo "Error deleting record: " . $conn->error;
}

$conn->close();
?>
