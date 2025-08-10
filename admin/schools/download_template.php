<?php
// Start output buffering to catch any unintended output
ob_start();

// Include dependencies
require_once '../db_connect.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    ob_end_clean(); // Clear buffer before redirect
    header("Location: ../../login.php");
    exit();
}

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$sheet->setCellValue('A1', 'Center Number');
$sheet->setCellValue('B1', 'School Name');
$sheet->setCellValue('C1', 'Subcounty');
$sheet->setCellValue('D1', 'School Type');

// Fetch all subcounties and school types
$stmt = $conn->query("SELECT subcounty FROM subcounties ORDER BY subcounty");
$subcounties = $stmt->fetch_all(MYSQLI_ASSOC);
$subcounty_list = array_column($subcounties, 'subcounty');

$stmt = $conn->query("SELECT type FROM school_types ORDER BY type");
$school_types = $stmt->fetch_all(MYSQLI_ASSOC);
$school_type_list = array_column($school_types, 'type');

// Add sample row
$sample_subcounty = !empty($subcounty_list) ? $subcounty_list[0] : 'Sample Subcounty';
$sample_school_type = !empty($school_type_list) ? $school_type_list[0] : 'Primary';
$sheet->setCellValue('A2', 'SC001');
$sheet->setCellValue('B2', 'Sample School');
$sheet->setCellValue('C2', $sample_subcounty);
$sheet->setCellValue('D2', $sample_school_type);

// Add instructions in a separate sheet
$instructionSheet = $spreadsheet->createSheet();
$instructionSheet->setTitle('Instructions');
$instructionSheet->setCellValue('A1', 'Instructions for Importing Schools');
$instructionSheet->setCellValue('A2', '1. Use this template to import schools into the Results Management System.');
$instructionSheet->setCellValue('A3', '2. Column A: Enter a unique Center Number (e.g., SC001).');
$instructionSheet->setCellValue('A4', '3. Column B: Enter the School Name.');
$instructionSheet->setCellValue('A5', '4. Column C: Select a Subcounty from the dropdown list. Only listed values are valid.');
$instructionSheet->setCellValue('A6', '5. Column D: Select a School Type from the dropdown list. Only listed values are valid.');
$instructionSheet->setCellValue('A7', '6. Do not modify the headers in row 1.');
$instructionSheet->setCellValue('A8', '7. Save the file and upload it via the "Import Schools from Excel" form.');

// Create a hidden sheet for dropdown lists
$dropdownSheet = $spreadsheet->createSheet();
$dropdownSheet->setTitle('Dropdown Lists');

// Add subcounty list to hidden sheet
foreach ($subcounty_list as $index => $subcounty) {
    $dropdownSheet->setCellValue('A' . ($index + 1), $subcounty);
}

// Add school type list to hidden sheet
foreach ($school_type_list as $index => $type) {
    $dropdownSheet->setCellValue('B' . ($index + 1), $type);
}

// Apply data validation for Subcounty (Column C)
$validation = $sheet->getDataValidation('C2:C1000');
$validation->setType(DataValidation::TYPE_LIST);
$validation->setErrorStyle(DataValidation::STYLE_STOP);
$validation->setAllowBlank(false);
$validation->setShowDropDown(true);
$validation->setFormula1('\'Dropdown Lists\'!$A$1:$A$' . count($subcounty_list));
$validation->setErrorTitle('Invalid Subcounty');
$validation->setError('Please select a valid subcounty from the dropdown list.');
$validation->setShowErrorMessage(true);

// Apply data validation for School Type (Column D)
$validation = $sheet->getDataValidation('D2:D1000');
$validation->setType(DataValidation::TYPE_LIST);
$validation->setErrorStyle(DataValidation::STYLE_STOP);
$validation->setAllowBlank(false);
$validation->setShowDropDown(true);
$validation->setFormula1('\'Dropdown Lists\'!$B$1:$B$' . count($school_type_list));
$validation->setErrorTitle('Invalid School Type');
$validation->setError('Please select a valid school type from the dropdown list.');
$validation->setShowErrorMessage(true);

// Hide the dropdown lists sheet
$dropdownSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

// Clear output buffer and set headers for file download
ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="school_import_template.xlsx"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// Save the file
try {
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Exception $e) {
    error_log("Error generating Excel template: " . $e->getMessage(), 3, '../../setup_errors.log');
    die("Error generating template: " . htmlspecialchars($e->getMessage()));
}

// Close database connection
$conn->close();
exit();
?>