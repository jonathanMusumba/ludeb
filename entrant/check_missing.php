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

// Pagination settings
$limit = 10; // Schools per page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch schools where the current data entrant submitted marks
$stmt = $conn->prepare("
    SELECT DISTINCT s.id AS school_id, s.center_no, s.school_name,
           GROUP_CONCAT(DISTINCT sub.name) AS subjects_entered
    FROM schools s
    JOIN marks m ON m.school_id = s.id
    JOIN subjects sub ON m.subject_id = sub.id
    WHERE m.submitted_by = ?
    AND s.results_status <> 'Not Declared'
    GROUP BY s.id
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$schools_result = $stmt->get_result();

// Count total schools for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id) AS total
    FROM schools s
    JOIN marks m ON m.school_id = s.id
    WHERE m.submitted_by = ?
    AND s.results_status <> 'Not Declared'
");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_schools = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_schools / $limit);

// Fetch candidates with partial marks for each school (submitted by the current data entrant)
$school_data = [];
if ($schools_result->num_rows > 0) {
    while ($school = $schools_result->fetch_assoc()) {
        $school_id = $school['school_id'];

        // Fetch candidates who have at least one mark but are missing others
        $stmt = $conn->prepare("
            SELECT c.id AS candidate_id, c.index_number, c.candidate_name,
                   COUNT(m.id) AS subject_count,
                   GROUP_CONCAT(DISTINCT CASE WHEN m.mark = -1 OR m.mark IS NULL THEN sub.name END ORDER BY sub.name) AS missing_subjects
            FROM candidates c
            LEFT JOIN marks m ON c.id = m.candidate_id
            LEFT JOIN subjects sub ON m.subject_id = sub.id
            WHERE c.school_id = ?
            AND m.submitted_by = ?
            GROUP BY c.id
            HAVING COUNT(CASE WHEN m.mark > 0 THEN 1 END) > 0
            AND (COUNT(CASE WHEN m.mark = -1 OR m.mark IS NULL THEN 1 END) > 0 OR COUNT(m.id) < 4)
        ");
        $stmt->bind_param("ii", $school_id, $user_id);
        $stmt->execute();
        $candidates_result = $stmt->get_result();
        $candidates = [];
        while ($candidate = $candidates_result->fetch_assoc()) {
            $candidates[] = $candidate;
        }
        $school['candidates'] = $candidates;
        $school_data[$school_id] = $school;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Missing Marks - Results Management System</title>
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

        /* Table Container */
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

        .nested-table {
            width: 100%;
            margin: 0;
            background: rgba(0, 0, 0, 0.02);
        }

        .nested-table th,
        .nested-table td {
            padding: 0.75rem;
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

        /* Pagination */
        .pagination-enhanced .page-link {
            border: none;
            color: var(--dark-color);
            background: rgba(255, 255, 255, 0.9);
            margin: 0 0.25rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .pagination-enhanced .page-link:hover {
            background: var(--primary-color);
            color: white;
        }

        .pagination-enhanced .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
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
                        <i class="fas fa-search"></i>
                        Check Missing Marks
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="home.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Check Missing Marks</li>
                        </ol>
                    </nav>
                </div>

                <!-- Table Container -->
                <div class="table-container">
                    <div class="table-header">
                        <h5 class="table-title">
                            <i class="fas fa-exclamation-circle"></i>
                            Schools with Missing Marks
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table-enhanced">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-id-badge"></i> Center No</th>
                                    <th><i class="fas fa-school"></i> School Name</th>
                                    <th><i class="fas fa-book"></i> Subjects Entered</th>
                                    <th><i class="fas fa-cog"></i> Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($school_data)): ?>
                                    <?php foreach ($school_data as $school): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($school['center_no']); ?></td>
                                            <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                                            <td><?php echo htmlspecialchars($school['subjects_entered']); ?></td>
                                            <td>
                                                <?php if (!empty($school['candidates'])): ?>
                                                    <button class="btn-enhanced btn-sm toggle-candidates" data-school-id="<?php echo $school['school_id']; ?>">
                                                        <i class="fas fa-eye"></i> View Candidates
                                                    </button>
                                                    <a href="edit_results.php?school_id=<?php echo $school['school_id']; ?>" class="btn-enhanced btn-sm">
                                                        <i class="fas fa-edit"></i> Bulk Edit
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">No candidates with missing marks</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr class="candidate-details" id="candidates-<?php echo $school['school_id']; ?>" style="display: none;">
                                            <td colspan="4">
                                                <table class="table-enhanced nested-table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th><i class="fas fa-id-card"></i> Index No</th>
                                                            <th><i class="fas fa-user"></i> Candidate Name</th>
                                                            <th><i class="fas fa-check-circle"></i> Subjects Done</th>
                                                            <th><i class="fas fa-exclamation-circle"></i> Missing Subjects</th>
                                                            <th><i class="fas fa-cog"></i> Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($school['candidates'] as $candidate): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($candidate['index_number']); ?></td>
                                                                <td><?php echo htmlspecialchars($candidate['candidate_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($candidate['subject_count']); ?>/4</td>
                                                                <td><?php echo htmlspecialchars($candidate['missing_subjects'] ?? 'None'); ?></td>
                                                                <td>
                                                                    <a href="edit_results.php?candidate_id=<?php echo $candidate['candidate_id']; ?>" class="btn-enhanced btn-sm">
                                                                        <i class="fas fa-edit"></i> Edit
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-4"><i class="fas fa-inbox text-muted"></i><br>No schools with missing marks found for your entries.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <nav aria-label="Page navigation example" class="mt-3">
                        <ul class="pagination pagination-enhanced justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous"><span aria-hidden="true">«</span></a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next"><span aria-hidden="true">»</span></a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
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

            // Toggle candidate details
            $('.toggle-candidates').click(function() {
                const schoolId = $(this).data('school-id');
                const $detailsRow = $('#candidates-' + schoolId);
                const $icon = $(this).find('i');
                $detailsRow.toggle();
                $icon.toggleClass('fa-eye fa-eye-slash');
                $(this).text($detailsRow.is(':visible') ? ' Hide Candidates' : ' View Candidates');
                $(this).prepend($icon); // Ensure icon stays at the start
            });

            // Table row animation
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