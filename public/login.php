<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once '../connections/db_connect.php';

$board_name = "Luuka Examination Board";

// Redirect if already logged in
if (isset($_SESSION['public_user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$errors = [];
$login_attempts = $_SESSION['login_attempts'] ?? 0;
$last_attempt = $_SESSION['last_attempt'] ?? 0;

// Rate limiting: max 5 attempts per 15 minutes
if ($login_attempts >= 5 && (time() - $last_attempt) < 900) {
    $errors[] = "Too many login attempts. Please try again in " . ceil((900 - (time() - $last_attempt)) / 60) . " minutes.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Validation
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, first_name, last_name, email, password, role, access_level, 
                       payment_status, status, last_login, failed_login_attempts
                FROM public_users 
                WHERE email = ? AND status = 'active'
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                $_SESSION['public_user_id'] = $user['id'];
                $_SESSION['public_user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['public_user_email'] = $user['email'];
                $_SESSION['public_user_role'] = $user['role'];
                $_SESSION['public_access_level'] = $user['access_level'];
                $_SESSION['public_payment_status'] = $user['payment_status'];
                $_SESSION['login_time'] = time();
                
                // Reset login attempts
                $_SESSION['login_attempts'] = 0;
                unset($_SESSION['last_attempt']);
                
                // Update last login and reset failed attempts
                $stmt = $pdo->prepare("
                    UPDATE public_users 
                    SET last_login = NOW(), failed_login_attempts = 0 
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);
                
                // Set remember me cookie if requested
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                    
                    // Store token in database
                    $stmt = $pdo->prepare("
                        UPDATE public_users 
                        SET remember_token = ?, remember_token_expires = DATE_ADD(NOW(), INTERVAL 30 DAY)
                        WHERE id = ?
                    ");
                    $stmt->execute([$token, $user['id']]);
                }
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            } else {
                // Failed login
                $errors[] = "Invalid email or password";
                $_SESSION['login_attempts'] = $login_attempts + 1;
                $_SESSION['last_attempt'] = time();
                
                // Update failed login attempts in database
                if ($user) {
                    $stmt = $pdo->prepare("
                        UPDATE public_users 
                        SET failed_login_attempts = failed_login_attempts + 1 
                        WHERE email = ?
                    ");
                    $stmt->execute([$email]);
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Login failed. Please try again later.";
        }
    }
}

// Check for remember me token
if (!isset($_SESSION['public_user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, role, access_level, payment_status
            FROM public_users 
            WHERE remember_token = ? AND remember_token_expires > NOW() AND status = 'active'
        ");
        $stmt->execute([$_COOKIE['remember_token']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['public_user_id'] = $user['id'];
            $_SESSION['public_user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['public_user_email'] = $user['email'];
            $_SESSION['public_user_role'] = $user['role'];
            $_SESSION['public_access_level'] = $user['access_level'];
            $_SESSION['public_payment_status'] = $user['payment_status'];
            $_SESSION['login_time'] = time();
            
            header('Location: dashboard.php');
            exit();
        }
    } catch (PDOException $e) {
        // Silent fail for remember token
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($board_name); ?></title>
    <meta name="description" content="Login to access your educational resources and premium content">
    
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
            --dark-blue: #1e3c72;
            --light-blue: #2a5298;
            --gradient-primary: linear-gradient(135deg, var(--primary-gold) 0%, var(--secondary-gold) 100%);
            --gradient-blue: linear-gradient(135deg, var(--dark-blue) 0%, var(--light-blue) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gradient-blue);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 30%, rgba(255, 215, 0, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, rgba(255, 215, 0, 0.1) 0%, transparent 50%);
            animation: backgroundGlow 8s ease-in-out infinite alternate;
        }

        @keyframes backgroundGlow {
            0% { opacity: 0.3; }
            100% { opacity: 0.7; }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 25px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 450px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
            animation: slideInUp 0.8s ease-out;
        }

        @keyframes slideInUp {
            0% {
                opacity: 0;
                transform: translateY(50px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: var(--gradient-primary);
            padding: 2.5rem 2rem;
            text-align: center;
            color: #000;
            position: relative;
        }

        .login-header::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-top: 20px solid var(--primary-gold);
        }

        .login-header i {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .login-header h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
        }

        .login-form {
            padding: 3rem 2.5rem 2.5rem;
        }

        .form-group {
            margin-bottom: 1.8rem;
            position: relative;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.8rem;
            display: block;
            font-size: 0.95rem;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 0.25rem rgba(0, 102, 204, 0.15);
            outline: none;
            transform: translateY(-2px);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 1.1rem;
            z-index: 3;
        }

        .form-control:focus + .input-icon {
            color: var(--accent-blue);
        }

        .btn-login {
            background: var(--gradient-blue);
            border: none;
            color: #fff;
            padding: 1.2rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
            box-shadow: 0 8px 25px rgba(30, 60, 114, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(30, 60, 114, 0.4);
            color: #fff;
        }

        .back-link {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            text-decoration: none;
            padding: 0.8rem 1.2rem;
            border-radius: 25px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .back-link:hover {
            background: var(--primary-gold);
            color: #000;
            transform: translateX(-3px);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: #fff;
        }

        .alert-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: #fff;
        }

        .alert-info {
            background: linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%);
            color: #fff;
        }

        .form-check {
            margin: 1.5rem 0;
        }

        .form-check-input {
            border-radius: 4px;
            border: 2px solid #e9ecef;
        }

        .form-check-input:checked {
            background-color: var(--accent-blue);
            border-color: var(--accent-blue);
        }

        .form-check-label {
            font-weight: 500;
            color: #555;
        }

        .divider {
            text-align: center;
            margin: 2rem 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
        }

        .divider span {
            background: #fff;
            padding: 0 1rem;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .auth-links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .auth-links a {
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .auth-links a:hover {
            color: var(--primary-gold);
        }

        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }

        .forgot-password a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .forgot-password a:hover {
            color: var(--accent-blue);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
            z-index: 3;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--accent-blue);
        }

        .login-benefits {
            background: rgba(0, 102, 204, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.8rem;
            font-size: 0.9rem;
            color: #555;
        }

        .benefit-item:last-child {
            margin-bottom: 0;
        }

        .benefit-item i {
            color: var(--success-green);
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .login-container {
                margin: 1rem;
                border-radius: 20px;
            }
            
            .login-form {
                padding: 2.5rem 2rem 2rem;
            }
            
            .back-link {
                top: 10px;
                left: 10px;
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
            
            .login-header i {
                font-size: 3rem;
            }
        }

        .loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Back Link -->
    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Back to Resources
    </a>

    <div class="container">
        <div class="login-container">
            <!-- Header -->
            <div class="login-header">
                <i class="fas fa-graduation-cap"></i>
                <h2>Welcome Back</h2>
                <p class="mb-0">Sign in to access your educational resources</p>
            </div>

            <!-- Login Form -->
            <div class="login-form">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['registered'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Registration successful! Please login with your credentials.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['logout'])): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        You have been logged out successfully.
                    </div>
                <?php endif; ?>

                <!-- Login Benefits -->
                <div class="login-benefits">
                    <h6 class="mb-3">
                        <i class="fas fa-star text-warning me-1"></i>
                        Member Benefits
                    </h6>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Access to premium study materials</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Download past papers and solutions</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Track your learning progress</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Personalized study recommendations</span>
                    </div>
                </div>

                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-1"></i>
                            Email Address
                        </label>
                        <div class="position-relative">
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                   placeholder="Enter your email address" required>
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-1"></i>
                            Password
                        </label>
                        <div class="position-relative">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter your password" required>
                            <i class="fas fa-lock input-icon"></i>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
                        </div>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                        <label class="form-check-label" for="remember_me">
                            Remember me for 30 days
                        </label>
                    </div>

                    <button type="submit" class="btn-login" id="loginBtn">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Sign In
                    </button>
                </form>

                <div class="forgot-password">
                    <a href="forgot-password.php">
                        <i class="fas fa-key me-1"></i>
                        Forgot your password?
                    </a>
                </div>

                <div class="divider">
                    <span>Don't have an account?</span>
                </div>

                <div class="auth-links">
                    <a href="register.php" class="btn btn-outline-primary w-100" style="border-radius: 25px; font-weight: 600;">
                        <i class="fas fa-user-plus me-2"></i>
                        Create New Account
                    </a>
                </div>

                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Your data is protected with industry-standard security
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash password-toggle';
            } else {
                passwordField.type = 'password';
                toggleIcon.className = 'fas fa-eye password-toggle';
            }
        }

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const loginBtn = document.getElementById('loginBtn');
            const originalContent = loginBtn.innerHTML;
            
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
            loginBtn.classList.add('loading');
            loginBtn.disabled = true;
            
            // Re-enable after timeout (fallback)
            setTimeout(() => {
                loginBtn.innerHTML = originalContent;
                loginBtn.classList.remove('loading');
                loginBtn.disabled = false;
            }, 10000);
        });

        // Email validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '';
            }
        });

        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });

        // Enhanced security: Clear form on page hide
        window.addEventListener('pagehide', function() {
            document.getElementById('password').value = '';
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });

        // Add ripple effect to login button
        document.querySelector('.btn-login').addEventListener('click', function(e) {
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
            ripple.style.background = 'rgba(255, 255, 255, 0.3)';
            ripple.style.pointerEvents = 'none';
            ripple.style.animation = 'ripple 0.6s ease-out';
            ripple.style.zIndex = '1';
            
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
                    transform: scale(2);
                    opacity: 0;
                }
            }
            
            .btn-login {
                position: relative;
                overflow: hidden;
            }
        `;
        document.head.appendChild(style);

        // Form validation feedback
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('invalid', function() {
                this.style.borderColor = '#dc3545';
                this.style.animation = 'shake 0.5s ease-in-out';
            });
            
            input.addEventListener('input', function() {
                if (this.checkValidity()) {
                    this.style.borderColor = '#28a745';
                } else {
                    this.style.borderColor = '#dc3545';
                }
            });
        });

        // Add shake animation
        const shakeStyle = document.createElement('style');
        shakeStyle.textContent = `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
        `;
        document.head.appendChild(shakeStyle);
    </script>
</body>
</html>