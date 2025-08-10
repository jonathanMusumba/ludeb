<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$candidate_id = isset($_GET['candidate_id']) ? intval($_GET['candidate_id']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update marks
    foreach ($_POST['marks'] as $subject_id => $mark) {
        $subject_id = intval($subject_id);
        $mark = intval($mark);
        $update_query = "UPDATE Marks SET mark = $mark WHERE candidate_id = $candidate_id AND subject_id = $subject_id";
        $conn->query($update_query);
    }
    echo "<div class='alert alert-success'>Marks updated successfully!</div>";
}

// Fetch candidate details
$candidate_query = "SELECT * FROM Candidates WHERE id = $candidate_id";
$candidate_result = $conn->query($candidate_query);
$candidate = $candidate_result->fetch_assoc();

// Fetch subjects and marks
$marks_query = "
    SELECT s.id AS subject_id, s.Name AS subject_name, m.mark
    FROM Subjects s
    LEFT JOIN Marks m ON s.id = m.subject_id AND m.candidate_id = $candidate_id
    ORDER BY s.Name ASC
";
$marks_result = $conn->query($marks_query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Candidate's Results</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Edit Results for <?php echo htmlspecialchars($candidate['Candidate_Name']); ?></h1>
        <form action="" method="POST">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Subject Name</th>
                        <th>Mark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $marks_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                            <td>
                                <input type="number" name="marks[<?php echo $row['subject_id']; ?>]" 
                                       value="<?php echo htmlspecialchars($row['mark']); ?>" 
                                       class="form-control" min="-1" max="100">
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
        <a href="candidates_list.php" class="btn btn-secondary mt-3">Back to Candidates List</a>
    </div>
</body>
</html>
