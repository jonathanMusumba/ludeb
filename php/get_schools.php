<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb"; // Replace with your actual database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

// Build the SQL query to fetch all records
$sql = "SELECT id, CenterNo, School_Name, Sub_county, School_type FROM schools";

// Execute the query
$result = $conn->query($sql);

// Check if query execution was successful
if (!$result) {
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    exit();
}

// Prepare the result data
$schools = [];
while ($row = $result->fetch_assoc()) {
    $schools[] = $row;
}

// Send JSON response
$response = [
    'schools' => $schools
];
echo json_encode($response);

// Close the connection
$conn->close();
?>