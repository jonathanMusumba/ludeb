<?php
// Database connection
$host = 'localhost';
$db = 'ludeb';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Function to hash passwords
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Insert System Admin (Active)
$admin_username = 'admin';
$admin_email = 'admin@admin.com';
$admin_password = hash_password('Admin@123');
$admin_role = 'System Admin';
$admin_status = 'active';

$sql = "INSERT INTO users (username, email, password, role, status) 
        VALUES (:username, :email, :password, :role, :status)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':username' => $admin_username,
    ':email' => $admin_email,
    ':password' => $admin_password,
    ':role' => $admin_role,
    ':status' => $admin_status
]);

// Insert Other Users (Pending Approval)
$users = [
    ['username' => 'receptionist', 'email' => 'receptionist@example.com', 'password' => 'Receptionist@123', 'role' => 'Receptionist'],
    ['username' => 'warden', 'email' => 'warden@example.com', 'password' => 'Warden@123', 'role' => 'Warden'],
    ['username' => 'analyst', 'email' => 'analyst@example.com', 'password' => 'Analyst@123', 'role' => 'Analyst'],
    ['username' => 'staff', 'email' => 'staff@example.com', 'password' => 'Staff@123', 'role' => 'Staff']
];

foreach ($users as $user) {
    $user['password'] = hash_password($user['password']);
    $user['status'] = 'pending'; // All users except admin start with 'pending' status

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':username' => $user['username'],
        ':email' => $user['email'],
        ':password' => $user['password'],
        ':role' => $user['role'],
        ':status' => $user['status']
    ]);
}

echo "Demo users inserted successfully!";
?>
