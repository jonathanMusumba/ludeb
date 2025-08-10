<?php
session_start();
require_once '../db_connect.php';

// Restrict to authenticated users only
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$exam_year_id = isset($_GET['exam_year_id']) ? intval($_GET['exam_year_id']) : 0;
$school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;

try {
    // Validate exam_year_id
    if ($exam_year_id <= 0) {
        $stmt = $conn->query("SELECT id FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC LIMIT 1");
        $row = $stmt->fetch_assoc();
        $exam_year_id = $row['id'] ?? 0;
    }

    if ($exam_year_id <= 0) {
        throw new Exception("No active exam year found");
    }

    // Log dashboard data access
    $stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, details) VALUES ('Dashboard Data Access', ?, ?)");
    $details = "Accessed dashboard data for exam_year_id: $exam_year_id" . ($school_id ? ", school_id: $school_id" : "");
    $stmt->bind_param("is", $user_id, $details);
    $stmt->execute();

    // Initialize response
    $response = [
        'totalSubcounties' => 0,
        'totalSchools' => 0,
        'privateSchools' => 0,
        'governmentSchools' => 0,
        'declaredSchools' => 0,
        'partiallyDeclaredSchools' => 0,
        'notDeclaredSchools' => 0,
        'totalCandidates' => 0,
        'femaleCandidates' => 0,
        'maleCandidates' => 0,
        'absentCandidates' => 0,
        'passRate' => 0,
        'targetProgress' => [
            'today' => ['percentage' => 0, 'target' => 0, 'actual' => 0],
            'history' => []
        ],
        'missingMarks' => [],
        'entrantData' => [],
        'summaryTable' => [],
        'resultsStatus' => [],
        'progressiveData' => [],
        'divisionData' => [],
        'subjectData' => [],
        'topSchools' => [],
        'subjectTrend' => [
            'english' => [],
            'sst' => [],
            'mtc' => [],
            'sci' => []
        ]
    ];

    // Total Subcounties
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM subcounties");
    $response['totalSubcounties'] = $stmt->fetch_assoc()['total'];

    // Total Schools
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM schools");
    $response['totalSchools'] = $stmt->fetch_assoc()['total'];

    // Private Schools
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM schools s JOIN school_types st ON s.school_type_id = st.id WHERE st.type = 'Private'");
    $response['privateSchools'] = $stmt->fetch_assoc()['total'];

    // Government (UPE) Schools
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM schools s JOIN school_types st ON s.school_type_id = st.id WHERE st.type = 'Government'");
    $response['governmentSchools'] = $stmt->fetch_assoc()['total'];

    // Schools by Results Status
    $query = "SELECT 
                SUM(CASE WHEN results_status = 'Declared' THEN 1 ELSE 0 END) AS declared,
                SUM(CASE WHEN results_status = 'Partially Declared' THEN 1 ELSE 0 END) AS partially_declared,
                SUM(CASE WHEN results_status = 'Not Declared' THEN 1 ELSE 0 END) AS not_declared
              FROM schools";
    $stmt = $conn->query($query);
    $row = $stmt->fetch_assoc();
    $response['declaredSchools'] = (int)($row['declared'] ?? 0);
    $response['partiallyDeclaredSchools'] = (int)($row['partially_declared'] ?? 0);
    $response['notDeclaredSchools'] = (int)($row['not_declared'] ?? 0);

    // Total Candidates
    $query = "SELECT COUNT(*) AS total FROM candidates WHERE exam_year_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $response['totalCandidates'] = $stmt->get_result()->fetch_assoc()['total'];

    // Female Candidates
    $query = "SELECT COUNT(*) AS total FROM candidates WHERE sex = 'Female' AND exam_year_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $response['femaleCandidates'] = $stmt->get_result()->fetch_assoc()['total'];

    // Male Candidates
    $query = "SELECT COUNT(*) AS total FROM candidates WHERE sex = 'Male' AND exam_year_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $response['maleCandidates'] = $stmt->get_result()->fetch_assoc()['total'];

    // Absent Candidates
    $query = "SELECT COUNT(DISTINCT candidate_id) AS total FROM marks WHERE status = 'ABSENT' AND exam_year_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $response['absentCandidates'] = $stmt->get_result()->fetch_assoc()['total'];

    // Pass Rate
    $query = "SELECT (SUM(CASE WHEN division IN ('Division 1', 'Division 2') THEN 1 ELSE 0 END) / COUNT(*) * 100) AS pass_rate
              FROM candidate_results
              WHERE division IS NOT NULL AND exam_year_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $response['passRate'] = round($stmt->get_result()->fetch_assoc()['pass_rate'] ?? 0, 1);

    // Daily Target Progress (Today)
    $today = date('Y-m-d');
    $query = "SELECT target_entries FROM daily_targets WHERE target_date = ? AND exam_year_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $today, $exam_year_id);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc()['target_entries'] ?? 0;

    $query = "SELECT COUNT(*) AS actual FROM marks WHERE DATE(submitted_at) = ? AND exam_year_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $today, $exam_year_id);
    $stmt->execute();
    $actual = $stmt->get_result()->fetch_assoc()['actual'] ?? 0;
    $response['targetProgress']['today'] = [
        'target' => (int)$target,
        'actual' => (int)$actual,
        'percentage' => $target ? min(round($actual / $target * 100), 100) : 0
    ];

    // Target Progress (Last 7 Days)
    $query = "SELECT dt.target_date, dt.target_entries, COUNT(m.id) AS actual_entries
              FROM daily_targets dt
              LEFT JOIN marks m ON DATE(m.submitted_at) = dt.target_date AND m.exam_year_id = dt.exam_year_id
              WHERE dt.target_date >= CURDATE() - INTERVAL 7 DAY AND dt.exam_year_id = ?
              GROUP BY dt.target_date
              ORDER BY dt.target_date";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['targetProgress']['history'][] = [
            'date' => strtotime($row['target_date']) * 1000,
            'target' => (int)$row['target_entries'],
            'actual' => (int)$row['actual_entries']
        ];
    }

    // Missing Marks (School Level)
    $query = "SELECT s.id AS school_id, s.center_no, s.school_name,
              COUNT(DISTINCT c.id) AS total_candidates,
              SUM(CASE WHEN (SELECT COUNT(*) FROM marks m WHERE m.candidate_id = c.id AND m.exam_year_id = ? AND m.status = 'PRESENT') = 4 THEN 1 ELSE 0 END) AS complete_marks,
              SUM(CASE WHEN (SELECT COUNT(*) FROM marks m WHERE m.candidate_id = c.id AND m.subject_id = (SELECT id FROM subjects WHERE code = 'ENG') AND m.exam_year_id = ? AND (m.status = 'ABSENT' OR m.id IS NULL)) > 0 THEN 1 ELSE 0 END) AS missing_eng,
              SUM(CASE WHEN (SELECT COUNT(*) FROM marks m WHERE m.candidate_id = c.id AND m.subject_id = (SELECT id FROM subjects WHERE code = 'SST') AND m.exam_year_id = ? AND (m.status = 'ABSENT' OR m.id IS NULL)) > 0 THEN 1 ELSE 0 END) AS missing_sst,
              SUM(CASE WHEN (SELECT COUNT(*) FROM marks m WHERE m.candidate_id = c.id AND m.subject_id = (SELECT id FROM subjects WHERE code = 'MTC') AND m.exam_year_id = ? AND (m.status = 'ABSENT' OR m.id IS NULL)) > 0 THEN 1 ELSE 0 END) AS missing_mtc,
              SUM(CASE WHEN (SELECT COUNT(*) FROM marks m WHERE m.candidate_id = c.id AND m.subject_id = (SELECT id FROM subjects WHERE code = 'SCI') AND m.exam_year_id = ? AND (m.status = 'ABSENT' OR m.id IS NULL)) > 0 THEN 1 ELSE 0 END) AS missing_sci
              FROM schools s
              LEFT JOIN candidates c ON s.id = c.school_id
              WHERE c.exam_year_id = ?" . ($school_id ? " AND s.id = ?" : "") . "
              GROUP BY s.id";
    $stmt = $conn->prepare($query);
    if ($school_id) {
        $stmt->bind_param('iiiiiii', $exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id, $school_id);
    } else {
        $stmt->bind_param('iiiiii', $exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['missingMarks'][] = [
            'school_id' => $row['school_id'],
            'center_no' => $row['center_no'],
            'school_name' => $row['school_name'],
            'complete_marks' => (int)$row['complete_marks'],
            'missing_eng' => (int)$row['missing_eng'],
            'missing_sst' => (int)$row['missing_sst'],
            'missing_mtc' => (int)$row['missing_mtc'],
            'missing_sci' => (int)$row['missing_sci']
        ];
    }

    // Results Status Table
    $query = "SELECT s.id AS school_id, s.center_no, s.school_name, s.results_status,
              COUNT(DISTINCT c.id) AS total_candidates,
              SUM(CASE WHEN (SELECT COUNT(*) FROM marks m WHERE m.candidate_id = c.id AND m.exam_year_id = ? AND m.status = 'PRESENT') = 4 THEN 1 ELSE 0 END) AS complete_marks
              FROM schools s
              LEFT JOIN candidates c ON s.id = c.school_id
              WHERE c.exam_year_id = ?" . ($school_id ? " AND s.id = ?" : "") . "
              GROUP BY s.id";
    $stmt = $conn->prepare($query);
    if ($school_id) {
        $stmt->bind_param('iii', $exam_year_id, $exam_year_id, $school_id);
    } else {
        $stmt->bind_param('ii', $exam_year_id, $exam_year_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['resultsStatus'][] = [
            'school_id' => $row['school_id'],
            'center_no' => $row['center_no'],
            'school_name' => $row['school_name'],
            'results_status' => $row['results_status'],
            'total_candidates' => (int)$row['total_candidates'],
            'complete_marks' => (int)$row['complete_marks']
        ];
    }

    // Data Entrant Monitoring
    $query = "SELECT u.username, u.id AS user_id, COUNT(DISTINCT m.candidate_id) AS entries,
              COUNT(DISTINCT CASE WHEN m.status = 'ABSENT' THEN m.candidate_id END) AS absentees,
              COUNT(DISTINCT c.id) AS total_candidates,
              GROUP_CONCAT(DISTINCT s.id) AS school_ids
              FROM system_users u
              LEFT JOIN marks m ON u.id = m.submitted_by
              LEFT JOIN candidates c ON m.candidate_id = c.id
              LEFT JOIN schools s ON c.school_id = s.id
              WHERE m.exam_year_id = ? AND u.role = 'Data Entrant'" . ($school_id ? " AND s.id = ?" : "") . "
              GROUP BY u.id";
    $stmt = $conn->prepare($query);
    if ($school_id) {
        $stmt->bind_param('ii', $exam_year_id, $school_id);
    } else {
        $stmt->bind_param('i', $exam_year_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['entrantData'][] = [
            'username' => $row['username'],
            'entries' => (int)$row['entries'],
            'absentees' => (int)$row['absentees'],
            'total_candidates' => (int)$row['total_candidates'],
            'school_id' => $school_id ?: $row['school_ids']
        ];
    }

    // Division Summary
    $query = "SELECT s.id AS school_id, s.school_name,
              SUM(CASE WHEN cr.division = 'Division 1' THEN 1 ELSE 0 END) AS division_1,
              SUM(CASE WHEN cr.division = 'Division 2' THEN 1 ELSE 0 END) AS division_2,
              SUM(CASE WHEN cr.division = 'Division 3' THEN 1 ELSE 0 END) AS division_3,
              SUM(CASE WHEN cr.division = 'Division 4' THEN 1 ELSE 0 END) AS division_4,
              SUM(CASE WHEN cr.division = 'Ungraded' THEN 1 ELSE 0 END) AS ungraded,
              SUM(CASE WHEN cr.division = 'X' THEN 1 ELSE 0 END) AS absentees
              FROM schools s
              LEFT JOIN candidates c ON s.id = c.school_id
              LEFT JOIN candidate_results cr ON c.id = cr.candidate_id
              WHERE cr.exam_year_id = ? AND c.exam_year_id = ?" . ($school_id ? " AND s.id = ?" : "") . "
              GROUP BY s.id";
    $stmt = $conn->prepare($query);
    if ($school_id) {
        $stmt->bind_param('iii', $exam_year_id, $exam_year_id, $school_id);
    } else {
        $stmt->bind_param('ii', $exam_year_id, $exam_year_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['summaryTable'][] = [
            'school_id' => $row['school_id'],
            'school_name' => $row['school_name'],
            'division_1' => (int)$row['division_1'],
            'division_2' => (int)$row['division_2'],
            'division_3' => (int)$row['division_3'],
            'division_4' => (int)$row['division_4'],
            'ungraded' => (int)$row['ungraded'],
            'absentees' => (int)$row['absentees']
        ];
    }

    // Progressive Data
    $query = "SELECT DATE(submitted_at) AS entry_date, COUNT(*) AS entry_count
              FROM marks
              WHERE submitted_at >= CURDATE() - INTERVAL 30 DAY AND exam_year_id = ?
              GROUP BY DATE(submitted_at)
              ORDER BY entry_date";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['progressiveData'][] = [strtotime($row['entry_date']) * 1000, (int)$row['entry_count']];
    }

    // Division Data
    $query = "SELECT division AS name, COUNT(*) AS y
              FROM candidate_results
              WHERE division IS NOT NULL AND exam_year_id = ?
              GROUP BY division
              ORDER BY FIELD(division, 'Division 1', 'Division 2', 'Division 3', 'Division 4', 'Ungraded', 'X')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['divisionData'][] = ['name' => $row['name'], 'y' => (int)$row['y']];
    }

    // Subject Data
    $query = "SELECT sub.name, AVG(r.mark) AS avg_mark
              FROM results r
              JOIN subjects sub ON r.subject_id = sub.id
              WHERE r.exam_year_id = ?" . ($school_id ? " AND r.school_id = ?" : "") . "
              GROUP BY sub.id
              ORDER BY sub.name";
    $stmt = $conn->prepare($query);
    if ($school_id) {
        $stmt->bind_param('ii', $exam_year_id, $school_id);
    } else {
        $stmt->bind_param('i', $exam_year_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['subjectData'][] = ['name' => $row['name'], 'y' => round($row['avg_mark'] ?? 0, 1)];
    }

    // Top Schools
    $query = "SELECT s.school_name AS name, AVG(r.mark) AS avg_mark
              FROM results r
              JOIN schools s ON r.school_id = s.id
              WHERE r.exam_year_id = ?
              GROUP BY s.id
              ORDER BY avg_mark DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['topSchools'][] = ['name' => $row['name'], 'y' => round($row['avg_mark'], 1)];
    }

    // Subject-wise Performance Trend
    $query = "SELECT DATE(m.submitted_at) AS entry_date, sub.code AS subject_code,
              AVG(CASE WHEN m.status = 'PRESENT' THEN m.mark ELSE NULL END) AS avg_mark
              FROM marks m
              JOIN subjects sub ON m.subject_id = sub.id
              WHERE m.exam_year_id = ? AND sub.code IN ('ENG', 'SST', 'MTC', 'SCI')" . ($school_id ? " AND m.school_id = ?" : "") . "
              GROUP BY DATE(m.submitted_at), sub.id
              ORDER BY entry_date DESC LIMIT 120";
    $stmt = $conn->prepare($query);
    if ($school_id) {
        $stmt->bind_param('ii', $exam_year_id, $school_id);
    } else {
        $stmt->bind_param('i', $exam_year_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subject_key = strtolower($row['subject_code']);
        if (in_array($subject_key, ['eng', 'sst', 'mtc', 'sci'])) {
            $response['subjectTrend'][$subject_key][] = [strtotime($row['entry_date']) * 1000, round($row['avg_mark'] ?? 0, 1)];
        }
    }

    // Output JSON
    echo json_encode($response, JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    // Log error to audit_logs
    $error_message = $e->getMessage();
    $stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, details) VALUES ('Dashboard Data Error', ?, ?)");
    $stmt->bind_param("is", $user_id, $error_message);
    $stmt->execute();
    echo json_encode(['error' => 'Server error: ' . $error_message], JSON_NUMERIC_CHECK);
}

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>