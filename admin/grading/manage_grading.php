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

$page_title = 'Manage Grading';
$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Log page access
$stmt = $conn->prepare("CALL log_action(?, ?, ?)");
$log_action = 'Manage Grading Page Access';
$log_description = 'Accessed manage grading page';
$stmt->bind_param("sis", $log_action, $user_id, $log_description);
$stmt->execute();
$stmt->close();

// Fetch subjects and exam years
$subjects = $conn->query("SELECT id, name, code FROM subjects ORDER BY name")->fetch_all(MYSQLI_ASSOC);
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
        $subject_id = $_POST['subject_id'] ? filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT) : null;
        $exam_year_id = $_POST['exam_year_id'] ? filter_input(INPUT_POST, 'exam_year_id', FILTER_VALIDATE_INT) : null;
        $ranges = $_POST['ranges'] ?? [];
        $grades = $_POST['grades'] ?? [];
        $scores = $_POST['scores'] ?? [];
        $edit_id = $action === 'edit' ? filter_input(INPUT_POST, 'edit_id', FILTER_VALIDATE_INT) : null;

        // Validation
        if (count($ranges) != count($grades) || count($grades) != count($scores)) {
            $errors[] = 'Invalid number of grading ranges, grades, or scores.';
        }

        foreach ($ranges as $index => $range) {
            $range_from = filter_input(INPUT_POST, "ranges[$index][from]", FILTER_VALIDATE_INT);
            $range_to = filter_input(INPUT_POST, "ranges[$index][to]", FILTER_VALIDATE_INT);
            $grade = trim($grades[$index] ?? '');
            $score = filter_input(INPUT_POST, "scores[$index]", FILTER_VALIDATE_INT);

            if ($range_from === false || $range_from < 0 || $range_from > 100) {
                $errors[] = "Range From in row " . ($index + 1) . " must be between 0 and 100.";
            }
            if ($range_to === false || $range_to < 0 || $range_to > 100) {
                $errors[] = "Range To in row " . ($index + 1) . " must be between 0 and 100.";
            }
            if ($range_from > $range_to) {
                $errors[] = "Range From must be less than or equal to Range To in row " . ($index + 1) . ".";
            }
            if (empty($grade) || strlen($grade) > 10) {
                $errors[] = "Grade in row " . ($index + 1) . " is required and must be 10 characters or less.";
            }
            if ($score === false || $score < 1 || $score > 9) {
                $errors[] = "Score in row " . ($index + 1) . " must be between 1 and 9.";
            }
        }
        if ($action === 'edit' && !$edit_id) {
            $errors[] = 'Invalid edit ID.';
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                foreach ($ranges as $index => $range) {
                    $range_from = $range['from'];
                    $range_to = $range['to'];
                    $grade = $grades[$index];
                    $score = $scores[$index];

                    // Check for overlapping ranges
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM grading WHERE (? <= range_to AND ? >= range_from) AND (? OR subject_id IS NULL OR subject_id != ?) AND (? OR exam_year_id IS NULL OR exam_year_id != ?) AND (? OR id != ?)");
                    $subject_condition = $subject_id ? 1 : 0;
                    $exam_year_condition = $exam_year_id ? 1 : 0;
                    $edit_condition = $action === 'edit' ? $edit_id : 0;
                    $stmt->bind_param("iiiiiiii", $range_from, $range_to, $subject_condition, $subject_id, $exam_year_condition, $exam_year_id, $edit_condition, $edit_id);
                    $stmt->execute();
                    $count = $stmt->get_result()->fetch_assoc()['count'];
                    if ($count > 0) {
                        throw new Exception("Mark range $range_from-$range_to overlaps with an existing rule in row " . ($index + 1) . ".");
                    }

                    if ($action === 'add') {
                        $stmt = $conn->prepare("INSERT INTO grading (range_from, range_to, grade, score, subject_id, exam_year_id) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iisiii", $range_from, $range_to, $grade, $score, $subject_id, $exam_year_id);
                        $stmt->execute();
                        $log_description = "Added grading rule: $range_from-$range_to, $grade, $score" . ($subject_id ? " for subject ID $subject_id" : "") . ($exam_year_id ? " for exam year ID $exam_year_id" : "");
                        $conn->query("CALL log_action('Add Grading Rule', $user_id, '$log_description')");
                    } elseif ($action === 'edit') {
                        $stmt = $conn->prepare("UPDATE grading SET range_from = ?, range_to = ?, grade = ?, score = ?, subject_id = ?, exam_year_id = ? WHERE id = ?");
                        $stmt->bind_param("iisiiii", $range_from, $range_to, $grade, $score, $subject_id, $exam_year_id, $edit_id);
                        $stmt->execute();
                        $log_description = "Edited grading rule ID $edit_id: $range_from-$range_to, $grade, $score" . ($subject_id ? " for subject ID $subject_id" : "") . ($exam_year_id ? " for exam year ID $exam_year_id" : "");
                        $conn->query("CALL log_action('Edit Grading Rule', $user_id, '$log_description')");
                    }
                }

                // Update results if exam_year_id is provided
                if ($exam_year_id) {
                    $stmt = $conn->prepare("CALL update_results_after_grading_change(?, ?)");
                    $stmt->bind_param("ii", $exam_year_id, $user_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update results: " . $conn->error);
                    }
                    $stmt->close();
                    $log_description = "Updated results for exam year ID $exam_year_id after grading change";
                    $conn->query("CALL log_action('Update Results After Grading Change', $user_id, '$log_description')");
                }

                $conn->commit();
                $success = $action === 'edit' ? 'Grading rule updated successfully.' : 'Grading rules added successfully.';
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = $e->getMessage();
                $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
                $log_action = $action === 'edit' ? 'Edit Grading Rule Error' : 'Add Grading Rule Error';
                $log_description = "Failed to " . ($action === 'edit' ? 'update' : 'add') . " grading rule: " . $e->getMessage();
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
                $stmt = $conn->prepare("SELECT exam_year_id FROM grading WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $exam_year_id = $result['exam_year_id'] ?? null;
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM grading WHERE id = ?");
                $stmt->bind_param("i", $id);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to delete grading rule: ' . $conn->error);
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

                $log_description = "Deleted grading rule ID $id" . ($exam_year_id ? " and updated results for exam year ID $exam_year_id" : "");
                $conn->query("CALL log_action('Delete Grading Rule', $user_id, '$log_description')");

                $conn->commit();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $conn->rollback();
                $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
                $log_action = 'Delete Grading Rule Error';
                $log_description = "Failed to delete grading rule ID $id: " . $e->getMessage();
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
        header("Location: manage_grading.php?$query");
        exit;
    }
}

