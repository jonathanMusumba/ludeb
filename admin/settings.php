<?php
session_start();
require_once 'db_connect.php';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header("Location: ../../login.php");
    exit();
}

// Log page access
$user_id = $_SESSION['user_id'];
$conn->query("CALL log_action('Settings Access', $user_id, 'System Admin accessed settings page')");

// Fetch current settings
$stmt = $conn->query("SELECT s.id, s.board_name, s.exam_year_id, s.logo, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$current_settings = $stmt->fetch_assoc();
$board_name = $current_settings['board_name'] ?? 'Luuka Examination Board';
$exam_year = $current_settings['exam_year'] ?? date('Y');
$exam_year_id = $current_settings['exam_year_id'] ?? null;
$logo = $current_settings['logo'] ?? null;

// Fetch available exam years
$stmt = $conn->query("SELECT id, exam_year FROM exam_years ORDER BY exam_year DESC");
$exam_years = $stmt->fetch_all(MYSQLI_ASSOC);

$username = htmlspecialchars($_SESSION['username']);
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Results Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #f59e0b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --sidebar-width: 280px;
            --border-radius: 12px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #1f2937;
            margin: 0;
        }

        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
        }

        .sidebar-brand {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: #6b7280;
            text-decoration: none;
            border-radius: 0 25px 25px 0;
            margin-right: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--primary-color);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
            transform: translateX(8px);
        }

        .nav-link:hover::before,
        .nav-link.active::before {
            transform: scaleY(1);
        }

        .nav-link i {
            margin-right: 0.75rem;
            font-size: 1.125rem;
            width: 20px;
            text-align: center;
        }

        .collapse-menu .nav-link {
            padding: 0.5rem 1.5rem;
            font-size: 0.875rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Top Bar */
        .topbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .topbar-left {
            display: flex;
            align-items: center;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--dark-color);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: var(--light-color);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .topbar-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--dark-color);
        }

        .topbar-info span {
            background: rgba(var(--primary-color), 0.1);
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            border: 1px solid rgba(var(--primary-color), 0.2);
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            color: white;
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
            background: rgba(255, 255, 255, 0.05);
            min-height: calc(100vh - 80px);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 0.5rem 1rem;
            margin: 0;
        }

        .breadcrumb-item {
            color: rgba(255, 255, 255, 0.8);
        }

        .breadcrumb-item.active {
            color: white;
        }

        /* Card Styles */
        .dashboard-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(245, 245, 245, 0.85));
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .dashboard-card h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }

        /* Form Styles */
        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            background: white;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 5px rgba(79, 70, 229, 0.3);
            outline: none;
        }

        .form-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }

        .form-control-file {
            background: none;
            border: none;
            padding: 0.5rem 0;
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

        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
            color: white;
        }

        /* Alerts */
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

        /* Logo Preview */
        .logo-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            margin-top: 1rem;
            border: 1px solid #e5e7eb;
            display: none;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar-toggle {
                display: block;
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .topbar-info {
                display: none;
            }

            .topbar-info.mobile {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }

            .content-area {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3 class="sidebar-brand">
                    <i class="fas fa-graduation-cap"></i>
                    RMS Dashboard
                </h3>
            </div>
            <div class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#schoolsMenu">
                            <i class="fas fa-school"></i>
                            Schools
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="schoolsMenu" class="collapse">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link" href="schools/add_school.php">
                                        <i class="fas fa-plus"></i>
                                        Add School
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="schools/manage_schools.php">
                                        <i class="fas fa-list"></i>
                                        Manage Schools
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#usersMenu">
                            <i class="fas fa-users"></i>
                            Users
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="usersMenu" class="collapse">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link" href="users/add_user.php">
                                        <i class="fas fa-plus"></i>
                                        Add User
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="users/manage_users.php">
                                        <i class="fas fa-list"></i>
                                        Manage Users
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#subcountiesMenu">
                            <i class="fas fa-map-marker-alt"></i>
                            Subcounties
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="subcountiesMenu" class="collapse">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link" href="subcounties/add_subcounty.php">
                                        <i class="fas fa-plus"></i>
                                        Add Subcounty
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="subcounties/manage_subcounties.php">
                                        <i class="fas fa-list"></i>
                                        Manage Subcounties
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#targetsMenu">
                            <i class="fas fa-bullseye"></i>
                            Daily Targets
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="targetsMenu" class="collapse">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link" href="targets/set_targets.php">
                                        <i class="fas fa-plus"></i>
                                        Set Targets
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="targets/manage_targets.php">
                                        <i class="fas fa-list"></i>
                                        Manage Targets
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#gradingMenu">
                            <i class="fas fa-table"></i>
                            Grading
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="gradingMenu" class="collapse">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link" href="grading/manage_grading.php">
                                        <i class="fas fa-list"></i>
                                        Manage Grading Table
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="grading/manage_grades.php">
                                        <i class="fas fa-list"></i>
                                        Manage Grade Table
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analysis/run_analysis.php">
                            <i class="fas fa-chart-bar"></i>
                            Run Analysis
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#resultsMenu">
                            <i class="fas fa-file-alt"></i>
                            Results
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="resultsMenu" class="collapse">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link" href="results/view_results.php">
                                        <i class="fas fa-eye"></i>
                                        View Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="results/audit_results.php">
                                        <i class="fas fa-history"></i>
                                        Audit Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="results/generate_school_results.php">
                                        <i class="fas fa-school"></i>
                                        School Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="results/generate_subcounty_results.php">
                                        <i class="fas fa-map-marker-alt"></i>
                                        Subcounty Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="results/generate_general_results.php">
                                        <i class="fas fa-file-alt"></i>
                                        General Results
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="chat.php">
                            <i class="fas fa-comments"></i>
                            Team Chat
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="settings.php">
                            <i class="fas fa-cog"></i>
                            Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="audit_logs.php">
                            <i class="fas fa-history"></i>
                            Audit Logs
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="topbar-left">
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                <div class="topbar-right">
                    <div class="topbar-info">
                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($board_name); ?></span>
                        <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($exam_year); ?></span>
                        <span><i class="fas fa-user"></i> <?php echo $username; ?></span>
                    </div>
                    <a href="../logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-cog"></i>
                        Settings
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Settings</li>
                        </ol>
                    </nav>
                </div>

                <!-- Alerts -->
                <div class="alerts" id="alertContainer">
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert-enhanced alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert-enhanced alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($_GET['success']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Settings Form -->
                <div class="dashboard-card">
                    <h2>System Settings</h2>
                    <form id="settingsForm" action="update_settings.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="settings_id" value="<?php echo htmlspecialchars($current_settings['id'] ?? ''); ?>">
                        <div class="mb-3">
                            <label for="board_name" class="form-label">Board Name</label>
                            <input type="text" class="form-control" id="board_name" name="board_name" value="<?php echo htmlspecialchars($board_name); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="exam_year_id" class="form-label">Exam Year</label>
                            <select class="form-control" id="exam_year_id" name="exam_year_id" required>
                                <option value="">Select Exam Year</option>
                                <?php foreach ($exam_years as $year): ?>
                                    <option value="<?php echo $year['id']; ?>" <?php echo $year['id'] == $exam_year_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year['exam_year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="logo" class="form-label">Board Logo</label>
                            <input type="file" class="form-control-file" id="logo" name="logo" accept="image/*">
                            <?php if ($logo): ?>
                                <img src="<?php echo htmlspecialchars($logo); ?>" alt="Current Logo" class="logo-preview mt-2" style="display: block;">
                            <?php endif; ?>
                            <img id="logoPreview" class="logo-preview" alt="Logo Preview">
                            <small class="form-text text-muted">Recommended size: 200x200px, Max size: 2MB</small>
                        </div>
                        <button type="submit" class="btn-enhanced">
                            <i class="fas fa-save"></i> Update Settings
                        </button>
                        <a href="settings.php" class="btn-enhanced btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Sidebar toggle functionality
            $('#sidebarToggle').click(function() {
                const sidebar = $('#sidebar');
                const mainContent = $('#mainContent');
                sidebar.toggleClass('show');
                mainContent.toggleClass('expanded');
            });

            // Close sidebar when clicking outside on mobile
            $(document).on('click', function(event) {
                if (window.innerWidth <= 768 && 
                    !$('#sidebar').get(0).contains(event.target) && 
                    !$('#sidebarToggle').get(0).contains(event.target)) {
                    $('#sidebar').removeClass('show');
                    $('#mainContent').removeClass('expanded');
                }
            });

            // Logo preview
            $('#logo').on('change', function(event) {
                const file = event.target.files[0];
                const preview = $('#logoPreview');
                if (file) {
                    if (file.size > 2 * 1024 * 1024) {
                        showNotification('File size exceeds 2MB limit.', 'error');
                        $(this).val('');
                        preview.hide();
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.attr('src', e.target.result).show();
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.hide();
                }
            });

            // Form validation
            $('#settingsForm').on('submit', function(e) {
                const boardName = $('#board_name').val().trim();
                const examYear = $('#exam_year_id').val();
                if (!boardName) {
                    e.preventDefault();
                    showNotification('Board name is required.', 'error');
                    return;
                }
                if (!examYear) {
                    e.preventDefault();
                    showNotification('Please select an exam year.', 'error');
                    return;
                }
            });

            // Enhanced notification system
            function showNotification(message, type = 'info') {
                const notification = $('<div>', {
                    class: `notification notification-${type}`,
                    html: `
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
                        ${message}
                    `,
                    css: {
                        position: 'fixed',
                        top: '20px',
                        right: '20px',
                        background: type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6',
                        color: 'white',
                        padding: '1rem 1.5rem',
                        borderRadius: '8px',
                        boxShadow: '0 10px 25px rgba(0, 0, 0, 0.1)',
                        zIndex: 9999,
                        transform: 'translateX(100%)',
                        transition: 'transform 0.3s ease',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '0.5rem',
                        maxWidth: '300px'
                    }
                });
                $('body').append(notification);
                setTimeout(() => {
                    notification.css('transform', 'translateX(0)');
                }, 100);
                setTimeout(() => {
                    notification.css('transform', 'translateX(100%)');
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 3000);
            }

            // Apply fade-in animation to card
            $('.dashboard-card').css('animation', 'fadeIn 0.3s ease');

            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    const sidebar = $('#sidebar');
                    const mainContent = $('#mainContent');
                    if (sidebar.hasClass('show')) {
                        sidebar.removeClass('show');
                        mainContent.removeClass('expanded');
                    }
                }
            });
        });
    </script>
</body>
</html>