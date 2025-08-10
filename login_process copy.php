<?php
// Start session with cookie lifetime set to 0 (expires on browser close)
session_set_cookie_params(0);
session_start();

// Prevent session fixation
session_regenerate_id(true);

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LUDEB";

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

            // Redirect based on role
            switch ($row['role']) {
                case 'System Admin':
                    header("Location: admin/Dashboard.php");
                    break;
                case 'Examination Administrator':
                    header("Location: inspection/AdminDashboard.php");
                    break;
                case 'Data Entrant':
                    header("Location: entrant/home.php"); // Corrected path
                    break;
                default:
                    header("Location: index.php");
                    break;
            }
            exit();
        } else {
            $conn->query("CALL log_action('Login Attempt', NULL, 'Failed: Invalid password for {$row['username']}')");
            throw new Exception("Invalid password.");
        }
    } else {
        $conn->query("CALL log_action('Login Attempt', NULL, 'Failed: No user found for {$user}')");
        throw new Exception("No user found with that username/email.");
    }
} catch (Exception $e) {
    $_SESSION['login_error'] = $e->getMessage();
    header("Location: login.php");
    exit();
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>