<?php
// Database configuration
$servername = "localhost";
$username = "root"; // Use your database username
$password = ""; // Use your database password
$dbname = "ludeb"; // Use your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$center_no = $_GET['center_no'];

// Load the data
$result = $conn->query("SELECT * FROM results WHERE center_no = '$center_no'");

require 'vendor/autoload.php';

$mpdf = new \Mpdf\Mpdf();

$html = '<h1>Results</h1>';
$html .= '<table border="1" style="width:100%; border-collapse:collapse;">';
$html .= '<tr><th>Column1</th><th>Column2</th><th>Column3</th></tr>';

while ($row = $result->fetch_assoc()) {
    $html .= '<tr>';
    $html .= '<td>' . $row['column1'] . '</td>';
    $html .= '<td>' . $row['column2'] . '</td>';
    $html .= '<td>' . $row['column3'] . '</td>';
    $html .= '</tr>';
}

$html .= '</table>';

$mpdf->WriteHTML($html);
$mpdf->Output('results.pdf', 'D');

$conn->close();
?>
