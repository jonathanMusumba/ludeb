<?php
// Include database connection
require_once 'db_connect.php';

// Get exam_year_id and optional filters
$exam_year_id = isset($_GET['exam_year_id']) ? (int)$_GET['exam_year_id'] : 1;
$district_id = isset($_GET['district_id']) ? (int)$_GET['district_id'] : null;
$subcounty_id = isset($_GET['subcounty_id']) ? (int)$_GET['subcounty_id'] : null;
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

try {
    // Query for division counts
    $query_divisions = "
        SELECT 
            COUNT(*) AS total_candidates,
            SUM(CASE WHEN division = 'Division 1' THEN 1 ELSE 0 END) AS division_1,
            SUM(CASE WHEN division = 'Division 2' THEN 1 ELSE 0 END) AS division_2,
            SUM(CASE WHEN division = 'Division 3' THEN 1 ELSE 0 END) AS division_3,
            SUM(CASE WHEN division = 'Division 4' THEN 1 ELSE 0 END) AS division_4,
            SUM(CASE WHEN division = 'Ungraded' THEN 1 ELSE 0 END) AS ungraded,
            SUM(CASE WHEN division = 'X' THEN 1 ELSE 0 END) AS division_x
        FROM candidate_results cr
        JOIN schools s ON cr.school_id = s.id
        JOIN subcounties sc ON s.subcounty_id = sc.id
        WHERE cr.exam_year_id = ?
        AND s.status = 'Active'
    ";
    $params = [$exam_year_id];
    $types = "i";

    if ($district_id) {
        $query_divisions .= " AND sc.district_id = ?";
        $params[] = $district_id;
        $types .= "i";
    }
    if ($subcounty_id) {
        $query_divisions .= " AND s.subcounty_id = ?";
        $params[] = $subcounty_id;
        $types .= "i";
    }

    // Execute division query
    $stmt = $conn->prepare($query_divisions);
    if (!$stmt) {
        throw new Exception("Division query preparation failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception("Division query execution failed: " . $stmt->error);
    }
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();

    // Ensure all fields are set
    $summary = array_merge([
        'total_candidates' => 0,
        'division_1' => 0,
        'division_2' => 0,
        'division_3' => 0,
        'division_4' => 0,
        'ungraded' => 0,
        'division_x' => 0
    ], $summary ?: []);

    // Query for absentee categories
    $query_absentees = "
        SELECT 
            COUNT(DISTINCT cr.candidate_id) AS total_absentees,
            SUM(CASE WHEN missed_subjects = 4 THEN 1 ELSE 0 END) AS all_subjects,
            SUM(CASE WHEN missed_subjects = 1 THEN 1 ELSE 0 END) AS one_subject,
            SUM(CASE WHEN missed_subjects = 2 THEN 1 ELSE 0 END) AS two_subjects,
            SUM(CASE WHEN missed_subjects = 3 THEN 1 ELSE 0 END) AS three_subjects
        FROM candidate_results cr
        JOIN schools s ON cr.school_id = s.id
        JOIN subcounties sc ON s.subcounty_id = sc.id
        LEFT JOIN (
            SELECT 
                m.candidate_id,
                COUNT(*) AS missed_subjects
            FROM marks m
            WHERE m.exam_year_id = ?
            AND (m.status = 'ABSENT' OR (m.status IS NULL AND m.mark = 0))
            GROUP BY m.candidate_id
            UNION
            SELECT 
                c.id AS candidate_id,
                4 AS missed_subjects
            FROM candidates c
            WHERE c.exam_year_id = ?
            AND NOT EXISTS (
                SELECT 1
                FROM marks m
                WHERE m.candidate_id = c.id
                AND m.exam_year_id = c.exam_year_id
            )
        ) abs ON cr.candidate_id = abs.candidate_id
        WHERE cr.exam_year_id = ?
        AND cr.division = 'X'
        AND s.status = 'Active'
    ";
    $params_abs = [$exam_year_id, $exam_year_id, $exam_year_id];
    $types_abs = "iii";

    if ($district_id) {
        $query_absentees .= " AND sc.district_id = ?";
        $params_abs[] = $district_id;
        $types_abs .= "i";
    }
    if ($subcounty_id) {
        $query_absentees .= " AND s.subcounty_id = ?";
        $params_abs[] = $subcounty_id;
        $types_abs .= "i";
    }

    // Execute absentee query
    $stmt = $conn->prepare($query_absentees);
    if (!$stmt) {
        throw new Exception("Absentee query preparation failed: " . $conn->error);
    }
    $stmt->bind_param($types_abs, ...$params_abs);
    if (!$stmt->execute()) {
        throw new Exception("Absentee query execution failed: " . $stmt->error);
    }
    $result = $stmt->get_result();
    $absentee_summary = $result->fetch_assoc();
    $stmt->close();

    // Ensure all fields are set
    $absentee_summary = array_merge([
        'total_absentees' => 0,
        'all_subjects' => 0,
        'one_subject' => 0,
        'two_subjects' => 0,
        'three_subjects' => 0
    ], $absentee_summary ?: []);

    // Log results
    error_log("results_summary.php: Fetched summary for exam_year_id=$exam_year_id, district_id=" . ($district_id ?? 'NULL') . 
              ", subcounty_id=" . ($subcounty_id ?? 'NULL') . ", user_id=$user_id, total_candidates={$summary['total_candidates']}, " .
              "division_x={$summary['division_x']}, all_subjects_absent={$absentee_summary['all_subjects']}, " .
              "one_subject_absent={$absentee_summary['one_subject']}, two_subjects_absent={$absentee_summary['two_subjects']}, " .
              "three_subjects_absent={$absentee_summary['three_subjects']}", 
              3, __DIR__ . '/logs/setup_errors.log');

} catch (Exception $e) {
    error_log("results_summary.php: Error: " . $e->getMessage(), 3, __DIR__ . '/logs/setup_errors.log');
    $summary = [
        'total_candidates' => 0,
        'division_1' => 0,
        'division_2' => 0,
        'division_3' => 0,
        'division_4' => 0,
        'ungraded' => 0,
        'division_x' => 0
    ];
    $absentee_summary = [
        'total_absentees' => 0,
        'all_subjects' => 0,
        'one_subject' => 0,
        'two_subjects' => 0,
        'three_subjects' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results Summary - Exam Year <?php echo htmlspecialchars($exam_year_id); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <h1 class="text-center mb-4">Results Summary for Exam Year <?php echo htmlspecialchars($exam_year_id); ?></h1>
        <?php if ($district_id || $subcounty_id): ?>
            <p class="text-center">
                <?php
                if ($district_id) {
                    $stmt = $conn->prepare("SELECT district_name FROM districts WHERE id = ?");
                    $stmt->bind_param("i", $district_id);
                    $stmt->execute();
                    $district_name = $stmt->get_result()->fetch_assoc()['district_name'] ?? 'Unknown';
                    $stmt->close();
                    echo "District: " . htmlspecialchars($district_name);
                }
                if ($subcounty_id) {
                    $stmt = $conn->prepare("SELECT subcounty_name FROM subcounties WHERE id = ?");
                    $stmt->bind_param("i", $subcounty_id);
                    $stmt->execute();
                    $subcounty_name = $stmt->get_result()->fetch_assoc()['subcounty_name'] ?? 'Unknown';
                    $stmt->close();
                    echo ($district_id ? " | " : "") . "Subcounty: " . htmlspecialchars($subcounty_name);
                }
                ?>
            </p>
        <?php endif; ?>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Candidates</h5>
                        <p class="card-text display-6"><?php echo htmlspecialchars($summary['total_candidates']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="card stat-card shadow-sm bg-primary text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Division 1</h5>
                        <p class="card-text display-6"><?php echo htmlspecialchars($summary['division_1']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="card stat-card shadow-sm bg-success text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Division 2</h5>
                        <p class="card-text display-6"><?php echo htmlspecialchars($summary['division_2']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="card stat-card shadow-sm bg-info text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Division 3</h5>
                        <p class="card-text display-6"><?php echo htmlspecialchars($summary['division_3']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="card stat-card shadow-sm bg-warning text-dark">
                    <div class="card-body text-center">
                        <h5 class="card-title">Division 4</h5>
                        <p class="card-text display-6"><?php echo htmlspecialchars($summary['division_4']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="card stat-card shadow-sm bg-secondary text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Ungraded</h5>
                        <p class="card-text display-6"><?php echo htmlspecialchars($summary['ungraded']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="card stat-card shadow-sm bg-danger text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Absentees (Division X)</h5>
                        <p class="card-text display-6"><?php echo htmlspecialchars($summary['division_x']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="card stat-card shadow-sm bg-dark text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">All Subjects Absent</h5>
                        <p class="card-text display-6"><?php echo htmlspecialchars($absentee_summary['all_subjects']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="card stat-card shadow-sm bg-dark text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">One Subject Absent</h5>
                        <p class="card-text display-6"><?php echo htmlspecialchars($absentee_summary['one_subject']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="card stat-card shadow-sm bg-dark text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Two Subjects Absent</h5>
                        <p class="card-text display-6"><?php echo htmlspecialchars($absentee_summary['two_subjects']); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="card stat-card shadow-sm bg-dark text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Three Subjects Absent</h5>
                        <p class="card-text display-6"><?php echo htmlspecialchars($absentee_summary['three_subjects']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>