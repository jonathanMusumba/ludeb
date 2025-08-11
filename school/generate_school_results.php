<?php
session_start();
require_once 'db_connect.php';
require_once '../lib/fpdf.php';

// Check if user is logged in and has proper access
if (!isset($_SESSION['school_id'])) {
    header('Location: ../login.php');
    exit;
}

$schoolId = $_SESSION['school_id'];
$exam_year_id = isset($_GET['exam_year_id']) ? (int)$_GET['exam_year_id'] : 0;
$format = isset($_GET['format']) ? $_GET['format'] : '';

// Validate inputs
if (!$exam_year_id || !in_array($format, ['pdf', 'excel'])) {
    header("Location: dashboard.php?error=invalid_parameters");
    exit();
}

// Fetch exam body and year details
$exam_body_query = "SELECT board_name FROM settings WHERE id = 1";
$exam_body_result = $conn->query($exam_body_query);
$exam_body = ($exam_body_result->num_rows > 0) ? $exam_body_result->fetch_assoc()['board_name'] : 'Luuka Examination Board';

$exam_year_query = "SELECT exam_year FROM exam_years WHERE id = ?";
$stmt = $conn->prepare($exam_year_query);
if ($stmt === false) {
    error_log("generate_school_results.php: Prepare failed for exam_year_query: " . $conn->error);
    die("Database error. Please try again later.");
}
$stmt->bind_param('i', $exam_year_id);
$stmt->execute();
$exam_year_result = $stmt->get_result();
$exam_year = ($exam_year_result->num_rows > 0) ? $exam_year_result->fetch_assoc()['exam_year'] : 'N/A';
$stmt->close();

// Fetch school details
$school_query = "SELECT center_no, school_name FROM schools WHERE id = ?";
$stmt = $conn->prepare($school_query);
if ($stmt === false) {
    error_log("generate_school_results.php: Prepare failed for school_query: " . $conn->error);
    die("Database error. Please try again later.");
}
$stmt->bind_param('i', $schoolId);
$stmt->execute();
$school_result = $stmt->get_result();
$school = $school_result->fetch_assoc();
$stmt->close();

if (!$school) {
    header("Location: dashboard.php?error=school_not_found");
    exit();
}

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
    error_log("generate_school_results.php: Prepare failed for results_query: " . $conn->error);
    die("Database error. Please try again later.");
}
$stmt->bind_param('iiiii', $exam_year_id, $exam_year_id, $exam_year_id, $schoolId, $exam_year_id);
if (!$stmt->execute()) {
    error_log("generate_school_results.php: Execute failed for results_query: " . $stmt->error);
    die("Database error. Please try again later.");
}
$results_result = $stmt->get_result();
while ($row = $results_result->fetch_assoc()) {
    $results[] = $row;
}
$stmt->close();

if (empty($results)) {
    header("Location: dashboard.php?error=no_results_found");
    exit();
}

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

// Calculate statistics
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

