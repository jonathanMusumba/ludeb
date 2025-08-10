<?php
session_start();
require_once '../db_connect.php';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header("Location: ../../login.php");
    exit();
}

// Log page access
$user_id = $_SESSION['user_id'];
$conn->query("CALL log_action('Manage Users Access', $user_id, 'System Admin accessed manage users page')");

// Initialize variables
$errors = [];
$success = '';
$edit_user = null;

// Handle AJAX request for user details
if (isset($_POST['fetch_details']) && $_POST['fetch_details'] === 'user_details') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $exam_year = filter_input(INPUT_POST, 'exam_year', FILTER_VALIDATE_INT) ?: null;
    $csrf_token = $_POST['csrf_token'] ?? '';

    if ($csrf_token !== $_SESSION['csrf_token'] || !$user_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }

    $response = ['success' => true, 'entries' => [], 'processed' => [], 'actions' => []];

    // Fetch marks entered
    $sql = "SELECT id, candidate_id, subject, mark, submitted_at FROM marks WHERE submitted_by = ?";
    if ($exam_year) {
        $sql .= " AND YEAR(submitted_at) = ?";
    }
    $stmt = $conn->prepare($sql);
    if ($exam_year) {
        $stmt->bind_param("ii", $user_id, $exam_year);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $response['entries'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch results processed
    $sql = "SELECT id, candidate_id, subject, mark, score, processed_at FROM results WHERE processed_by = ?";
    if ($exam_year) {
        $sql .= " AND YEAR(processed_at) = ?";
    }
    $stmt = $conn->prepare($sql);
    if ($exam_year) {
        $stmt->bind_param("ii", $user_id, $exam_year);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $response['processed'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch actions
    $sql = "SELECT action, details, created_at FROM audit_logs WHERE user_id = ?";
    if ($exam_year) {
        $sql .= " AND YEAR(created_at) = ?";
    }
    $stmt = $conn->prepare($sql);
    if ($exam_year) {
        $stmt->bind_param("ii", $user_id, $exam_year);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $response['actions'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode($response);
    exit;
}

// Handle form submissions (add/edit) and delete requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['fetch_details'])) {
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
                if (empty($username) || strlen($username) > 255) {
                    $errors[] = "Username for user " . ($i + 1) . " is invalid";
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Email for user " . ($i + 1) . " is invalid";
                }
                if (strlen($password) < 6) {
                    $errors[] = "Password for user " . ($i + 1) . " must be at least 6 characters";
                }
                if (!in_array($role, ['System Admin', 'Exams Admin', 'Data Entrant'])) {
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
            if (empty($username) || strlen($username) > 255) {
                $errors[] = 'Username is invalid';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email is invalid';
            }
            if (!in_array($role, ['System Admin', 'Exams Admin', 'Data Entrant'])) {
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

if ($role_filter && in_array($role_filter, ['System Admin', 'Exams Admin', 'Data Entrant'])) {
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
    $sql .= " AND YEAR(m.submitted_at) = ?";
    $params[] = $exam_year_filter;
    $types .= 'i';
}
$sql .= ") as entries_count,
        (SELECT COUNT(*) FROM results r WHERE r.processed_by = u.id";
if ($exam_year_filter) {
    $sql .= " AND YEAR(r.processed_at) = ?";
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
$current_exam_year_result = $conn->query("SELECT MAX(exam_year) as current_exam_year FROM exam_years");
$current_exam_year = $current_exam_year_result->fetch_assoc()['current_exam_year'] ?? date('Y');
$previous_exam_years_result = $conn->query("SELECT exam_year FROM exam_years WHERE exam_year < $current_exam_year ORDER BY exam_year DESC");
$exam_years = [];
while ($row = $previous_exam_years_result->fetch_assoc()) {
    $exam_years[] = $row['exam_year'];
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
            background: url('../../Common/background.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow-x: hidden;
        }
        .container-fluid {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.3));
            min-height: 100vh;
            padding-top: 80px;
        }
        .sidebar {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(220, 220, 220, 0.95));
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            padding-top: 70px;
            transition: transform 0.3s ease;
            z-index: 1001;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.2);
        }
        .sidebar.collapsed {
            transform: translateX(-250px);
        }
        .sidebar .nav-link {
            color: #2d3436;
            padding: 12px 20px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid transparent;
        }
        .sidebar .nav-link:hover {
            background: #ffd700;
            color: #000;
            transform: translateX(5px);
            border-left-color: #e63946;
        }
        .sidebar .nav-link.active {
            background: #ffd700;
            color: #000;
            font-weight: 600;
            border-left-color: #e63946;
        }
        .sidebar .nav-link i {
            transition: transform 0.3s ease;
        }
        .sidebar .nav-link:hover i {
            transform: rotate(15deg);
        }
        .sidebar .collapse .nav-link {
            padding-left: 40px;
            font-size: 0.9em;
        }
        .topbar {
            background: linear-gradient(90deg, rgba(40, 40, 40, 0.9), rgba(20, 20, 20, 0.95));
            padding: 10px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        .topbar .board-name {
            font-size: 1.4rem;
            font-weight: 800;
            margin: 0;
            text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.7);
            color: #ffd700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 70%;
        }
        .topbar .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #f1f3f5;
            font-weight: 500;
            white-space: nowrap;
            font-size: 0.9rem;
        }
        .topbar .btn-danger {
            background-color: #e63946;
            border: none;
            transition: background 0.3s ease, transform 0.3s ease;
            padding: 5px 15px;
        }
        .topbar .btn-danger:hover {
            background-color: #d00000;
            transform: scale(1.05);
        }
        .topbar .btn-outline-light {
            transition: transform 0.3s ease;
            padding: 5px 10px;
        }
        .topbar .btn-outline-light:hover {
            transform: scale(1.1);
        }
        .main-content {
            margin-left: 250px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }
        .card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(245, 245, 245, 0.1));
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            padding: 25px;
            color: #fff;
        }
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
        }
        .card-body {
            position: relative;
            z-index: 1;
        }
        .btn-primary, .btn-info, .btn-secondary {
            background: linear-gradient(90deg, #ffd700, #ffca28);
            border: none;
            color: #1d3557;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.3s ease;
            padding: 8px 15px;
            margin: 0 5px;
        }
        .btn-primary:hover, .btn-info:hover, .btn-secondary:hover {
            background: linear-gradient(90deg, #ffca28, #ffb300);
            transform: scale(1.05);
        }
        .btn-danger {
            background-color: #e63946;
            transition: background 0.3s ease, transform 0.3s ease;
            padding: 8px 15px;
            margin: 0 5px;
        }
        .btn-danger:hover {
            background-color: #d00000;
            transform: scale(1.05);
        }
        h1, h2 {
            color: #ffd700;
            text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.7);
            font-weight: 800;
            position: relative;
            margin-bottom: 25px;
        }
        h1::after, h2::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 50px;
            height: 4px;
            background: #ffd700;
            transition: width 0.3s ease;
        }
        h1:hover::after, h2:hover::after {
            width: 100px;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.95);
            color: #2d3436;
            border: 1px solid rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus {
            border-color: #ffd700;
            box-shadow: 0 0 8px rgba(255, 215, 0, 0.5);
        }
        .form-group label {
            color: #ffd700;
            font-weight: 500;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }
        .user-fields .form-row {
            margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        .user-fields .form-row:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .password-requirements {
            font-size: 0.9em;
            display: none;
            margin-top: 5px;
            transition: color 0.3s ease;
        }
        .password-requirements.invalid {
            color: #e63946;
            display: block;
        }
        .password-requirements.valid {
            color: #28a745;
            display: block;
        }
        .table {
            background: rgba(255, 255, 255, 0.95);
            color: #2d3436;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .table thead th {
            background: #1d3557;
            color: #ffd700;
            font-weight: 600;
        }
        .table tbody tr {
            transition: background 0.3s ease;
        }
        .table tbody tr:hover {
            background: rgba(255, 215, 0, 0.1);
        }
        .pagination .page-link {
            color: #ffd700;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            transition: background 0.3s ease, transform 0.3s ease;
        }
        .pagination .page-link:hover {
            background: #ffc107;
            transform: scale(1.05);
        }
        .pagination .page-item.active .page-link {
            background: #ffd700;
            color: #000;
            font-weight: 600;
        }
        .alert-success, .alert-danger {
            background: rgba(255, 255, 255, 0.95);
            color: #2d3436;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .details-row {
            display: none;
            transition: all 0.3s ease;
        }
        .details-row td {
            background: rgba(200, 200, 200, 0.9);
            padding: 20px;
        }
        .details-row h5 {
            color: #1d3557;
            margin-bottom: 15px;
        }
        .details-row .table {
            background: rgba(255, 255, 255, 0.98);
        }
        .loading {
            font-style: italic;
            color: #666;
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
                padding: 90px 15px 15px;
            }
            .topbar .board-name {
                font-size: 1.1rem;
                max-width: 50%;
            }
            .topbar .user-info {
                font-size: 0.8rem;
                gap: 5px;
            }
            .topbar .btn-danger,
            .topbar .btn-outline-light {
                padding: 3px 8px;
                font-size: 0.8rem;
            }
            .form-row {
                flex-direction: column;
            }
            .form-group {
                margin-bottom: 15px;
            }
            .table-responsive {
                overflow-x: auto;
            }
            .table th, .table td {
                font-size: 0.9rem;
                padding: 8px;
            }
            .btn-sm {
                padding: 5px 10px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="board-name" title="<?php echo htmlspecialchars($board_name); ?>"><?php echo htmlspecialchars($board_name); ?></div>
        <div class="user-info">
            <span>User: <?php echo $username; ?> | Year: <?php echo $exam_year; ?></span>
            <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
        <button class="btn btn-outline-light d-lg-none" type="button" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebar" class="sidebar bg-light col-lg-2">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../Dashboard.php">
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
                        <li class="nav-item">
                            <a class="nav-link" href="../chat/team_chat.php">
                                <i class="fas fa-comments"></i> Team Chat
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content col-lg-10">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <h1>Manage Users</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent">
                            <li class="breadcrumb-item"><a href="../Dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item">Users</li>
                            <li class="breadcrumb-item active" aria-current="page">Manage Users</li>
                        </ol>
                    </nav>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h2><?php echo $edit_user !== null ? 'Edit User' : 'Add User'; ?></h2>
                        <form id="user-form" method="post" class="mb-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="<?php echo $edit_user !== null ? 'edit' : 'add'; ?>">
                            <input type="hidden" name="ajax" value="1">
                            <?php if ($edit_user !== null): ?>
                                <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($edit_user['id'] ?? ''); ?>">
                            <?php endif; ?>
                            <div id="user-fields" class="user-fields <?php echo $edit_user !== null ? 'single-user' : ''; ?>">
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
                                        <input type="password" class="form-control" name="password[]" <?php echo $edit_user !== null ? '' : 'required'; ?> onkeyup="validatePassword(this)">
                                        <small class="password-requirements">Password must be at least 6 characters.</small>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="role">Role</label>
                                        <select name="role[]" class="form-control" required>
                                            <option value="System Admin" <?php echo ($edit_user !== null && $edit_user['role'] === 'System Admin') ? 'selected' : ''; ?>>System Admin</option>
                                            <option value="Exams Admin" <?php echo ($edit_user !== null && $edit_user['role'] === 'Exams Admin') ? 'selected' : ''; ?>>Exams Admin</option>
                                            <option value="Data Entrant" <?php echo ($edit_user !== null && $edit_user['role'] === 'Data Entrant') ? 'selected' : ''; ?>>Data Entrant</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="status">Status</label>
                                        <select name="status[]" class="form-control" required>
                                            <option value="Active" <?php echo ($edit_user !== null && $edit_user['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="Invalid" <?php echo ($edit_user !== null && $edit_user['status'] === 'Invalid') ? 'selected' : ''; ?>>Invalid</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php if ($edit_user === null): ?>
                                <button type="button" class="btn btn-secondary mb-3" id="add-user-button">Add Another User</button>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary"><?php echo $edit_user !== null ? 'Update User' : 'Add User(s)'; ?></button>
                            <?php if ($edit_user !== null): ?>
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
                                        <option value="Exams Admin" <?php echo $role_filter === 'Exams Admin' ? 'selected' : ''; ?>>Exams Admin</option>
                                        <option value="Data Entrant" <?php echo $role_filter === 'Data Entrant' ? 'selected' : ''; ?>>Data Entrant</option>
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

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Marks Entered</th>
                                        <th>Results Processed</th>
                                        <th>Actions</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr><td colspan="10">No users found</td></tr>
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
                                                <td><?php echo htmlspecialchars($user['processed_count']); ?></td>
                                                <td><?php echo htmlspecialchars($user['actions_count']); ?></td>
                                                <td>
                                                    <button class="btn btn-info btn-sm edit-btn" data-id="<?php echo $user['id']; ?>">Edit</button>
                                                    <button class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $user['id']; ?>">Delete</button>
                                                    <button class="btn btn-secondary btn-sm details-btn" data-id="<?php echo $user['id']; ?>">Details</button>
                                                </td>
                                            </tr>
                                            <tr class="details-row" id="details-<?php echo $user['id']; ?>">
                                                <td colspan="10">
                                                    <div class="entries">
                                                        <h5>Marks Entered</h5>
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Mark ID</th>
                                                                    <th>Candidate ID</th>
                                                                    <th>Subject</th>
                                                                    <th>Mark</th>
                                                                    <th>Submitted At</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="entries-<?php echo $user['id']; ?>">
                                                                <tr><td colspan="5" class="loading">Loading...</td></tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="processed">
                                                        <h5>Results Processed</h5>
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Result ID</th>
                                                                    <th>Candidate ID</th>
                                                                    <th>Subject</th>
                                                                    <th>Mark</th>
                                                                    <th>Score</th>
                                                                    <th>Processed At</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="processed-<?php echo $user['id']; ?>">
                                                                <tr><td colspan="6" class="loading">Loading...</td></tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="actions">
                                                        <h5>Actions</h5>
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Action</th>
                                                                    <th>Details</th>
                                                                    <th>Created At</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="actions-<?php echo $user['id']; ?>">
                                                                <tr><td colspan="3" class="loading">Loading...</td></tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

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
        $(document).ready(function() {
            // Sidebar toggle
            $('#sidebarToggle').click(function() {
                $('#sidebar').toggleClass('collapsed');
            });

            // Password validation
            function validatePassword(input) {
                const requirements = $(input).next('.password-requirements');
                if (input.value.length >= 6) {
                    requirements.removeClass('invalid').addClass('valid').text('Password meets requirements.');
                } else {
                    requirements.removeClass('valid').addClass('invalid').text('Password must be at least 6 characters.');
                }
                requirements.show();
            }

            // Add another user
            $('#add-user-button').click(function() {
                const $userFields = $('#user-fields');
                const $newFields = $userFields.find('.form-row').first().clone();
                $newFields.find('input').val('');
                $newFields.find('select[name="role[]"]').val('Data Entrant');
                $newFields.find('select[name="status[]"]').val('Active');
                $newFields.find('.password-requirements').hide();
                $userFields.append($newFields);
            });

            // Form submission
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
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                $('#alert-container').html('<div class="alert alert-danger">' + result.error + '</div>');
                            }
                        } catch (e) {
                            $('#alert-container').html('<div class="alert alert-danger">Failed to process request</div>');
                            console.error('Form submission error:', e);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#alert-container').html('<div class="alert alert-danger">Error processing request</div>');
                        console.error('AJAX error:', status, error);
                    }
                });
            });

            // Edit button
            $('.edit-btn').click(function() {
                window.location.href = 'manage_users.php?edit_id=' + $(this).data('id');
            });

            // Delete button
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
                                    $('#alert-container').html('<div class="alert alert-success'>User deleted successfully</div>');
                                    setTimeout(() => location.reload(), 1000);
                                } else {
                                    $('#alert-container').html('<div class="alert alert-danger">' + result.error + '</div>');
                                }
                            } catch (e) {
                                $('#alert-container').html('<div class="alert alert-danger">Failed to delete user</div>');
                                console.error('Delete error:', e);
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#alert-container').html('<div class="alert alert-danger">Error deleting user</div>');
                            console.error('AJAX error:', status, error);
                        }
                    });
                }
            });

            // Details button with smooth toggle
            $('.details-btn').click(function() {
                const userId = $(this).data('id');
                const $detailsRow = $('#details-' + userId);
                const $entriesTable = $('#entries-' + userId);
                const $processedTable = $('#processed-' + userId);
                const $actionsTable = $('#actions-' + userId);

                if ($detailsRow.is(':visible')) {
                    $detailsRow.slideUp(300);
                } else {
                    // Reset tables to loading state
                    $entriesTable.html('<tr><td colspan="5" class="loading">Loading...</td></tr>');
                    $processedTable.html('<tr><td colspan="6" class="loading">Loading...</td></tr>');
                    $actionsTable.html('<tr><td colspan="3" class="loading">Loading...</td></tr>');

                    // Fetch all details in a single AJAX call
                    $.ajax({
                        url: 'manage_users.php',
                        type: 'POST',
                        data: { 
                            fetch_details: 'user_details', 
                            user_id: userId, 
                            exam_year: '<?php echo $exam_year_filter ?: ''; ?>', 
                            csrf_token: '<?php echo $csrf_token; ?>' 
                        },
                        success: function(response) {
                            try {
                                const result = JSON.parse(response);
                                if (result.success) {
                                    // Populate marks entered
                                    $entriesTable.empty();
                                    if (result.entries.length > 0) {
                                        result.entries.forEach(entry => {
                                            $entriesTable.append(`
                                                <tr>
                                                    <td>${entry.id}</td>
                                                    <td>${entry.candidate_id}</td>
                                                    <td>${entry.subject}</td>
                                                    <td>${entry.mark}</td>
                                                    <td>${entry.submitted_at}</td>
                                                </tr>
                                            `);
                                        });
                                    } else {
                                        $entriesTable.html('<tr><td colspan="5">No marks found</td></tr>');
                                    }

                                    // Populate results processed
                                    $processedTable.empty();
                                    if (result.processed.length > 0) {
                                        result.processed.forEach(item => {
                                            $processedTable.append(`
                                                <tr>
                                                    <td>${item.id}</td>
                                                    <td>${item.candidate_id}</td>
                                                    <td>${item.subject}</td>
                                                    <td>${item.mark}</td>
                                                    <td>${item.score}</td>
                                                    <td>${item.processed_at}</td>
                                                </tr>
                                            `);
                                        });
                                    } else {
                                        $processedTable.html('<tr><td colspan="6">No results found</td></tr>');
                                    }

                                    // Populate actions
                                    $actionsTable.empty();
                                    if (result.actions.length > 0) {
                                        result.actions.forEach(action => {
                                            $actionsTable.append(`
                                                <tr>
                                                    <td>${action.action}</td>
                                                    <td>${action.details || 'N/A'}</td>
                                                    <td>${action.created_at}</td>
                                                </tr>
                                            `);
                                        });
                                    } else {
                                        $actionsTable.html('<tr><td colspan="3">No actions found</td></tr>');
                                    }
                                } else {
                                    $entriesTable.html('<tr><td colspan="5">' + result.error + '</td></tr>');
                                    $processedTable.html('<tr><td colspan="6">' + result.error + '</td></tr>');
                                    $actionsTable.html('<tr><td colspan="3">' + result.error + '</td></tr>');
                                }
                            } catch (e) {
                                $entriesTable.html('<tr><td colspan="5">Error loading marks</td></tr>');
                                $processedTable.html('<tr><td colspan="6">Error loading results</td></tr>');
                                $actionsTable.html('<tr><td colspan="3">Error loading actions</td></tr>');
                                console.error('Details parse error:', e);
                            }
                        },
                        error: function(xhr, status, error) {
                            $entriesTable.html('<tr><td colspan="5">Error loading marks</td></tr>');
                            $processedTable.html('<tr><td colspan="6">Error loading results</td></tr>');
                            $actionsTable.html('<tr><td colspan="3">Error loading actions</td></tr>');
                            console.error('AJAX error:', status, error);
                        }
                    });

                    $detailsRow.slideDown(300);
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>