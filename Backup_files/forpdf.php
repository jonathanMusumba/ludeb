<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate aggregates and other necessary details
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

    while ($row = $result->fetch_assoc()) {
        if ($row['mark'] === -1) {
            $has_absence = true;
            break; // No need to process further if any mark is -1
        } else {
            $total_aggregate += $row['score'];
            if ($row['Name'] == 'English') {
                $english_score = $row['score'];
            }
            if ($row['Name'] == 'Mathematics') {
                $math_score = $row['score'];
            }
            $subject_count++;
        }
    }

    // Set total aggregate to 0 if there's any absence
    if ($has_absence) {
        $total_aggregate = 0;
    }

    return [
        'total_aggregate' => $total_aggregate,
        'english_score' => $english_score,
        'math_score' => $math_score,
        'subject_count' => $subject_count,
        'has_absence' => $has_absence
    ];
}

// Function to determine division based on aggregates and scores
function calculateDivision($candidate_id) {
    global $conn;

    $aggregates = calculateAggregates($candidate_id);

    $total_aggregate = $aggregates['total_aggregate'];
    $english_score = $aggregates['english_score'];
    $math_score = $aggregates['math_score'];
    $subject_count = $aggregates['subject_count'];
    $has_absence = $aggregates['has_absence'];

    // Division determination logic
    if ($has_absence || $subject_count < 4) {
        return 'X'; // Absence in any subject or less than 4 subjects
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

    return 'X'; // Default to 'X' if none of the conditions are met
}

// Fetch exam year and board name
$current_year = date('Y');
$board_name = "MOCK EXAMINATIONS";

// Fetch schools for selection
$schools = null;
if (!isset($_GET['school_id']) && !isset($_GET['view_school'])) {
    $schools = $conn->query("SELECT id, school_Name FROM schools");
    if ($schools->num_rows == 0) {
        die("No schools found.");
    }
}

// Store selected school name
$selected_school_name = '';
$school_id = null;
if (isset($_GET['school_id']) || isset($_GET['view_school'])) {
    $school_id = intval($_GET['school_id'] ?? $_GET['view_school']);
    $school_query = $conn->query("SELECT school_Name FROM schools WHERE id = $school_id");
    if ($school_query->num_rows > 0) {
        $selected_school_name = $school_query->fetch_assoc()['school_Name'];
    }
}

// Fetch subjects for displaying in the table header
$subjects = $conn->query("SELECT id, Name, Code FROM subjects");

// Prepare for candidate data
$candidates = [];
if (isset($school_id)) {
    // Fetch candidates
    $candidates_result = $conn->query("
        SELECT c.id, c.Candidate_Name, c.IndexNo, c.Gender
        FROM candidates c
        WHERE c.school_id = $school_id
    ");

    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[$row['id']] = [
            'id' => $row['id'],
            'name' => $row['Candidate_Name'],
            'index_number' => $row['IndexNo'],
            'gender' => $row['Gender'],
            'marks' => [],
            'grades' => [],
            'aggregate' => 0,
            'subjectCount' => 0,
            'division' => ''
        ];
    }

    // Fetch marks for the candidates
    $marks_result = $conn->query("SELECT candidate_id, subject_id, mark FROM marks WHERE school_id = $school_id");

    while ($row = $marks_result->fetch_assoc()) {
        $candidate_id = $row['candidate_id'];
        $subject_id = $row['subject_id'];
        $mark = $row['mark'];

        // Determine the grade based on the mark
        $grade_query = $conn->query("SELECT grade, score FROM grading WHERE $mark BETWEEN range_from AND range_to");
        $grade_data = $grade_query->fetch_assoc();
        $grade = $grade_data['grade'] ?? 'NA';
        $score = $grade_data['score'] ?? 0;

        $candidates[$candidate_id]['marks'][$subject_id] = ($mark == -1) ? 'Abs' : $mark;
        $candidates[$candidate_id]['grades'][$subject_id] = $grade;
        $candidates[$candidate_id]['aggregate'] += $score;
        $candidates[$candidate_id]['subjectCount']++;
    }

    // Apply the functions to each candidate
    foreach ($candidates as &$candidate) {
        $division = calculateDivision($candidate['id']);
        $candidate['division'] = $division;
    }
}

// Include FPDF
require('fpdf186/fpdf.php');

class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'Examination Results', 0, 1, 'C');
        $this->Ln(10);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Luuka Mock Results - ' . date('Y') . ' Printed on ' . date('Y-m-d'), 0, 0, 'C');
    }

    function ChapterTitle($title)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, $title, 0, 1, 'L');
        $this->Ln(4);
    }

    function ChapterBody($body)
    {
        $this->SetFont('Arial', '', 12);
        $this->MultiCell(0, 10, $body);
        $this->Ln();
    }
}

