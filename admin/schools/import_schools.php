<?php
// Start output buffering
ob_start();

require_once '../db_connect.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    ob_end_clean();
    header("Location: " . $root_url . "login.php");
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed", 3, '../../setup_errors.log');
    ob_end_clean();
    header("Location: {$base_url}schools/add_school.php?error=" . urlencode("Invalid CSRF token"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
    try {
        // Validate file type
        $allowed_types = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!in_array($_FILES['excel_file']['type'], $allowed_types)) {
            throw new Exception("Invalid file type. Please upload an Excel file (.xls or .xlsx)");
        }

        // Load spreadsheet
        $file = $_FILES['excel_file']['tmp_name'];
        $spreadsheet = IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray(null, true, true, true);

        // Validate headers
        $expected_headers = ['A' => 'Center Number', 'B' => 'School Name', 'C' => 'Subcounty', 'D' => 'School Type'];
        $row_1 = array_intersect_key($data[1], $expected_headers);
        if ($row_1 !== $expected_headers) {
            throw new Exception("Invalid Excel file format. Please use the provided template.");
        }

        // Fetch subcounties and school types for mapping
        $subcounties = [];
        $stmt = $conn->query("SELECT id, LOWER(subcounty) AS subcounty FROM subcounties");
        while ($row = $stmt->fetch_assoc()) {
            $subcounties[$row['subcounty']] = $row['id'];
        }

        $school_types = [];
        $stmt = $conn->query("SELECT id, LOWER(type) AS type FROM school_types");
        while ($row = $stmt->fetch_assoc()) {
            $school_types[$row['type']] = $row['id'];
        }

        if (empty($subcounties) || empty($school_types)) {
            throw new Exception("No subcounties or school types found in the database. Please add them first.");
        }

        // Prepare SQL statement
        $stmt = $conn->prepare("
            INSERT INTO schools (center_no, school_name, subcounty_id, school_type_id, results_status) 
            VALUES (?, ?, ?, ?, 'Not Declared')
            ON DUPLICATE KEY UPDATE 
            school_name = VALUES(school_name), 
            subcounty_id = VALUES(subcounty_id), 
            school_type_id = VALUES(school_type_id)
        ");
        $stmt->bind_param("ssii", $center_no, $school_name, $subcounty_id, $school_type_id);

        $user_id = $_SESSION['user_id'];
        $success_count = 0;
        $skipped_count = 0;

        foreach ($data as $index => $row) {
            if ($index === 1) continue; // Skip header row

            $center_no = trim($row['A'] ?? '');
            $school_name = trim($row['B'] ?? '');
            $subcounty_name = trim(strtolower($row['C'] ?? ''));
            $school_type = trim(strtolower($row['D'] ?? ''));

            // Skip empty rows
            if (empty($center_no) && empty($school_name) && empty($subcounty_name) && empty($school_type)) {
                error_log("Row $index: Skipped (completely empty)", 3, '../../setup_errors.log');
                $skipped_count++;
                continue;
            }

            // Validate required fields
            if (empty($center_no) || empty($school_name) || empty($subcounty_name) || empty($school_type)) {
                error_log("Row $index: Skipped (missing data: CN=$center_no, Name=$school_name, Subcounty=$subcounty_name, Type=$school_type)", 3, '../../setup_errors.log');
                $skipped_count++;
                continue;
            }

            // Map subcounty and school type
            $subcounty_id = $subcounties[$subcounty_name] ?? null;
            $school_type_id = $school_types[$school_type] ?? null;

            if (!$subcounty_id || !$school_type_id) {
                error_log("Row $index: Skipped (invalid subcounty '$subcounty_name' or school type '$school_type')", 3, '../../setup_errors.log');
                $skipped_count++;
                continue;
            }

            // Execute insert/update
            if ($stmt->execute()) {
                $success_count++;
                $escaped_school_name = mysqli_real_escape_string($conn, $school_name);
                $escaped_center_no = mysqli_real_escape_string($conn, $center_no);
                $conn->query("CALL log_action('Import School', $user_id, 'Imported/Updated school: $escaped_school_name (Center: $escaped_center_no)')");
            } else {
                error_log("Row $index: Failed to import: " . $stmt->error, 3, '../../setup_errors.log');
                $skipped_count++;
            }
        }

        $message = "Imported $success_count schools successfully";
        if ($skipped_count > 0) {
            $message .= ". Skipped $skipped_count rows due to invalid or missing data.";
        }
        ob_end_clean();
        header("Location: {$base_url}schools/add_school.php?success=" . urlencode($message));
    } catch (Exception $e) {
        error_log("Import Schools Error: " . $e->getMessage(), 3, '../../setup_errors.log');
        ob_end_clean();
        header("Location: {$base_url}schools/add_school.php?error=" . urlencode($e->getMessage()));
    } finally {
        $stmt->close();
        $conn->close();
    }
} else {
    $error = $_FILES['excel_file']['error'] ?? 'No file uploaded';
    error_log("Import Schools Error: $error", 3, '../../setup_errors.log');
    ob_end_clean();
    header("Location: {$base_url}schools/add_school.php?error=" . urlencode("No file uploaded or upload error"));
}
?>