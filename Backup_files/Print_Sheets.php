<?php
require('fpdf186/fpdf.php');

// Function to get the list of schools
function getSchools($conn) {
    $query = "SELECT id, School_Name FROM schools";
    $result = $conn->query($query);
    $schools = [];
    while ($row = $result->fetch_assoc()) {
        $schools[] = $row;
    }
    return $schools;
}

// Function to fetch candidates for a specific school
function getCandidates($conn, $school_id) {
    $query = "SELECT c.id, c.IndexNo, c.Candidate_Name, c.gender FROM candidates c WHERE c.school_id = $school_id";
    $result = $conn->query($query);
    $candidates = [];
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    return $candidates;
}

// Function to fetch marks for a specific candidate
function getMarks($conn, $candidate_id) {
    $query = "SELECT subject_id, mark FROM marks WHERE candidate_id = $candidate_id";
    $result = $conn->query($query);
    $marks = [];
    while ($row = $result->fetch_assoc()) {
        $marks[$row['subject_id']] = $row['mark'];
    }
    return $marks;
}

// Function to fetch subjects
function getSubjects($conn) {
    $query = "SELECT id, Code FROM subjects";
    $result = $conn->query($query);
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    return $subjects;
}

if (isset($_POST['download_sheet'])) {
    class PDF extends FPDF {
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

    $schools = getSchools($conn);
    $subjects = getSubjects($conn);

    foreach ($schools as $school) {
        $school_id = $school['id'];
        $selected_school_name = $school['School_Name'];
        $candidates = getCandidates($conn, $school_id);

        // Start PDF generation for each school
        $pdf = new PDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);

        // Title Section
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'LUUKA DISTRICT LOCAL GOVERNMENT EXAMINATIONS BOARD', 0, 1, 'L');
        $pdf->Cell(0, 10, 'PRIMARY LEAVING EXAMINATIONS', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'MOCK EXAMINATIONS - ' . date('Y'), 0, 1, 'L');
        $pdf->Cell(0, 10, 'SCHOOL: ' . $selected_school_name, 0, 1, 'L');

        $pdf->Ln(2);

        // Table Header
        $pdf->SetFont('Arial', 'B', 8);

        $pdf->Cell(20, 8, 'Index No', 1);
        $pdf->Cell(60, 8, 'Candidate Name', 1);
        $pdf->Cell(20, 8, 'Gender', 1);

        foreach ($subjects as $subject) {
            $pdf->Cell(15, 8, $subject['Code'], 1);
        }

        $pdf->Cell(20, 8, 'Agg', 1);
        $pdf->Cell(20, 8, 'Div', 1);
        $pdf->Ln();

        // Table Body
        $pdf->SetFont('Arial', '', 8);

        foreach ($candidates as $candidate) {
            $pdf->Cell(20, 8, $candidate['IndexNo'], 1);
            $pdf->Cell(60, 8, $candidate['Candidate_Name'], 1);
            $pdf->Cell(20, 8, $candidate['gender'], 1);

            $marks = getMarks($conn, $candidate['id']);
            foreach ($subjects as $subject) {
                $mark = $marks[$subject['id']] ?? 'Abs';
                $pdf->Cell(15, 8, $mark, 1);
            }

            $aggregate = $candidate['aggregate'] ?? 0;
            $division = $candidate['division'] ?? 'X';
            $pdf->Cell(20, 8, $aggregate, 1);
            $pdf->Cell(20, 8, $division, 1);
            $pdf->Ln();
        }

        // Additional Summary Sections (can be added here)

        // Save PDF
        $pdf->Output('F', "results/{$selected_school_name}_Results.pdf");
    }

    echo "PDFs generated successfully!";
}
?>