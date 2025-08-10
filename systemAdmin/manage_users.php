<?php
session_start();
require_once '../../../php/db_connect.php';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header("Location: ../../../login.php");
    exit();
}

// Log page access
$user_id = $_SESSION['user_id'];
$conn->query("CALL log_action('Manage Users Access', $user_id, 'System Admin accessed manage users page')");

// Initialize variables
$errors = [];
$success = '';
$edit_user = null;

// Handle form submissions (add/edit) and delete requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Verify CSRF token
    if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        $errors[] = 'Invalid CSRF token';
    } else {
        if ($action === 'add') {
            // Process add multiple users
            $usernames = $_POST['username'] ?? [];
            $emails = $_POST['email'] ?? [];
            $passwords = $_POST['password'] ?? [];
            $roles = $_POST['role'] ?? [];
            $statuses = $_POST['status'] ?? [];

            for ($i = 0; $i < count($usernames); $i++) {
                $username = trim($usernames[$i]);
                $email = trim($emails[$i]);
                $password = $passwords[$i];
                $role = $roles[$i];
                $status = $statuses[$i];

                // Validation
                if (empty($username) || strlen($username) > 50) {
                    $errors[] = "Username for user " . ($i + 1) . " is invalid";
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Email for user " . ($i + 1) . " is invalid";
                }
                if (strlen($password) < 6) {
                    $errors[] = "Password for user " . ($i + 1) . " must be at least 6 characters";
                }
                if (!in_array($role, ['System Admin', 'Data Entrant', 'Exams Admin'])) {
                    $errors[] = "Invalid role for user " . ($i + 1);
                }
                if (!in_array($status, ['Active', 'Invalid'])) {
                    $errors[] = "Invalid status for user " . ($i + 1);
                }

                // Check for duplicate username or email
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM system_users WHERE username = ? OR email = ?");
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
                    $errors[] = "Username or email for user " . ($i + 1) . " already exists";
                }
            }

            if (empty($errors)) {
                for ($i = 0; $i < count($usernames); $i++) {
                    $username = trim($usernames[$i]);
                    $email = trim($emails[$i]);
                    $hashed_password = password_hash($passwords[$i], PASSWORD_DEFAULT);
                    $role = $roles[$i];
                    $status = $statuses[$i];

                    $stmt = $conn->prepare("INSERT INTO system_users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $username, $email, $hashed_password, $role, $status);
                    if ($stmt->execute()) {
                        $conn->query("CALL log_action('Add User', $user_id, 'Added user: $username, $role, $status')");
                    } else {
                        $errors[] = "Failed to add user " . ($i + 1);
                    }
                }
                if (empty($errors)) {
                    if (isset($_POST['ajax'])) {
                        echo json_encode(['success' => true, 'message' => 'User(s) added successfully']);
                        exit;
                    }
                    $success = 'User(s) added successfully';
                }
            }
        } elseif ($action === 'edit') {
            // Process edit
            $edit_id = filter_input(INPUT_POST, 'edit_id', FILTER_VALIDATE_INT);
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? '';
            $status = $_POST['status'] ?? '';
            $password = $_POST['password'] ?? '';

            // Validation
            if (!$edit_id) {
                $errors[] = 'Invalid user ID';
            }
            if (empty($username) || strlen($username) > 50) {
                $errors[] = 'Username is invalid';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email is invalid';
            }
            if (!in_array($role, ['System Admin', 'Data Entrant', 'Exams Admin'])) {
                $errors[] = 'Invalid role';
            }
            if (!in_array($status, ['Active', 'Invalid'])) {
                $errors[] = 'Invalid status';
            }
            if ($password && strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters';
            }

            // Check for duplicate username or email (excluding current user)
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM system_users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->bind_param("ssi", $username, $email, $edit_id);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
                $errors[] = 'Username or email already exists';
            }

            if (empty($errors)) {
                if ($password) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE system_users SET username = ?, email = ?, password = ?, role = ?, status = ? WHERE id = ?");
                    $stmt->bind_param("sssssi", $username, $email, $hashed_password, $role, $status, $edit_id);
                } else {
                    $stmt = $conn->prepare("UPDATE system_users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $username, $email, $role, $status, $edit_id);
                }
                if ($stmt->execute()) {
                    $conn->query("CALL log_action('Edit User', $user_id, 'Edited user ID $edit_id: $username, $role, $status')");
                    if (isset($_POST['ajax'])) {
                        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
                        exit;
                    }
                    $success = 'User updated successfully';
                } else {
                    $errors[] = 'Failed to update user';
                }
            }
        } elseif ($action === 'delete') {
            // Handle AJAX delete
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $conn->prepare("DELETE FROM system_users WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $conn->query("CALL log_action('Delete User', $user_id, 'Deleted user ID $id')");
                    echo json_encode(['success' => true]);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to delete user']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
                exit;
            }
        }
    }

    // For non-AJAX requests, redirect to show messages
    if (!isset($_POST['ajax']) && (!empty($errors) || !empty($success))) {
        $query = http_build_query(['error' => $errors ? implode('|', $errors) : null, 'success' => $success]);
        header("Location: manage_users.php?$query");
        exit;
    }
}

