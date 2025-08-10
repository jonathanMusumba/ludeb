<?php
session_start();

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

// Fetch data from POST request
$candidate_id = isset($_POST['candidate_id']) ? intval($_POST['candidate_id']) : 0;
$mark = isset($_POST['mark']) ? intval($_POST['mark']) : 0;
$subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
$school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : 0;

// Validate inputs
if ($candidate_id <= 0 || $mark < 0 || $subject_id <= 0 || $school_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit();
}

// Get the current user ID from the session
$submitted_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

// Prepare and execute SQL query
$stmt = $conn->prepare("
    INSERT INTO marks (candidate_id, subject_id, mark, school_id, submitted_by, submitted_at)
    VALUES (?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE mark = VALUES(mark), updated_at = NOW(), edited_by = VALUES(submitted_by)
");
$stmt->bind_param("iiii", $candidate_id, $subject_id, $mark, $school_id, $submitted_by);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
