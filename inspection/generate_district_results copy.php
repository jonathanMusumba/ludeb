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

// Helper function to format subject results
function formatSubjectResult($marks, $grade, $is_absent) {
    if ($is_absent || $marks === null || $marks === '') {
        return '-(X)';
    }
    if ($grade === null || $grade === '') {
        return $marks . '(-)';
    }
    return $marks . '(' . $grade . ')';
}

// Helper function to convert division to short form
function getDivisionShort($division) {
    switch ($division) {
        case 'Division 1': return '1';
        case 'Division 2': return '2';
        case 'Division 3': return '3';
        case 'Division 4': return '4';
        case 'Ungraded': return 'U';
        case 'X': return 'X';
        default: return $division;
    }
}

// Create PDF
class DistrictResultsPDF extends FPDF {
    
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new DistrictResultsPDF();
$pdf->AliasNbPages();
$pdf->SetMargins(10, 15, 10);
$pdf->SetAutoPageBreak(true, 25);

// Track if we've added any schools
$school_count = 0;

// Loop through schools
foreach ($schools as $school) {
    $school_id = $school['id'];
    
    // Fetch results for the school
    $results_query = "
        SELECT 
            c.id as candidate_id,
            c.index_number AS index_no,
            c.candidate_name,
            MAX(CASE WHEN s.code = 'ENG' THEN m.mark END) AS eng_marks,
            MAX(CASE WHEN s.code = 'ENG' AND g.grade IS NOT NULL THEN g.grade END) AS eng_grade,
            MAX(CASE WHEN s.code = 'SCI' THEN m.mark END) AS sci_marks,
            MAX(CASE WHEN s.code = 'SCI' AND g.grade IS NOT NULL THEN g.grade END) AS sci_grade,
            MAX(CASE WHEN s.code = 'MTC' THEN m.mark END) AS mtc_marks,
            MAX(CASE WHEN s.code = 'MTC' AND g.grade IS NOT NULL THEN g.grade END) AS mtc_grade,
            MAX(CASE WHEN s.code = 'SST' THEN m.mark END) AS sst_marks,
            MAX(CASE WHEN s.code = 'SST' AND g.grade IS NOT NULL THEN g.grade END) AS sst_grade,
            CASE 
                WHEN absent.candidate_id IS NOT NULL THEN 0 
                ELSE COALESCE(cr.aggregates, 0) 
            END AS aggregates,
            CASE 
                WHEN absent.candidate_id IS NOT NULL THEN 'X' 
                ELSE COALESCE(cr.division, 'X') 
            END AS division,
            COUNT(m.mark) AS has_marks
        FROM candidates c
        LEFT JOIN marks m ON c.id = m.candidate_id AND m.exam_year_id = ?
        LEFT JOIN subjects s ON m.subject_id = s.id
        LEFT JOIN grading g ON m.mark >= g.range_from AND m.mark <= g.range_to AND g.subject_id = s.id AND g.exam_year_id = ?
        LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
        LEFT JOIN (
            SELECT candidate_id, exam_year_id
            FROM marks
            WHERE exam_year_id = ? AND status = 'ABSENT'
        ) absent ON c.id = absent.candidate_id AND c.exam_year_id = absent.exam_year_id
        WHERE c.school_id = ? AND c.exam_year_id = ?
        AND c.id IN (
            SELECT candidate_id
            FROM marks
            WHERE exam_year_id = ?
            GROUP BY candidate_id
            HAVING COUNT(DISTINCT subject_id) = 4
        )
        GROUP BY c.id, c.index_number, c.candidate_name, cr.aggregates, cr.division, absent.candidate_id
        ORDER BY c.index_number
    ";
    $results = [];
    $stmt = $conn->prepare($results_query);
    if ($stmt) {
        $stmt->bind_param('iiiiiii', $exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id, $school_id, $exam_year_id, $exam_year_id);
        $stmt->execute();
        $results_result = $stmt->get_result();
        while ($row = $results_result->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    }

    if (empty($results)) {
        continue; // Skip schools with no candidates
    }

    // Add page for this school
    $pdf->AddPage();
    $school_count++;

    // School Header - Complete Header for Each School
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, strtoupper($exam_body), 0, 1, 'L');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 6, "PLE MOCK EXAMINATIONS $exam_year", 0, 1, 'L');
    $pdf->Ln(8);
    
