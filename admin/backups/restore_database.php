<?php
ob_start();
require_once '../db_connect.php';

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict to authorized roles
$allowed_roles = ['System Admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: " . $root_url . "login.php");
    exit();
}

$page_title = 'Restore Database';
$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Log page access
$stmt = $conn->prepare("CALL log_action(?, ?, ?)");
$log_action = 'Restore Database Page Access';
$log_description = 'Accessed restore database page';
$stmt->bind_param("sis", $log_action, $user_id, $log_description);
$stmt->execute();
$stmt->close();

// Handle restore request
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        $file = $_FILES['backup_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'sql') {
            throw new Exception('Invalid file type. Only .sql files are allowed.');
        }

        $temp_file = $file['tmp_name'];

        // Use mysql command to restore database
        $command = sprintf(
            'mysql --user=%s --password=%s --host=%s %s < %s',
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($servername),
            escapeshellarg($dbname),
            escapeshellarg($temp_file)
        );

        exec($command . ' 2>&1', $output, $return_var);
        if ($return_var !== 0) {
            throw new Exception('Restore failed: ' . implode(', ', $output));
        }

        // Log restore action
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        $log_action = 'Database Restore';
        $log_description = "Restored database from: " . $file['name'];
        $stmt->bind_param("sis", $log_action, $user_id, $log_description);
        $stmt->execute();
        $stmt->close();

        $success_message = "Database restored successfully from " . htmlspecialchars($file['name']);
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        // Log error
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        $log_action = 'Restore Error';
        $log_description = "Restore failed: " . $e->getMessage();
        $stmt->bind_param("sis", $log_action, $user_id, $log_description);
        $stmt->execute();
        $stmt->close();
    }
}

// Generate new CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

// Extra head content
$extra_head = '
    <style>
        .restore-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(245, 245, 245, 0.85));
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .restore-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        .alert-enhanced {
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        .alert-success {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
        }
        .alert-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
        }
        .alert-warning {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: white;
        }
    </style>
';

// Page-specific content
?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-undo"></i>
        Restore Database
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Restore Database</li>
        </ol>
    </nav>
</div>

<?php if ($success_message): ?>
    <div class="alert-enhanced alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="alert-enhanced alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>
<div class="alert-enhanced alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    Warning: Restoring a database will overwrite all current data. Ensure you have a recent backup before proceeding.
</div>

<div class="restore-container">
    <h5 class="mb-3"><i class="fas fa-database"></i> Restore Database</h5>
    <p class="text-muted mb-4">Upload a .sql backup file to restore the database.</p>
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="mb-3">
            <label for="backup_file" class="form-label">Select Backup File (.sql)</label>
            <input type="file" name="backup_file" id="backup_file" class="form-control" accept=".sql" required>
        </div>
        <button type="submit" class="btn-enhanced">
            <i class="fas fa-undo"></i>
            Restore Database
        </button>
    </form>
</div>

<?php
$content = ob_get_clean();
require '../layout.php';
?>