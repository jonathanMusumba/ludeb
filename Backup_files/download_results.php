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

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set the header
$sheet->setCellValue('A1', 'Column1');
$sheet->setCellValue('B1', 'Column2');
$sheet->setCellValue('C1', 'Column3');

// Fill the data
$rowNum = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue('A' . $rowNum, $row['column1']);
    $sheet->setCellValue('B' . $rowNum, $row['column2']);
    $sheet->setCellValue('C' . $rowNum, $row['column3']);
    $rowNum++;
}

// Output the Excel file
$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="results.xlsx"');
$writer->save('php://output');

$conn->close();
?>
