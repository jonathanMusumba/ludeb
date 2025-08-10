<?php
$page_title = 'Candidate Results';

ob_start();
require_once '../db_connect.php'; // MySQLi connection
require_once '../../vendor/autoload.php'; // Composer autoload for Dompdf and PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;

// Check user session and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header('Location: ../login.php');
    exit();
}

// Get active exam year
$result = $conn->query("SELECT id, exam_year FROM exam_years WHERE status = 'Active' LIMIT 1");
$active_year = $result->fetch_assoc();
$exam_year_id = isset($_GET['exam_year_id']) ? (int)$_GET['exam_year_id'] : $active_year['id'];

// Handle search
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$results = [];
$school_name = '';
if ($search_term) {
    $stmt = $conn->prepare("
        SELECT c.id, c.index_number, c.candidate_name, sch.school_name,
               MAX(CASE WHEN s.code = 'ENG' THEN r.marks END) AS eng_mark,
               MAX(CASE WHEN s.code = 'ENG' THEN r.grade END) AS eng_grade,
               MAX(CASE WHEN s.code = 'SCI' THEN r.marks END) AS sci_mark,
               MAX(CASE WHEN s.code = 'SCI' THEN r.grade END) AS sci_grade,
               MAX(CASE WHEN s.code = 'SST' THEN r.marks END) AS sst_mark,
               MAX(CASE WHEN s.code = 'SST' THEN r.grade END) AS sst_grade,
               MAX(CASE WHEN s.code = 'MTC' THEN r.marks END) AS mtc_mark,
               MAX(CASE WHEN s.code = 'MTC' THEN r.grade END) AS mtc_grade,
               cr.aggregates, cr.division
        FROM candidates c
        JOIN school_results r ON c.id = r.candidate_id
        JOIN subjects s ON r.subject_code = s.code
        JOIN schools sch ON c.school_id = sch.id
        JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
        WHERE (c.index_number LIKE ? OR c.candidate_name LIKE ?) AND c.exam_year_id = ?
        GROUP BY c.id, c.index_number, c.candidate_name, sch.school_name, cr.aggregates, cr.division
        ORDER BY c.index_number
    ");
    $search_like = "%$search_term%";
    $stmt->bind_param('issi', $exam_year_id, $search_like, $search_like, $exam_year_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
    if ($results) {
        $school_name = $results[0]['school_name'];
    }
}

// Summary table
$summary = [];
if ($search_term && $results) {
    $stmt = $conn->prepare("
        SELECT division, COUNT(*) AS candidate_count
        FROM candidate_results cr
        JOIN candidates c ON cr.candidate_id = c.id
        WHERE c.exam_year_id = ? AND (c.index_number LIKE ? OR c.candidate_name LIKE ?)
        GROUP BY division
    ");
    $search_like = "%$search_term%";
    $stmt->bind_param('iss', $exam_year_id, $search_like, $search_like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $summary[] = $row;
    }
    $stmt->close();
}

// Export to Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel' && $search_term) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Candidate Results');

    // Header
    $sheet->setCellValue('A1', 'School: ' . $school_name);
    $sheet->setCellValue('A2', 'Mock Results ' . ($exam_year_id == $active_year['id'] ? 'Active Year' : $exam_year_id));
    $sheet->setCellValue('A4', 'Index Number');
    $sheet->setCellValue('B4', 'Candidate Name');
    $sheet->setCellValue('C4', 'ENG');
    $sheet->setCellValue('D4', 'SCI');
    $sheet->setCellValue('E4', 'SST');
    $sheet->setCellValue('F4', 'MTC');
    $sheet->setCellValue('G4', 'Aggregates');
    $sheet->setCellValue('H4', 'Division');

    // Data
    $row = 5;
    foreach ($results as $result) {
        $sheet->setCellValue("A$row", $result['index_number']);
        $sheet->setCellValue("B$row", $result['candidate_name']);
        $sheet->setCellValue("C$row", ($result['eng_mark'] !== null ? $result['eng_mark'] . '(' . $result['eng_grade'] . ')' : '-'));
        $sheet->setCellValue("D$row", ($result['sci_mark'] !== null ? $result['sci_mark'] . '(' . $result['sci_grade'] . ')' : '-'));
        $sheet->setCellValue("E$row", ($result['sst_mark'] !== null ? $result['sst_mark'] . '(' . $result['sst_grade'] . ')' : '-'));
        $sheet->setCellValue("F$row", ($result['mtc_mark'] !== null ? $result['mtc_mark'] . '(' . $result['mtc_grade'] . ')' : '-'));
        $sheet->setCellValue("G$row", $result['aggregates']);
        $sheet->setCellValue("H$row", $result['division']);
        $row++;
    }

    // Summary
    $sheet->setCellValue("A$row", 'Summary');
    $row++;
    $sheet->setCellValue("A$row", 'Division');
    $sheet->setCellValue("B$row", 'Candidates');
    $row++;
    foreach ($summary as $sum) {
        $sheet->setCellValue("A$row", $sum['division']);
        $sheet->setCellValue("B$row", $sum['candidate_count']);
        $row++;
    }

    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="candidate_results_' . $exam_year_id . '.xlsx"');
    $writer->save('php://output');
    exit();
}

// Export to PDF
if (isset($_GET['export']) && $_GET['export'] === 'pdf' && $search_term) {
    $dompdf = new Dompdf();
    $html = '
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h1, h2 { text-align: center; }
    </style>
    <h1>' . htmlspecialchars($school_name) . '</h1>
    <h2>Mock Results ' . ($exam_year_id == $active_year['id'] ? 'Active Year' : $exam_year_id) . '</h2>
    <table>
        <tr>
            <th>Index Number</th>
            <th>Candidate Name</th>
            <th>ENG</th>
            <th>SCI</th>
            <th>SST</th>
            <th>MTC</th>
            <th>Aggregates</th>
            <th>Division</th>
        </tr>';
    foreach ($results as $result) {
        $html .= '
        <tr>
            <td>' . htmlspecialchars($result['index_number']) . '</td>
            <td>' . htmlspecialchars($result['candidate_name']) . '</td>
            <td>' . htmlspecialchars($result['eng_mark'] !== null ? $result['eng_mark'] . '(' . $result['eng_grade'] . ')' : '-') . '</td>
            <td>' . htmlspecialchars($result['sci_mark'] !== null ? $result['sci_mark'] . '(' . $result['sci_grade'] . ')' : '-') . '</td>
            <td>' . htmlspecialchars($result['sst_mark'] !== null ? $result['sst_mark'] . '(' . $result['sst_grade'] . ')' : '-') . '</td>
            <td>' . htmlspecialchars($result['mtc_mark'] !== null ? $result['mtc_mark'] . '(' . $result['mtc_grade'] . ')' : '-') . '</td>
            <td>' . htmlspecialchars($result['aggregates']) . '</td>
            <td>' . htmlspecialchars($result['division']) . '</td>
        </tr>';
    }
    $html .= '</table>
    <h3>Summary</h3>
    <table>
        <tr><th>Division</th><th>Candidates</th></tr>';
    foreach ($summary as $sum) {
        $html .= '
        <tr>
            <td>' . htmlspecialchars($sum['division']) . '</td>
            <td>' . htmlspecialchars($sum['candidate_count']) . '</td>
        </tr>';
    }
    $html .= '</table>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('candidate_results_' . $exam_year_id . '.pdf', ['Attachment' => true]);
    exit();
}

// Render content
$content = '
<div class="page-header">
    <h1 class="page-title">Candidate Results</h1>
    <nav aria-label="breadcrumb" class="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Candidate Results</li>
        </ol>
    </nav>
</div>
<div class="filter-container">
    <form method="GET">
        <div class="row g-3 align-items-center">
            <div class="col-auto">
                <label>Search Candidate (Index/Name):</label>
            </div>
            <div class="col-auto">
                <input type="text" name="search" class="form-control" value="' . htmlspecialchars($search_term) . '" placeholder="Index number or name">
            </div>
            <div class="col-auto">
                <label>Exam Year:</label>
            </div>
            <div class="col-auto">
                <select name="exam_year_id" class="form-select" onchange="this.form.submit()">
                    ';
$result = $conn->query("SELECT id, exam_year FROM exam_years ORDER BY exam_year DESC");
while ($year = $result->fetch_assoc()) {
    $content .= '<option value="' . $year['id'] . '" ' . ($exam_year_id == $year['id'] ? 'selected' : '') . '>' 
                . htmlspecialchars($year['exam_year']) . ($year['id'] == $active_year['id'] ? ' (Active)' : '') . '</option>';
}
$content .= '
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-enhanced">Search</button>
            </div>
        </div>
    </form>
</div>';

if ($search_term && $results) {
    $content .= '
    <div class="dashboard-card">
        <h3>' . htmlspecialchars($school_name) . ' - Mock Results ' . ($exam_year_id == $active_year['id'] ? 'Active Year' : $exam_year_id) . '</h3>
        <div class="mb-3">
            <a href="?search=' . urlencode($search_term) . '&exam_year_id=' . $exam_year_id . '&export=pdf" class="btn btn-enhanced btn-sm"><i class="fas fa-file-pdf"></i> Download PDF</a>
            <a href="?search=' . urlencode($search_term) . '&exam_year_id=' . $exam_year_id . '&export=excel" class="btn btn-enhanced btn-sm"><i class="fas fa-file-excel"></i> Download Excel</a>
        </div>
        <table class="table-enhanced">
            <thead>
                <tr>
                    <th>Index Number</th>
                    <th>Candidate Name</th>
                    <th>ENG</th>
                    <th>SCI</th>
                    <th>SST</th>
                    <th>MTC</th>
                    <th>Aggregates</th>
                    <th>Division</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($results as $result) {
        $content .= '
                <tr>
                    <td>' . htmlspecialchars($result['index_number']) . '</td>
                    <td>' . htmlspecialchars($result['candidate_name']) . '</td>
                    <td>' . htmlspecialchars($result['eng_mark'] !== null ? $result['eng_mark'] . '(' . $result['eng_grade'] . ')' : '-') . '</td>
                    <td>' . htmlspecialchars($result['sci_mark'] !== null ? $result['sci_mark'] . '(' . $result['sci_grade'] . ')' : '-') . '</td>
                    <td>' . htmlspecialchars($result['sst_mark'] !== null ? $result['sst_mark'] . '(' . $result['sst_grade'] . ')' : '-') . '</td>
                    <td>' . htmlspecialchars($result['mtc_mark'] !== null ? $result['mtc_mark'] . '(' . $result['mtc_grade'] . ')' : '-') . '</td>
                    <td>' . htmlspecialchars($result['aggregates']) . '</td>
                    <td>' . htmlspecialchars($result['division']) . '</td>
                </tr>';
    }
    $content .= '
            </tbody>
        </table>
        <h4>Summary</h4>
        <table class="table-enhanced">
            <thead>
                <tr>
                    <th>Division</th>
                    <th>Candidates</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($summary as $sum) {
        $content .= '
                <tr>
                    <td>' . htmlspecialchars($sum['division']) . '</td>
                    <td>' . htmlspecialchars($sum['candidate_count']) . '</td>
                </tr>';
    }
    $content .= '
            </tbody>
        </table>
    </div>';
}

$content .= '
<script>
$(document).ready(function() {
    window.showNotification("Candidate results loaded successfully", "success");
});
</script>';

require_once '../layout.php';
?>