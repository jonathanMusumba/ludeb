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

$candidates = [];
if (isset($school_id)) {
    // Updated query to include gender
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

        $candidates[$candidate_id]['marks'][$subject_id] = $mark;
        $candidates[$candidate_id]['grades'][$subject_id] = $grade;
        $candidates[$candidate_id]['aggregate'] += $score;
        $candidates[$candidate_id]['subjectCount']++;
    }

    // Apply the functions to each candidate
    foreach ($candidates as &$candidate) {
        // Check if 'id' key exists in the candidate array
        if (!isset($candidate['id'])) {
            continue; // Skip this iteration if ID is not set
        }

        $division = calculateDivision($candidate['id']);
        $candidate['division'] = $division;
    }
}
require('fpdf186/fpdf.php');

if (isset($_POST['download_sheet'])) {
    class PDF extends FPDF {
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Luuka Mock Results - ' . date('Y') . ' Printed on ' . date('Y-m-d'), 0, 0, 'C');
        }

        function ChapterTitle($title) {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 7, $title, 0, 1, 'L'); // Reduced line height
            $this->Ln(2); // Reduced spacing after title
        }

        function ChapterBody($body) {
            $this->SetFont('Arial', '', 12);
            $this->MultiCell(0, 7, $body); // Reduced line height
            $this->Ln();
        }
    }

    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);

    // Title Section
    $pdf->SetFont('Arial', 'B', 12);  // Set font for titles
    $pdf->Cell(0, 10, 'LUUKA DISTRICT LOCAL GOVERNMENT EXAMINATIONS BOARD', 0, 1, 'C');
    $pdf->Cell(0, 10, 'PRIMARY LEAVING EXAMINATIONS', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);  // Set font for body text
    $pdf->Cell(0, 10, 'MOCK EXAMINATIONS - ' . ($current_year ?? 'Current Year'), 0, 1, 'C');
    $pdf->Cell(0, 10, 'SCHOOL: ' . ($selected_school_name ?? 'Unknown School'), 0, 1, 'C');

    // Minimized space between header and table
    $pdf->Ln(2); // Set minimal space between header and table

        // Table Header
        // Set Font Size for the Table Header
        $pdf->SetFont('Arial', 'B', 8); // Reduced font size for headers

        // Table Header
        $pdf->Cell(20, 8, 'Index No', 1);
        $pdf->Cell(60, 8, 'Candidate Name', 1);
        $pdf->Cell(20, 8, 'Gender', 1);

        $subjects->data_seek(0);
        $columnWidths = [];
        while ($subject = $subjects->fetch_assoc()) {
            $code = $subject['Code'] ?? 'Unknown';
            $width = max(15, strlen($code) * 2); // Adjusted width
            $pdf->Cell($width, 8, $code, 1);
            $columnWidths[] = $width;
        }

        // Adjust width of the "Agg" column
        $aggWidth = 20; // Narrower width for the "Agg" column
        $divWidth = 20; // Adjust width for the "Div" column if necessary
        $pdf->Cell($aggWidth, 8, 'Agg', 1);
        $pdf->Cell($divWidth, 8, 'Div', 1);
        $pdf->Ln();

        // Set Font Size for the Table Body
        $pdf->SetFont('Arial', '', 8); // Reduced font size for body text

        // Table Body
        foreach ($candidates as $candidate) {
    $pdf->Cell(20, 8, $candidate['index_number'] ?? 'N/A', 1);
    $pdf->Cell(60, 8, $candidate['name'] ?? 'N/A', 1);
    $pdf->Cell(20, 8, $candidate['gender'] ?? 'N/A', 1);

    $subjects->data_seek(0);
    foreach ($columnWidths as $index => $width) {
        $subject = $subjects->fetch_assoc();
        if ($subject) {
            $subject_id = $subject['id'];
            $mark = $candidate['marks'][$subject_id] ?? 'Abs';
            $mark = ($mark == -1) ? 'Abs' : $mark;
            $grade = $candidate['grades'][$subject_id] ?? 'NA';
            $pdf->Cell($width, 8, $mark . ' (' . $grade . ')', 1);
        } else {
            $pdf->Cell($width, 8, '', 1);
        }
    }

    $aggregate = $candidate['aggregate'] ?? 0;
    $division = $candidate['division'] ?? 'X';
    $pdf->Cell($aggWidth, 8, $aggregate, 1); // Adjusted width for the "Agg" column
    $pdf->Cell($divWidth, 8, $division, 1); // Adjust width for the "Div" column if necessary
    $pdf->Ln();
}


    // Summary Section
    $pdf->AddPage();
    $pdf->ChapterTitle('Summary');

    // Summary Details - Horizontally
    $total_female = $conn->query("SELECT COUNT(*) AS count FROM candidates WHERE school_id = $school_id AND Gender = 'F'")->fetch_assoc()['count'];
    $total_male = $conn->query("SELECT COUNT(*) AS count FROM candidates WHERE school_id = $school_id AND Gender = 'M'")->fetch_assoc()['count'];
    $total_candidates = $total_female + $total_male;

    // Set font and column widths for the horizontal layout
    $pdf->SetFont('Arial', '', 10);
    $summary_col_width = 60; // Width for each column
    $pdf->Cell($summary_col_width, 10, "Female: $total_female", 0, 0);
    $pdf->Cell($summary_col_width, 10, "Male: $total_male", 0, 0);
    $pdf->Cell($summary_col_width, 10, "Total: $total_candidates", 0, 1);
    // Divisions and Total Numbers