// Fetch edit rule if edit_id is provided
$edit_id = isset($_GET['edit_id']) ? filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT) : null;
if ($edit_id) {
    $stmt = $conn->prepare("SELECT id, range_from, range_to, grade, score, subject_id, exam_year_id FROM grading WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_rule = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch grading rules with pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT g.id, g.range_from, g.range_to, g.grade, g.score, s.name AS subject_name, e.exam_year 
                        FROM grading g 
                        LEFT JOIN subjects s ON g.subject_id = s.id 
                        LEFT JOIN exam_years e ON g.exam_year_id = e.id 
                        ORDER BY g.range_from ASC 
                        LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$grading_rules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count total grading rules for pagination
$stmt = $conn->query("SELECT COUNT(*) as total FROM grading");
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
    return 'manage_grading.php?' . http_build_query($query);
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
        .grading-row input, .grading-row select {
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

        function addGradingRow() {
            const container = document.getElementById("grading-rows");
            const row = document.createElement("div");
            row.className = "grading-row";
            row.innerHTML = `
                <input type="number" name="ranges[][from]" class="form-control" placeholder="Range From" required min="0" max="100">
                <input type="number" name="ranges[][to]" class="form-control" placeholder="Range To" required min="0" max="100">
                <input type="text" name="grades[]" class="form-control" placeholder="Grade (e.g., D1)" required maxlength="10">
                <input type="number" name="scores[]" class="form-control" placeholder="Score (1-9)" required min="1" max="9">
                <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">Remove</button>
            `;
            container.appendChild(row);
        }

        $(document).ready(function() {
            $(".delete-btn").click(function() {
                if (confirm("Are you sure you want to delete this grading rule?")) {
                    const ruleId = $(this).data("id");
                    $.ajax({
                        url: "manage_grading.php",
                        type: "POST",
                        data: { action: "delete", id: ruleId, csrf_token: "<?php echo $csrf_token; ?>" },
                        success: function(response) {
                            try {
                                const result = JSON.parse(response);
                                if (result.success) {
                                    window.showNotification("Grading rule deleted successfully", "success");
                                    location.reload();
                                } else {
                                    window.showNotification(result.error, "error");
                                }
                            } catch (e) {
                                window.showNotification("Failed to delete grading rule: " + e.message, "error");
                            }
                        },
                        error: function(xhr, status, error) {
                            window.showNotification("Error deleting grading rule: " + error, "error");
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
        Manage Grading
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item">Grading</li>
            <li class="breadcrumb-item active" aria-current="page">Manage Grading</li>
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
    <h5 class="mb-3"><i class="fas fa-cog"></i> <?php echo $edit_rule ? 'Edit Grading Rule' : 'Add Grading Rule'; ?></h5>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="action" value="<?php echo $edit_rule ? 'edit' : 'add'; ?>">
        <?php if ($edit_rule): ?>
            <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($edit_rule['id']); ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label for="subject_id" class="form-label">Subject (Optional)</label>
            <select name="subject_id" id="subject_id" class="form-control">
                <option value="">Standard (All Subjects)</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?php echo $subject['id']; ?>" <?php echo ($edit_rule && $edit_rule['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($subject['name'] . ' (' . $subject['code'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
        <div id="grading-rows">
            <?php if ($edit_rule): ?>
                <div class="grading-row">
                    <input type="number" name="ranges[][from]" class="form-control" placeholder="Range From" value="<?php echo htmlspecialchars($edit_rule['range_from']); ?>" required min="0" max="100">
                    <input type="number" name="ranges[][to]" class="form-control" placeholder="Range To" value="<?php echo htmlspecialchars($edit_rule['range_to']); ?>" required min="0" max="100">
                    <input type="text" name="grades[]" class="form-control" placeholder="Grade (e.g., D1)" value="<?php echo htmlspecialchars($edit_rule['grade']); ?>" required maxlength="10">
                    <input type="number" name="scores[]" class="form-control" placeholder="Score (1-9)" value="<?php echo htmlspecialchars($edit_rule['score']); ?>" required min="1" max="9">
                </div>
            <?php else: ?>
                <div class="grading-row">
                    <input type="number" name="ranges[][from]" class="form-control" placeholder="Range From" required min="0" max="100">
                    <input type="number" name="ranges[][to]" class="form-control" placeholder="Range To" required min="0" max="100">
                    <input type="text" name="grades[]" class="form-control" placeholder="Grade (e.g., D1)" required maxlength="10">
                    <input type="number" name="scores[]" class="form-control" placeholder="Score (1-9)" required min="1" max="9">
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">Remove</button>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!$edit_rule): ?>
            <button type="button" class="btn-enhanced mb-3" onclick="addGradingRow()">
                <i class="fas fa-plus"></i> Add Grading Range
            </button>
        <?php endif; ?>
        <br>
        <button type="submit" class="btn-enhanced">
            <i class="fas fa-save"></i> <?php echo $edit_rule ? 'Update Rule' : 'Save Grading'; ?>
        </button>
        <?php if ($edit_rule): ?>
            <a href="manage_grading.php" class="btn-enhanced btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        <?php endif; ?>
    </form>
</div>

<div class="dashboard-card">
    <h5 class="card-title"><i class="fas fa-list"></i> Grading Rules</h5>
    <table class="table-enhanced">
        <thead>
            <tr>
                <th>Range From</th>
                <th>Range To</th>
                <th>Grade</th>
                <th>Score</th>
                <th>Subject</th>
                <th>Exam Year</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($grading_rules)): ?>
                <tr><td colspan="7">No grading rules found</td></tr>
            <?php else: ?>
                <?php foreach ($grading_rules as $rule): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($rule['range_from']); ?></td>
                        <td><?php echo htmlspecialchars($rule['range_to']); ?></td>
                        <td><?php echo htmlspecialchars($rule['grade']); ?></td>
                        <td><?php echo htmlspecialchars($rule['score']); ?></td>
                        <td><?php echo htmlspecialchars($rule['subject_name'] ?? 'All Subjects'); ?></td>
                        <td><?php echo htmlspecialchars($rule['exam_year'] ?? 'All Years'); ?></td>
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