<?php
ob_start(); // Start output buffering

// Define base and root URLs dynamically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// Debug session
error_log("Session ID: " . session_id() . ", User ID: " . ($_SESSION['user_id'] ?? 'Not set') . ", Role: " . ($_SESSION['role'] ?? 'Not set'), 3, 'C:\xampp\htdocs\ludeb\debug.log');

// Restrict access to authenticated users
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: " . $root_url . "login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');

// Log page access
$conn->query("CALL log_action('Page Access', $user_id, 'Accessed page: " . ($page_title ?? 'Unknown') . "')");

// Fetch schools for filter
$schools = $conn->query("SELECT id, school_name AS name FROM schools ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Results Management System'); ?> - RMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/export-data.js"></script>
    <base href="<?php echo $base_url; ?>">
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

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-content.expanded {
            margin-left: 0;
        }

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

        .dashboard-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(245, 245, 245, 0.85));
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .dashboard-card .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }

        .dashboard-card .card-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .dashboard-card i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        #progressiveGraph, #divisionChart, #subjectChart, #topSchoolsChart, #targetProgressChart {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .table-enhanced {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .table-enhanced th {
            background: linear-gradient(135deg, #f8fafc, #e5e7eb);
            padding: 0.5rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--dark-color);
            border-bottom: 1px solid #e5e7eb;
        }

        .table-enhanced td {
            padding: 0.5rem;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.3s ease;
        }

        .table-enhanced tr:hover td {
            background: rgba(79, 70, 229, 0.05);
        }

        .btn-enhanced.btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .btn-enhanced.btn-sm i {
            font-size: 0.9rem;
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

        .alert-warning {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: white;
        }

        .filter-container {
            margin-bottom: 1.5rem;
        }

        .filter-container label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
            margin-right: 0.5rem;
        }

        .filter-container select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            background: white;
            min-width: 160px;
        }

        .filter-container select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 5px rgba(79, 70, 229, 0.3);
            outline: none;
        }

        .section-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
            margin: 2rem 0;
            opacity: 0.6;
        }

        .loading-spinner {
            font-size: 1.5rem;
            color: var(--primary-color);
            animation: spin 1s linear infinite;
            display: block;
            margin: 180px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .tooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.875rem;
            z-index: 1002;
            display: none;
        }

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

            .dashboard-card {
                margin-bottom: 1rem;
            }

            .filter-container select {
                width: 100%;
            }
        }

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
                        <a class="nav-link <?php echo ($page_title === 'Dashboard') ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>
                    <?php if (in_array($role, ['System Admin', 'Examination Administrator'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Add School' || $page_title === 'Manage Schools' || $page_title === 'Import Schools') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#schoolsMenu">
                            <i class="fas fa-school"></i>
                            Schools
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="schoolsMenu" class="collapse <?php echo ($page_title === 'Add School' || $page_title === 'Manage Schools' || $page_title === 'Import Schools') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Add School') ? 'active' : ''; ?>" href="schools/add_school.php">
                                        <i class="fas fa-plus"></i>
                                        Add School
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Manage Schools') ? 'active' : ''; ?>" href="schools/manage_schools.php">
                                        <i class="fas fa-list"></i>
                                        Manage Schools
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Import Schools') ? 'active' : ''; ?>" href="schools/import_schools.php">
                                        <i class="fas fa-file-import"></i>
                                        Import Schools
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Add User' || $page_title === 'Manage Users') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#usersMenu">
                            <i class="fas fa-users"></i>
                            Users
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="usersMenu" class="collapse <?php echo ($page_title === 'Add User' || $page_title === 'Manage Users') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Add User') ? 'active' : ''; ?>" href="users/add_user.php">
                                        <i class="fas fa-plus"></i>
                                        Add User
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Manage Users') ? 'active' : ''; ?>" href="users/manage_users.php">
                                        <i class="fas fa-list"></i>
                                        Manage Users
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Add Subcounty' || $page_title === 'Manage Subcounties') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#subcountiesMenu">
                            <i class="fas fa-map-marker-alt"></i>
                            Subcounties
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="subcountiesMenu" class="collapse <?php echo ($page_title === 'Add Subcounty' || $page_title === 'Manage Subcounties') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Add Subcounty') ? 'active' : ''; ?>" href="subcounties/add_subcounty.php">
                                        <i class="fas fa-plus"></i>
                                        Add Subcounty
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Manage Subcounties') ? 'active' : ''; ?>" href="subcounties/manage_subcounties.php">
                                        <i class="fas fa-list"></i>
                                        Manage Subcounties
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Set Targets' || $page_title === 'Manage Targets') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#targetsMenu">
                            <i class="fas fa-bullseye"></i>
                            Daily Targets
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="targetsMenu" class="collapse <?php echo ($page_title === 'Set Targets' || $page_title === 'Manage Targets') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Set Targets') ? 'active' : ''; ?>" href="targets/set_targets.php">
                                        <i class="fas fa-plus"></i>
                                        Set Targets
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Manage Targets') ? 'active' : ''; ?>" href="targets/manage_targets.php">
                                        <i class="fas fa-list"></i>
                                        Manage Targets
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Manage Grading' || $page_title === 'Manage Grades') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#gradingMenu">
                            <i class="fas fa-table"></i>
                            Grading
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="gradingMenu" class="collapse <?php echo ($page_title === 'Manage Grading' || $page_title === 'Manage Grades') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Manage Grading') ? 'active' : ''; ?>" href="grading/manage_grading.php">
                                        <i class="fas fa-list"></i>
                                        Manage Grading Table
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Manage Grades') ? 'active' : ''; ?>" href="grading/manage_grades.php">
                                        <i class="fas fa-list"></i>
                                        Manage Grade Table
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Run Analysis') ? 'active' : ''; ?>" href="analysis/run_analysis.php">
                            <i class="fas fa-chart-bar"></i>
                            Run Analysis
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'View Results' || $page_title === 'Audit Results' || $page_title === 'School Results' || $page_title === 'Subcounty Results' || $page_title === 'General Results' || $page_title === 'Update Results' || $page_title === 'Process Results' || $page_title === 'Edit Results' || $page_title === 'Print Results' || $page_title === 'Print Edited Results') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#resultsMenu">
                            <i class="fas fa-file-alt"></i>
                            Results
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="resultsMenu" class="collapse <?php echo ($page_title === 'View Results' || $page_title === 'Audit Results' || $page_title === 'School Results' || $page_title === 'Subcounty Results' || $page_title === 'General Results' || $page_title === 'Update Results' || $page_title === 'Process Results' || $page_title === 'Edit Results' || $page_title === 'Print Results' || $page_title === 'Print Edited Results') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'View Results') ? 'active' : ''; ?>" href="results/view_results.php">
                                        <i class="fas fa-eye"></i>
                                        View Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Audit Results') ? 'active' : ''; ?>" href="results/audit_results.php">
                                        <i class="fas fa-history"></i>
                                        Audit Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'School Results') ? 'active' : ''; ?>" href="results/generate_school_results.php">
                                        <i class="fas fa-school"></i>
                                        School Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Subcounty Results') ? 'active' : ''; ?>" href="results/generate_subcounty_results.php">
                                        <i class="fas fa-map-marker-alt"></i>
                                        Subcounty Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'General Results') ? 'active' : ''; ?>" href="results/generate_general_results.php">
                                        <i class="fas fa-file-alt"></i>
                                        General Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Update Results') ? 'active' : ''; ?>" href="results/update_results.php">
                                        <i class="fas fa-sync-alt"></i>
                                        Update Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Process Results') ? 'active' : ''; ?>" href="results/process_results.php">
                                        <i class="fas fa-cogs"></i>
                                        Process Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Edit Results') ? 'active' : ''; ?>" href="results/edit_results.php">
                                        <i class="fas fa-edit"></i>
                                        Edit Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Print Results') ? 'active' : ''; ?>" href="results/print_results.php">
                                        <i class="fas fa-print"></i>
                                        Print Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Print Edited Results') ? 'active' : ''; ?>" href="results/print_edited_results.php">
                                        <i class="fas fa-print"></i>
                                        Print Edited Results
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Generate Reports' || $page_title === 'View Reports') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#reportingMenu">
                            <i class="fas fa-chart-pie"></i>
                            Reporting
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="reportingMenu" class="collapse <?php echo ($page_title === 'Generate Reports' || $page_title === 'View Reports') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Generate Reports') ? 'active' : ''; ?>" href="reporting/generate_reports.php">
                                        <i class="fas fa-file-export"></i>
                                        Generate Reports
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'View Reports') ? 'active' : ''; ?>" href="reporting/view_reports.php">
                                        <i class="fas fa-eye"></i>
                                        View Reports
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <!-- New Payments Menu -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Make Payment' || $page_title === 'Payment History' || $page_title === 'Payment Reports') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#paymentsMenu">
                            <i class="fas fa-money-bill-wave"></i>
                            Payments
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="paymentsMenu" class="collapse <?php echo ($page_title === 'Make Payment' || $page_title === 'Payment History' || $page_title === 'Payment Reports') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Make Payment') ? 'active' : ''; ?>" href="payments/make_payment.php">
                                        <i class="fas fa-plus"></i>
                                        Make Payment
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Payment History') ? 'active' : ''; ?>" href="payments/history.php">
                                        <i class="fas fa-history"></i>
                                        Payment History
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Payment Reports') ? 'active' : ''; ?>" href="payments/reports.php">
                                        <i class="fas fa-chart-bar"></i>
                                        Payment Reports
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <?php if (in_array($role, ['System Admin', 'Examination Administrator'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Manage Backups' || $page_title === 'Trigger Backup' || $page_title === 'Restore Database') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#backupMenu">
                            <i class="fas fa-database"></i>
                            Backup & Restore
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="backupMenu" class="collapse <?php echo ($page_title === 'Manage Backups' || $page_title === 'Trigger Backup' || $page_title === 'Restore Database') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Trigger Backup') ? 'active' : ''; ?>" href="backups/trigger_backup.php">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        Trigger Backup
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Manage Backups') ? 'active' : ''; ?>" href="backups/manage_backups.php">
                                        <i class="fas fa-list"></i>
                                        Manage Backups
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Restore Database') ? 'active' : ''; ?>" href="backups/restore_database.php">
                                        <i class="fas fa-undo"></i>
                                        Restore Database
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <?php endif; ?>
                    <?php if ($role === 'Data Entrant'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Enter Marks') ? 'active' : ''; ?>" href="data_entry/enter_marks.php">
                            <i class="fas fa-edit"></i>
                            Enter Marks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'View Marks') ? 'active' : ''; ?>" href="data_entry/view_marks.php">
                            <i class="fas fa-eye"></i>
                            View Marks
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Team Chat') ? 'active' : ''; ?>" href="chat.php">
                            <i class="fas fa-comments"></i>
                            Team Chat
                        </a>
                    </li>
                    <?php if (in_array($role, ['System Admin', 'Examination Administrator'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Settings') ? 'active' : ''; ?>" href="settings.php">
                            <i class="fas fa-cog"></i>
                            Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Audit Logs') ? 'active' : ''; ?>" href="audit_logs.php">
                            <i class="fas fa-history"></i>
                            Audit Logs
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Monitoring') ? 'active' : ''; ?>" href="monitoring.php">
                            <i class="fas fa-eye"></i>
                            Monitoring
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Create Resource' || $page_title === 'Manage Resources' || $page_title === 'Manage Payments') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#resourcesMenu">
                            <i class="fas fa-box-open"></i>
                            Managing Resources
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="resourcesMenu" class="collapse <?php echo ($page_title === 'Create Resource' || $page_title === 'Manage Resources' || $page_title === 'Manage Payments') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Create Resource') ? 'active' : ''; ?>" href="resources/create.php">
                                        <i class="fas fa-plus"></i>
                                        Create Resource
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Manage Resources') ? 'active' : ''; ?>" href="resources/manage.php">
                                        <i class="fas fa-list"></i>
                                        Manage Resources
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Manage Payments') ? 'active' : ''; ?>" href="resources/payment.php">
                                        <i class="fas fa-money-bill-wave"></i>
                                        Manage Payments
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Create Announcement' || $page_title === 'Manage Announcements') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#announcementsMenu">
                            <i class="fas fa-bullhorn"></i>
                            Managing Announcements
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="announcementsMenu" class="collapse <?php echo ($page_title === 'Create Announcement' || $page_title === 'Manage Announcements') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Create Announcement') ? 'active' : ''; ?>" href="announcements/create.php">
                                        <i class="fas fa-plus"></i>
                                        Create Announcement
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Manage Announcements') ? 'active' : ''; ?>" href="announcements/manage.php">
                                        <i class="fas fa-list"></i>
                                        Manage Announcements
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Create Feedback' || $page_title === 'Manage Feedbacks') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#feedbacksMenu">
                            <i class="fas fa-comments"></i>
                            Managing Feedbacks
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="feedbacksMenu" class="collapse <?php echo ($page_title === 'Create Feedback' || $page_title === 'Manage Feedbacks') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Create Feedback') ? 'active' : ''; ?>" href="feedbacks/create.php">
                                        <i class="fas fa-plus"></i>
                                        Create Feedback
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Manage Feedbacks') ? 'active' : ''; ?>" href="feedbacks/manage.php">
                                        <i class="fas fa-list"></i>
                                        Manage Feedbacks
                                    </a>
                                </li>
                            </ul>
                        </div>
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
                    <a href="<?php echo $root_url; ?>logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <?php echo $content ?? '<p>No content defined for this page.</p>'; ?>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Sidebar toggle functionality
            $('#sidebarToggle').on('click', function() {
                console.log('Sidebar toggle clicked');
                const sidebar = $('#sidebar');
                const mainContent = $('#mainContent');
                sidebar.toggleClass('show');
                mainContent.toggleClass('expanded');
            });

            // Log collapse menu clicks
            $('.nav-link[data-bs-toggle="collapse"]').on('click', function() {
                console.log('Collapse menu toggled:', $(this).data('bs-target'));
            });

            // Close sidebar when clicking outside on mobile
            $(document).on('click', function(event) {
                if (window.innerWidth <= 768 && 
                    !$('#sidebar').get(0).contains(event.target) && 
                    !$('#sidebarToggle').get(0).contains(event.target)) {
                    console.log('Clicked outside sidebar on mobile');
                    $('#sidebar').removeClass('show');
                    $('#mainContent').removeClass('expanded');
                }
            });

            // Create tooltip element
            const tooltip = $('<div>', { class: 'tooltip' });
            $('body').append(tooltip);

            // Tooltip functionality
            function showTooltip(element, text) {
                element.on('mouseover', function(e) {
                    tooltip.text(text).css({
                        display: 'block',
                        left: (e.pageX + 10) + 'px',
                        top: (e.pageY + 10) + 'px'
                    });
                }).on('mousemove', function(e) {
                    tooltip.css({
                        left: (e.pageX + 10) + 'px',
                        top: (e.pageY + 10) + 'px'
                    });
                }).on('mouseout', function() {
                    tooltip.hide();
                });
            }

            // Apply tooltips to nav links
            $('.nav-link').each(function() {
                const text = $(this).text().trim();
                showTooltip($(this), text);
            });

            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    const sidebar = $('#sidebar');
                    const mainContent = $('#mainContent');
                    if (sidebar.hasClass('show')) {
                        console.log('Escape key pressed, closing sidebar');
                        sidebar.removeClass('show');
                        mainContent.removeClass('expanded');
                    }
                }
            });

            // Notification system
            window.showNotification = function(message, type = 'info') {
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
            };
        });
    </script>
</body>
</html>
<?php
$conn->close();
ob_end_flush();
?>