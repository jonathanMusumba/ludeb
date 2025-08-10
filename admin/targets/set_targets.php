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
$username = htmlspecialchars($_SESSION['username']);
$errors = [];
$success = '';

// Log page access
$conn->query("CALL log_action('Set Targets Access', $user_id, 'Accessed set targets page')");

// Fetch board name and current exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year, e.id AS exam_year_id 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');
$exam_year_id = $row['exam_year_id'] ?? 0;

// Fetch all active exam years
$exam_years = $conn->query("SELECT id, exam_year FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $csrf_token) {
    $target_date = trim($_POST['target_date'] ?? '');
    $target_entries = trim($_POST['target_entries'] ?? '');
    $selected_exam_year_id = intval($_POST['exam_year_id'] ?? 0);

    // Validation
    if (empty($target_date)) {
        $errors[] = 'Target date is required.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)) {
        $errors[] = 'Invalid date format.';
    }
    if (empty($target_entries)) {
        $errors[] = 'Target entries are required.';
    } elseif (!is_numeric($target_entries) || $target_entries <= 0) {
        $errors[] = 'Target entries must be a positive number.';
    }
    if (!$selected_exam_year_id) {
        $errors[] = 'Exam year is required.';
    } else {
        // Validate exam_year_id
        $stmt = $conn->prepare("SELECT id FROM exam_years WHERE id = ? AND status = 'Active'");
        $stmt->bind_param("i", $selected_exam_year_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $errors[] = 'Invalid or inactive exam year selected.';
        }
        $stmt->close();
    }

    // Check for existing target
    $stmt = $conn->prepare("SELECT id FROM daily_targets WHERE target_date = ? AND exam_year_id = ?");
    $stmt->bind_param("si", $target_date, $selected_exam_year_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = 'A target already exists for this date and exam year.';
    }
    $stmt->close();

    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare("INSERT INTO daily_targets (target_date, target_entries, exam_year_id) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $target_date, $target_entries, $selected_exam_year_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to add daily target.');
            }
            $stmt->close();

            // Log successful target creation
            $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
            $action = 'Add Daily Target';
            $details = "Added target for date: $target_date, exam year ID: $selected_exam_year_id, entries: $target_entries";
            $stmt->bind_param("sis", $action, $user_id, $details);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $success = 'Daily target added successfully.';
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();

            // Log error
            $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
            $action = 'Add Daily Target Error';
            $details = "Error adding target for date: $target_date, exam year ID: $selected_exam_year_id: " . $e->getMessage();
            $stmt->bind_param("sis", $action, $user_id, $details);
            $stmt->execute();
            $stmt->close();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors[] = 'Invalid CSRF token.';
    error_log("CSRF token validation failed: Received " . ($_POST['csrf_token'] ?? 'none') . ", Expected $csrf_token", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
}

// Set page title
$page_title = "Set Daily Target";

// Define content
ob_start();
?>
<div class="page-header">
    <h1 class="page-title">Set Daily Target</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="Dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Set Targets</li>
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
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="form-group">
                <label for="exam_year_id">Exam Year</label>
                <select id="exam_year_id" name="exam_year_id" class="form-control" required>
                    <option value="">Select an exam year</option>
                    <?php while ($row = $exam_years->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>" <?php echo ($row['id'] == $exam_year_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($row['exam_year']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="target_date">Target Date</label>
                <input type="date" class="form-control" id="target_date" name="target_date" required>
            </div>
            <div class="form-group">
                <label for="target_entries">Target Entries</label>
                <input type="number" class="form-control" id="target_entries" name="target_entries" min="1" required>
            </div>
            <button type="submit" class="btn btn-enhanced">Set Target</button>
            <a href="manage_targets.php" class="btn btn-enhanced btn-secondary">Back to Manage Targets</a>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();

// Include layout
require_once '../layout.php';
?>