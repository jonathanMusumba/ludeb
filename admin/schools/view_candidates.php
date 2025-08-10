<?php
ob_start();
require_once '../db_connect.php';

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Debug session
error_log("view_school.php: User ID: " . ($_SESSION['user_id'] ?? 'Not set') . ", Role: " . ($_SESSION['role'] ?? 'Not set'), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    ob_clean();
    header("Location: " . $root_url . "login.php");
    exit();
}

// Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Log page access
$user_id = $_SESSION['user_id'];
$conn->query("CALL log_action('View School Access', $user_id, 'System Admin accessed view school page')");

$center_no = isset($_GET['center_no']) ? trim($_GET['center_no']) : '';
if (empty($center_no) || !preg_match('/^\d{6}$/', $center_no)) {
    ob_clean();
    header("Location: schools/manage_schools.php?error=" . urlencode("Invalid center number"));
    exit();
}

// Fetch school data
$stmt = $conn->prepare("
    SELECT s.id, s.center_no, s.school_name, s.subcounty_id, s.school_type_id, s.results_status, 
           sc.subcounty, st.type
    FROM schools s
    JOIN subcounties sc ON s.subcounty_id = sc.id
    JOIN school_types st ON s.school_type_id = st.id
    WHERE s.center_no = ?
");
$stmt->bind_param("s", $center_no);
$stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
if (!$school) {
    ob_clean();
    header("Location: schools/manage_schools.php?error=" . urlencode("School not found"));
    exit();
}
$school_id = $school['id'];

// Fetch exam years and default to current year
$current_year = date('Y');
$default_exam_year_id = 0;
$exam_years_result = $conn->query("SELECT id, exam_year FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC");
$exam_years = $exam_years_result->fetch_all(MYSQLI_ASSOC);
foreach ($exam_years as $year) {
    if ($year['exam_year'] == $current_year) {
        $default_exam_year_id = $year['id'];
        break;
    }
}
if ($default_exam_year_id == 0 && !empty($exam_years)) {
    $default_exam_year_id = $exam_years[0]['id'];
}

// Fetch uploaded files
$stmt = $conn->prepare("SELECT id, filename, uploaded_at FROM uploads WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$uploads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Display success or error messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$status_badges = [
    'Not Declared' => 'danger',
    'Partially Declared' => 'warning',
    'Declared' => 'success'
];
$status_class = $status_badges[$school['results_status']] ?? 'secondary';

// Set page title
$page_title = "View School: " . htmlspecialchars($school['school_name']);

// Define content
ob_start();
?>
<div class="page-header">
    <h1 class="page-title">View School: <?php echo htmlspecialchars($school['school_name']); ?></h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="schools/manage_schools.php">Manage Schools</a></li>
            <li class="breadcrumb-item active" aria-current="page">View School</li>
        </ol>
    </nav>
</div>

<?php if ($success): ?>
    <div class="alert alert-enhanced alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-enhanced alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="dashboard-card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h4>School Profile</h4>
                <p><strong>Center Number:</strong> <?php echo htmlspecialchars($school['center_no']); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($school['school_name']); ?></p>
                <p><strong>Subcounty:</strong> <?php echo htmlspecialchars($school['subcounty']); ?></p>
                <p><strong>School Type:</strong> <?php echo htmlspecialchars($school['type']); ?></p>
                <p><strong>Results Status:</strong> <span class="badge bg-<?php echo $status_class; ?>"><?php echo htmlspecialchars($school['results_status']); ?></span></p>
            </div>
            <div class="col-md-6">
                <a href="schools/download_candidates_template.php?center_no=<?php echo urlencode($school['center_no']); ?>" class="btn btn-enhanced mb-3">Download Candidates Template</a>
                <a href="schools/download_results.php?center_no=<?php echo urlencode($school['center_no']); ?>" class="btn btn-enhanced mb-3">Download Results (Excel)</a>
                <a href="schools/download_results_pdf.php?center_no=<?php echo urlencode($school['center_no']); ?>" class="btn btn-enhanced mb-3">Download Results (PDF)</a>
                <a href="schools/capture_results.php?center_no=<?php echo urlencode($school['center_no']); ?>" class="btn btn-enhanced mb-3">Capture Results</a>
                <a href="schools/view_candidates.php?center_no=<?php echo urlencode($school['center_no']); ?>" class="btn btn-enhanced mb-3">View Candidates</a>
                <form id="uploadForm" method="POST" action="schools/upload_candidates.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="center_no" value="<?php echo htmlspecialchars($school['center_no']); ?>">
                    <div class="mb-3">
                        <label for="exam_year_id" class="form-label">Exam Year:</label>
                        <select class="form-control" id="exam_year_id" name="exam_year_id" required>
                            <option value="">Select Exam Year</option>
                            <?php foreach ($exam_years as $year): ?>
                                <option value="<?php echo $year['id']; ?>" <?php echo $year['id'] == $default_exam_year_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year['exam_year']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="file" class="form-label">Upload Candidates (Excel):</label>
                        <input type="file" class="form-control" id="file" name="file" accept=".xlsx,.xls" required>
                    </div>
                    <button type="submit" class="btn btn-enhanced">Upload</button>
                </form>
                <div id="uploadMessage" class="mt-2"></div>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-card mt-4">
    <div class="card-body">
        <h4>Uploaded Files</h4>
        <table class="table-enhanced" id="uploadsTable">
            <thead>
                <tr>
                    <th>File Name</th>
                    <th>Uploaded Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($uploads)): ?>
                    <tr><td colspan="3">No files uploaded</td></tr>
                <?php else: ?>
                    <?php foreach ($uploads as $upload): ?>
                        <tr data-upload-id="<?php echo $upload['id']; ?>">
                            <td><?php echo htmlspecialchars($upload['filename']); ?></td>
                            <td><?php echo htmlspecialchars($upload['uploaded_at']); ?></td>
                            <td>
                                <a href="schools/download_file.php?id=<?php echo $upload['id']; ?>" class="btn btn-enhanced btn-sm me-1" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <button class="btn btn-enhanced btn-sm delete-btn" 
                                        data-upload-id="<?php echo $upload['id']; ?>" 
                                        data-csrf="<?php echo htmlspecialchars($csrf_token); ?>" 
                                        title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery.noConflict();
