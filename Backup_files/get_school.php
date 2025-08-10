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

// Get the term to search
$term = isset($_GET['term']) ? $_GET['term'] : '';

// Prepare the SQL query to fetch schools matching the term
$query = $conn->prepare("SELECT id, school_Name FROM schools WHERE school_Name LIKE ?");
$term = "%" . $term . "%";
$query->bind_param("s", $term);
$query->execute();
$result = $query->get_result();

$schools = [];
while ($row = $result->fetch_assoc()) {
    $schools[] = [
        'id' => $row['id'],
        'label' => $row['school_Name'],
        'value' => $row['school_Name']
    ];
}

// Return the list of schools as JSON
echo json_encode($schools);
?>
