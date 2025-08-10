<?php
// Database connection
include 'connections/db.connection.php'; // Adjust as necessary

// Handle search request
if (isset($_GET['term'])) {
    $term = $conn->real_escape_string($_GET['term']);
    
    // Search candidates by name or index number
    $query = "SELECT id, Candidate_Name, IndexNo 
              FROM candidates 
              WHERE Candidate_Name LIKE '%$term%' OR IndexNo LIKE '%$term%'";
    $result = $conn->query($query);
    
    $candidates = [];
    while ($row = $result->fetch_assoc()) {
        $candidates[] = [
            'id' => $row['id'],
            'name' => $row['Candidate_Name'],
            'index_number' => $row['IndexNo']
        ];
    }
    
    echo json_encode($candidates);
}
?>
