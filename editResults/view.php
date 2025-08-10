<?php
// Include the database connection file
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query to fetch marks submissions with concatenated user names
$query = "
    SELECT 
        s.School_Name,
        sub.Name AS Subject_Name,
        GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ') AS Submitted_By
    FROM 
        Marks m
    JOIN 
        Schools s ON m.school_id = s.id
    JOIN 
        Subjects sub ON m.subject_id = sub.id
    JOIN 
        system_users u ON m.submitted_by = u.id
    GROUP BY 
        s.School_Name, sub.Name
    ORDER BY 
        s.School_Name, sub.Name
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks Submissions by Subject and School</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"> <!-- Bootstrap CSS -->
</head>
<body>
<div class="container">
    <h2 class="my-4">Marks Submissions by Subject and School</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>School Name</th>
                <th>Subject Name</th>
                <th>Submitted By</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['School_Name']); ?></td>
                    <td><?php echo htmlspecialchars($row['Subject_Name']); ?></td>
                    <td><?php echo htmlspecialchars($row['Submitted_By']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="3">No data found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
