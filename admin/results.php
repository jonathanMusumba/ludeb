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
$page_title = 'School Results';

// Fetch board name and exam years
$stmt = $conn->query("SELECT s.board_name, e.id AS exam_year_id, e.exam_year
                      FROM settings s
                      JOIN exam_years e ON s.exam_year_id = e.id
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$current_exam_year_id = $row['exam_year_id'] ?? 0;
$current_exam_year = $row['exam_year'] ?? date('Y');

// Fetch all exam years for filter
$exam_years = $conn->query("SELECT id, exam_year FROM exam_years ORDER BY exam_year DESC")->fetch_all(MYSQLI_ASSOC);

// Fetch schools for filter
$schools = $conn->query("SELECT id, school_name AS name FROM schools ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Log page access
$conn->query("CALL log_action('School Results Access', $user_id, 'Accessed school results page')");

// Handle AJAX data request
if (isset($_GET['ajax']) && $_GET['ajax'] === 'data') {
    $exam_year_id = isset($_GET['exam_year_id']) ? intval($_GET['exam_year_id']) : $current_exam_year_id;
    $school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;

    try {
        // Validate inputs
        if ($school_id <= 0) {
            echo json_encode(['error' => 'Please select a school'], JSON_NUMERIC_CHECK);
            exit;
        }
        if ($exam_year_id <= 0) {
            $stmt = $conn->query("SELECT id FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC LIMIT 1");
            $row = $stmt->fetch_assoc();
            $exam_year_id = $row['id'] ?? 0;
        }

        // Log data access
        $stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, details) VALUES ('School Results Data Access', ?, ?)");
        $details = "Accessed results for school_id: $school_id, exam_year_id: $exam_year_id";
        $stmt->bind_param("is", $user_id, $details);
        $stmt->execute();

        // Query to fetch results for the selected school
        $query = "SELECT 
                    c.id AS candidate_id,
                    c.index_number,
                    c.candidate_name,
                    c.sex,
                    cr.aggregates,
                    cr.division,
                    MAX(CASE WHEN s.code = 'ENG' THEN r.mark END) AS eng_mark,
                    MAX(CASE WHEN s.code = 'ENG' THEN g.grade END) AS eng_grade,
                    MAX(CASE WHEN s.code = 'SST' THEN r.mark END) AS sst_mark,
                    MAX(CASE WHEN s.code = 'SST' THEN g.grade END) AS sst_grade,
                    MAX(CASE WHEN s.code = 'MTC' THEN r.mark END) AS mtc_mark,
                    MAX(CASE WHEN s.code = 'MTC' THEN g.grade END) AS mtc_grade,
                    MAX(CASE WHEN s.code = 'SCI' THEN r.mark END) AS sci_mark,
                    MAX(CASE WHEN s.code = 'SCI' THEN g.grade END) AS sci_grade
                  FROM candidates c
                  LEFT JOIN results r ON c.id = r.candidate_id AND r.exam_year_id = ?
                  LEFT JOIN subjects s ON r.subject_id = s.id
                  LEFT JOIN grading g ON r.subject_id = g.subject_id AND r.mark BETWEEN g.range_from AND g.range_to AND g.exam_year_id = ?
                  LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
                  WHERE c.school_id = ? AND c.exam_year_id = ?
                  GROUP BY c.id
                  ORDER BY c.index_number";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iiiii', $exam_year_id, $exam_year_id, $exam_year_id, $school_id, $exam_year_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            // Escape special characters for JSON safety
            $data[] = [
                'candidate_id' => $row['candidate_id'],
                'index_number' => htmlspecialchars($row['index_number']),
                'candidate_name' => htmlspecialchars($row['candidate_name']),
                'sex' => htmlspecialchars($row['sex']),
                'eng_mark' => $row['eng_mark'] !== null ? (int)$row['eng_mark'] : '-',
                'eng_grade' => htmlspecialchars($row['eng_grade'] ?? '-'),
                'sst_mark' => $row['sst_mark'] !== null ? (int)$row['sst_mark'] : '-',
                'sst_grade' => htmlspecialchars($row['sst_grade'] ?? '-'),
                'mtc_mark' => $row['mtc_mark'] !== null ? (int)$row['mtc_mark'] : '-',
                'mtc_grade' => htmlspecialchars($row['mtc_grade'] ?? '-'),
                'sci_mark' => $row['sci_mark'] !== null ? (int)$row['sci_mark'] : '-',
                'sci_grade' => htmlspecialchars($row['sci_grade'] ?? '-'),
                'aggregates' => htmlspecialchars($row['aggregates'] ?? '-'),
                'division' => htmlspecialchars($row['division'] ?? '-')
            ];
        }

        echo json_encode(['data' => $data], JSON_NUMERIC_CHECK);
        exit;

    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, details) VALUES ('School Results Data Error', ?, ?)");
        $stmt->bind_param("is", $user_id, $error_message);
        $stmt->execute();
        echo json_encode(['error' => 'Server error: ' . $error_message], JSON_NUMERIC_CHECK);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Results Management System</title>
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
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
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

        /* Loading States */
        .loading-spinner {
            font-size: 1.5rem;
            color: var(--primary-color);
            animation: spin 1s linear infinite;
            display: none; /* Initially hidden */
            margin: 180px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

            .filter-container select {
                width: 100%;
            }

            .table-enhanced {
                display: block;
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
                        <a class="nav-link" href="partially_declared_schools.php">
                            <i class="fas fa-hourglass-half"></i>
                            Partially Declared Schools
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="school_results.php">
                            <i class="fas fa-file-alt"></i>
                            School Results
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
                                <li class="nav-item">
                                    <a class="nav-link" href="results/update_results.php">
                                        <i class="fas fa-edit"></i>
                                        Update Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="results/process_results.php">
                                        <i class="fas fa-cogs"></i>
                                        Process Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="results/edit_results.php">
                                        <i class="fas fa-pencil-alt"></i>
                                        Edit Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="results/print_results.php">
                                        <i class="fas fa-print"></i>
                                        Print Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="results/print_edited_results.php">
                                        <i class="fas fa-print"></i>
                                        Print Edited Results
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#reportingMenu">
                            <i class="fas fa-chart-pie"></i>
                            Reporting
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="reportingMenu" class="collapse">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link" href="reporting/generate_reports.php">
                                        <i class="fas fa-file-alt"></i>
                                        Generate Reports
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="reporting/view_reports.php">
                                        <i class="fas fa-eye"></i>
                                        View Reports
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
                        <a class="nav-link" href="settings.php">
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
                        <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($current_exam_year); ?></span>
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></span>
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
                        <i class="fas fa-file-alt"></i>
                        <?php echo htmlspecialchars($page_title); ?>
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">School Results</li>
                        </ol>
                    </nav>
                </div>

                <!-- Alerts -->
                <div class="alerts" id="alertContainer"></div>

                <!-- Results Table -->
                <div class="row mb-5">
                    <div class="col-md-12 mb-4">
                        <h2 class="page-title">Candidate Results for Selected School</h2>
                        <div class="filter-container">
                            <label for="examYearFilter">Exam Year:</label>
                            <select id="examYearFilter" class="form-control d-inline-block w-auto">
                                <option value="">All Exam Years</option>
                                <?php foreach ($exam_years as $year): ?>
                                    <option value="<?php echo $year['id']; ?>" <?php echo $year['id'] == $current_exam_year_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year['exam_year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="schoolFilter" class="ms-3">Select School:</label>
                            <select id="schoolFilter" class="form-control d-inline-block w-auto" required>
                                <option value="">Select a School</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>">
                                        <?php echo htmlspecialchars($school['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="dashboard-card">
                            <i class="fas fa-spinner loading-spinner"></i>
                            <table class="table-enhanced">
                                <thead>
                                    <tr>
                                        <th>Index Number</th>
                                        <th>Candidate Name</th>
                                        <th>Sex</th>
                                        <th>English (Mark/Grade)</th>
                                        <th>Social Studies (Mark/Grade)</th>
                                        <th>Mathematics (Mark/Grade)</th>
                                        <th>Science (Mark/Grade)</th>
                                        <th>Aggregates</th>
                                        <th>Division</th>
                                    </tr>
                                </thead>
                                <tbody id="resultsTable">
                                    <tr><td colspan="9">Please select a school to view results</td></tr>
                                </tbody>
                            </table>
                            <button id="exportCsv" class="btn-enhanced mt-2" disabled>
                                <i class="fas fa-download"></i> Export to CSV
                            </button>
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

            // Function to escape HTML characters
            function escapeHtml(unsafe) {
                if (unsafe === null || unsafe === undefined) return '';
                return unsafe
                    .toString()
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            // Load data
            function loadData() {
                const examYearId = $('#examYearFilter').val();
                const schoolId = $('#schoolFilter').val();
                
                if (!schoolId) {
                    $('#resultsTable').html('<tr><td colspan="9">Please select a school to view results</td></tr>');
                    $('#exportCsv').prop('disabled', true);
                    $('#alertContainer').empty();
                    $('.loading-spinner').hide();
                    return;
                }

                $('.loading-spinner').show();
                $('#resultsTable').html('<tr><td colspan="9">Loading...</td></tr>');
                $('#exportCsv').prop('disabled', true);

                $.ajax({
                    url: 'results.php',
                    method: 'GET',
                    data: { 
                        ajax: 'data',
                        exam_year_id: examYearId,
                        school_id: schoolId
                    },
                    dataType: 'json',
                    timeout: 10000,
                    success: function(response) {
                        $('.loading-spinner').hide();
                        if (response.error) {
                            showNotification('Error loading data: ' + response.error, 'error');
                            $('#resultsTable').html('<tr><td colspan="9">Error loading data: ' + escapeHtml(response.error) + '</td></tr>');
                            return;
                        }

                        // Populate table
                        let tableBody = response.data.length ? '' : '<tr><td colspan="9">No results found for the selected school</td></tr>';
                        try {
                            response.data.forEach(row => {
                                tableBody += `
                                    <tr data-candidate-id="${escapeHtml(row.candidate_id)}">
                                        <td>${escapeHtml(row.index_number)}</td>
                                        <td>${escapeHtml(row.candidate_name)}</td>
                                        <td>${escapeHtml(row.sex)}</td>
                                        <td>${escapeHtml(row.eng_mark)}/${escapeHtml(row.eng_grade)}</td>
                                        <td>${escapeHtml(row.sst_mark)}/${escapeHtml(row.sst_grade)}</td>
                                        <td>${escapeHtml(row.mtc_mark)}/${escapeHtml(row.mtc_grade)}</td>
                                        <td>${escapeHtml(row.sci_mark)}/${escapeHtml(row.sci_grade)}</td>
                                        <td>${escapeHtml(row.aggregates)}</td>
                                        <td>${escapeHtml(row.division)}</td>
                                    </tr>
                                `;
                            });
                        } catch (e) {
                            console.error('Error building table:', e);
                            showNotification('Error rendering table data', 'error');
                            $('#resultsTable').html('<tr><td colspan="9">Error rendering table data</td></tr>');
                            return;
                        }
                        $('#resultsTable').html(tableBody);

                        // Enable export button if data exists
                        if (response.data.length > 0) {
                            $('#exportCsv').prop('disabled', false);
                            $('#alertContainer').html(`
                                <div class="alert-enhanced alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Displaying ${response.data.length} candidate(s) for the selected school. Verify results for accuracy.
                                </div>
                            `);
                        } else {
                            $('#alertContainer').empty();
                        }

                        $('.dashboard-card, .table-enhanced').css('animation', 'fadeIn 0.3s ease');
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        $('.loading-spinner').hide();
                        showNotification('Failed to load data. Please try again.', 'error');
                        $('#resultsTable').html('<tr><td colspan="9">Error loading data</td></tr>');
                    }
                });
            }

            // Initial data load
            loadData();

            // Filter change handlers
            $('#examYearFilter, #schoolFilter').on('change', loadData);

            // Export to CSV
            $('#exportCsv').on('click', function() {
                let csv = [];
                const headers = $('#resultsTable').closest('table').find('thead th').map(function() { 
                    return `"${$(this).text().replace(/"/g, '""')}"`; 
                }).get();
                csv.push(headers.join(','));

                $('#resultsTable tr:not([style*="display: none"])').each(function() {
                    const rowData = $(this).find('td').map(function() { 
                        return `"${$(this).text().replace(/"/g, '""')}"`; 
                    }).get();
                    csv.push(rowData.join(','));
                });

                const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = $('<a>', { href: url, download: 'school_results.csv' }).get(0);
                a.click();
                window.URL.revokeObjectURL(url);
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