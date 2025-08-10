<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the current year
$current_year = date("Y");

// Prepare the SQL statement
$stmt = $conn->prepare("INSERT INTO exam_years (year) VALUES (?)");
$stmt->bind_param("i", $current_year);

// Execute the statement
if ($stmt->execute()) {
    echo "Current year $current_year has been inserted into the exam_years table.";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>

