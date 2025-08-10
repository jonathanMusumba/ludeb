<?php
session_start();

// Database connection
$host = 'localhost';
$db = 'ludeb';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

// Check if email exists
$query = "SELECT id FROM users WHERE email = :email";
$stmt = $pdo->prepare($query);
$stmt->execute(['email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $token = bin2hex(random_bytes(16));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Insert token and expiry
    $query = "INSERT INTO password_resets (email, token, expires) VALUES (:email, :token, :expires)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'email' => $email,
        'token' => $token,
        'expires' => $expires
    ]);

    // Send reset link (example using mail function)
    $resetLink = "http://yourdomain.com/reset_password.php?token=$token";
    mail($email, 'Password Reset Request', "Please click the following link to reset your password: $resetLink");

    echo 'Password reset link has been sent to your email.';
} else {
    echo 'No account found with that email address.';
}
?>
