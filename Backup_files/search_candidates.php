<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['term']) && isset($_GET['school_id'])) {
    $term = $conn->real_escape_string($_GET['term']);
    $school_id = intval($_GET['school_id']);

    $query = "SELECT id, Candidate_Name, IndexNo FROM candidates 
              WHERE school_id = $school_id AND Candidate_Name LIKE '%$term%' ORDER BY Candidate_Name ASC";
    
    $result = $conn->query($query);

    $candidates = [];
    while ($row = $result->fetch_assoc()) {
        $candidates[] = [
            'label' => $row['Candidate_Name'],
            'value' => $row['Candidate_Name'],
            'id' => $row['id']
        ];
    }
    
    echo json_encode($candidates);
}

$conn->close();
?>
