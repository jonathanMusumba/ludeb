<?php
session_start();
require_once 'db_connect.php';

// Restrict to Data Entrants
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Data Entrant') {
    header("Location: ../../login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);
$user_id = $_SESSION['user_id'];

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');

// Log dashboard access
$conn->query("CALL log_action('Dashboard Access', $user_id, 'Accessed dashboard')");

// Fetch schools for filter (only those handled by the user)
$schoolsForFilter = $conn->prepare("
    SELECT DISTINCT s.id, s.school_name AS name 
    FROM schools s
    JOIN candidates c ON s.id = c.school_id
    JOIN marks m ON c.id = m.candidate_id
    WHERE m.submitted_by = ? AND c.exam_year_id = (
        SELECT id FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC LIMIT 1
    )
    ORDER BY s.school_name
");
$schoolsForFilter->bind_param("i", $user_id);
$schoolsForFilter->execute();
$schoolsForFilter = $schoolsForFilter->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Results Management System</title>
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

        .collapse-menu {
            padding-left: 1rem;
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

        /* Stat Cards */
        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b7280;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .stat-card-value .sub-value {
            font-size: 1rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }

        .stat-card-change {
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-card-change.positive {
            color: var(--success-color);
        }

        .stat-card-change.negative {
            color: var(--danger-color);
        }

        /* Chart Cards */
        .chart-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .chart-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .chart-card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Progress Bar */
        .progress-enhanced {
            height: 12px;
            background: rgba(229, 231, 235, 0.3);
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .progress-bar-enhanced {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 6px;
            transition: width 0.6s ease;
            position: relative;
        }

        .progress-bar-enhanced::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #6b7280;
        }

        /* Table Styles */
        .table-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        .table-enhanced {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
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

        /* Alerts */
        .alert-enhanced {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            backdrop-filter: blur(10px);
        }

        .alert-enhanced i {
            font-size: 1.25rem;
            color: var(--warning-color);
        }

        .alert-enhanced-content {
            flex: 1;
            color: #92400e;
            font-weight: 500;
        }

        /* Loading States */
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 4px;
            height: 1.5rem;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
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

            .stat-card-value {
                font-size: 1.5rem;
            }

            .table-container {
                overflow-x: auto;
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
                        <a class="nav-link active" href="#" id="dashboardLink">
                            <i class="fas fa-chart-line"></i>
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
                                    <a class="nav-link" href="schools.php">
                                        <i class="fas fa-list"></i>
                                        List Schools
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#marksMenu">
                            <i class="fas fa-edit"></i>
                            Capture Marks
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="marksMenu" class="collapse">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link" href="marks.php">
                                        <i class="fas fa-plus"></i>
                                        Enter Marks
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="see_marks.php">
                                        <i class="fas fa-eye"></i>
                                        View Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="check_missing.php">
                                        <i class="fas fa-search"></i>
                                        Check Missing
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
                        <i class="fas fa-chart-line"></i>
                        Dashboard
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item active" aria-current="page">
                                <i class="fas fa-home"></i>
                                Dashboard
                            </li>
                        </ol>
                    </nav>
                </div>

                <!-- Alerts -->
                <div class="alerts" id="alertContainer"></div>

                <!-- Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-title">
                                    <i class="fas fa-school"></i>
                                    Total Schools
                                </div>
                                <div class="stat-card-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                                    <i class="fas fa-school"></i>
                                </div>
                            </div>
                            <div class="stat-card-value" id="totalSchools">
                                <div class="loading-skeleton"></div>
                            </div>
                            <div class="stat-card-change positive">
                                <i class="fas fa-arrow-up"></i>
                                Active schools
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-title">
                                    <i class="fas fa-users"></i>
                                    Total Candidates
                                </div>
                                <div class="stat-card-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                            <div class="stat-card-value" id="totalCandidates">
                                <div class="loading-skeleton"></div>
                                <div class="sub-value" id="maleCandidates">
                                    <i class="fas fa-mars"></i> Male: <span><div class="loading-skeleton d-inline-block" style="width: 50px;"></div></span>
                                </div>
                                <div class="sub-value" id="femaleCandidates">
                                    <i class="fas fa-venus"></i> Female: <span><div class="loading-skeleton d-inline-block" style="width: 50px;"></div></span>
                                </div>
                            </div>
                            <div class="stat-card-change positive">
                                <i class="fas fa-arrow-up"></i>
                                Registered
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-title">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Undeclared Results
                                </div>
                                <div class="stat-card-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                            <div class="stat-card-value" id="undeclaredResults">
                                <div class="loading-skeleton"></div>
                            </div>
                            <div class="stat-card-change negative">
                                <i class="fas fa-arrow-down"></i>
                                Needs attention
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-title">
                                    <i class="fas fa-user-check"></i>
                                    My Schools Handled
                                </div>
                                <div class="stat-card-icon" style="background: linear-gradient(135deg, #84cc16, #65a30d);">
                                    <i class="fas fa-user-check"></i>
                                </div>
                            </div>
                            <div class="stat-card-value" id="schoolsHandled">
                                <div class="loading-skeleton"></div>
                            </div>
                            <div class="stat-card-change positive">
                                <i class="fas fa-arrow-up"></i>
                                Assigned to me
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subject Performance Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-title">
                                    <i class="fas fa-medal"></i>
                                    Candidates with 4 Subjects
                                </div>
                                <div class="stat-card-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                    <i class="fas fa-medal"></i>
                                </div>
                            </div>
                            <div class="stat-card-value" id="subject4">
                                <div class="loading-skeleton"></div>
                            </div>
                            <div class="stat-card-change positive">
                                <i class="fas fa-star"></i>
                                Complete
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-title">
                                    <i class="fas fa-award"></i>
                                    Candidates with 3 Subjects
                                </div>
                                <div class="stat-card-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                                    <i class="fas fa-award"></i>
                                </div>
                            </div>
                            <div class="stat-card-value" id="subject3">
                                <div class="loading-skeleton"></div>
                            </div>
                            <div class="stat-card-change">
                                <i class="fas fa-thumbs-up"></i>
                                Partial
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-title">
                                    <i class="fas fa-certificate"></i>
                                    Candidates with 2 Subjects
                                </div>
                                <div class="stat-card-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                                    <i class="fas fa-certificate"></i>
                                </div>
                            </div>
                            <div class="stat-card-value" id="subject2">
                                <div class="loading-skeleton"></div>
                            </div>
                            <div class="stat-card-change">
                                <i class="fas fa-check"></i>
                                Partial
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-title">
                                    <i class="fas fa-ribbon"></i>
                                    Candidates with 1 Subject
                                </div>
                                <div class="stat-card-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                                    <i class="fas fa-ribbon"></i>
                                </div>
                            </div>
                            <div class="stat-card-value" id="subject1">
                                <div class="loading-skeleton"></div>
                            </div>
                            <div class="stat-card-change">
                                <i class="fas fa-info-circle"></i>
                                Partial
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Target Progress Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h5 class="chart-card-title">
                                    <i class="fas fa-bullseye"></i>
                                    Daily Target Progress
                                </h5>
                            </div>
                            <div class="progress-enhanced">
                                <div id="targetProgressBar" class="progress-bar-enhanced" style="width: 0%;"></div>
                            </div>
                            <div class="progress-info">
                                <span>Target: <strong id="targetEntries">0</strong></span>
                                <span>Actual: <strong id="actualEntries">0</strong></span>
                                <span id="progressPercentage">0%</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h5 class="chart-card-title">
                                    <i class="fas fa-chart-bar"></i>
                                    Target Progress (Last 7 Days)
                                </h5>
                            </div>
                            <div id="targetProgressChart" style="height: 300px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Progressive Graph -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h5 class="chart-card-title">
                                    <i class="fas fa-chart-line"></i>
                                    Data Entries Over Time
                                </h5>
                            </div>
                            <div id="progressiveGraph" style="height: 400px;"></div>
                        </div>
                    </div>
                </div>

                <!-- My Schools Table -->
                <div class="table-container">
                    <div class="table-header">
                        <h5 class="table-title">
                            <i class="fas fa-school"></i>
                            My Schools
                        </h5>
                        <div class="table-filter">
                            <label for="schoolFilter">Filter by School:</label>
                            <select id="schoolFilter" class="form-select">
                                <option value="">All Schools</option>
                                <?php foreach ($schoolsForFilter as $school): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table-enhanced">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-school"></i> School Name</th>
                                    <th><i class="fas fa-users"></i> Total Candidates</th>
                                    <th><i class="fas fa-check-circle"></i> Candidates with Marks</th>
                                    <th><i class="fas fa-book"></i> Subjects</th>
                                    <th><i class="fas fa-info-circle"></i> Status</th>
                                    <th><i class="fas fa-calendar"></i> Handled When</th>
                                </tr>
                            </thead>
                            <tbody id="schoolsTable">
                                <tr>
                                    <td colspan="6">
                                        <div class="loading-skeleton"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Summary of Entries Table -->
                <div class="table-container">
                    <div class="table-header">
                        <h5 class="table-title">
                            <i class="fas fa-chart-pie"></i>
                            Summary of Subject Entries
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table-enhanced">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-book"></i> Subject</th>
                                    <th><i class="fas fa-list-ol"></i> Number of Entries</th>
                                    <th><i class="fas fa-check"></i> Marks Submitted</th>
                                </tr>
                            </thead>
                            <tbody id="summaryTable">
                                <tr>
                                    <td colspan="3">
                                        <div class="loading-skeleton"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('show');
            mainContent.classList.toggle('expanded');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !sidebarToggle.contains(event.target)) {
                sidebar.classList.remove('show');
                document.getElementById('mainContent').classList.remove('expanded');
            }
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add loading animation to cards
        function showLoading(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                element.innerHTML = '<div class="loading-skeleton"></div>';
            }
        }

        // Animate numbers when they load
        function animateNumber(element, finalValue, duration = 1000) {
            const startValue = 0;
            const increment = finalValue / (duration / 16);
            let currentValue = startValue;
            
            const timer = setInterval(() => {
                currentValue += increment;
                if (currentValue >= finalValue) {
                    element.textContent = finalValue;
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(currentValue);
                }
            }, 16);
        }

        // Animate sub-values for candidates
        function animateSubValue(elementId, value, label) {
            const element = document.getElementById(elementId);
            if (element) {
                const span = element.querySelector('span');
                span.innerHTML = '';
                animateNumber(span, value);
                element.querySelector('span').insertAdjacentHTML('beforebegin', `${label}: `);
            }
        }

        // Enhanced progress bar animation
        function animateProgressBar(percentage) {
            const progressBar = document.getElementById('targetProgressBar');
            const progressText = document.getElementById('progressPercentage');
            
            if (progressBar && progressText) {
                progressBar.style.width = percentage + '%';
                progressText.textContent = percentage + '%';
                
                if (percentage >= 80) {
                    progressBar.style.animation = 'pulse 2s infinite';
                }
            }
        }

        // Fetch and display dashboard data
        fetch('data/entrant_dashboard_data.php')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error loading data:', data.error);
                    showNotification('Error loading dashboard data', 'error');
                    return;
                }

                // Animate stat cards
                const stats = [
                    { id: 'totalSchools', value: data.totalSchools },
                    { id: 'undeclaredResults', value: data.undeclaredResults },
                    { id: 'schoolsHandled', value: data.schoolsHandled },
                    { id: 'subject4', value: data.subjectCounts[4] },
                    { id: 'subject3', value: data.subjectCounts[3] },
                    { id: 'subject2', value: data.subjectCounts[2] },
                    { id: 'subject1', value: data.subjectCounts[1] }
                ];

                stats.forEach((stat, index) => {
                    setTimeout(() => {
                        const element = document.getElementById(stat.id);
                        if (element) {
                            animateNumber(element, stat.value);
                        }
                    }, index * 100);
                });

                // Animate Total Candidates with sub-values
                setTimeout(() => {
                    const totalElement = document.getElementById('totalCandidates');
                    if (totalElement) {
                        totalElement.innerHTML = '';
                        animateNumber(totalElement, data.totalCandidates);
                    }
                    animateSubValue('maleCandidates', data.maleCandidates, 'Male');
                    animateSubValue('femaleCandidates', data.femaleCandidates, 'Female');
                }, 100);

                // Handle alerts
                if (data.undeclaredResults > 0) {
                    document.getElementById('alertContainer').innerHTML = `
                        <div class="alert-enhanced">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div class="alert-enhanced-content">
                                <strong>Attention!</strong> ${data.undeclaredResults} school(s) have undeclared results. Please review missing marks.
                            </div>
                        </div>
                    `;
                }

                // Daily Target Progress
                const targetProgress = data.targetProgress.today;
                animateProgressBar(targetProgress.percentage);
                document.getElementById('targetEntries').textContent = targetProgress.target;
                document.getElementById('actualEntries').textContent = targetProgress.actual;

                // Target Progress Chart
                Highcharts.chart('targetProgressChart', {
                    chart: { 
                        type: 'column', 
                        backgroundColor: 'transparent',
                        style: {
                            fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
                        }
                    },
                    title: { text: null },
                    xAxis: { 
                        type: 'datetime',
                        labels: { style: { color: '#6b7280' } },
                        lineColor: '#e5e7eb',
                        tickColor: '#e5e7eb'
                    },
                    yAxis: { 
                        title: { 
                            text: 'Entries',
                            style: { color: '#6b7280' }
                        },
                        min: 0,
                        gridLineColor: '#f3f4f6',
                        labels: { style: { color: '#6b7280' } }
                    },
                    legend: { itemStyle: { color: '#6b7280' } },
                    plotOptions: {
                        column: {
                            borderRadius: 4,
                            groupPadding: 0.1,
                            pointPadding: 0.05
                        }
                    },
                    series: [
                        { 
                            name: 'Target', 
                            data: data.targetProgress.history.map(h => [h.date, h.target]),
                            color: '#e5e7eb'
                        },
                        { 
                            name: 'Actual', 
                            data: data.targetProgress.history.map(h => [h.date, h.actual]),
                            color: '#4f46e5'
                        }
                    ],
                    credits: { enabled: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        style: { color: '#ffffff' },
                        borderRadius: 8
                    }
                });

                // Progressive Line Chart
                Highcharts.chart('progressiveGraph', {
                    chart: { 
                        type: 'area', 
                        backgroundColor: 'transparent',
                        style: {
                            fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
                        }
                    },
                    title: { text: null },
                    xAxis: { 
                        type: 'datetime',
                        labels: { style: { color: '#6b7280' } },
                        lineColor: '#e5e7eb',
                        tickColor: '#e5e7eb'
                    },
                    yAxis: { 
                        title: { 
                            text: 'Cumulative Entries',
                            style: { color: '#6b7280' }
                        },
                        min: 0,
                        gridLineColor: '#f3f4f6',
                        labels: { style: { color: '#6b7280' } }
                    },
                    plotOptions: {
                        area: {
                            fillColor: {
                                linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                                stops: [
                                    [0, 'rgba(79, 70, 229, 0.3)'],
                                    [1, 'rgba(79, 70, 229, 0.05)']
                                ]
                            },
                            lineWidth: 3,
                            marker: {
                                enabled: false,
                                states: { hover: { enabled: true, radius: 5 } }
                            },
                            states: { hover: { lineWidth: 3 } }
                        }
                    },
                    series: [{ 
                        name: 'Entries', 
                        data: data.progressiveData,
                        color: '#4f46e5'
                    }],
                    credits: { enabled: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        style: { color: '#ffffff' },
                        borderRadius: 8
                    }
                });

                // My Schools Table
                let schoolsBody = '';
                if (data.schools.length === 0) {
                    schoolsBody = '<tr><td colspan="6" class="text-center py-4"><i class="fas fa-inbox text-muted"></i><br>No schools handled</td></tr>';
                } else {
                    data.schools.forEach(row => {
                        const statusClass = row.status === 'Complete' ? 'text-success' : 'text-warning';
                        const statusIcon = row.status === 'Complete' ? 'check-circle' : 'clock';
                        const subjects = row.subjects || 'None';
                        schoolsBody += `
                            <tr data-school-id="${row.id}">
                                <td><strong>${row.name}</strong></td>
                                <td><span class="badge bg-primary">${row.candidate_count}</span></td>
                                <td><span class="badge bg-success">${row.candidates_with_marks}</span></td>
                                <td>${subjects}</td>
                                <td><i class="fas fa-${statusIcon} ${statusClass}"></i> ${row.status}</td>
                                <td>${row.handled_when ? new Date(row.handled_when).toLocaleDateString() : '<span class="text-muted">N/A</span>'}</td>
                            </tr>
                        `;
                    });
                }
                document.getElementById('schoolsTable').innerHTML = schoolsBody;

                // Summary of Entries Table
                let summaryBody = '';
                if (data.summary.length === 0) {
                    summaryBody = '<tr><td colspan="3" class="text-center py-4"><i class="fas fa-inbox text-muted"></i><br>No entries</td></tr>';
                } else {
                    data.summary.forEach(row => {
                        summaryBody += `
                            <tr>
                                <td><strong>${row.subject}</strong></td>
                                <td><span class="badge bg-info">${row.entries}</span></td>
                                <td><span class="badge bg-success">${row.marks_submitted}</span></td>
                            </tr>
                        `;
                    });
                }
                document.getElementById('summaryTable').innerHTML = summaryBody;

                // Filter functionality for My Schools Table
                function applyFilter(tableId, filterId) {
                    const schoolId = document.getElementById(filterId).value;
                    const rows = document.querySelectorAll(`#${tableId} tr[data-school-id]`);
                    
                    rows.forEach(row => {
                        const shouldShow = !schoolId || row.dataset.schoolId == schoolId;
                        row.style.display = shouldShow ? '' : 'none';
                        
                        if (shouldShow) {
                            row.style.animation = 'fadeIn 0.3s ease';
                        }
                    });
                }

                document.getElementById('schoolFilter').addEventListener('change', () => {
                    applyFilter('schoolsTable', 'schoolFilter');
                });

                // Success notification
                showNotification('Dashboard loaded successfully', 'success');
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                showNotification('Failed to load dashboard data', 'error');
            });

        // Enhanced notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
                ${message}
            `;
            
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                z-index: 9999;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                max-width: 300px;
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
            
            .notification {
                animation: slideIn 0.3s ease;
            }
            
            @keyframes slideIn {
                from { transform: translateX(100%); }
                to { transform: translateX(0); }
            }
        `;
        document.head.appendChild(style);

        // Resize handling
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                mainContent.classList.remove('expanded');
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('mainContent');
                
                if (sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    mainContent.classList.remove('expanded');
                }
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>