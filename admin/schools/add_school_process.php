<?php
session_start();
require_once '../db_connect.php';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header("Location: ../../login.php");
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed", 3, '../../../setup_errors.log');
    die("Invalid CSRF token");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $center_no = trim($_POST['center_no']);
        $school_name = trim($_POST['school_name']);
        $subcounty_id = (int)$_POST['subcounty_id'];
        $school_type_id = (int)$_POST['school_type_id'];

        // Validate inputs
        if (empty($center_no) || empty($school_name) || $subcounty_id <= 0 || $school_type_id <= 0) {
            throw new Exception("All fields are required");
        }

        // Check if center_no already exists
        $stmt = $conn->prepare("SELECT id FROM schools WHERE center_no = ?");
        $stmt->bind_param("s", $center_no);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Center number already exists");
        }

        // Insert school
        $stmt = $conn->prepare("INSERT INTO schools (center_no, school_name, subcounty_id, school_type_id, results_status) VALUES (?, ?, ?, ?, 'Not Declared')");
        $stmt->bind_param("ssii", $center_no, $school_name, $subcounty_id, $school_type_id);
        $stmt->execute();

        // Log action
        $user_id = $_SESSION['user_id'];
        $conn->query("CALL log_action('Add School', $user_id, 'Added school: $school_name (Center: $center_no)')");

        // Redirect with success message
        header("Location: add_school.php?success=School added successfully");
    } catch (Exception $e) {
        error_log("Add School Error: " . $e->getMessage(), 3, '../../setup_errors.log');
        header("Location: add_school.php?error=" . urlencode($e->getMessage()));
    } finally {
        $conn->close();
    }
}
?>