<?php
session_start();
require_once 'db_connect.php';
require_once 'layout.php';

// Restrict to Examination Administrators
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Examination Administrator') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = htmlspecialchars($_SESSION['username'] ?? 'N/A');

// Fetch exam body
$exam_body_query = "SELECT board_name FROM settings WHERE id = 1";
$exam_body_result = $conn->query($exam_body_query);
$exam_body = ($exam_body_result->num_rows > 0) ? $exam_body_result->fetch_assoc()['board_name'] : 'Luuka Examination Board';

// Fetch exam year
$current_year = date("Y");
$exam_year_query = "SELECT id, exam_year FROM exam_years WHERE exam_year = ? OR status = 'Active' ORDER BY exam_year DESC LIMIT 1";
$stmt = $conn->prepare($exam_year_query);
if (!$stmt) {
    error_log("Exam year query preparation failed: " . $conn->error);
    $exam_year = 'N/A';
    $exam_year_id = null;
} else {
    $stmt->bind_param('i', $current_year);
    $stmt->execute();
    $exam_year_result = $stmt->get_result();
    if ($exam_year_result->num_rows > 0) {
        $exam_year_data = $exam_year_result->fetch_assoc();
        $exam_year = $exam_year_data['exam_year'];
        $exam_year_id = $exam_year_data['id'];
    } else {
        error_log("No exam year found for $current_year or active status");
        $exam_year = 'N/A';
        $exam_year_id = null;
    }
    $stmt->close();
}

// Fetch school counts
$active_schools_query = "SELECT COUNT(*) AS active_schools_count FROM schools WHERE status = 'Active'";
$active_schools_result = $conn->query($active_schools_query);
$active_schools_count = $active_schools_result->num_rows > 0 ? $active_schools_result->fetch_assoc()['active_schools_count'] : 0;

$with_results_query = "SELECT COUNT(id) AS with_results FROM schools WHERE results_status = 'Declared' AND status = 'Active'";
$with_results_result = $conn->query($with_results_query);
$with_results = $with_results_result->num_rows > 0 ? $with_results_result->fetch_assoc()['with_results'] : 0;

$without_results_query = "SELECT COUNT(*) AS without_results FROM schools WHERE results_status IN ('Not Declared', 'Partially Declared') AND status = 'Active'";
$without_results_result = $conn->query($without_results_query);
$without_results = $without_results_result->num_rows > 0 ? $without_results_result->fetch_assoc()['without_results'] : 0;

// Fetch candidate counts
$candidates_query = "SELECT COUNT(*) AS candidate_count FROM candidates WHERE exam_year_id = ?";
$stmt = $conn->prepare($candidates_query);
if (!$stmt || !$exam_year_id) {
    $candidate_count = 0;
} else {
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $candidates_result = $stmt->get_result();
    $candidate_count = $candidates_result->num_rows > 0 ? $candidates_result->fetch_assoc()['candidate_count'] : 0;
    $stmt->close();
}

