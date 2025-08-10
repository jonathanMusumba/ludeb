<?php
// Start session with cookie lifetime set to 0 (expires on browser close)
session_set_cookie_params(0);
session_start();

// Initialize variables
$error = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prevent session fixation
    session_regenerate_id(true);

    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "ludeb";

    try {
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }

        // Get form data
        $user = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $pass = $_POST['password'] ?? '';

        if (!$user || !$pass) {
            throw new Exception("Username/email and password are required.");
        }

        // Prepare and execute SQL statement
        $stmt = $conn->prepare("SELECT id, username, email, password, role, status FROM system_users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $user, $user);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();

            // Check status
            if ($row['status'] == 'Invalid') {
                $conn->query("CALL log_action('Login Attempt', NULL, 'Failed: Invalid account status for {$row['username']}')");
                throw new Exception("Your account is invalid. Please contact support.");
            }

            // Verify password
            if (password_verify($pass, $row['password'])) {
                // Update last login time
                $now = date('Y-m-d H:i:s');
                $updateStmt = $conn->prepare("UPDATE system_users SET last_login = ? WHERE id = ?");
                $updateStmt->bind_param("si", $now, $row['id']);
                $updateStmt->execute();
                $updateStmt->close();

                // Set session variables
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];

                // Log successful login
                $conn->query("CALL log_action('Login Success', {$row['id']}, 'User {$row['username']} logged in')");

                // Set success flag and redirect URL
                $success = true;
                $redirectUrl = '';
                switch ($row['role']) {
                    case 'System Admin':
                        $redirectUrl = "admin/Dashboard.php";
                        break;
                    case 'Examination Administrator':
                        $redirectUrl = "inspection/AdminDashboard.php";
                        break;
                    case 'Data Entrant':
                        $redirectUrl = "entrant/home.php";
                        break;
                    default:
                        $redirectUrl = "index.php";
                        break;
                }
            } else {
                $conn->query("CALL log_action('Login Attempt', NULL, 'Failed: Invalid password for {$row['username']}')");
                throw new Exception("Invalid username/email or password.");
            }
        } else {
            $conn->query("CALL log_action('Login Attempt', NULL, 'Failed: No user found for {$user}')");
            throw new Exception("Invalid username/email or password.");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    } finally {
        if (isset($stmt)) $stmt->close();
        if (isset($conn)) $conn->close();
    }
}

// Clear any existing session errors
if (isset($_SESSION['login_error'])) {
    if (empty($error)) {
        $error = $_SESSION['login_error'];
    }
    unset($_SESSION['login_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Results Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #4facfe;
            --warning-color: #ffd700;
            --error-color: #ff6b6b;
            --dark-overlay: rgba(0, 0, 0, 0.3);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .bg-animation::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: drift 20s linear infinite;
        }

        @keyframes drift {
            0% { transform: rotate(0deg) translate(-50%, -50%); }
            100% { transform: rotate(360deg) translate(-50%, -50%); }
        }

        /* Floating particles */
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            pointer-events: none;
            animation: float 6s ease-in-out infinite;
        }

        .particle:nth-child(1) { width: 80px; height: 80px; top: 10%; left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { width: 120px; height: 120px; top: 70%; left: 80%; animation-delay: 2s; }
        .particle:nth-child(3) { width: 60px; height: 60px; top: 40%; left: 70%; animation-delay: 4s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Main Container */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--accent-color), var(--success-color));
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            font-weight: 300;
        }

        .form-floating {
            position: relative;
            margin-bottom: 20px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 1rem;
            color: #333;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.95);
            border-color: var(--success-color);
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.2);
            transform: translateY(-2px);
        }

        .form-control::placeholder {
            color: rgba(0, 0, 0, 0.5);
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            background: transparent;
            border: none;
            color: #666;
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 5;
            cursor: pointer;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--success-color), var(--accent-color));
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 172, 254, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-login:hover::before {
            width: 300px;
            height: 300px;
        }

        .alert {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            border-radius: 12px;
            color: #ff6b6b;
            padding: 12px 16px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: white;
        }

        /* Loading Spinner */
        .loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(102, 126, 234, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(10px);
        }

        .spinner-container {
            text-align: center;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255, 255, 255, 0.2);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        .spinner-text {
            color: white;
            font-size: 1.1rem;
            font-weight: 500;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Validation Messages */
        .validation-message {
            color: var(--error-color);
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .validation-message.show {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-card {
                padding: 30px 20px;
                margin: 20px;
            }
            
            .login-header h1 {
                font-size: 1.8rem;
            }
        }

        /* Success Animation */
        .success-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--success-color), var(--accent-color));
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }

        .success-checkmark {
            width: 80px;
            height: 80px;
            border: 4px solid white;
            border-radius: 50%;
            position: relative;
            animation: scale 0.5s ease-in-out;
        }

        .success-checkmark::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 28px;
            width: 10px;
            height: 20px;
            border: solid white;
            border-width: 0 4px 4px 0;
            transform: rotate(45deg);
        }

        @keyframes scale {
            0% { transform: scale(0); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><i class="fas fa-graduation-cap"></i> Welcome Back</h1>
                <p>Sign in to your Results Management System</p>
            </div>

            <?php if ($error): ?>
                <div class="alert" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="post" action="">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username or Email" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <div class="validation-message" id="usernameHelp">Please enter a valid username or email.</div>
                </div>

                <div class="form-floating">
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <span class="input-group-text" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div class="validation-message" id="passwordHelp">Password cannot be empty.</div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div class="loader" id="loader">
        <div class="spinner-container">
            <div class="spinner"></div>
            <div class="spinner-text">Signing you in...</div>
        </div>
    </div>

    <!-- Success Animation -->
    <div class="success-animation" id="successAnimation">
        <div class="success-checkmark"></div>
    </div>

    <?php if ($success): ?>
    <script>
        // Show success animation and redirect
        document.getElementById('successAnimation').style.display = 'flex';
        setTimeout(function() {
            window.location.href = '<?php echo $redirectUrl; ?>';
        }, 1500);
    </script>
    <?php endif; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Form validation and submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            let valid = true;
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const usernameHelp = document.getElementById('usernameHelp');
            const passwordHelp = document.getElementById('passwordHelp');

            // Clear previous validation messages
            usernameHelp.classList.remove('show');
            passwordHelp.classList.remove('show');

            // Validate username
            if (!username) {
                usernameHelp.textContent = 'Please enter a username or email.';
                usernameHelp.classList.add('show');
                valid = false;
            } else if (!/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(username) && username.length < 3) {
                usernameHelp.textContent = 'Username must be at least 3 characters or a valid email.';
                usernameHelp.classList.add('show');
                valid = false;
            }

            // Validate password
            if (!password) {
                passwordHelp.textContent = 'Password cannot be empty.';
                passwordHelp.classList.add('show');
                valid = false;
            } else if (password.length < 6) {
                passwordHelp.textContent = 'Password must be at least 6 characters.';
                passwordHelp.classList.add('show');
                valid = false;
            }

            if (!valid) {
                e.preventDefault();
                
                // Shake animation for invalid form
                const loginCard = document.querySelector('.login-card');
                loginCard.style.animation = 'shake 0.5s ease-in-out';
                setTimeout(() => {
                    loginCard.style.animation = '';
                }, 500);
            } else {
                // Show loading spinner
                document.getElementById('loader').style.display = 'flex';
            }
        });

        // Shake animation keyframes
        const shakeKeyframes = `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = shakeKeyframes;
        document.head.appendChild(styleSheet);

        // Input focus effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Auto-hide error messages after 5 seconds
        <?php if ($error): ?>
        setTimeout(function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>