foreach ($results as $result) {
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

// Generate based on format
if ($format === 'pdf') {
    // PDF Generation
    class SchoolResultsPDF extends FPDF {
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            global $exam_year, $school;
            $download_time = date('d M, Y H:i');
            // Left: School Results [exam_year]
            $this->Cell(60, 10, "School Results $exam_year", 0, 0, 'L');
            // Middle: Page X/{nb}
            $this->Cell(70, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
            // Right: Download time
            $this->Cell(60, 10, "Downloaded: $download_time", 0, 0, 'R');
        }
    }

    $pdf = new SchoolResultsPDF();
    $pdf->AliasNbPages();
    $pdf->SetMargins(10, 15, 10);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    // School Header
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, strtoupper($exam_body), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 6, "PLE MOCK EXAMINATIONS $exam_year", 0, 1, 'C');
    $pdf->Ln(8);
    
    // School Details
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, "CENTER NO: " . $school['center_no'] . " | " . strtoupper($school['school_name']), 0, 1, 'L');
    $pdf->Ln(8);

    // Results table header
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(240, 240, 240);

    $header = ['Index No', 'Candidate Name', 'English', 'Science', 'Mathematics', 'Social Studies', 'AGG', 'Div'];
    $widths = [20, 50, 20, 20, 25, 30, 15, 15];

    foreach ($header as $i => $col) {
        $pdf->Cell($widths[$i], 8, $col, 0, 0, 'L', true);
    }
    $pdf->Ln();
    
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + array_sum($widths), $pdf->GetY());
    $pdf->Ln(2);

    // Data rows
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetFillColor(250, 250, 250);
    $fill = false;

    foreach ($results as $result) {
        $eng = formatSubjectResult($result['eng_marks'], $result['eng_grade'], $result['eng_absent']);
        $sci = formatSubjectResult($result['sci_marks'], $result['sci_grade'], $result['sci_absent']);
        $mtc = formatSubjectResult($result['mtc_marks'], $result['mtc_grade'], $result['mtc_absent']);
        $sst = formatSubjectResult($result['sst_marks'], $result['sst_grade'], $result['sst_absent']);
        
        $aggregate = ($result['division'] === 'X' || $result['aggregates'] === null || $result['aggregates'] == 0) ? '-(X)' : $result['aggregates'];
        $division = $result['division'] ? getDivisionShort($result['division']) : 'X';
        
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
    }

    // Summary Section
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'SUMMARY', 0, 1, 'C');
    $pdf->Ln(3);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'Division Summary', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);

    $summary_headers = ['Division', '1', '2', '3', '4', 'U', 'X', 'Total'];
    $summary_widths = [25, 15, 15, 15, 15, 15, 15, 20];

    $pdf->SetFillColor(240, 240, 240);
    foreach ($summary_headers as $i => $header) {
        $pdf->Cell($summary_widths[$i], 6, $header, 0, 0, 'C', true);
    }
    $pdf->Ln();

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

    // Output PDF
    $filename = "School_Results_" . $school['center_no'] . "_" . $exam_year . ".pdf";
    $base_name = "School_Results_" . $school['center_no'] . "_" . $exam_year;
    $base_name = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $base_name);
    $base_name = preg_replace('/\s+/', '_', $base_name);
    $base_name = trim($base_name, '_');
    $filename = $base_name . ".pdf";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    ob_clean();
    $pdf->Output($filename, 'D');
    exit;

} elseif ($format === 'excel') {
    // Excel Generation
    $filename = "School_Results_" . $school['center_no'] . "_" . $exam_year . ".xls";
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Clear output buffer
    ob_clean();

    // Start Excel output
    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
    echo "<head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'></head>";
    echo "<body>";

    // School Header
    echo "<table>";
    echo "<tr><td colspan='8' style='text-align:center; font-weight:bold; font-size:14px;'>" . strtoupper($exam_body) . "</td></tr>";
    echo "<tr><td colspan='8' style='text-align:center; font-size:12px;'>PLE MOCK EXAMINATIONS $exam_year</td></tr>";
    echo "<tr><td colspan='8'>&nbsp;</td></tr>";
    echo "<tr><td colspan='8' style='font-weight:bold;'>CENTER NO: " . $school['center_no'] . " | " . strtoupper($school['school_name']) . "</td></tr>";
    echo "<tr><td colspan='8'>&nbsp;</td></tr>";

    // Results table header
    echo "<tr style='background-color:#f0f0f0; font-weight:bold;'>";
    echo "<td>Index No</td>";
    echo "<td>Candidate Name</td>";
    echo "<td>English</td>";
    echo "<td>Science</td>";
    echo "<td>Mathematics</td>";
    echo "<td>Social Studies</td>";
    echo "<td>AGG</td>";
    echo "<td>Div</td>";
    echo "</tr>";

    // Data rows
    foreach ($results as $result) {
        $eng = formatSubjectResult($result['eng_marks'], $result['eng_grade'], $result['eng_absent']);
        $sci = formatSubjectResult($result['sci_marks'], $result['sci_grade'], $result['sci_absent']);
        $mtc = formatSubjectResult($result['mtc_marks'], $result['mtc_grade'], $result['mtc_absent']);
        $sst = formatSubjectResult($result['sst_marks'], $result['sst_grade'], $result['sst_absent']);
        
        $aggregate = ($result['division'] === 'X' || $result['aggregates'] === null || $result['aggregates'] == 0) ? '-(X)' : $result['aggregates'];
        $division = $result['division'] ? getDivisionShort($result['division']) : 'X';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($result['index_no']) . "</td>";
        echo "<td>" . htmlspecialchars($result['candidate_name']) . "</td>";
        echo "<td>" . htmlspecialchars($eng) . "</td>";
        echo "<td>" . htmlspecialchars($sci) . "</td>";
        echo "<td>" . htmlspecialchars($mtc) . "</td>";
        echo "<td>" . htmlspecialchars($sst) . "</td>";
        echo "<td>" . htmlspecialchars($aggregate) . "</td>";
        echo "<td>" . htmlspecialchars($division) . "</td>";
        echo "</tr>";
    }

    // Summary Section
    echo "<tr><td colspan='8'>&nbsp;</td></tr>";
    echo "<tr><td colspan='8' style='text-align:center; font-weight:bold; font-size:12px;'>SUMMARY</td></tr>";
    echo "<tr><td colspan='8'>&nbsp;</td></tr>";

    // Division Summary
    echo "<tr><td colspan='8' style='font-weight:bold;'>Division Summary</td></tr>";
    echo "<tr style='background-color:#f0f0f0; font-weight:bold;'>";
    echo "<td>Division</td><td>1</td><td>2</td><td>3</td><td>4</td><td>U</td><td>X</td><td>Total</td>";
    echo "</tr>";
    echo "<tr>";
    echo "<td>Candidates</td>";
    echo "<td>" . $division_counts['Division 1'] . "</td>";
    echo "<td>" . $division_counts['Division 2'] . "</td>";
    echo "<td>" . $division_counts['Division 3'] . "</td>";
    echo "<td>" . $division_counts['Division 4'] . "</td>";
    echo "<td>" . $division_counts['Ungraded'] . "</td>";
    echo "<td>" . $division_counts['X'] . "</td>";
    echo "<td>" . $total_candidates . "</td>";
    echo "</tr>";

    // Statistics
    echo "<tr><td colspan='8'>&nbsp;</td></tr>";
    echo "<tr><td colspan='8' style='font-weight:bold;'>Statistics</td></tr>";
    echo "<tr><td>Average Aggregate:</td><td>" . $average_aggregate . "</td><td colspan='6'></td></tr>";
    echo "<tr><td>Best Candidate Aggregate:</td><td>" . $best_aggregate . "</td><td colspan='6'></td></tr>";
    echo "<tr><td>Worst Candidate Aggregate:</td><td>" . $worst_aggregate . "</td><td colspan='6'></td></tr>";

    // Best candidates
    if (!empty($best_5_candidates)) {
        echo "<tr><td colspan='8'>&nbsp;</td></tr>";
        echo "<tr><td colspan='8' style='font-weight:bold;'>Best Candidates (Top 5)</td></tr>";
        foreach ($best_5_candidates as $i => $candidate) {
            echo "<tr>";
            echo "<td>" . ($i + 1) . ".</td>";
            echo "<td>" . htmlspecialchars($candidate['name']) . "</td>";
            echo "<td>" . htmlspecialchars($candidate['index']) . "</td>";
            echo "<td>AGG: " . $candidate['aggregate'] . "</td>";
            echo "<td>Div: " . getDivisionShort($candidate['division']) . "</td>";
            echo "<td colspan='3'></td>";
            echo "</tr>";
        }
    }

    // Worst candidates
    if (!empty($worst_5_candidates)) {
        echo "<tr><td colspan='8'>&nbsp;</td></tr>";
        echo "<tr><td colspan='8' style='font-weight:bold;'>Worst Candidates (Bottom 5)</td></tr>";
        foreach ($worst_5_candidates as $i => $candidate) {
            echo "<tr>";
            echo "<td>" . ($i + 1) . ".</td>";
            echo "<td>" . htmlspecialchars($candidate['name']) . "</td>";
            echo "<td>" . htmlspecialchars($candidate['index']) . "</td>";
            echo "<td>AGG: " . $candidate['aggregate'] . "</td>";
            echo "<td>Div: " . getDivisionShort($candidate['division']) . "</td>";
            echo "<td colspan='3'></td>";
            echo "</tr>";
        }
    }

    echo "</table>";
    echo "</body></html>";
    exit;
}

// If we reach here, invalid format
header("Location: dashboard.php?error=invalid_format");
exit();
?>