    // Center and School Name
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, "CENTER NO: " . $school['center_no'] . " | " . strtoupper($school['school_name']), 0, 1, 'L');
    $pdf->Ln(8);

    // Results table
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(200, 220, 255);

    $header = ['Index No', 'Candidate Name', 'English', 'Science', 'Mathematics', 'Social Studies', 'AGG', 'Div'];
    $widths = [20, 50, 20, 20, 25, 30, 15, 15];

    foreach ($header as $i => $col) {
        $pdf->Cell($widths[$i], 8, $col, 1, 0, 'L', true);
    }
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetFillColor(245, 245, 245);
    $fill = false;

    $division_counts = [
        'Division 1' => 0,
        'Division 2' => 0,
        'Division 3' => 0,
        'Division 4' => 0,
        'Ungraded' => 0,
        'X' => 0
    ];
    $absentees = 0;
    $total_candidates = count($results);
    $aggregate_values = [];
    $best_candidates = [];
    $worst_candidates = [];

    foreach ($results as $result) {
        $is_absent = ($result['has_marks'] < 4 || $result['aggregates'] == 0 || $result['division'] === 'X');
        
        $eng = formatSubjectResult($result['eng_marks'], $result['eng_grade'], $is_absent);
        $sci = formatSubjectResult($result['sci_marks'], $result['sci_grade'], $is_absent);
        $mtc = formatSubjectResult($result['mtc_marks'], $result['mtc_grade'], $is_absent);
        $sst = formatSubjectResult($result['sst_marks'], $result['sst_grade'], $is_absent);
        
        $aggregate = $is_absent ? '-(X)' : $result['aggregates'];
        $division = $is_absent ? '-(X)' : getDivisionShort($result['division']);
        
        $pdf->Cell($widths[0], 6, $result['index_no'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[1], 6, substr($result['candidate_name'], 0, 25), 1, 0, 'L', $fill);
        $pdf->Cell($widths[2], 6, $eng, 1, 0, 'C', $fill);
        $pdf->Cell($widths[3], 6, $sci, 1, 0, 'C', $fill);
        $pdf->Cell($widths[4], 6, $mtc, 1, 0, 'C', $fill);
        $pdf->Cell($widths[5], 6, $sst, 1, 0, 'C', $fill);
        $pdf->Cell($widths[6], 6, $aggregate, 1, 0, 'C', $fill);
        $pdf->Cell($widths[7], 6, $division, 1, 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;

        if ($is_absent) {
            $absentees++;
            $division_counts['X']++;
        } else {
            $div = trim($result['division']);
            if (isset($division_counts[$div])) {
                $division_counts[$div]++;
            }
            if (is_numeric($result['aggregates']) && $result['aggregates'] > 0) {
                $aggregate_val = (int)$result['aggregates'];
                $aggregate_values[] = $aggregate_val;
                $best_candidates[] = [
                    'name' => $result['candidate_name'],
                    'index' => $result['index_no'],
                    'aggregate' => $aggregate_val,
                    'division' => $result['division'],
                    'eng_marks' => $result['eng_marks'],
                    'mtc_marks' => $result['mtc_marks']
                ];
            }
        }
    }

    // Sort candidates for best/worst
    usort($best_candidates, function($a, $b) {
        if ($a['aggregate'] != $b['aggregate']) {
            return $a['aggregate'] - $b['aggregate'];
        }
        $a_eng = $a['eng_marks'] ?? 0;
        $b_eng = $b['eng_marks'] ?? 0;
        if ($a_eng != $b_eng) {
            return $b_eng - $a_eng;
        }
        $a_math = $a['mtc_marks'] ?? 0;
        $b_math = $b['mtc_marks'] ?? 0;
        return $b_math - $a_math;
    });

    $best_5_candidates = array_slice($best_candidates, 0, 5);
    $best_5_indexes = array_column($best_5_candidates, 'index');

    $remaining_candidates = array_filter($best_candidates, function($candidate) use ($best_5_indexes) {
        return !in_array($candidate['index'], $best_5_indexes);
    });

    usort($remaining_candidates, function($a, $b) {
        if ($a['aggregate'] != $b['aggregate']) {
            return $b['aggregate'] - $a['aggregate'];
        }
        $a_eng = $a['eng_marks'] ?? 0;
        $b_eng = $b['eng_marks'] ?? 0;
        if ($a_eng != $b_eng) {
            return $a_eng - $b_eng;
        }
        $a_math = $a['mtc_marks'] ?? 0;
        $b_math = $b['mtc_marks'] ?? 0;
        return $a_math - $b_math;
    });

    $worst_5_candidates = array_slice($remaining_candidates, 0, 5);

    $average_aggregate = !empty($aggregate_values) ? round(array_sum($aggregate_values) / count($aggregate_values), 0) : 0;
    $best_aggregate = !empty($aggregate_values) ? min($aggregate_values) : 0;
    $worst_aggregate = !empty($aggregate_values) ? max($aggregate_values) : 0;

    // Summary Section
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'SUMMARY', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'Division Summary', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);

    $summary_headers = ['Division', '1', '2', '3', '4', 'U', 'Absentees', 'Total'];
    $summary_widths = [25, 15, 15, 15, 15, 15, 20, 20];

    $pdf->SetFillColor(200, 220, 255);
    foreach ($summary_headers as $i => $header) {
        $pdf->Cell($summary_widths[$i], 6, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();

    $summary_data = [
        'Candidates',
        $division_counts['Division 1'],
        $division_counts['Division 2'],
        $division_counts['Division 3'],
        $division_counts['Division 4'],
        $division_counts['Ungraded'],
        $absentees,
        $total_candidates
    ];

    foreach ($summary_data as $i => $data) {
        $pdf->Cell($summary_widths[$i], 6, $data, 1, 0, 'C', false);
    }
    $pdf->Ln(10);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'Statistics', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);

    $pdf->Cell(50, 6, 'Average Aggregate:', 0, 0, 'L');
    $pdf->Cell(30, 6, $average_aggregate, 0, 1, 'L');
    $pdf->Cell(50, 6, 'Best Candidate Aggregate:', 0, 0, 'L');
    $pdf->Cell(30, 6, $best_aggregate, 0, 1, 'L');
    $pdf->Cell(50, 6, 'Worst Candidate Aggregate:', 0, 0, 'L');
    $pdf->Cell(30, 6, $worst_aggregate, 0, 1, 'L');

    $pdf->Ln(5);

    if (!empty($best_5_candidates)) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, 'Best Candidates (Top 5)', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 9);
        
        foreach ($best_5_candidates as $i => $candidate) {
            $pdf->Cell(10, 5, ($i + 1) . '.', 0, 0, 'L');
            $pdf->Cell(60, 5, $candidate['name'], 0, 0, 'L');
            $pdf->Cell(25, 5, $candidate['index'], 0, 0, 'L');
            $pdf->Cell(20, 5, 'AGG: ' . $candidate['aggregate'], 0, 0, 'L');
            $pdf->Cell(15, 5, 'Div: ' . getDivisionShort($candidate['division']), 0, 1, 'L');
        }
        $pdf->Ln(3);
    }

    if (!empty($worst_5_candidates)) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, 'Worst Candidates (Bottom 5)', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 9);
        
        foreach ($worst_5_candidates as $i => $candidate) {
            $pdf->Cell(10, 5, ($i + 1) . '.', 0, 0, 'L');
            $pdf->Cell(60, 5, $candidate['name'], 0, 0, 'L');
            $pdf->Cell(25, 5, $candidate['index'], 0, 0, 'L');
            $pdf->Cell(20, 5, 'AGG: ' . $candidate['aggregate'], 0, 0, 'L');
            $pdf->Cell(15, 5, 'Div: ' . getDivisionShort($candidate['division']), 0, 1, 'L');
        }
    }
}

// Check if we processed any schools
if ($school_count == 0) {
    // Add a page with "No results available" message
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 50, 'No results available for the selected exam year.', 0, 1, 'C');
}

// Output PDF
$filename = "District_Results_" . $exam_year . ".pdf";
$pdf->Output($filename, 'D');

function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $filename);
    $filename = preg_replace('/\s+/', '_', $filename);
    return trim($filename, '_');
}
?>