<?php
ob_start();
require_once '../db_connect.php';

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    error_log("Unauthorized access attempt, User ID: " . ($_SESSION['user_id'] ?? 'Not set'), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header("Location: $root_url" . "login.php");
    exit;
}

// Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Log page access
$user_id = $_SESSION['user_id'];
$conn->query("CALL log_action('Edit School Access', $user_id, 'System Admin accessed edit school page')");

$center_no = isset($_GET['center_no']) ? trim($_GET['center_no']) : '';
if (empty($center_no) || !preg_match('/^\d{6}$/', $center_no)) {
    ob_clean();
    header("Location: $base_url" . "schools/manage_schools.php?error=" . urlencode("Invalid center number"));
    exit;
}

// Fetch school data
$stmt = $conn->prepare("SELECT id, center_no, school_name, subcounty_id, school_type_id 
                        FROM schools WHERE center_no = ?");
$stmt->bind_param("s", $center_no);
$stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
if (!$school) {
    ob_clean();
    header("Location: $base_url" . "schools/manage_schools.php?error=" . urlencode("School not found"));
    exit;
}
$school_id = $school['id'];

// Fetch subcounties
$stmt = $conn->query("SELECT id, subcounty FROM subcounties ORDER BY subcounty");
$subcounties = $stmt->fetch_all(MYSQLI_ASSOC);

// Fetch school types
$stmt = $conn->query("SELECT id, type FROM school_types ORDER BY type");
$school_types = $stmt->fetch_all(MYSQLI_ASSOC);

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');

$username = htmlspecialchars($_SESSION['username']);

// Set page title
$page_title = "Edit School";

// Define content
ob_start();
?>
<div class="page-header">
    <h1 class="page-title">Edit School</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>Dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>schools/manage_schools.php">Manage Schools</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit School</li>
        </ol>
    </nav>
</div>

<div class="dashboard-card">
    <div class="card-body">
        <h2>Edit School Details</h2>
        <form action="<?php echo $base_url; ?>schools/edit_school_process.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="center_no" value="<?php echo htmlspecialchars($center_no); ?>">
            <div class="form-group">
                <label for="center_no">Center Number</label>
                <input type="text" class="form-control" id="center_no" name="center_no" value="<?php echo htmlspecialchars($school['center_no']); ?>" required pattern="\d{6}" title="Center number must be exactly 6 digits">
            </div>
            <div class="form-group">
                <label for="school_name">School Name</label>
                <input type="text" class="form-control" id="school_name" name="school_name" value="<?php echo htmlspecialchars($school['school_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="subcounty_id">Subcounty</label>
                <select class="form-control" id="subcounty_id" name="subcounty_id" required>
                    <option value="">Select Subcounty</option>
                    <?php foreach ($subcounties as $subcounty): ?>
                        <option value="<?php echo $subcounty['id']; ?>" <?php echo $subcounty['id'] == $school['subcounty_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subcounty['subcounty']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="school_type_id">School Type</label>
                <select class="form-control" id="school_type_id" name="school_type_id" required>
                    <option value="">Select School Type</option>
                    <?php foreach ($school_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>" <?php echo $type['id'] == $school['school_type_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['type']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-enhanced">Update School</button>
            <a href="<?php echo $base_url; ?>schools/manage_schools.php" class="btn btn-enhanced btn-secondary">Cancel</a>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();

// Include layout
require_once '../layout.php';
?>