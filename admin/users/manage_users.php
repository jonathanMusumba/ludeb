<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../db_connect.php';

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Debug session
error_log("=== DEBUG manage_users.php ===", 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("Session ID: " . session_id(), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("Session started: " . (session_status() === PHP_SESSION_ACTIVE ? 'YES' : 'NO'), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("Role: " . ($_SESSION['role'] ?? 'NOT SET'), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("=== END DEBUG ===", 3, 'C:\xampp\htdocs\ludeb\debug.log');

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header("Location: " . $root_url . "login.php");
    exit();
}

// Check connection
if (!$conn || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn ? $conn->connect_error : 'No connection object'), 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
    die("Database connection failed.");
}

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Log page access
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("CALL log_action(?, ?, ?)");
if ($stmt) {
    $action = 'Manage Users Access';
    $details = 'System Admin accessed manage users page';
    $stmt->bind_param("sis", $action, $user_id, $details);
    $stmt->execute();
    $stmt->close();
} else {
    error_log("Failed to prepare log_action query: " . $conn->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
}

// Initialize variables
$errors = [];
$success = '';
$edit_user = null;

// Fetch edit user if edit_id is provided
$edit_id = isset($_GET['edit_id']) ? filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT) : null;
if ($edit_id !== null && $edit_id > 0) {
    $stmt = $conn->prepare("SELECT id, username, email, role, school_id, status FROM system_users WHERE id = ?");
    if ($stmt === false) {
        error_log("Prepare failed for edit user query: " . $conn->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
        $edit_user = null; // Fallback to null if preparation fails
    } else {
        if (!$stmt->bind_param("i", $edit_id)) {
            error_log("Bind param failed for edit user query: " . $stmt->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
            $edit_user = null;
        } elseif (!$stmt->execute()) {
            error_log("Execute failed for edit user query: " . $stmt->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
            $edit_user = null;
        } else {
            $result = $stmt->get_result();
            if ($result === false) {
                error_log("Get result failed for edit user query: " . $stmt->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
                $edit_user = null;
            } else {
                $edit_user = $result->fetch_assoc();
                if ($edit_user === null) {
                    error_log("No user found for edit_id: $edit_id", 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
                }
            }
        }
        $stmt->close();
    }
} else {
    $edit_user = null; // Ensure $edit_user is null if edit_id is invalid
}

// Fetch schools for filter and edit form
$stmt = $conn->prepare("SELECT id, school_name AS name FROM schools ORDER BY name");
if ($stmt) {
    $stmt->execute();
    $schools = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    error_log("Failed to fetch schools: " . $conn->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
    $schools = [];
}

// Fetch users by role with pagination and filters
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$school_filter = isset($_GET['school']) ? (int)$_GET['school'] : null;
$exam_year_filter = isset($_GET['exam_year']) ? (int)$_GET['exam_year'] : null;

$roles = ['System Admin', 'Examination Administrator', 'Data Entrant', 'School'];
$users_by_role = [];
$total_users = 0;
$total_pages = 1;

foreach ($roles as $role) {
    $where = ["u.role = ?"];
    $params = [$role];
    $types = 's';

    if ($status_filter && in_array($status_filter, ['Active', 'Invalid'])) {
        $where[] = "u.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    if ($school_filter && $role === 'School') {
        $where[] = "u.school_id = ?";
        $params[] = $school_filter;
        $types .= 'i';
    }

    $sql = "SELECT u.id, u.username, u.email, u.role, u.school_id, s.school_name, u.status, u.last_login, 
            (SELECT COUNT(*) FROM marks m WHERE m.submitted_by = u.id";
    if ($exam_year_filter) {
        $sql .= " AND m.exam_year_id = ?";
        $params[] = $exam_year_filter;
        $types .= 'i';
    }
    $sql .= ") as entries_count,
            (SELECT COUNT(*) FROM results r WHERE r.processed_by = u.id";
    if ($exam_year_filter) {
        $sql .= " AND r.exam_year_id = ?";
        $params[] = $exam_year_filter;
        $types .= 'i';
    }
    $sql .= ") as processed_count,
            (SELECT COUNT(*) FROM audit_logs al WHERE al.user_id = u.id";
    if ($exam_year_filter) {
        $sql .= " AND YEAR(al.created_at) = ?";
        $params[] = $exam_year_filter;
        $types .= 'i';
    }
    $sql .= ") as actions_count
            FROM system_users u
            LEFT JOIN schools s ON u.school_id = s.id";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY u.id ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Only bind parameters if we have any
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                $users_by_role[$role] = $result->fetch_all(MYSQLI_ASSOC);
            } else {
                error_log("Failed to get result for role $role: " . $stmt->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
                $users_by_role[$role] = [];
            }
        } else {
            error_log("Failed to execute query for role $role: " . $stmt->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
            $users_by_role[$role] = [];
        }
        $stmt->close();

        // Count total users for this role
        $count_sql = "SELECT COUNT(*) as total FROM system_users u";
        if (!empty($where)) {
            $count_sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $count_stmt = $conn->prepare($count_sql);
        if ($count_stmt) {
            // Prepare count parameters (exclude limit and offset)
            $count_params = [];
            $count_types = '';
            
            // Add role parameter
            $count_params[] = $role;
            $count_types .= 's';
            
            // Add other parameters (excluding exam_year_filter which is only for subqueries and limit/offset)
            if ($status_filter && in_array($status_filter, ['Active', 'Invalid'])) {
                $count_params[] = $status_filter;
                $count_types .= 's';
            }
            if ($school_filter && $role === 'School') {
                $count_params[] = $school_filter;
                $count_types .= 'i';
            }
            
            // Only bind if we have parameters
            if (!empty($count_params)) {
                $count_stmt->bind_param($count_types, ...$count_params);
            }
            
            if ($count_stmt->execute()) {
                $count_result = $count_stmt->get_result();
                if ($count_result) {
                    $role_total = $count_result->fetch_assoc()['total'];
                    $total_users += $role_total;
                    $total_pages = max($total_pages, ceil($role_total / $limit));
                } else {
                    error_log("Failed to get count result for role $role: " . $count_stmt->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
                }
            } else {
                error_log("Failed to execute count query for role $role: " . $count_stmt->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
            }
            $count_stmt->close();
        } else {
            error_log("Failed to prepare count query for role $role: " . $conn->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
        }
    } else {
        error_log("Failed to prepare main query for role $role: " . $conn->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
        $users_by_role[$role] = [];
    }
}

// Set page title
$page_title = $edit_user !== null ? 'Edit User' : 'Manage Users';

// Define content
ob_start();
?>
<div class="page-header">
    <h1 class="page-title"><?php echo $edit_user !== null ? 'Edit User' : 'Manage Users'; ?></h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item">Users</li>
            <li class="breadcrumb-item active" aria-current="page">Manage Users</li>
        </ol>
    </nav>
</div>

<div class="dashboard-card">
    <div class="card-body">
        <h2 class="card-title"><?php echo $edit_user !== null ? 'Edit User' : 'Add User'; ?></h2>
        <form id="user-form" method="post" class="mb-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="<?php echo $edit_user !== null ? 'edit' : 'add'; ?>">
            <input type="hidden" name="ajax" value="1">
            <?php if ($edit_user !== null): ?>
                <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($edit_user['id']); ?>">
            <?php endif; ?>
            <div id="user-fields" class="user-fields <?php echo $edit_user !== null ? 'single-user' : ''; ?>">
                <div class="form-row">
                    <div class="form-group col-md-2">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" name="username[]" value="<?php echo htmlspecialchars($edit_user['username'] ?? ''); ?>" required onblur="validateField(this, 'username')">
                        <small class="validation-message"></small>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" name="email[]" value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>" required onblur="validateField(this, 'email')">
                        <small class="validation-message"></small>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="password">Password</label>
                        <input type="password" class="form-control" name="password[]" <?php echo $edit_user !== null ? '' : 'required'; ?> onkeyup="validatePassword(this)">
                        <small class="password-requirements">Must be 8+ chars, include a letter, number, and special char.</small>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="role">Role</label>
                        <select name="role[]" class="form-control" required onchange="toggleSchoolField(this)">
                            <option value="System Admin" <?php echo ($edit_user && $edit_user['role'] === 'System Admin') ? 'selected' : ''; ?>>System Admin</option>
                            <option value="Examination Administrator" <?php echo ($edit_user && $edit_user['role'] === 'Examination Administrator') ? 'selected' : ''; ?>>Examination Administrator</option>
                            <option value="Data Entrant" <?php echo ($edit_user && $edit_user['role'] === 'Data Entrant') ? 'selected' : ''; ?>>Data Entrant</option>
                            <option value="School" <?php echo ($edit_user && $edit_user['role'] === 'School') ? 'selected' : ''; ?>>School</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2 school-field" style="display: <?php echo ($edit_user && $edit_user['role'] === 'School') ? 'block' : 'none'; ?>;">
                        <label for="school_id">School</label>
                        <select name="school_id[]" class="form-control" onblur="validateSchoolField(this, <?php echo $edit_user ? $edit_user['id'] : 0; ?>)">
                            <option value="">Select School</option>
                            <?php
                            $stmt = $conn->prepare("SELECT s.id, s.school_name AS name 
                                                    FROM schools s 
                                                    WHERE NOT EXISTS (
                                                        SELECT 1 FROM system_users u 
                                                        WHERE u.school_id = s.id AND u.role = 'School' AND u.id != ?
                                                    ) 
                                                    ORDER BY s.name");
                            if ($stmt) {
                                $stmt->bind_param("i", $edit_id ?: 0);
                                $stmt->execute();
                                $available_schools = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                $stmt->close();
                                foreach ($available_schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>" <?php echo ($edit_user && $edit_user['school_id'] == $school['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($school['name']); ?></option>
                                <?php endforeach;
                            } ?>
                        </select>
                        <small class="validation-message"></small>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="status">Status</label>
                        <select name="status[]" class="form-control" required>
                            <option value="Active" <?php echo ($edit_user && $edit_user['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Invalid" <?php echo ($edit_user && $edit_user['status'] === 'Invalid') ? 'selected' : ''; ?>>Invalid</option>
                        </select>
                    </div>
                </div>
            </div>
            <?php if ($edit_user === null): ?>
                <button type="button" class="btn btn-enhanced btn-secondary mb-3" id="add-user-button">Add Another User</button>
            <?php endif; ?>
            <button type="submit" class="btn btn-enhanced btn-primary"><?php echo $edit_user !== null ? 'Update User' : 'Add User(s)'; ?></button>
            <?php if ($edit_user !== null): ?>
                <button type="button" class="btn btn-enhanced btn-secondary" onclick="window.location.href='manage_users.php'">Cancel</button>
            <?php endif; ?>
        </form>

        <?php if ($edit_user !== null): ?>
            <form id="reset-password-form" method="post" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="reset_id" value="<?php echo htmlspecialchars($edit_user['id']); ?>">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="new_password">New Password</label>
                        <input type="password" class="form-control" name="new_password" onkeyup="validatePassword(this)">
                        <small class="password-requirements">Must be 8+ chars, include a letter, number, and special char.</small>
                    </div>
                    <div class="form-group col-md-3 align-self-end">
                        <button type="submit" class="btn btn-enhanced btn-danger">Reset Password</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <!-- Filters -->
        <form method="get" class="mb-4 filter-container">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="role_filter">Filter by Role</label>
                    <select name="role" id="role_filter" class="form-control">
                        <option value="">All Roles</option>
                        <option value="System Admin" <?php echo $role_filter === 'System Admin' ? 'selected' : ''; ?>>System Admin</option>
                        <option value="Examination Administrator" <?php echo $role_filter === 'Examination Administrator' ? 'selected' : ''; ?>>Examination Administrator</option>
                        <option value="Data Entrant" <?php echo $role_filter === 'Data Entrant' ? 'selected' : ''; ?>>Data Entrant</option>
                        <option value="School" <?php echo $role_filter === 'School' ? 'selected' : ''; ?>>School</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="status_filter">Filter by Status</label>
                    <select name="status" id="status_filter" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Invalid" <?php echo $status_filter === 'Invalid' ? 'selected' : ''; ?>>Invalid</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="school_filter">Filter by School</label>
                    <select name="school" id="school_filter" class="form-control">
                        <option value="">All Schools</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?php echo $school['id']; ?>" <?php echo $school_filter == $school['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($school['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="exam_year_filter">Filter by Exam Year</label>
                    <input type="number" name="exam_year" id="exam_year_filter" class="form-control" value="<?php echo htmlspecialchars($exam_year_filter ?? ''); ?>" placeholder="e.g., 2025">
                </div>
                <div class="form-group col-md-3 align-self-end">
                    <button type="submit" class="btn btn-enhanced btn-primary">Apply Filters</button>
                    <a href="manage_users.php" class="btn btn-enhanced btn-secondary">Clear Filters</a>
                </div>
            </div>
        </form>

        <!-- Display Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-enhanced alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-enhanced alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Users by Role -->
        <?php foreach ($roles as $role): ?>
            <?php if (!empty($users_by_role[$role]) || $role_filter === $role || empty($role_filter)): ?>
                <h3 class="card-title mt-4"><?php echo $role; ?> Users</h3>
                <div class="table-responsive">
                    <table class="table-enhanced table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>School</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Entries</th>
                                <th>Processed</th>
                                <th>Actions</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_by_role[$role] as $user): ?>
                                <tr data-user-id="<?php echo $user['id']; ?>">
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td><?php echo htmlspecialchars($user['school_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['status']); ?></td>
                                    <td><?php echo htmlspecialchars($user['last_login'] ?? 'Never'); ?></td>
                                    <td><a href="#" class="view-entries" data-count="<?php echo $user['entries_count']; ?>">View (<?php echo $user['entries_count']; ?>)</a></td>
                                    <td><a href="#" class="view-processed" data-count="<?php echo $user['processed_count']; ?>">View (<?php echo $user['processed_count']; ?>)</a></td>
                                    <td><a href="#" class="view-actions" data-count="<?php echo $user['actions_count']; ?>">View (<?php echo $user['actions_count']; ?>)</a></td>
                                    <td>
                                        <a href="manage_users.php?edit_id=<?php echo $user['id']; ?>" class="btn btn-enhanced btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                        <button class="btn btn-enhanced btn-sm btn-danger delete-user" data-id="<?php echo $user['id']; ?>"><i class="fas fa-trash"></i> Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users_by_role[$role])): ?>
                                <tr><td colspan="11">No users found for <?php echo $role; ?>.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter(['role' => $role_filter, 'status' => $status_filter, 'school' => $school_filter, 'exam_year' => $exam_year_filter])); ?>">Previous</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter(['role' => $role_filter, 'status' => $status_filter, 'school' => $school_filter, 'exam_year' => $exam_year_filter])); ?>"><?php echo $i; ?></a></li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter(['role' => $role_filter, 'status' => $status_filter, 'school' => $school_filter, 'exam_year' => $exam_year_filter])); ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>

<!-- Modal for Viewing Details -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="modal-filter-form" class="mb-3">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="modal_exam_year">Exam Year</label>
                            <input type="number" class="form-control" id="modal_exam_year" placeholder="e.g., 2025">
                        </div>
                        <div class="form-group col-md-4 align-self-end">
                            <button type="submit" class="btn btn-enhanced btn-primary">Apply Year Filter</button>
                        </div>
                    </div>
                </form>
                <div id="details-content"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-enhanced btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSchoolField(select) {
    const schoolField = $(select).closest('.form-row').find('.school-field');
    schoolField.toggle(select.value === 'School');
    if (select.value !== 'School') {
        schoolField.find('select').val('');
        schoolField.find('.validation-message').hide();
    }
}

function validatePassword(input) {
    const requirements = input.nextElementSibling;
    const regex = /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
    if (regex.test(input.value)) {
        requirements.classList.remove('invalid');
        requirements.classList.add('valid');
        requirements.textContent = 'Password meets requirements.';
    } else {
        requirements.classList.remove('valid');
        requirements.classList.add('invalid');
        requirements.textContent = 'Must be 8+ chars, include a letter, number, and special char.';
    }
    requirements.style.display = 'block';
}

function validateField(input, type) {
    const message = input.nextElementSibling;
    if (!input.value) return;

    $.ajax({
        url: 'add_user.php',
        type: 'POST',
        data: { validate: 'username_email', [type]: input.value },
        success: function(response) {
            const result = JSON.parse(response);
            if (result[type]) {
                message.classList.remove('valid');
                message.classList.add('invalid');
                message.textContent = result[type];
            } else {
                message.classList.remove('invalid');
                message.classList.add('valid');
                message.textContent = type.charAt(0).toUpperCase() + type.slice(1) + ' is available.';
            }
            message.style.display = 'block';
        },
        error: function() {
            message.classList.add('invalid');
            message.textContent = 'Error validating ' + type;
            message.style.display = 'block';
        }
    });
}

function validateSchoolField(select, editId) {
    const message = select.nextElementSibling;
    if (!select.value) return;

    $.ajax({
        url: 'manage_users.php',
        type: 'POST',
        data: { validate: 'school_id', school_id: select.value, edit_id: editId },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.school_id) {
                message.classList.remove('valid');
                message.classList.add('invalid');
                message.textContent = result.school_id;
            } else {
                message.classList.remove('invalid');
                message.classList.add('valid');
                message.textContent = 'School is available.';
            }
            message.style.display = 'block';
        },
        error: function() {
            message.classList.add('invalid');
            message.textContent = 'Error validating school';
            message.style.display = 'block';
        }
    });
}

document.getElementById('add-user-button')?.addEventListener('click', function() {
    const userFields = document.getElementById('user-fields');
    const newFields = userFields.firstElementChild.cloneNode(true);
    newFields.querySelectorAll('input').forEach(input => input.value = '');
    newFields.querySelector('select[name="role[]"]').value = 'Data Entrant';
    newFields.querySelector('select[name="status[]"]').value = 'Active';
    newFields.querySelector('.school-field').style.display = 'none';
    newFields.querySelector('select[name="school_id[]"]').value = '';
    newFields.querySelectorAll('.password-requirements, .validation-message').forEach(el => {
        el.style.display = 'none';
        el.textContent = '';
    });
    userFields.appendChild(newFields);
});

$('#user-form').submit(function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    $.ajax({
        url: 'manage_users.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    const alert = $('<div class="alert alert-enhanced alert-success"><i class="fas fa-check-circle"></i> ' + result.message + '</div>');
                    $('.dashboard-card .card-body').prepend(alert);
                    $('#user-form')[0].reset();
                    if (!$('#user-form input[name="edit_id"]').length) {
                        $('#user-fields').html($('#user-fields .form-row').first().clone());
                    }
                    $('.password-requirements, .validation-message').hide();
                    $('.school-field').hide();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    const alert = $('<div class="alert alert-enhanced alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + result.error.replace('|', '<br>') + '</div>');
                    $('.dashboard-card .card-body').prepend(alert);
                }
            } catch (e) {
                const alert = $('<div class="alert alert-enhanced alert-danger"><i class="fas fa-exclamation-triangle"></i> Failed to process request</div>');
                $('.dashboard-card .card-body').prepend(alert);
            }
        },
        error: function() {
            const alert = $('<div class="alert alert-enhanced alert-danger"><i class="fas fa-exclamation-triangle"></i> Error processing request</div>');
            $('.dashboard-card .card-body').prepend(alert);
        }
    });
});

$('#reset-password-form').submit(function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    $.ajax({
        url: 'manage_users.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    const alert = $('<div class="alert alert-enhanced alert-success"><i class="fas fa-check-circle"></i> ' + result.message + '</div>');
                    $('.dashboard-card .card-body').prepend(alert);
                    $('#reset-password-form')[0].reset();
                    $('.password-requirements').hide();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    const alert = $('<div class="alert alert-enhanced alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + result.error.replace('|', '<br>') + '</div>');
                    $('.dashboard-card .card-body').prepend(alert);
                }
            } catch (e) {
                const alert = $('<div class="alert alert-enhanced alert-danger"><i class="fas fa-exclamation-triangle"></i> Failed to reset password</div>');
                $('.dashboard-card .card-body').prepend(alert);
            }
        },
        error: function() {
            const alert = $('<div class="alert alert-enhanced alert-danger"><i class="fas fa-exclamation-triangle"></i> Error resetting password</div>');
            $('.dashboard-card .card-body').prepend(alert);
        }
    });
});

$('.delete-user').click(function() {
    if (confirm('Are you sure you want to delete this user?')) {
        const userId = $(this).data('id');
        $.ajax({
            url: 'manage_users.php',
            type: 'POST',
            data: {
                action: 'delete',
                id: userId,
                csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>',
                ajax: 1
            },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $(`tr[data-user-id="${userId}"]`).remove();
                        const alert = $('<div class="alert alert-enhanced alert-success"><i class="fas fa-check-circle"></i> User deleted successfully</div>');
                        $('.dashboard-card .card-body').prepend(alert);
                    } else {
                        const alert = $('<div class="alert alert-enhanced alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + result.error + '</div>');
                        $('.dashboard-card .card-body').prepend(alert);
                    }
                } catch (e) {
                    const alert = $('<div class="alert alert-enhanced alert-danger"><i class="fas fa-exclamation-triangle"></i> Failed to delete user</div>');
                    $('.dashboard-card .card-body').prepend(alert);
                }
            },
            error: function() {
                const alert = $('<div class="alert alert-enhanced alert-danger"><i class="fas fa-exclamation-triangle"></i> Error deleting user</div>');
                $('.dashboard-card .card-body').prepend(alert);
            }
        });
    }
});

$('.view-entries, .view-processed, .view-actions').click(function(e) {
    e.preventDefault();
    const userId = $(this).closest('tr').data('user-id');
    const type = $(this).hasClass('view-entries') ? 'entries' : $(this).hasClass('view-processed') ? 'processed' : 'actions';
    const count = $(this).data('count');
    const examYear = $('#modal_exam_year').val();

    if (count == 0 && !examYear) {
        $('#details-content').html('<p>No data available.</p>');
        $('#detailsModal').modal('show');
        return;
    }

    $.ajax({
        url: 'manage_users.php',
        type: 'POST',
        data: {
            fetch_details: 'user_details',
            user_id: userId,
            exam_year: examYear,
            csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
        },
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    let html = '';
                    if (type === 'entries' && result.entries.length > 0) {
                        html += '<h5>Marks Entered</h5><table class="table table-bordered"><thead><tr><th>ID</th><th>Candidate ID</th><th>Subject</th><th>Mark</th><th>Submitted At</th></tr></thead><tbody>';
                        result.entries.forEach(entry => {
                            html += `<tr><td>${entry.id}</td><td>${entry.candidate_id}</td><td>${entry.subject}</td><td>${entry.mark}</td><td>${entry.submitted_at}</td></tr>`;
                        });
                        html += '</tbody></table>';
                    } else if (type === 'processed' && result.processed.length > 0) {
                        html += '<h5>Results Processed</h5><table class="table table-bordered"><thead><tr><th>ID</th><th>Candidate ID</th><th>Subject</th><th>Mark</th><th>Score</th><th>Processed At</th></tr></thead><tbody>';
                        result.processed.forEach(entry => {
                            html += `<tr><td>${entry.id}</td><td>${entry.candidate_id}</td><td>${entry.subject}</td><td>${entry.mark}</td><td>${entry.score}</td><td>${entry.processed_at}</td></tr>`;
                        });
                        html += '</tbody></table>';
                    } else if (type === 'actions' && result.actions.length > 0) {
                        html += '<h5>Actions</h5><table class="table table-bordered"><thead><tr><th>Action</th><th>Details</th><th>Created At</th></tr></thead><tbody>';
                        result.actions.forEach(action => {
                            html += `<tr><td>${action.action}</td><td>${action.details}</td><td>${action.created_at}</td></tr>`;
                        });
                        html += '</tbody></table>';
                    } else {
                        html = '<p>No data available for the selected year or type.</p>';
                    }
                    $('#details-content').html(html);
                    $('#detailsModal').modal('show');
                } else {
                    $('#details-content').html('<p>Error: ' + result.error + '</p>');
                    $('#detailsModal').modal('show');
                }
            } catch (e) {
                $('#details-content').html('<p>Failed to load details.</p>');
                $('#detailsModal').modal('show');
            }
        },
        error: function() {
            $('#details-content').html('<p>Error loading details.</p>');
            $('#detailsModal').modal('show');
        }
    });
});

$('#modal-filter-form').submit(function(e) {
    e.preventDefault();
    const examYear = $('#modal_exam_year').val();
    const userId = $('#detailsModal').data('current-user-id');
    const type = $('#detailsModal').data('current-type');

    $.ajax({
        url: 'manage_users.php',
        type: 'POST',
        data: {
            fetch_details: 'user_details',
            user_id: userId,
            exam_year: examYear,
            csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
        },
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    let html = '';
                    if (type === 'entries' && result.entries.length > 0) {
                        html += '<h5>Marks Entered</h5><table class="table table-bordered"><thead><tr><th>ID</th><th>Candidate ID</th><th>Subject</th><th>Mark</th><th>Submitted At</th></tr></thead><tbody>';
                        result.entries.forEach(entry => {
                            html += `<tr><td>${entry.id}</td><td>${entry.candidate_id}</td><td>${entry.subject}</td><td>${entry.mark}</td><td>${entry.submitted_at}</td></tr>`;
                        });
                        html += '</tbody></table>';
                    } else if (type === 'processed' && result.processed.length > 0) {
                        html += '<h5>Results Processed</h5><table class="table table-bordered"><thead><tr><th>ID</th><th>Candidate ID</th><th>Subject</th><th>Mark</th><th>Score</th><th>Processed At</th></tr></thead><tbody>';
                        result.processed.forEach(entry => {
                            html += `<tr><td>${entry.id}</td><td>${entry.candidate_id}</td><td>${entry.subject}</td><td>${entry.mark}</td><td>${entry.score}</td><td>${entry.processed_at}</td></tr>`;
                        });
                        html += '</tbody></table>';
                    } else if (type === 'actions' && result.actions.length > 0) {
                        html += '<h5>Actions</h5><table class="table table-bordered"><thead><tr><th>Action</th><th>Details</th><th>Created At</th></tr></thead><tbody>';
                        result.actions.forEach(action => {
                            html += `<tr><td>${action.action}</td><td>${action.details}</td><td>${action.created_at}</td></tr>`;
                        });
                        html += '</tbody></table>';
                    } else {
                        html = '<p>No data available for the selected year or type.</p>';
                    }
                    $('#details-content').html(html);
                } else {
                    $('#details-content').html('<p>Error: ' + result.error + '</p>');
                }
            } catch (e) {
                $('#details-content').html('<p>Failed to load details.</p>');
            }
        },
        error: function() {
            $('#details-content').html('<p>Error loading details.</p>');
        }
    });
});

$('#detailsModal').on('show.bs.modal', function(e) {
    const trigger = $(e.relatedTarget);
    if (trigger.hasClass('view-entries') || trigger.hasClass('view-processed') || trigger.hasClass('view-actions')) {
        const type = trigger.hasClass('view-entries') ? 'entries' : trigger.hasClass('view-processed') ? 'processed' : 'actions';
        const userId = trigger.closest('tr').data('user-id');
        $(this).data('current-user-id', userId);
        $(this).data('current-type', type);
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../layout.php';
?>