// Fetch edit user if edit_id is provided
$edit_id = isset($_GET['edit_id']) ? filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT) : null;
if ($edit_id) {
    $stmt = $conn->prepare("SELECT id, username, email, role, status FROM system_users WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
}

// Fetch users with pagination and filters
$limit = 10; // Users per page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$exam_year_filter = isset($_GET['exam_year']) ? (int)$_GET['exam_year'] : null;

$where = [];
$params = [];
$types = '';

if ($role_filter && in_array($role_filter, ['System Admin', 'Data Entrant', 'Exams Admin'])) {
    $where[] = "u.role = ?";
    $params[] = $role_filter;
    $types .= 's';
}
if ($status_filter && in_array($status_filter, ['Active', 'Invalid'])) {
    $where[] = "u.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$sql = "SELECT u.id, u.username, u.email, u.role, u.status, u.last_login, 
        (SELECT COUNT(*) FROM marks m WHERE m.submitted_by = u.id";
if ($exam_year_filter) {
    $sql .= " AND YEAR(m.created_at) = ?";
    $params[] = $exam_year_filter;
    $types .= 'i';
}
$sql .= ") as entries_count,
        (SELECT COUNT(*) FROM audit_logs al WHERE al.id = u.id";
if ($exam_year_filter) {
    $sql .= " AND YEAR(al.created_at) = ?";
    $params[] = $exam_year_filter;
    $types .= 'i';
}
$sql .= ") as actions_count
        FROM system_users u";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY u.id ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);

// Count total users for pagination
$count_sql = "SELECT COUNT(*) as total FROM system_users u";
if (!empty($where)) {
    $count_sql .= " WHERE " . implode(" AND ", $where);
}
$stmt = $conn->prepare($count_sql);
if (!empty($params) && count($params) > 2) {
    $count_types = substr($types, 0, -4); // Remove LIMIT/OFFSET types
    $count_params = array_slice($params, 0, -2);
    $stmt->bind_param($count_types, ...$count_params);
}
$stmt->execute();
$total_users = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_users / $limit);

// Fetch exam years
$current_exam_year_result = $conn->query("SELECT MAX(Exam_year) as current_exam_year FROM exam_years");
$current_exam_year = $current_exam_year_result->fetch_assoc()['current_exam_year'] ?? date('Y');
$previous_exam_years_result = $conn->query("SELECT Exam_year FROM exam_years WHERE Exam_year < $current_exam_year ORDER BY Exam_year DESC");
$exam_years = [];
while ($row = $previous_exam_years_result->fetch_assoc()) {
    $exam_years[] = $row['Exam_year'];
}

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');

$username = htmlspecialchars($_SESSION['username']);
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Results Management System</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: url('../../../background.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            font-family: Arial, sans-serif;
            margin: 0;
        }
        .container-fluid {
            background-color: rgba(0, 0, 0, 0.3);
            min-height: 100vh;
        }
        .sidebar {
            background-color: rgba(255, 255, 255, 0.9);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            padding-top: 60px;
            transition: transform 0.3s ease;
        }
        .sidebar.collapsed {
            transform: translateX(-250px);
        }
        .sidebar .nav-link {
            color: #333;
            padding: 10px 20px;
        }
        .sidebar .nav-link:hover {
            background-color: #ffd700;
            color: #000;
        }
        .sidebar .nav-link.active {
            background-color: #ffd700;
            color: #000;
        }
        .sidebar .collapse .nav-link {
            padding-left: 40px;
            font-size: 0.9em;
        }
        .topbar {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 10px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        .topbar .btn-danger {
            background-color: #dc3545;
        }
        .main-content {
            margin-left: 250px;
            padding: 80px 20px 20px;
        }
        .card {
            background-color: rgba(255, 255, 255, 0.9);
            color: #333;
            border: none;
            border-radius: 10px;
        }
        .btn-primary, .btn-info, .btn-secondary {
            background-color: #ffd700;
            border: none;
            color: #000;
        }
        .btn-primary:hover, .btn-info:hover, .btn-secondary:hover {
            background-color: #ffc107;
        }
        h1, h2 {
            color: #ffd700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        .table {
            background-color: rgba(255, 255, 255, 0.9);
            color: #333;
        }
        .pagination .page-link {
            color: #ffd700;
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
        }
        .pagination .page-link:hover {
            background-color: #ffc107;
        }
        .pagination .page-item.active .page-link {
            background-color: #ffd700;
            color: #000;
        }
        .user-fields .form-row {
            margin-bottom: 15px;
        }
        .password-requirements {
            font-size: 0.9em;
            color: red;
            display: none;
        }
        .password-requirements.valid {
            color: green;
        }
        .details-row td {
            background-color: rgba(200, 200, 200, 0.9);
        }
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-250px);
            }
            .sidebar.collapsed {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="topbar d-flex justify-content-between align-items-center">
        <button class="btn btn-outline-light d-lg-none" type="button" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div>
            <span class="mr-3">Board: <?php echo htmlspecialchars($board_name); ?></span>
            <span class="mr-3">Year: <?php echo htmlspecialchars($exam_year); ?></span>
            <span class="mr-3">User: <?php echo $username; ?></span>
            <a href="../../../logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebar" class="sidebar bg-light col-lg-2">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#schoolsMenu">
                                <i class="fas fa-school"></i> Schools
                            </a>
                            <div id="schoolsMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="../schools/add_school.php">Add School</a></li>
                                    <li class="nav-item"><a class="nav-link" href="../schools/manage_schools.php">Manage Schools</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="#" data-toggle="collapse" data-target="#usersMenu">
                                <i class="fas fa-users"></i> Users
                            </a>
                            <div id="usersMenu" class="collapse show">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="add_user.php">Add User</a></li>
                                    <li class="nav-item"><a class="nav-link active" href="manage_users.php">Manage Users</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#subcountiesMenu">
                                <i class="fas fa-map-marker-alt"></i> Subcounties
                            </a>
                            <div id="subcountiesMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="../subcounties/add_subcounty.php">Add Subcounty</a></li>
                                    <li class="nav-item"><a class="nav-link" href="../subcounties/manage_subcounties.php">Manage Subcounties</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#targetsMenu">
                                <i class="fas fa-bullseye"></i> Daily Targets
                            </a>
                            <div id="targetsMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="../targets/set_targets.php">Set Targets</a></li>
                                    <li class="nav-item"><a class="nav-link" href="../targets/manage_targets.php">Manage Targets</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#gradingMenu">
                                <i class="fas fa-table"></i> Grading
                            </a>
                            <div id="gradingMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="../grading/manage_grading.php">Manage Grading Table</a></li>
                                    <li class="nav-item"><a class="nav-link" href="../grading/manage_grades.php">Manage Grade Table</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../analysis/run_analysis.php">
                                <i class="fas fa-chart-bar"></i> Run Analysis
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#resultsMenu">
                                <i class="fas fa-file-alt"></i> Results
                            </a>
                            <div id="resultsMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="../results/view_results.php">View Results</a></li>
                                    <li class="nav-item"><a class="nav-link" href="../results/audit_results.php">Audit Results</a></li>
                                    <li class="nav-item"><a class="nav-link" href="../results/generate_school_results.php">School Results</a></li>
                                    <li class="nav-item"><a class="nav-link" href="../results/generate_subcounty_results.php">Subcounty Results</a></li>
                                    <li class="nav-item"><a class="nav-link" href="../results/generate_general_results.php">General Results</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../audit_logs.php">
                                <i class="fas fa-history"></i> Audit Logs
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content col-lg-10">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Manage Users</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item">Users</li>
                            <li class="breadcrumb-item active" aria-current="page">Manage Users</li>
                        </ol>
                    </nav>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h2><?php echo $edit_user ? 'Edit User' : 'Add User'; ?></h2>
                        <form id="user-form" method="post" class="mb-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="<?php echo $edit_user ? 'edit' : 'add'; ?>">
                            <input type="hidden" name="ajax" value="1">
                            <?php if ($edit_user): ?>
                                <input type="hidden" name="edit_id" value="<?php echo $edit_user['id']; ?>">
                            <?php endif; ?>
                            <div id="user-fields" class="<?php echo $edit_user ? 'single-user' : ''; ?>">
                                <div class="form-row">
                                    <div class="form-group col-md-2">
                                        <label for="username">Username</label>
                                        <input type="text" class="form-control" name="username[]" value="<?php echo htmlspecialchars($edit_user['username'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="email">Email</label>
                                        <input type="email" class="form-control" name="email[]" value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="password">Password</label>
                                        <input type="password" class="form-control" name="password[]" <?php echo $edit_user ? '' : 'required'; ?> onkeyup="validatePassword(this)">
                                        <small class="password-requirements">Password must be at least 6 characters.</small>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="role">Role</label>
                                        <select name="role[]" class="form-control" required>
                                            <option value="System Admin" <?php echo ($edit_user && $edit_user['role'] === 'System Admin') ? 'selected' : ''; ?>>System Admin</option>
                                            <option value="Data Entrant" <?php echo ($edit_user && $edit_user['role'] === 'Data Entrant') ? 'selected' : ''; ?>>Data Entrant</option>
                                            <option value="Exams Admin" <?php echo ($edit_user && $edit_user['role'] === 'Exams Admin') ? 'selected' : ''; ?>>Exams Admin</option>
                                        </select>
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
                            <?php if (!$edit_user): ?>
                                <button type="button" class="btn btn-secondary mb-3" id="add-user-button">Add Another User</button>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary"><?php echo $edit_user ? 'Update User' : 'Add User(s)'; ?></button>
                            <?php if ($edit_user): ?>
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='manage_users.php'">Cancel</button>
                            <?php endif; ?>
                        </form>

                        <!-- Filters -->
                        <form method="get" class="mb-4">
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label for="role_filter">Filter by Role</label>
                                    <select name="role" id="role_filter" class="form-control">
                                        <option value="">All Roles</option>
                                        <option value="System Admin" <?php echo $role_filter === 'System Admin' ? 'selected' : ''; ?>>System Admin</option>
                                        <option value="Data Entrant" <?php echo $role_filter === 'Data Entrant' ? 'selected' : ''; ?>>Data Entrant</option>
                                        <option value="Exams Admin" <?php echo $role_filter === 'Exams Admin' ? 'selected' : ''; ?>>Exams Admin</option>
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
                                    <label for="exam_year_filter">Filter by Exam Year</label>
                                    <select name="exam_year" id="exam_year_filter" class="form-control">
                                        <option value="">All Years</option>
                                        <option value="<?php echo $current_exam_year; ?>" <?php echo $exam_year_filter == $current_exam_year ? 'selected' : ''; ?>><?php echo $current_exam_year; ?></option>
                                        <?php foreach ($exam_years as $year): ?>
                                            <option value="<?php echo $year; ?>" <?php echo $exam_year_filter == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-3 align-self-end">
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                </div>
                            </div>
                        </form>

                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars(str_replace('|', '<br>', $_GET['error'])); ?></div>
                        <?php endif; ?>
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
                        <?php endif; ?>
                        <div id="alert-container"></div>

                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Entries</th>
                                    <th>Actions</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr><td colspan="9">No users found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                                            <td><?php echo htmlspecialchars($user['status']); ?></td>
                                            <td><?php echo htmlspecialchars($user['last_login'] ?? 'Never'); ?></td>
                                            <td><?php echo htmlspecialchars($user['entries_count']); ?></td>
                                            <td><?php echo htmlspecialchars($user['actions_count']); ?></td>
                                            <td>
                                                <button class="btn btn-info btn-sm edit-btn" data-id="<?php echo $user['id']; ?>">Edit</button>
                                                <button class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $user['id']; ?>">Delete</button>
                                                <button class="btn btn-secondary btn-sm details-btn" data-id="<?php echo $user['id']; ?>">Details</button>
                                            </td>
                                        </tr>
                                        <tr class="details-row" id="details-<?php echo $user['id']; ?>" style="display: none;">
                                            <td colspan="9">
                                                <div class="entries">
                                                    <h5>Entries</h5>
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Mark ID</th>
                                                                <th>Student ID</th>
                                                                <th>Subject</th>
                                                                <th>Marks</th>
                                                                <th>Created At</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="entries-<?php echo $user['id']; ?>"></tbody>
                                                    </table>
                                                </div>
                                                <div class="actions">
                                                    <h5>Actions</h5>
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Action</th>
                                                                <th>Description</th>
                                                                <th>Created At</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="actions-<?php echo $user['id']; ?>"></tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&exam_year=<?php echo $exam_year_filter; ?>" aria-label="Previous">
                                        <span aria-hidden="true">«</span>
                                    </a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&exam_year=<?php echo $exam_year_filter; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&exam_year=<?php echo $exam_year_filter; ?>" aria-label="Next">
                                        <span aria-hidden="true">»</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        function validatePassword(input) {
            const requirements = input.nextElementSibling;
            if (input.value.length >= 6) {
                requirements.classList.add('valid');
                requirements.textContent = 'Password meets the requirements.';
            } else {
                requirements.classList.remove('valid');
                requirements.textContent = 'Password must be at least 6 characters.';
            }
            requirements.style.display = 'block';
        }

        document.getElementById('add-user-button').addEventListener('click', function() {
            const userFields = document.getElementById('user-fields');
            const newFields = userFields.firstElementChild.cloneNode(true);
            newFields.querySelectorAll('input').forEach(input => input.value = '');
            newFields.querySelector('select[name="role[]"]').value = 'Data Entrant';
            newFields.querySelector('select[name="status[]"]').value = 'Active';
            newFields.querySelector('.password-requirements').style.display = 'none';
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
                            $('#alert-container').html('<div class="alert alert-success">' + result.message + '</div>');
                            $('#user-form')[0].reset();
                            $('#user-fields').html($('#user-fields .form-row').first().clone());
                            $('.password-requirements').hide();
                            location.reload(); // Refresh to show new users
                        } else {
                            $('#alert-container').html('<div class="alert alert-danger">' + result.error + '</div>');
                        }
                    } catch (e) {
                        $('#alert-container').html('<div class="alert alert-danger">Failed to process request</div>');
                    }
                },
                error: function() {
                    $('#alert-container').html('<div class="alert alert-danger">Error processing request</div>');
                }
            });
        });

        $('.edit-btn').click(function() {
            window.location.href = 'manage_users.php?edit_id=' + $(this).data('id');
        });

        $('.delete-btn').click(function() {
            if (confirm('Are you sure you want to delete this user?')) {
                const userId = $(this).data('id');
                $.ajax({
                    url: 'manage_users.php',
                    type: 'POST',
                    data: { action: 'delete', id: userId, csrf_token: '<?php echo $csrf_token; ?>', ajax: 1 },
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                $('#alert-container').html('<div class="alert alert-success">User deleted successfully</div>');
                                location.reload();
                            } else {
                                $('#alert-container').html('<div class="alert alert-danger">' + result.error + '</div>');
                            }
                        } catch (e) {
                            $('#alert-container').html('<div class="alert alert-danger">Failed to delete user</div>');
                        }
                    },
                    error: function() {
                        $('#alert-container').html('<div class="alert alert-danger">Error deleting user</div>');
                    }
                });
            }
        });

        $('.details-btn').click(function() {
            const userId = $(this).data('id');
            const detailsRow = $('#details-' + userId);
            const entriesTable = $('#entries-' + userId);
            const actionsTable = $('#actions-' + userId);

            if (detailsRow.is(':visible')) {
                detailsRow.hide();
            } else {
                // Fetch entries
                $.ajax({
                    url: 'fetch_user_details.php',
                    type: 'POST',
                    data: { user_id: userId, type: 'entries', exam_year: '<?php echo $exam_year_filter ?: ''; ?>', csrf_token: '<?php echo $csrf_token; ?>' },
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                entriesTable.html('');
                                result.data.forEach(entry => {
                                    entriesTable.append(`
                                        <tr>
                                            <td>${entry.id}</td>
                                            <td>${entry.student_id}</td>
                                            <td>${entry.subject}</td>
                                            <td>${entry.marks}</td>
                                            <td>${entry.created_at}</td>
                                        </tr>
                                    `);
                                });
                            } else {
                                entriesTable.html('<tr><td colspan="5">No entries found</td></tr>');
                            }
                        } catch (e) {
                            entriesTable.html('<tr><td colspan="5">Error loading entries</td></tr>');
                        }
                    }
                });

                // Fetch actions
                $.ajax({
                    url: 'fetch_user_details.php',
                    type: 'POST',
                    data: { user_id: userId, type: 'actions', exam_year: '<?php echo $exam_year_filter ?: ''; ?>', csrf_token: '<?php echo $csrf_token; ?>' },
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                actionsTable.html('');
                                result.data.forEach(action => {
                                    actionsTable.append(`
                                        <tr>
                                            <td>${action.action}</td>
                                            <td>${action.description}</td>
                                            <td>${action.created_at}</td>
                                        </tr>
                                    `);
                                });
                            } else {
                                actionsTable.html('<tr><td colspan="3">No actions found</td></tr>');
                            }
                        } catch (e) {
                            actionsTable.html('<tr><td colspan="3">Error loading actions</td></tr>');
                        }
                    }
                });

                detailsRow.show();
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>