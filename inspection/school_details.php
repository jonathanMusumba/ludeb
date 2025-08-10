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
$username = htmlspecialchars($_SESSION['username'] ?? 'N/A');
$center_no = isset($_GET['center_no']) ? $_GET['center_no'] : '';

if (!$center_no) {
    header("Location: list_schools.php");
    exit();
}

// Fetch exam body and year
$exam_body_query = "SELECT board_name FROM settings WHERE id = 1";
$exam_body_result = $conn->query($exam_body_query);
$exam_body = ($exam_body_result->num_rows > 0) ? $exam_body_result->fetch_assoc()['board_name'] : 'Luuka Examination Board';

$current_year = date("Y");
$exam_year_query = "SELECT id, exam_year FROM exam_years WHERE exam_year = ? OR status = 'Active' ORDER BY exam_year DESC LIMIT 1";
$stmt = $conn->prepare($exam_year_query);
$stmt->bind_param('i', $current_year);
$stmt->execute();
$exam_year_result = $stmt->get_result();
$exam_year_data = $exam_year_result->num_rows > 0 ? $exam_year_result->fetch_assoc() : ['exam_year' => 'N/A', 'id' => null];
$exam_year = $exam_year_data['exam_year'];
$exam_year_id = $exam_year_data['id'];
$stmt->close();

// Fetch school details
$school_query = "
    SELECT 
        s.center_no,
        s.school_name,
        st.type AS school_type,
        s.status,
        s.results_status,
        COUNT(c.id) AS candidate_count
    FROM schools s
    JOIN school_types st ON s.school_type_id = st.id
    LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    WHERE s.center_no = ?
    GROUP BY s.id, s.center_no, s.school_name, st.type, s.status, s.results_status
";
$school = null;
if ($exam_year_id) {
    $stmt = $conn->prepare($school_query);
    $stmt->bind_param('is', $exam_year_id, $center_no);
    $stmt->execute();
    $school_result = $stmt->get_result();
    $school = $school_result->num_rows > 0 ? $school_result->fetch_assoc() : null;
    $stmt->close();
}

if (!$school) {
    header("Location: list_schools.php");
    exit();
}

// Fetch candidate results
$results_query = "
    SELECT 
        sr.candidate_index_number AS index_no,
        sr.candidate_name,
        MAX(CASE WHEN s.code = 'ENG' THEN CONCAT(sr.marks, '/', sr.grade) END) AS eng,
        MAX(CASE WHEN s.code = 'SCI' THEN CONCAT(sr.marks, '/', sr.grade) END) AS sci,
        MAX(CASE WHEN s.code = 'MTC' THEN CONCAT(sr.marks, '/', sr.grade) END) AS mtc,
        MAX(CASE WHEN s.code = 'SST' THEN CONCAT(sr.marks, '/', sr.grade) END) AS sst,
        cr.aggregates AS agg,
        cr.division AS div
    FROM school_results sr
    JOIN subjects s ON sr.subject_code = s.code
    JOIN candidate_results cr ON sr.candidate_id = cr.candidate_id AND sr.exam_year_id = cr.exam_year_id
    WHERE sr.school_id = (SELECT id FROM schools WHERE center_no = ?)
    AND sr.exam_year_id = ?
    GROUP BY sr.candidate_id, sr.candidate_index_number, sr.candidate_name, cr.aggregates, cr.division
    ORDER BY sr.candidate_index_number
";
$results = [];
if ($exam_year_id) {
    $stmt = $conn->prepare($results_query);
    $stmt->bind_param('si', $center_no, $exam_year_id);
    $stmt->execute();
    $results_result = $stmt->get_result();
    while ($row = $results_result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
}

// Content
ob_start();
?>
<div class="welcome-header fade-in">
    <h2><?php echo htmlspecialchars($school['school_name']); ?></h2>
    <p><i class="fas fa-school me-2"></i>Details for Center <?php echo htmlspecialchars($school['center_no']); ?> - <?php echo htmlspecialchars($exam_year); ?></p>
</div>

<div class="table-card fade-in">
    <h5><i class="fas fa-info-circle me-2"></i>School Details</h5>
    <div class="table-responsive">
        <table class="table table-striped">
            <tbody>
                <tr>
                    <th>Center Number</th>
                    <td><?php echo htmlspecialchars($school['center_no']); ?></td>
                </tr>
                <tr>
                    <th>School Name</th>
                    <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                </tr>
                <tr>
                    <th>School Type</th>
                    <td><?php echo htmlspecialchars($school['school_type']); ?></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td><?php echo htmlspecialchars($school['status']); ?></td>
                </tr>
                <tr>
                    <th>Results Status</th>
                    <td><?php echo htmlspecialchars($school['results_status']); ?></td>
                </tr>
                <tr>
                    <th>Number of Candidates</th>
                    <td><?php echo number_format($school['candidate_count']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="table-card fade-in">
    <h5><i class="fas fa-list me-2"></i>Candidate Results</h5>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><i class="fas fa-id-card me-1"></i>Index No</th>
                    <th><i class="fas fa-user me-1"></i>Candidate Name</th>
                    <th><i class="fas fa-book me-1"></i>English (Marks/Grade)</th>
                    <th><i class="fas fa-flask me-1"></i>Science</th>
                    <th><i class="fas fa-calculator me-1"></i>Mathematics</th>
                    <th><i class="fas fa-globe me-1"></i>Social Studies</th>
                    <th><i class="fas fa-trophy me-1"></i>Aggregates</th>
                    <th><i class="fas fa-medal me-1"></i>Division</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="8" class="text-center">No results available for this school.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $result): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($result['index_no']); ?></td>
                            <td><?php echo htmlspecialchars($result['candidate_name']); ?></td>
                            <td><?php echo htmlspecialchars($result['eng'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($result['sci'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($result['mtc'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($result['sst'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($result['agg']); ?></td>
                            <td><?php echo htmlspecialchars($result['div']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();

// Render layout
renderLayout("School Details - {$school['school_name']} - $exam_body", [
    'username' => $username,
    'exam_body' => $exam_body,
    'exam_year' => $exam_year,
    'content' => $content,
    'current_page' => 'list_schools'
]);
?>