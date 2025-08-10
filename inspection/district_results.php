<?php
session_start();
require_once 'db_connect.php';
require_once 'layout.php';

// Enable error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Restrict to Examination Administrators
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Examination Administrator') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username'] ?? 'N/A');

// Fetch exam body
$exam_body_query = "SELECT board_name FROM settings WHERE id = 1";
$exam_body_result = $conn->query($exam_body_query);
$exam_body = ($exam_body_result->num_rows > 0) ? $exam_body_result->fetch_assoc()['board_name'] : 'Luuka Examination Board';

// Fetch exam year
$current_year = date("Y");
$exam_year_query = "SELECT id, exam_year FROM exam_years WHERE exam_year = ? OR status = 'Active' ORDER BY exam_year DESC LIMIT 1";
$stmt = $conn->prepare($exam_year_query);
if ($stmt === false) {
    error_log("district_results.php: Prepare failed for exam_year_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param('i', $current_year);
$stmt->execute();
$exam_year_result = $stmt->get_result();
$exam_year_data = $exam_year_result->num_rows > 0 ? $exam_year_result->fetch_assoc() : ['exam_year' => 'N/A', 'id' => null];
$exam_year = $exam_year_data['exam_year'];
$exam_year_id = $exam_year_data['id'];
$stmt->close();

// Fetch total schools
$total_schools_query = "SELECT COUNT(*) AS total FROM schools WHERE status = 'Active'";
$total_schools_result = $conn->query($total_schools_query);
$total_schools = $total_schools_result->fetch_assoc()['total'] ?? 0;

// Fetch schools with results
$with_results_query = "SELECT COUNT(id) AS with_results FROM schools WHERE results_status = 'Declared' AND status = 'Active'";
$with_results_result = $conn->query($with_results_query);
$with_results = $with_results_result->num_rows > 0 ? $with_results_result->fetch_assoc()['with_results'] : 0;

// Fetch schools with missing results
$without_results_query = "SELECT COUNT(*) AS without_results FROM schools WHERE results_status IN ('Not Declared', 'Partially Declared') AND status = 'Active'";
$without_results_result = $conn->query($without_results_query);
$without_results = $without_results_result->num_rows > 0 ? $without_results_result->fetch_assoc()['without_results'] : 0;

// Fetch schools with incomplete results or no candidates
$incomplete_schools_query = "
    SELECT DISTINCT s.id, s.center_no, s.school_name
    FROM schools s
    LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    LEFT JOIN (
        SELECT candidate_id, exam_year_id, COUNT(DISTINCT subject_id) AS subject_count
        FROM marks
        WHERE exam_year_id = ? AND status = 'PRESENT'
        GROUP BY candidate_id, exam_year_id
    ) m ON c.id = m.candidate_id AND c.exam_year_id = m.exam_year_id
    WHERE s.results_status IN ('Partially Declared', 'Not Declared') AND c.exam_year_id = ? AND s.status = 'Active'
    GROUP BY s.id, s.center_no, s.school_name
    HAVING COUNT(c.id) = 0 OR MIN(m.subject_count) IS NULL OR MIN(m.subject_count) < 4
    ORDER BY s.center_no
";
$incomplete_schools = [];
if ($exam_year_id) {
    $stmt = $conn->prepare($incomplete_schools_query);
    $stmt->bind_param('iii', $exam_year_id, $exam_year_id, $exam_year_id);
    $stmt->execute();
    $incomplete_schools_result = $stmt->get_result();
    while ($row = $incomplete_schools_result->fetch_assoc()) {
        $incomplete_schools[] = $row;
    }
    $stmt->close();
}

// Fetch valid school IDs for division and gender summaries
$valid_schools_query = "
    SELECT s.id
    FROM schools s
    WHERE s.results_status = 'Declared' AND s.status = 'Active'
";
$valid_school_ids = [];
$valid_schools_result = $conn->query($valid_schools_query);
while ($row = $valid_schools_result->fetch_assoc()) {
    $valid_school_ids[] = $row['id'];
}

// Fetch absent candidates with categories
$absent_query = "
    SELECT 
        COUNT(DISTINCT cr.candidate_id) AS absent_count,
        SUM(CASE WHEN abs.missed_subjects = 4 THEN 1 ELSE 0 END) AS all_subjects,
        SUM(CASE WHEN abs.missed_subjects = 1 THEN 1 ELSE 0 END) AS one_subject,
        SUM(CASE WHEN abs.missed_subjects = 2 THEN 1 ELSE 0 END) AS two_subjects,
        SUM(CASE WHEN abs.missed_subjects = 3 THEN 1 ELSE 0 END) AS three_subjects
    FROM candidate_results cr
    JOIN schools s ON cr.school_id = s.id
    JOIN subcounties sc ON s.subcounty_id = sc.id
    LEFT JOIN (
        SELECT 
            m.candidate_id,
            COUNT(*) AS missed_subjects
        FROM marks m
        WHERE m.exam_year_id = ? AND (m.status = 'ABSENT' OR (m.status IS NULL AND m.mark = 0))
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
    WHERE cr.exam_year_id = ? AND cr.division = 'X' AND s.status = 'Active'
";
$absent_candidates = [
    'absent_count' => 0,
    'all_subjects' => 0,
    'one_subject' => 0,
    'two_subjects' => 0,
    'three_subjects' => 0
];
if ($exam_year_id) {
    $stmt = $conn->prepare($absent_query);
    if ($stmt) {
        $stmt->bind_param('iii', $exam_year_id, $exam_year_id, $exam_year_id);
        $stmt->execute();
        $absent_result = $stmt->get_result();
        $absent_candidates = $absent_result->fetch_assoc() ?: $absent_candidates;
        $stmt->close();
    } else {
        error_log("district_results.php: Prepare failed for absent_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    }
}

// Fetch absentee details grouped by school
$absentee_details_query = "
    SELECT 
        s.school_name,
        c.index_number,
        c.candidate_name,
        GROUP_CONCAT(CONCAT(sub.name, ': ', COALESCE(m.mark, 'ABSENT'))) AS subject_scores,
        COUNT(CASE WHEN m.status = 'ABSENT' OR (m.status IS NULL AND m.mark = 0) THEN 1 END) AS missed_subjects
    FROM candidates c
    JOIN schools s ON c.school_id = s.id
    LEFT JOIN marks m ON c.id = m.candidate_id AND m.exam_year_id = ?
    LEFT JOIN subjects sub ON m.subject_id = sub.id
    WHERE c.exam_year_id = ? AND (m.status = 'ABSENT' OR (m.status IS NULL AND m.mark = 0) OR m.candidate_id IS NULL)
    GROUP BY s.id, s.school_name, c.id, c.index_number, c.candidate_name
    HAVING missed_subjects > 0 OR COUNT(m.id) = 0
    ORDER BY s.school_name, c.index_number
";
$absentee_details = [];
if ($exam_year_id) {
    $stmt = $conn->prepare($absentee_details_query);
    if ($stmt) {
        $stmt->bind_param('ii', $exam_year_id, $exam_year_id);
        $stmt->execute();
        $absentee_result = $stmt->get_result();
        while ($row = $absentee_result->fetch_assoc()) {
            $absentee_details[$row['school_name']][] = [
                'index_number' => $row['index_number'],
                'candidate_name' => $row['candidate_name'],
                'subject_scores' => $row['subject_scores'],
                'missed_subjects' => $row['missed_subjects'] ?: 4 // Assume 4 if no marks
            ];
        }
        $stmt->close();
    } else {
        error_log("district_results.php: Prepare failed for absentee_details_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    }
}

// Fetch division summary with absentee categories
$division_summary_query = "
    SELECT 
        COALESCE(CASE WHEN abs.missed_subjects IS NOT NULL THEN 
            CASE 
                WHEN abs.missed_subjects = 4 THEN 'All Subjects Absent'
                WHEN abs.missed_subjects = 1 THEN 'One Subject Absent'
                WHEN abs.missed_subjects = 2 THEN 'Two Subjects Absent'
                WHEN abs.missed_subjects = 3 THEN 'Three Subjects Absent'
                ELSE 'X'
            END 
            ELSE cr.division END, 'X') AS division,
        COUNT(*) AS total
    FROM candidates c
    JOIN schools s ON c.school_id = s.id
    LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
    LEFT JOIN (
        SELECT 
            m.candidate_id,
            COUNT(*) AS missed_subjects
        FROM marks m
        WHERE m.exam_year_id = ? AND (m.status = 'ABSENT' OR (m.status IS NULL AND m.mark = 0))
        GROUP BY m.candidate_id
        UNION
        SELECT 
            c2.id AS candidate_id,
            4 AS missed_subjects
        FROM candidates c2
        WHERE c2.exam_year_id = ?
        AND NOT EXISTS (
            SELECT 1
            FROM marks m
            WHERE m.candidate_id = c2.id
            AND m.exam_year_id = c2.exam_year_id
        )
    ) abs ON c.id = abs.candidate_id
    WHERE c.exam_year_id = ? AND s.status = 'Active'
    GROUP BY division
    ORDER BY FIELD(COALESCE(CASE WHEN abs.missed_subjects IS NOT NULL THEN 
        CASE 
            WHEN abs.missed_subjects = 4 THEN 'All Subjects Absent'
            WHEN abs.missed_subjects = 1 THEN 'One Subject Absent'
            WHEN abs.missed_subjects = 2 THEN 'Two Subjects Absent'
            WHEN abs.missed_subjects = 3 THEN 'Three Subjects Absent'
            ELSE 'X'
        END 
        ELSE cr.division END, 'X'), 
        'Division 1', 'Division 2', 'Division 3', 'Division 4', 'Ungraded', 'All Subjects Absent', 'One Subject Absent', 'Two Subjects Absent', 'Three Subjects Absent', 'X')
";
$divisions = [
    'Division 1' => 0,
    'Division 2' => 0,
    'Division 3' => 0,
    'Division 4' => 0,
    'Ungraded' => 0,
    'All Subjects Absent' => 0,
    'One Subject Absent' => 0,
    'Two Subjects Absent' => 0,
    'Three Subjects Absent' => 0,
    'X' => 0
];
$total_candidates = 0;
if ($exam_year_id) {
    $stmt = $conn->prepare($division_summary_query);
    if ($stmt) {
        $stmt->bind_param('iiii', $exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id);
        $stmt->execute();
        $division_result = $stmt->get_result();
        while ($row = $division_result->fetch_assoc()) {
            $divisions[$row['division']] = $row['total'];
            $total_candidates += $row['total'];
        }
        $stmt->close();
    } else {
        error_log("district_results.php: Prepare failed for division_summary_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    }
}

// Fetch gender summary with absentee categories
$gender_summary_query = "
    SELECT 
        COALESCE(CASE WHEN abs.missed_subjects IS NOT NULL THEN 
            CASE 
                WHEN abs.missed_subjects = 4 THEN 'All Subjects Absent'
                WHEN abs.missed_subjects = 1 THEN 'One Subject Absent'
                WHEN abs.missed_subjects = 2 THEN 'Two Subjects Absent'
                WHEN abs.missed_subjects = 3 THEN 'Three Subjects Absent'
                ELSE 'X'
            END 
            ELSE cr.division END, 'X') AS division,
        SUM(CASE WHEN c.sex = 'Male' THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN c.sex = 'Female' THEN 1 ELSE 0 END) AS female
    FROM candidates c
    JOIN schools s ON c.school_id = s.id
    LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
    LEFT JOIN (
        SELECT 
            m.candidate_id,
            COUNT(*) AS missed_subjects
        FROM marks m
        WHERE m.exam_year_id = ? AND (m.status = 'ABSENT' OR (m.status IS NULL AND m.mark = 0))
        GROUP BY m.candidate_id
        UNION
        SELECT 
            c2.id AS candidate_id,
            4 AS missed_subjects
        FROM candidates c2
        WHERE c2.exam_year_id = ?
        AND NOT EXISTS (
            SELECT 1
            FROM marks m
            WHERE m.candidate_id = c2.id
            AND m.exam_year_id = c2.exam_year_id
        )
    ) abs ON c.id = abs.candidate_id
    WHERE c.exam_year_id = ? AND s.status = 'Active'
    GROUP BY division
    ORDER BY FIELD(COALESCE(CASE WHEN abs.missed_subjects IS NOT NULL THEN 
        CASE 
            WHEN abs.missed_subjects = 4 THEN 'All Subjects Absent'
            WHEN abs.missed_subjects = 1 THEN 'One Subject Absent'
            WHEN abs.missed_subjects = 2 THEN 'Two Subjects Absent'
            WHEN abs.missed_subjects = 3 THEN 'Three Subjects Absent'
            ELSE 'X'
        END 
        ELSE cr.division END, 'X'), 
        'Division 1', 'Division 2', 'Division 3', 'Division 4', 'Ungraded', 'All Subjects Absent', 'One Subject Absent', 'Two Subjects Absent', 'Three Subjects Absent', 'X')
";
$gender_data = [];
if ($exam_year_id) {
    $stmt = $conn->prepare($gender_summary_query);
    if ($stmt) {
        $stmt->bind_param('iiii', $exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id);
        $stmt->execute();
        $gender_result = $stmt->get_result();
        while ($row = $gender_result->fetch_assoc()) {
            $gender_data[$row['division']] = ['male' => $row['male'], 'female' => $row['female']];
        }
        $stmt->close();
    } else {
        error_log("district_results.php: Prepare failed for gender_summary_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    }
}

// Log results
error_log("district_results.php: Fetched summary for exam_year_id=$exam_year_id, user_id=$user_id, total_candidates=$total_candidates, " .
          "division_x={$divisions['X']}, all_subjects_absent={$divisions['All Subjects Absent']}, " .
          "one_subject_absent={$divisions['One Subject Absent']}, two_subjects_absent={$divisions['Two Subjects Absent']}, " .
          "three_subjects_absent={$divisions['Three Subjects Absent']}, absent_count={$absent_candidates['absent_count']}", 
          3, __DIR__ . '/logs/setup_errors.log');

// Content
ob_start();
?>
<div class="welcome-header fade-in">
    <h2>District Results</h2>
    <p class="mb-0"><i class="fas fa-file-alt me-2"></i>Summary and downloadable results for <?php echo htmlspecialchars($exam_year); ?></p>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-4 col-md-12">
        <div class="stat-card fade-in">
            <div class="stat-icon">
                <i class="fas fa-school" style="color: #2a9d8f;"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_schools); ?></div>
            <div class="stat-label">Total Schools</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-12">
        <div class="stat-card fade-in">
            <div class="stat-icon">
                <i class="fas fa-check-circle" style="color: #2a9d8f;"></i>
            </div>
            <div class="stat-value"><?php echo number_format($with_results); ?></div>
            <div class="stat-label">With Results</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-12">
        <div class="stat-card fade-in">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle" style="color: #e63946;"></i>
            </div>
            <div class="stat-value"><?php echo number_format($without_results); ?></div>
            <div class="stat-label">Missing Results</div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-4 col-md-12">
        <div class="stat-card fade-in" data-bs-toggle="modal" data-bs-target="#absenteeModal" style="cursor: pointer;">
            <div class="stat-icon">
                <i class="fas fa-user-slash" style="color: #d00000;"></i>
            </div>
            <div class="stat-value"><?php echo number_format($absent_candidates['absent_count']); ?></div>
            <div class="stat-label">Absent Candidates</div>
        </div>
    </div>
</div>

<!-- Absentee Modal -->
<div class="modal fade" id="absenteeModal" tabindex="-1" aria-labelledby="absenteeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="absenteeModalLabel"><i class="fas fa-user-slash me-2"></i>Absent Candidates by School</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($absentee_details)): ?>
                    <p class="text-center">No absentee data available.</p>
                <?php else: ?>
                    <?php foreach ($absentee_details as $school_name => $candidates): ?>
                        <h6><i class="fas fa-school me-2"></i><?php echo htmlspecialchars($school_name); ?> (<?php echo count($candidates); ?> Absentees)</h6>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Index Number</th>
                                        <th>Candidate Name</th>
                                        <th>Subject Scores</th>
                                        <th>Missed Subjects</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($candidates as $candidate): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($candidate['index_number']); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['candidate_name']); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['subject_scores']); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['missed_subjects']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td colspan="4" class="text-end">
                                            <strong>Total: <?php echo count($candidates); ?></strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-6 col-lg-6 col-md-12">
        <div class="chart-card fade-in">
            <h5><i class="fas fa-chart-pie me-2"></i>Division Distribution</h5>
            <div id="donut-chart" style="height: 300px; width: 100%;"></div>
        </div>
    </div>
    <div class="col-xl-6 col-lg-6 col-md-12">
        <div class="chart-card fade-in">
            <h5><i class="fas fa-chart-bar me-2"></i>Results Summary by Gender</h5>
            <div id="gender-chart" style="height: 300px; width: 100%;"></div>
        </div>
    </div>
</div>

<div class="table-card fade-in mb-4">
    <h5><i class="fas fa-download me-2"></i>Download Results</h5>
    <p class="mb-3">Download the complete results or individual candidate result slips for schools with complete results.</p>
    <div class="d-flex flex-wrap gap-3">
        <a href="generate_district_results.php?exam_year_id=<?php echo $exam_year_id; ?>" class="btn btn-primary btn-lg" id="downloadPdf">
            <i class="fas fa-file-pdf me-2"></i>Download District Results PDF
        </a>
        <a href="generate_all_schools_slips.php?exam_year_id=<?php echo $exam_year_id; ?>" class="btn btn-success btn-lg" id="downloadSlips">
            <i class="fas fa-file-pdf me-2"></i>Download All Result Slips
        </a>
    </div>
    <div class="progress mt-3" id="downloadProgress" style="display: none;">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
</div>

<?php if (!empty($incomplete_schools)): ?>
<div class="table-card fade-in mb-4">
    <h5><i class="fas fa-exclamation-circle me-2"></i>Schools with Incomplete or No Results</h5>
    <p class="mb-3">The following schools have candidates missing marks for one or more subjects or no registered candidates. Download their results separately.</p>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Center No</th>
                    <th>School Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incomplete_schools as $school): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($school['center_no']); ?></td>
                        <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                        <td>
                            <a href="generate_incomplete_school_results.php?center_no=<?php echo urlencode($school['center_no']); ?>&exam_year_id=<?php echo $exam_year_id; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-file-pdf me-2"></i>Download Results
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-6 col-lg-6 col-md-12">
        <div class="table-card fade-in">
            <h5><i class="fas fa-table me-2"></i>Results Summary by Division</h5>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Division</th>
                            <th>Total Candidates</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($divisions as $division => $count): ?>
                            <?php if ($count > 0): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($division); ?></td>
                                    <td><?php echo number_format($count); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-6 col-lg-6 col-md-12">
        <div class="table-card fade-in">
            <h5><i class="fas fa-table me-2"></i>Results Summary by Gender</h5>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Division</th>
                            <th>Male</th>
                            <th>Female</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gender_data as $division => $data): ?>
                            <?php if ($data['male'] > 0 || $data['female'] > 0): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($division); ?></td>
                                    <td><?php echo number_format($data['male']); ?></td>
                                    <td><?php echo number_format($data['female']); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

// Render layout
renderLayout("District Results - $exam_body", [
    'username' => $username,
    'exam_body' => $exam_body,
    'exam_year' => $exam_year,
    'content' => $content,
    'current_page' => 'district_results'
]);
?>

<script>
$(document).ready(function() {
    // Donut Chart for Division Distribution
    Highcharts.chart('donut-chart', {
        chart: { type: 'pie', backgroundColor: 'transparent', style: { fontFamily: 'Inter, sans-serif' } },
        title: { text: 'Division Distribution - <?php echo htmlspecialchars($exam_year); ?>', style: { fontSize: '14px', fontWeight: '600', color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#1d3557' } },
        plotOptions: {
            pie: {
                innerSize: '50%',
                dataLabels: { enabled: true, format: '{point.name}: {point.y} ({point.percentage:.1f}%)', style: { fontSize: '10px', fontWeight: '500' } },
                showInLegend: true
            }
        },
        series: [{
            name: 'Candidates',
            colorByPoint: true,
            data: [
                { name: 'Division 1', y: <?php echo $divisions['Division 1']; ?>, color: '#1d3557' },
                { name: 'Division 2', y: <?php echo $divisions['Division 2']; ?>, color: '#457b9d' },
                { name: 'Division 3', y: <?php echo $divisions['Division 3']; ?>, color: '#a8dadc' },
                { name: 'Division 4', y: <?php echo $divisions['Division 4']; ?>, color: '#f4a261' },
                { name: 'Ungraded', y: <?php echo $divisions['Ungraded']; ?>, color: '#e63946' },
                { name: 'All Subjects Absent', y: <?php echo $divisions['All Subjects Absent']; ?>, color: '#d00000' },
                { name: 'One Subject Absent', y: <?php echo $divisions['One Subject Absent']; ?>, color: '#b00000' },
                { name: 'Two Subjects Absent', y: <?php echo $divisions['Two Subjects Absent']; ?>, color: '#900000' },
                { name: 'Three Subjects Absent', y: <?php echo $divisions['Three Subjects Absent']; ?>, color: '#700000' },
                { name: 'X', y: <?php echo $divisions['X']; ?>, color: '#500000' }
            ].filter(item => item.y > 0) // Only include non-zero values
        }],
        tooltip: { pointFormat: '<b>{point.y}</b> candidates ({point.percentage:.1f}%)' },
        legend: { align: 'center', verticalAlign: 'bottom', itemStyle: { fontSize: '10px', fontWeight: '500', color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#2d3436' } },
        accessibility: { enabled: true, point: { valueSuffix: ' candidates' } }
    });

    // Bar Chart for Gender Summary
    Highcharts.chart('gender-chart', {
        chart: { type: 'bar', backgroundColor: 'transparent', style: { fontFamily: 'Inter, sans-serif' } },
        title: { text: 'Results Summary by Gender - <?php echo htmlspecialchars($exam_year); ?>', style: { fontSize: '14px', fontWeight: '600', color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#1d3557' } },
        xAxis: { categories: <?php echo json_encode(array_keys(array_filter($gender_data, function($data) { return $data['male'] > 0 || $data['female'] > 0; }))); ?>, title: { text: 'Divisions' } },
        yAxis: { title: { text: 'Number of Candidates' } },
        series: [
            { name: 'Male', data: <?php echo json_encode(array_column(array_filter($gender_data, function($data) { return $data['male'] > 0 || $data['female'] > 0; }), 'male')); ?>, color: '#1d3557' },
            { name: 'Female', data: <?php echo json_encode(array_column(array_filter($gender_data, function($data) { return $data['male'] > 0 || $data['female'] > 0; }), 'female')); ?>, color: '#e63946' }
        ],
        plotOptions: { bar: { dataLabels: { enabled: true } } },
        tooltip: { shared: true },
        legend: { align: 'center', verticalAlign: 'bottom', itemStyle: { fontSize: '10px', fontWeight: '500', color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#2d3436' } },
        accessibility: { enabled: true, point: { valueSuffix: ' candidates' } }
    });

    // Download buttons loading state
    $('#downloadPdf, #downloadSlips').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $progress = $('#downloadProgress');
        $btn.prop('disabled', true);
        $btn.html('<i class="fas fa-spinner fa-spin me-2"></i>Downloading...');
        $progress.show();

        let progress = 0;
        const interval = setInterval(() => {
            progress += 10;
            $progress.find('.progress-bar').css('width', progress + '%').attr('aria-valuenow', progress);
            if (progress >= 100) {
                clearInterval(interval);
                $progress.hide();
                $btn.prop('disabled', false);
                $btn.html('<i class="fas fa-file-pdf me-2"></i>' + ($btn.attr('id') === 'downloadPdf' ? 'Download District Results PDF' : 'Download All Result Slips'));
                window.location.href = $btn.attr('href');
            }
        }, 200);

        setTimeout(() => {
            clearInterval(interval);
            $progress.hide();
            $btn.prop('disabled', false);
            $btn.html('<i class="fas fa-file-pdf me-2"></i>' + ($btn.attr('id') === 'downloadPdf' ? 'Download District Results PDF' : 'Download All Result Slips'));
            window.location.href = $btn.attr('href');
        }, 2000);
    });
});
</script>