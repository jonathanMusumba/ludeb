<?php
ob_start();
require_once '../db_connect.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    ob_clean();
    header("Location: " . $root_url . "login.php");
    exit();
}

$center_no = isset($_GET['center_no']) ? trim($_GET['center_no']) : '';
if (!preg_match('/^\d{6}$/', $center_no)) {
    ob_clean();
    die("Invalid center number");
}

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$sheet->setCellValue('A1', 'IndexNo');
$sheet->setCellValue('B1', 'Candidate_Name');
$sheet->setCellValue('C1', 'Gender');

// Add sample row
$sheet->setCellValue('A2', $center_no . '/001');
$sheet->setCellValue('B2', 'Sample Student');
$sheet->setCellValue('C2', 'Male');

// Add instructions in a separate sheet
$instructionSheet = $spreadsheet->createSheet();
$instructionSheet->setTitle('Instructions');
$instructionSheet->setCellValue('A1', 'Instructions for Importing Candidates');
$instructionSheet->setCellValue('A2', '1. Use this template to import candidates into the Results Management System.');
$instructionSheet->setCellValue('A3', '2. Column A (IndexNo): Enter a unique Index Number (e.g., XXXXXX/001, where XXXXXX matches the school center number).');
$instructionSheet->setCellValue('A4', '3. Column B (Candidate_Name): Enter the full name of the candidate.');
$instructionSheet->setCellValue('A5', '4. Column C (Gender): Enter "Male" or "Female" (or "M" or "F").');
$instructionSheet->setCellValue('A6', '5. Do not modify the headers in row 1.');
$instructionSheet->setCellValue('A7', '6. Save the file and upload it via the "Upload Candidates" form.');

// Clear output buffer and set headers for file download
ob_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="candidates_template.xlsx"');
header('Cache-Control: max-age=0');
header('Pragma: public');

try {
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Exception $e) {
    error_log("Error generating candidates template: " . $e->getMessage(), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    die("Error generating template: " . htmlspecialchars($e->getMessage()));
}
exit();
?>