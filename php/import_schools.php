<?php
require 'vendor/autoload.php'; // Include the Composer autoload file

use PhpOffice\PhpSpreadsheet\IOFactory;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb"; // Replace with your actual database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Read data from the Excel file
$inputFileName = 'C:/Users/Nabwire Jane/Documents/schools.xlsx'; // Replace with your actual file path
$spreadsheet = IOFactory::load($inputFileName);
$worksheet = $spreadsheet->getActiveSheet();
$data = $worksheet->toArray(null, true, true, true);

// Prepare SQL for inserting or updating records
$sql = "INSERT INTO schools (CenterNo, School_Name) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE School_Name = VALUES(School_Name)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// Process each row of the data
foreach ($data as $index => $row) {
    if ($index === 1) { // Skip header row
        continue;
    }

    $centerNo = trim($row['A']); // CenterNo is in column 'A'
    $schoolName = trim($row['B']); // School_Name is in column 'B'

    // Check if the data is valid
    if (empty($centerNo) || empty($schoolName)) {
        echo "Skipping invalid row: CenterNo or School_Name is missing.<br>";
        continue;
    }

    // Bind parameters and execute statement
    $stmt->bind_param('ss', $centerNo, $schoolName);
    if (!$stmt->execute()) {
        echo "Failed to insert or update CenterNo $centerNo: " . $stmt->error . "<br>";
    } else {
        // Output the number of affected rows
        if ($stmt->affected_rows > 0) {
            if ($stmt->affected_rows === 1) {
                echo "Inserted or Updated CenterNo $centerNo<br>"; // Debugging output
            } else {
                echo "No change for CenterNo $centerNo (possibly same name)<br>"; // Debugging output
            }
        } else {
            echo "No rows affected for CenterNo $centerNo<br>"; // Debugging output
        }
    }
}

// Close statement and connection
$stmt->close();
$conn->close();

echo "Data import completed.";
?>
