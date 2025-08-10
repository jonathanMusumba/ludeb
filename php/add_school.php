<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $centerNo = $_POST['centerNo'];
    $schoolName = $_POST['schoolName'];
    $subCounty = $_POST['subCounty'];
    $schoolType = $_POST['schoolType'];

    $stmt = $conn->prepare("INSERT INTO Schools (CenterNo, School_Name, Sub_county, School_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssii", $centerNo, $schoolName, $subCounty, $schoolType);

    if ($stmt->execute()) {
        echo "New school added successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
