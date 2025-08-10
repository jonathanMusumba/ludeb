<?php
session_start();
require_once '../db_connect.php';

// Restrict to System Admins and Data Entrants
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['System Admin', 'Data Entrant'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed", 3, '../../../setup_errors.log');
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $school_id = (int)($_POST['school_id'] ?? 0);
        $subject_id = (int)($_POST['subject_id'] ?? 0);

        if ($school_id <= 0 || $subject_id <= 0) {
            throw new Exception("Invalid school or subject");
        }

        // Fetch candidates with existing marks
        $stmt = $conn->prepare("SELECT c.id, c.index_number, c.candidate_name, m.mark
                                FROM candidates c
                                LEFT JOIN marks m ON c.id = m.candidate_id AND m.subject_id = ?
                                WHERE c.school_id = ?");
        $stmt->bind_param("ii", $subject_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $candidates = [];
        while ($row = $result->fetch_assoc()) {
            $candidates[] = [
                'id' => $row['id'],
                'index_number' => $row['index_number'],
                'candidate_name' => $row['candidate_name'],
                'mark' => $row['mark']
            ];
        }

        // Log action
        $user_id = $_SESSION['user_id'];
        $conn->query("CALL log_action('Fetch Candidates', $user_id, 'Fetched candidates for school ID $school_id, subject ID $subject_id')");

        header('Content-Type: application/json');
        echo json_encode(['candidates' => $candidates]);
    } catch (Exception $e) {
        error_log("Fetch Candidates Error: " . $e->getMessage(), 3, '../../../setup_errors.log');
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    } finally {
        $conn->close();
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>