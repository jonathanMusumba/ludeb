<?php
session_start();
require_once '../db_connect.php';

// Restrict to System Admins and Examination Administrators
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['System Admin', 'Examination Administrator'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Total Subcounties
    $totalSubcounties = $conn->query("SELECT COUNT(*) AS total FROM subcounties")->fetch_assoc()['total'];

    // Total Schools
    $totalSchools = $conn->query("SELECT COUNT(*) AS total FROM schools")->fetch_assoc()['total'];

    // Private and Government Schools
    $privateSchools = $conn->query("
        SELECT COUNT(*) AS total 
        FROM schools s 
        JOIN school_types st ON s.school_type_id = st.id 
        WHERE st.type = 'Private'
    ")->fetch_assoc()['total'];
    $governmentSchools = $conn->query("
        SELECT COUNT(*) AS total 
        FROM schools s 
        JOIN school_types st ON s.school_type_id = st.id 
        WHERE st.type = 'Government'
    ")->fetch_assoc()['total'];

    // Results Declared
    $declaredResults = $conn->query("SELECT COUNT(*) AS total FROM schools WHERE results_status = 'Declared'")->fetch_assoc()['total'];

    // Undeclared Results
    $undeclaredResults = $conn->query("SELECT COUNT(*) AS total FROM schools WHERE results_status != 'Declared'")->fetch_assoc()['total'];

    // Total Candidates
    $totalCandidates = $conn->query("SELECT COUNT(*) AS total FROM candidates")->fetch_assoc()['total'];

    // Gender Distribution
    $femaleCandidates = $conn->query("SELECT COUNT(*) AS total FROM candidates WHERE sex = 'Female'")->fetch_assoc()['total'];
    $maleCandidates = $conn->query("SELECT COUNT(*) AS total FROM candidates WHERE sex = 'Male'")->fetch_assoc()['total'];

    // Pass Rate (Division 1 or 2)
    $passRateResult = $conn->query("
        SELECT (SUM(CASE WHEN division IN ('1', '2') THEN 1 ELSE 0 END) / COUNT(*) * 100) AS pass_rate
        FROM results
        WHERE division IS NOT NULL
    ");
    $passRate = round($passRateResult->fetch_assoc()['pass_rate'] ?? 0, 1);

    // Daily Target Progress
    $today = date('Y-m-d');
    $target = $conn->query("SELECT target_entries FROM daily_targets WHERE target_date = '$today'")->fetch_assoc()['target_entries'] ?? 0;
    $actual = $conn->query("SELECT COUNT(*) AS actual FROM marks WHERE DATE(submitted_at) = '$today'")->fetch_assoc()['actual'];
    $percentage = $target ? min(round($actual / $target * 100), 100) : 0;

    // Missing Marks
    $missingMarks = [];
    $missingResult = $conn->query("
        SELECT 
            s.id AS school_id,
            s.school_name,
            c.index_number,
            GROUP_CONCAT(sub.name ORDER BY sub.name SEPARATOR ', ') AS missing_subjects
        FROM candidates c
        JOIN schools s ON c.school_id = s.id
        JOIN subject_candidates sc ON c.id = sc.candidate_id
        JOIN subjects sub ON sc.subject_id = sub.id
        LEFT JOIN marks m ON c.id = m.candidate_id AND m.subject_id = sub.id
        WHERE m.id IS NULL
        GROUP BY c.id, s.id
    ");
    while ($row = $missingResult->fetch_assoc()) {
        $missingMarks[] = [
            'school_id' => $row['school_id'],
            'school_name' => $row['school_name'],
            'index_number' => $row['index_number'],
            'missing_subjects' => $row['missing_subjects'] ?? 'None'
        ];
    }

    // Division Summary
    $summaryTable = [];
    $summaryResult = $conn->query("
        SELECT 
            r.division,
            r.school_id,
            SUM(CASE WHEN c.sex = 'Male' THEN 1 ELSE 0 END) AS male,
            SUM(CASE WHEN c.sex = 'Female' THEN 1 ELSE 0 END) AS female,
            COUNT(*) AS total
        FROM results r
        JOIN candidates c ON r.candidate_id = c.id
        WHERE r.division IS NOT NULL
        GROUP BY r.division, r.school_id
        ORDER BY r.division
    ");
    while ($row = $summaryResult->fetch_assoc()) {
        $summaryTable[] = [
            'division' => 'Division ' . $row['division'],
            'school_id' => $row['school_id'],
            'male' => (int)$row['male'],
            'female' => (int)$row['female'],
            'total' => (int)$row['total']
        ];
    }

    // Data Entrant Monitoring
    $entrantData = [];
    $entrantResult = $conn->query("
        SELECT 
            u.username,
            m.school_id,
            COUNT(DISTINCT m.candidate_id) AS total_candidates,
            SUM(CASE WHEN mc.subject_count = 4 THEN 1 ELSE 0 END) AS four_subjects,
            SUM(CASE WHEN mc.subject_count = 3 THEN 1 ELSE 0 END) AS three_subjects,
            SUM(CASE WHEN mc.subject_count = 2 THEN 1 ELSE 0 END) AS two_subjects,
            SUM(CASE WHEN mc.subject_count = 1 THEN 1 ELSE 0 END) AS one_subject
        FROM system_users u
        LEFT JOIN marks m ON u.id = m.submitted_by
        LEFT JOIN (
            SELECT candidate_id, COUNT(DISTINCT subject_id) AS subject_count
            FROM marks
            GROUP BY candidate_id
        ) mc ON m.candidate_id = mc.candidate_id
        WHERE m.candidate_id IS NOT NULL
        GROUP BY u.id, m.school_id
        HAVING total_candidates > 0
    ");
    while ($row = $entrantResult->fetch_assoc()) {
        $entrantData[] = [
            'username' => $row['username'],
            'school_id' => $row['school_id'],
            'total_candidates' => (int)$row['total_candidates'],
            'four_subjects' => (int)$row['four_subjects'],
            'three_subjects' => (int)$row['three_subjects'],
            'two_subjects' => (int)$row['two_subjects'],
            'one_subject' => (int)$row['one_subject']
        ];
    }

    // Progressive Data
    $progressiveData = [];
    $progressiveResult = $conn->query("
        SELECT 
            DATE(submitted_at) AS entry_date,
            COUNT(*) AS entry_count
        FROM marks
        WHERE submitted_at >= CURDATE() - INTERVAL 30 DAY
        GROUP BY DATE(submitted_at)
        ORDER BY entry_date
    ");
    while ($row = $progressiveResult->fetch_assoc()) {
        $progressiveData[] = [
            strtotime($row['entry_date']) * 1000,
            (int)$row['entry_count']
        ];
    }

    // Division Data
    $divisionData = [];
    $divisionResult = $conn->query("
        SELECT 
            division AS name,
            COUNT(*) AS y
        FROM results
        WHERE division IS NOT NULL
        GROUP BY division
        ORDER BY division
    ");
    while ($row = $divisionResult->fetch_assoc()) {
        $divisionData[] = [
            'name' => 'Division ' . $row['name'],
            'y' => (int)$row['y']
        ];
    }

    // Subject Data
    $subjectData = [];
    $subjectResult = $conn->query("
        SELECT 
            sub.name,
            AVG(m.mark) AS avg_mark
        FROM marks m
        JOIN subjects sub ON m.subject_id = sub.id
        GROUP BY sub.id
        ORDER BY sub.name
    ");
    while ($row = $subjectResult->fetch_assoc()) {
        $subjectData[] = [
            'name' => $row['name'],
            'y' => round($row['avg_mark'], 1)
        ];
    }

    // Top Schools
    $topSchools = [];
    $topSchoolsResult = $conn->query("
        SELECT 
            s.school_name AS name,
            AVG(m.mark) AS avg_mark
        FROM marks m
        JOIN schools s ON m.school_id = s.id
        GROUP BY s.id
        ORDER BY avg_mark DESC
        LIMIT 5
    ");
    while ($row = $topSchoolsResult->fetch_assoc()) {
        $topSchools[] = [
            $row['name'],
            round($row['avg_mark'], 1)
        ];
    }

    // Output JSON
    echo json_encode([
        'totalSubcounties' => (int)$totalSubcounties,
        'totalSchools' => (int)$totalSchools,
        'privateSchools' => (int)$privateSchools,
        'governmentSchools' => (int)$governmentSchools,
        'schoolsResultsDeclared' => (int)$declaredResults,
        'undeclaredResults' => (int)$undeclaredResults,
        'totalCandidates' => (int)$totalCandidates,
        'femaleCandidates' => (int)$femaleCandidates,
        'maleCandidates' => (int)$maleCandidates,
        'passRate' => $passRate,
        'targetProgress' => [
            'target' => (int)$target,
            'actual' => (int)$actual,
            'percentage' => $percentage
        ],
        'missingMarks' => $missingMarks,
        'summaryTable' => $summaryTable,
        'entrantData' => $entrantData,
        'progressiveData' => $progressiveData,
        'divisionData' => $divisionData,
        'subjectData' => $subjectData,
        'topSchools' => $topSchools
    ], JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    // Log error to audit_logs
    $error_message = $e->getMessage();
    $stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, details) VALUES ('Dashboard Data Error', ?, ?)");
    $stmt->bind_param("is", $user_id, $error_message);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['error' => 'Server error']);
}

$conn->close();
?>