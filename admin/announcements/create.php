<?php
ob_start();
require_once '../db_connect.php';

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Restrict to authorized users
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['System Admin', 'Examination Administrator'])) {
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

$page_title = 'Create Announcement';

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content']) && isset($_POST['category']) && isset($_POST['priority'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        $uploader_id = $_SESSION['user_id'];
        $content = trim($_POST['content']);
        $category = trim($_POST['category']);
        $priority = trim($_POST['priority']);

        // Validate inputs
        if (empty($content)) {
            $message = 'Announcement content is required.';
            $message_type = 'error';
        } elseif (!in_array($category, ['system', 'maintenance', 'policy', 'event', 'general'])) {
            $message = 'Invalid category selected.';
            $message_type = 'error';
        } elseif (!in_array($priority, ['high', 'medium', 'low'])) {
            $message = 'Invalid priority selected.';
            $message_type = 'error';
        } else {
            $sql = "INSERT INTO announcements (content, uploader_id, category, priority) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siss", $content, $uploader_id, $category, $priority);

            if ($stmt->execute()) {
                $message = 'Announcement created successfully.';
                $message_type = 'success';

                // Log the action
                $action_desc = "Created announcement: " . substr($content, 0, 50) . "... (Category: $category, Priority: $priority)";
                $conn->query("CALL log_action('Announcement Created', $uploader_id, '$action_desc')");
            } else {
                $message = 'Failed to create announcement. Database error.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-bullhorn"></i> Create Announcement</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="manage.php">Announcements</a></li>
            <li class="breadcrumb-item active">Create Announcement</li>
        </ol>
    </nav>
</div>

<?php if (!empty($message)): ?>
<div class="alert-enhanced alert-<?php echo $message_type === 'success' ? 'success' : 'warning'; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8 col-md-10 mx-auto">
        <div class="dashboard-card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-bullhorn"></i>
                    Create New Announcement
                </h5>
                <p class="text-muted mb-4">Share important updates with users. Announcements will be visible immediately.</p>

                <form method="POST" action="" id="announcementForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="mb-3">
                        <label for="content" class="form-label">
                            <i class="fas fa-comment"></i>
                            Announcement Content <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="content" name="content" required rows="5" maxlength="1000"
                                  placeholder="Enter the announcement content"></textarea>
                        <div class="form-text">Provide a clear and concise message (max 1000 characters).</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">
                                    <i class="fas fa-tags"></i>
                                    Category <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">-- Select Category --</option>
                                    <option value="system">System Updates</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="policy">Policy Changes</option>
                                    <option value="event">Events</option>
                                    <option value="general">General</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="priority" class="form-label">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Priority <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="priority" name="priority" required>
                                    <option value="">-- Select Priority --</option>
                                    <option value="high">High Priority</option>
                                    <option value="medium">Medium Priority</option>
                                    <option value="low">Low Priority</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="manage.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Announcements
                        </a>
                        <button type="submit" class="btn-enhanced" id="submitBtn">
                            <i class="fas fa-bullhorn"></i>
                            <span class="btn-text">Create Announcement</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Form submission with loading state
document.addEventListener('DOMContentLoaded', function() {
    const announcementForm = document.getElementById('announcementForm');
    if (announcementForm) {
        announcementForm.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            
            if (submitBtn && btnText) {
                submitBtn.disabled = true;
                btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
                
                setTimeout(function() {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        btnText.innerHTML = '<i class="fas fa-bullhorn"></i> Create Announcement';
                    }
                }, 10000);
            }
        });
    }

    // Content validation
    const contentInput = document.getElementById('content');
    if (contentInput) {
        contentInput.addEventListener('input', function() {
            const value = this.value.trim();
            if (value.length < 10) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    }
});
</script>

<style>
.form-control.is-valid {
    border-color: var(--success-color);
}
.form-control.is-invalid {
    border-color: var(--danger-color);
}
.btn-enhanced:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<?php
$content = ob_get_clean();
include '../layout.php';
?>