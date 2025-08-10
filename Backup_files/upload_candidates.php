<?php
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

// Fetch the current year ID from the exam_years table
$current_year_id = null;
$year_result = $conn->query("SELECT id FROM exam_years WHERE YEAR(CURDATE()) = Exam_year LIMIT 1");
if ($year_result && $row = $year_result->fetch_assoc()) {
    $current_year_id = $row['id'];
    echo "Current year ID: $current_year_id<br>";
} else {
    die("Could not retrieve current year ID.");
}

// Check if file is uploaded
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["file"])) {
    $file = $_FILES["file"]["tmp_name"];

    // Load the Excel file
    require 'vendor/autoload.php';
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();

    // Prepare the SQL statement
    $stmt = $conn->prepare("INSERT INTO candidates (school_id, IndexNo, Candidate_Name, Gender, exam_year) VALUES (?, ?, ?, ?, ?)");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Skip the header row
    $isFirstRow = true;

    // Loop through each row of the sheet
    foreach ($sheet->getRowIterator() as $row) {
        if ($isFirstRow) {
            $isFirstRow = false;
            continue; // Skip header row
        }

        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        $data = [];
        foreach ($cellIterator as $cell) {
            $data[] = $cell->getValue();
        }

        // Ensure there are exactly 3 columns of data
        if (count($data) === 3) {
            $index_no = trim($data[0]); // Remove any leading/trailing spaces
            $candidate_name = trim($data[1]); // Remove any leading/trailing spaces
            $gender = strtoupper(trim($data[2])); // Ensure uppercase and trim spaces

            // Validate gender
            if (!in_array($gender, ['M', 'F'])) {
                echo "Invalid gender value: $gender for IndexNo $index_no<br>";
                continue;
            }

            // Extract CenterNo from IndexNo
            $center_no = explode('/', $index_no)[0]; // Extract part before '/'

            // Fetch school_id based on CenterNo
            $school_result = $conn->prepare("SELECT id FROM schools WHERE CenterNo = ?");
            $school_result->bind_param("s", $center_no);
            $school_result->execute();
            $school_result->store_result();

            if ($school_result->num_rows === 0) {
                echo "No school found for CenterNo $center_no.<br>";
                continue;
            }

            $school_result->bind_result($school_id);
            $school_result->fetch();
            $school_result->close();

            // Bind parameters
            $stmt->bind_param("issii", $school_id, $index_no, $candidate_name, $gender, $current_year_id);

            // Execute the statement
            if (!$stmt->execute()) {
                echo "Error inserting row: " . $stmt->error . "<br>";
            }
        } else {
            echo "Row data does not match expected format: " . implode(", ", $data) . "<br>";
        }
    }
    echo "Candidates uploaded successfully.";
} else {
    echo "Error: No file uploaded.";
}

$conn->close();
?>
