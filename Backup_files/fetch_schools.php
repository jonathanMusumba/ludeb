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

$search = $_GET['search'] ?? '';
$response = [];

if ($search) {
    $stmt = $conn->prepare("SELECT id, School_Name FROM schools WHERE School_Name LIKE ? AND status = 'Active'");
    $search_param = "%$search%";
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $response[] = $row;
    }
}

echo json_encode($response);
?>
