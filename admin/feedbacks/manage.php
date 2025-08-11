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

$page_title = 'Manage Feedbacks';

// Handle filters
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$priority = isset($_GET['priority']) ? $_GET['priority'] : '';

// Handle delete action
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_feedback'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        $feedback_id = (int)$_POST['feedback_id'];
        $sql = "DELETE FROM feedbacks WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $feedback_id);
        
        if ($stmt->execute()) {
            $message = 'Feedback deleted successfully.';
            $message_type = 'success';
            $action_desc = "Deleted feedback ID: $feedback_id";
            $conn->query("CALL log_action('Feedback Deleted', {$_SESSION['user_id']}, '$action_desc')");
        } else {
            $message = 'Failed to delete feedback. Database error.';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Build the feedback query with filters
$sql = "SELECT f.id, f.ticket_number, f.feedback_text, f.response_text, f.priority, f.status, f.submitted_at, f.responded_at, s.school_name 
        FROM feedbacks f 
        JOIN schools s ON f.school_id = s.id 
        WHERE YEAR(f.submitted_at) = ?";
$params = [$year];
$types = "i";

if ($school_id) {
    $sql .= " AND f.school_id = ?";
    $params[] = $school_id;
    $types .= "i";
}
if ($status && in_array($status, ['open', 'closed'])) {
    $sql .= " AND f.status = ?";
    $params[] = $status;
    $types .= "s";
}
if ($priority && in_array($priority, ['High', 'Medium', 'Low'])) {
    $sql .= " AND f.priority = ?";
    $params[] = $priority;
    $types .= "s";
}

$sql .= " ORDER BY f.submitted_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $feedbacks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $message = 'Database error: Unable to fetch feedbacks.';
    $message_type = 'error';
    $feedbacks = [];
}

// Fetch schools for filter
$schools = $conn->query("SELECT id, school_name AS name FROM schools ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-comments"></i> Manage Feedbacks</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Manage Feedbacks</li>
        </ol>
    </nav>
</div>

<?php if (!empty($message)): ?>
<div class="alert-enhanced alert-<?php echo $message_type === 'success' ? 'success' : 'warning'; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="filter-container">
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label for="year" class="form-label">Year</label>
            <select class="form-control" id="year" name="year">
                <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                    <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="school_id" class="form-label">School</label>
            <select class="form-control" id="school_id" name="school_id">
                <option value="">All Schools</option>
                <?php foreach ($schools as $school): ?>
                    <option value="<?php echo $school['id']; ?>" <?php echo $school_id == $school['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($school['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-control" id="status" name="status">
                <option value="">All Statuses</option>
                <option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>Open</option>
                <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Closed</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="priority" class="form-label">Priority</label>
            <select class="form-control" id="priority" name="priority">
                <option value="">All Priorities</option>
                <option value="High" <?php echo $priority === 'High' ? 'selected' : ''; ?>>High</option>
                <option value="Medium" <?php echo $priority === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="Low" <?php echo $priority === 'Low' ? 'selected' : ''; ?>>Low</option>
            </select>
        </div>
        <div class="col-md-12 mt-3">
            <button type="submit" class="btn-enhanced">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
        </div>
    </form>
</div>

<div class="dashboard-card">
    <div class="card-body">
        <h5 class="card-title">
            <i class="fas fa-list"></i>
            Feedback List
        </h5>
        <?php if (empty($feedbacks)): ?>
        <div class="text-center py-4">
            <i class="fas fa-comment-slash text-muted fa-2x mb-2"></i>
            <p class="text-muted">No feedbacks found for the selected filters.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table-enhanced">
                <thead>
                    <tr>
                        <th>Ticket Number</th>
                        <th>School</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                        <th>Responded At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedbacks as $feedback): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($feedback['ticket_number']); ?></td>
                        <td><?php echo htmlspecialchars($feedback['school_name']); ?></td>
                        <td>
                            <span class="badge <?php echo $feedback['priority'] === 'High' ? 'bg-danger' : ($feedback['priority'] === 'Medium' ? 'bg-warning' : 'bg-success'); ?>">
                                <?php echo htmlspecialchars($feedback['priority']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $feedback['status'] === 'open' ? 'bg-primary' : 'bg-secondary'; ?>">
                                <?php echo htmlspecialchars(ucfirst($feedback['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y g:i A', strtotime($feedback['submitted_at'])); ?></td>
                        <td><?php echo $feedback['responded_at'] ? date('M d, Y g:i A', strtotime($feedback['responded_at'])) : '-'; ?></td>
                        <td>
                            <a href="feedbacks/view_feedbacks.php?id=<?php echo $feedback['id']; ?>" class="btn-enhanced btn-sm" title="View & Respond">
                                <i class="fas fa-eye"></i>
                            </a>
                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                <button type="submit" name="delete_feedback" class="btn btn-danger btn-sm" title="Delete Feedback">
                                    <i class="fas fa-trash"></i>
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit filter form on change
    const filterInputs = document.querySelectorAll('#year, #school_id, #status, #priority');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
});
</script>

<style>
.table-enhanced th, .table-enhanced td {
    vertical-align: middle;
}
.badge {
    padding: 0.5em 0.75em;
}
</style>

<?php
$content = ob_get_clean();
include '../layout.php';
?>