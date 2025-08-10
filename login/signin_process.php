<?php
session_start();

// Database connection
$host = 'localhost';
$db = 'ludeb';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Sanitize input
function sanitize_input($data) {
    return htmlspecialchars(trim($data));
}

$email = sanitize_input($_POST['email']);
$password = sanitize_input($_POST['password']);

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo 'Invalid email format.';
    exit;
}

// Check credentials
$query = "SELECT id, password, role, status FROM users WHERE email = :email";
$stmt = $pdo->prepare($query);
$stmt->execute(['email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    if ($user['status'] === 'active') {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header('Location: dashboard.php');
        exit;
    } elseif ($user['status'] === 'rejected') {
        // Log out user and redirect
        session_unset();
        session_destroy();
        header('Location: rejected.php
        '); // Show rejected page
        exit;
    } else {
        echo 'Account is not active. Please contact the administrator.';
    }
} else {
    echo 'Invalid email or password.';
}
?>
