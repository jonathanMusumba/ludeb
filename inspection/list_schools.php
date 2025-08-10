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

// Fetch schools
$schools_query = "
    SELECT 
        s.center_no,
        s.school_name,
        st.type AS school_type,
        s.status,
        s.results_status
    FROM schools s
    JOIN school_types st ON s.school_type_id = st.id
    ORDER BY s.center_no ASC
";
$schools = [];
$schools_result = $conn->query($schools_query);
while ($row = $schools_result->fetch_assoc()) {
    $schools[] = $row;
}

// Content
ob_start();
?>
<div class="welcome-header fade-in">
    <h2>List of Schools</h2>
    <p><i class="fas fa-school me-2"></i>View all examination centers for <?php echo htmlspecialchars($exam_year); ?></p>
</div>

<div class="table-card fade-in">
    <h5><i class="fas fa-list me-2"></i>Schools</h5>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><i class="fas fa-id-card me-1"></i>Center Number</th>
                    <th><i class="fas fa-school me-1"></i>School Name</th>
                    <th><i class="fas fa-building me-1"></i>School Type</th>
                    <th><i class="fas fa-info-circle me-1"></i>Status</th>
                    <th><i class="fas fa-clipboard-check me-1"></i>Results Status</th>
                    <th><i class="fas fa-eye me-1"></i>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schools as $school): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($school['center_no']); ?></td>
                        <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                        <td><?php echo htmlspecialchars($school['school_type']); ?></td>
                        <td><?php echo htmlspecialchars($school['status']); ?></td>
                        <td><?php echo htmlspecialchars($school['results_status']); ?></td>
                        <td>
                            <a href="school_details.php?center_no=<?php echo urlencode($school['center_no']); ?>" class="btn btn-primary btn-sm no-loading">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();

// Render layout
renderLayout("List Schools - $exam_body", [
    'username' => $username,
    'exam_body' => $exam_body,
    'exam_year' => $exam_year,
    'content' => $content,
    'current_page' => 'list_schools'
]);
?>