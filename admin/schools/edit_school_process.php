<?php
session_start();
require_once '../db_connect.php';

// Define root URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    error_log("Unauthorized access attempt, User ID: " . ($_SESSION['user_id'] ?? 'Not set'), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    header("Location: $root_url" . "login.php");
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed: Received " . ($_POST['csrf_token'] ?? 'none') . ", Expected " . $_SESSION['csrf_token'], 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    header("Location: $base_url" . "schools/manage_schools.php?error=" . urlencode("Invalid CSRF token"));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $center_no = trim($_POST['center_no'] ?? '');
        $school_name = trim($_POST['school_name'] ?? '');
        $subcounty_id = (int)($_POST['subcounty_id'] ?? 0);
        $school_type_id = (int)($_POST['school_type_id'] ?? 0);

        if (empty($center_no) || empty($school_name) || $subcounty_id <= 0 || $school_type_id <= 0) {
            throw new Exception("All fields are required");
        }

        if (!preg_match('/^\d{6}$/', $center_no)) {
            throw new Exception("Center number must be exactly 6 digits");
        }

        // Fetch current school_id using center_no
        $stmt = $conn->prepare("SELECT id FROM schools WHERE center_no = ?");
        $stmt->bind_param("s", $center_no);
        $stmt->execute();
        $result = $stmt->get_result();
        $school = $result->fetch_assoc();
        if (!$school) {
            throw new Exception("School not found");
        }
        $school_id = $school['id'];

        // Check if center_no is unique (excluding current school)
        $stmt = $conn->prepare("SELECT id FROM schools WHERE center_no = ? AND id != ?");
        $stmt->bind_param("si", $center_no, $school_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Center number already exists");
        }

        // Update school
        $stmt = $conn->prepare("UPDATE schools SET center_no = ?, school_name = ?, subcounty_id = ?, school_type_id = ? WHERE id = ?");
        $stmt->bind_param("ssiii", $center_no, $school_name, $subcounty_id, $school_type_id, $school_id);
        $stmt->execute();

        // Log action
        $user_id = $_SESSION['user_id'];
        $conn->query("CALL log_action('Edit School', $user_id, 'Edited school: $school_name (Center: $center_no)')");

        header("Location: $base_url" . "schools/manage_schools.php?success=" . urlencode("School updated successfully"));
        exit;
    } catch (Exception $e) {
        error_log("Edit School Error: " . $e->getMessage(), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
        header("Location: $base_url" . "schools/edit_school.php?center_no=" . urlencode($center_no) . "&error=" . urlencode($e->getMessage()));
        exit;
    } finally {
        $conn->close();
    }
}
?>