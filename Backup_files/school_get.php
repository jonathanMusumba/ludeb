<?php
// get_school.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$term = $_GET['term'];
$sql = "SELECT id, school_Name FROM schools WHERE school_Name LIKE ?";
$stmt = $conn->prepare($sql);
$likeTerm = "%$term%";
$stmt->bind_param('s', $likeTerm);
$stmt->execute();
$result = $stmt->get_result();

$schools = [];
while ($row = $result->fetch_assoc()) {
    $schools[] = [
        'label' => $row['school_Name'],
        'id' => $row['id']
    ];
}

echo json_encode($schools);

$stmt->close();
$conn->close();
?>