// Divisions and Total Numbers
$pdf->ChapterTitle('Divisions and Total Numbers');
$divisions = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, 'U' => 0, 'X' => 0];
foreach ($candidates as $candidate) {
    $division = $candidate['division'] ?? 'U';
    $divisions[$division]++;
}

// Define column width and row height
$columnWidth = 60; // Adjust width as needed
$rowHeight = 10;   // Height of each row
$xStart = 10;      // Starting X position
$yStart = $pdf->GetY(); // Starting Y position

// Set the font for output
$pdf->SetFont('Arial', '', 10);

// Output data in columns
$index = 0;
foreach ($divisions as $division => $count) {
    // Calculate the position for each division entry
    $x = $xStart + ($index % 3) * $columnWidth; // X position for column
    $y = $yStart + floor($index / 3) * $rowHeight; // Y position for row

    // Set position and output text
    $pdf->SetXY($x, $y);
    $pdf->Cell($columnWidth, $rowHeight, "Division $division: $count", 0, 0);

    $index++;
}

// Move to next line after the last row
$pdf->Ln();



    // Average Aggregates
    if (count($candidates) > 0) {
        $total_aggregate = array_sum(array_column($candidates, 'aggregate'));
        $average_aggregate = $total_aggregate / count($candidates);
    } else {
        $average_aggregate = 0;
    }
    $pdf->ChapterTitle('Average Aggregates');
    $pdf->ChapterBody('Average Aggregate: ' . number_format($average_aggregate, 0));


// Fetch and filter candidates
// Step 1: Fetch grading table data
$grading_query = "SELECT range_from, range_to, grade, score FROM grading ORDER BY range_from ASC";
$grading_result = $conn->query($grading_query);
$grading = [];

while ($row = $grading_result->fetch_assoc()) {
    $grading[] = $row;
}

// Step 2: Function to calculate grade and aggregate
function get_grade_and_aggregate($marks, $grading) {
    $aggregate = 0;
    $valid_subjects = 0;

    foreach ($marks as $mark) {
        $valid_grade = false;
        foreach ($grading as $grade) {
            if ($mark >= $grade['range_from'] && $mark <= $grade['range_to']) {
                $aggregate += $grade['score'];
                $valid_subjects++;
                $valid_grade = true;
                break;
            }
        }
        if (!$valid_grade) {
            return false; // If any mark is invalid, return false
        }
    }

    return ($valid_subjects == 4) ? $aggregate : false; // Only consider candidates with exactly 4 valid subjects
}

// Step 3: Fetch candidates and calculate aggregates
$candidates_query = "SELECT c.id, c.IndexNo, c.Candidate_Name, c.gender FROM candidates c WHERE c.school_id = $school_id";
$candidates_result = $conn->query($candidates_query);
$candidates = [];

while ($candidate = $candidates_result->fetch_assoc()) {
    $marks_query = "SELECT mark FROM marks WHERE candidate_id = " . $candidate['id'];
    $marks_result = $conn->query($marks_query);

    $marks = [];
    while ($mark_row = $marks_result->fetch_assoc()) {
        $marks[] = $mark_row['mark'];
    }

    $aggregate = get_grade_and_aggregate($marks, $grading);

    if ($aggregate !== false) {
        $candidate['aggregate'] = $aggregate;
        $candidates[] = $candidate;
    }
}

// Step 4: Sort candidates by aggregate and display the best candidates
usort($candidates, function($a, $b) {
    return $a['aggregate'] <=> $b['aggregate']; // Sort ascending
});

$best_candidates = array_slice($candidates, 0, 5);

// Display best candidates
$pdf->ChapterTitle('Best Candidates');

// Table Headers
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 10, 'IndexNo', 1);
$pdf->Cell(80, 10, 'Candidate Name', 1);
$pdf->Cell(30, 10, 'Gender', 1);
$pdf->Cell(40, 10, 'Aggregates', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);
foreach ($best_candidates as $candidate) {
    $pdf->Cell(30, 10, $candidate['IndexNo'], 1);
    $pdf->Cell(80, 10, $candidate['Candidate_Name'], 1);
    $pdf->Cell(30, 10, $candidate['gender'], 1);
    $pdf->Cell(40, 10, $candidate['aggregate'], 1);
    $pdf->Ln();
}


// Add a new page for the combined tables
$pdf->AddPage();
$pdf->ChapterTitle('Subject Performance and Grade Distribution');

