<?php
session_start();
if (!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
    exit();
}

include 'database_connection.php';

$currentYear = date('Y');
$yearsQuery = "SELECT DISTINCT exam_year FROM exam_years ORDER BY exam_year DESC";
$result = $conn->query($yearsQuery);
$years = [];
while ($row = $result->fetch_assoc()) {
    $years[] = $row['exam_year'];
}

echo json_encode(['currentYear' => $currentYear, 'years' => $years]);
?>
