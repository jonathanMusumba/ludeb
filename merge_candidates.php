<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['cid1']) && isset($_GET['cid2'])) {
    $cid1 = $_GET['cid1'];
    $cid2 = $_GET['cid2'];

    // Transfer marks from cid2 to cid1
    $update_marks = "UPDATE marks SET candidate_id = ? WHERE candidate_id = ?";
    $stmt = $conn->prepare($update_marks);
    $stmt->bind_param("ii", $cid1, $cid2);
    $stmt->execute();

    // Delete the duplicate candidate record
    $delete_candidate_sql = "DELETE FROM Candidates WHERE id = ?";
    $stmt = $conn->prepare($delete_candidate_sql);
    $stmt->bind_param("i", $cid2);
    $stmt->execute();

    header("Location: view_similar_candidates.php?merge_success=1");
    exit();
} else {
    echo "Invalid request.";
}

$conn->close();
?>
