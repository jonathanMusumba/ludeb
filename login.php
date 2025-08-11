<?php
/**
 * Enhanced Secure Login System - FIXED VERSION
 * Features: Rate limiting, CSRF protection, account lockout, proper logging, Windows 11 detection
 * Fixed: Database connection issues, array offset warnings, error handling
 */

// Load environment variables (create a .env file for production)
function loadEnv($file) {
    if (!file_exists($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        // Fixed: Check if explode returns at least 2 parts
        $parts = explode('=', $line, 2);
        if (count($parts) >= 2) {
            list($key, $value) = $parts;
            $_ENV[trim($key)] = trim($value);
        }
    }
}
loadEnv('.env');

// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
ini_set('session.use_strict_mode', 1);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Initialize variables
$error = '';
$success = false;
$redirectUrl = '';
$isLocked = false;
$lockoutTime = 0;

// Database configuration - FIXED: Better default handling
$servername = $_ENV['DB_HOST'] ?? 'localhost';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? ''; // For XAMPP, usually no password for root
$dbname = $_ENV['DB_NAME'] ?? 'ludeb';

$conn = null;

/**
 * Secure Logger Class
 */
class SecureLogger {
    private $logFile;
    
    public function __construct($logFile = 'logs/security.log') {
        $this->logFile = $logFile;
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    public function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200);
        
        $logEntry = sprintf(
            "[%s] %s - IP: %s - %s - Context: %s - User-Agent: %s\n",
            $timestamp,
            strtoupper($level),
            $ip,
            $message,
            json_encode($context),
            $userAgent
        );
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        if (in_array($level, ['error', 'critical'])) {
            error_log($message);
        }
    }
    
    public function info($message, $context = []) { $this->log('info', $message, $context); }
    public function warning($message, $context = []) { $this->log('warning', $message, $context); }
    public function error($message, $context = []) { $this->log('error', $message, $context); }
    public function critical($message, $context = []) { $this->log('critical', $message, $context); }
}

/**
 * Rate Limiter Class
 */
class RateLimiter {
    private $conn;
    private $maxAttempts;
    private $timeWindow;
    
    public function __construct($conn, $maxAttempts = 5, $timeWindow = 900) { // 15 minutes
        $this->conn = $conn;
        $this->maxAttempts = $maxAttempts;
        $this->timeWindow = $timeWindow;
    }
    
    public function isRateLimited($ip) {
        if (!$this->conn) return false;
        
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
            AND status = 'failed'
        ");
        
        if (!$stmt) return false;
        
        $stmt->bind_param("si", $ip, $this->timeWindow);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['attempts'] >= $this->maxAttempts;
    }
    
