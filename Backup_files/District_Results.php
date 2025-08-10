<?php
session_start();
require 'vendor/autoload.php'; // Include Composer autoload file

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\Fpdf\Fpdf; // Ensure this path is correct based on your fpdi-fpdf version

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve schools with ResultsStatus 'Declared'
$query = "SELECT * FROM schools WHERE ResultsStatus = 'Declared'";
$result = $conn->query($query);

$schools = [];
while ($row = $result->fetch_assoc()) {
    $schools[] = $row;
}

// Generate PDF for each school
function generateSchoolPDF($schoolData) {
    // Define the custom PDF class extending Fpdf
    class PDF extends Fpdf {
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Luuka Mock Results - ' . date('Y') . ' Printed on ' . date('Y-m-d'), 0, 0, 'C');
        }

        function ChapterTitle($title) {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 7, $title, 0, 1, 'L');
            $this->Ln(2);
        }

        function ChapterBody($body) {
            $this->SetFont('Arial', '', 12);
            $this->MultiCell(0, 7, $body);
            $this->Ln();
        }
    }

    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);

    // Title Section
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'LUUKA DISTRICT LOCAL GOVERNMENT EXAMINATIONS BOARD', 0, 1, 'C');
    $pdf->Cell(0, 10, 'PRIMARY LEAVING EXAMINATIONS', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 10, 'MOCK EXAMINATIONS - ' . date('Y'), 0, 1, 'C');
    $pdf->Cell(0, 10, 'SCHOOL: ' . ($schoolData['name'] ?? 'Unknown School'), 0, 1, 'C');
    $pdf->Ln(2);

    // Table Header
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(20, 8, 'Index No', 1);
    $pdf->Cell(60, 8, 'Candidate Name', 1);
    $pdf->Cell(20, 8, 'Gender', 1);

    // Fetch subjects
    $subjectsResult = $GLOBALS['conn']->query("SELECT * FROM subjects");
    $columnWidths = [];
    $subjects = [];

    while ($subject = $subjectsResult->fetch_assoc()) {
        if ($subject) {
            $code = $subject['Code'] ?? 'Unknown';
            $width = max(15, strlen($code) * 2);
            $pdf->Cell($width, 8, $code, 1);
            $columnWidths[] = $width;
            $subjects[$subject['id']] = $subject; // Store subjects by ID for later use
        }
    }

    $pdf->Cell(20, 8, 'Agg', 1);
    $pdf->Cell(20, 8, 'Div', 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 8);
    $candidatesResult = $GLOBALS['conn']->query("SELECT * FROM candidates WHERE school_id = " . $schoolData['id']);
    
    while ($candidate = $candidatesResult->fetch_assoc()) {
        $pdf->Cell(20, 8, $candidate['index_number'] ?? 'N/A', 1);
        $pdf->Cell(60, 8, $candidate['name'] ?? 'N/A', 1);
        $pdf->Cell(20, 8, $candidate['gender'] ?? 'N/A', 1);

        foreach ($columnWidths as $index => $width) {
            $subject = array_values($subjects)[$index] ?? null; // Fetch the subject for this column
            if ($subject) {
                $subject_id = $subject['id'];
                $mark = $candidate['marks'][$subject_id] ?? 'Abs';
                $grade = $candidate['grades'][$subject_id] ?? 'NA';
                $pdf->Cell($width, 8, $mark . ' (' . $grade . ')', 1);
            } else {
                $pdf->Cell($width, 8, '', 1);
            }
        }

        $aggregate = $candidate['aggregate'] ?? 0;
        $division = $candidate['division'] ?? 'X';
        $pdf->Cell(20, 8, $aggregate, 1);
        $pdf->Cell(20, 8, $division, 1);
        $pdf->Ln();
    }

    $pdf->AddPage();
    $pdf->ChapterTitle('Summary');
    $total_female = $GLOBALS['conn']->query("SELECT COUNT(*) AS count FROM candidates WHERE school_id = " . $schoolData['id'] . " AND Gender = 'F'")->fetch_assoc()['count'];
    $total_male = $GLOBALS['conn']->query("SELECT COUNT(*) AS count FROM candidates WHERE school_id = " . $schoolData['id'] . " AND Gender = 'M'")->fetch_assoc()['count'];
    $total_candidates = $total_female + $total_male;
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 10, "Female: $total_female", 0, 0);
    $pdf->Cell(60, 10, "Male: $total_male", 0, 0);
    $pdf->Cell(60, 10, "Total: $total_candidates", 0, 1);

    $pdf->ChapterTitle('Divisions and Total Numbers');
    $divisions = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, 'U' => 0, 'X' => 0];
    $candidatesResult->data_seek(0);
    while ($candidate = $candidatesResult->fetch_assoc()) {
        $division = $candidate['division'] ?? 'U';
        $divisions[$division]++;
    }

    $columnWidth = 60;
    $rowHeight = 10;
    $xStart = 10;
    $yStart = $pdf->GetY();
    $pdf->SetFont('Arial', '', 10);

    $index = 0;
    foreach ($divisions as $division => $count) {
        $x = $xStart + ($index % 3) * $columnWidth;
        $y = $yStart + floor($index / 3) * $rowHeight;
        $pdf->SetXY($x, $y);
        $pdf->Cell($columnWidth, $rowHeight, "Division $division: $count", 0, 0);
        $index++;
    }
    $pdf->Ln();

    $filename = 'temp_' . $schoolData['id'] . '.pdf';
    $pdf->Output('F', $filename);

    return $filename;
}

$filenames = [];
foreach ($schools as $school) {
    $filename = generateSchoolPDF($school);
    $filenames[] = $filename;
}

function mergePDFs($filenames, $outputFilename) {
    $pdf = new FPDI();
    
    foreach ($filenames as $file) {
        $pdf->AddPage();
        $pdf->setSourceFile($file);
        $tplIdx = $pdf->importPage(1);
        $pdf->useTemplate($tplIdx);
    }
    
    $pdf->Output('F', $outputFilename);
}

$mergedFilename = 'District_Results_Luuka_Mock_' . date('Y') . '.pdf';
mergePDFs($filenames, $mergedFilename);

foreach ($filenames as $file) {
    unlink($file);
}

echo "PDF generated successfully: $mergedFilename";
