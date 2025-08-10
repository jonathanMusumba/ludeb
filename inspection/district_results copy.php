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

// Fetch exam body and year
$exam_body_query = "SELECT board_name FROM settings WHERE id = 1";
$exam_body_result = $conn->query($exam_body_query);
$exam_body = ($exam_body_result->num_rows > 0) ? $exam_body_result->fetch_assoc()['board_name'] : 'Luuka Examination Board';

$current_year = date("Y"); // 2025
$exam_year_query = "SELECT id, exam_year FROM exam_years WHERE exam_year = ? OR status = 'Active' ORDER BY exam_year DESC LIMIT 1";
$stmt = $conn->prepare($exam_year_query);
if ($stmt === false) {
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
$total_schools_query = "SELECT COUNT(*) AS total FROM schools";
$total_schools_result = $conn->query($total_schools_query);
$total_schools = $total_schools_result->fetch_assoc()['total'] ?? 0;

// Fetch schools with complete results (all candidates have 4 subjects or no candidates)
$valid_schools_query = "
    SELECT s.id
    FROM schools s
    LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    LEFT JOIN (
        SELECT candidate_id, exam_year_id, COUNT(DISTINCT subject_id) AS subject_count
        FROM marks
        WHERE exam_year_id = ?
        GROUP BY candidate_id, exam_year_id
    ) m ON c.id = m.candidate_id AND c.exam_year_id = m.exam_year_id
    WHERE s.results_status = 'Declared'
    GROUP BY s.id
    HAVING COUNT(c.id) = 0 OR (MIN(m.subject_count) = 4 AND MAX(m.subject_count) = 4)
";
$valid_school_ids = [];
if ($exam_year_id) {
    $stmt = $conn->prepare($valid_schools_query);
    $stmt->bind_param('ii', $exam_year_id, $exam_year_id);
    $stmt->execute();
    $valid_schools_result = $stmt->get_result();
    while ($row = $valid_schools_result->fetch_assoc()) {
        $valid_school_ids[] = $row['id'];
    }
    $stmt->close();
}

// Fetch schools with results (declared and complete)
$with_results_query = "
    SELECT COUNT(DISTINCT s.id) AS total
    FROM schools s
    LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    LEFT JOIN (
        SELECT candidate_id, exam_year_id, COUNT(DISTINCT subject_id) AS subject_count
        FROM marks
        WHERE exam_year_id = ?
        GROUP BY candidate_id, exam_year_id
    ) m ON c.id = m.candidate_id AND c.exam_year_id = m.exam_year_id
    WHERE s.results_status = 'Declared'
    GROUP BY s.id
    HAVING COUNT(c.id) = 0 OR (MIN(m.subject_count) = 4 AND MAX(m.subject_count) = 4)
";
$with_results = 0;
if ($exam_year_id) {
    $stmt = $conn->prepare($with_results_query);
    $stmt->bind_param('ii', $exam_year_id, $exam_year_id);
    $stmt->execute();
    $with_results_result = $stmt->get_result();
    $with_results = $with_results_result->num_rows;
    $stmt->close();
}

// Fetch schools with missing results
$missing_results_query = "
    SELECT COUNT(DISTINCT s.id) AS total
    FROM schools s
    LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    LEFT JOIN (
        SELECT candidate_id, exam_year_id, COUNT(DISTINCT subject_id) AS subject_count
        FROM marks
        WHERE exam_year_id = ?
        GROUP BY candidate_id, exam_year_id
    ) m ON c.id = m.candidate_id AND c.exam_year_id = m.exam_year_id
    WHERE s.results_status IN ('Partially Declared', 'Not Declared')
    GROUP BY s.id
    HAVING COUNT(c.id) = 0 OR MIN(m.subject_count) IS NULL OR MIN(m.subject_count) < 4
";
$missing_results = 0;
if ($exam_year_id) {
    $stmt = $conn->prepare($missing_results_query);
    $stmt->bind_param('ii', $exam_year_id, $exam_year_id);
    $stmt->execute();
    $missing_results_result = $stmt->get_result();
    $missing_results = $missing_results_result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

// Fetch schools with incomplete results or no candidates
$incomplete_schools_query = "
    SELECT DISTINCT s.id, s.center_no, s.school_name
    FROM schools s
    LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    LEFT JOIN (
        SELECT candidate_id, exam_year_id, COUNT(DISTINCT subject_id) AS subject_count
        FROM marks
        WHERE exam_year_id = ?
        GROUP BY candidate_id, exam_year_id
    ) m ON c.id = m.candidate_id AND c.exam_year_id = m.exam_year_id
    WHERE s.results_status IN ('Partially Declared', 'Not Declared') OR c.exam_year_id = ?
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

// Fetch division summary
$division_summary_query = "
    SELECT 
        COALESCE(CASE WHEN absent.candidate_id IS NOT NULL THEN 'X' ELSE cr.division END, 'X') AS division,
        COUNT(*) AS total
    FROM candidates c
    JOIN schools s ON c.school_id = s.id
    LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
    JOIN (
        SELECT candidate_id, exam_year_id, COUNT(DISTINCT subject_id) AS subject_count
        FROM marks
        WHERE exam_year_id = ?
        GROUP BY candidate_id, exam_year_id
        HAVING subject_count = 4
    ) m ON c.id = m.candidate_id AND c.exam_year_id = m.exam_year_id
    LEFT JOIN (
        SELECT candidate_id, exam_year_id
        FROM marks
        WHERE exam_year_id = ? AND status = 'ABSENT'
    ) absent ON c.id = absent.candidate_id AND c.exam_year_id = absent.exam_year_id
    WHERE s.results_status = 'Declared' AND c.exam_year_id = ? AND s.id IN (" . (!empty($valid_school_ids) ? implode(',', array_fill(0, count($valid_school_ids), '?')) : '0') . ")
    GROUP BY division
    ORDER BY FIELD(COALESCE(CASE WHEN absent.candidate_id IS NOT NULL THEN 'X' ELSE cr.division END, 'X'), 'Division 1', 'Division 2', 'Division 3', 'Division 4', 'Ungraded', 'X')
";
$divisions = [
    'Division 1' => 0,
    'Division 2' => 0,
    'Division 3' => 0,
    'Division 4' => 0,
    'Ungraded' => 0,
    'X' => 0
];
$total_candidates = 0;
if ($exam_year_id && !empty($valid_school_ids)) {
    $stmt = $conn->prepare($division_summary_query);
    if ($stmt === false) {
        error_log("Prepare failed for division_summary_query: " . $conn->error);
    } else {
        $types = str_repeat('i', count($valid_school_ids) + 4);
        $params = array_merge([$exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id], $valid_school_ids);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $division_result = $stmt->get_result();
        while ($row = $division_result->fetch_assoc()) {
            $divisions[$row['division']] = $row['total'];
            $total_candidates += $row['total'];
        }
        $stmt->close();
    }
}

// Fetch gender summary
$gender_summary_query = "
    SELECT 
        COALESCE(CASE WHEN absent.candidate_id IS NOT NULL THEN 'X' ELSE cr.division END, 'X') AS division,
        SUM(CASE WHEN c.sex = 'Male' THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN c.sex = 'Female' THEN 1 ELSE 0 END) AS female
    FROM candidates c
    JOIN schools s ON c.school_id = s.id
    LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
    JOIN (
        SELECT candidate_id, exam_year_id, COUNT(DISTINCT subject_id) AS subject_count
        FROM marks
        WHERE exam_year_id = ?
        GROUP BY candidate_id, exam_year_id
        HAVING subject_count = 4
    ) m ON c.id = m.candidate_id AND c.exam_year_id = m.exam_year_id
    LEFT JOIN (
        SELECT candidate_id, exam_year_id
        FROM marks
        WHERE exam_year_id = ? AND status = 'ABSENT'
    ) absent ON c.id = absent.candidate_id AND c.exam_year_id = absent.exam_year_id
    WHERE s.results_status = 'Declared' AND c.exam_year_id = ? AND s.id IN (" . (!empty($valid_school_ids) ? implode(',', array_fill(0, count($valid_school_ids), '?')) : '0') . ")
    GROUP BY division
    ORDER BY FIELD(COALESCE(CASE WHEN absent.candidate_id IS NOT NULL THEN 'X' ELSE cr.division END, 'X'), 'Division 1', 'Division 2', 'Division 3', 'Division 4', 'Ungraded', 'X')
";
$gender_data = [];
if ($exam_year_id && !empty($valid_school_ids)) {
    $stmt = $conn->prepare($gender_summary_query);
    if ($stmt === false) {
        error_log("Prepare failed for gender_summary_query: " . $conn->error);
    } else {
        $types = str_repeat('i', count($valid_school_ids) + 4);
        $params = array_merge([$exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id], $valid_school_ids);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $gender_result = $stmt->get_result();
        while ($row = $gender_result->fetch_assoc()) {
            $gender_data[$row['division']] = ['male' => $row['male'], 'female' => $row['female']];
        }
        $stmt->close();
    }
}

// Fetch absent candidates
$absent_query = "
    SELECT COUNT(DISTINCT m.candidate_id) AS absent_count
    FROM marks m
    JOIN schools s ON m.school_id = s.id
    JOIN (
        SELECT candidate_id, exam_year_id, COUNT(DISTINCT subject_id) AS subject_count
        FROM marks
        WHERE exam_year_id = ?
        GROUP BY candidate_id, exam_year_id
        HAVING subject_count = 4
    ) mc ON m.candidate_id = mc.candidate_id AND m.exam_year_id = mc.exam_year_id
    WHERE m.exam_year_id = ? AND m.status = 'ABSENT' AND s.results_status = 'Declared'
    AND s.id IN (" . (!empty($valid_school_ids) ? implode(',', array_fill(0, count($valid_school_ids), '?')) : '0') . ")
";
$absent_candidates = 0;
if ($exam_year_id && !empty($valid_school_ids)) {
    $stmt = $conn->prepare($absent_query);
    if ($stmt === false) {
        error_log("Prepare failed for absent_query: " . $conn->error);
    } else {
        $types = str_repeat('i', count($valid_school_ids) + 2);
        $params = array_merge([$exam_year_id, $exam_year_id], $valid_school_ids);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $absent_result = $stmt->get_result();
        $absent_candidates = $absent_result->fetch_assoc()['absent_count'] ?? 0;
        $stmt->close();
    }
}

// Content
ob_start();
?>
<div class="welcome-header fade-in">
    <h2>District Results</h2>
    <p class="mb-0"><i class="fas fa-file-alt me-2"></i>Summary and downloadable results for <?php echo htmlspecialchars($exam_year); ?></p>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-4 col-lg-4 col-md-12">
        <div class="stat-card fade-in">
            <div class="stat-icon">
                <i class="fas fa-school" style="color: #2a9d8f;"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_schools); ?></div>
            <div class="stat-label">Total Schools</div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-4 col-md-12">
        <div class="stat-card fade-in">
            <div class="stat-icon">
                <i class="fas fa-check-circle" style="color: #2a9d8f;"></i>
            </div>
            <div class="stat-value"><?php echo number_format($with_results); ?></div>
            <div class="stat-label">With Results (Complete)</div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-4 col-md-12">
        <div class="stat-card fade-in">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle" style="color: #e63946;"></i>
            </div>
            <div class="stat-value"><?php echo number_format($missing_results); ?></div>
            <div class="stat-label">Missing Results</div>
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
    <p class="mb-3">Download the complete results or individual candidate result slips for schools with complete results (all candidates have marks for all subjects).</p>
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
                            <tr>
                                <td><?php echo htmlspecialchars($division); ?></td>
                                <td><?php echo number_format($count); ?></td>
                            </tr>
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
                            <tr>
                                <td><?php echo htmlspecialchars($division); ?></td>
                                <td><?php echo number_format($data['male']); ?></td>
                                <td><?php echo number_format($data['female']); ?></td>
                            </tr>
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
                { name: 'Division X', y: <?php echo $divisions['X']; ?>, color: '#d00000' }
            ]
        }],
        tooltip: { pointFormat: '<b>{point.y}</b> candidates ({point.percentage:.1f}%)' },
        legend: { align: 'center', verticalAlign: 'bottom', itemStyle: { fontSize: '10px', fontWeight: '500', color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#2d3436' } },
        accessibility: { enabled: true, point: { valueSuffix: ' candidates' } }
    });

    // Bar Chart for Gender Summary
    Highcharts.chart('gender-chart', {
        chart: { type: 'bar', backgroundColor: 'transparent', style: { fontFamily: 'Inter, sans-serif' } },
        title: { text: 'Results Summary by Gender - <?php echo htmlspecialchars($exam_year); ?>', style: { fontSize: '14px', fontWeight: '600', color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#1d3557' } },
        xAxis: { categories: <?php echo json_encode(array_keys($gender_data)); ?>, title: { text: 'Divisions' } },
        yAxis: { title: { text: 'Number of Candidates' } },
        series: [
            { name: 'Male', data: <?php echo json_encode(array_column($gender_data, 'male')); ?>, color: '#1d3557' },
            { name: 'Female', data: <?php echo json_encode(array_column($gender_data, 'female')); ?>, color: '#e63946' }
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