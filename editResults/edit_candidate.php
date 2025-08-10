<?php
// Include the database connection file
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$candidate_id = isset($_GET['candidate_id']) ? (int)$_GET['candidate_id'] : 0;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get candidate ID from URL
$candidate_id = isset($_GET['candidate_id']) ? $_GET['candidate_id'] : '';

// Fetch current marks for the candidate
$query = "
    SELECT m.id, m.subject_id, m.mark, s.Name as subject_name
    FROM Marks m
    JOIN Subjects s ON m.subject_id = s.id
    WHERE m.candidate_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $candidate_id);
$stmt->execute();
$result = $stmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Fetch updated marks from POST data
    foreach ($_POST['marks'] as $mark_id => $mark_value) {
        $mark_value = (int)$mark_value;

        // Update marks in the database
        $update_query = "UPDATE Marks SET mark = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('ii', $mark_value, $mark_id);
        $update_stmt->execute();
    }
    echo "<div class='alert alert-success'>Marks updated successfully.</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Candidate Marks</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"> <!-- Bootstrap CSS -->
</head>
<body>
<div class="container">
    <h2 class="my-4">Edit Marks for Candidate ID: <?php echo htmlspecialchars($candidate_id); ?></h2>
    <form method="POST">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Mark</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                        <td>
                            <input type="number" name="marks[<?php echo htmlspecialchars($row['id']); ?>]" value="<?php echo htmlspecialchars($row['mark']); ?>" class="form-control" required>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="2">No marks found for this candidate.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <button type="submit" class="btn btn-primary">Update Marks</button>
        <a href="View_missing_marks.php" class="btn btn-secondary">Back</a>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
