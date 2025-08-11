<?php
// Start session with cookie lifetime set to 0 (expires on browser close)
session_set_cookie_params(0);
session_start();

// Clear session if no valid user_id to prevent persistent logins
if (!isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    session_start();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        header("Location: setup.php");
        exit();
    }

    // Fetch data from the database
    $sql = "SELECT s.board_name, s.logo, e.exam_year 
            FROM settings s 
            JOIN exam_years e ON s.exam_year_id = e.id 
            ORDER BY s.id DESC LIMIT 1";
    $result = $conn->query($sql);

    $board_name = "Luuka Examination Board";
    $logo = "default-logo.png";
    $exam_year = date('Y');

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $board_name = $row['board_name'];
        // Check if logo exists in uploads/resources, otherwise use default
        $logo_path = $row['logo'] ? 'uploads/resources/' . $row['logo'] : null;
        if ($row['logo'] && file_exists($logo_path)) {
            $logo = $logo_path;
        } else {
            $logo = null; // No logo set
        }
        $exam_year = $row['exam_year'];
    }

    $conn->close();
} catch (mysqli_sql_exception $e) {
    // Redirect to setup if database doesn't exist or other connection issues
    header("Location: setup.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title><?php echo htmlspecialchars($board_name); ?> - Results Management System | Educational Portal Uganda</title>
    <meta name="description" content="Access examination results, educational resources, and school management solutions. <?php echo htmlspecialchars($board_name); ?> - Your trusted educational partner in Uganda.">
    <meta name="keywords" content="results management, examination results, educational resources, school portal, <?php echo htmlspecialchars($board_name); ?>, Uganda education">
    <meta name="author" content="ILABS UGANDA LIMITED - Jonathan Musumba">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($board_name); ?> - Educational Portal">
    <meta property="og:description" content="Access examination results, educational resources, and comprehensive school management solutions.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?php echo $logo ? htmlspecialchars($logo) : 'static/img/og-image.jpg'; ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../static/img/icon.ico">
    
    <style>
        :root {
            --primary-gold: #ffd700;
            --secondary-gold: #ffc107;
            --accent-blue: #0066cc;
            --success-green: #28a745;
            --dark-overlay: rgba(0, 0, 0, 0.7);
            --light-overlay: rgba(255, 255, 255, 0.12);
            --shadow-light: rgba(255, 215, 0, 0.3);
            --shadow-dark: rgba(0, 0, 0, 0.5);
            --gradient-primary: linear-gradient(135deg, var(--primary-gold) 0%, var(--secondary-gold) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
            position: relative;
        }

        /* Background overlay with animation */
        .overlay {
            background: linear-gradient(135deg, var(--dark-overlay) 0%, rgba(0, 0, 0, 0.5) 100%);
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
        }

        .overlay::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, var(--shadow-light) 0%, transparent 70%),
                        radial-gradient(circle at 70% 80%, var(--shadow-light) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite alternate;
        }

        @keyframes pulse {
            0% { opacity: 0.3; }
            100% { opacity: 0.6; }
        }

        /* Main container */
        .main-container {
            z-index: 2;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 2rem;
            position: relative;
        }

        /* Welcome card */
        .welcome-card {
            background: var(--light-overlay);
            backdrop-filter: blur(25px);
            border-radius: 30px;
            padding: 3.5rem 2.5rem;
            box-shadow: 0 25px 50px var(--shadow-dark);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 650px;
            width: 100%;
            animation: fadeInUp 1s ease-out 0.5s both;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 215, 0, 0.08), transparent);
            animation: shimmer 4s infinite;
            pointer-events: none;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        /* Logo container - centered */
        .logo-container {
            margin-bottom: 2rem;
            position: relative;
            animation: fadeInScale 1s ease-out both;
            display: flex;
            justify-content: center;
        }

        .logo {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 4px solid var(--primary-gold);
            padding: 10px;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            object-fit: contain;
            box-shadow: 0 15px 40px var(--shadow-dark),
                        0 0 0 8px rgba(255, 215, 0, 0.2);
            transition: all 0.4s ease;
        }

        .logo:hover {
            transform: scale(1.08) rotate(5deg);
            box-shadow: 0 20px 50px var(--shadow-dark),
                        0 0 0 12px rgba(255, 215, 0, 0.4);
        }

        /* Default logo placeholder */
        .logo-placeholder {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 4px solid var(--primary-gold);
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #000;
            font-weight: 700;
            box-shadow: 0 15px 40px var(--shadow-dark),
                        0 0 0 8px rgba(255, 215, 0, 0.2);
            transition: all 0.4s ease;
        }

        .logo-placeholder:hover {
            transform: scale(1.08) rotate(5deg);
            box-shadow: 0 20px 50px var(--shadow-dark),
                        0 0 0 12px rgba(255, 215, 0, 0.4);
        }

        /* Typography */
        .main-title {
            font-weight: 700;
            font-size: 2.8rem;
            color: var(--primary-gold);
            text-shadow: 2px 2px 8px var(--shadow-dark);
            margin-bottom: 1rem;
            line-height: 1.2;
            animation: fadeInUp 1s ease-out 0.7s both;
        }

        .board-name {
            font-weight: 600;
            font-size: 2rem;
            color: #fff;
            text-shadow: 1px 1px 4px var(--shadow-dark);
            margin-bottom: 0.5rem;
            animation: fadeInUp 1s ease-out 0.9s both;
            line-height: 1.3;
        }

        .subtitle {
            font-size: 1.2rem;
            color: #e9ecef;
            margin-bottom: 1rem;
            animation: fadeInUp 1s ease-out 1.1s both;
            font-weight: 400;
        }

        .description {
            font-size: 1rem;
            color: #adb5bd;
            margin-bottom: 2rem;
            animation: fadeInUp 1s ease-out 1.2s both;
            line-height: 1.6;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .exam-year-badge {
            display: inline-block;
            background: var(--gradient-primary);
            color: #000;
            padding: 0.6rem 1.8rem;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.4);
            animation: fadeInUp 1s ease-out 1.3s both;
            transition: all 0.3s ease;
        }

        .exam-year-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(255, 215, 0, 0.5);
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease-out 1.5s both;
        }

        .btn-guest {
            background: var(--gradient-primary);
            border: none;
            color: #000;
            font-weight: 600;
            padding: 1.2rem 3rem;
            border-radius: 50px;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.4);
            position: relative;
            overflow: hidden;
            min-width: 200px;
            justify-content: center;
        }

        .btn-login {
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid var(--primary-gold);
            color: #fff;
            font-weight: 600;
            padding: 1.2rem 3rem;
            border-radius: 50px;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            min-width: 200px;
            justify-content: center;
        }

        .btn-guest::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn-guest:hover::before {
            left: 100%;
        }

        .btn-guest:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(255, 215, 0, 0.6);
            color: #000;
        }

        .btn-login:hover {
            background: rgba(255, 215, 0, 0.2);
            transform: translateY(-3px);
            color: var(--primary-gold);
            border-color: var(--secondary-gold);
        }

        .btn-guest:active,
        .btn-login:active {
            transform: translateY(-1px);
        }

        /* Quick access info */
        .quick-access {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInUp 1s ease-out 1.7s both;
        }

        .quick-access-title {
            font-size: 1rem;
            color: #adb5bd;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .quick-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .quick-link {
            color: #adb5bd;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .quick-link:hover {
            color: var(--primary-gold);
            transform: translateY(-2px);
        }

        .quick-link i {
            font-size: 0.8rem;
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #fff;
            padding: 2.5rem 0 1rem;
            text-align: center;
            z-index: 2;
            width: 100%;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .footer-main {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.6rem;
            font-size: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .footer-main i {
            color: var(--primary-gold);
            font-size: 1.2rem;
        }

        .footer-contact {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2.5rem;
            font-size: 0.9rem;
            color: #adb5bd;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s ease;
        }

        .contact-item:hover {
            color: var(--primary-gold);
        }

        .contact-item i {
            color: var(--primary-gold);
            font-size: 1rem;
            width: 16px;
        }

        .email-protected {
            font-family: monospace;
            letter-spacing: 0.5px;
        }

        .footer-bottom {
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.85rem;
            color: #6c757d;
        }

        /* Loader styles */
        .loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: all 0.6s ease-out;
        }

        .loader.hidden {
            opacity: 0;
            pointer-events: none;
            transform: scale(1.1);
        }

        .spinner-container {
            text-align: center;
        }

        .spinner {
            border: 4px solid rgba(255, 215, 0, 0.3);
            border-top: 4px solid var(--primary-gold);
            border-radius: 50%;
            width: 70px;
            height: 70px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem;
        }

        .loading-text {
            color: var(--primary-gold);
            font-weight: 600;
            font-size: 1.2rem;
            animation: fadeInOut 1.5s ease-in-out infinite alternate;
        }

        .loading-subtitle {
            color: #adb5bd;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeInOut {
            0% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(40px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInScale {
            0% {
                opacity: 0;
                transform: scale(0.7);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Floating particles effect */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .particle {
            position: absolute;
            width: 5px;
            height: 5px;
            background: var(--primary-gold);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
            opacity: 0.7;
        }

        .particle:nth-child(1) { left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { left: 20%; animation-delay: 1.2s; }
        .particle:nth-child(3) { left: 30%; animation-delay: 2.4s; }
        .particle:nth-child(4) { left: 40%; animation-delay: 3.6s; }
        .particle:nth-child(5) { left: 50%; animation-delay: 4.8s; }
        .particle:nth-child(6) { left: 60%; animation-delay: 0.6s; }
        .particle:nth-child(7) { left: 70%; animation-delay: 1.8s; }
        .particle:nth-child(8) { left: 80%; animation-delay: 3s; }
        .particle:nth-child(9) { left: 90%; animation-delay: 4.2s; }

        @keyframes float {
            0%, 100% { 
                transform: translateY(100vh) scale(0);
                opacity: 0;
            }
            10%, 90% {
                opacity: 0.7;
            }
            50% { 
                transform: translateY(-20px) scale(1);
                opacity: 1;
            }
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .main-title {
                font-size: 2.2rem;
            }
            
            .board-name {
                font-size: 1.6rem;
            }
            
            .welcome-card {
                padding: 2.5rem 1.8rem;
                margin: 1rem;
                border-radius: 25px;
            }
            
            .logo, .logo-placeholder {
                width: 120px;
                height: 120px;
            }
            
            .logo-placeholder {
                font-size: 2.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }

            .btn-guest,
            .btn-login {
                width: 100%;
                max-width: 280px;
                padding: 1rem 2rem;
                font-size: 1rem;
            }

            .footer-contact {
                flex-direction: column;
                gap: 1rem;
            }

            .quick-links {
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .main-title {
                font-size: 1.9rem;
            }
            
            .board-name {
                font-size: 1.4rem;
            }
            
            .welcome-card {
                padding: 2rem 1.2rem;
                margin: 0.5rem;
            }

            .footer-main {
                flex-direction: column;
                gap: 0.3rem;
            }

            .logo, .logo-placeholder {
                width: 100px;
                height: 100px;
            }

            .logo-placeholder {
                font-size: 2rem;
            }

            .exam-year-badge {
                padding: 0.5rem 1.2rem;
                font-size: 0.85rem;
            }
        }

        /* Enhanced visual effects */
        .welcome-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.02) 50%, transparent 70%);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .welcome-card:hover::after {
            opacity: 1;
        }

        /* Button hover effects */
        .btn-guest::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.3s ease;
        }

        .btn-guest:hover::after {
            width: 300px;
            height: 300px;
        }

        /* Accessibility improvements */
        .btn-guest:focus,
        .btn-login:focus {
            outline: 3px solid rgba(255, 215, 0, 0.5);
            outline-offset: 2px;
        }

        /* Print styles */
        @media print {
            .particles,
            .loader,
            .overlay {
                display: none;
            }
            
            body {
                background: white;
                color: black;
            }
            
            .welcome-card {
                background: white;
                color: black;
                box-shadow: none;
                border: 1px solid #ccc;
            }
        }
    </style>
</head>
<body>
    <!-- Loader -->
    <div class="loader" id="loader">
        <div class="spinner-container">
            <div class="spinner"></div>
            <div class="loading-text">Loading Results Management System</div>
            <div class="loading-subtitle">Connecting to educational services...</div>
        </div>
    </div>

    <!-- Floating particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="overlay"></div>
    
    <div class="main-container">
        <div class="welcome-card">
            <div class="logo-container">
                <?php if ($logo): ?>
                    <img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($board_name); ?> Logo" class="logo">
                <?php else: ?>
                    <div class="logo-placeholder">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <h1 class="main-title">Welcome to</h1>
            <h2 class="board-name"><?php echo htmlspecialchars($board_name); ?></h2>
            <p class="subtitle">Results Management System</p>
            
            <p class="description">
                Your comprehensive educational platform providing examination results, resources, and school management solutions for educational excellence.
            </p>
            
            <div class="exam-year-badge">
                <i class="fas fa-calendar-alt me-2"></i>
                Active Exam Year: <?php echo htmlspecialchars($exam_year); ?>
            </div>
            
            <div class="action-buttons">
                <a href="public/index.php" class="btn-guest">
                    <i class="fas fa-globe"></i>
                    Continue as Guest
                </a>
                <a href="login.php" class="btn-login">
                    <i class="fas fa-school"></i>
                    School Portal
                </a>
            </div>

            <div class="quick-access">
                <div class="quick-access-title">Quick Access</div>
                <div class="quick-links">
                    <a href="public/index.php#resources" class="quick-link">
                        <i class="fas fa-book"></i>
                        Resources
                    </a>
                    <a href="public/index.php#results" class="quick-link">
                        <i class="fas fa-chart-bar"></i>
                        Public Results
                    </a>
                    <a href="help.php" class="quick-link">
                        <i class="fas fa-question-circle"></i>
                        Help
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-main">
                <i class="fas fa-code"></i>
                <span>Powered by</span>
                <strong>ILABS UGANDA LIMITED</strong>
                <span>(Jonathan Musumba)</span>
            </div>
            <div class="footer-contact">
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <span>+256 777 115 678</span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <span class="email-protected">jmprossy[at]gmail[dot]com</span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Kampala, Uganda</span>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($board_name); ?>. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced page load handling
        window.addEventListener('load', function() {
            const minLoadTime = 1800;
            const startTime = performance.now();
            
            setTimeout(() => {
                const elapsed = performance.now() - startTime;
                const remainingTime = Math.max(0, minLoadTime - elapsed);
                
                setTimeout(() => {
                    const loader = document.getElementById('loader');
                    loader.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                    
                    // Add subtle entrance effect
                    setTimeout(() => {
                        loader.style.display = 'none';
                    }, 600);
                }, remainingTime);
            }, 100);
        });

        // Add ripple effect to buttons
        function addRippleEffect(button) {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.4)';
                ripple.style.pointerEvents = 'none';
                ripple.style.animation = 'ripple 0.6s ease-out';
                ripple.style.zIndex = '1';
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        }

        // Apply ripple effect to action buttons
        document.querySelectorAll('.btn-guest, .btn-login').forEach(addRippleEffect);

        // Enhanced particle system
        function createParticles() {
            const particlesContainer = document.querySelector('.particles');
            const particleCount = window.innerWidth < 768 ? 6 : 9;
            
            particlesContainer.innerHTML = '';
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                particle.style.left = (Math.random() * 90 + 5) + '%';
                particle.style.animationDelay = Math.random() * 8 + 's';
                particle.style.animationDuration = (Math.random() * 4 + 6) + 's';
                
                // Vary particle sizes
                const size = Math.random() * 3 + 3;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                
                particlesContainer.appendChild(particle);
            }
        }

        // Initialize particles
        createParticles();

        // Recreate particles on window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(createParticles, 250);
        });

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                0% {
                    transform: scale(0);
                    opacity: 1;
                }
                100% {
                    transform: scale(2);
                    opacity: 0;
                }
            }
            
            .btn-guest,
            .btn-login {
                position: relative;
                overflow: hidden;
            }
        `;
        document.head.appendChild(style);

        // Keyboard navigation enhancement
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                const focused = document.activeElement;
                if (focused.classList.contains('quick-link')) {
                    e.preventDefault();
                    focused.click();
                }
            }
            
            // Quick keyboard shortcuts
            if (e.ctrlKey || e.metaKey) {
                switch(e.key.toLowerCase()) {
                    case 'g':
                        e.preventDefault();
                        window.location.href = 'public/index.php';
                        break;
                    case 'l':
                        e.preventDefault();
                        window.location.href = 'login.php';
                        break;
                }
            }
        });

        // Add subtle animations on interaction
        document.querySelector('.welcome-card').addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.02)';
            this.style.transition = 'transform 0.3s ease';
        });

        document.querySelector('.welcome-card').addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });

        // Preload next page for faster navigation
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = 'public/index.php';
        document.head.appendChild(link);

        // Add loading state for navigation
        document.querySelectorAll('.btn-guest, .btn-login').forEach(button => {
            button.addEventListener('click', function(e) {
                // Add loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
                this.style.pointerEvents = 'none';
                
                // Restore if navigation fails (fallback)
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.style.pointerEvents = 'auto';
                }, 3000);
            });
        });

        // Performance optimization
        if (window.innerWidth < 768) {
            // Reduce animation complexity on mobile
            document.documentElement.style.setProperty('--animation-duration', '0.4s');
            
            // Limit particle count on low-end devices
            if (navigator.hardwareConcurrency && navigator.hardwareConcurrency < 4) {
                document.querySelector('.particles').style.display = 'none';
            }
        }

        // Add error handling for logo images
        document.querySelectorAll('img.logo').forEach(img => {
            img.addEventListener('error', function() {
                this.style.display = 'none';
                const placeholder = document.createElement('div');
                placeholder.className = 'logo-placeholder';
                placeholder.innerHTML = '<i class="fas fa-graduation-cap"></i>';
                this.parentNode.appendChild(placeholder);
            });
        });

        // Smooth fade transition for page navigation
        window.addEventListener('beforeunload', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease';
        });

        // Add dynamic year update
        setInterval(function() {
            const now = new Date();
            const currentYear = now.getFullYear();
            const examYearElement = document.querySelector('.exam-year-badge');
            if (examYearElement) {
                const yearText = examYearElement.textContent;
                if (yearText.includes('<?php echo $exam_year; ?>') && currentYear > <?php echo $exam_year; ?>) {
                    // Could add notification about year update needed
                }
            }
        }, 60000); // Check every minute

        // Add touch feedback for mobile
        if ('ontouchstart' in window) {
            document.querySelectorAll('.btn-guest, .btn-login, .quick-link').forEach(element => {
                element.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.95)';
                });
                
                element.addEventListener('touchend', function() {
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        }

        // Analytics tracking (if needed)
        function trackEvent(action, label) {
            // Placeholder for analytics tracking
            console.log(`Event: ${action}, Label: ${label}`);
        }

        // Track button clicks
        document.querySelector('.btn-guest')?.addEventListener('click', () => {
            trackEvent('navigation', 'guest_access');
        });

        document.querySelector('.btn-login')?.addEventListener('click', () => {
            trackEvent('navigation', 'school_login');
        });
    </script>
</body>
</html>