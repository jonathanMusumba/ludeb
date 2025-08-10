<?php
session_start();
require_once 'db_connect.php';
require_once '../lib/fpdf.php';

// Restrict to Examination Administrators
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Examination Administrator') {
    header("Location: ../login.php");
    exit();
}

$exam_year_id = isset($_GET['exam_year_id']) ? (int)$_GET['exam_year_id'] : 0;

if (!$exam_year_id) {
    header("Location: district_results.php");
    exit();
}

// Fetch exam body and year details
$exam_body_query = "SELECT board_name FROM settings WHERE id = 1";
$exam_body_result = $conn->query($exam_body_query);
$exam_body = ($exam_body_result->num_rows > 0) ? $exam_body_result->fetch_assoc()['board_name'] : 'Luuka Examination Board';

$exam_year_query = "SELECT exam_year FROM exam_years WHERE id = ?";
$stmt = $conn->prepare($exam_year_query);
$stmt->bind_param('i', $exam_year_id);
$stmt->execute();
$exam_year_result = $stmt->get_result();
$exam_year = ($exam_year_result->num_rows > 0) ? $exam_year_result->fetch_assoc()['exam_year'] : 'N/A';
$stmt->close();

// Fetch schools with complete results
$schools_query = "
    SELECT s.center_no, s.school_name, s.id
    FROM schools s
    LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    LEFT JOIN (
        SELECT candidate_id, exam_year_id, COUNT(DISTINCT subject_id) AS subject_count
        FROM marks
        WHERE exam_year_id = ?
        GROUP BY candidate_id, exam_year_id
    ) m ON c.id = m.candidate_id AND c.exam_year_id = m.exam_year_id
    WHERE s.results_status = 'Declared'
    GROUP BY s.id, s.center_no, s.school_name
    HAVING (COUNT(c.id) = 0 OR (MIN(m.subject_count) = 4 AND MAX(m.subject_count) = 4))
    ORDER BY s.center_no
";
$schools = [];
$stmt = $conn->prepare($schools_query);
$stmt->bind_param('ii', $exam_year_id, $exam_year_id);
$stmt->execute();
$schools_result = $stmt->get_result();
while ($row = $schools_result->fetch_assoc()) {
    $schools[] = $row;
}
$stmt->close();

