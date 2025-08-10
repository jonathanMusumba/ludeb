<?php
session_start();
require_once 'db_connect.php';

// Restrict to authenticated users
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Set page title
$page_title = 'Dashboard';

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year, e.id AS exam_year_id
                      FROM settings s
                      JOIN exam_years e ON s.exam_year_id = e.id
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');
$exam_year_id = $row['exam_year_id'] ?? 0;

// Log dashboard access
$conn->query("CALL log_action('Dashboard Access', $user_id, 'Accessed dashboard')");

// Fetch schools for filter
$schools = $conn->query("SELECT id, school_name AS name FROM schools ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Define template variables
$template_vars = [
    'board_name' => $board_name,
    'exam_year' => $exam_year,
    'username' => $username,
    'role' => $role
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Results Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/export-data.js"></script>
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

        /* Dashboard Cards */
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

        /* Charts */
        #progressiveGraph, #divisionChart, #subjectChart, #topSchoolsChart, #targetProgressChart, #subjectTrendChart {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        /* Tables */
        .table-enhanced {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .table-enhanced th {
            background: linear-gradient(135deg, #f8fafc, #e5e7eb);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 2px solid #e5e7eb;
        }

        .table-enhanced td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.3s ease;
        }

        .table-enhanced tr:hover td {
            background: rgba(79, 70, 229, 0.05);
        }

        /* Buttons */
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

        .alert-warning {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: white;
        }

        /* Filter Container */
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

        /* Section Divider */
        .section-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
            margin: 2rem 0;
            opacity: 0.6;
        }

        /* Loading States */
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

        /* Tooltip */
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

            .dashboard-card {
                margin-bottom: 1rem;
            }

            .filter-container select {
                width: 100%;
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
                        <a class="nav-link <?php echo ($page_title === 'Data Entrants Monitoring' || $page_title === 'Schools Monitoring') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#monitoringMenu">
                            <i class="fas fa-eye"></i>
                            Monitoring
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="monitoringMenu" class="collapse <?php echo ($page_title === 'Data Entrants Monitoring' || $page_title === 'Schools Monitoring') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Data Entrants Monitoring') ? 'active' : ''; ?>" href="monitoring/data_entrants.php">
                                        <i class="fas fa-user-edit"></i>
                                        Data Entrants Monitoring
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Schools Monitoring') ? 'active' : ''; ?>" href="monitoring/schools.php">
                                        <i class="fas fa-school"></i>
                                        Schools Monitoring
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Create Resource' || $page_title === 'Update Resource' || $page_title === 'Delete Resource' || $page_title === 'Change Resource Type') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#resourcesMenu">
                            <i class="fas fa-box-open"></i>
                            Managing Resources
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="resourcesMenu" class="collapse <?php echo ($page_title === 'Create Resource' || $page_title === 'Update Resource' || $page_title === 'Delete Resource' || $page_title === 'Change Resource Type') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Create Resource') ? 'active' : ''; ?>" href="resources/create.php">
                                        <i class="fas fa-plus"></i>
                                        Create Resource
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Update Resource') ? 'active' : ''; ?>" href="resources/update.php">
                                        <i class="fas fa-edit"></i>
                                        Update Resource
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Delete Resource') ? 'active' : ''; ?>" href="resources/delete.php">
                                        <i class="fas fa-trash"></i>
                                        Delete Resource
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Change Resource Type') ? 'active' : ''; ?>" href="resources/change_type.php">
                                        <i class="fas fa-sync"></i>
                                        Change Resource Type
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page_title === 'Create Announcement' || $page_title === 'Update Announcement' || $page_title === 'Delete Announcement') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#announcementsMenu">
                            <i class="fas fa-bullhorn"></i>
                            Managing Announcements
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="announcementsMenu" class="collapse <?php echo ($page_title === 'Create Announcement' || $page_title === 'Update Announcement' || $page_title === 'Delete Announcement') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Create Announcement') ? 'active' : ''; ?>" href="announcements/create.php">
                                        <i class="fas fa-plus"></i>
                                        Create Announcement
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Update Announcement') ? 'active' : ''; ?>" href="announcements/update.php">
                                        <i class="fas fa-edit"></i>
                                        Update Announcement
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($page_title === 'Delete Announcement') ? 'active' : ''; ?>" href="announcements/delete.php">
                                        <i class="fas fa-trash"></i>
                                        Delete Announcement
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
                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($template_vars['board_name']); ?></span>
                        <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($template_vars['exam_year']); ?></span>
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($template_vars['username']); ?></span>
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
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                        </ol>
                    </nav>
                </div>

                <!-- Alerts -->
                <div class="alerts" id="alertContainer"></div>

                <!-- Dashboard Cards -->
                <div class="row mb-5">
                    <div class="col-md-2 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <i class="fas fa-map-marker-alt"></i>
                                <h5 class="card-title">Total Subcounties</h5>
                                <p class="card-text" id="totalSubcounties"><i class="fas fa-spinner loading-spinner"></i></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <i class="fas fa-school"></i>
                                <h5 class="card-title">Total Schools</h5>
                                <p class="card-text" id="totalSchools"><i class="fas fa-spinner loading-spinner"></i></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <i class="fas fa-building"></i>
                                <h5 class="card-title">Private Schools</h5>
                                <p class="card-text" id="privateSchools"><i class="fas fa-spinner loading-spinner"></i></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <i class="fas fa-building"></i>
                                <h5 class="card-title">UPE Schools</h5>
                                <p class="card-text" id="governmentSchools"><i class="fas fa-spinner loading-spinner"></i></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <i class="fas fa-check-circle"></i>
                                <h5 class="card-title">Declared Schools</h5>
                                <p class="card-text" id="declaredSchools"><i class="fas fa-spinner loading-spinner"></i></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <i class="fas fa-hourglass-half"></i>
                                <h5 class="card-title">Partially Declared</h5>
                                <p class="card-text" id="partiallyDeclaredSchools"><i class="fas fa-spinner loading-spinner"></i></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <i class="fas fa-times-circle"></i>
                                <h5 class="card-title">Not Declared</h5>
                                <p class="card-text" id="notDeclaredSchools"><i class="fas fa-spinner loading-spinner"></i></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <i class="fas fa-users"></i>
                                <h5 class="card-title">Total Candidates</h5>
                                <p class="card-text" id="totalCandidates"><i class="fas fa-spinner loading-spinner"></i></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <i class="fas fa-female"></i>
                                <h5 class="card-title">Female Candidates</h5>
                                <p class="card-text" id="femaleCandidates"><i class="fas fa-spinner loading-spinner"></i></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <i class="fas fa-male"></i>
                                <h5 class="card-title">Male Candidates</h5>
                                <p class="card-text" id="maleCandidates"><i class="fas fa-spinner loading-spinner"></i></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <i class="fas fa-graduation-cap"></i>
                                <h5 class="card-title">Pass Rate</h5>
                                <p class="card-text" id="passRate"><i class="fas fa-spinner loading-spinner"></i></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <i class="fas fa-user-times"></i>
                                <h5 class="card-title">Absent Candidates</h5>
                                <p class="card-text" id="absentCandidates"><i class="fas fa-spinner loading-spinner"></i></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <!-- Target Progress -->
                <div class="row mb-5">
                    <div class="col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-bullseye"></i> Daily Target Progress (Today)</h5>
                                <div class="progress">
                                    <div id="targetProgressBar" class="progress-bar bg-primary" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                </div>
                                <p class="mt-2">Target: <span id="targetEntries">0</span> | Actual: <span id="actualEntries">0</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-chart-bar"></i> Target Progress (Last 7 Days)</h5>
                                <div id="targetProgressChart"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <!-- School Results Status Table -->
                <div class="row mb-5">
                    <div class="col-md-12 mb-4">
                        <h2 class="page-title">School Results Status</h2>
                        <div class="filter-container">
                            <label for="resultsStatusFilter">Filter by Status:</label>
                            <select id="resultsStatusFilter" class="form-control d-inline-block w-auto">
                                <option value="">All Statuses</option>
                                <option value="Declared">Declared</option>
                                <option value="Partially Declared">Partially Declared</option>
                                <option value="Not Declared">Not Declared</option>
                            </select>
                            <label for="resultsSchoolFilter" class="ms-3">Filter by School:</label>
                            <select id="resultsSchoolFilter" class="form-control d-inline-block w-auto">
                                <option value="">All Schools</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="dashboard-card">
                            <table class="table-enhanced">
                                <thead>
                                    <tr>
                                        <th>Center Number</th>
                                        <th>School Name</th>
                                        <th>Results Status</th>
                                        <th>Total Candidates</th>
                                        <th>Candidates with All Marks</th>
                                    </tr>
                                </thead>
                                <tbody id="resultsStatusTable">
                                    <tr><td colspan="5">Loading...</td></tr>
                                </tbody>
                            </table>
                            <button id="exportResultsStatusCsv" class="btn-enhanced mt-2">
                                <i class="fas fa-download"></i> Export to CSV
                            </button>
                        </div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <!-- Charts -->
                <div class="row mb-5">
                    <div class="col-md-6 mb-4"><div id="divisionChart"><i class="fas fa-spinner loading-spinner"></i></div></div>
                    <div class="col-md-6 mb-4"><div id="subjectChart"><i class="fas fa-spinner loading-spinner"></i></div></div>
                </div>

                <div class="section-divider"></div>

                <div class="row mb-5">
                    <div class="col-md-12 mb-4"><div id="topSchoolsChart"><i class="fas fa-spinner loading-spinner"></i></div></div>
                </div>

                <div class="section-divider"></div>

                <div class="row mb-5">
                    <div class="col-md-12 mb-4"><div id="progressiveGraph"><i class="fas fa-spinner loading-spinner"></i></div></div>
                </div>

                <div class="section-divider"></div>

                <!-- Missing Marks -->
                <div class="row mb-5">
                    <div class="col-md-12 mb-4">
                        <h2 class="page-title">Missing Marks (School Level)</h2>
                        <div class="filter-container">
                            <label for="missingSchoolFilter">Filter by School:</label>
                            <select id="missingSchoolFilter" class="form-control d-inline-block w-auto">
                                <option value="">All Schools</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="dashboard-card">
                            <table class="table-enhanced">
                                <thead>
                                    <tr>
                                        <th>Center Number</th>
                                        <th>School Name</th>
                                        <th>Candidates with All Marks</th>
                                        <th>Missing ENG</th>
                                        <th>Missing SST</th>
                                        <th>Missing MTC</th>
                                        <th>Missing SCI</th>
                                    </tr>
                                </thead>
                                <tbody id="missingTable">
                                    <tr><td colspan="7">Loading...</td></tr>
                                </tbody>
                            </table>
                            <button id="exportMissingCsv" class="btn-enhanced mt-2">
                                <i class="fas fa-download"></i> Export to CSV
                            </button>
                        </div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <!-- Data Entrant Monitoring -->
                <div class="row mb-5">
                    <div class="col-md-12 mb-4">
                        <h2 class="page-title">Data Entrant Monitoring</h2>
                        <div class="filter-container">
                            <label for="entrantSchoolFilter">Filter by School:</label>
                            <select id="entrantSchoolFilter" class="form-control d-inline-block w-auto">
                                <option value="">All Schools</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="dashboard-card">
                            <table class="table-enhanced">
                                <thead>
                                    <tr>
                                        <th>Entrant</th>
                                        <th>Schools Handled (Entries/Absentees)</th>
                                        <th>Total Candidates</th>
                                    </tr>
                                </thead>
                                <tbody id="entrantTable">
                                    <tr><td colspan="3">Loading...</td></tr>
                                </tbody>
                            </table>
                            <button id="exportEntrantCsv" class="btn-enhanced mt-2">
                                <i class="fas fa-download"></i> Export to CSV
                            </button>
                        </div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <!-- Division Summary -->
                <div class="row mb-5">
                    <div class="col-md-12 mb-4">
                        <h2 class="page-title">Division Summary</h2>
                        <div class="filter-container">
                            <label for="divisionSchoolFilter">Filter by School:</label>
                            <select id="divisionSchoolFilter" class="form-control d-inline-block w-auto">
                                <option value="">All Schools</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="dashboard-card">
                            <table class="table-enhanced">
                                <thead>
                                    <tr>
                                        <th>School Name</th>
                                        <th>Division 1</th>
                                        <th>Division 2</th>
                                        <th>Division 3</th>
                                        <th>Division 4</th>
                                        <th>Ungraded (U)</th>
                                        <th>Absentees (X)</th>
                                    </tr>
                                </thead>
                                <tbody id="summaryTable">
                                    <tr><td colspan="7">Loading...</td></tr>
                                </tbody>
                            </table>
                            <button id="exportSummaryCsv" class="btn-enhanced mt-2">
                                <i class="fas fa-download"></i> Export to CSV
                            </button>
                        </div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <!-- Subject-wise Performance Trend -->
                <div class="row mb-5">
                    <div class="col-md-12 mb-4">
                        <h2 class="page-title">Subject-wise Performance Trend</h2>
                        <div class="filter-container">
                            <label for="subjectSchoolFilter">Filter by School:</label>
                            <select id="subjectSchoolFilter" class="form-control d-inline-block w-auto">
                                <option value="">All Schools</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="dashboard-card">
                            <div id="subjectTrendChart"><i class="fas fa-spinner loading-spinner"></i></div>
                        </div>
                    </div>
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

            // Create tooltip element
            const tooltip = $('<div>', { class: 'tooltip' });
            $('body').append(tooltip);

            // Notification system
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

            // Show loading spinners
            $('.loading-spinner').show();

            // Fetch dashboard data with timeout
            $.ajax({
                url: 'data/dashboard_data.php',
                method: 'GET',
                data: { exam_year_id: <?php echo $exam_year_id; ?> },
                dataType: 'json',
                timeout: 10000, // 10-second timeout
                success: function(data) {
                    if (data.error) {
                        showNotification('Error loading data: ' + data.error, 'error');
                        $('#missingTable, #entrantTable, #summaryTable, #resultsStatusTable').html('<tr><td colspan="7">Error loading data</td></tr>');
                        $('.loading-spinner').hide();
                        return;
                    }

                    // Update widgets
                    $('#totalSubcounties').text(data.totalSubcounties);
                    $('#totalSchools').text(data.totalSchools);
                    $('#privateSchools').text(data.privateSchools);
                    $('#governmentSchools').text(data.governmentSchools);
                    $('#declaredSchools').text(data.declaredSchools);
                    $('#partiallyDeclaredSchools').text(data.partiallyDeclaredSchools);
                    $('#notDeclaredSchools').text(data.notDeclaredSchools);
                    $('#totalCandidates').text(data.totalCandidates);
                    $('#femaleCandidates').text(data.femaleCandidates);
                    $('#maleCandidates').text(data.maleCandidates);
                    $('#passRate').text(data.passRate + '%');
                    $('#absentCandidates').text(data.absentCandidates);

                    // Add tooltips to cards
                    function showTooltip(element, text) {
                        element.on('mouseover', function(e) {
                            $('.tooltip').text(text).css({
                                display: 'block',
                                left: (e.pageX + 10) + 'px',
                                top: (e.pageY + 10) + 'px'
                            });
                        }).on('mousemove', function(e) {
                            $('.tooltip').css({
                                left: (e.pageX + 10) + 'px',
                                top: (e.pageY + 10) + 'px'
                            });
                        }).on('mouseout', function() {
                            $('.tooltip').hide();
                        });
                    }

                    showTooltip($('#totalSubcounties'), 'Total number of subcounties in the system');
                    showTooltip($('#totalSchools'), 'Total number of schools registered');
                    showTooltip($('#privateSchools'), 'Number of private schools');
                    showTooltip($('#governmentSchools'), 'Number of UPE (government) schools');
                    showTooltip($('#declaredSchools'), 'Schools with more than 50% of candidates having marks in all four subjects');
                    showTooltip($('#partiallyDeclaredSchools'), 'Schools with some candidates having marks in 1 to 3 subjects');
                    showTooltip($('#notDeclaredSchools'), 'Schools with no candidates or no marks recorded');
                    showTooltip($('#totalCandidates'), 'Total number of candidates across all schools');
                    showTooltip($('#femaleCandidates'), 'Total number of female candidates');
                    showTooltip($('#maleCandidates'), 'Total number of male candidates');
                    showTooltip($('#passRate'), 'Percentage of candidates who passed (Division 1 or 2)');
                    showTooltip($('#absentCandidates'), 'Number of candidates marked absent in at least one subject');

                    // Alerts
                    if (data.notDeclaredSchools > 0 || data.partiallyDeclaredSchools > 0) {
                        $('#alertContainer').html(`
                            <div class="alert-enhanced alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                ${data.notDeclaredSchools} school(s) not declared, ${data.partiallyDeclaredSchools} partially declared. Please review missing marks.
                            </div>
                        `);
                    }

                    if (data.absentCandidates > 0) {
                        $('#alertContainer').append(`
                            <div class="alert-enhanced alert-warning">
                                <i class="fas fa-user-times"></i>
                                ${data.absentCandidates} candidate(s) marked as absent. Review for accuracy.
                            </div>
                        `);
                    }

                    // Daily Target Progress
                    $('#targetProgressBar').css('width', data.targetProgress.today.percentage + '%')
                        .attr('aria-valuenow', data.targetProgress.today.percentage)
                        .text(data.targetProgress.today.percentage + '%');
                    $('#targetEntries').text(data.targetProgress.today.target);
                    $('#actualEntries').text(data.targetProgress.today.actual);

                    // Target Progress Chart
                    Highcharts.chart('targetProgressChart', {
                        chart: { type: 'column', backgroundColor: 'transparent' },
                        title: { text: 'Target vs Actual Entries (Last 7 Days)', style: { color: '#1f2937', fontWeight: 'bold' } },
                        xAxis: { type: 'datetime', title: { text: 'Date', style: { color: '#1f2937' } } },
                        yAxis: { title: { text: 'Entries', style: { color: '#1f2937' } }, min: 0 },
                        series: [
                            { name: 'Target', data: data.targetProgress.history.map(h => [h.date, h.target]), color: '#4f46e5' },
                            { name: 'Actual', data: data.targetProgress.history.map(h => [h.date, h.actual]), color: '#f59e0b' }
                        ],
                        credits: { enabled: false }
                    });

                    // Missing Marks Table
                    let missingBody = data.missingMarks.length ? '' : '<tr><td colspan="7">No missing marks found</td></tr>';
                    data.missingMarks.forEach(row => {
                        missingBody += `
                            <tr data-school-id="${row.school_id}">
                                <td>${row.center_no}</td>
                                <td>${row.school_name}</td>
                                <td>${row.complete_marks}</td>
                                <td>${row.missing_eng}</td>
                                <td>${row.missing_sst}</td>
                                <td>${row.missing_mtc}</td>
                                <td>${row.missing_sci}</td>
                            </tr>
                        `;
                    });
                    $('#missingTable').html(missingBody);

                    // Data Entrant Monitoring Table
                    let entrantBody = data.entrantData.length ? '' : '<tr><td colspan="3">No data available</td></tr>';
                    data.entrantData.forEach(row => {
                        entrantBody += `
                            <tr data-school-id="${row.school_id || ''}">
                                <td>${row.username}</td>
                                <td>${row.entries} (${row.absentees} absentees)</td>
                                <td>${row.total_candidates}</td>
                            </tr>
                        `;
                    });
                    $('#entrantTable').html(entrantBody);

                    // Division Summary Table
                    let summaryBody = data.summaryTable.length ? '' : '<tr><td colspan="7">No data available</td></tr>';
                    data.summaryTable.forEach(row => {
                        summaryBody += `
                            <tr data-school-id="${row.school_id}">
                                <td>${row.school_name}</td>
                                <td>${row.division_1}</td>
                                <td>${row.division_2}</td>
                                <td>${row.division_3}</td>
                                <td>${row.division_4}</td>
                                <td>${row.ungraded}</td>
                                <td>${row.absentees}</td>
                            </tr>
                        `;
                    });
                    $('#summaryTable').html(summaryBody);

                    // Results Status Table
                    let resultsStatusBody = data.resultsStatus.length ? '' : '<tr><td colspan="5">No data available</td></tr>';
                    data.resultsStatus.forEach(row => {
                        resultsStatusBody += `
                            <tr data-school-id="${row.school_id}" data-status="${row.results_status}">
                                <td>${row.center_no}</td>
                                <td>${row.school_name}</td>
                                <td>${row.results_status}</td>
                                <td>${row.total_candidates}</td>
                                <td>${row.complete_marks}</td>
                            </tr>
                        `;
                    });
                    $('#resultsStatusTable').html(resultsStatusBody);

                    // Progressive Line Chart
                    Highcharts.chart('progressiveGraph', {
                        chart: { type: 'line', backgroundColor: 'transparent' },
                        title: { text: 'Data Entries Over Time', style: { color: '#1f2937', fontWeight: 'bold' } },
                        xAxis: { type: 'datetime', title: { text: 'Date', style: { color: '#1f2937' } } },
                        yAxis: { title: { text: 'Number of Entries', style: { color: '#1f2937' } }, min: 0 },
                        series: [{ name: 'Entries', data: data.progressiveData, color: '#4f46e5' }],
                        credits: { enabled: false }
                    });

                    // Division Donut Chart
                    Highcharts.chart('divisionChart', {
                        chart: { type: 'pie', backgroundColor: 'transparent' },
                        title: { text: 'Results by Division', style: { color: '#1f2937', fontWeight: 'bold' } },
                        plotOptions: { pie: { innerSize: '50%', dataLabels: { enabled: true, format: '{point.name}: {point.percentage:.1f}%', style: { color: '#1f2937' } } } },
                        series: [{ name: 'Candidates', data: data.divisionData, colors: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#6b7280'] }],
                        credits: { enabled: false }
                    });

                    // Subject Performance Donut Chart
                    Highcharts.chart('subjectChart', {
                        chart: { type: 'pie', backgroundColor: 'transparent' },
                        title: { text: 'Average Subject Performance', style: { color: '#1f2937', fontWeight: 'bold' } },
                        plotOptions: { pie: { innerSize: '50%', dataLabels: { enabled: true, format: '{point.name}: {point.y:.1f}', style: { color: '#1f2937' } } } },
                        series: [{ name: 'Average Mark', data: data.subjectData, colors: ['#3b82f6', '#6b7280', '#fd7e14', '#20c997'] }],
                        credits: { enabled: false }
                    });

                    // Top Schools Bar Chart
                    Highcharts.chart('topSchoolsChart', {
                        chart: { type: 'bar', backgroundColor: 'transparent' },
                        title: { text: 'Top Performing Schools', style: { color: '#1f2937', fontWeight: 'bold' } },
                        xAxis: { type: 'category', title: { text: 'School', style: { color: '#1f2937' } } },
                        yAxis: { title: { text: 'Average Mark', style: { color: '#1f2937' } }, min: 0, max: 100 },
                        series: [{ name: 'Average Mark', data: data.topSchools, color: '#4f46e5' }],
                        credits: { enabled: false }
                    });

                    // Subject-wise Performance Trend Chart
                    Highcharts.chart('subjectTrendChart', {
                        chart: { type: 'line', backgroundColor: 'transparent' },
                        title: { text: 'Subject-wise Performance Trend', style: { color: '#1f2937', fontWeight: 'bold' } },
                        xAxis: { type: 'datetime', title: { text: 'Date', style: { color: '#1f2937' } } },
                        yAxis: { title: { text: 'Average Mark', style: { color: '#1f2937' } }, min: 0, max: 100 },
                        series: [
                            { name: 'English', data: data.subjectTrend.english, color: '#3b82f6' },
                            { name: 'Social Studies', data: data.subjectTrend.sst, color: '#6b7280' },
                            { name: 'Mathematics', data: data.subjectTrend.mtc, color: '#fd7e14' },
                            { name: 'Science', data: data.subjectTrend.sci, color: '#20c997' }
                        ],
                        credits: { enabled: false }
                    });

                    // School Filters
                    function applyFilter(tableId, filterId) {
                        const schoolId = $(`#${filterId}`).val();
                        $(`#${tableId} tr[data-school-id]`).each(function() {
                            $(this).css('display', !schoolId || $(this).data('school-id') == schoolId ? '' : 'none');
                        });

                        if (filterId === 'subjectSchoolFilter') {
                            $.ajax({
                                url: 'data/dashboard_data.php',
                                method: 'GET',
                                data: { exam_year_id: <?php echo $exam_year_id; ?>, school_id: schoolId },
                                dataType: 'json',
                                timeout: 10000,
                                success: function(data) {
                                    if (data.error) {
                                        showNotification('Error loading subject trend: ' + data.error, 'error');
                                        return;
                                    }
                                    Highcharts.chart('subjectTrendChart', {
                                        chart: { type: 'line', backgroundColor: 'transparent' },
                                        title: { text: 'Subject-wise Performance Trend', style: { color: '#1f2937', fontWeight: 'bold' } },
                                        xAxis: { type: 'datetime', title: { text: 'Date', style: { color: '#1f2937' } } },
                                        yAxis: { title: { text: 'Average Mark', style: { color: '#1f2937' } }, min: 0, max: 100 },
                                        series: [
                                            { name: 'English', data: data.subjectTrend.english, color: '#3b82f6' },
                                            { name: 'Social Studies', data: data.subjectTrend.sst, color: '#6b7280' },
                                            { name: 'Mathematics', data: data.subjectTrend.mtc, color: '#fd7e14' },
                                            { name: 'Science', data: data.subjectTrend.sci, color: '#20c997' }
                                        ],
                                        credits: { enabled: false }
                                    });
                                },
                                error: function(xhr, status, error) {
                                    console.error('Error fetching subject trend:', error);
                                    showNotification('Failed to load subject trend data', 'error');
                                }
                            });
                        }
                    }

                    // Results Status Filter
                    function applyResultsStatusFilter() {
                        const schoolId = $('#resultsSchoolFilter').val();
                        const status = $('#resultsStatusFilter').val();
                        $('#resultsStatusTable tr[data-school-id]').each(function() {
                            const schoolMatch = !schoolId || $(this).data('school-id') == schoolId;
                            const statusMatch = !status || $(this).data('status') === status;
                            $(this).css('display', schoolMatch && statusMatch ? '' : 'none');
                        });
                    }

                    $('#missingSchoolFilter').on('change', () => applyFilter('missingTable', 'missingSchoolFilter'));
                    $('#divisionSchoolFilter').on('change', () => applyFilter('summaryTable', 'divisionSchoolFilter'));
                    $('#entrantSchoolFilter').on('change', () => applyFilter('entrantTable', 'entrantSchoolFilter'));
                    $('#subjectSchoolFilter').on('change', () => applyFilter('subjectTrendChart', 'subjectSchoolFilter'));
                    $('#resultsSchoolFilter').on('change', applyResultsStatusFilter);
                    $('#resultsStatusFilter').on('change', applyResultsStatusFilter);

                    // Export to CSV
                    function exportTableToCsv(tableId, filename) {
                        let csv = [];
                        const headers = $(`#${tableId} thead th`).map(function() { return $(this).text(); }).get();
                        csv.push(headers.join(','));
                        $(`#${tableId} tbody tr:not([style*="display: none"])`).each(function() {
                            const rowData = $(this).find('td').map(function() { return `"${$(this).text().replace(/"/g, '""')}"`; }).get();
                            csv.push(rowData.join(','));
                        });
                        const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = $('<a>', { href: url, download: filename }).get(0);
                        a.click();
                        window.URL.revokeObjectURL(url);
                    }

                    $('#exportMissingCsv').on('click', () => exportTableToCsv('missingTable', 'missing_marks.csv'));
                    $('#exportSummaryCsv').on('click', () => exportTableToCsv('summaryTable', 'division_summary.csv'));
                    $('#exportEntrantCsv').on('click', () => exportTableToCsv('entrantTable', 'entrant_monitoring.csv'));
                    $('#exportResultsStatusCsv').on('click', () => exportTableToCsv('resultsStatusTable', 'results_status.csv'));

                    // Hide loading spinners
                    $('.loading-spinner').hide();

                    // Apply fade-in animation
                    $('.dashboard-card, .table-enhanced').each(function() {
                        $(this).css('animation', 'fadeIn 0.3s ease');
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching data:', error);
                    showNotification('Failed to load dashboard data. Please try again.', 'error');
                    $('#missingTable, #entrantTable, #summaryTable, #resultsStatusTable').html('<tr><td colspan="7">Error loading data</td></tr>');
                    $('.loading-spinner').hide();
                }
            });
        });
    </script>
</body>
</html>

<?php
// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>