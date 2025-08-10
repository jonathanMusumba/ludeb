<?php
require('fpdf186/fpdf.php');
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate aggregates
function calculateAggregates($candidate_id) {
    global $conn;

    $marks_query = "SELECT m.mark, g.score, s.Name 
                    FROM marks m
                    JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to
                    JOIN subjects s ON m.subject_id = s.id
                    WHERE m.candidate_id = ?";

    $stmt = $conn->prepare($marks_query);
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $total_aggregate = 0;
    $english_score = null;
    $math_score = null;
    $subject_count = 0;
    $has_absence = false;
    $total_marks = 0;

    while ($row = $result->fetch_assoc()) {
        if ($row['mark'] == -1) {
            $has_absence = true;
            break;
        } else {
            $total_aggregate += $row['score'];
            $total_marks += $row['mark'];
            if ($row['Name'] == 'English') {
                $english_score = $row['score'];
            }
            if ($row['Name'] == 'Mathematics') {
                $math_score = $row['score'];
            }
            $subject_count++;
        }
    }

    $average_mark = ($subject_count > 0) ? ($total_marks / $subject_count) : 0;

    if ($has_absence) {
        $total_aggregate = 0;
    }

    return [
        'total_aggregate' => $total_aggregate,
        'english_score' => $english_score,
        'math_score' => $math_score,
        'subject_count' => $subject_count,
        'has_absence' => $has_absence,
        'average_mark' => $average_mark
    ];
}

// Function to determine division
function calculateDivision($candidate_id) {
    global $conn;

    $aggregates = calculateAggregates($candidate_id);

    $total_aggregate = $aggregates['total_aggregate'];
    $english_score = $aggregates['english_score'];
    $math_score = $aggregates['math_score'];
    $subject_count = $aggregates['subject_count'];
    $has_absence = $aggregates['has_absence'];

    if ($has_absence || $subject_count < 4) {
        return 'X';
    } elseif ($total_aggregate >= 4 && $total_aggregate <= 12) {
        if ($english_score < 7 && $math_score <= 8) {
            return '1';
        } elseif ($english_score == 8 || $math_score == 9) {
            return '2';
        } elseif ($english_score == 9) {
            return '3';
        }
    } elseif ($total_aggregate >= 13 && $total_aggregate <= 24) {
        return ($english_score <= 8) ? '2' : '3';
    } elseif ($total_aggregate >= 25 && $total_aggregate <= 28) {
        return ($english_score <= 8) ? '3' : '4';
    } elseif ($total_aggregate == 29) {
        if ($english_score <= 6) {
            return '3';
        } else {
            return '4';
        }
    } elseif ($total_aggregate >= 30 && $total_aggregate <= 32) {
        return '4';
    } elseif ($total_aggregate == 33) {
        if ($english_score < 8 && $math_score < 9) {
            return '4';
        } else {
            return 'U';
        }
    } elseif ($total_aggregate > 33) {
        return 'U';
    }

    return 'X';
}

