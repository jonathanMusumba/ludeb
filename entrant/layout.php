<?php
session_start();
require_once 'db_connect.php';

// Restrict to authenticated users (customizable per page via $allowed_roles)
$allowed_roles = isset($allowed_roles) ? $allowed_roles : ['Data Entrant', 'System Admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: ../login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);
$user_id = $_SESSION['user_id'];

// Enable error logging, disable display
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:\xampp\htdocs\ludeb\setup_errors.log');

// Fetch board name and exam year
try {
    $stmt = $conn->prepare("SELECT s.board_name, e.exam_year 
                            FROM settings s 
                            JOIN exam_years e ON s.exam_year_id = e.id 
                            ORDER BY s.id DESC LIMIT 1");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $row = $stmt->get_result()->fetch_assoc();
    $board_name = $row['board_name'] ?? 'Luuka Examination Board';
    $exam_year = $row['exam_year'] ?? date('Y');
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching board name and exam year in layout.php: " . $e->getMessage(), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    $board_name = 'Luuka Examination Board';
    $exam_year = date('Y');
}

// Log action (customizable per page via $log_action and $log_description)
if (isset($log_action) && isset($log_description)) {
    try {
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed for log_action: " . $conn->error);
        }
        $stmt->bind_param("sis", $log_action, $user_id, $log_description);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for log_action: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Failed to log action in layout.php: " . $e->getMessage(), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Results Management System'; ?> - Results Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (isset($extra_head)) echo $extra_head; ?>
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
            overflow-x: hidden;
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
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
            cursor: pointer;
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

            .sidebar-nav {
                padding: 0.5rem 0;
            }

            .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            .sidebar-brand {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .topbar {
                flex-direction: column;
                gap: 0.5rem;
                padding: 0.75rem 1rem;
            }

            .topbar-right {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-logout {
                width: 100%;
                justify-content: center;
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
                        <span class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'home.php') ? 'active' : ''; ?>" data-page="home.php">
                            <i class="fas fa-chart-line"></i>
                            Dashboard
                        </span>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'schools.php') ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#schoolsMenu">
                            <i class="fas fa-school"></i>
                            Schools
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </span>
                        <div id="schoolsMenu" class="collapse <?php echo (basename($_SERVER['PHP_SELF']) == 'schools.php') ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <span class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'schools.php') ? 'active' : ''; ?>" data-page="schools.php">
                                        <i class="fas fa-list"></i>
                                        List Schools
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['marks.php', 'see_marks.php', 'check_missing.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#marksMenu">
                            <i class="fas fa-edit"></i>
                            Capture Marks
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </span>
                        <div id="marksMenu" class="collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['marks.php', 'see_marks.php', 'check_missing.php']) ? 'show' : ''; ?>">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <span class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'marks.php') ? 'active' : ''; ?>" data-page="marks.php">
                                        <i class="fas fa-plus"></i>
                                        Enter Marks
                                    </span>
                                </li>
                                <li class="nav-item">
                                    <span class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'see_marks.php') ? 'active' : ''; ?>" data-page="see_marks.php">
                                        <i class="fas fa-eye"></i>
                                        View Results
                                    </span>
                                </li>
                                <li class="nav-item">
                                    <span class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'check_missing.php') ? 'active' : ''; ?>" data-page="check_missing.php">
                                        <i class="fas fa-search"></i>
                                        Check Missing
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'chat.php') ? 'active' : ''; ?>" data-page="chat.php">
                            <i class="fas fa-comments"></i>
                            Team Chat
                        </span>
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
                <?php echo isset($content) ? $content : ''; ?>
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

        // Navigation handling
        document.querySelectorAll('.nav-link[data-page]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = this.getAttribute('data-page');
                if (page) {
                    window.location.href = page;
                }
            });
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
            .notification {
                animation: slideIn 0.3s ease;
            }
            @keyframes slideIn {
                from { transform: translateX(100%); }
                to { transform: translateX(0); }
            }
        `;
        document.head.appendChild(style);
    </script>
    <?php if (isset($extra_scripts)) echo $extra_scripts; ?>
</body>
</html>
<?php if (isset($conn)) $conn->close(); ?>