// Generate PDF
if (isset($_POST['download_sheet'])) {
    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);

        // Title Section
        $pdf->ChapterTitle('LUUKA DISTRICT LOCAL GOVERNMENT EXAMINATIONS BOARD');
        $pdf->ChapterTitle('PRIMARY LEAVING EXAMINATIONS');
        $pdf->ChapterBody('MOCK EXAMINATIONS - ' . $current_year);
        $pdf->ChapterBody('SCHOOL: ' . $selected_school_name);
    
        // Table Header
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(20, 10, 'Index No', 1);
        $pdf->Cell(60, 10, 'Candidate Name', 1);
        $pdf->Cell(20, 10, 'Gender', 1);
    
        // Reset subject fetch
        $subjects->data_seek(0);
        $columnWidths = [];
        $subject_headers = [];
        while ($subject = $subjects->fetch_assoc()) {
            $code = $subject['Code'];
            $width = max(30, strlen($code) * 1.5); // Adjust width based on content
            $pdf->Cell($width, 10, $code, 1);
            $columnWidths[] = $width;
            $subject_headers[$subject['id']] = $code;
        }
    
        $pdf->Cell(30, 10, 'Aggregates', 1);
        $pdf->Cell(30, 10, 'Division', 1);
        $pdf->Ln();
    
        // Table Body
        $pdf->SetFont('Arial', '', 12);
        foreach ($candidates as $candidate) {
            $pdf->Cell(20, 10, $candidate['index_number'], 1);
            $pdf->Cell(60, 10, $candidate['name'], 1);
            $pdf->Cell(20, 10, $candidate['gender'], 1);
    
            // Reset subject fetch
            $subjects->data_seek(0);
            foreach ($columnWidths as $index => $width) {
                $subject_id = array_keys($subject_headers)[$index];
                $mark = $candidate['marks'][$subject_id] ?? 'Abs';
                $pdf->Cell($width, 10, $mark, 1);
            }
            $pdf->Cell(30, 10, ($candidate['aggregate'] == 0 && $candidate['has_absence']) ? '0' : $candidate['aggregate'], 1);
            $pdf->Cell(30, 10, $candidate['division'], 1);
            $pdf->Ln();
        }
    
        // Summary Section
        $pdf->AddPage();
        $pdf->ChapterTitle('Summary');
    
        // Female and Male counts
        $total_female = $conn->query("SELECT COUNT(*) AS count FROM candidates WHERE school_id = $school_id AND Gender = 'F'")->fetch_assoc()['count'];
        $total_male = $conn->query("SELECT COUNT(*) AS count FROM candidates WHERE school_id = $school_id AND Gender = 'M'")->fetch_assoc()['count'];
        $total_candidates = $total_female + $total_male;
    
        $pdf->ChapterBody("Female: $total_female\nMale: $total_male\nTotal: $total_candidates");
    
        // Division by Grade Table
        $pdf->ChapterTitle('Divisions and Total Numbers');
        $divisions = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, 'U' => 0, 'X' => 0]; // Initialize as needed
        foreach ($candidates as $candidate) {
            $divisions[$candidate['division']]++;
        }
    
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(30, 10, 'Division', 1);
        $pdf->Cell(30, 10, 'Count', 1);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);
    
        foreach ($divisions as $division => $count) {
            $pdf->Cell(30, 10, $division, 1);
            $pdf->Cell(30, 10, $count, 1);
            $pdf->Ln();
        }
    
        // Average Aggregates
        $total_aggregate = array_sum(array_column($candidates, 'aggregate'));
        $average_aggregate = $total_candidates > 0 ? $total_aggregate / $total_candidates : 0;
        $pdf->ChapterTitle('Average Aggregates');
        $pdf->ChapterBody('Average Aggregate: ' . number_format($average_aggregate, 2));
    
        // Best Candidates
        $pdf->ChapterTitle('Best Candidates');
        usort($candidates, function($a, $b) {
            return ($b['aggregate'] <=> $a['aggregate']) ?: ($b['index_number'] <=> $a['index_number']);
        });
    
        $best_candidates = array_slice($candidates, 0, 5);
        foreach ($best_candidates as $candidate) {
            $pdf->Cell(20, 10, $candidate['index_number'], 1);
            $pdf->Cell(60, 10, $candidate['name'], 1);
            $pdf->Cell(20, 10, $candidate['gender'], 1);
            $pdf->Cell(30, 10, $candidate['aggregate'], 1);
            $pdf->Ln();
        }
    
        // Subject Performance Table
        $pdf->AddPage();
        $pdf->ChapterTitle('Subject Performance');
    
        $subject_performance_query = "
            SELECT s.Name AS subject_name, 
                   AVG(CASE WHEN m.mark != -1 THEN m.mark ELSE NULL END) AS average_mark, 
                   COUNT(CASE WHEN m.mark BETWEEN g.range_from AND g.range_to THEN 1 ELSE NULL END) AS grade_count, 
                   COUNT(*) AS total_marks
            FROM marks m
            JOIN subjects s ON m.subject_id = s.id
            JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to
            WHERE m.school_id = $school_id
            GROUP BY s.Name";
        
        $performance_result = $conn->query($subject_performance_query);
    
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(60, 10, 'Subject', 1);
        $pdf->Cell(30, 10, 'Avg Mark', 1);
        $pdf->Cell(30, 10, 'Grade Count', 1);
        $pdf->Cell(30, 10, 'Total Marks', 1);
        $pdf->Ln();
    
        $pdf->SetFont('Arial', '', 12);
        while ($row = $performance_result->fetch_assoc()) {
            $pdf->Cell(60, 10, $row['subject_name'], 1);
            $pdf->Cell(30, 10, number_format($row['average_mark'], 2), 1);
            $pdf->Cell(30, 10, $row['grade_count'], 1);
            $pdf->Cell(30, 10, $row['total_marks'], 1);
            $pdf->Ln();
        }
    
        // Output the PDF
        $pdf->Output('D', 'Results_' . $selected_school_name . '_' . $current_year . '.pdf');
        exit();
    }
    ?>
    