// Function to generate PDF result slips for candidates with updates in the specified date range
function generateResultSlipsForUpdatedRecords() {
    global $conn;

    $start_date = '2024-08-15 18:00:00';
    $end_date = '2024-08-16 19:22:00';

    // Query to get all active schools
    $schools_query = "SELECT id, School_Name FROM schools WHERE ResultsStatus = 'Declared'";
    $schools_result = $conn->query($schools_query);

    $pdf = new FPDF();
    $pdf->SetAutoPageBreak(false);
    $pdf->SetFont('Arial', 'B', 10);

    $slip_width = 190; // Width for the slip, considering margins
    $slip_height = 90; // Height for the slip
    $x_start = 10;
    $y_start = 10;
    $line_height = 5;
    $current_year = date('Y');

    // Iterate over each active school
    while ($school = $schools_result->fetch_assoc()) {
        $school_id = $school['id'];
        $school_name = $school['School_Name'];

        // Start a new page for each school
        $pdf->AddPage();

        // Query to get all candidates with updated records in the specified date range
        $candidates_query = "
            SELECT DISTINCT c.id, c.Candidate_Name, c.Gender, c.IndexNo 
            FROM candidates c 
            JOIN marks m ON c.id = m.candidate_id
            WHERE c.school_id = ? AND m.Updated_at BETWEEN ? AND ?";
        $stmt = $conn->prepare($candidates_query);
        $stmt->bind_param("iss", $school_id, $start_date, $end_date);
        $stmt->execute();
        $candidates = $stmt->get_result();

        $count = 0;
        $y_start = 10; // Reset y_start for each new school

        while ($candidate = $candidates->fetch_assoc()) {
            if ($count % 3 == 0 && $count > 0) {
                $pdf->AddPage();
                $y_start = 10;
            }

            $candidate_id = $candidate['id'];
            $aggregates = calculateAggregates($candidate_id);
            $division = calculateDivision($candidate_id);
            $average_mark = $aggregates['average_mark'];
            $index_no_last_two = substr($candidate['IndexNo'], -2);
            $serial_number = $current_year . $candidate_id . $index_no_last_two;

            // Draw slip content
            $pdf->SetXY($x_start, $y_start);
            $pdf->Cell(0, $line_height, 'LUUKA DISTRICT LOCAL GOVERNMENT EXAMINATIONS BOARD', 0, 1, 'L');
            $pdf->Cell(0, $line_height, 'PRIMARY LEAVING EXAMINATIONS', 0, 1, 'L');
            $pdf->Cell(0, $line_height, 'MOCK RESULT SLIP - ' . $current_year, 0, 1, 'L');
            $pdf->Cell(0, $line_height, 'Serial No: ' . $serial_number, 0, 1, 'L');

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, $line_height, 'School Name: ' . $school_name, 0, 1);
            $pdf->Cell(0, $line_height, 'Candidate Name: ' . $candidate['Candidate_Name'] . ' Index No: ' . $candidate['IndexNo'] . ' Sex: ' . $candidate['Gender'], 0, 1);

            $pdf->Ln(5);

            // Table Header
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(70, $line_height, 'Subject', 1);
            $pdf->Cell(30, $line_height, 'Score', 1);
            $pdf->Cell(30, $line_height, 'Grade', 1);
            $pdf->Ln();

            // Table Content
            $marks_query = "SELECT s.Name, m.mark, g.score 
                            FROM marks m 
                            JOIN subjects s ON m.subject_id = s.id 
                            LEFT JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to 
                            WHERE m.candidate_id = ?";
            $stmt = $conn->prepare($marks_query);
            $stmt->bind_param("i", $candidate_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $pdf->Cell(70, $line_height, $row['Name'], 1);
                $pdf->Cell(30, $line_height, $row['mark'], 1);
                $pdf->Cell(30, $line_height, $row['score'], 1);
                $pdf->Ln();
            }

            $pdf->Ln(5);

            // Aggregates and Division
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(70, $line_height, 'Total Aggregate:', 0, 0);
            $pdf->Cell(30, $line_height, $aggregates['total_aggregate'], 0, 1);
            $pdf->Cell(70, $line_height, 'English:', 0, 0);
            $pdf->Cell(30, $line_height, $aggregates['english_score'], 0, 1);
            $pdf->Cell(70, $line_height, 'Mathematics:', 0, 0);
            $pdf->Cell(30, $line_height, $aggregates['math_score'], 0, 1);
            $pdf->Cell(70, $line_height, 'Average Mark:', 0, 0);
            $pdf->Cell(30, $line_height, number_format($average_mark, 2), 0, 1);
            $pdf->Cell(70, $line_height, 'Division:', 0, 0);
            $pdf->Cell(30, $line_height, $division, 0, 1);

            $y_start += $slip_height;
            $count++;
        }
    }

    $pdf->Output();
}

generateResultSlipsForUpdatedRecords();
?>
