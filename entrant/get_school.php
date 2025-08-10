<?php
session_start();
require_once 'db_connect.php';

// Restrict to authenticated users
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Get the search term from the request
$term = isset($_GET['term']) ? $conn->real_escape_string($_GET['term']) : '';

// Query to fetch matching center_no and school_name with prepared statement
$stmt = $conn->prepare("SELECT center_no, school_name FROM schools WHERE center_no LIKE ? OR school_name LIKE ? LIMIT 10");
$search_term = "%$term%";
$stmt->bind_param("ss", $search_term, $search_term);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];

// Fetch the results
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = [
            'value' => $row['center_no'] . ' - ' . $row['school_name'], // Display format
            'label' => $row['center_no'], // Search term to match
            'id' => $row['center_no'] // Include center_no as id for form submission
        ];
    }
}

// Return the results as JSON
header('Content-Type: application/json');
echo json_encode($suggestions);

// Close the statement
$stmt->close();
$conn->close();
?>