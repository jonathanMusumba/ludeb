<?php
session_start();
require_once '../db_connect.php';

// Restrict to Data Entrants
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Data Entrant') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Fetch the active exam year
    $activeYearStmt = $conn->query("SELECT id FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC LIMIT 1");
    if ($activeYearStmt === false) {
        throw new Exception("Active exam year query failed: " . $conn->error);
    }
    $activeYear = $activeYearStmt->fetch_assoc()['id'] ?? null;
    if (!$activeYear) {
        throw new Exception("No active exam year found");
    }

    // Fetch total candidates and gender distribution (for schools handled by user)
    $totalCandidatesResult = $conn->prepare("
        SELECT COUNT(DISTINCT c.id) AS total 
        FROM candidates c
        JOIN marks m ON c.id = m.candidate_id
        WHERE m.submitted_by = ? AND c.exam_year_id = ?
    ");
    $totalCandidatesResult->bind_param("ii", $user_id, $activeYear);
    $totalCandidatesResult->execute();
    $totalCandidates = $totalCandidatesResult->get_result()->fetch_assoc()['total'] ?? 0;

    $femaleCandidatesResult = $conn->prepare("
        SELECT COUNT(DISTINCT c.id) AS total 
        FROM candidates c
        JOIN marks m ON c.id = m.candidate_id
        WHERE c.sex = 'Female' AND m.submitted_by = ? AND c.exam_year_id = ?
    ");
    $femaleCandidatesResult->bind_param("ii", $user_id, $activeYear);
    $femaleCandidatesResult->execute();
    $totalFemale = $femaleCandidatesResult->get_result()->fetch_assoc()['total'] ?? 0;

    $maleCandidatesResult = $conn->prepare("
        SELECT COUNT(DISTINCT c.id) AS total 
        FROM candidates c
        JOIN marks m ON c.id = m.candidate_id
        WHERE c.sex = 'Male' AND m.submitted_by = ? AND c.exam_year_id = ?
    ");
    $maleCandidatesResult->bind_param("ii", $user_id, $activeYear);
    $maleCandidatesResult->execute();
    $totalMale = $maleCandidatesResult->get_result()->fetch_assoc()['total'] ?? 0;

    // Fetch total schools (only those handled by user)
    $totalSchoolsResult = $conn->prepare("
        SELECT COUNT(DISTINCT s.id) AS total 
        FROM schools s
        JOIN candidates c ON s.id = c.school_id
        JOIN marks m ON c.id = m.candidate_id
        WHERE m.submitted_by = ? AND c.exam_year_id = ?
    ");
    $totalSchoolsResult->bind_param("ii", $user_id, $activeYear);
    $totalSchoolsResult->execute();
    $totalSchools = $totalSchoolsResult->get_result()->fetch_assoc()['total'] ?? 0;

    $privateSchoolsResult = $conn->prepare("
        SELECT COUNT(DISTINCT s.id) AS total 
        FROM schools s
        JOIN candidates c ON s.id = c.school_id
        JOIN marks m ON c.id = m.candidate_id
        WHERE s.school_type_id = 2 AND m.submitted_by = ? AND c.exam_year_id = ?
    ");
    $privateSchoolsResult->bind_param("ii", $user_id, $activeYear);
    $privateSchoolsResult->execute();
    $totalPrivateSchools = $privateSchoolsResult->get_result()->fetch_assoc()['total'] ?? 0;

    $governmentSchoolsResult = $conn->prepare("
        SELECT COUNT(DISTINCT s.id) AS total 
        FROM schools s
        JOIN candidates c ON s.id = c.school_id
        JOIN marks m ON c.id = m.candidate_id
        WHERE s.school_type_id = 1 AND m.submitted_by = ? AND c.exam_year_id = ?
    ");
    $governmentSchoolsResult->bind_param("ii", $user_id, $activeYear);
    $governmentSchoolsResult->execute();
    $totalGovernmentSchools = $governmentSchoolsResult->get_result()->fetch_assoc()['total'] ?? 0;

    // Fetch declared and undeclared results (for schools handled by user)
    $declaredResultsQuery = "
        SELECT COUNT(*) AS declared_count
        FROM (
            SELECT s.id
            FROM schools s
            JOIN candidates c ON s.id = c.school_id
            JOIN marks m ON c.id = m.candidate_id
            WHERE m.submitted_by = ? AND c.exam_year_id = ?
            GROUP BY s.id
            HAVING COUNT(DISTINCT m.subject_id) = (SELECT COUNT(*) FROM subjects)
        ) AS declared_schools
    ";
    $declaredResultsStmt = $conn->prepare($declaredResultsQuery);
    $declaredResultsStmt->bind_param("ii", $user_id, $activeYear);
    $declaredResultsStmt->execute();
    $totalDeclaredResults = $declaredResultsStmt->get_result()->fetch_assoc()['declared_count'] ?? 0;

    $undeclaredResultsQuery = "
        SELECT COUNT(*) AS undeclared_count
        FROM (
            SELECT s.id
            FROM schools s
            JOIN candidates c ON s.id = c.school_id
            LEFT JOIN marks m ON c.id = m.candidate_id AND m.submitted_by = ?
            WHERE c.exam_year_id = ?
            GROUP BY s.id
            HAVING COUNT(DISTINCT m.subject_id) < (SELECT COUNT(*) FROM subjects)
        ) AS undeclared_schools
    ";
    $undeclaredResultsStmt = $conn->prepare($undeclaredResultsQuery);
    $undeclaredResultsStmt->bind_param("ii", $user_id, $activeYear);
    $undeclaredResultsStmt->execute();
    $totalUndeclaredResults = $undeclaredResultsStmt->get_result()->fetch_assoc()['undeclared_count'] ?? 0;

    // Fetch total schools handled by the Entrant
    $schoolsHandledResult = $conn->prepare("
        SELECT COUNT(DISTINCT s.id) AS total 
        FROM marks m 
        JOIN candidates c ON m.candidate_id = c.id 
        JOIN schools s ON c.school_id = s.id 
        WHERE m.submitted_by = ? AND c.exam_year_id = ?
    ");
    $schoolsHandledResult->bind_param("ii", $user_id, $activeYear);
    $schoolsHandledResult->execute();
    $totalSchoolsHandled = $schoolsHandledResult->get_result()->fetch_assoc()['total'] ?? 0;

    // Fetch schools handled by the user (for "My Schools" table)
    $schoolsHandled = $conn->prepare("
        SELECT 
            s.id AS school_id, 
            s.school_name AS name, 
            COUNT(DISTINCT c.id) AS candidate_count,
            COUNT(DISTINCT CASE WHEN m.mark IS NOT NULL AND m.submitted_by = ? THEN c.id ELSE NULL END) AS candidates_with_marks,
            GROUP_CONCAT(DISTINCT subj.name ORDER BY subj.name ASC SEPARATOR ', ') AS subjects,
            CASE 
                WHEN COUNT(DISTINCT m.subject_id) = (SELECT COUNT(*) FROM subjects) 
                    AND COUNT(DISTINCT CASE WHEN m.mark IS NOT NULL AND m.submitted_by = ? THEN c.id ELSE NULL END) = COUNT(DISTINCT c.id) 
                    THEN 'Complete'
                ELSE 'Incomplete'
            END AS status,
            MIN(m.submitted_at) AS handled_when
        FROM schools s
        JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
        LEFT JOIN marks m ON c.id = m.candidate_id AND m.submitted_by = ?
        LEFT JOIN subjects subj ON m.subject_id = subj.id
        WHERE m.submitted_by = ?
        GROUP BY s.id, s.school_name
    ");
    $schoolsHandled->bind_param("iiiii", $user_id, $user_id, $activeYear, $user_id, $user_id);
    $schoolsHandled->execute();
    $schoolsResult = $schoolsHandled->get_result();
    $schools = $schoolsResult->fetch_all(MYSQLI_ASSOC);

    // Fetch summary of entries for the user
    $userSummary = $conn->prepare("
        SELECT 
            subj.name AS subject, 
            COUNT(m.id) AS entries,
            COUNT(CASE WHEN m.mark > 0 THEN 1 END) AS marks_submitted
        FROM marks m
        JOIN subjects subj ON m.subject_id = subj.id
        JOIN candidates c ON m.candidate_id = c.id
        WHERE m.submitted_by = ? AND c.exam_year_id = ?
        GROUP BY subj.id
    ");
    $userSummary->bind_param("ii", $user_id, $activeYear);
    $userSummary->execute();
    $summaryResult = $userSummary->get_result();
    $summary = $summaryResult->fetch_all(MYSQLI_ASSOC);

    // Fetch daily progress for the user (for progressive graph)
    $dailyProgress = $conn->prepare("
        SELECT 
            DATE(m.submitted_at) AS submission_date,
            COUNT(m.id) AS entries,
            UNIX_TIMESTAMP(DATE(m.submitted_at)) * 1000 AS date_ms
        FROM marks m
        JOIN candidates c ON m.candidate_id = c.id
        WHERE m.submitted_by = ? AND c.exam_year_id = ?
        GROUP BY DATE(m.submitted_at)
    ");
    $dailyProgress->bind_param("ii", $user_id, $activeYear);
    $dailyProgress->execute();
    $progressResult = $dailyProgress->get_result();
    $progress = $progressResult->fetch_all(MYSQLI_ASSOC);

    // --- Daily Target Progress ---
    $today = date('Y-m-d');

    // Today's Target and Actual
    $targetStmt = $conn->prepare("SELECT target_entries FROM daily_targets WHERE target_date = ?");
    $targetStmt->bind_param("s", $today);
    $targetStmt->execute();
    $targetResult = $targetStmt->get_result();
    $target = $targetResult->fetch_assoc()['target_entries'] ?? 0;
    $targetStmt->close();

    $actualStmt = $conn->prepare("
        SELECT COUNT(*) AS actual 
        FROM marks m
        JOIN candidates c ON m.candidate_id = c.id
        WHERE m.submitted_by = ? AND DATE(m.submitted_at) = ? AND c.exam_year_id = ?
    ");
    $actualStmt->bind_param("isi", $user_id, $today, $activeYear);
    $actualStmt->execute();
    $actualResult = $actualStmt->get_result();
    $actual = $actualResult->fetch_assoc()['actual'] ?? 0;
    $actualStmt->close();

    $percentage = $target > 0 ? min(round($actual / $target * 100), 100) : 0;

    // Historical Target Progress (last 7 days)
    $targetProgressData = [];
    $progressResult = $conn->prepare("
        SELECT 
            dt.target_date,
            dt.target_entries,
            COUNT(m.id) AS actual_entries,
            IF(dt.target_entries > 0, LEAST(ROUND(COUNT(m.id) / dt.target_entries * 100), 100), 0) AS percentage
        FROM daily_targets dt
        LEFT JOIN marks m ON DATE(m.submitted_at) = dt.target_date AND m.submitted_by = ?
        JOIN candidates c ON m.candidate_id = c.id
        WHERE dt.target_date >= CURDATE() - INTERVAL 7 DAY AND c.exam_year_id = ?
        GROUP BY dt.target_date
        ORDER BY dt.target_date
    ");
    $progressResult->bind_param("ii", $user_id, $activeYear);
    $progressResult->execute();
    $progressResultSet = $progressResult->get_result();
    if ($progressResultSet === false) {
        throw new Exception("Historical target progress query failed: " . $conn->error);
    }
    while ($row = $progressResultSet->fetch_assoc()) {
        $targetProgressData[] = [
            'date' => strtotime($row['target_date']) * 1000,
            'target' => (int)$row['target_entries'],
            'actual' => (int)$row['actual_entries'],
            'percentage' => (int)$row['percentage']
        ];
    }

    // Fetch candidates with 4, 3, 2, 1 subjects submitted by the Entrant
    $subjectCounts = [];
    for ($i = 1; $i <= 4; $i++) {
        $query = "
            SELECT COUNT(*) AS count
            FROM (
                SELECT c.id
                FROM candidates c
                JOIN marks m ON c.id = m.candidate_id
                WHERE m.submitted_by = ? AND c.exam_year_id = ?
                GROUP BY c.id
                HAVING COUNT(DISTINCT m.subject_id) = ?
            ) AS subquery
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $user_id, $activeYear, $i);
        $stmt->execute();
        $result = $stmt->get_result();
        $subjectCounts[$i] = $result->fetch_assoc()['count'] ?? 0;
    }

    // Prepare the response data
    $response = [
        'totalSchools' => $totalSchools,
        'privateSchools' => $totalPrivateSchools,
        'governmentSchools' => $totalGovernmentSchools,
        'schoolsResultsDeclared' => $totalDeclaredResults,
        'undeclaredResults' => $totalUndeclaredResults,
        'totalCandidates' => $totalCandidates,
        'femaleCandidates' => $totalFemale,
        'maleCandidates' => $totalMale,
        'schoolsHandled' => $totalSchoolsHandled,
        'subjectCounts' => $subjectCounts,
        'targetProgress' => [
            'today' => [
                'percentage' => $percentage,
                'target' => (int)$target,
                'actual' => (int)$actual
            ],
            'history' => $targetProgressData
        ],
        'progressiveData' => array_map(function($row) {
            return [$row['date_ms'], $row['entries']];
        }, $progress),
        'schools' => $schools,
        'summary' => $summary
    ];

    header('Content-Type: application/json');
    echo json_encode($response, JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage() . " at line " . $e->getLine() . " in file " . $e->getFile());
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>