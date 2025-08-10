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
    <title>Welcome - Results Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gold: #ffd700;
            --secondary-gold: #ffc107;
            --dark-overlay: rgba(0, 0, 0, 0.6);
            --light-overlay: rgba(255, 255, 255, 0.1);
            --shadow-light: rgba(255, 215, 0, 0.3);
            --shadow-dark: rgba(0, 0, 0, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: url('background.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }

        /* Animated background overlay */
        .overlay {
            background: linear-gradient(135deg, var(--dark-overlay) 0%, rgba(0, 0, 0, 0.4) 100%);
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
            background: radial-gradient(circle at 30% 20%, var(--shadow-light) 0%, transparent 50%),
                        radial-gradient(circle at 70% 80%, var(--shadow-light) 0%, transparent 50%);
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
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 3rem 2rem;
            box-shadow: 0 20px 40px var(--shadow-dark);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 600px;
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
            background: linear-gradient(45deg, transparent, rgba(255, 215, 0, 0.1), transparent);
            animation: shimmer 3s infinite;
            pointer-events: none;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        /* Logo container */
        .logo-container {
            margin-bottom: 2rem;
            position: relative;
            animation: fadeInScale 1s ease-out both;
        }

        .logo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid var(--primary-gold);
            padding: 8px;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            object-fit: contain;
            box-shadow: 0 10px 30px var(--shadow-dark),
                        0 0 0 8px rgba(255, 215, 0, 0.2);
            transition: all 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 40px var(--shadow-dark),
                        0 0 0 12px rgba(255, 215, 0, 0.3);
        }

        /* Default logo placeholder */
        .logo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid var(--primary-gold);
            background: linear-gradient(135deg, var(--primary-gold) 0%, var(--secondary-gold) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #000;
            font-weight: 700;
            box-shadow: 0 10px 30px var(--shadow-dark),
                        0 0 0 8px rgba(255, 215, 0, 0.2);
            transition: all 0.3s ease;
        }

        .logo-placeholder:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 40px var(--shadow-dark),
                        0 0 0 12px rgba(255, 215, 0, 0.3);
        }

        /* Typography */
        .main-title {
            font-weight: 700;
            font-size: 2.5rem;
            color: var(--primary-gold);
            text-shadow: 2px 2px 8px var(--shadow-dark);
            margin-bottom: 1rem;
            line-height: 1.2;
            animation: fadeInUp 1s ease-out 0.7s both;
        }

        .board-name {
            font-weight: 600;
            font-size: 1.8rem;
            color: #fff;
            text-shadow: 1px 1px 4px var(--shadow-dark);
            margin-bottom: 0.5rem;
            animation: fadeInUp 1s ease-out 0.9s both;
        }

        .subtitle {
            font-size: 1.1rem;
            color: #e9ecef;
            margin-bottom: 1.5rem;
            animation: fadeInUp 1s ease-out 1.1s both;
        }

        .exam-year-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-gold) 0%, var(--secondary-gold) 100%);
            color: #000;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
            animation: fadeInUp 1s ease-out 1.3s both;
        }

        /* Login button */
        .login-btn {
            background: linear-gradient(135deg, var(--primary-gold) 0%, var(--secondary-gold) 100%);
            border: none;
            color: #000;
            font-weight: 600;
            padding: 1rem 3rem;
            border-radius: 50px;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
            animation: fadeInUp 1s ease-out 1.5s both;
            position: relative;
            overflow: hidden;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(255, 215, 0, 0.6);
            color: #000;
        }

        .login-btn:active {
            transform: translateY(0);
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #fff;
            padding: 2rem 0;
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
            gap: 0.5rem;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .footer-main i {
            color: var(--primary-gold);
            font-size: 1.1rem;
        }

        .footer-contact {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2rem;
            font-size: 0.85rem;
            color: #adb5bd;
            flex-wrap: wrap;
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
        }

        .email-protected {
            font-family: monospace;
            letter-spacing: 0.5px;
        }

        /* Loader styles */
        .loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #000 0%, #1a1a1a 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out;
        }

        .loader.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .spinner-container {
            text-align: center;
        }

        .spinner {
            border: 4px solid rgba(255, 215, 0, 0.3);
            border-top: 4px solid var(--primary-gold);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        .loading-text {
            color: var(--primary-gold);
            font-weight: 600;
            font-size: 1.1rem;
            animation: fadeInOut 1.5s ease-in-out infinite alternate;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeInOut {
            0% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInScale {
            0% {
                opacity: 0;
                transform: scale(0.8);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .main-title {
                font-size: 2rem;
            }
            
            .board-name {
                font-size: 1.5rem;
            }
            
            .welcome-card {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
            
            .logo, .logo-placeholder {
                width: 100px;
                height: 100px;
            }
            
            .logo-placeholder {
                font-size: 2rem;
            }
            
            .login-btn {
                padding: 0.8rem 2rem;
                font-size: 1rem;
            }

            .footer-contact {
                flex-direction: column;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .main-title {
                font-size: 1.8rem;
            }
            
            .board-name {
                font-size: 1.3rem;
            }
            
            .welcome-card {
                padding: 1.5rem 1rem;
            }

            .footer-main {
                flex-direction: column;
                gap: 0.3rem;
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
            width: 4px;
            height: 4px;
            background: var(--primary-gold);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
            opacity: 0.6;
        }

        .particle:nth-child(1) { left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { left: 20%; animation-delay: 1s; }
        .particle:nth-child(3) { left: 30%; animation-delay: 2s; }
        .particle:nth-child(4) { left: 40%; animation-delay: 3s; }
        .particle:nth-child(5) { left: 50%; animation-delay: 4s; }
        .particle:nth-child(6) { left: 60%; animation-delay: 5s; }
        .particle:nth-child(7) { left: 70%; animation-delay: 0.5s; }
        .particle:nth-child(8) { left: 80%; animation-delay: 1.5s; }
        .particle:nth-child(9) { left: 90%; animation-delay: 2.5s; }

        @keyframes float {
            0%, 100% { transform: translateY(100vh) scale(0); }
            50% { transform: translateY(-10px) scale(1); }
        }
    </style>
</head>
<body>
    <!-- Loader -->
    <div class="loader" id="loader">
        <div class="spinner-container">
            <div class="spinner"></div>
            <div class="loading-text">Loading Results Management System...</div>
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
            
            <div class="exam-year-badge">
                <i class="fas fa-calendar-alt"></i>
                Active Exam Year: <?php echo htmlspecialchars($exam_year); ?>
            </div>
            
            <a href="login.php" class="login-btn">
                <i class="fas fa-sign-in-alt"></i>
                Login to Continue
            </a>
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
                    <span>+256777115678</span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <span class="email-protected">jmprossy[at]gmail[dot]com</span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced page load handling
        window.addEventListener('load', function() {
            // Minimum loading time for better UX
            const minLoadTime = 1500;
            const startTime = performance.now();
            
            setTimeout(() => {
                const elapsed = performance.now() - startTime;
                const remainingTime = Math.max(0, minLoadTime - elapsed);
                
                setTimeout(() => {
                    document.getElementById('loader').classList.add('hidden');
                    
                    // Add entrance animations
                    document.body.style.overflow = 'auto';
                }, remainingTime);
            }, 100);
        });

        // Add smooth scrolling for any internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add click ripple effect to login button
        document.querySelector('.login-btn').addEventListener('click', function(e) {
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
            ripple.style.background = 'rgba(255, 255, 255, 0.5)';
            ripple.style.pointerEvents = 'none';
            ripple.style.animation = 'ripple 0.6s ease-out';
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
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
                    transform: scale(1);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Preload background image for better performance
        const img = new Image();
        img.src = 'static/img/background.jpg';
    </script>
</body>
</html>