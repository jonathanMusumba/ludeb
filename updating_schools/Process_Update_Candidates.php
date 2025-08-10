<?php
require 'vendor/autoload.php'; // Ensure Composer's autoload file is required

use PhpOffice\PhpSpreadsheet\IOFactory;

// Database configuration
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

$target_dir = "uploads/";

// Ensure the target directory exists
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// Process each uploaded file
foreach ($_FILES['files']['name'] as $key => $name) {
    $file_path = $target_dir . basename($name);
    $fileType = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    if ($fileType === 'xlsx' || $fileType === 'xls') {
        // Move the uploaded file to the target directory
        if (move_uploaded_file($_FILES['files']['tmp_name'][$key], $file_path)) {
            // Load the Excel file
            $spreadsheet = IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Skip the header row
            foreach (array_slice($rows, 1) as $row) {
                $index_no = $row[0]; // Assuming IndexNo is in the first column
                $candidate_name = $row[1]; // Assuming Candidate_Name is in the second column
                $gender = $row[2]; // Assuming Gender is in the third column

                // Update the database
                $stmt = $conn->prepare("UPDATE candidates SET Gender = ? WHERE IndexNo = ? AND Candidate_Name = ?");
                $stmt->bind_param('sss', $gender, $index_no, $candidate_name);
                $stmt->execute();
                $stmt->close();
            }

            echo "Processed file: " . htmlspecialchars($name) . "<br>";
        } else {
            echo "Failed to move file: " . htmlspecialchars($name) . "<br>";
        }
    } else {
        echo "File type not supported: " . htmlspecialchars($name) . "<br>";
    }
}

$conn->close();
?>
