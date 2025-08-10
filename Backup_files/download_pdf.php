<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// Include Composer's autoloader
require 'vendor/autoload.php';

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch data for the report
$current_year = date('Y');
$board_name = "MOCK EXAMINATIONS";

$school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : null;
$selected_school_name = '';

if ($school_id) {
    $school_query = $conn->query("SELECT school_Name FROM schools WHERE id = $school_id");
    $selected_school_name = $school_query->fetch_assoc()['school_Name'];
}

// Fetch subjects
$subjects = $conn->query("SELECT id, Name, Code FROM subjects");

// Fetch candidates and marks
$candidates = [];
if ($school_id) {
    $candidates_result = $conn->query("SELECT id, Candidate_Name, IndexNo, Gender FROM candidates WHERE school_id = $school_id");
    
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[$row['id']] = [
            'name' => $row['Candidate_Name'],
            'index_number' => $row['IndexNo'],
            'gender' => $row['Gender'],
            'marks' => [],
            'grades' => [],
            'aggregate' => 0
        ];
    }

    $marks_result = $conn->query("SELECT candidate_id, subject_id, mark FROM marks WHERE school_id = $school_id");

    while ($row = $marks_result->fetch_assoc()) {
        $candidate_id = $row['candidate_id'];
        $subject_id = $row['subject_id'];
        $mark = $row['mark'];

        $grade_query = $conn->query("SELECT grade, score FROM grading WHERE $mark BETWEEN range_from AND range_to");
        $grade_data = $grade_query->fetch_assoc();
        $grade = $grade_data['grade'] ?? 'NA';
        $score = $grade_data['score'] ?? 0;

        $candidates[$candidate_id]['marks'][$subject_id] = $mark;
        $candidates[$candidate_id]['grades'][$subject_id] = $grade;
        $candidates[$candidate_id]['aggregate'] += $score;
    }
}

// Function to determine division
function determineDivision($aggregate) {
    if ($aggregate <= 12) return 'Division 1';
    if ($aggregate <= 24) return 'Division 2';
    if ($aggregate <= 28) return 'Division 3';
    if ($aggregate <= 33) return 'Division 4';
    if ($aggregate <= 36) return 'Division U';
    return 'Abs';
}

// Sort candidates
usort($candidates, function($a, $b) {
    return $b['aggregate'] <=> $a['aggregate']; // Sort descending
});

$best_candidates = array_slice($candidates, 0, 5);

// Calculate summary statistics
$male_count = 0;
$female_count = 0;
$division_summary = ['Division 1' => 0, 'Division 2' => 0, 'Division 3' => 0, 'Division 4' => 0, 'Division U' => 0, 'Abs' => 0];
$total_aggregates = 0;
$subject_averages = [];

if (count($candidates) > 0) {
    foreach ($candidates as $candidate) {
        $gender = $candidate['gender'];
        if ($gender == 'Male') $male_count++;
        if ($gender == 'Female') $female_count++;

        $division = determineDivision($candidate['aggregate']);
        $division_summary[$division]++;

        $total_aggregates += $candidate['aggregate'];

        foreach ($candidate['marks'] as $subject_id => $mark) {
            if (!isset($subject_averages[$subject_id])) {
                $subject_averages[$subject_id] = ['total_marks' => 0, 'count' => 0];
            }
            $subject_averages[$subject_id]['total_marks'] += $mark;
            $subject_averages[$subject_id]['count']++;
        }
    }

    $average_aggregate = $total_aggregates / count($candidates);

    foreach ($subject_averages as $subject_id => $subject_stats) {
        $subject_averages[$subject_id]['average'] = $subject_stats['total_marks'] / $subject_stats['count'];
    }
} else {
    $average_aggregate = 0;
    $subject_averages = [];
}

// Use TCPDF from Composer
use TCPDF;

// Download button clicked
if (isset($_POST['download_sheet'])) {
    // Create new PDF document
    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);

    // Add title
    $pdf->Cell(0, 10, "$board_name - Exam Year: $current_year", 0, 1, 'C');
    $pdf->Cell(0, 10, "SCHOOL: $selected_school_name", 0, 1, 'C');
    $pdf->Ln(10);

    // Add table header
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(20, 10, 'Index Number', 1, 0, 'C', true);
    $pdf->Cell(50, 10, 'Candidate Name', 1, 0, 'C', true);
    while ($subject = $conn->query("SELECT Code FROM subjects")->fetch_assoc()) {
        $pdf->Cell(25, 10, $subject['Code'] . ' (Grade)', 1, 0, 'C', true);
    }
    $pdf->Cell(25, 10, 'Aggregates', 1, 0, 'C', true);
    $pdf->Cell(25, 10, 'Division', 1, 1, 'C', true);

    // Add candidates data
    foreach ($candidates as $candidate) {
        $pdf->Cell(20, 10, $candidate['index_number'], 1);
        $pdf->Cell(50, 10, $candidate['name'], 1);
        foreach ($conn->query("SELECT id FROM subjects")->fetch_assoc() as $subject) {
            $subject_id = $subject['id'];
            $mark = $candidate['marks'][$subject_id] ?? 'NA';
            $grade = $candidate['grades'][$subject_id] ?? 'NA';
            $pdf->Cell(25, 10, "$mark ($grade)", 1);
        }
        $pdf->Cell(25, 10, $candidate['aggregate'], 1);
        $pdf->Cell(25, 10, determineDivision($candidate['aggregate']), 1);
        $pdf->Ln();
    }

    // Add summary
    $pdf->Ln(10);
    $pdf->Cell(0, 10, "Summary", 0, 1, 'L');
    $pdf->Cell(0, 10, "Males: $male_count, Females: $female_count", 0, 1, 'L');
    $pdf->Cell(0, 10, "Average Aggregates: " . number_format($average_aggregate, 2), 0, 1, 'L');

    // Add division summary
    $pdf->Cell(0, 10, "Division Summary:", 0, 1, 'L');
    foreach ($division_summary as $division => $count) {
        $pdf->Cell(0, 10, "$division: $count", 0, 1, 'L');
    }

    // Add average marks per subject
    $pdf->Ln(10);
    $pdf->Cell(0, 10, "Average Marks per Subject:", 0, 1, 'L');
    foreach ($subject_averages as $subject_id => $subject_stats) {
        $subject_code = $conn->query("SELECT Code FROM subjects WHERE id = $subject_id")->
