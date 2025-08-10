<?php
session_start();
require_once '../db_connect.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header("Location: ../login.php");
    exit();
}

$center_no = isset($_GET['center_no']) ? trim($_GET['center_no']) : '';
if (empty($center_no)) {
    die("Invalid Center Number");
}

try {
    // Fetch school data
    $stmt = $conn->prepare("SELECT id, school_name FROM schools WHERE center_no = ?");
    $stmt->bind_param("s", $center_no);
    $stmt->execute();
    $school = $stmt->get_result()->fetch_assoc();
    if (!$school) {
        throw new Exception("School not found");
    }
    $school_id = $school['id'];

    // Fetch results
    $stmt = $conn->prepare("SELECT c.index_number, c.candidate_name, s.name as subject_name, 
                            r.mark, r.score, r.division
                            FROM results r
                            JOIN candidates c ON r.candidate_id = c.id
                            JOIN subjects s ON r.subject_id = s.id
                            WHERE r.school_id = ?
                            ORDER BY c.index_number, s.name");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Create spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Results');

    // Set headers
    $headers = ['Index Number', 'Candidate Name', 'Subject', 'Mark', 'Score', 'Division'];
    $sheet->fromArray($headers, null, 'A1');

    // Fill data
    $row_num = 2;
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValue("A$row_num", $row['index_number']);
        $sheet->setCellValue("B$row_num", $row['candidate_name']);
        $sheet->setCellValue("C$row_num", $row['subject_name']);
        $sheet->setCellValue("D$row_num", $row['mark']);
        $sheet->setCellValue("E$row_num", $row['score']);
        $sheet->setCellValue("F$row_num", $row['division']);
        $row_num++;
    }

    // Auto-size columns
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Log action
    $user_id = $_SESSION['user_id'];
    $conn->query("CALL log_action('Download Results', $user_id, 'Downloaded Excel results for school ID $school_id')");

    // Output Excel file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"results_{$center_no}.xlsx\"");
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Exception $e) {
    error_log("Download Results Error: " . $e->getMessage(), 3, '../../../setup_errors.log');
    die("Error: " . $e->getMessage());
} finally {
    $conn->close();
}
?>