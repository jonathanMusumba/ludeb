<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id']) && isset($_GET['mark_id'])) {
    $candidate_id = $_GET['id'];
    $mark_id = $_GET['mark_id'];

    // Delete the mark
    $delete_mark_sql = "DELETE FROM marks WHERE id = ?";
    $stmt = $conn->prepare($delete_mark_sql);
    $stmt->bind_param("i", $mark_id);
    $stmt->execute();

    // Delete the candidate
    $delete_candidate_sql = "DELETE FROM Candidates WHERE id = ?";
    $stmt = $conn->prepare($delete_candidate_sql);
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();

    header("Location: view_duplicates.php?success=1");
    exit();
} else {
    echo "Invalid request.";
}

$conn->close();
?>
