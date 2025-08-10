<?php
// Database credentials
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
$term = isset($_GET['term']) ? $conn->real_escape_string($_GET['term']) : '';

// Query to fetch matching CenterNo and School Name
$sql = "SELECT CenterNo, school_Name 
        FROM schools 
        WHERE CenterNo LIKE '%$term%' 
        OR school_Name LIKE '%$term%' 
        LIMIT 10"; // Limit the number of suggestions to 10

$result = $conn->query($sql);

$suggestions = [];

// Fetch the results
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = [
            'value' => $row['CenterNo'] . ' - ' . $row['school_Name'], // Display format
            'label' => $row['CenterNo'], // Search term to match
        ];
    }
}

// Return the results as JSON
header('Content-Type: application/json');
echo json_encode($suggestions);

// Close the database connection
$conn->close();
?>
