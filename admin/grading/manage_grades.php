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

$page_title = 'Manage Division Rules';
$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Log page access
$stmt = $conn->prepare("CALL log_action(?, ?, ?)");
$log_action = 'Manage Division Rules Page Access';
$log_description = 'Accessed manage division rules page';
$stmt->bind_param("sis", $log_action, $user_id, $log_description);
$stmt->execute();
$stmt->close();

// Fetch exam years
$exam_years = $conn->query("SELECT id, exam_year FROM exam_years ORDER BY exam_year DESC")->fetch_all(MYSQLI_ASSOC);

// Initialize variables
$errors = [];
$success = '';
$edit_rule = null;

// Handle form submissions (add/edit) and delete requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $action = $_POST['action'];
    
    if ($action === 'add' || $action === 'edit') {
        // Process add/edit
        $aggregate_from = filter_input(INPUT_POST, 'aggregate_from', FILTER_VALIDATE_INT);
        $aggregate_to = filter_input(INPUT_POST, 'aggregate_to', FILTER_VALIDATE_INT);
        $division = trim(filter_input(INPUT_POST, 'division', FILTER_SANITIZE_STRING));
        $conditions = trim($_POST['conditions'] ?? '');
        $exam_year_id = $_POST['exam_year_id'] ? filter_input(INPUT_POST, 'exam_year_id', FILTER_VALIDATE_INT) : null;
        $edit_id = $action === 'edit' ? filter_input(INPUT_POST, 'edit_id', FILTER_VALIDATE_INT) : null;

        // Validation
        if ($aggregate_from === false || $aggregate_from < 4 || $aggregate_from > 36) {
            $errors[] = 'Aggregate From must be between 4 and 36.';
        }
        if ($aggregate_to === false || $aggregate_to < 4 || $aggregate_to > 36) {
            $errors[] = 'Aggregate To must be between 4 and 36.';
        }
        if ($aggregate_from > $aggregate_to) {
            $errors[] = 'Aggregate From must be less than or equal to Aggregate To.';
        }
        if (empty($division) || strlen($division) > 50) {
            $errors[] = 'Division is required and must be 50 characters or less.';
        }
        // Validate JSON conditions
        if (!empty($conditions)) {
            $decoded = json_decode($conditions, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Invalid JSON in conditions: ' . json_last_error_msg();
            } else {
                // Basic validation of conditions structure
                if (!isset($decoded['subjects']) || !is_array($decoded['subjects'])) {
                    $errors[] = 'Conditions must include a "subjects" array.';
                }
            }
        }
        if ($action === 'edit' && !$edit_id) {
            $errors[] = 'Invalid edit ID.';
        }

        // Check for overlapping aggregate ranges
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM grades WHERE (? <= aggregate_range_to AND ? >= aggregate_range_from) AND (? OR exam_year_id IS NULL OR exam_year_id != ?) AND (? OR id != ?)");
            $exam_year_condition = $exam_year_id ? 1 : 0;
            $edit_condition = $action === 'edit' ? $edit_id : 0;
            $stmt->bind_param("iiiiii", $aggregate_from, $aggregate_to, $exam_year_condition, $exam_year_id, $edit_condition, $edit_id);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['count'];
            if ($count > 0) {
                $errors[] = "Aggregate range $aggregate_from-$aggregate_to overlaps with an existing rule.";
            }
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                if ($action === 'add') {
                    $stmt = $conn->prepare("INSERT INTO grades (aggregate_range_from, aggregate_range_to, division, conditions, exam_year_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissi", $aggregate_from, $aggregate_to, $division, $conditions, $exam_year_id);
                    $stmt->execute();
                    $log_description = "Added division rule: $aggregate_from-$aggregate_to, $division" . ($exam_year_id ? " for exam year ID $exam_year_id" : "");
                    $conn->query("CALL log_action('Add Division Rule', $user_id, '$log_description')");
                } elseif ($action === 'edit') {
                    $stmt = $conn->prepare("UPDATE grades SET aggregate_range_from = ?, aggregate_range_to = ?, division = ?, conditions = ?, exam_year_id = ? WHERE id = ?");
                    $stmt->bind_param("iissii", $aggregate_from, $aggregate_to, $division, $conditions, $exam_year_id, $edit_id);
                    $stmt->execute();
                    $log_description = "Edited division rule ID $edit_id: $aggregate_from-$aggregate_to, $division" . ($exam_year_id ? " for exam year ID $exam_year_id" : "");
                    $conn->query("CALL log_action('Edit Division Rule', $user_id, '$log_description')");
                }

                // Update results if exam_year_id is provided
                if ($exam_year_id) {
                    $stmt = $conn->prepare("CALL update_results_after_grading_change(?, ?)");
                    $stmt->bind_param("ii", $exam_year_id, $user_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update results: " . $conn->error);
                    }
                    $stmt->close();
                    $log_description = "Updated results for exam year ID $exam_year_id after division rule change";
                    $conn->query("CALL log_action('Update Results After Division Change', $user_id, '$log_description')");
                }

                $conn->commit();
                $success = $action === 'edit' ? 'Division rule updated successfully.' : 'Division rule added successfully.';
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = $e->getMessage();
                $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
                $log_action = $action === 'edit' ? 'Edit Division Rule Error' : 'Add Division Rule Error';
                $log_description = "Failed to " . ($action === 'edit' ? 'update' : 'add') . " division rule: " . $e->getMessage();
                $stmt->bind_param("sis", $log_action, $user_id, $log_description);
                $stmt->execute();
                $stmt->close();
            }
        }
    } elseif ($action === 'delete') {
        // Handle AJAX delete
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT exam_year_id FROM grades WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $exam_year_id = $result['exam_year_id'] ?? null;
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM grades WHERE id = ?");
                $stmt->bind_param("i", $id);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to delete division rule: ' . $conn->error);
                }
                $stmt->close();

                // Update results if exam_year_id exists
                if ($exam_year_id) {
                    $stmt = $conn->prepare("CALL update_results_after_grading_change(?, ?)");
                    $stmt->bind_param("ii", $exam_year_id, $user_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update results: " . $conn->error);
                    }
                    $stmt->close();
                }

                $log_description = "Deleted division rule ID $id" . ($exam_year_id ? " and updated results for exam year ID $exam_year_id" : "");
                $conn->query("CALL log_action('Delete Division Rule', $user_id, '$log_description')");

                $conn->commit();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $conn->rollback();
                $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
                $log_action = 'Delete Division Rule Error';
                $log_description = "Failed to delete division rule ID $id: " . $e->getMessage();
                $stmt->bind_param("sis", $log_action, $user_id, $log_description);
                $stmt->execute();
                $stmt->close();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Invalid rule ID']);
            exit;
        }
    }

    // Redirect to clear POST data and show messages
    if (!empty($errors) || !empty($success)) {
        $query = http_build_query(['error' => $errors ? implode('|', $errors) : null, 'success' => $success]);
        header("Location: manage_grades.php?$query");
        exit;
    }
}

