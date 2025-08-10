<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$user = $_POST['username'];
$pass = $_POST['password'];

// Prepare and execute SQL statement
$stmt = $conn->prepare("SELECT id, username, password, role FROM system_users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $user, $user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $row = $result->fetch_assoc();
    
    // Verify password
    if (password_verify($pass, $row['password'])) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        
        // Redirect based on role
        switch ($row['role']) {
            case 'System Admin':
                header("Location: ././system_admin_dashboard.php");
                break;
            case 'Examination Administrator':
                header("Location: ././dmin_dashboard.php");
                break;
            case 'Data Entrant':
                header("Location: ././data_entry_dashboard.php");
                break;
            default:
                header("Location: ././index.php");
                break;
        }
        exit();
    } else {
        echo "Invalid password.";
    }
} else {
    echo "No user found with that username/email.";
}

$stmt->close();
$conn->close();
?>
