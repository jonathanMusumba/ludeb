<?php
session_start();
require_once 'db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['school_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['school_id']) || !isset($input['exam_year_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$schoolId = (int)$input['school_id'];
$examYearId = (int)$input['exam_year_id'];

// Verify school access
if ($schoolId !== $_SESSION['school_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

try {
    // Get results statistics for the selected exam year
    $sql = "SELECT 
                COUNT(DISTINCT c.id) as total_candidates,
                COUNT(CASE WHEN cr.division IS NOT NULL AND cr.division != '' THEN 1 END) as processed_results,
                COUNT(CASE WHEN cr.division = 'Division 1' OR cr.division = 'Div 1' OR cr.division = '1' THEN 1 END) as div1,
                COUNT(CASE WHEN cr.division = 'Division 2' OR cr.division = 'Div 2' OR cr.division = '2' THEN 1 END) as div2,
                COUNT(CASE WHEN cr.division = 'Division 3' OR cr.division = 'Div 3' OR cr.division = '3' THEN 1 END) as div3,
                COUNT(CASE WHEN cr.division = 'Division 4' OR cr.division = 'Div 4' OR cr.division = '4' THEN 1 END) as div4,
                COUNT(CASE WHEN cr.division = 'Ungraded' OR cr.division = 'U' THEN 1 END) as ungraded,
                COUNT(CASE WHEN cr.division = 'X' OR cr.division = 'Absent' OR cr.division IS NULL THEN 1 END) as absent,
                AVG(CASE WHEN cr.aggregates > 0 AND cr.aggregates IS NOT NULL THEN cr.aggregates END) as avg_aggregate,
                ey.exam_year
            FROM candidates c
            LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
            LEFT JOIN exam_years ey ON ey.id = ?
            WHERE c.school_id = ? AND c.exam_year_id = ?
            GROUP BY ey.exam_year";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("iiii", $examYearId, $examYearId, $schoolId, $examYearId);
    
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    // If no data found, return empty stats
    if (!$stats) {
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_candidates' => 0,
                'processed_results' => 0,
                'div1' => 0,
                'div2' => 0,
                'div3' => 0,
                'div4' => 0,
                'ungraded' => 0,
                'absent' => 0,
                'avg_aggregate' => null,
                'exam_year' => ''
            ]
        ]);
        exit;
    }
    
    // Ensure all values are properly formatted
    $stats['total_candidates'] = (int)$stats['total_candidates'];
    $stats['processed_results'] = (int)$stats['processed_results'];
    $stats['div1'] = (int)$stats['div1'];
    $stats['div2'] = (int)$stats['div2'];
    $stats['div3'] = (int)$stats['div3'];
    $stats['div4'] = (int)$stats['div4'];
    $stats['ungraded'] = (int)$stats['ungraded'];
    $stats['absent'] = (int)$stats['absent'];
    $stats['avg_aggregate'] = $stats['avg_aggregate'] ? round((float)$stats['avg_aggregate'], 1) : null;
    
    // Return success response
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    // Log error (optional)
    error_log("Results stats error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch results statistics'
    ]);
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>