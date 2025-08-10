<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL query to get the number of subjects with marks for each school
$sql = "
    SELECT 
        school_id, 
        COUNT(DISTINCT subject_id) AS subject_count 
    FROM 
        marks 
    WHERE 
        mark >= 1 
    GROUP BY 
        school_id
";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $school_id = $row['school_id'];
        $subject_count = $row['subject_count'];
        
        // Determine the ResultsStatus based on the number of subjects with marks
        if ($subject_count >= 4) {
            $status = 'Declared';
        } elseif ($subject_count >= 2) {
            $status = 'Partially Declared';
        } else {
            $status = 'Not Declared';
        }
        
        // Update the ResultsStatus in the schools table
        $update_sql = "
            UPDATE schools 
            SET ResultsStatus = '$status' 
            WHERE id = $school_id
        ";
        
        if (!$conn->query($update_sql)) {
            echo "Error updating ResultsStatus for school ID $school_id: " . $conn->error . "\n";
        }
    }
} else {
    echo "No schools found with marks.\n";
}

// Close the database connection
$conn->close();
?>