// Create PDF
class ResultSlipPDF extends FPDF {
    public function Header() {
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Luuka Examination Board - Result Slips', 0, 1, 'C');
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

function drawEnhancedResultSlip($pdf, $exam_body, $exam_year, $school, $result) {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, $exam_body, 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Primary Leaving Examination (PLE) Results - ' . $exam_year, 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 6, 'Center No:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, $school['center_no'], 0, 1, 'L');
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 6, 'School:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, $school['school_name'], 0, 1, 'L');
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 6, 'Index No:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, $result['index_no'], 0, 1, 'L');
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 6, 'Candidate:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, $result['candidate_name'], 0, 1, 'L');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(60, 8, 'Subject', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Marks', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Grade', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 10);
    $is_absent = ($result['aggregates'] == 0 || $result['division'] === 'Absent');

    $subjects = [
        ['name' => 'English', 'marks' => $result['eng_marks'], 'grade' => $result['eng_grade']],
        ['name' => 'Science', 'marks' => $result['sci_marks'], 'grade' => $result['sci_grade']],
        ['name' => 'Mathematics', 'marks' => $result['mtc_marks'], 'grade' => $result['mtc_grade']],
        ['name' => 'Social Studies', 'marks' => $result['sst_marks'], 'grade' => $result['sst_grade']],
    ];

    foreach ($subjects as $subject) {
        $marks = $is_absent ? '-' : ($subject['marks'] ?? '-');
        $grade = $is_absent ? 'X' : ($subject['grade'] ?? '-');
        $pdf->Cell(60, 6, $subject['name'], 1, 0, 'L');
        $pdf->Cell(30, 6, $marks, 1, 0, 'C');
        $pdf->Cell(30, 6, $grade, 1, 1, 'C');
    }

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 6, 'Aggregates:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 6, $is_absent ? '-' : $result['aggregates'], 0, 1, 'L');
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 6, 'Division:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 6, $result['division'], 0, 1, 'L');
    $pdf->Ln(10);
}

$pdf = new ResultSlipPDF();
$pdf->AliasNbPages();
$pdf->SetMargins(10, 15, 10);
$pdf->SetAutoPageBreak(true, 25);

foreach ($schools as $school) {
    $school_id = $school['id'];

    // Fetch results for the school
    $results_query = "
        SELECT 
            all_candidates.candidate_id,
            CASE 
                WHEN absent.candidate_id IS NOT NULL THEN 0 
                ELSE all_candidates.aggregates 
            END AS aggregates,
            CASE 
                WHEN absent.candidate_id IS NOT NULL THEN 'Absent' 
                ELSE CASE 
                    WHEN all_candidates.division = 'Division 1' THEN 'One'
                    WHEN all_candidates.division = 'Division 2' THEN 'Two'
                    WHEN all_candidates.division = 'Division 3' THEN 'Three'
                    WHEN all_candidates.division = 'Division 4' THEN 'Four'
                    WHEN all_candidates.division = 'Ungraded' THEN 'Ungraded'
                    ELSE 'Absent'
                END 
            END AS division,
            sr.candidate_index_number AS index_no,
            sr.candidate_name,
            MAX(CASE WHEN s.code = 'ENG' THEN sr.marks END) AS eng_marks,
            MAX(CASE WHEN s.code = 'ENG' THEN sr.grade END) AS eng_grade,
            MAX(CASE WHEN s.code = 'SCI' THEN sr.marks END) AS sci_marks,
            MAX(CASE WHEN s.code = 'SCI' THEN sr.grade END) AS sci_grade,
            MAX(CASE WHEN s.code = 'MTC' THEN sr.marks END) AS mtc_marks,
            MAX(CASE WHEN s.code = 'MTC' THEN sr.grade END) AS mtc_grade,
            MAX(CASE WHEN s.code = 'SST' THEN sr.marks END) AS sst_marks,
            MAX(CASE WHEN s.code = 'SST' THEN sr.grade END) AS sst_grade
        FROM (
            SELECT DISTINCT 
                cr.candidate_id,
                cr.aggregates,
                cr.division
            FROM candidate_results cr
            JOIN (
                SELECT candidate_id, exam_year_id, COUNT(DISTINCT subject_id) AS subject_count
                FROM marks
                WHERE exam_year_id = ?
                GROUP BY candidate_id, exam_year_id
                HAVING subject_count = 4
            ) m ON cr.candidate_id = m.candidate_id AND cr.exam_year_id = m.exam_year_id
            WHERE cr.school_id = ? AND cr.exam_year_id = ?
        ) AS all_candidates
        LEFT JOIN school_results sr ON all_candidates.candidate_id = sr.candidate_id AND sr.exam_year_id = ?
        LEFT JOIN subjects s ON sr.subject_code = s.code
        LEFT JOIN (
            SELECT candidate_id, exam_year_id
            FROM marks
            WHERE exam_year_id = ? AND status = 'ABSENT'
        ) absent ON all_candidates.candidate_id = absent.candidate_id AND all_candidates.exam_year_id = absent.exam_year_id
        WHERE sr.candidate_index_number IS NOT NULL
        GROUP BY all_candidates.candidate_id, sr.candidate_index_number, sr.candidate_name, all_candidates.aggregates, all_candidates.division, absent.candidate_id
        ORDER BY sr.candidate_index_number
    ";
    $results = [];
    $stmt = $conn->prepare($results_query);
    $stmt->bind_param('iiiii', $exam_year_id, $school_id, $exam_year_id, $exam_year_id, $exam_year_id);
    $stmt->execute();
    $results_result = $stmt->get_result();
    while ($row = $results_result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();

    if (empty($results)) {
        continue; // Skip schools with no candidates
    }

    // Add school cover page
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, $exam_body, 0, 1, 'C');
    $pdf->Cell(0, 10, "PLE MOCK EXAMINATIONS $exam_year", 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, "Result Slips for {$school['school_name']} ({$school['center_no']})", 0, 1, 'C');
    $pdf->Ln(10);

    // Generate result slips
    foreach ($results as $result) {
        $pdf->AddPage();
        drawEnhancedResultSlip($pdf, $exam_body, $exam_year, $school, $result);
    }
}

// Output PDF
$filename = "All_Schools_Result_Slips_" . $exam_year . ".pdf";
$pdf->Output($filename, 'D');

function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $filename);
    $filename = preg_replace('/\s+/', '_', $filename);
    return trim($filename, '_');
}
?>