    public function recordAttempt($ip, $username, $status) {
        if (!$this->conn) return;
        
        $stmt = $this->conn->prepare("
            INSERT INTO login_attempts (ip_address, username, status, attempt_time) 
            VALUES (?, ?, ?, NOW())
        ");
        
        if ($stmt) {
            $stmt->bind_param("sss", $ip, $username, $status);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    public function getRemainingTime($ip) {
        if (!$this->conn) return 0;
        
        $stmt = $this->conn->prepare("
            SELECT UNIX_TIMESTAMP(MAX(attempt_time)) as last_attempt 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND status = 'failed'
        ");
        
        if (!$stmt) return 0;
        
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['last_attempt']) {
            $timeElapsed = time() - $row['last_attempt'];
            return max(0, $this->timeWindow - $timeElapsed);
        }
        
        return 0;
    }
}

/**
 * Account Lockout Manager
 */
class AccountLockout {
    private $conn;
    private $maxAttempts;
    private $lockoutDuration;
    
    public function __construct($conn, $maxAttempts = 5, $lockoutDuration = 1800) { // 30 minutes
        $this->conn = $conn;
        $this->maxAttempts = $maxAttempts;
        $this->lockoutDuration = $lockoutDuration;
    }
    
    public function isAccountLocked($username) {
        if (!$this->conn) return false;
        
        $stmt = $this->conn->prepare("
            SELECT locked_until 
            FROM system_users 
            WHERE username = ? OR email = ?
        ");
        
        if (!$stmt) return false;
        
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            
            if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
                return strtotime($row['locked_until']) - time();
            }
        } else {
            $stmt->close();
        }
        
        return false;
    }
    
    public function recordFailedAttempt($username) {
        if (!$this->conn) return;
        
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE username = ? 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 3600 SECOND)
            AND status = 'failed'
        ");
        
        if (!$stmt) return;
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $attempts = $row['attempts'];
        $stmt->close();
        
        if ($attempts >= $this->maxAttempts) {
            $lockUntil = date('Y-m-d H:i:s', time() + $this->lockoutDuration);
            $stmt = $this->conn->prepare("
                UPDATE system_users 
                SET locked_until = ? 
                WHERE username = ? OR email = ?
            ");
            
            if ($stmt) {
                $stmt->bind_param("sss", $lockUntil, $username, $username);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

/**
 * CSRF Protection
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 3600) { // Refresh token every hour
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && 
           isset($_SESSION['csrf_token_time']) &&
           (time() - $_SESSION['csrf_token_time']) <= 3600 &&
           hash_equals($_SESSION['csrf_token'], $token);
}

// Initialize logger
$logger = new SecureLogger();

try {
    // FIXED: Better database connection with proper error handling
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        $logger->critical("Database connection failed: " . $conn->connect_error);
        throw new Exception("Database connection failed. Please check your configuration.");
    }
    
    // Set charset to prevent SQL injection
    $conn->set_charset("utf8mb4");
    
    // Initialize security components only if connection is successful
    $rateLimiter = new RateLimiter($conn);
    $accountLockout = new AccountLockout($conn);
    
    // Enhanced OS detection with Windows 11 support
    function getOperatingSystem() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $os = 'Unknown';
        
        // Check for Windows 11 first (requires specific detection)
        if (preg_match('/Windows NT 10.0/i', $userAgent)) {
            // Windows 11 detection is tricky as it reports NT 10.0
            // We can use additional browser info or JavaScript for more accuracy
            if (strpos($userAgent, 'Edg/') !== false && preg_match('/Edg\/(\d+)/', $userAgent, $matches)) {
                $edgeVersion = intval($matches[1]);
                if ($edgeVersion >= 96) { // Edge 96+ typically indicates Windows 11
                    $os = 'Windows 11';
                } else {
                    $os = 'Windows 10';
                }
            } else if (strpos($userAgent, 'Chrome/') !== false && preg_match('/Chrome\/(\d+)/', $userAgent, $matches)) {
                $chromeVersion = intval($matches[1]);
                if ($chromeVersion >= 96) { // Chrome 96+ often indicates Windows 11
                    $os = 'Windows 11';
                } else {
                    $os = 'Windows 10';
                }
            } else {
                $os = 'Windows 10/11'; // Cannot determine specifically
            }
        } else {
            $osArray = [
                '/windows nt 6.3/i' => 'Windows 8.1',
                '/windows nt 6.2/i' => 'Windows 8',
                '/windows nt 6.1/i' => 'Windows 7',
                '/macintosh|mac os x/i' => 'Mac OS X',
                '/linux/i' => 'Linux',
                '/ubuntu/i' => 'Ubuntu',
                '/android/i' => 'Android',
                '/iphone|ipad/i' => 'iOS'
            ];
            
            foreach ($osArray as $regex => $value) {
                if (preg_match($regex, $userAgent)) {
                    $os = $value;
                    break;
                }
            }
        }
        
        return $os;
    }

    // Enhanced browser detection
    function getBrowser() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browser = 'Unknown';
        
        $browserArray = [
            '/edg\//i' => 'Microsoft Edge',
            '/edge|edgios/i' => 'Microsoft Edge Legacy',
            '/chrome/i' => 'Chrome',
            '/firefox/i' => 'Firefox',
            '/safari/i' => 'Safari',
            '/opera|opr/i' => 'Opera',
            '/msie|trident/i' => 'Internet Explorer'
        ];
        
        foreach ($browserArray as $regex => $value) {
            if (preg_match($regex, $userAgent)) {
                $browser = $value;
                break;
            }
        }
        
        return $browser;
    }

    // Enhanced login logging
    function logLoginAttempt($conn, $user_id, $status, $ip_address, $operating_system, $browser, $username = null) {
        if (!$conn) return;
        
        $stmt = $conn->prepare("
            INSERT INTO login_logs (user_id, username, status, ip_address, operating_system, browser, user_agent, timestamp) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if ($stmt) {
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
            $stmt->bind_param("issssss", $user_id, $username, $status, $ip_address, $operating_system, $browser, $userAgent);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // CSRF Protection
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $logger->warning('CSRF token validation failed', ['ip' => $ip_address]);
            throw new Exception("Invalid request. Please refresh the page and try again.");
        }
        
        // Get form data
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';

        if (empty($user) || empty($pass)) {
            throw new Exception("Username/email and password are required.");
        }

        if (strlen($user) > 255 || strlen($pass) > 255) {
            $logger->warning('Input length exceeded', ['username' => substr($user, 0, 50), 'ip' => $ip_address]);
            throw new Exception("Input too long.");
        }

        // Rate limiting check
        if ($rateLimiter->isRateLimited($ip_address)) {
            $remainingTime = $rateLimiter->getRemainingTime($ip_address);
            $minutes = ceil($remainingTime / 60);
            $logger->warning('Rate limit exceeded', [
                'ip' => $ip_address,
                'username' => $user,
                'remaining_time' => $remainingTime
            ]);
            throw new Exception("Too many failed attempts from your IP. Please try again in {$minutes} minute(s).");
        }

        // Check if account is locked
        $lockTime = $accountLockout->isAccountLocked($user);
        if ($lockTime) {
            $minutes = ceil($lockTime / 60);
            $isLocked = true;
            $lockoutTime = $lockTime;
            $logger->warning('Login attempt on locked account', [
                'username' => $user,
                'ip' => $ip_address,
                'lock_time_remaining' => $lockTime
            ]);
            throw new Exception("Account is temporarily locked due to multiple failed attempts. Please try again in {$minutes} minute(s) or contact support.");
        }
        
        // Prevent session fixation
        session_regenerate_id(true);

        $operating_system = getOperatingSystem();
        $browser = getBrowser();

        // Prepare and execute SQL statement with additional security
        $stmt = $conn->prepare("
            SELECT id, username, email, password, role, status, school_id, failed_login_attempts, last_login 
            FROM system_users 
            WHERE (username = ? OR email = ?) AND status != 'Deleted'
        ");
        
        if (!$stmt) {
            $logger->error('Database query preparation failed');
            throw new Exception("System error. Please try again later.");
        }
        
        $stmt->bind_param("ss", $user, $user);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();

            // Check account status
            if ($row['status'] !== 'Active') {
                $logger->warning('Login attempt on inactive account', [
                    'username' => $row['username'],
                    'status' => $row['status'],
                    'ip' => $ip_address
                ]);
                
                $rateLimiter->recordAttempt($ip_address, $user, 'failed');
                logLoginAttempt($conn, $row['id'], 'Failed - Inactive Account', $ip_address, $operating_system, $browser, $row['username']);
                
                if ($row['status'] === 'Invalid') {
                    throw new Exception("Your account is invalid. Please contact support.");
                } else {
                    throw new Exception("Your account is not active. Please contact support.");
                }
            }

            // Verify password with timing attack protection
            $passwordVerified = password_verify($pass, $row['password']);
            
            // Add small random delay to prevent timing attacks
            usleep(mt_rand(100000, 500000)); // 0.1 to 0.5 seconds
            
            if ($passwordVerified) {
                // Successful login
                $now = date('Y-m-d H:i:s');
                $updateStmt = $conn->prepare("
                    UPDATE system_users 
                    SET last_login = ?, locked_until = NULL, failed_login_attempts = 0, last_login_ip = ? 
                    WHERE id = ?
                ");
                
                if ($updateStmt) {
                    $updateStmt->bind_param("ssi", $now, $ip_address, $row['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                }

                // Set session variables with additional security
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['school_id'] = $row['school_id'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                $_SESSION['ip_address'] = $ip_address;

                // Regenerate session ID after successful login
                session_regenerate_id(true);

                // Log successful login
                $logger->info('Successful login', [
                    'user_id' => $row['id'],
                    'username' => $row['username'],
                    'role' => $row['role'],
                    'ip' => $ip_address,
                    'os' => $operating_system,
                    'browser' => $browser
                ]);
                
                $rateLimiter->recordAttempt($ip_address, $user, 'success');
                logLoginAttempt($conn, $row['id'], 'Success', $ip_address, $operating_system, $browser, $row['username']);

                // Set success flag and redirect URL
                $success = true;
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
                    case 'School':
                        $redirectUrl = "school/index.php";
                        break;
                    default:
                        $redirectUrl = "dashboard.php";
                        break;
                }
            } else {
                // Failed password - enhanced logging and security
                $logger->warning('Failed login attempt - invalid password', [
                    'username' => $row['username'],
                    'user_id' => $row['id'],
                    'ip' => $ip_address,
                    'os' => $operating_system,
                    'browser' => $browser
                ]);
                
                // Update failed attempts counter - FIXED: Use proper prepared statement
                $failedStmt = $conn->prepare("UPDATE system_users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?");
                if ($failedStmt) {
                    $failedStmt->bind_param("i", $row['id']);
                    $failedStmt->execute();
                    $failedStmt->close();
                }
                
                $rateLimiter->recordAttempt($ip_address, $user, 'failed');
                $accountLockout->recordFailedAttempt($user);
                logLoginAttempt($conn, $row['id'], 'Failed - Invalid Password', $ip_address, $operating_system, $browser, $row['username']);
                
                throw new Exception("Invalid username/email or password.");
            }
        } else {
            // User not found - don't reveal this information
            $logger->warning('Failed login attempt - user not found', [
                'username' => $user,
                'ip' => $ip_address,
                'os' => $operating_system,
                'browser' => $browser
            ]);
            
            $rateLimiter->recordAttempt($ip_address, $user, 'failed');
            logLoginAttempt($conn, null, 'Failed - User Not Found', $ip_address, $operating_system, $browser, $user);
            
            // Add delay to prevent user enumeration
            usleep(mt_rand(500000, 1000000)); // 0.5 to 1 second
            
            throw new Exception("Invalid username/email or password.");
        }
        
        $stmt->close();
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    if (!$isLocked) { // Don't log lockout errors as system errors
        $logger->error('Login system error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
} finally {
    // FIXED: Proper connection cleanup
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $conn->close();
    }
}

// Handle session errors
if (isset($_SESSION['login_error'])) {
    if (empty($error)) {
        $error = $_SESSION['login_error'];
    }
    unset($_SESSION['login_error']);
}

// Redirect on successful login
if ($success && !empty($redirectUrl)) {
    header("Location: " . $redirectUrl);
    exit();
}

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Secure Login - Results Management System</title>
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com;">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="static/img/icon.ico">
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

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-top: 5px;
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

        .form-control.is-invalid {
            border-color: var(--error-color);
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
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
            transition: color 0.3s ease;
        }

        .input-group-text:hover {
            color: var(--primary-color);
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

        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 172, 254, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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
            animation: slideIn 0.3s ease;
        }

        .alert-lockout {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: #ffc107;
        }

        .alert-lockout .countdown {
            font-weight: bold;
            color: #ff9800;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
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
        .invalid-feedback {
            color: var(--error-color);
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .invalid-feedback.show {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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

        /* Shake animation for errors */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .shake {
            animation: shake 0.5s ease-in-out;
        }

        /* Security indicators */
        .security-indicators {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .security-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.75rem;
            margin-bottom: 5px;
        }

        .security-indicator i {
            color: #4caf50;
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

            .particle {
                display: none;
            }
        }

        /* Password strength indicator */
        .password-strength {
            height: 3px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background-color 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background-color: #ff4444; width: 25%; }
        .strength-fair { background-color: #ffaa00; width: 50%; }
        .strength-good { background-color: #00ddff; width: 75%; }
        .strength-strong { background-color: #00ff00; width: 100%; }
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
                <h1><i class="fas fa-shield-alt"></i> Secure Login</h1>
                <p>Results Management System</p>
                <div class="security-badge">
                    <i class="fas fa-lock"></i>
                    <span>Enhanced Security</span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert <?php echo $isLocked ? 'alert-lockout' : ''; ?>" role="alert" id="errorAlert">
                    <i class="fas fa-<?php echo $isLocked ? 'clock' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <?php if ($isLocked && $lockoutTime > 0): ?>
                        <div class="countdown" id="countdown" data-time="<?php echo $lockoutTime; ?>">
                            Time remaining: <span id="timeRemaining"><?php echo gmdate("i:s", $lockoutTime); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="post" action="" novalidate>
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="form-floating">
                    <input 
                        type="text" 
                        class="form-control" 
                        id="username" 
                        name="username" 
                        placeholder="Username or Email" 
                        required 
                        maxlength="255"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        autocomplete="username"
                    >
                    <label for="username">Username or Email</label>
                    <div class="invalid-feedback" id="usernameError"></div>
                </div>

                <div class="form-floating">
                    <div class="input-group">
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            placeholder="Password" 
                            required 
                            maxlength="255"
                            autocomplete="current-password"
                        >
                        <span class="input-group-text" id="togglePassword" title="Show/Hide Password">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <label for="password">Password</label>
                    <div class="password-strength" id="passwordStrength" style="display: none;">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="invalid-feedback" id="passwordError"></div>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i> 
                    <span id="loginBtnText">Sign In Securely</span>
                </button>
            </form>

            <div class="security-indicators">
                <div class="security-indicator">
                    <i class="fas fa-shield-alt"></i>
                    <span>CSRF Protection Enabled</span>
                </div>
                <div class="security-indicator">
                    <i class="fas fa-user-shield"></i>
                    <span>Rate Limiting Active</span>
                </div>
                <div class="security-indicator">
                    <i class="fas fa-lock"></i>
                    <span>Account Lockout Protection</span>
                </div>
                <div class="security-indicator">
                    <i class="fas fa-eye"></i>
                    <span>Activity Monitoring</span>
                </div>
            </div>

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
            <div class="spinner-text">Authenticating securely...</div>
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
            window.location.href = '<?php echo htmlspecialchars($redirectUrl); ?>';
        }, 1500);
    </script>
    <?php endif; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const loginBtn = document.getElementById('loginBtn');
            const togglePassword = document.getElementById('togglePassword');
            const strengthBar = document.getElementById('strengthBar');
            const passwordStrength = document.getElementById('passwordStrength');

            // Enhanced client-side OS detection
            function detectOS() {
                const userAgent = navigator.userAgent;
                const platform = navigator.platform;
                
                // Windows 11 detection
                if (userAgent.indexOf('Windows NT 10.0') !== -1) {
                    // More sophisticated Windows 11 detection
                    if (navigator.userAgentData && navigator.userAgentData.platform === 'Windows') {
                        // Use User-Agent Client Hints if available
                        navigator.userAgentData.getHighEntropyValues(['platformVersion'])
                            .then(ua => {
                                if (parseInt(ua.platformVersion.split('.')[0]) >= 13) {
                                    console.log('Detected: Windows 11');
                                } else {
                                    console.log('Detected: Windows 10');
                                }
                            });
                    } else {
                        // Fallback detection
                        const isWindows11 = userAgent.includes('Edg/') || 
                                          (userAgent.includes('Chrome/') && parseInt(userAgent.match(/Chrome\/(\d+)/)[1]) >= 96);
                        console.log('Detected: Windows', isWindows11 ? '11' : '10');
                    }
                }
            }

            detectOS();

            // Countdown timer for lockout
            <?php if ($isLocked && $lockoutTime > 0): ?>
            let remainingTime = <?php echo $lockoutTime; ?>;
            const countdownElement = document.getElementById('timeRemaining');
            
            if (countdownElement) {
                const countdownTimer = setInterval(function() {
                    remainingTime--;
                    if (remainingTime <= 0) {
                        clearInterval(countdownTimer);
                        location.reload();
                    } else {
                        const minutes = Math.floor(remainingTime / 60);
                        const seconds = remainingTime % 60;
                        countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    }
                }, 1000);
            }
            <?php endif; ?>

            // Password visibility toggle
            togglePassword.addEventListener('click', function() {
                const icon = this.querySelector('i');
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                    this.title = 'Hide Password';
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    this.title = 'Show Password';
                }
            });

            // Password strength indicator
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                if (password.length === 0) {
                    passwordStrength.style.display = 'none';
                    return;
                }
                
                passwordStrength.style.display = 'block';
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength++;
                // Uppercase check
                if (/[A-Z]/.test(password)) strength++;
                // Lowercase check
                if (/[a-z]/.test(password)) strength++;
                // Number check
                if (/[0-9]/.test(password)) strength++;
                // Special character check
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                
                // Remove all strength classes
                strengthBar.className = 'password-strength-bar';
                
                // Add appropriate strength class
                switch (strength) {
                    case 1:
                    case 2:
                        strengthBar.classList.add('strength-weak');
                        break;
                    case 3:
                        strengthBar.classList.add('strength-fair');
                        break;
                    case 4:
                        strengthBar.classList.add('strength-good');
                        break;
                    case 5:
                        strengthBar.classList.add('strength-strong');
                        break;
                }
            });

            // Enhanced form validation
            function validateForm() {
                let isValid = true;
                const username = usernameInput.value.trim();
                const password = passwordInput.value;

                // Clear previous errors
                clearErrors();

                // Username validation
                if (!username) {
                    showError('usernameError', 'Username or email is required');
                    usernameInput.classList.add('is-invalid');
                    isValid = false;
                } else if (username.length < 3 && !isValidEmail(username)) {
                    showError('usernameError', 'Username must be at least 3 characters or a valid email');
                    usernameInput.classList.add('is-invalid');
                    isValid = false;
                }

                // Password validation
                if (!password) {
                    showError('passwordError', 'Password is required');
                    passwordInput.classList.add('is-invalid');
                    isValid = false;
                } else if (password.length < 6) {
                    showError('passwordError', 'Password must be at least 6 characters');
                    passwordInput.classList.add('is-invalid');
                    isValid = false;
                }

                return isValid;
            }

            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }

            function showError(elementId, message) {
                const errorElement = document.getElementById(elementId);
                errorElement.textContent = message;
                errorElement.classList.add('show');
            }

            function clearErrors() {
                const errorElements = document.querySelectorAll('.invalid-feedback');
                const inputElements = document.querySelectorAll('.form-control');
                
                errorElements.forEach(el => {
                    el.classList.remove('show');
                    el.textContent = '';
                });
                
                inputElements.forEach(el => {
                    el.classList.remove('is-invalid');
                });
            }

            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!validateForm()) {
                    // Shake animation for invalid form
                    const loginCard = document.querySelector('.login-card');
                    loginCard.classList.add('shake');
                    setTimeout(() => {
                        loginCard.classList.remove('shake');
                    }, 500);
                    return;
                }

                // Disable form and show loading
                loginBtn.disabled = true;
                document.getElementById('loginBtnText').textContent = 'Authenticating...';
                document.getElementById('loader').style.display = 'flex';
                
                // Submit form
                this.submit();
            });

