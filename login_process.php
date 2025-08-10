<?php
session_start();
require_once 'connections/db_connect.php';

// Function to detect operating system
function getOperatingSystem() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $os = 'Unknown';
    $osArray = [
        '/windows nt 10/i' => 'Windows 10',
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
    return $os;
}

// Function to detect browser
function getBrowser() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $browser = 'Unknown';
    $browserArray = [
        '/msie|trident/i' => 'Internet Explorer',
        '/firefox/i' => 'Firefox',
        '/chrome/i' => 'Chrome',
        '/safari/i' => 'Safari',
        '/opera|opr/i' => 'Opera',
        '/edge|edgios/i' => 'Edge'
    ];
    foreach ($browserArray as $regex => $value) {
        if (preg_match($regex, $userAgent)) {
            $browser = $value;
            break;
        }
    }
    return $browser;
}

// Function to log login attempt
function logLoginAttempt($conn, $user_id, $status, $ip_address, $operating_system, $browser) {
    $stmt = $conn->prepare("INSERT INTO login_logs (user_id, status, ip_address, operating_system, browser) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $status, $ip_address, $operating_system, $browser);
    $stmt->execute();
    $stmt->close();
}

// Initialize variables
$errors = [];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$operating_system = getOperatingSystem();
$browser = getBrowser();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Verify CSRF token
    if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid CSRF token';
        logLoginAttempt($conn, null, 'Failed', $ip_address, $operating_system, $browser);
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: login.php');
        exit;
    }

    // Validate inputs
    if (empty($username)) {
        $errors[] = 'Username or center number is required';
    }
    if (empty($password)) {
        $errors[] = 'Password is required';
    }

    if (empty($errors)) {
        // Check if the username is a school center number
        $stmt = $conn->prepare("SELECT u.id, u.username, u.password, u.role, u.school_id, u.status, s.center_no 
                               FROM system_users u 
                               LEFT JOIN schools s ON u.school_id = s.id 
                               WHERE u.username = ? OR s.center_no = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && ($user['username'] === $username || $user['center_no'] === $username) && password_verify($password, $user['password'])) {
            if ($user['status'] === 'Active') {
                // Update last login
                $stmt = $conn->prepare("UPDATE system_users SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $stmt->close();

                // Log successful login
                logLoginAttempt($conn, $user['id'], 'Success', $ip_address, $operating_system, $browser);

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['school_id'] = $user['school_id'];

                // Log action in audit_logs
                $conn->query("CALL log_action('Login', {$user['id']}, 'User logged in successfully')");

                // Redirect based on role
                if ($user['role'] === 'School') {
                    header('Location: ../school/index.php');
                } else {
                    header('Location: admin/dashboard.php');
                }
                exit;
            } else {
                $errors[] = 'Account is not active';
                logLoginAttempt($conn, $user['id'], 'Failed', $ip_address, $operating_system, $browser);
            }
        } else {
            $errors[] = 'Invalid username or password';
            logLoginAttempt($conn, null, 'Failed', $ip_address, $operating_system, $browser);
        }
    }

    // Store errors in session and redirect
    $_SESSION['error'] = implode('|', $errors);
    header('Location: login.php');
    exit;
} else {
    // Invalid request method
    logLoginAttempt($conn, null, 'Failed', $ip_address, $operating_system, $browser);
    $_SESSION['error'] = 'Invalid request method';
    header('Location: login.php');
    exit;
}

$conn->close();
?>