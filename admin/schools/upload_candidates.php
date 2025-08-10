<?php
ob_start();
require_once '../db_connect.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Debug session
error_log("upload_candidates.php: Starting, User ID: " . ($_SESSION['user_id'] ?? 'Not set') . ", Role: " . ($_SESSION['role'] ?? 'Not set'), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    error_log("Unauthorized access attempt", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header("Location: $root_url" . "login.php");
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed: Received " . ($_POST['csrf_token'] ?? 'none') . ", Expected " . ($_SESSION['csrf_token'] ?? 'none'), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }
    ob_clean();
    header("Location: view_school.php?center_no=" . urlencode($_POST['center_no'] ?? '') . "&error=" . urlencode("Invalid CSRF token"));
    exit();
}

// Get center_no and exam_year_id from POST
$center_no = isset($_POST['center_no']) ? trim($_POST['center_no']) : '';
$exam_year_id = isset($_POST['exam_year_id']) ? intval($_POST['exam_year_id']) : 0;
error_log("Received center_no: $center_no, exam_year_id: $exam_year_id", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');

if (!preg_match('/^\d{6}$/', $center_no)) {
    error_log("Invalid center number: $center_no", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Invalid center number: Must be exactly 6 digits']);
        exit();
    }
    ob_clean();
    header("Location: view_school.php?center_no=" . urlencode($center_no) . "&error=" . urlencode("Invalid center number: Must be exactly 6 digits"));
    exit();
}
if ($exam_year_id <= 0) {
    error_log("Invalid exam_year_id: $exam_year_id", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Invalid exam year']);
        exit();
    }
    ob_clean();
    header("Location: schools/view_school.php?center_no=" . urlencode($center_no) . "&error=" . urlencode("Invalid exam year"));
    exit();
}

// Validate exam_year_id
$stmt = $conn->prepare("SELECT id FROM exam_years WHERE id = ? AND status = 'Active'");
$stmt->bind_param("i", $exam_year_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result->fetch_assoc()) {
    error_log("Invalid or inactive exam_year_id: $exam_year_id", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Exam year not found or inactive']);
        exit();
    }
    ob_clean();
    header("Location: view_school.php?center_no=" . urlencode($center_no) . "&error=" . urlencode("Exam year not found or inactive"));
    exit();
}
$stmt->close();

// Fetch school_id using the center_no
$stmt = $conn->prepare("SELECT id FROM schools WHERE center_no = ?");
$stmt->bind_param("s", $center_no);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $school_id = $row['id'];
} else {
    error_log("School not found for center_no: $center_no", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => "School not found for center number: $center_no"]);
        exit();
    }
    ob_clean();
    header("Location: view_school.php?center_no=" . urlencode($center_no) . "&error=" . urlencode("School not found for center number: $center_no"));
    exit();
}
$stmt->close();

