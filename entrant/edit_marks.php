<?php
session_start();
require_once 'db_connect.php';

// Restrict to authenticated users (e.g., Data Entrant or higher)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Data Entrant', 'Examination Administrator', 'System Admin'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');

// Determine if editing a single candidate or bulk editing a school
$candidate_id = isset($_GET['candidate_id']) ? intval($_GET['candidate_id']) : null;
$school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : null;

$mode = $candidate_id ? 'single' : ($school_id ? 'bulk' : 'invalid');
if ($mode === 'invalid') {
    header("Location: check_missing.php");
    exit();
}

$success_message = '';
$locked_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if marks are locked for the school or candidate
    if ($mode === 'single') {
        $stmt = $conn->prepare("SELECT s.locked FROM schools s JOIN candidates c ON c.school_id = s.id WHERE c.id = ?");
        $stmt->bind_param("i", $candidate_id);
    } else {
        $stmt = $conn->prepare("SELECT locked FROM schools WHERE id = ?");
        $stmt->bind_param("i", $school_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()['locked']) {
        $locked_message = "Marks are locked and cannot be edited.";
    } else {
        if ($mode === 'single') {
            // Update marks for a single candidate only if missing
            foreach ($_POST['marks'] as $subject_id => $mark) {
                $subject_id = intval($subject_id);
                $mark = floatval($mark);
                // Enforce policy: marks must be > 0, set 0 to 1
                if ($mark == 0) $mark = 1;
                if ($mark >= 0 && $mark <= 100) {
                    $stmt = $conn->prepare("SELECT mark FROM marks WHERE candidate_id = ? AND subject_id = ?");
                    $stmt->bind_param("ii", $candidate_id, $subject_id);
                    $stmt->execute();
                    $existing_mark = $stmt->get_result()->fetch_assoc()['mark'] ?? null;
                    if ($existing_mark === -1 || $existing_mark === null) {
                        $stmt = $conn->prepare("INSERT INTO marks (candidate_id, subject_id, school_id, mark, submitted_by) 
                                               VALUES (?, ?, (SELECT school_id FROM candidates WHERE id = ?), ?, ?)
                                               ON DUPLICATE KEY UPDATE mark = ?, edited_by = ?, updated_at = NOW()");
                        $stmt->bind_param("iiiiiii", $candidate_id, $subject_id, $candidate_id, $mark, $user_id, $mark, $user_id);
                        $stmt->execute();
                    }
                }
            }
            // Lock the school after saving
            $stmt = $conn->prepare("UPDATE schools s JOIN candidates c ON c.school_id = s.id SET s.locked = 1 WHERE c.id = ?");
            $stmt->bind_param("i", $candidate_id);
            $stmt->execute();
            $success_message = "Marks updated successfully and locked!";
        } else {
            // Bulk update for a school only if missing
            foreach ($_POST['marks'] as $candidate_id => $subjects) {
                $candidate_id = intval($candidate_id);
                foreach ($subjects as $subject_id => $mark) {
                    $subject_id = intval($subject_id);
                    $mark = floatval($mark);
                    // Enforce policy: marks must be > 0, set 0 to 1
                    if ($mark == 0) $mark = 1;
                    if ($mark >= 0 && $mark <= 100) {
                        $stmt = $conn->prepare("SELECT mark FROM marks WHERE candidate_id = ? AND subject_id = ?");
                        $stmt->bind_param("ii", $candidate_id, $subject_id);
                        $stmt->execute();
                        $existing_mark = $stmt->get_result()->fetch_assoc()['mark'] ?? null;
                        if ($existing_mark === -1 || $existing_mark === null) {
                            $stmt = $conn->prepare("INSERT INTO marks (candidate_id, subject_id, school_id, mark, submitted_by) 
                                                   VALUES (?, ?, ?, ?, ?)
                                                   ON DUPLICATE KEY UPDATE mark = ?, edited_by = ?, updated_at = NOW()");
                            $stmt->bind_param("iiiiiii", $candidate_id, $subject_id, $school_id, $mark, $user_id, $mark, $user_id);
                            $stmt->execute();
                        }
                    }
                }
            }
            // Lock the school after saving
            $stmt = $conn->prepare("UPDATE schools SET locked = 1 WHERE id = ?");
            $stmt->bind_param("i", $school_id);
            $stmt->execute();
            $success_message = "Marks updated successfully and locked!";
        }
    }
}

// Fetch data based on mode
if ($mode === 'single') {
    // Check if locked
    $stmt = $conn->prepare("SELECT s.locked FROM schools s JOIN candidates c ON c.school_id = s.id WHERE c.id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $locked = $stmt->get_result()->fetch_assoc()['locked'] ?? 0;

    if ($locked) {
        $locked_message = "Marks are locked and cannot be edited.";
    } else {
        // Fetch candidate details
        $stmt = $conn->prepare("SELECT c.index_number, c.candidate_name, s.school_name 
                                FROM candidates c 
                                JOIN schools s ON c.school_id = s.id 
                                WHERE c.id = ?");
        $stmt->bind_param("i", $candidate_id);
        $stmt->execute();
        $candidate_result = $stmt->get_result();
        $candidate = $candidate_result->fetch_assoc();

        // Fetch subjects and marks for the candidate
        $stmt = $conn->prepare("
            SELECT s.id AS subject_id, s.name AS subject_name, m.mark
            FROM subjects s
            LEFT JOIN marks m ON s.id = m.subject_id AND m.candidate_id = ?
            ORDER BY s.name ASC
        ");
        $stmt->bind_param("i", $candidate_id);
        $stmt->execute();
        $marks_result = $stmt->get_result();
        $subjects = [];
        while ($row = $marks_result->fetch_assoc()) {
            $subjects[] = $row;
        }
    }
} else {
    // Check if locked
    $stmt = $conn->prepare("SELECT locked FROM schools WHERE id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $locked = $stmt->get_result()->fetch_assoc()['locked'] ?? 0;

    if ($locked) {
        $locked_message = "Marks are locked and cannot be edited.";
    } else {
        // Fetch school details
        $stmt = $conn->prepare("SELECT school_name, center_no FROM schools WHERE id = ?");
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $school_result = $stmt->get_result();
        $school = $school_result->fetch_assoc();

        // Fetch candidates with missing marks
        $stmt = $conn->prepare("
            SELECT c.id AS candidate_id, c.index_number, c.candidate_name,
                   COUNT(m.id) AS subject_count
            FROM candidates c
            LEFT JOIN marks m ON c.id = m.candidate_id
            WHERE c.school_id = ?
            GROUP BY c.id
            HAVING COUNT(CASE WHEN m.mark = -1 OR m.mark IS NULL THEN 1 END) > 0
            OR COUNT(m.id) < 4
        ");
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $candidates_result = $stmt->get_result();
        $candidates = [];
        while ($row = $candidates_result->fetch_assoc()) {
            $candidates[$row['candidate_id']] = $row;
        }

        // Fetch all subjects
        $subjects_result = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC");
        $subjects = [];
        while ($row = $subjects_result->fetch_assoc()) {
            $subjects[] = $row;
        }

        // Fetch marks for all candidates
        if (!empty($candidates)) {
            $candidate_ids = array_keys($candidates);
            $placeholders = implode(',', array_fill(0, count($candidate_ids), '?'));
            $stmt = $conn->prepare("SELECT candidate_id, subject_id, mark FROM marks WHERE candidate_id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($candidate_ids)), ...$candidate_ids);
            $stmt->execute();
            $marks_result = $stmt->get_result();
            while ($row = $marks_result->fetch_assoc()) {
                $candidates[$row['candidate_id']]['marks'][$row['subject_id']] = $row['mark'];
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Results - Results Management System</title>
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

        /* Card Styles */
        .card-enhanced {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .card-enhanced:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }

        .card-body p {
            margin: 0.5rem 0;
            font-size: 0.875rem;
            color: #4b5563;
        }

        /* Table Styles */
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

        .form-control {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control[readonly] {
            background: #e5e7eb;
            cursor: not-allowed;
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

        .btn-secondary-enhanced {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        .btn-secondary-enhanced:hover {
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

        .alert-warning {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: white;
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

            .card-enhanced {
                padding: 1rem;
            }

            .table-enhanced {
                font-size: 0.75rem;
            }

            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
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
                        <a class="nav-link" href="home.php">
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
                        <a class="nav-link active" href="#" data-bs-toggle="collapse" data-bs-target="#marksMenu">
                            <i class="fas fa-edit"></i>
                            Capture Marks
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="marksMenu" class="collapse show">
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
                                    <a class="nav-link active" href="check_missing.php">
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
                        <i class="fas fa-edit"></i>
                        Edit Results
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="home.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="check_missing.php">Check Missing Marks</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit Results</li>
                        </ol>
                    </nav>
                </div>

                <?php if ($locked_message): ?>
                    <div class="alert-enhanced alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $locked_message; ?>
                    </div>
                    <a href="check_missing.php" class="btn-enhanced btn-secondary-enhanced">
                        <i class="fas fa-arrow-left"></i>
                        Back
                    </a>
                <?php elseif ($success_message): ?>
                    <div class="alert-enhanced alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success_message; ?>
                    </div>
                    <a href="check_missing.php" class="btn-enhanced btn-secondary-enhanced">
                        <i class="fas fa-arrow-left"></i>
                        Back
                    </a>
                <?php elseif (!$locked): ?>
                    <div class="card-enhanced">
                        <h5 class="card-title">
                            <i class="fas fa-edit"></i>
                            <?php if ($mode === 'single'): ?>
                                Editing Results for <?php echo htmlspecialchars($candidate['candidate_name']); ?> (<?php echo htmlspecialchars($candidate['index_number']); ?>)
                            <?php else: ?>
                                Bulk Edit Results for <?php echo htmlspecialchars($school['school_name']); ?> (<?php echo htmlspecialchars($school['center_no']); ?>)
                            <?php endif; ?>
                        </h5>
                        <div class="card-body">
                            <?php if ($mode === 'single'): ?>
                                <p><strong>School:</strong> <?php echo htmlspecialchars($candidate['school_name']); ?></p>
                                <form id="marksForm" action="" method="POST">
                                    <div class="table-responsive">
                                        <table class="table-enhanced">
                                            <thead>
                                                <tr>
                                                    <th><i class="fas fa-book"></i> Subject Name</th>
                                                    <th><i class="fas fa-star"></i> Mark</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subjects as $subject): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                        <td>
                                                            <input type="number" name="marks[<?php echo $subject['subject_id']; ?>]" 
                                                                   value="<?php echo htmlspecialchars($subject['mark'] ?? -1); ?>" 
                                                                   class="form-control" min="-1" max="100" step="0.01" 
                                                                   <?php echo ($subject['mark'] > 0) ? 'readonly' : ''; ?>
                                                                   required>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" class="btn-enhanced">
                                            <i class="fas fa-save"></i>
                                            Save and Lock Changes
                                        </button>
                                        <a href="check_missing.php" class="btn-enhanced btn-secondary-enhanced">
                                            <i class="fas fa-arrow-left"></i>
                                            Back
                                        </a>
                                    </div>
                                </form>
                            <?php else: ?>
                                <form id="marksForm" action="" method="POST">
                                    <div class="table-responsive">
                                        <table class="table-enhanced">
                                            <thead>
                                                <tr>
                                                    <th><i class="fas fa-id-card"></i> Index No</th>
                                                    <th><i class="fas fa-user"></i> Candidate Name</th>
                                                    <?php foreach ($subjects as $subject): ?>
                                                        <th><?php echo htmlspecialchars($subject['name']); ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($candidates)): ?>
                                                    <?php foreach ($candidates as $candidate): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($candidate['index_number']); ?></td>
                                                            <td><?php echo htmlspecialchars($candidate['candidate_name']); ?></td>
                                                            <?php foreach ($subjects as $subject): ?>
                                                                <td>
                                                                    <input type="number" name="marks[<?php echo $candidate['candidate_id']; ?>][<?php echo $subject['id']; ?>]" 
                                                                           value="<?php echo htmlspecialchars($candidate['marks'][$subject['id']] ?? -1); ?>" 
                                                                           class="form-control" min="-1" max="100" step="0.01" 
                                                                           <?php echo (isset($candidate['marks'][$subject['id']]) && $candidate['marks'][$subject['id']] > 0) ? 'readonly' : ''; ?>
                                                                           required>
                                                                </td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="<?php echo 2 + count($subjects); ?>" class="text-center text-muted">
                                                            <i class="fas fa-inbox"></i>
                                                            No candidates with missing marks
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" class="btn-enhanced">
                                            <i class="fas fa-save"></i>
                                            Save and Lock All Changes
                                        </button>
                                        <a href="check_missing.php" class="btn-enhanced btn-secondary-enhanced">
                                            <i class="fas fa-arrow-left"></i>
                                            Back
                                        </a>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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

            // Card and table row animations
            $('.card-enhanced').each(function() {
                this.style.animation = 'fadeIn 0.3s ease';
            });

            $('.table-enhanced tr').each(function() {
                this.style.animation = 'fadeIn 0.3s ease';
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

            // Form validation and submission
            $('#marksForm').on('submit', function(e) {
                let isValid = true;
                $(this).find('input[type="number"]').each(function() {
                    const value = parseFloat($(this).val());
                    if (!$(this).prop('readonly') && (isNaN(value) || value < -1 || value > 100)) {
                        isValid = false;
                        $(this).addClass('is-invalid');
                        showNotification('Marks must be between -1 and 100', 'error');
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });
                if (!isValid) {
                    e.preventDefault();
                }
            });

            // Input validation on change
            $('input[type="number"]').on('input', function() {
                const value = parseFloat($(this).val());
                if (!$(this).prop('readonly') && (isNaN(value) || value < -1 || value > 100)) {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });

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

            // Show notification for success or locked messages
            <?php if ($success_message): ?>
                showNotification('<?php echo $success_message; ?>', 'success');
            <?php endif; ?>
            <?php if ($locked_message): ?>
                showNotification('<?php echo $locked_message; ?>', 'error');
            <?php endif; ?>
        });
    </script>
</body>
</html>