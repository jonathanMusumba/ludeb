<?php
// Database connection details
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

// Get the search term from the request
$term = $_GET['term'] ?? '';
$term = $conn->real_escape_string($term);

// Query to fetch schools based on the search term
$sql = "SELECT id, school_Name FROM schools WHERE school_Name LIKE '%$term%'";
$result = $conn->query($sql);

// Prepare an array to store the search results
$schools = [];
while ($row = $result->fetch_assoc()) {
    $schools[] = [
        'value' => $row['id'],
        'label' => $row['school_Name']
    ];
}

// Return the results as a JSON array
echo json_encode($schools);

// Close connection
$conn->close();
?>
