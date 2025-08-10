<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

// Database connection
$host = 'localhost';
$db = 'ludeb';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['user_id']);
    $status = 'active';

    // Update user status
    $query = "UPDATE users SET status = :status WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'status' => $status,
        'id' => $userId
    ]);

    // Redirect to dashboard with success message
    header('Location: dashboard.php?action=approved');
    exit;
}
?>