// Handle file upload
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['file']['tmp_name'];
    $original_file_name = basename($_FILES['file']['name']);
    error_log("File uploaded: $original_file_name, tmp: $file_tmp", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');

    $current_year = date('Y');
    $upload_dir = '../../Uploads/' . $current_year . '/';

    // Create unique file name
    $file_extension = pathinfo($original_file_name, PATHINFO_EXTENSION);
    $file_base = pathinfo($original_file_name, PATHINFO_FILENAME);
    $file_name = $file_base . '_' . time() . '.' . $file_extension;
    $destination = $upload_dir . $file_name;

    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Failed to create upload directory: $upload_dir", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
                exit();
            }
            ob_clean();
            header("Location: view_school.php?center_no=" . urlencode($center_no) . "&error=" . urlencode("Failed to create upload directory"));
            exit();
        }
    }

    // Check directory permissions
    if (!is_writable($upload_dir)) {
        error_log("Upload directory not writable: $upload_dir", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => 'Upload directory not writable']);
            exit();
        }
        ob_clean();
        header("Location: view_school.php?center_no=" . urlencode($center_no) . "&error=" . urlencode("Upload directory not writable"));
        exit();
    }

    $success_count = 0;
    $error_details = [];
    $user_id = $_SESSION['user_id'];
    $candidates_data = [];

    try {
        // Load spreadsheet
        $spreadsheet = IOFactory::load($file_tmp);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        error_log("Excel rows loaded: " . count($rows) . " rows", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');

        // Validate headers
        $valid_headers = [
            ['A' => 'IndexNo', 'B' => 'Candidate_Name', 'C' => 'Gender'],
            ['A' => 'Index Number', 'B' => 'Candidate Name', 'C' => 'Sex']
        ];
        $row_1 = array_intersect_key($rows[1] ?? [], $valid_headers[0]);
        $headers_valid = false;
        foreach ($valid_headers as $expected_headers) {
            if ($row_1 === $expected_headers) {
                $headers_valid = true;
                break;
            }
        }
        if (!$headers_valid) {
            error_log("Invalid headers: " . print_r($row_1, true), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
            throw new Exception("Invalid Excel file format. Expected headers: IndexNo, Candidate_Name, Gender or Index Number, Candidate Name, Sex");
        }

        // Collect unique candidates
        foreach ($rows as $index => $row) {
            if ($index === 1) continue; // Skip header row

            $index_number = trim($row['A'] ?? '');
            $candidate_name = trim($row['B'] ?? '');
            $sex = trim(strtolower($row['C'] ?? ''));

            // Skip empty rows
            if (empty($index_number) && empty($candidate_name) && empty($sex)) {
                error_log("Row $index: Skipped (completely empty)", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                continue;
            }

            // Validate data
            if (empty($index_number) || empty($candidate_name)) {
                $error_details[] = "Row $index: Empty index number or name ($index_number, $candidate_name)";
                error_log("Row $index: Empty index number or name ($index_number, $candidate_name)", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                continue;
            }

            // Normalize sex
            $sex_map = [
                'male' => 'Male', 'm' => 'Male', 'female' => 'Female', 'f' => 'Female'
            ];
            $sex = $sex_map[strtolower($sex)] ?? $sex;
            if (!in_array($sex, ['Male', 'Female'])) {
                $error_details[] = "Row $index: Invalid gender value '$sex' for $index_number (must be 'Male', 'Female', 'M', or 'F')";
                error_log("Row $index: Invalid gender value '$sex' for $index_number", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                continue;
            }

            if (!preg_match('/^\d{6}\/\d{3}$/', $index_number)) {
                $error_details[] = "Row $index: Invalid index number format '$index_number' (must be XXXXXX/XXX)";
                error_log("Row $index: Invalid index number format '$index_number'", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                continue;
            }

            $index_center_no = substr($index_number, 0, 6);
            if ($index_center_no !== $center_no) {
                $error_details[] = "Row $index: Index number $index_number does not match center number $center_no";
                error_log("Row $index: Index number $index_number does not match center number $center_no", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                continue;
            }

            // Store candidate data, overwriting duplicates
            if (isset($candidates_data[$index_number])) {
                $error_details[] = "Row $index: Duplicate index number '$index_number' found, keeping last occurrence";
                error_log("Row $index: Duplicate index number '$index_number' found", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
            }
            $candidates_data[$index_number] = [
                'row' => $index,
                'name' => $candidate_name,
                'sex' => $sex
            ];
        }

        error_log("Valid candidates collected: " . count($candidates_data), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');

        // Prepare statement for candidate insertion
        $candidate_stmt = $conn->prepare("
            INSERT INTO candidates (school_id, index_number, candidate_name, sex, exam_year_id) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                candidate_name = VALUES(candidate_name), 
                sex = VALUES(sex), 
                exam_year_id = VALUES(exam_year_id)
        ");
        if (!$candidate_stmt) {
            error_log("Prepare failed: " . $conn->error, 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
            throw new Exception("Database error: Failed to prepare statement");
        }
        $candidate_stmt->bind_param("isssi", $school_id, $index_number, $candidate_name, $sex, $exam_year_id);

        // Process unique candidates
        foreach ($candidates_data as $index_number => $candidate) {
            $candidate_name = $candidate['name'];
            $sex = $candidate['sex'];
            $row_index = $candidate['row'];

            // Check for existing candidate in different school or exam year
            $check_stmt = $conn->prepare("
                SELECT school_id, exam_year_id 
                FROM candidates 
                WHERE index_number = ? AND (school_id != ? OR exam_year_id != ?)
            ");
            if (!$check_stmt) {
                error_log("Check prepare failed: " . $conn->error, 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                continue;
            }
            $check_stmt->bind_param("sii", $index_number, $school_id, $exam_year_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->fetch_assoc()) {
                $error_details[] = "Row $row_index: Index number $index_number already exists for another school or exam year";
                error_log("Row $row_index: Index number $index_number already exists for another school or exam year", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();

            // Insert candidate
            error_log("Inserting candidate: $index_number, $candidate_name, $sex, school_id: $school_id, exam_year_id: $exam_year_id", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
            if ($candidate_stmt->execute()) {
                $success_count++;
                $escaped_candidate_name = mysqli_real_escape_string($conn, $candidate_name);
                $conn->query("CALL log_action('Upload Candidate', $user_id, 'Uploaded/Updated candidate: $escaped_candidate_name (Index: $index_number) for school ID $school_id, exam_year_id $exam_year_id')");
            } else {
                $error_details[] = "Row $row_index: Failed to upload candidate $index_number: " . $candidate_stmt->error;
                error_log("Row $row_index: Failed to upload candidate $index_number: " . $candidate_stmt->error, 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
            }
        }

        // Move file only if at least one candidate was processed
        if ($success_count > 0 || empty($error_details)) {
            if (move_uploaded_file($file_tmp, $destination)) {
                // Log the upload in the uploads table
                $stmt = $conn->prepare("INSERT INTO uploads (school_id, filename, uploaded_at, uploaded_by) VALUES (?, ?, NOW(), ?)");
                if (!$stmt) {
                    error_log("Upload log prepare failed: " . $conn->error, 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                    throw new Exception("Database error: Failed to log upload");
                }
                $stmt->bind_param("isi", $school_id, $file_name, $user_id);
                $stmt->execute();
                $upload_id = $conn->insert_id;
                $uploaded_at = date('Y-m-d H:i:s');
                $stmt->close();

                $success_message = "Successfully uploaded $success_count candidate" . ($success_count !== 1 ? 's' : '');
                if (!empty($error_details)) {
                    $success_message .= ". Some errors occurred: " . implode('; ', $error_details);
                }

                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    echo json_encode([
                        'success' => true,
                        'message' => $success_message,
                        'upload_id' => $upload_id,
                        'filename' => $file_name,
                        'uploaded_at' => $uploaded_at
                    ]);
                    exit();
                }
                ob_clean();
                header("Location: schools/view_school.php?center_no=" . urlencode($center_no) . "&success=" . urlencode($success_message));
                exit();
            } else {
                error_log("Failed to move uploaded file to $destination", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                throw new Exception("Failed to move uploaded file to $destination");
            }
        } else {
            error_log("No valid candidates to upload", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
            throw new Exception("No valid candidates to upload");
        }
    } catch (Exception $e) {
        error_log("Upload Candidates Error: " . $e->getMessage(), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
        if (file_exists($destination)) {
            unlink($destination);
        }
        $details = !empty($error_details) ? implode('; ', $error_details) : '';
        $error_message = "Error processing file: " . $e->getMessage() . ($details ? "; $details" : "");
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit();
        }
        ob_clean();
        header("Location: view_school.php?center_no=" . urlencode($center_no) . "&error=" . urlencode($error_message));
        exit();
    } finally {
        if (isset($candidate_stmt)) $candidate_stmt->close();
        $conn->close();
    }
} else {
    $upload_error = $_FILES['file']['error'] ?? 'No file provided';
    error_log("File upload failed: $upload_error", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => "No file uploaded or upload error: $upload_error"]);
        exit();
    }
    ob_clean();
    header("Location: view_school.php?center_no=" . urlencode($center_no) . "&error=" . urlencode("No file uploaded or upload error: $upload_error"));
    exit();
}
?>