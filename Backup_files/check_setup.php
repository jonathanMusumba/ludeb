<?php
// Database credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LUDEB";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        header("Location: setup.html");
        exit();
    }

    // Check if settings table has data
    $sql = "SELECT COUNT(*) as count FROM settings";
    $result = $conn->query($sql);
    if ($result === false) {
        header("Location: setup.html");
        exit();
    }
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        // Setup complete, redirect to homepage
        header("Location: index.php");
    } else {
        // Setup incomplete, redirect to setup page
        header("Location: setup.html");
    }

    $conn->close();
} catch (mysqli_sql_exception $e) {
    // Redirect to setup if database doesn't exist or other connection issues
    header("Location: setup.html");
    exit();
}
?>