// Male candidates
$male_candidates_query = "SELECT COUNT(*) AS total FROM candidates WHERE sex = 'Male' AND exam_year_id = ?";
$stmt = $conn->prepare($male_candidates_query);
if (!$stmt || !$exam_year_id) {
    $male_candidates = 0;
} else {
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $male_candidates = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

// Female candidates
$female_candidates_query = "SELECT COUNT(*) AS total FROM candidates WHERE sex = 'Female' AND exam_year_id = ?";
$stmt = $conn->prepare($female_candidates_query);
if (!$stmt || !$exam_year_id) {
    $female_candidates = 0;
} else {
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $female_candidates = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

// Absent candidates (from marks table)
$absent_candidates_query = "SELECT COUNT(DISTINCT candidate_id) AS total FROM marks WHERE status = 'ABSENT' AND exam_year_id = ?";
$stmt = $conn->prepare($absent_candidates_query);
if (!$stmt || !$exam_year_id) {
    $absent_candidates = 0;
} else {
    $stmt->bind_param('i', $exam_year_id);
    $stmt->execute();
    $absent_candidates = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

// Fetch divisions using candidate_results.division
$divisions_query = "
    SELECT 
        cr.division AS division,
        COUNT(*) AS total_candidates,
        SUM(CASE WHEN c.sex = 'Male' THEN 1 ELSE 0 END) AS male_candidates,
        SUM(CASE WHEN c.sex = 'Female' THEN 1 ELSE 0 END) AS female_candidates
    FROM candidates c
    LEFT JOIN candidate_results cr 
        ON c.id = cr.candidate_id 
        AND cr.exam_year_id = ?
    WHERE c.exam_year_id = ?
    GROUP BY cr.division
    ORDER BY 
        CASE 
            WHEN cr.division = 'Division 1' THEN 1
            WHEN cr.division = 'Division 2' THEN 2
            WHEN cr.division = 'Division 3' THEN 3
            WHEN cr.division = 'Division 4' THEN 4
            WHEN cr.division = 'Ungraded' THEN 5
            WHEN cr.division = 'X' THEN 6
            ELSE 7
        END
";
$divisions = [
    'Division 1' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Division 2' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Division 3' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Division 4' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Ungraded' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'X' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0]
];
if ($exam_year_id) {
    $stmt = $conn->prepare($divisions_query);
    if (!$stmt) {
        error_log("Divisions query preparation failed: " . $conn->error);
    } else {
        $stmt->bind_param('ii', $exam_year_id, $exam_year_id);
        $stmt->execute();
        $divisions_result = $stmt->get_result();
        $debug_divisions = [];
        while ($row = $divisions_result->fetch_assoc()) {
            $division = $row['division'] ?? 'X';
            $debug_divisions[] = $row;
            if (isset($divisions[$division])) {
                $divisions[$division]['total_candidates'] = $row['total_candidates'];
                $divisions[$division]['male_candidates'] = $row['male_candidates'];
                $divisions[$division]['female_candidates'] = $row['female_candidates'];
            }
        }
        error_log("Divisions query results: " . json_encode($debug_divisions));
        $stmt->close();
    }
}

// Division data for charts
$division_data_query = "
    SELECT division AS name, COUNT(*) AS y
    FROM candidate_results
    WHERE exam_year_id = ?
    GROUP BY division
    ORDER BY FIELD(division, 'Division 1', 'Division 2', 'Division 3', 'Division 4', 'Ungraded', 'X')
";
$division_data = [];
if ($exam_year_id) {
    $stmt = $conn->prepare($division_data_query);
    if ($stmt) {
        $stmt->bind_param('i', $exam_year_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $division_data[] = ['name' => $row['name'] ?? 'X', 'y' => (int)$row['y']];
        }
        error_log("Division data query results: " . json_encode($division_data));
        $stmt->close();
    } else {
        error_log("Division data query preparation failed: " . $conn->error);
    }
}

// Prepare data for charts
$chart_data = [];
foreach ($divisions as $division => $data) {
    $chart_data['categories'][] = $division;
    $chart_data['male'][] = $data['male_candidates'];
    $chart_data['female'][] = $data['female_candidates'];
}

$pie_chart_data = [];
foreach ($division_data as $data) {
    $pie_chart_data[] = ['name' => $data['name'], 'y' => $data['y']];
}

// Fetch top 5 schools by Division 1 count
$top_div1_schools_query = "
    SELECT s.school_name, COUNT(*) AS div1_count
    FROM candidate_results cr
    JOIN schools s ON cr.school_id = s.id
    WHERE cr.exam_year_id = ?
    AND cr.division = 'Division 1'
    GROUP BY s.id, s.school_name
    ORDER BY div1_count DESC
    LIMIT 5
";
$top_div1_schools = [];
if ($exam_year_id) {
    $stmt = $conn->prepare($top_div1_schools_query);
    if ($stmt) {
        $stmt->bind_param('i', $exam_year_id);
        $stmt->execute();
        $top_div1_schools_result = $stmt->get_result();
        while ($row = $top_div1_schools_result->fetch_assoc()) {
            $top_div1_schools[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Top Division 1 schools query preparation failed: " . $conn->error);
    }
}

// Fetch top 5 schools by percentage pass (Divisions 1-4)
$top_pass_schools_query = "
    SELECT 
        s.school_name,
        SUM(CASE WHEN cr.division IN ('Division 1', 'Division 2', 'Division 3', 'Division 4') THEN 1 ELSE 0 END) AS passed_count,
        COUNT(c.id) AS total_candidates,
        (SUM(CASE WHEN cr.division IN ('Division 1', 'Division 2', 'Division 3', 'Division 4') THEN 1 ELSE 0 END) / COUNT(c.id) * 100) AS pass_percentage
    FROM schools s
    JOIN candidates c ON s.id = c.school_id
    LEFT JOIN candidate_results cr ON c.id = cr.candidate_id 
        AND cr.exam_year_id = ?
    WHERE c.exam_year_id = ?
    GROUP BY s.id, s.school_name
    HAVING total_candidates > 0
    ORDER BY pass_percentage DESC
    LIMIT 5
";
$top_pass_schools = [];
if ($exam_year_id) {
    $stmt = $conn->prepare($top_pass_schools_query);
    if ($stmt) {
        $stmt->bind_param('ii', $exam_year_id, $exam_year_id);
        $stmt->execute();
        $top_pass_schools_result = $stmt->get_result();
        while ($row = $top_pass_schools_result->fetch_assoc()) {
            $top_pass_schools[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Top pass schools query preparation failed: " . $conn->error);
    }
}

// Fetch subcounty summaries
$subcounty_query = "
    SELECT 
        sc.subcounty AS subcounty_name,
        SUM(CASE WHEN cr.division = 'Division 1' THEN 1 ELSE 0 END) AS div1,
        SUM(CASE WHEN cr.division = 'Division 2' THEN 1 ELSE 0 END) AS div2,
        SUM(CASE WHEN cr.division = 'Division 3' THEN 1 ELSE 0 END) AS div3,
        SUM(CASE WHEN cr.division = 'Division 4' THEN 1 ELSE 0 END) AS div4,
        SUM(CASE WHEN cr.division = 'Ungraded' THEN 1 ELSE 0 END) AS ungraded,
        SUM(CASE WHEN cr.division = 'X' THEN 1 ELSE 0 END) AS absentees,
        COUNT(c.id) AS total
    FROM subcounties sc
    JOIN schools s ON sc.id = s.subcounty_id
    JOIN candidates c ON s.id = c.school_id
    LEFT JOIN candidate_results cr ON c.id = cr.candidate_id 
        AND cr.exam_year_id = ?
    WHERE c.exam_year_id = ?
    GROUP BY sc.id, sc.subcounty
    ORDER BY div1 DESC, div2 DESC, div3 DESC, div4 DESC
";
$subcounties = [];
if ($exam_year_id) {
    $stmt = $conn->prepare($subcounty_query);
    if ($stmt) {
        $stmt->bind_param('ii', $exam_year_id, $exam_year_id);
        $stmt->execute();
        $subcounty_result = $stmt->get_result();
        $debug_subcounties = [];
        $sn = 1;
        while ($row = $subcounty_result->fetch_assoc()) {
            $row['sn'] = $sn++;
            $subcounties[] = $row;
            $debug_subcounties[] = $row;
        }
        error_log("Subcounty query results: " . json_encode($debug_subcounties));
        $stmt->close();
    } else {
        error_log("Subcounty query preparation failed: " . $conn->error);
    }
}

// Fetch schools with candidates missing results (fewer than 4 subjects)
$missing_results_query = "
    SELECT 
        s.school_name,
        COUNT(DISTINCT c.id) AS candidates_with_missing
    FROM schools s
    JOIN candidates c ON s.id = c.school_id
    LEFT JOIN marks m ON c.id = m.candidate_id
        AND m.exam_year_id = ?
        AND m.status = 'PRESENT'
    WHERE c.exam_year_id = ?
    GROUP BY s.id, s.school_name
    HAVING COUNT(DISTINCT m.subject_id) < 4
    ORDER BY candidates_with_missing DESC
";
$missing_results_schools = [];
if ($exam_year_id) {
    $stmt = $conn->prepare($missing_results_query);
    if ($stmt) {
        $stmt->bind_param('ii', $exam_year_id, $exam_year_id);
        $stmt->execute();
        $missing_results_result = $stmt->get_result();
        while ($row = $missing_results_result->fetch_assoc()) {
            $missing_results_schools[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Missing results query preparation failed: " . $conn->error);
    }
}

// Fetch worst performing subjects (bottom 5 by average mark)
$worst_subjects_query = "
    SELECT sub.name, AVG(m.mark) AS avg_mark
    FROM marks m
    JOIN subjects sub ON m.subject_id = sub.id
    WHERE m.exam_year_id = ?
    AND m.status = 'PRESENT'
    GROUP BY sub.id, sub.name
    ORDER BY avg_mark ASC
    LIMIT 5
";
$worst_subjects = [];
if ($exam_year_id) {
    $stmt = $conn->prepare($worst_subjects_query);
    if ($stmt) {
        $stmt->bind_param('i', $exam_year_id);
        $stmt->execute();
        $worst_subjects_result = $stmt->get_result();
        while ($row = $worst_subjects_result->fetch_assoc()) {
            $worst_subjects[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Worst subjects query preparation failed: " . $conn->error);
    }
}

// Fetch top-performing schools (by avg aggregates)
$top_schools_query = "
    SELECT s.school_name, AVG(CAST(cr.aggregates AS UNSIGNED)) AS avg_aggregates
    FROM candidate_results cr
    JOIN schools s ON cr.school_id = s.id
    WHERE cr.exam_year_id = ?
    AND cr.division != 'X'
    GROUP BY s.id, s.school_name
    ORDER BY avg_aggregates ASC
    LIMIT 5
";
$top_schools = [];
if ($exam_year_id) {
    $stmt = $conn->prepare($top_schools_query);
    if ($stmt) {
        $stmt->bind_param('i', $exam_year_id);
        $stmt->execute();
        $top_schools_result = $stmt->get_result();
        while ($row = $top_schools_result->fetch_assoc()) {
            $top_schools[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Top schools query preparation failed: " . $conn->error);
    }
}

// Fetch subject performance
$subject_performance_query = "
    SELECT sub.name, AVG(m.mark) AS avg_mark
    FROM marks m
    JOIN subjects sub ON m.subject_id = sub.id
    WHERE m.exam_year_id = ?
    AND m.status = 'PRESENT'
    GROUP BY sub.id, sub.name
    ORDER BY avg_mark DESC
";
$subject_chart_data = ['categories' => [], 'data' => []];
if ($exam_year_id) {
    $stmt = $conn->prepare($subject_performance_query);
    if ($stmt) {
        $stmt->bind_param('i', $exam_year_id);
        $stmt->execute();
        $subject_performance_result = $stmt->get_result();
        while ($row = $subject_performance_result->fetch_assoc()) {
            $subject_chart_data['categories'][] = $row['name'];
            $subject_chart_data['data'][] = (float)$row['avg_mark'];
        }
        $stmt->close();
    } else {
        error_log("Subject performance query preparation failed: " . $conn->error);
    }
}

// Start output buffering for content
ob_start();
?>
<div class="welcome-header fade-in">
    <h2>Welcome back, <?php echo htmlspecialchars($username); ?>!</h2>
    <p class="mb-0">
        <i class="fas fa-calendar-alt me-2"></i>
        Examination Administrator Dashboard - <?php echo htmlspecialchars($exam_year); ?>
    </p>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stat-card fade-in">
            <div class="stat-icon"><i class="fas fa-school"></i></div>
            <div class="stat-value"><?php echo number_format($active_schools_count); ?></div>
            <div class="stat-label">Total Exam Centers</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stat-card fade-in">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?php echo number_format($with_results); ?></div>
            <div class="stat-label">With Results</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stat-card fade-in">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-value"><?php echo number_format($without_results); ?></div>
            <div class="stat-label">Missing Results</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stat-card fade-in">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?php echo number_format($candidate_count); ?></div>
            <div class="stat-label">Total Candidates</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stat-card fade-in">
            <div class="stat-icon"><i class="fas fa-male"></i></div>
            <div class="stat-value"><?php echo number_format($male_candidates); ?></div>
            <div class="stat-label">Male Candidates</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stat-card fade-in">
            <div class="stat-icon"><i class="fas fa-female"></i></div>
            <div class="stat-value"><?php echo number_format($female_candidates); ?></div>
            <div class="stat-label">Female Candidates</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stat-card fade-in">
            <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
            <div class="stat-value"><?php echo number_format($absent_candidates); ?></div>
            <div class="stat-label">Absent Candidates</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="chart-card fade-in">
            <h5><i class="fas fa-chart-pie me-2"></i>Results by Division</h5>
            <div id="pie-chart"></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card fade-in">
            <h5><i class="fas fa-chart-bar me-2"></i>Division by Gender</h5>
            <div id="bar-chart"></div>
        </div>
    </div>
</div>

<!-- Subject Performance and Top Schools Row -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="chart-card fade-in">
            <h5><i class="fas fa-chart-column me-2"></i>Subject Performance (Avg. Marks)</h5>
            <div id="subject-chart"></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="acid:0c42be1e-8e3e-4f5c-b7a4-6d8c2e3f9a1c
            <div class="table-card fade-in">
                <h5><i class="fas fa-trophy me-2"></i>Top Performing Schools (Avg. Aggregates)</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><i class="fas fa-school me-1"></i>School Name</th>
                                <th><i class="fas fa-star me-1"></i>Avg. Aggregates</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_schools)): ?>
                                <tr>
                                    <td colspan="2" class="text-center">No top schools data available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_schools as $school): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                                        <td><strong><?php echo number_format($school['avg_aggregates'], 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Top Performing Schools Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="table-card fade-in">
                <h5><i class="fas fa-medal me-2"></i>Top Schools by Division 1</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><i class="fas fa-school me-1"></i>School Name</th>
                                <th><i class="fas fa-trophy me-1"></i>Division 1 Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_div1_schools)): ?>
                                <tr>
                                    <td colspan="2" class="text-center">No Division 1 results available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_div1_schools as $school): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                                        <td><strong><?php echo number_format($school['div1_count']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="table-card fade-in">
                <h5><i class="fas fa-percentage me-2"></i>Top Schools by Pass Rate</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><i class="fas fa-school me-1"></i>School Name</th>
                                <th><i class="fas fa-check-circle me-1"></i>Pass Rate (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_pass_schools)): ?>
                                <tr>
                                    <td colspan="2" class="text-center">No pass rate data available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_pass_schools as $school): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                                        <td><strong><?php echo number_format($school['pass_percentage'], 2); ?>%</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Subcounty Summaries Row -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="table-card fade-in">
                <h5><i class="fas fa-map-marker-alt me-2"></i>Subcounty Performance Summary</h5>
                <div class="table-responsive table-responsive-sm">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><i class="fas fa-list-ol me-1"></i>SN</th>
                                <th><i class="fas fa-map me-1"></i>Subcounty</th>
                                <th><i class="fas fa-medal me-1"></i>Div 1</th>
                                <th class="d-none d-md-table-cell"><i class="fas fa-medal me-1"></i>Div 2</th>
                                <th class="d-none d-md-table-cell"><i class="fas fa-medal me-1"></i>Div 3</th>
                                <th class="d-none d-md-table-cell"><i class="fas fa-medal me-1"></i>Div 4</th>
                                <th class="d-none d-lg-table-cell"><i class="fas fa-times me-1"></i>Ungraded</th>
                                <th class="d-none d-lg-table-cell"><i class="fas fa-user-slash me-1"></i>Absentees</th>
                                <th><i class="fas fa-users me-1"></i>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subcounties)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No subcounty data available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subcounties as $subcounty): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subcounty['sn']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($subcounty['subcounty_name']); ?></strong></td>
                                        <td><?php echo number_format($subcounty['div1']); ?></td>
                                        <td class="d-none d-md-table-cell"><?php echo number_format($subcounty['div2']); ?></td>
                                        <td class="d-none d-md-table-cell"><?php echo number_format($subcounty['div3']); ?></td>
                                        <td class="d-none d-md-table-cell"><?php echo number_format($subcounty['div4']); ?></td>
                                        <td class="d-none d-lg-table-cell"><?php echo number_format($subcounty['ungraded']); ?></td>
                                        <td class="d-none d-lg-table-cell"><?php echo number_format($subcounty['absentees']); ?></td>
                                        <td><strong><?php echo number_format($subcounty['total']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Schools with Missing Results and Worst Subjects Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="table-card fade-in">
                <h5><i class="fas fa-exclamation-circle me-2"></i>Schools with Missing Results</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><i class="fas fa-school me-1"></i>School Name</th>
                                <th><i class="fas fa-users me-1"></i>Candidates Affected</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($missing_results_schools)): ?>
                                <tr>
                                    <td colspan="2" class="text-center">No schools with missing results.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($missing_results_schools as $school): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                                        <td><strong><?php echo number_format($school['candidates_with_missing']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="table-card fade-in">
                <h5><i class="fas fa-chart-line me-2"></i>Worst Performing Subjects</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><i class="fas fa-book me-1"></i>Subject</th>
                                <th><i class="fas fa-percentage me-1"></i>Avg. Mark (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($worst_subjects)): ?>
                                <tr>
                                    <td colspan="2" class="text-center">No subject performance data available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($worst_subjects as $subject): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                        <td><strong><?php echo number_format($subject['avg_mark'], 2); ?>%</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Division Tables Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="table-card fade-in">
                <h5><i class="fas fa-list me-2"></i>Division Summary</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><i class="fas fa-medal me-1"></i>Division</th>
                                <th><i class="fas fa-users me-1"></i>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($divisions)): ?>
                                <tr>
                                    <td colspan="2" class="text-center">No division data available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($divisions as $division => $data): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($division); ?></strong></td>
                                        <td><?php echo number_format($data['total_candidates']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="table-card fade-in">
                <h5><i class="fas fa-venus-mars me-2"></i>Division by Gender</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><i class="fas fa-medal me-1"></i>Division</th>
                                <th><i class="fas fa-mars me-1"></i>Male</th>
                                <th><i class="fas fa-venus me-1"></i>Female</th>
                                <th><i class="fas fa-users me-1"></i>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($divisions)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No division data available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($divisions as $division => $data): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($division); ?></strong></td>
                                        <td><?php echo number_format($data['male_candidates']); ?></td>
                                        <td><?php echo number_format($data['female_candidates']); ?></td>
                                        <td><strong><?php echo number_format($data['total_candidates']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php
$content = ob_get_clean();

// Render layout
renderLayout("Examination Administrator Dashboard - $exam_body", [
    'username' => $username,
    'exam_body' => $exam_body,
    'exam_year' => $exam_year,
    'active_schools_count' => $active_schools_count,
    'with_results' => $with_results,
    'without_results' => $without_results,
    'candidate_count' => $candidate_count,
    'male_candidates' => $male_candidates,
    'female_candidates' => $female_candidates,
    'absent_candidates' => $absent_candidates,
    'divisions' => $divisions,
    'division_data' => $division_data,
    'pie_chart_data' => $pie_chart_data,
    'chart_data' => $chart_data,
    'top_schools' => $top_schools,
    'subject_chart_data' => $subject_chart_data,
    'top_div1_schools' => $top_div1_schools,
    'top_pass_schools' => $top_pass_schools,
    'subcounties' => $subcounties,
    'missing_results_schools' => $missing_results_schools,
    'worst_subjects' => $worst_subjects,
    'content' => $content
]);
?>