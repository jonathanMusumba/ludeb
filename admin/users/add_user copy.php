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
$conn->query("CALL log_action('Add User Access', $user_id, 'System Admin accessed add user page')");

// Handle AJAX validation for username/email
if (isset($_POST['validate']) && $_POST['validate'] === 'username_email') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $errors = [];

    if (!empty($username)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM system_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
            $errors['username'] = "Username already exists";
        }
    }
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM system_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
            $errors['email'] = "Email already exists";
        }
    }
    echo json_encode($errors);
    exit;
}

// Handle form submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['validate'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $usernames = $_POST['username'] ?? [];
    $emails = $_POST['email'] ?? [];
    $passwords = $_POST['password'] ?? [];
    $roles = $_POST['role'] ?? [];
    $statuses = $_POST['status'] ?? [];
    $errors = [];

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
        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
            $errors[] = "Password for user " . ($i + 1) . " must be at least 8 characters, include a letter, a number, and a special character";
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
            if (!$stmt->execute()) {
                $errors[] = "Failed to add user " . ($i + 1);
            } else {
                $conn->query("CALL log_action('Add User', $user_id, 'Added user: $username, $role, $status')");
            }
        }
        if (empty($errors)) {
            echo json_encode(['success' => true, 'message' => 'User(s) added successfully']);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => implode('|', $errors)]);
    exit;
}

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
    <title>Add User - Results Management System</title>
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
        .btn-primary, .btn-secondary {
            background: linear-gradient(90deg, #ffd700, #ffca28);
            border: none;
            color: #1d3557;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.3s ease;
            padding: 8px 20px;
        }
        .btn-primary:hover, .btn-secondary:hover {
            background: linear-gradient(90deg, #ffca28, #ffb300);
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
        .password-requirements, .validation-message {
            font-size: 0.9em;
            display: none;
            margin-top: 5px;
            transition: color 0.3s ease;
        }
        .password-requirements.invalid, .validation-message.invalid {
            color: #e63946;
            display: block;
        }
        .password-requirements.valid, .validation-message.valid {
            color: #28a745;
            display: block;
        }
        .alert-success, .alert-danger {
            background: rgba(255, 255, 255, 0.95);
            color: #2d3436;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
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
                        <li class="nav-item"><a class="nav-link" href="../Dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#schoolsMenu"><i class="fas fa-school"></i> Schools</a>
                            <div id="schoolsMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="../schools/add_school.php">Add School</a></li>
                                    <li class="nav-item"><a class="nav-link" href="../schools/manage_schools.php">Manage Schools</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="#" data-toggle="collapse" data-target="#usersMenu"><i class="fas fa-users"></i> Users</a>
                            <div id="usersMenu" class="collapse show">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link active" href="add_user.php">Add User</a></li>
                                    <li class="nav-item"><a class="nav-link" href="manage_users.php">Manage Users</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#subcountiesMenu"><i class="fas fa-map-marker-alt"></i> Subcounties</a>
                            <div id="subcountiesMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="../subcounties/add_subcounty.php">Add Subcounty</a></li>
                                    <li class="nav-item"><a class="nav-link" href="../subcounties/manage_subcounties.php">Manage Subcounties</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#targetsMenu"><i class="fas fa-bullseye"></i> Daily Targets</a>
                            <div id="targetsMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="../targets/set_targets.php">Set Targets</a></li>
                                    <li class="nav-item"><a class="nav-link" href="../targets/manage_targets.php">Manage Targets</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#gradingMenu"><i class="fas fa-table"></i> Grading</a>
                            <div id="gradingMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="../grading/manage_grading.php">Manage Grading Table</a></li>
                                    <li class="nav-item"><a class="nav-link" href="../grading/manage_grades.php">Manage Grade Table</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="../analysis/run_analysis.php"><i class="fas fa-chart-bar"></i> Run Analysis</a></li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#resultsMenu"><i class="fas fa-file-alt"></i> Results</a>
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
                        <li class="nav-item"><a class="nav-link" href="../chat.php"><i class="fas fa-comments"></i> Team Chat</a></li>
                        <li class="nav-item"><a class="nav-link" href="../settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                        <li class="nav-item"><a class="nav-link" href="../audit_logs.php"><i class="fas fa-history"></i> Audit Logs</a></li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content col-lg-10">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <h1>Add User</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent">
                            <li class="breadcrumb-item"><a href="../Dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item">Users</li>
                            <li class="breadcrumb-item active" aria-current="page">Add User</li>
                        </ol>
                    </nav>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h2>Add User</h2>
                        <form id="add-user-form" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div id="user-fields" class="user-fields">
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label for="username">Username</label>
                                        <input type="text" class="form-control" name="username[]" required onblur="validateField(this, 'username')">
                                        <small class="validation-message"></small>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="email">Email</label>
                                        <input type="email" class="form-control" name="email[]" required onblur="validateField(this, 'email')">
                                        <small class="validation-message"></small>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="password">Password</label>
                                        <input type="password" class="form-control" name="password[]" required onkeyup="validatePassword(this)">
                                        <small class="password-requirements">Must be 8+ chars, include a letter, number, and special char.</small>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="role">Role</label>
                                        <select name="role[]" class="form-control" required>
                                            <option value="System Admin">System Admin</option>
                                            <option value="Data Entrant" selected>Data Entrant</option>
                                            <option value="Exams Admin">Exams Admin</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="status">Status</label>
                                        <select name="status[]" class="form-control" required>
                                            <option value="Active" selected>Active</option>
                                            <option value="Invalid">Invalid</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary mb-3" id="add-user-button">Add Another User</button>
                            <button type="submit" class="btn btn-primary">Add User(s)</button>
                        </form>
                        <div id="alert-container" class="mt-3"></div>
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

        document.getElementById('add-user-button').addEventListener('click', function() {
            const userFields = document.getElementById('user-fields');
            const newFields = userFields.firstElementChild.cloneNode(true);
            newFields.querySelectorAll('input').forEach(input => input.value = '');
            newFields.querySelector('select[name="role[]"]').value = 'Data Entrant';
            newFields.querySelector('select[name="status[]"]').value = 'Active';
            newFields.querySelectorAll('.password-requirements, .validation-message').forEach(el => {
                el.style.display = 'none';
                el.textContent = '';
            });
            userFields.appendChild(newFields);
        });

        $('#add-user-form').submit(function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            $.ajax({
                url: 'add_user.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            $('#alert-container').html('<div class="alert alert-success">' + result.message + '</div>');
                            $('#add-user-form')[0].reset();
                            $('#user-fields').html($('#user-fields .form-row').first().clone());
                            $('.password-requirements, .validation-message').hide();
                        } else {
                            $('#alert-container').html('<div class="alert alert-danger">' + result.error.replace('|', '<br>') + '</div>');
                        }
                    } catch (e) {
                        $('#alert-container').html('<div class="alert alert-danger">Failed to add user(s)</div>');
                    }
                },
                error: function() {
                    $('#alert-container').html('<div class="alert alert-danger">Error adding user(s)</div>');
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>