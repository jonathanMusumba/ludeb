<?php
session_start();
require_once '../../Common/db_connect.php';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $search = isset($_GET['search']) ? '%' . trim($_GET['search']) . '%' : '%';
    $stmt = $conn->prepare("SELECT s.id, s.center_no, s.school_name, sc.subcounty_name, st.type, s.results_status
                            FROM schools s
                            JOIN subcounties sc ON s.subcounty_id = sc.id
                            JOIN school_types st ON s.school_type_id = st.id
                            WHERE s.center_no LIKE ? OR s.school_name LIKE ?
                            ORDER BY s.school_name");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();

    $schools = [];
    while ($row = $result->fetch_assoc()) {
        $schools[] = $row;
    }

    // Log action
    $user_id = $_SESSION['user_id'];
    $conn->query("CALL log_action('View Schools', $user_id, 'Accessed schools list')");

    header('Content-Type: application/json');
    echo json_encode(['schools' => $schools]);
} catch (Exception $e) {
    error_log("Get Schools Error: " . $e->getMessage(), 3, '../../../setup_errors.log');
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
} finally {
    $conn->close();
}
?>