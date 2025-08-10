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

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['role'] === 'System Admin';
$username = htmlspecialchars($_SESSION['username']);
$targets = [];
$errors = [];
$success = '';

// Log page access
$conn->query("CALL log_action('Manage Targets Access', $user_id, 'Accessed manage targets page')");

// Fetch board name and current exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year, e.id AS exam_year_id 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');
$default_exam_year_id = $row['exam_year_id'] ?? 0;

// Fetch all active exam years
$exam_years = $conn->query("SELECT id, exam_year FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC");

// Get selected exam year from GET or default to current
$selected_exam_year_id = isset($_GET['exam_year_id']) ? intval($_GET['exam_year_id']) : $default_exam_year_id;

// Validate selected exam year
if ($selected_exam_year_id) {
    $stmt = $conn->prepare("SELECT id FROM exam_years WHERE id = ? AND status = 'Active'");
    $stmt->bind_param("i", $selected_exam_year_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $errors[] = 'Invalid or inactive exam year selected.';
        $selected_exam_year_id = $default_exam_year_id;
    }
    $stmt->close();
}

// Fetch targets for the selected exam year
$stmt = $conn->prepare("
    SELECT 
        dt.id,
        dt.target_date,
        dt.target_entries,
        dt.exam_year_id,
        e.exam_year,
        COUNT(m.id) AS actual_entries,
        LEAST(ROUND(COUNT(m.id) / dt.target_entries * 100), 100) AS percentage
    FROM daily_targets dt
    JOIN exam_years e ON dt.exam_year_id = e.id
    LEFT JOIN marks m ON DATE(m.submitted_at) = dt.target_date AND m.exam_year_id = dt.exam_year_id
    WHERE dt.exam_year_id = ?
    GROUP BY dt.id
    ORDER BY dt.target_date DESC
");
$stmt->bind_param("i", $selected_exam_year_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $targets[] = $row;
}
$stmt->close();

// Handle delete
if ($is_admin && isset($_POST['delete_id']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $csrf_token) {
    $delete_id = intval($_POST['delete_id']);
    try {
        $conn->begin_transaction();

        // Verify target exists and get details for logging
        $stmt = $conn->prepare("SELECT target_date, exam_year_id FROM daily_targets WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $target_date = $row['target_date'];
            $target_exam_year_id = $row['exam_year_id'];
        } else {
            throw new Exception('Target not found.');
        }
        $stmt->close();

        // Delete target
        $stmt = $conn->prepare("DELETE FROM daily_targets WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete target.');
        }
        $stmt->close();

        // Log deletion
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        $action = 'Delete Daily Target';
        $details = "Deleted target ID $delete_id for date: $target_date, exam year ID: $target_exam_year_id";
        $stmt->bind_param("sis", $action, $user_id, $details);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $success = 'Target deleted successfully.';
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();

        // Log error
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        $action = 'Delete Daily Target Error';
        $details = "Error deleting target ID $delete_id: " . $e->getMessage();
        $stmt->bind_param("sis", $action, $user_id, $details);
        $stmt->execute();
        $stmt->close();
    }
    // Redirect to avoid form resubmission
    ob_clean();
    header("Location: manage_targets.php?exam_year_id=$selected_exam_year_id");
    exit;
} elseif ($is_admin && isset($_POST['delete_id'])) {
    $errors[] = 'Invalid CSRF token.';
    error_log("CSRF token validation failed for delete: Received " . ($_POST['csrf_token'] ?? 'none') . ", Expected $csrf_token", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
}

// Set page title
$page_title = "Manage Daily Targets";

// Define content
ob_start();
?>
<div class="page-header">
    <h1 class="page-title">Manage Daily Targets</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="Dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Manage Targets</li>
        </ol>
    </nav>
</div>

<?php if ($success): ?>
    <div class="alert alert-enhanced alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-enhanced alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="dashboard-card">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4">
                <form method="GET" action="">
                    <div class="form-group">
                        <label for="exam_year_id">Exam Year</label>
                        <select id="exam_year_id" name="exam_year_id" class="form-control" onchange="this.form.submit()">
                            <option value="">Select an exam year</option>
                            <?php $exam_years->data_seek(0); while ($row = $exam_years->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>" <?php echo ($selected_exam_year_id == $row['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['exam_year']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>
        <a href="set_targets.php" class="btn btn-enhanced mb-3">Set New Target</a>
        <table class="table-enhanced">
            <thead>
                <tr>
                    <th>Exam Year</th>
                    <th>Date</th>
                    <th>Target Entries</th>
                    <th>Actual Entries</th>
                    <th>Progress (%)</th>
                    <?php if ($is_admin): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($targets)): ?>
                    <tr>
                        <td colspan="<?php echo $is_admin ? 6 : 5; ?>" class="text-center">No targets found for the selected exam year.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($targets as $target): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($target['exam_year']); ?></td>
                            <td><?php echo htmlspecialchars($target['target_date']); ?></td>
                            <td><?php echo htmlspecialchars($target['target_entries']); ?></td>
                            <td><?php echo htmlspecialchars($target['actual_entries']); ?></td>
                            <td><?php echo htmlspecialchars($target['percentage']); ?>%</td>
                            <?php if ($is_admin): ?>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this target?');">
                                        <input type="hidden" name="delete_id" value="<?php echo $target['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <button type="submit" class="btn btn-enhanced btn-sm btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();

// Include layout
require_once '../layout.php';
?>