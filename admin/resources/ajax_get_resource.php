<?php
session_start();
require_once '../db_connect.php';

// Security check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$resource_id = intval($_POST['id']);
$user_id = $_SESSION['user_id'];
$is_admin = in_array($_SESSION['role'], ['System Admin', 'Examination Administrator']);

// Build query based on user permissions
$sql = "SELECT r.id, r.title, r.file_path, r.type, r.amount, r.class, r.category, r.created_at, 
               u.username AS uploader, r.approved, r.uploader_id
        FROM resources r 
        JOIN system_users u ON r.uploader_id = u.id 
        WHERE r.id = ?";

// Non-admin users can only view their own resources or approved resources
if (!$is_admin) {
    $sql .= " AND (r.uploader_id = ? OR r.approved = 1)";
}

$stmt = $conn->prepare($sql);

if (!$is_admin) {
    $stmt->bind_param("ii", $resource_id, $user_id);
} else {
    $stmt->bind_param("i", $resource_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Resource not found']);
    exit;
}

$resource = $result->fetch_assoc();

// Format the response
$response = [
    'id' => $resource['id'],
    'title' => $resource['title'],
    'file_path' => '../../' . $resource['file_path'], // Adjust path for downloads
    'type' => $resource['type'],
    'amount' => $resource['amount'] ? number_format($resource['amount'], 2) : null,
    'class' => $resource['class'],
    'category' => $resource['category'],
    'uploader' => $resource['uploader'],
    'created_at' => date('F j, Y g:i A', strtotime($resource['created_at'])),
    'approved' => (bool)$resource['approved'],
    'uploader_id' => $resource['uploader_id']
];

// Log the action for audit trail
$action_desc = "Viewed resource details: " . $resource['title'];
$log_stmt = $conn->prepare("CALL log_action('Resource Viewed', ?, ?)");
$log_stmt->bind_param("is", $user_id, $action_desc);
$log_stmt->execute();
$log_stmt->close();

header('Content-Type: application/json');
echo json_encode($response);

$stmt->close();
$conn->close();
?>