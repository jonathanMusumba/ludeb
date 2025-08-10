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

$username = sanitize_input($_POST['username']);
$email = sanitize_input($_POST['email']);
$role = sanitize_input($_POST['role']);
$password = sanitize_input($_POST['password']);

// Validate password strength
function validate_password($password) {
    $lengthCriteria = strlen($password) >= 8;
    $hasUppercase = preg_match('/[A-Z]/', $password);
    $hasLowercase = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSpecial = preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password);

    return $lengthCriteria && $hasUppercase && $hasLowercase && $hasNumber && $hasSpecial;
}

if (!preg_match('/^[a-zA-Z0-9_ ]+$/', $username)) {
    die('Invalid username!');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Invalid email!');
}

if (!validate_password($password)) {
    die('Password does not meet criteria!');
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Insert user with "pending" status
$query = "INSERT INTO users (username, email, role, password, status) VALUES (:username, :email, :role, :password, 'pending')";
$stmt = $pdo->prepare($query);
$stmt->execute([
    'username' => $username,
    'email' => $email,
    'role' => $role,
    'password' => $hashedPassword
]);

echo 'Sign up successful! Your account is pending approval.';
?>
