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

$page_title = 'Manage Backups';
$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Log page access
$stmt = $conn->prepare("CALL log_action(?, ?, ?)");
$log_action = 'Manage Backups Page Access';
$log_description = 'Accessed manage backups page';
$stmt->bind_param("sis", $log_action, $user_id, $log_description);
$stmt->execute();
$stmt->close();

// Backup directory
$backup_dir = __DIR__ . '/../../backups/';
$backups = [];

// Get list of backup files
if (is_dir($backup_dir)) {
    $files = glob($backup_dir . '*.sql');
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'path' => $file,
            'size' => filesize($file),
            'date' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }
    // Sort by date descending
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

// Handle delete request
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $backup_file = $_POST['backup_file'] ?? '';
    $file_path = $backup_dir . basename($backup_file);
    if (file_exists($file_path)) {
        try {
            if (unlink($file_path)) {
                $success_message = "Backup file $backup_file deleted successfully.";
                // Log delete action
                $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
                $log_action = 'Delete Backup';
                $log_description = "Deleted backup: $backup_file";
                $stmt->bind_param("sis", $log_action, $user_id, $log_description);
                $stmt->execute();
                $stmt->close();
                // Refresh backups list
                $backups = [];
                $files = glob($backup_dir . '*.sql');
                foreach ($files as $file) {
                    $backups[] = [
                        'name' => basename($file),
                        'path' => $file,
                        'size' => filesize($file),
                        'date' => date('Y-m-d H:i:s', filemtime($file))
                    ];
                }
                usort($backups, function($a, $b) {
                    return strtotime($b['date']) - strtotime($a['date']);
                });
            } else {
                throw new Exception("Failed to delete backup file: $backup_file");
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            // Log error
            $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
            $log_action = 'Delete Backup Error';
            $log_description = "Failed to delete backup: " . $e->getMessage();
            $stmt->bind_param("sis", $log_action, $user_id, $log_description);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $error_message = "Backup file not found.";
    }
}

// Generate new CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

// Extra head content
$extra_head = '
    <style>
        .table-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(245, 245, 245, 0.85));
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .table-container:hover {
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
        .table-enhanced {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        .table-enhanced th {
            background: linear-gradient(135deg, #f8fafc, #e5e7eb);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 2px solid #e5e7eb;
        }
        .table-enhanced td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .table-enhanced tr:hover td {
            background: rgba(79, 70, 229, 0.05);
        }
        .btn-enhanced.btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
    </style>
';

// Page-specific content
?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-list"></i>
        Manage Backups
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Manage Backups</li>
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

<div class="table-container">
    <h5 class="mb-3"><i class="fas fa-database"></i> Backup Files</h5>
    <?php if (empty($backups)): ?>
        <p class="text-center text-muted">
            <i class="fas fa-inbox"></i>
            No backup files found.
        </p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table-enhanced">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Size</th>
                        <th>Date Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($backup['name']); ?></td>
                            <td><?php echo round($backup['size'] / 1024, 2); ?> KB</td>
                            <td><?php echo htmlspecialchars($backup['date']); ?></td>
                            <td>
                                <a href="backups/download_backup.php?file=<?php echo urlencode($backup['name']); ?>" class="btn-enhanced btn-sm">
                                    <i class="fas fa-download"></i>
                                    Download
                                </a>
                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this backup?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                    <button type="submit" name="delete_backup" class="btn-enhanced btn-sm" style="background: linear-gradient(135deg, var(--danger-color), #dc2626);">
                                        <i class="fas fa-trash"></i>
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require '../layout.php';
?>