jQuery(document).ready(function($) {
    console.log('jQuery loaded, version:', $.fn.jquery);

    // Fallback notification function
    window.showNotification = window.showNotification || function(message, type) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-triangle"></i>';
        $('#uploadMessage').html('<div class="alert alert-enhanced ' + alertClass + '">' + icon + ' ' + message + '</div>');
    };

    // Delete button handler
    $(document).on('click', '.delete-btn', function() {
        var $button = $(this);
        var uploadId = $button.data('upload-id');
        var csrfToken = $button.data('csrf');
        if (confirm('Are you sure you want to delete this file?')) {
            $.ajax({
                url: 'schools/delete_file.php',
                type: 'POST',
                data: { id: uploadId, csrf_token: csrfToken },
                dataType: 'json',
                success: function(response) {
                    console.log('Delete Response:', response);
                    if (response.success) {
                        window.showNotification('File deleted successfully.', 'success');
                        $button.closest('tr').remove();
                        if ($('#uploadsTable tbody tr').length === 0) {
                            $('#uploadsTable tbody').html('<tr><td colspan="3">No files uploaded</td></tr>');
                        }
                    } else {
                        window.showNotification(response.message || 'Failed to delete file.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Delete AJAX Error:', xhr.responseText);
                    window.showNotification('Failed to delete file: ' + error, 'error');
                }
            });
        }
    });
});
</script>
<?php
$content = ob_get_clean();

// Include layout
require_once '../layout.php';
?>