// Fetch edit rule if edit_id is provided
$edit_id = isset($_GET['edit_id']) ? filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT) : null;
if ($edit_id) {
    $stmt = $conn->prepare("SELECT id, aggregate_range_from, aggregate_range_to, division, conditions, exam_year_id FROM grades WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_rule = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch division rules with pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT g.id, g.aggregate_range_from, g.aggregate_range_to, g.division, g.conditions, e.exam_year 
                        FROM grades g 
                        LEFT JOIN exam_years e ON g.exam_year_id = e.id 
                        ORDER BY g.aggregate_range_from ASC 
                        LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$division_rules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count total division rules for pagination
$stmt = $conn->query("SELECT COUNT(*) as total FROM grades");
$total_rules = $stmt->fetch_assoc()['total'];
$total_pages = ceil($total_rules / $limit);

// Generate new CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

// Function to build pagination URL with preserved GET parameters
function buildPaginationUrl($page, $params = []) {
    $query = array_merge($_GET, $params, ['page' => $page]);
    unset($query['error'], $query['success']); // Remove transient messages
    if (isset($query['edit_id']) && !isset($params['edit_id'])) {
        unset($query['edit_id']); // Remove edit_id unless explicitly set
    }
    return 'manage_grades.php?' . http_build_query($query);
}

// Extra head content
$extra_head = '
    <style>
        .grading-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(245, 245, 245, 0.85));
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .grading-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        .grading-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: center;
        }
        .grading-row input, .grading-row select, .grading-row textarea {
            flex: 1;
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
            background: linear-gradient(135deg, var(--success-color, #10b981), #059669);
            color: white;
        }
        .alert-danger {
            background: linear-gradient(135deg, var(--danger-color, #ef4444), #dc2626);
            color: white;
        }
    </style>
    <script>
        // Fallback showNotification function
        window.showNotification = window.showNotification || function(message, type) {
            alert(message);
        };

        $(document).ready(function() {
            $(".delete-btn").click(function() {
                if (confirm("Are you sure you want to delete this division rule?")) {
                    const ruleId = $(this).data("id");
                    $.ajax({
                        url: "manage_grades.php",
                        type: "POST",
                        data: { action: "delete", id: ruleId, csrf_token: "<?php echo $csrf_token; ?>" },
                        success: function(response) {
                            try {
                                const result = JSON.parse(response);
                                if (result.success) {
                                    window.showNotification("Division rule deleted successfully", "success");
                                    location.reload();
                                } else {
                                    window.showNotification(result.error, "error");
                                }
                            } catch (e) {
                                window.showNotification("Failed to delete division rule: " + e.message, "error");
                            }
                        },
                        error: function(xhr, status, error) {
                            window.showNotification("Error deleting division rule: " + error, "error");
                        }
                    });
                }
            });
        });
    </script>
';

// Page-specific content
?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-table"></i>
        Manage Division Rules
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item">Grading</li>
            <li class="breadcrumb-item active" aria-current="page">Manage Division Rules</li>
        </ol>
    </nav>
</div>

<?php if ($success): ?>
    <div class="alert-enhanced alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert-enhanced alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars(implode('<br>', $errors)); ?>
    </div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert-enhanced alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars(str_replace('|', '<br>', $_GET['error'])); ?>
    </div>
<?php endif; ?>
<?php if (isset($_GET['success'])): ?>
    <div class="alert-enhanced alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_GET['success']); ?>
    </div>
<?php endif; ?>

<div class="grading-container">
    <h5 class="mb-3"><i class="fas fa-cog"></i> <?php echo $edit_rule ? 'Edit Division Rule' : 'Add Division Rule'; ?></h5>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="action" value="<?php echo $edit_rule ? 'edit' : 'add'; ?>">
        <?php if ($edit_rule): ?>
            <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($edit_rule['id']); ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label for="aggregate_from" class="form-label">Aggregate From</label>
            <input type="number" name="aggregate_from" id="aggregate_from" class="form-control" placeholder="Aggregate From" value="<?php echo $edit_rule ? htmlspecialchars($edit_rule['aggregate_range_from']) : ''; ?>" required min="4" max="36">
        </div>
        <div class="mb-3">
            <label for="aggregate_to" class="form-label">Aggregate To</label>
            <input type="number" name="aggregate_to" id="aggregate_to" class="form-control" placeholder="Aggregate To" value="<?php echo $edit_rule ? htmlspecialchars($edit_rule['aggregate_range_to']) : ''; ?>" required min="4" max="36">
        </div>
        <div class="mb-3">
            <label for="division" class="form-label">Division</label>
            <input type="text" name="division" id="division" class="form-control" placeholder="Division (e.g., Division 1)" value="<?php echo $edit_rule ? htmlspecialchars($edit_rule['division']) : ''; ?>" required maxlength="50">
        </div>
        <div class="mb-3">
            <label for="exam_year_id" class="form-label">Exam Year (Optional)</label>
            <select name="exam_year_id" id="exam_year_id" class="form-control">
                <option value="">All Years</option>
                <?php foreach ($exam_years as $year): ?>
                    <option value="<?php echo $year['id']; ?>" <?php echo ($edit_rule && $edit_rule['exam_year_id'] == $year['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($year['exam_year']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="conditions" class="form-label">Conditions (JSON, Optional)</label>
            <textarea name="conditions" id="conditions" class="form-control" placeholder='{"subjects": ["ENG", "MTC"], "must_pass_all": true}' rows="5"><?php echo $edit_rule ? htmlspecialchars($edit_rule['conditions']) : ''; ?></textarea>
        </div>
        <button type="submit" class="btn-enhanced">
            <i class="fas fa-save"></i> <?php echo $edit_rule ? 'Update Rule' : 'Save Rule'; ?>
        </button>
        <?php if ($edit_rule): ?>
            <a href="manage_grades.php" class="btn-enhanced btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        <?php endif; ?>
    </form>
</div>

<div class="dashboard-card">
    <h5 class="card-title"><i class="fas fa-list"></i> Division Rules</h5>
    <table class="table-enhanced">
        <thead>
            <tr>
                <th>Aggregate From</th>
                <th>Aggregate To</th>
                <th>Division</th>
                <th>Exam Year</th>
                <th>Conditions</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($division_rules)): ?>
                <tr><td colspan="6">No division rules found</td></tr>
            <?php else: ?>
                <?php foreach ($division_rules as $rule): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($rule['aggregate_range_from']); ?></td>
                        <td><?php echo htmlspecialchars($rule['aggregate_range_to']); ?></td>
                        <td><?php echo htmlspecialchars($rule['division']); ?></td>
                        <td><?php echo htmlspecialchars($rule['exam_year'] ?? 'All Years'); ?></td>
                        <td><pre><?php echo htmlspecialchars($rule['conditions'] ?? '{}'); ?></pre></td>
                        <td>
                            <a href="<?php echo buildPaginationUrl($page, ['edit_id' => $rule['id']]); ?>" class="btn-enhanced btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button class="btn-enhanced btn-sm btn-danger delete-btn" data-id="<?php echo $rule['id']; ?>">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center mt-3">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo buildPaginationUrl($page - 1); ?>" aria-label="Previous">
                    <span aria-hidden="true">«</span>
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo buildPaginationUrl($i); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo buildPaginationUrl($page + 1); ?>" aria-label="Next">
                    <span aria-hidden="true">»</span>
                </a>
            </li>
        </ul>
    </nav>
</div>

<?php
$content = ob_get_clean();
require '../layout.php';
?>