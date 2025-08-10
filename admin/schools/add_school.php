<?php
require_once '../db_connect.php';

// Define base and root URLs (consistent with layout.php)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Debug session
error_log("=== DEBUG SESSION INFO ===", 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("Session ID: " . session_id(), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("Session started: " . (session_status() === PHP_SESSION_ACTIVE ? 'YES' : 'NO'), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("User ID isset: " . (isset($_SESSION['user_id']) ? 'YES' : 'NO'), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("User ID value: " . ($_SESSION['user_id'] ?? 'NOT SET'), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("Role isset: " . (isset($_SESSION['role']) ? 'YES' : 'NO'), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("Role value: " . ($_SESSION['role'] ?? 'NOT SET'), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("All session data: " . print_r($_SESSION, true), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("=== END DEBUG ===", 3, 'C:\xampp\htdocs\ludeb\debug.log');

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header("Location: " . $root_url . "login.php");
    exit();
}

// Fetch subcounties
$stmt = $conn->query("SELECT id, subcounty FROM subcounties ORDER BY subcounty");
$subcounties = $stmt->fetch_all(MYSQLI_ASSOC);

// Fetch school types
$stmt = $conn->query("SELECT id, type FROM school_types ORDER BY type");
$school_types = $stmt->fetch_all(MYSQLI_ASSOC);

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Set page title
$page_title = "Add School";

// Define content
ob_start();
?>
<div class="page-header">
    <h1 class="page-title">Add School</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Add School</li>
        </ol>
    </nav>
</div>

<div class="dashboard-card form-section">
    <div class="card-body">
        <h2>Add New School</h2>
        <form id="addSchoolForm" action="<?php echo $base_url; ?>schools/add_school_process.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="mb-3">
                <label for="center_no" class="form-label">Center Number</label>
                <input type="text" class="form-control" id="center_no" name="center_no" required>
            </div>
            <div class="mb-3">
                <label for="school_name" class="form-label">School Name</label>
                <input type="text" class="form-control" id="school_name" name="school_name" required>
            </div>
            <div class="mb-3">
                <label for="subcounty_id" class="form-label">Subcounty</label>
                <select class="form-control" id="subcounty_id" name="subcounty_id" required>
                    <option value="">Select Subcounty</option>
                    <?php foreach ($subcounties as $subcounty): ?>
                        <option value="<?php echo $subcounty['id']; ?>"><?php echo htmlspecialchars($subcounty['subcounty']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="school_type_id" class="form-label">School Type</label>
                <select class="form-control" id="school_type_id" name="school_type_id" required>
                    <option value="">Select School Type</option>
                    <?php foreach ($school_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['type']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-enhanced">Add School</button>
        </form>
    </div>
</div>

<div class="dashboard-card form-section mt-4">
    <div class="card-body">
        <h2>Import Schools from Excel</h2>
        <p>Download the template to ensure your Excel file is formatted correctly.</p>
        <a href="<?php echo $base_url; ?>schools/download_template.php" class="btn btn-enhanced" style="background: linear-gradient(135deg, #28a745, #20c997);">
            Download Excel Template
        </a>
        <form method="post" action="<?php echo $base_url; ?>schools/import_schools.php" enctype="multipart/form-data" class="mt-3">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="mb-3">
                <label for="excel_file" class="form-label">Upload Excel File</label>
                <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx,.xls" required>
            </div>
            <button type="submit" class="btn btn-enhanced">Import Schools</button>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();

// Include layout
require_once '../layout.php';
?>