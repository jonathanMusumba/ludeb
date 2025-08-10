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
$is_data_entrant = $_SESSION['role'] === 'Data Entrant';

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');

// Fetch schools for autocomplete
$schools = $conn->query("SELECT id, school_name, center_no FROM schools");

// Fetch candidates and their marks based on the selected school
$selected_school_name = '';
$candidates = [];
if (isset($_GET['school_id'])) {
    $school_id = intval($_GET['school_id']);
    $stmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $selected_school_name = $result->fetch_assoc()['school_name'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, candidate_name, index_number FROM candidates WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $candidates_result = $stmt->get_result();
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[$row['id']] = [
            'name' => $row['candidate_name'],
            'index_number' => $row['index_number'],
            'marks' => []
        ];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT candidate_id, subject_id, mark FROM marks WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $marks_result = $stmt->get_result();
    while ($row = $marks_result->fetch_assoc()) {
        $candidates[$row['candidate_id']]['marks'][$row['subject_id']] = $row['mark'];
    }
    $stmt->close();
}

$subjects = $conn->query("SELECT id, name, code FROM subjects");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retrieve Marks - Results Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
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

        .table-filter {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .table-filter label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }

        .table-filter input {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            background: white;
            min-width: 160px;
        }

        .table-filter input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 5px rgba(79, 70, 229, 0.3);
            outline: none;
        }

        .ui-autocomplete {
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .ui-autocomplete .ui-menu-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
        }

        .ui-autocomplete .ui-menu-item:hover {
            background: rgba(79, 70, 229, 0.1);
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

        /* Disable printing for Data Entrants */
        <?php if ($is_data_entrant): ?>
        @media print {
            body {
                display: none !important;
            }
        }
        <?php endif; ?>

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

            .table-filter {
                flex-direction: column;
                align-items: flex-start;
            }

            .table-filter input {
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
                                    <a class="nav-link active" href="see_marks.php">
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
                        <i class="fas fa-eye"></i>
                        Retrieve Marks
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="home.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Retrieve Marks</li>
                        </ol>
                    </nav>
                </div>

                <!-- Table Container -->
                <div class="table-container">
                    <div class="table-header">
                        <h5 class="table-title">
                            <i class="fas fa-table"></i>
                            Marks Overview
                        </h5>
                        <form method="GET" action="" class="table-filter">
                            <label for="school_name">Select School:</label>
                            <input type="text" id="school_name" name="school_name" class="form-control" value="<?= isset($_GET['school_name']) ? htmlspecialchars($_GET['school_name']) : '' ?>" required>
                            <input type="hidden" id="school_id" name="school_id" value="<?= isset($_GET['school_id']) ? htmlspecialchars($_GET['school_id']) : '' ?>">
                            <button type="submit" class="btn-enhanced"><i class="fas fa-search"></i> Search</button>
                        </form>
                    </div>
                    <?php if (!empty($candidates)): ?>
                        <h5 class="mb-3">SCHOOL: <?= htmlspecialchars($selected_school_name) ?></h5>
                        <div class="table-responsive">
                            <table class="table-enhanced">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-id-card"></i> Index Number</th>
                                        <th><i class="fas fa-user"></i> Candidate Name</th>
                                        <?php $subjects->data_seek(0); while($subject = $subjects->fetch_assoc()): ?>
                                            <th><?= htmlspecialchars($subject['code']) ?></th>
                                        <?php endwhile; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($candidates as $candidate): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($candidate['index_number']) ?></td>
                                            <td><?= htmlspecialchars($candidate['name']) ?></td>
                                            <?php $subjects->data_seek(0); while($subject = $subjects->fetch_assoc()): ?>
                                                <td>
                                                    <?php
                                                    $mark = isset($candidate['marks'][$subject['id']]) ? $candidate['marks'][$subject['id']] : null;
                                                    echo $mark === -1 || $mark === null ? 'NA' : htmlspecialchars($mark);
                                                    ?>
                                                </td>
                                            <?php endwhile; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted"><i class="fas fa-inbox"></i> No candidates or marks found for the selected school.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
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

            // Autocomplete for school selection
            $("#school_name").autocomplete({
                source: "get_school.php",
                minLength: 2,
                select: function(event, ui) {
                    $("#school_name").val(ui.item.value);
                    $("#school_id").val(ui.item.id);
                    $(this.form).submit();
                }
            }).autocomplete("instance")._renderItem = function(ul, item) {
                return $("<li>")
                    .append("<div>" + item.value + "</div>")
                    .appendTo(ul);
            };

            // Table row animation
            $('.table-enhanced tr').each(function() {
                this.style.animation = 'fadeIn 0.3s ease';
            });

            // Prevent printing and exporting for Data Entrants
            <?php if ($is_data_entrant): ?>
            document.addEventListener('keydown', function(event) {
                if (event.ctrlKey && (event.key === 'p' || event.key === 'P')) {
                    event.preventDefault();
                    showNotification('Printing is disabled for Data Entrants.', 'error');
                }
            });

            // Prevent context menu actions that could lead to exporting
            document.addEventListener('contextmenu', function(event) {
                const target = event.target.closest('.table-container');
                if (target) {
                    event.preventDefault();
                    showNotification('Exporting is disabled for Data Entrants.', 'error');
                }
            });
            <?php endif; ?>

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
<?php $conn->close(); ?>