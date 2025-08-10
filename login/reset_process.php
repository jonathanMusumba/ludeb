<?php
session_start();

// Database connection
$host = 'localhost';
$db = 'ludeb';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$token = $_POST['token'];
$password = $_POST['password'];

// Validate password strength
function validate_password($password) {
    $lengthCriteria = strlen($password) >= 8;
    $hasUppercase = preg_match('/[A-Z]/', $password);
    $hasLowercase = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSpecial = preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password);

    return $lengthCriteria && $hasUppercase && $hasLowercase && $hasNumber && $hasSpecial;
}

if (!validate_password($password)) {
    die('Password does not meet criteria!');
}

// Hash the new password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Get token details
$query = "SELECT email, expires FROM password_resets WHERE token = :token";
$stmt = $pdo->prepare($query);
$stmt->execute(['token' => $token]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if ($reset && $reset['expires'] > date('Y-m-d H:i:s')) {
    // Update user password
    $query = "UPDATE users SET password = :password WHERE email = :email";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'password' => $hashedPassword,
        'email' => $reset['email']
    ]);

    // Delete the token
    $query = "DELETE FROM password_resets WHERE token = :token";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['token' => $token]);

    echo 'Your password has been reset successfully.';
} else {
    echo 'Invalid or expired reset token.';
}
?>
