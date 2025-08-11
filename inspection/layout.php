<?php
function renderLayout($title, $data) {
    extract($data);
    // Ensure $content is defined
    $content = isset($content) ? $content : '<div class="alert alert-warning fade-in">Content not available. Please try again or contact the administrator.</div>';
    // Default $current_page if not set
    $current_page = isset($current_page) ? $current_page : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHQk2r6xHgH2vK2Z2yV/1eWV9rV5bW5bW5D7gA+8pW5bW5bW5bW5bW5bW5bW5bW5bW5bW5bW5b" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/drilldown.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <link rel="icon" type="image/x-icon" href="../static/img/icon.ico">
    <style>
        :root {
            --primary-color: #1d3557;
            --secondary-color: #457b9d;
            --accent-color: #e63946;
            --success-color: #2a9d8f;
            --warning-color: #f4a261;
            --info-color: #a8dadc;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #2d3436;
            --text-secondary: #636e72;
            --border-color: #e9ecef;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 15px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 25px rgba(0, 0, 0, 0.12);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            /* Dark mode variables */
            --dark-bg: #1a202c;
            --dark-card-bg: #2d3748;
            --dark-text-primary: #e2e8f0;
            --dark-text-secondary: #a0aec0;
            --dark-border-color: #4a5568;
        }

        [data-theme="dark"] {
            --light-bg: var(--dark-bg);
            --card-bg: var(--dark-card-bg);
            --text-primary: var(--dark-text-primary);
            --text-secondary: var(--dark-text-secondary);
            --border-color: var(--dark-border-color);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
            transition: var(--transition);
        }

        /* Topbar */
        .topbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            box-shadow: var(--shadow-lg);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .topbar .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: white !important;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .topbar .info-badges {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .topbar .badge {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }

        .topbar .badge:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .topbar .btn-danger {
            background: var(--accent-color);
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            transition: var(--transition);
        }

        .topbar .btn-danger:hover {
            background: #d00000;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(230, 57, 70, 0.3);
        }

        .theme-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .theme-toggle:hover {
            color: var(--info-color);
        }

        /* Sidebar */
        .sidebar {
            background: var(--card-bg);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            padding-top: 80px;
            transition: transform 0.3s ease, background-color 0.3s ease;
            box-shadow: var(--shadow-lg);
            border-right: 1px solid var(--border-color);
            z-index: 1040;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1030;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .sidebar .nav-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 10px;
        }

        .sidebar .nav-header h6 {
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 1px;
            margin: 0;
        }

        .sidebar .nav-link {
            color: var(--text-primary);
            padding: 14px 20px;
            margin: 4px 12px;
            border-radius: var(--border-radius);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s ease;
        }

        .sidebar .nav-link:hover::before {
            left: 100%;
        }

        .sidebar .nav-link:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            transform: translateX(4px);
            box-shadow: var(--shadow-md);
        }

        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .sidebar .nav-sublink {
            padding-left: 40px;
            font-size: 0.9rem;
        }

        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .collapse.show .nav-sublink {
            animation: slideIn 0.4s ease-out;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 100px 30px 30px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        .welcome-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .welcome-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            transform: rotate(45deg);
        }

        .welcome-header h2 {
            font-weight: 700;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .welcome-header p {
            opacity: 0.9;
            position: relative;
            z-index: 1;
            font-size: 1rem;
        }

        /* Stat Cards */
        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .stat-card .stat-icon i {
            font-size: 1.5rem;
        }

        .stat-card .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
            line-height: 1;
        }

        .stat-card .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Chart and Table Cards */
        .chart-card, .table-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .chart-card:hover, .table-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .chart-card h5, .table-card h5 {
            margin-bottom: 20px;
            font-weight: 600;
            color: var(--primary-color);
            position: relative;
            padding-bottom: 10px;
        }

        .chart-card h5::after, .table-card h5::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        #pie-chart, #bar-chart, #subject-chart {
            height: 400px;
            width: 100%;
        }

        /* Tables */
        .table {
            margin-bottom: 0;
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .table th {
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            padding: 15px 12px;
        }

        .table td {
            padding: 12px;
            vertical-align: middle;
            border-color: var(--border-color);
            font-size: 0.9rem;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(29, 53, 87, 0.02);
        }

        .table tbody tr:hover {
            background-color: rgba(29, 53, 87, 0.05);
            transform: scale(1.01);
            transition: var(--transition);
        }

        /* Mobile Responsive Styles */
        @media (max-width: 1199.98px) {
            .main-content {
                margin-left: 0;
                padding: 90px 20px 20px;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            #pie-chart, #bar-chart, #subject-chart {
                height: 300px;
            }
        }

        @media (max-width: 991.98px) {
            .topbar {
                padding: 10px 15px;
            }

            .topbar .info-badges {
                display: none;
            }

            .welcome-header {
                padding: 20px;
                text-align: center;
            }

            .welcome-header h2 {
                font-size: 1.5rem;
            }

            .stat-card {
                text-align: center;
                padding: 20px;
            }

            .stat-card .stat-icon {
                margin: 0 auto 15px;
            }

            .chart-card, .table-card {
                padding: 20px;
            }

            #pie-chart, #bar-chart, #subject-chart {
                height: 250px;
            }

            .table-responsive {
                font-size: 0.9rem;
            }

            .table th, .table td {
                padding: 10px;
            }
        }

        @media (max-width: 767.98px) {
            .main-content {
                padding: 90px 15px 15px;
            }

            .topbar .navbar-brand {
                font-size: 1.1rem;
            }

            .stat-card .stat-value {
                font-size: 2rem;
            }

            .table-responsive {
                border-radius: var(--border-radius);
            }

            .sidebar {
                width: 100%;
            }

            .table th, .table td {
                padding: 8px;
            }

            .table-responsive-sm .table th:not(:first-child):not(:nth-child(2)):not(:last-child),
            .table-responsive-sm .table td:not(:first-child):not(:nth-child(2)):not(:last-child) {
                display: none;
            }
        }

        @media (max-width: 575.98px) {
            .topbar {
                padding: 8px 12px;
            }

            .welcome-header {
                padding: 15px;
            }

            .welcome-header h2 {
                font-size: 1.25rem;
            }

            .welcome-header p {
                font-size: 0.9rem;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card .stat-value {
                font-size: 1.75rem;
            }

            .chart-card, .table-card {
                padding: 15px;
            }

            .chart-card h5, .table-card h5 {
                font-size: 1rem;
            }

            #pie-chart, #bar-chart, #subject-chart {
                height: 200px;
            }

            .table-responsive-sm .table th:not(:first-child):not(:last-child),
            .table-responsive-sm .table td:not(:first-child):not(:last-child) {
                display: none;
            }
        }

        /* Loading Animation */
        .loading-spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
        }

        .spinner-border {
            color: var(--primary-color);
        }

        /* Custom Scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 2px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-in {
            animation: slideIn 0.4s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="loading-spinner">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Topbar -->
    <nav class="topbar">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-light d-lg-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="#" class="navbar-brand d-none d-md-block">
                    <i class="fas fa-graduation-cap me-2"></i>
                    Exam Dashboard
                </a>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                    <i class="fas fa-moon"></i>
                </button>
                <div class="info-badges d-none d-lg-flex">
                    <span class="badge">
                        <i class="fas fa-building me-1"></i>
                        <?php echo htmlspecialchars($exam_body); ?>
                    </span>
                    <span class="badge">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo htmlspecialchars($exam_year); ?>
                    </span>
                    <span class="badge">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($username); ?>
                    </span>
                </div>
                <a href="../logout.php" class="btn btn-danger btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>
                    <span class="d-none d-sm-inline">Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <nav id="sidebar" class="sidebar">
        <div class="nav-header">
            <h6>Navigation Menu</h6>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link slide-in <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" href="AdminDashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link slide-in <?php echo in_array($current_page, ['list_schools', 'registered_schools', 'unregistered_schools']) ? 'active' : ''; ?>" href="#schoolsSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($current_page, ['list_schools', 'registered_schools', 'unregistered_schools']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-school"></i>
                    <span>Schools</span>
                </a>
                <ul class="collapse nav flex-column <?php echo in_array($current_page, ['list_schools', 'registered_schools', 'unregistered_schools']) ? 'show' : ''; ?>" id="schoolsSubmenu">
                    <li class="nav-item">
                        <a class="nav-link nav-sublink slide-in <?php echo $current_page == 'list_schools' ? 'active' : ''; ?>" href="list_schools.php">
                            <i class="fas fa-list"></i>
                            <span>List Schools</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-sublink slide-in <?php echo $current_page == 'registered_schools' ? 'active' : ''; ?>" href="registered_schools.php">
                            <i class="fas fa-check-circle"></i>
                            <span>Registered Schools</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-sublink slide-in <?php echo $current_page == 'unregistered_schools' ? 'active' : ''; ?>" href="unregistered_schools.php">
                            <i class="fas fa-times-circle"></i>
                            <span>Unregistered Schools</span>
                        </a>
                    </li>
                </ul>
            </li>
           <li class="nav-item">
    <a class="nav-link slide-in <?php echo in_array($current_page, ['district_results', 'detailed_report']) ? 'active' : ''; ?>" href="#reportsSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($current_page, ['district_results', 'detailed_report']) ? 'true' : 'false'; ?>">
        <i class="fas fa-chart-line"></i>
        <span>Reports</span>
    </a>
    <ul class="collapse nav flex-column <?php echo in_array($current_page, ['district_results', 'detailed_report']) ? 'show' : ''; ?>" id="reportsSubmenu">
        <li class="nav-item">
            <?php 
            $district_url = 'district_results.php';
            if (isset($exam_year_id) && $exam_year_id > 0) {
                $district_url .= '?exam_year_id=' . urlencode($exam_year_id);
            } elseif (isset($_GET['exam_year_id']) && $_GET['exam_year_id'] > 0) {
                $district_url .= '?exam_year_id=' . urlencode($_GET['exam_year_id']);
            }
            ?>
            <a class="nav-link nav-sublink slide-in <?php echo $current_page == 'district_results' ? 'active' : ''; ?>" href="<?php echo $district_url; ?>">
                <i class="fas fa-file-alt"></i>
                <span>District Results</span>
            </a>
        </li>
        <li class="nav-item">
            <?php 
            $detailed_url = 'detailed_report.php';
            if (isset($exam_year_id) && $exam_year_id > 0) {
                $detailed_url .= '?exam_year_id=' . urlencode($exam_year_id);
            } elseif (isset($_GET['exam_year_id']) && $_GET['exam_year_id'] > 0) {
                $detailed_url .= '?exam_year_id=' . urlencode($_GET['exam_year_id']);
            }
            ?>
            <a class="nav-link nav-sublink slide-in <?php echo $current_page == 'detailed_report' ? 'active' : ''; ?>" href="<?php echo $detailed_url; ?>">
                <i class="fas fa-file-signature"></i>
                <span>Detailed Report</span>
            </a>
        </li>
    </ul>
</li>
            <li class="nav-item">
                <a class="nav-link slide-in <?php echo $current_page == 'chat' ? 'active' : ''; ?>" href="chat.php">
                    <i class="fas fa-comments"></i>
                    <span>Team Chat</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link slide-in <?php echo $current_page == 'settings' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="container-fluid">
        <main class="main-content">
            <?php echo $content; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {
            // Show loading spinner
            $('.loading-spinner').show();

            // Initialize charts after page load
            setTimeout(function() {
                initializeCharts();
                $('.loading-spinner').hide();
            }, 500);

            // Sidebar toggle functionality
            $('#sidebarToggle').click(function() {
                $('#sidebar').addClass('show');
                $('#sidebarOverlay').addClass('show');
                $('body').css('overflow', 'hidden');
            });

            $('#sidebarOverlay').click(function() {
                closeSidebar();
            });

            function closeSidebar() {
                $('#sidebar').removeClass('show');
                $('#sidebarOverlay').removeClass('show');
                $('body').css('overflow', 'auto');
            }

            // Close sidebar on window resize if larger than lg
            $(window).resize(function() {
                if ($(window).width() >= 1200) {
                    closeSidebar();
                }
            });

            // Dark mode toggle
            $('#themeToggle').click(function() {
                const body = $('body');
                const currentTheme = body.attr('data-theme') === 'dark' ? 'light' : 'dark';
                body.attr('data-theme', currentTheme);
                $(this).find('i').toggleClass('fa-moon fa-sun');
                localStorage.setItem('theme', currentTheme);
                // Redraw charts to apply theme
                initializeCharts();
            });

            // Load saved theme
            if (localStorage.getItem('theme') === 'dark') {
                $('body').attr('data-theme', 'dark');
                $('#themeToggle i').removeClass('fa-moon').addClass('fa-sun');
            }

            // Enhanced navigation link clicks
            $('.nav-link').click(function(e) {
                if ($(this).attr('href') !== '#' && !$(this).attr('data-bs-toggle')) {
                    $('.loading-spinner').show();
                }
            });

            // Initialize charts
            function initializeCharts() {
                // Prepare drilldown data for Division by Gender
                const drilldownData = <?php
                    $drilldown = [];
                    $totalCandidates = array_sum(array_column($divisions, 'total_candidates'));
                    foreach ($chart_data['categories'] as $index => $category) {
                        $male = $chart_data['male'][$index];
                        $female = $chart_data['female'][$index];
                        $total = $male + $female;
                        $malePercent = $total > 0 ? ($male / $total * 100) : 0;
                        $femalePercent = $total > 0 ? ($female / $total * 100) : 0;
                        $drilldown[] = [
                            'id' => $category,
                            'name' => $category,
                            'data' => [
                                ['name' => 'Male', 'y' => $male, 'percentage' => round($malePercent, 1)],
                                ['name' => 'Female', 'y' => $female, 'percentage' => round($femalePercent, 1)]
                            ]
                        ];
                    }
                    echo json_encode($drilldown);
                ?>;

                // Enhanced Pie Chart
                Highcharts.chart('pie-chart', {
                    chart: {
                        type: 'pie',
                        backgroundColor: 'transparent',
                        style: { fontFamily: 'Inter, sans-serif' }
                    },
                    title: {
                        text: 'Results Distribution - <?php echo htmlspecialchars($exam_year); ?>',
                        style: {
                            fontSize: '16px',
                            fontWeight: '600',
                            color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#1d3557'
                        }
                    },
                    colors: ['#1d3557', '#457b9d', '#a8dadc', '#f1faee', '#e63946', '#2a9d8f'],
                    plotOptions: {
                        pie: {
                            allowPointSelect: true,
                            cursor: 'pointer',
                            showInLegend: true,
                            dataLabels: {
                                enabled: true,
                                format: '{point.name}: {point.percentage:.1f}%',
                                style: { fontSize: '12px', fontWeight: '500' }
                            },
                            borderWidth: 2,
                            borderColor: $('body').attr('data-theme') === 'dark' ? '#4a5568' : '#ffffff',
                            innerSize: '40%'
                        }
                    },
                    series: [{
                        name: 'Candidates',
                        data: <?php echo json_encode($pie_chart_data); ?>,
                        animation: { duration: 1000 }
                    }],
                    tooltip: {
                        pointFormat: '<b>{point.y}</b> candidates ({point.percentage:.1f}%)',
                        style: { fontSize: '12px' }
                    },
                    legend: {
                        align: 'bottom',
                        verticalAlign: 'bottom',
                        layout: 'horizontal',
                        itemStyle: {
                            fontSize: '12px',
                            fontWeight: '500',
                            color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#2d3436'
                        }
                    },
                    accessibility: {
                        enabled: true,
                        point: {
                            valueSuffix: ' candidates'
                        }
                    }
                });

                // Enhanced Division by Gender Bar Chart with Drilldown
                Highcharts.chart('bar-chart', {
                    chart: {
                        type: 'bar',
                        backgroundColor: 'transparent',
                        style: { fontFamily: 'Inter, sans-serif' }
                    },
                    title: {
                        text: 'Gender Distribution by Division - <?php echo htmlspecialchars($exam_year); ?>',
                        style: {
                            fontSize: '16px',
                            fontWeight: '600',
                            color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#1d3557'
                        }
                    },
                    xAxis: {
                        categories: <?php echo json_encode($chart_data['categories']); ?>,
                        title: {
                            text: 'Divisions',
                            style: { fontSize: '12px', fontWeight: '500' }
                        },
                        labels: {
                            style: {
                                fontSize: '11px',
                                color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#2d3436'
                            }
                        }
                    },
                    yAxis: {
                        title: {
                            text: 'Number of Candidates',
                            style: { fontSize: '12px', fontWeight: '500' }
                        },
                        labels: {
                            style: {
                                fontSize: '11px',
                                color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#2d3436'
                            }
                        }
                    },
                    series: [{
                        name: 'Total Candidates',
                        data: <?php
                            $seriesData = [];
                            foreach ($chart_data['categories'] as $index => $category) {
                                $total = $chart_data['male'][$index] + $chart_data['female'][$index];
                                $seriesData[] = [
                                    'y' => $total,
                                    'drilldown' => $category
                                ];
                            }
                            echo json_encode($seriesData);
                        ?>,
                        color: {
                            linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                            stops: [
                                [0, '#457b9d'],
                                [1, '#1d3557']
                            ]
                        },
                        animation: { duration: 1000 }
                    }],
                    drilldown: {
                        series: drilldownData
                    },
                    plotOptions: {
                        bar: {
                            borderWidth: 0,
                            borderRadius: 6,
                            groupPadding: 0.1,
                            pointPadding: 0.05,
                            dataLabels: {
                                enabled: true,
                                format: '{y}',
                                style: { fontSize: '10px', fontWeight: '500' }
                            }
                        }
                    },
                    tooltip: {
                        shared: true,
                        pointFormatter: function() {
                            const total = this.y;
                            const male = <?php echo json_encode($chart_data['male']); ?>[this.index];
                            const female = <?php echo json_encode($chart_data['female']); ?>[this.index];
                            const malePercent = total > 0 ? (male / total * 100).toFixed(1) : 0;
                            const femalePercent = total > 0 ? (female / total * 100).toFixed(1) : 0;
                            return `<b>${this.category}</b><br>` +
                                   `Total: ${total}<br>` +
                                   `Male: ${male} (${malePercent}%)<br>` +
                                   `Female: ${female} (${femalePercent}%)`;
                        },
                        style: { fontSize: '12px' }
                    },
                    legend: {
                        align: 'bottom',
                        verticalAlign: 'bottom',
                        itemStyle: {
                            fontSize: '12px',
                            fontWeight: '500',
                            color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#2d3436'
                        }
                    },
                    accessibility: {
                        enabled: true,
                        point: {
                            valueSuffix: ' candidates'
                        }
                    }
                });

                // Enhanced Subject Performance Chart
                Highcharts.chart('subject-chart', {
                    chart: {
                        type: 'column',
                        backgroundColor: 'transparent',
                        style: { fontFamily: 'Inter, sans-serif' }
                    },
                    title: {
                        text: 'Subject Performance Overview - <?php echo htmlspecialchars($exam_year); ?>',
                        style: {
                            fontSize: '16px',
                            fontWeight: '600',
                            color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#1d3557'
                        }
                    },
                    xAxis: {
                        categories: <?php echo json_encode($subject_chart_data['categories']); ?>,
                        title: {
                            text: 'Subjects',
                            style: { fontSize: '12px', fontWeight: '500' }
                        },
                        labels: {
                            rotation: -45,
                            style: {
                                fontSize: '10px',
                                color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#2d3436'
                            }
                        }
                    },
                    yAxis: {
                        title: {
                            text: 'Average Marks (%)',
                            style: { fontSize: '12px', fontWeight: '500' }
                        },
                        max: 100,
                        labels: {
                            style: {
                                fontSize: '11px',
                                color: $('body').attr('data-theme') === 'dark' ? '#e2e8f0' : '#2d3436'
                            }
                        }
                    },
                    series: [{
                        name: 'Average Marks',
                        data: <?php echo json_encode($subject_chart_data['data']); ?>,
                        color: {
                            linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                            stops: [
                                [0, '#457b9d'],
                                [1, '#a8dadc']
                            ]
                        },
                        animation: { duration: 1200 }
                    }],
                    plotOptions: {
                        column: {
                            dataLabels: {
                                enabled: true,
                                format: '{y:.1f}%',
                                style: { fontSize: '10px', fontWeight: '500' }
                            },
                            borderWidth: 0,
                            borderRadius: 6,
                            pointPadding: 0.1,
                            groupPadding: 0.05
                        }
                    },
                    tooltip: {
                        pointFormat: '<b>{point.y:.1f}%</b> average marks',
                        style: { fontSize: '12px' }
                    },
                    legend: { enabled: false },
                    accessibility: {
                        enabled: true,
                        point: {
                            valueSuffix: '%'
                        }
                    }
                });
            }

            // Add smooth scrolling for anchor links
            $('a[href^="#"]').on('click', function(event) {
                var target = $(this.getAttribute('href'));
                if (target.length) {
                    event.preventDefault();
                    $('html, body').stop().animate({
                        scrollTop: target.offset().top - 100
                    }, 1000);
                }
            });

            // Add loading state to buttons
            $('.btn').on('click', function() {
                var $btn = $(this);
                if (!$btn.hasClass('btn-outline-light') && !$btn.hasClass('theme-toggle')) {
                    $btn.prop('disabled', true);
                    var originalText = $btn.html();
                    $btn.html('<i class="fas fa-spinner fa-spin me-1"></i>Loading...');
                    setTimeout(function() {
                        $btn.prop('disabled', false);
                        $btn.html(originalText);
                    }, 2000);
                }
            });

            // Add intersection observer for animations
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }
                    });
                }, { threshold: 0.1 });

                document.querySelectorAll('.fade-in').forEach((el) => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(20px)';
                    el.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
                    observer.observe(el);
                });
            }

            // Add table row click effects
            $('.table tbody tr').on('click', function() {
                $(this).addClass('table-active');
                setTimeout(() => {
                    $(this).removeClass('table-active');
                }, 200);
            });

            // Enhanced responsive behavior
            function handleResize() {
                const width = $(window).width();
                if (width < 1200) {
                    $('.sidebar').removeClass('show');
                    $('.sidebar-overlay').removeClass('show');
                    $('body').css('overflow', 'auto');
                }
                // Redraw charts on resize
                setTimeout(() => {
                    if (typeof Highcharts !== 'undefined') {
                        Highcharts.charts.forEach(chart => {
                            if (chart) {
                                chart.reflow();
                            }
                        });
                    }
                }, 100);
            }

            $(window).on('resize', handleResize);

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
<?php
}
?>