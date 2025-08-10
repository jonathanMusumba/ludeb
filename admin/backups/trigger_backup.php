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
$allowed_roles = ['System Admin', 'Examination Administrator'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: " . $root_url . "login.php");
    exit();
}

$page_title = 'Trigger Backup';
$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Log page access
$stmt = $conn->prepare("CALL log_action(?, ?, ?)");
$log_action = 'Trigger Backup Page Access';
$log_description = 'Accessed trigger backup page';
$stmt->bind_param("sis", $log_action, $user_id, $log_description);
$stmt->execute();
$stmt->close();

// Backup directory
$backup_dir = __DIR__ . '/../../backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Handle backup request
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_backup']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        // Use database variables from db_connect.php
        $backup_file = $backup_dir . 'backup_' . date('Ymd_His') . '.sql';
        
        // Use mysqldump to create backup
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($servername),
            escapeshellarg($dbname),
            escapeshellarg($backup_file)
        );

        exec($command . ' 2>&1', $output, $return_var);
        if ($return_var !== 0) {
            throw new Exception('Backup failed: ' . implode(', ', $output));
        }

        // Log backup action
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        $log_action = 'Database Backup';
        $log_description = "Created backup: " . basename($backup_file);
        $stmt->bind_param("sis", $log_action, $user_id, $log_description);
        $stmt->execute();
        $stmt->close();

        $success_message = "Backup created successfully: " . basename($backup_file);
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        // Log error
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        $log_action = 'Backup Error';
        $log_description = "Backup failed: " . $e->getMessage();
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
        .backup-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(245, 245, 245, 0.85));
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .backup-container:hover {
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
    </style>
';

// Page-specific content
?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-cloud-upload-alt"></i>
        Trigger Backup
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Trigger Backup</li>
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

<div class="backup-container">
    <h5 class="mb-3"><i class="fas fa-database"></i> Create Database Backup</h5>
    <p class="text-muted mb-4">Click the button below to create a backup of the entire database. The backup will be saved as a .sql file in the backups directory.</p>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <button type="submit" name="trigger_backup" class="btn-enhanced">
            <i class="fas fa-cloud-upload-alt"></i>
            Trigger Backup
        </button>
    </form>
</div>

<?php
$content = ob_get_clean();
require '../layout.php';
?>