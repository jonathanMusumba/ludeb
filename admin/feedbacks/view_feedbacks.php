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

$page_title = 'View Feedbacks';

// Handle form submission (respond or close without response)
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_id'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        $feedback_id = (int)$_POST['feedback_id'];
        $response_text = isset($_POST['response_text']) ? trim($_POST['response_text']) : '';
        $action = $_POST['action'] ?? 'respond';
        $responder_id = $_SESSION['user_id'];

        // Validate inputs
        if ($action === 'respond' && empty($response_text)) {
            $message = 'Response text is required when responding to feedback.';
            $message_type = 'error';
        } else {
            $sql = "UPDATE feedbacks SET response_text = ?, status = 'closed', responded_at = NOW() WHERE id = ?";
            $response_text = ($action === 'respond') ? $response_text : null;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $response_text, $feedback_id);

            if ($stmt->execute()) {
                $message = $action === 'respond' ? 'Feedback responded to and closed successfully.' : 'Feedback closed successfully.';
                $message_type = 'success';

                // Log the action
                $action_desc = $action === 'respond' ? "Responded to and closed feedback ID: $feedback_id" : "Closed feedback ID: $feedback_id without response";
                $conn->query("CALL log_action('Feedback Updated', $responder_id, '$action_desc')");
            } else {
                $message = 'Failed to update feedback. Database error.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Fetch feedback details
$feedback = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $feedback_id = (int)$_GET['id'];
    $sql = "SELECT f.id, f.ticket_number, f.feedback_text, f.response_text, f.priority, f.status, f.submitted_at, f.responded_at, s.school_name 
            FROM feedbacks f 
            JOIN schools s ON f.school_id = s.id 
            WHERE f.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $feedback_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $feedback = $result->fetch_assoc();
    $stmt->close();
    
    if (!$feedback) {
        $message = 'Feedback not found.';
        $message_type = 'error';
    }
}

?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-comments"></i> View Feedback</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="feedbacks/manage.php">Manage Feedbacks</a></li>
            <li class="breadcrumb-item active">View Feedback</li>
        </ol>
    </nav>
</div>

<?php if (!empty($message)): ?>
<div class="alert-enhanced alert-<?php echo $message_type === 'success' ? 'success' : 'warning'; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if (!$feedback): ?>
<div class="dashboard-card">
    <div class="card-body text-center">
        <i class="fas fa-exclamation-circle text-danger fa-2x mb-3"></i>
        <h5 class="card-title">Feedback Not Found</h5>
        <p class="text-muted">The requested feedback does not exist or you do not have permission to view it.</p>
        <a href="feedbacks/manage.php" class="btn-enhanced">
            <i class="fas fa-arrow-left"></i> Back to Feedbacks
        </a>
    </div>
</div>
<?php else: ?>
<div class="row">
    <div class="col-lg-8 col-md-10 mx-auto">
        <div class="dashboard-card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-ticket-alt"></i>
                    Feedback: <?php echo htmlspecialchars($feedback['ticket_number']); ?>
                </h5>
                <div class="mb-4">
                    <p><strong>School:</strong> <?php echo htmlspecialchars($feedback['school_name']); ?></p>
                    <p><strong>Priority:</strong> 
                        <span class="badge <?php echo $feedback['priority'] === 'High' ? 'bg-danger' : ($feedback['priority'] === 'Medium' ? 'bg-warning' : 'bg-success'); ?>">
                            <?php echo htmlspecialchars($feedback['priority']); ?>
                        </span>
                    </p>
                    <p><strong>Status:</strong> 
                        <span class="badge <?php echo $feedback['status'] === 'open' ? 'bg-primary' : 'bg-secondary'; ?>">
                            <?php echo htmlspecialchars(ucfirst($feedback['status'])); ?>
                        </span>
                    </p>
                    <p><strong>Submitted At:</strong> <?php echo date('M d, Y g:i A', strtotime($feedback['submitted_at'])); ?></p>
                    <?php if ($feedback['responded_at']): ?>
                    <p><strong>Responded At:</strong> <?php echo date('M d, Y g:i A', strtotime($feedback['responded_at'])); ?></p>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <h6 class="text-muted">Feedback Content</h6>
                    <p class="p-3 bg-light border rounded"><?php echo nl2br(htmlspecialchars($feedback['feedback_text'])); ?></p>
                </div>

                <?php if ($feedback['response_text']): ?>
                <div class="mb-4">
                    <h6 class="text-muted">Previous Response</h6>
                    <p class="p-3 bg-success-subtle border rounded"><?php echo nl2br(htmlspecialchars($feedback['response_text'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($feedback['status'] !== 'closed'): ?>
                <div class="row">
                    <div class="col-md-12">
                        <form method="POST" action="" id="responseForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                            <input type="hidden" name="action" value="respond">
                            
                            <div class="mb-3">
                                <label for="response_text" class="form-label">
                                    <i class="fas fa-reply"></i>
                                    Your Response <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" id="response_text" name="response_text" rows="5" maxlength="1000"
                                          placeholder="Enter your response to the feedback"></textarea>
                                <div class="form-text">Provide a clear and concise response (max 1000 characters).</div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <a href="feedbacks/manage.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i>
                                    Back to Feedbacks
                                </a>
                                <button type="submit" class="btn-enhanced" id="submitResponseBtn">
                                    <i class="fas fa-reply"></i>
                                    <span class="btn-text">Submit Response & Close</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-12 mt-3">
                        <form method="POST" action="" id="closeForm" onsubmit="return confirm('Are you sure you want to close this feedback without a response?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                            <input type="hidden" name="action" value="close">
                            <button type="submit" class="btn btn-warning w-100" id="closeWithoutResponseBtn">
                                <i class="fas fa-lock"></i>
                                <span class="btn-text">Close Without Response</span>
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert-enhanced alert-info">
                    <i class="fas fa-info-circle"></i>
                    This feedback is closed and cannot be responded to or modified.
                </div>
                <a href="feedbacks/manage.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Feedbacks
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const responseForm = document.getElementById('responseForm');
    const closeForm = document.getElementById('closeForm');

    if (responseForm) {
        responseForm.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitResponseBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const responseText = document.getElementById('response_text').value.trim();
            
            if (!responseText) {
                e.preventDefault();
                document.getElementById('response_text').classList.add('is-invalid');
                return;
            }

            if (submitBtn && btnText) {
                submitBtn.disabled = true;
                btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                
                setTimeout(function() {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        btnText.innerHTML = '<i class="fas fa-reply"></i> Submit Response & Close';
                    }
                }, 10000);
            }
        });
    }

    if (closeForm) {
        closeForm.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('closeWithoutResponseBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            
            if (submitBtn && btnText) {
                submitBtn.disabled = true;
                btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Closing...';
                
                setTimeout(function() {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        btnText.innerHTML = '<i class="fas fa-lock"></i> Close Without Response';
                    }
                }, 10000);
            }
        });
    }

    const responseInput = document.getElementById('response_text');
    if (responseInput) {
        responseInput.addEventListener('input', function() {
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
.btn-enhanced:disabled, .btn-warning:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.badge {
    padding: 0.5em 0.75em;
}
</style>

<?php
$content = ob_get_clean();
include '../layout.php';
?>