// Subject Performance Table (Top)
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

// Setting the width for each column in the Subject Performance table
$subject_col_width = 60;
$avg_mark_col_width = 30;
$grade_count_col_width = 30;
$total_marks_col_width = 30;

// Start position for the Subject Performance table
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell($subject_col_width, 10, 'Subject', 1);
$pdf->Cell($avg_mark_col_width, 10, 'Avg Mark', 1);
$pdf->Cell($grade_count_col_width, 10, 'Grade Count', 1);
$pdf->Cell($total_marks_col_width, 10, 'Total Marks', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);
while ($row = $performance_result->fetch_assoc()) {
    $pdf->Cell($subject_col_width, 10, $row['subject_name'] ?? 'N/A', 1);
    $pdf->Cell($avg_mark_col_width, 10, number_format($row['average_mark'] ?? 0, 1), 1);
    $pdf->Cell($grade_count_col_width, 10, $row['grade_count'] ?? 0, 1);
    $pdf->Cell($total_marks_col_width, 10, $row['total_marks'] ?? 0, 1);
    $pdf->Ln();
}

// Move to a new position for the Grade Distribution table (below the first table)
$pdf->Ln(10); // Space between the two tables

// Grade Distribution Table (Bottom)
$grades_query = "SELECT * FROM grading ORDER BY range_from DESC";
$grades_result = $conn->query($grades_query);

$grade_columns = [];
while ($grade_row = $grades_result->fetch_assoc()) {
    $grade_columns[] = $grade_row;
}

// Adjust cell width for grades to fit within the page
$grade_col_width = 14; // Reduced width for each grade column to fit within the A4 portrait page

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell($subject_col_width, 10, 'Subject', 1);

foreach ($grade_columns as $grade) {
    $pdf->Cell($grade_col_width, 10, $grade['grade'], 1);
}
$pdf->Cell($grade_col_width, 10, 'X', 1); // Column for marks with -1
$pdf->Ln();

$grade_distribution_query = "
    SELECT 
        s.Name AS subject_name,
        g.grade,
        COUNT(CASE 
            WHEN m.mark = -1 THEN 1 
            WHEN m.mark BETWEEN g.range_from AND g.range_to THEN 1 
            ELSE NULL 
            END) AS grade_count,
        SUM(CASE WHEN m.mark = -1 THEN 1 ELSE 0 END) AS absent_count
    FROM marks m
    JOIN subjects s ON m.subject_id = s.id
    LEFT JOIN grading g ON m.mark != -1 AND m.mark BETWEEN g.range_from AND g.range_to
    WHERE m.school_id = $school_id
    GROUP BY s.Name, g.grade
    ORDER BY s.Name, g.range_from";


$grade_distribution_result = $conn->query($grade_distribution_query);

$pdf->SetFont('Arial', '', 10);

$current_subject = '';
$subject_grades = array_fill_keys(array_column($grade_columns, 'grade'), 0);
$absent_count = 0;

while ($row = $grade_distribution_result->fetch_assoc()) {
    if ($current_subject !== $row['subject_name']) {
        if ($current_subject !== '') {
            // Print the last subject's data
            $pdf->Cell($subject_col_width, 10, $current_subject, 1);
            foreach ($subject_grades as $grade_count) {
                $pdf->Cell($grade_col_width, 10, $grade_count, 1);
            }
            $pdf->Cell($grade_col_width, 10, $absent_count, 1); // Absent count (X)
            $pdf->Ln();
        }

        // Reset for new subject
        $current_subject = $row['subject_name'];
        $subject_grades = array_fill_keys(array_column($grade_columns, 'grade'), 0);
        $absent_count = $row['absent_count'];
    }

    $subject_grades[$row['grade']] = $row['grade_count'];
}

// Print the last subject's data
$pdf->Cell($subject_col_width, 10, $current_subject, 1);
foreach ($subject_grades as $grade_count) {
    $pdf->Cell($grade_col_width, 10, $grade_count, 1);
}
$pdf->Cell($grade_col_width, 10, $absent_count, 1); // Absent count (X)
$pdf->Ln();

$pdf->Ln(10); // Space before the Grading Table
    $pdf->ChapterTitle('Grading Table');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(60, 10, 'Range', 1);
    $pdf->Cell(60, 10, 'Grade', 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 10);
    $grading_query = "SELECT range_from, range_to, grade FROM grading";
    $grading_result = $conn->query($grading_query);

    while ($row = $grading_result->fetch_assoc()) {
        $range = $row['range_from'] . ' - ' . $row['range_to'];
        $pdf->Cell(60, 10, $range, 1);
        $pdf->Cell(60, 10, $row['grade'] ?? 'N/A', 1);
        $pdf->Ln();
    }
    // Output the PDF
    $pdf->Output('D', 'Results_' . ($selected_school_name ?? 'Unknown') . '_' . ($current_year ?? 'Current_Year') . '.pdf');
    exit();
}



?>