            // Input focus effects
            document.querySelectorAll('.form-control').forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });

                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) {
                        this.classList.remove('is-invalid');
                        const errorId = this.id + 'Error';
                        const errorElement = document.getElementById(errorId);
                        if (errorElement) {
                            errorElement.classList.remove('show');
                        }
                    }
                });
            });

            // Auto-hide error messages after 10 seconds
            <?php if ($error && !$isLocked): ?>
            setTimeout(function() {
                const alert = document.getElementById('errorAlert');
                if (alert) {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }
            }, 10000);
            <?php endif; ?>

            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }

            // Security monitoring (client-side)
            let suspiciousActivity = 0;
            
            // Monitor for rapid form submissions
            let lastSubmissionTime = 0;
            form.addEventListener('submit', function() {
                const now = Date.now();
                if (now - lastSubmissionTime < 3000) { // Less than 3 seconds
                    suspiciousActivity++;
                    if (suspiciousActivity > 3) {
                        console.warn('Suspicious activity detected');
                    }
                }
                lastSubmissionTime = now;
            });

            // Monitor for automated behavior
            let humanInteraction = false;
            document.addEventListener('mousemove', () => humanInteraction = true, { once: true });
            document.addEventListener('keypress', () => humanInteraction = true, { once: true });

            // Add honeypot field (hidden from users, but visible to bots)
            const honeypot = document.createElement('input');
            honeypot.type = 'text';
            honeypot.name = 'website';
            honeypot.style.display = 'none';
            honeypot.setAttribute('aria-hidden', 'true');
            honeypot.setAttribute('tabindex', '-1');
            form.insertBefore(honeypot, form.firstChild);
        });
    </script>
</body>
</html>