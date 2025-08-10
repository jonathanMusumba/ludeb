<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['public_user_id'])) {
    header('Location: login.php');
    exit();
}

// Database connection
require_once '../config/database.php';

$board_name = "Luuka Examination Board";
$user_id = $_SESSION['public_user_id'];
$user_name = $_SESSION['public_user_name'];
$user_role = $_SESSION['public_user_role'];
$access_level = $_SESSION['public_access_level'];
$payment_status = $_SESSION['public_payment_status'];

// Fetch user details and stats
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT d.id) as downloads_count,
               COUNT(DISTINCT f.id) as favorites_count
        FROM public_users u
        LEFT JOIN user_downloads d ON u.id = d.user_id
        LEFT JOIN user_favorites f ON u.id = f.user_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch recent downloads
    $stmt = $pdo->prepare("
        SELECT resource_name, resource_type, download_date
        FROM user_downloads 
        WHERE user_id = ? 
        ORDER BY download_date DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch available resources based on access level
    $access_condition = $access_level === 'premium' ? '' : "AND (is_premium = 0 OR is_premium IS NULL)";
    $stmt = $pdo->prepare("
        SELECT category, COUNT(*) as count 
        FROM resources 
        WHERE status = 'active' $access_condition
        GROUP BY category
    ");
    $stmt->execute();
    $resource_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Unable to load dashboard data. Please try again later.";
}

// Handle logout
if (isset($_GET['logout'])) {
    // Clear remember token
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        
        try {
            $stmt = $pdo->prepare("UPDATE public_users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            // Silent fail
        }
    }
    
    // Destroy session
    session_destroy();
    header('Location: login.php?logout=1');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($board_name); ?></title>
    <meta name="description" content="Your educational resources dashboard">
    
    <!-- External Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-gold: #ffd700;
            --secondary-gold: #ffc107;
            --accent-blue: #0066cc;
            --success-green: #28a745;
            --danger-red: #dc3545;
            --warning-orange: #fd7e14;
            --info-cyan: #17a2b8;
            --dark-blue: #1e3c72;
            --light-blue: #2a5298;
            --gradient-primary: linear-gradient(135deg, var(--primary-gold) 0%, var(--secondary-gold) 100%);
            --gradient-blue: linear-gradient(135deg, var(--dark-blue) 0%, var(--light-blue) 100%);
            --gradient-success: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: var(--gradient-blue);
            min-height: 100vh;
            width: 280px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar-brand {
            color: var(--primary-gold);
            font-size: 1.3rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.2rem;
            position: absolute;
            top: 1.5rem;
            right: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            color: var(--primary-gold);
        }

        .sidebar-nav {
            padding: 2rem 0;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 0;
            font-weight: 500;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--primary-gold);
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(255, 215, 0, 0.2);
            color: var(--primary-gold);
            border-right: 3px solid var(--primary-gold);
        }

        .nav-icon {
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        .main-content.expanded {
            margin-left: 80px;
        }

        /* Top Bar */
        .top-bar {
            background: #fff;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: between;
            align-items: center;
            gap: 1rem;
        }

        .welcome-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            flex-grow: 1;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .access-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-free {
            background: #e9ecef;
            color: #495057;
        }

        .badge-premium {
            background: var(--gradient-primary);
            color: #000;
        }

        .badge-pending {
            background: var(--gradient-warning);
            color: #fff;
        }

        .dropdown-toggle::after {
            display: none;
        }

        /* Dashboard Content */
        .dashboard-content {
            padding: 2rem;
        }

        .stats-row {
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fff;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #fff;
            margin-bottom: 1rem;
        }

        .stat-downloads {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-favorites {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-progress {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-resources {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #333;
            line-height: 1;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Content Cards */
        .content-card {
            background: #fff;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .card-header {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .recent-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f8f9fa;
            transition: all 0.3s ease;
        }

        .recent-item:hover {
            background: #f8f9fa;
            border-radius: 10px;
            margin: 0 -1rem;
            padding: 1rem;
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        .recent-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1rem;
        }

        .icon-pdf {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }

        .icon-video {
            background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
        }

        .icon-doc {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }

        .recent-info {
            flex-grow: 1;
        }

        .recent-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.2rem;
        }

        .recent-meta {
            font-size: 0.85rem;
            color: #666;
        }

        .quick-action {
            background: #fff;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            display: block;
            height: 100%;
        }

        .quick-action:hover {
            border-color: var(--accent-blue);
            background: rgba(0, 102, 204, 0.05);
            transform: translateY(-5px);
            color: var(--accent-blue);
        }

        .quick-action i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--accent-blue);
            transition: all 0.3s ease;
        }

        .quick-action:hover i {
            transform: scale(1.1);
            color: var(--primary-gold);
        }

        .quick-action h5 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .access-status {
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .status-free {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1565c0;
        }

        .status-premium {
            background: var(--gradient-primary);
            color: #000;
        }

        .status-pending {
            background: var(--gradient-warning);
            color: #fff;
        }

        .upgrade-btn {
            background: var(--gradient-primary);
            border: none;
            color: #000;
            padding: 0.8rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
        }

        .upgrade-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.4);
            color: #000;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content.expanded {
                margin-left: 0;
            }
            
            .top-bar {
                padding: 1rem;
            }
            
            .dashboard-content {
                padding: 1rem;
            }
            
            .mobile-menu-btn {
                display: block !important;
            }
        }

        .mobile-menu-btn {
            display: none;
            background: var(--accent-blue);
            color: #fff;
            border: none;
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 1.1rem;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        @media (max-width: 768px) {
            .sidebar-overlay.show {
                display: block;
            }
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 8px;
            height: 1rem;
            margin-bottom: 0.5rem;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .progress-ring {
            transform: rotate(-90deg);
        }

        .progress-ring-circle {
            fill: transparent;
            stroke: #e9ecef;
            stroke-width: 8;
        }

        .progress-ring-progress {
            fill: transparent;
            stroke: var(--accent-blue);
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dasharray 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-graduation-cap"></i>
                <span class="brand-text">EduPortal</span>
            </a>
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="#dashboard" class="nav-link active">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="resources/" class="nav-link">
                        <i class="nav-icon fas fa-book"></i>
                        <span class="nav-text">Browse Resources</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="downloads.php" class="nav-link">
                        <i class="nav-icon fas fa-download"></i>
                        <span class="nav-text">My Downloads</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="favorites.php" class="nav-link">
                        <i class="nav-icon fas fa-heart"></i>
                        <span class="nav-text">Favorites</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="progress.php" class="nav-link">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <span class="nav-text">Progress</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="nav-icon fas fa-user-cog"></i>
                        <span class="nav-text">Profile Settings</span>
                    </a>
                </li>
                <?php if ($access_level !== 'premium'): ?>
                <li class="nav-item">
                    <a href="upgrade.php" class="nav-link" style="color: var(--primary-gold) !important;">
                        <i class="nav-icon fas fa-crown"></i>
                        <span class="nav-text">Upgrade to Premium</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item mt-3">
                    <a href="?logout=1" class="nav-link" onclick="return confirm('Are you sure you want to logout?')">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <span class="nav-text">Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="top-bar">
            <button class="mobile-menu-btn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="welcome-text">
                <i class="fas fa-sun text-warning me-1"></i>
                Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>, 
                <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!
            </div>
            <div class="user-info">
                <div class="access-badge badge-<?php echo $access_level === 'premium' ? 'premium' : ($payment_status === 'pending_verification' ? 'pending' : 'free'); ?>">
                    <?php echo $access_level === 'premium' ? 'Premium' : ($payment_status === 'pending_verification' ? 'Pending' : 'Free'); ?>
                </div>
                <div class="dropdown">
                    <div class="user-avatar" data-bs-toggle="dropdown" style="cursor: pointer;">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="?logout=1"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Access Status Alert -->
            <?php if ($payment_status === 'pending_verification'): ?>
                <div class="access-status status-pending">
                    <i class="fas fa-clock fa-2x mb-2"></i>
                    <h5>Payment Verification Pending</h5>
                    <p class="mb-2">Your premium access payment is being verified by our admin team.</p>
                    <small>This usually takes 24-48 hours. You'll receive an email confirmation once verified.</small>
                </div>
            <?php elseif ($access_level === 'free'): ?>
                <div class="access-status status-free">
                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                    <h5>Free Account</h5>
                    <p class="mb-3">You have access to basic resources. Upgrade to premium for full access!</p>
                    <a href="upgrade.php" class="upgrade-btn">
                        <i class="fas fa-crown"></i>
                        Upgrade Now - UGX 50,000
                    </a>
                </div>
            <?php else: ?>
                <div class="access-status status-premium">
                    <i class="fas fa-crown fa-2x mb-2"></i>
                    <h5>Premium Member</h5>
                    <p class="mb-0">Enjoy unlimited access to all educational resources and premium content!</p>
                </div>
            <?php endif; ?>

            <!-- Statistics Row -->
            <div class="row stats-row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon stat-favorites">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="stat-number" id="favoritesCount"><?php echo $user['favorites_count'] ?? 0; ?></div>
                        <div class="stat-label">Favorites</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon stat-progress">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-number">
                            <svg width="60" height="60" class="progress-ring">
                                <circle class="progress-ring-circle" cx="30" cy="30" r="25"></circle>
                                <circle class="progress-ring-progress" cx="30" cy="30" r="25" 
                                        stroke-dasharray="157" stroke-dashoffset="94.2"></circle>
                            </svg>
                        </div>
                        <div class="stat-label">Progress: 40%</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon stat-resources">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <div class="stat-number" id="resourcesCount">
                            <?php 
                            $total_resources = 0;
                            foreach ($resource_counts as $category) {
                                $total_resources += $category['count'];
                            }
                            echo $total_resources;
                            ?>
                        </div>
                        <div class="stat-label">Available Resources</div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Recent Downloads -->
                <div class="col-lg-8">
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-clock-rotate-left"></i>
                                Recent Activity
                            </h3>
                        </div>
                        
                        <?php if (!empty($recent_downloads)): ?>
                            <div class="recent-activity">
                                <?php foreach ($recent_downloads as $download): ?>
                                    <div class="recent-item">
                                        <div class="recent-icon icon-<?php echo strtolower($download['resource_type']); ?>">
                                            <i class="fas fa-<?php echo $download['resource_type'] === 'PDF' ? 'file-pdf' : ($download['resource_type'] === 'Video' ? 'play' : 'file-alt'); ?>"></i>
                                        </div>
                                        <div class="recent-info">
                                            <div class="recent-title"><?php echo htmlspecialchars($download['resource_name']); ?></div>
                                            <div class="recent-meta">
                                                Downloaded <?php echo date('M j, Y', strtotime($download['download_date'])); ?>
                                                • <?php echo htmlspecialchars($download['resource_type']); ?>
                                            </div>
                                        </div>
                                        <a href="#" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-download"></i>
                                <h5>No Downloads Yet</h5>
                                <p>Start exploring our resources to see your download history here.</p>
                                <a href="resources/" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>
                                    Browse Resources
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-lg-4">
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-rocket"></i>
                                Quick Actions
                            </h3>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <a href="resources/primary" class="quick-action">
                                    <i class="fas fa-child"></i>
                                    <h5>Primary Resources</h5>
                                    <small>P1-P7 Materials</small>
                                </a>
                            </div>
                            <div class="col-12">
                                <a href="resources/secondary" class="quick-action">
                                    <i class="fas fa-user-graduate"></i>
                                    <h5>Secondary Resources</h5>
                                    <small>S1-S6 Materials</small>
                                </a>
                            </div>
                            <div class="col-12">
                                <a href="resources/past-papers" class="quick-action">
                                    <i class="fas fa-file-pdf"></i>
                                    <h5>Past Papers</h5>
                                    <small>All Examination Papers</small>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Resource Categories -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-pie"></i>
                                Resource Categories
                            </h3>
                        </div>
                        
                        <?php if (!empty($resource_counts)): ?>
                            <?php foreach ($resource_counts as $category): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <strong><?php echo htmlspecialchars(ucfirst($category['category'])); ?></strong>
                                        <div class="text-muted small">Available resources</div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary"><?php echo $category['count']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-folder-open fa-2x mb-2"></i>
                                <p>No resources available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Role-specific Content -->
            <?php if ($user_role === 'student'): ?>
            <div class="row g-4 mt-2">
                <div class="col-lg-8">
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-graduation-cap"></i>
                                Study Recommendations for <?php echo htmlspecialchars($user['class_level'] ?? 'Your Level'); ?>
                            </h3>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <h6><i class="fas fa-calculator text-primary me-1"></i> Mathematics</h6>
                                    <p class="small text-muted mb-2">Essential for your current level</p>
                                    <a href="resources/mathematics" class="btn btn-sm btn-outline-primary">
                                        View Materials
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <h6><i class="fas fa-flask text-success me-1"></i> Science</h6>
                                    <p class="small text-muted mb-2">Build strong foundations</p>
                                    <a href="resources/science" class="btn btn-sm btn-outline-success">
                                        View Materials
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <h6><i class="fas fa-book text-info me-1"></i> English</h6>
                                    <p class="small text-muted mb-2">Improve communication skills</p>
                                    <a href="resources/english" class="btn btn-sm btn-outline-info">
                                        View Materials
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <h6><i class="fas fa-globe text-warning me-1"></i> Social Studies</h6>
                                    <p class="small text-muted mb-2">Understand the world</p>
                                    <a href="resources/social-studies" class="btn btn-sm btn-outline-warning">
                                        View Materials
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-target"></i>
                                Study Goals
                            </h3>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Weekly Study Time</small>
                                <small>12/20 hours</small>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: 60%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Resources Completed</small>
                                <small>8/15</small>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-info" style="width: 53%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Practice Tests</small>
                                <small>5/10</small>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-warning" style="width: 50%"></div>
                            </div>
                        </div>
                        
                        <a href="goals.php" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-plus me-1"></i>
                            Set New Goals
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($user_role === 'teacher'): ?>
            <div class="row g-4 mt-2">
                <div class="col-12">
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chalkboard-teacher"></i>
                                Teacher Resources - <?php echo htmlspecialchars($user['subject_specialization'] ?? 'All Subjects'); ?>
                            </h3>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-lg-3 col-md-6">
                                <a href="resources/lesson-plans" class="quick-action">
                                    <i class="fas fa-clipboard-list"></i>
                                    <h5>Lesson Plans</h5>
                                    <small>Ready-to-use plans</small>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <a href="resources/marking-schemes" class="quick-action">
                                    <i class="fas fa-check-square"></i>
                                    <h5>Marking Schemes</h5>
                                    <small>Assessment tools</small>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <a href="resources/teaching-aids" class="quick-action">
                                    <i class="fas fa-tools"></i>
                                    <h5>Teaching Aids</h5>
                                    <small>Visual resources</small>
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <a href="resources/assessments" class="quick-action">
                                    <i class="fas fa-tasks"></i>
                                    <h5>Assessments</h5>
                                    <small>Tests & quizzes</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($user_role === 'parent'): ?>
            <div class="row g-4 mt-2">
                <div class="col-12">
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users"></i>
                                Parent Resources & Support
                            </h3>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-lg-4 col-md-6">
                                <a href="resources/homework-help" class="quick-action">
                                    <i class="fas fa-question-circle"></i>
                                    <h5>Homework Help</h5>
                                    <small>Support your child</small>
                                </a>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <a href="resources/parent-guides" class="quick-action">
                                    <i class="fas fa-book-reader"></i>
                                    <h5>Parent Guides</h5>
                                    <small>Educational support</small>
                                </a>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <a href="resources/curriculum-info" class="quick-action">
                                    <i class="fas fa-info-circle"></i>
                                    <h5>Curriculum Info</h5>
                                    <small>Stay informed</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Latest Updates -->
            <div class="row g-4 mt-2">
                <div class="col-12">
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-bell"></i>
                                Latest Updates & Announcements
                            </h3>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="alert alert-info mb-0">
                                    <h6><i class="fas fa-plus-circle me-1"></i> New Content Added</h6>
                                    <p class="mb-2 small">2024 examination papers now available for download.</p>
                                    <small class="text-muted">2 days ago</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-success mb-0">
                                    <h6><i class="fas fa-video me-1"></i> Video Series Released</h6>
                                    <p class="mb-2 small">New mathematics tutorial series for S1-S4 students.</p>
                                    <small class="text-muted">1 week ago</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-warning mb-0">
                                    <h6><i class="fas fa-calendar me-1"></i> Upcoming Changes</h6>
                                    <p class="mb-2 small">New curriculum updates will be reflected next month.</p>
                                    <small class="text-muted">2 weeks ago</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth <= 768) {
                // Mobile behavior
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            } else {
                // Desktop behavior
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            }
        }

        // Close sidebar when clicking overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            toggleSidebar();
        });

        // Navigation active state
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        // Animate statistics on load
        function animateNumber(element, finalNumber, duration = 2000) {
            const start = 0;
            const increment = finalNumber / (duration / 16);
            let current = 0;
            
            const timer = setInterval(() => {
                current += increment;
                element.textContent = Math.floor(current);
                
                if (current >= finalNumber) {
                    clearInterval(timer);
                    element.textContent = finalNumber;
                }
            }, 16);
        }

        // Initialize number animations
        document.addEventListener('DOMContentLoaded', function() {
            const downloadsCount = document.getElementById('downloadsCount');
            const favoritesCount = document.getElementById('favoritesCount');
            const resourcesCount = document.getElementById('resourcesCount');
            
            if (downloadsCount) {
                const finalValue = parseInt(downloadsCount.textContent);
                animateNumber(downloadsCount, finalValue);
            }
            
            if (favoritesCount) {
                const finalValue = parseInt(favoritesCount.textContent);
                animateNumber(favoritesCount, finalValue, 1500);
            }
            
            if (resourcesCount) {
                const finalValue = parseInt(resourcesCount.textContent);
                animateNumber(resourcesCount, finalValue, 2500);
            }
        });

        // Auto-refresh dashboard data every 5 minutes
        setInterval(function() {
            // You can implement AJAX refresh here
            console.log('Dashboard data refresh check...');
        }, 300000);

        // Enhanced hover effects for cards
        document.querySelectorAll('.stat-card, .content-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Add loading states for navigation
        document.querySelectorAll('a[href]:not([href^="#"])').forEach(link => {
            link.addEventListener('click', function() {
                // Add loading indicator
                const icon = this.querySelector('i');
                if (icon && !icon.classList.contains('fa-spin')) {
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    
                    // Restore original icon after timeout
                    setTimeout(() => {
                        icon.className = originalClass;
                    }, 3000);
                }
            });
        });

        // Responsive sidebar handling
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
            }
        });

        // Add session timeout warning
        let sessionTimeout = 1800000; // 30 minutes
        let warningShown = false;
        
        setTimeout(() => {
            if (!warningShown) {
                warningShown = true;
                if (confirm('Your session will expire in 5 minutes. Click OK to extend your session.')) {
                    // Refresh session by making a small AJAX request
                    fetch('refresh-session.php', { method: 'POST' })
                        .then(() => {
                            warningShown = false;
                        })
                        .catch(() => {
                            alert('Unable to extend session. Please save your work and login again.');
                        });
                }
            }
        }, sessionTimeout - 300000); // Show warning 5 minutes before expiry

        // Track user activity for analytics
        let activityTimer;
        function trackActivity() {
            clearTimeout(activityTimer);
            activityTimer = setTimeout(() => {
                // Log user activity
                console.log('User active on dashboard');
            }, 1000);
        }

        document.addEventListener('mousemove', trackActivity);
        document.addEventListener('keypress', trackActivity);
        document.addEventListener('click', trackActivity);

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Welcome animation
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .content-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });
    </script>
</body>
</html>
card">
                        <div class="stat-icon stat-downloads">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="stat-number" id="downloadsCount"><?php echo $user['downloads_count'] ?? 0; ?></div>
                        <div class="stat-label">Total Downloads</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="stat-