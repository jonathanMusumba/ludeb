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
if ($stmt === false) {
    error_log("generate_district_results.php: Prepare failed for exam_year_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    die("Database error. Please try again later.");
}
$stmt->bind_param('i', $exam_year_id);
$stmt->execute();
$exam_year_result = $stmt->get_result();
$exam_year = ($exam_year_result->num_rows > 0) ? $exam_year_result->fetch_assoc()['exam_year'] : 'N/A';
$stmt->close();

// Fetch schools with results
$schools_query = "
    SELECT s.center_no, s.school_name, s.id
    FROM schools s
    LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    WHERE s.status = 'Active'
    GROUP BY s.id, s.center_no, s.school_name
    ORDER BY s.center_no
";
$schools = [];
$stmt = $conn->prepare($schools_query);
if ($stmt === false) {
    error_log("generate_district_results.php: Prepare failed for schools_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    die("Database error. Please try again later.");
}
$stmt->bind_param('i', $exam_year_id);
$stmt->execute();
$schools_result = $stmt->get_result();
while ($row = $schools_result->fetch_assoc()) {
    $schools[] = $row;
}
$stmt->close();

// Helper function to format subject results
function formatSubjectResult($mark, $grade, $is_absent) {
    if ($is_absent || $mark === null || $mark === '') {
        return '-(X)';
    }
    if ($grade === null || $grade === '') {
        return $mark . '(-)';
    }
    return $mark . '(' . $grade . ')';
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
        global $exam_year;
        $download_time = date('d M, Y H:i');
        // Left: Mock Results [exam_year]
        $this->Cell(60, 10, "Mock Results $exam_year", 0, 0, 'L');
        // Middle: Page X/{nb}
        $this->Cell(70, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        // Right: Download time
        $this->Cell(60, 10, "Download time: $download_time", 0, 0, 'R');
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
            c.id AS candidate_id,
            c.index_number AS index_no,
            c.candidate_name,
            MAX(CASE WHEN s.code = 'ENG' THEN m.mark END) AS eng_marks,
            MAX(CASE WHEN s.code = 'ENG' AND g.grade IS NOT NULL THEN g.grade END) AS eng_grade,
            MAX(CASE WHEN s.code = 'ENG' THEN (m.status = 'ABSENT' OR m.mark = 0 OR m.mark IS NULL) END) AS eng_absent,
            MAX(CASE WHEN s.code = 'SCI' THEN m.mark END) AS sci_marks,
            MAX(CASE WHEN s.code = 'SCI' AND g.grade IS NOT NULL THEN g.grade END) AS sci_grade,
            MAX(CASE WHEN s.code = 'SCI' THEN (m.status = 'ABSENT' OR m.mark = 0 OR m.mark IS NULL) END) AS sci_absent,
            MAX(CASE WHEN s.code = 'MTC' THEN m.mark END) AS mtc_marks,
            MAX(CASE WHEN s.code = 'MTC' AND g.grade IS NOT NULL THEN g.grade END) AS mtc_grade,
            MAX(CASE WHEN s.code = 'MTC' THEN (m.status = 'ABSENT' OR m.mark = 0 OR m.mark IS NULL) END) AS mtc_absent,
            MAX(CASE WHEN s.code = 'SST' THEN m.mark END) AS sst_marks,
            MAX(CASE WHEN s.code = 'SST' AND g.grade IS NOT NULL THEN g.grade END) AS sst_grade,
            MAX(CASE WHEN s.code = 'SST' THEN (m.status = 'ABSENT' OR m.mark = 0 OR m.mark IS NULL) END) AS sst_absent,
            cr.aggregates,
            cr.division
        FROM candidates c
        LEFT JOIN marks m ON c.id = m.candidate_id AND m.exam_year_id = ?
        LEFT JOIN subjects s ON m.subject_id = s.id
        LEFT JOIN grading g ON m.mark >= g.range_from AND m.mark <= g.range_to AND g.subject_id = s.id AND g.exam_year_id = ?
        LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
        WHERE c.school_id = ? AND c.exam_year_id = ?
        GROUP BY c.id, c.index_number, c.candidate_name, cr.aggregates, cr.division
        ORDER BY c.index_number
    ";
    $results = [];
    $stmt = $conn->prepare($results_query);
    if ($stmt === false) {
        error_log("generate_district_results.php: Prepare failed for results_query, school_id=$school_id: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
        continue;
    }
    $stmt->bind_param('iiiii', $exam_year_id, $exam_year_id, $exam_year_id, $school_id, $exam_year_id);
    if (!$stmt->execute()) {
        error_log("generate_district_results.php: Execute failed for results_query, school_id=$school_id: " . $stmt->error, 3, __DIR__ . '/logs/setup_errors.log');
        $stmt->close();
        continue;
    }
    $results_result = $stmt->get_result();
    if ($results_result === false) {
        error_log("generate_district_results.php: Get result failed for results_query, school_id=$school_id: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
        $stmt->close();
        continue;
    }
    while ($row = $results_result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();

    if (empty($results)) {
        continue; // Skip schools with no candidates
    }

    // Add page for this school
    $pdf->AddPage();
    $school_count++;

    // School Header - More modest design
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 7, strtoupper($exam_body), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 6, "PLE MOCK EXAMINATIONS $exam_year", 0, 1, 'C');
    $pdf->Ln(8);
    
    // Center and School Name
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, "CENTER NO: " . $school['center_no'] . " | " . strtoupper($school['school_name']), 0, 1, 'L');
    $pdf->Ln(6);

    // Results table header - Modest design with subtle background
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(240, 240, 240); // Light gray background for headers

    $header = ['Index No', 'Candidate Name', 'English', 'Science', 'Mathematics', 'Social Studies', 'AGG', 'Div'];
    $widths = [20, 50, 20, 20, 25, 30, 15, 15];

    // Header row with subtle background, no borders
    foreach ($header as $i => $col) {
        $pdf->Cell($widths[$i], 8, $col, 0, 0, 'L', true);
    }
    $pdf->Ln();
    
    // Add a subtle line under header
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + array_sum($widths), $pdf->GetY());
    $pdf->Ln(2);

    // Data rows - Clean, no borders
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetFillColor(250, 250, 250); // Very light alternating background
    $fill = false;

    $division_counts = [
        'Division 1' => 0,
        'Division 2' => 0,
        'Division 3' => 0,
        'Division 4' => 0,
        'Ungraded' => 0,
        'X' => 0
    ];
    $total_candidates = count($results);
    $aggregate_values = [];
    $best_candidates = [];
    $worst_candidates = [];

    foreach ($results as $result) {
        $eng = formatSubjectResult($result['eng_marks'], $result['eng_grade'], $result['eng_absent']);
        $sci = formatSubjectResult($result['sci_marks'], $result['sci_grade'], $result['sci_absent']);
        $mtc = formatSubjectResult($result['mtc_marks'], $result['mtc_grade'], $result['mtc_absent']);
        $sst = formatSubjectResult($result['sst_marks'], $result['sst_grade'], $result['sst_absent']);
        
        $aggregate = ($result['division'] === 'X' || $result['aggregates'] === null || $result['aggregates'] == 0) ? '-(X)' : $result['aggregates'];
        $division = $result['division'] ? getDivisionShort($result['division']) : 'X';
        
        // Data rows without borders
        $pdf->Cell($widths[0], 6, $result['index_no'], 0, 0, 'C', $fill);
        $pdf->Cell($widths[1], 6, substr($result['candidate_name'], 0, 25), 0, 0, 'L', $fill);
        $pdf->Cell($widths[2], 6, $eng, 0, 0, 'C', $fill);
        $pdf->Cell($widths[3], 6, $sci, 0, 0, 'C', $fill);
        $pdf->Cell($widths[4], 6, $mtc, 0, 0, 'C', $fill);
        $pdf->Cell($widths[5], 6, $sst, 0, 0, 'C', $fill);
        $pdf->Cell($widths[6], 6, $aggregate, 0, 0, 'C', $fill);
        $pdf->Cell($widths[7], 6, $division, 0, 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;

        $division_key = $result['division'] ?: 'X';
        if (isset($division_counts[$division_key])) {
            $division_counts[$division_key]++;
        }
        if ($result['division'] !== 'X' && is_numeric($result['aggregates']) && $result['aggregates'] > 0) {
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

    // Summary Section - Modest design
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'SUMMARY', 0, 1, 'C');
    $pdf->Ln(3);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'Division Summary', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);

    $summary_headers = ['Division', '1', '2', '3', '4', 'U', 'X', 'Total'];
    $summary_widths = [25, 15, 15, 15, 15, 15, 15, 20];

    // Summary table header with subtle background
    $pdf->SetFillColor(240, 240, 240);
    foreach ($summary_headers as $i => $header) {
        $pdf->Cell($summary_widths[$i], 6, $header, 0, 0, 'C', true);
    }
    $pdf->Ln();

    // Add subtle line under summary header
    $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + array_sum($summary_widths), $pdf->GetY());
    $pdf->Ln(1);

    $summary_data = [
        'Candidates',
        $division_counts['Division 1'],
        $division_counts['Division 2'],
        $division_counts['Division 3'],
        $division_counts['Division 4'],
        $division_counts['Ungraded'],
        $division_counts['X'],
        $total_candidates
    ];

    // Summary data row without borders
    foreach ($summary_data as $i => $data) {
        $pdf->Cell($summary_widths[$i], 6, $data, 0, 0, 'C', false);
    }
    $pdf->Ln(8);

    // Statistics section
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'Statistics', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 8);

    $pdf->Cell(50, 5, 'Average Aggregate:', 0, 0, 'L');
    $pdf->Cell(30, 5, $average_aggregate, 0, 1, 'L');
    $pdf->Cell(50, 5, 'Best Candidate Aggregate:', 0, 0, 'L');
    $pdf->Cell(30, 5, $best_aggregate, 0, 1, 'L');
    $pdf->Cell(50, 5, 'Worst Candidate Aggregate:', 0, 0, 'L');
    $pdf->Cell(30, 5, $worst_aggregate, 0, 1, 'L');

    $pdf->Ln(5);

    // Best candidates section
    if (!empty($best_5_candidates)) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 6, 'Best Candidates (Top 5)', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        foreach ($best_5_candidates as $i => $candidate) {
            $pdf->Cell(8, 4, ($i + 1) . '.', 0, 0, 'L');
            $pdf->Cell(55, 4, substr($candidate['name'], 0, 30), 0, 0, 'L');
            $pdf->Cell(22, 4, $candidate['index'], 0, 0, 'L');
            $pdf->Cell(18, 4, 'AGG: ' . $candidate['aggregate'], 0, 0, 'L');
            $pdf->Cell(15, 4, 'Div: ' . getDivisionShort($candidate['division']), 0, 1, 'L');
        }
        $pdf->Ln(3);
    }

    // Worst candidates section
    if (!empty($worst_5_candidates)) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 6, 'Worst Candidates (Bottom 5)', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        foreach ($worst_5_candidates as $i => $candidate) {
            $pdf->Cell(8, 4, ($i + 1) . '.', 0, 0, 'L');
            $pdf->Cell(55, 4, substr($candidate['name'], 0, 30), 0, 0, 'L');
            $pdf->Cell(22, 4, $candidate['index'], 0, 0, 'L');
            $pdf->Cell(18, 4, 'AGG: ' . $candidate['aggregate'], 0, 0, 'L');
            $pdf->Cell(15, 4, 'Div: ' . getDivisionShort($candidate['division']), 0, 1, 'L');
        }
    }

    // Log school results
    error_log("generate_district_results.php: Processed school_id=$school_id, school_name={$school['school_name']}, " .
              "total_candidates=$total_candidates, division_x={$division_counts['X']}", 
              3, __DIR__ . '/logs/setup_errors.log');
}

// Check if we processed any schools
if ($school_count == 0) {
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 50, 'No results available for the selected exam year.', 0, 1, 'C');
}

// Output PDF with corrected filename
$filename = "District_Results_" . $exam_year . ".pdf";
// Sanitize only the base name, preserving the .pdf extension
$base_name = "District_Results_" . $exam_year;
$base_name = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $base_name);
$base_name = preg_replace('/\s+/', '_', $base_name);
$base_name = trim($base_name, '_');
$filename = $base_name . ".pdf";

// Set headers to ensure proper download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

ob_clean(); // Clear output buffer to prevent corruption
$pdf->Output($filename, 'D');
exit;
?>