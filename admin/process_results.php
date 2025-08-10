<?php
session_start();
require_once 'db_connect.php';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['process_results'])) {
    $exam_year_id = isset($_POST['exam_year_id']) ? intval($_POST['exam_year_id']) : 0;

    // Validate exam_year_id
    $stmt = $conn->prepare("SELECT id FROM exam_years WHERE id = ? AND status = 'Active'");
    $stmt->bind_param("i", $exam_year_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        $error_message = "Invalid or inactive exam year selected.";
    } else {
        try {
            $conn->begin_transaction();

            // Call ProcessAllCandidates
            $stmt = $conn->prepare("CALL ProcessAllCandidates(?, ?)");
            $stmt->bind_param("ii", $exam_year_id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to process results for exam year ID $exam_year_id");
            }
            $stmt->close();

            $conn->commit();
            $success_message = "Results processed successfully for exam year ID $exam_year_id!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error processing results: " . $e->getMessage();
        }
    }
}

// Fetch exam years
$exam_years = $conn->query("SELECT id, exam_year FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC");

// Page-specific content
ob_start();
?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-cogs"></i>
        Process Results
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="home.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Process Results</li>
        </ol>
    </nav>
</div>

<!-- Alerts -->
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

<!-- Form -->
<div class="table-container">
    <div class="table-header">
        <h5 class="table-title">
            <i class="fas fa-cogs"></i>
            Process Results for All Candidates
        </h5>
    </div>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="table-filter">
                    <label for="exam_year_id">Exam Year:</label>
                    <select id="exam_year_id" name="exam_year_id" class="form-select" required>
                        <option value="">Select an exam year</option>
                        <?php while ($row = $exam_years->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>">
                                <?php echo htmlspecialchars($row['exam_year']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </div>
        <button type="submit" name="process_results" class="btn-enhanced">
            <i class="fas fa-cogs"></i>
            Process Results
        </button>
    </form>
</div>

<?php
$content = ob_get_clean();

// Extra head content
$extra_head = '
    <style>
        .table-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .table-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .table-filter {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .table-filter label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }

        .table-filter select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            background: white;
            min-width: 160px;
        }

        .table-filter select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 5px rgba(79, 70, 229, 0.3);
            outline: none;
        }

        .btn-enhanced {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            color: white;
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

// Page-specific scripts
$extra_scripts = '
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
if (typeof window.showNotification === "undefined") {
    window.showNotification = function(message, type) {
        alert(message);
        console.log("Notification:", type, message);
    };
}

$(document).ready(function() {
    // Show notification for initial messages
    ' . ($success_message ? "window.showNotification('$success_message', 'success');" : '') . '
    ' . ($error_message ? "window.showNotification('$error_message', 'error');" : '') . '
});
</script>
';

require 'layout.php';
?>