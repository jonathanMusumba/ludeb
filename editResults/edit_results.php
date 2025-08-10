<?php
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

// Fetch candidate details
$candidate_query = "
    SELECT c.IndexNo, c.Candidate_Name, s.School_Name
    FROM candidates c
    JOIN schools s ON c.school_id = s.id
    WHERE c.id = ?
";
$stmt = $conn->prepare($candidate_query);
$stmt->bind_param("i", $candidate_id);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();

// Fetch subjects and their current marks
$marks_query = "
    SELECT r.subject_id, sub.Name, r.mark
    FROM results r
    JOIN subjects sub ON r.subject_id = sub.id
    WHERE r.candidate_id = ?
";
$stmt = $conn->prepare($marks_query);
$stmt->bind_param("i", $candidate_id);
$stmt->execute();
$marks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch all subjects available in the system
$subjects_query = "SELECT id, Name FROM subjects";
$subjects_result = $conn->query($subjects_query);
$subjects = $subjects_result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['marks'] as $subject_id => $mark) {
        $mark = (int)$mark;
        $update_query = "
            INSERT INTO results (candidate_id, subject_id, mark, updated_at, updated_by)
            VALUES (?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE mark = VALUES(mark), updated_at = NOW(), updated_by = VALUES(updated_by)
        ";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("iiis", $candidate_id, $subject_id, $mark, $user_id);
        $stmt->execute();
    }
    header("Location: missing_marks.php"); // Redirect to another page after update
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Results</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">Edit Results for <?php echo htmlspecialchars($candidate['IndexNo']); ?> - <?php echo htmlspecialchars($candidate['Candidate_Name']); ?></h2>
    <form method="post">
        <div class="form-group">
            <label for="school">School Name</label>
            <input type="text" class="form-control" id="school" value="<?php echo htmlspecialchars($candidate['School_Name']); ?>" disabled>
        </div>
        <?php foreach ($subjects as $subject): ?>
            <div class="form-group">
                <label for="subject-<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['Name']); ?></label>
                <input type="number" class="form-control" id="subject-<?php echo $subject['id']; ?>" name="marks[<?php echo $subject['id']; ?>]" value="<?php
                    $mark = array_filter($marks, fn($m) => $m['subject_id'] == $subject['id']);
                    echo !empty($mark) ? htmlspecialchars($mark[0]['mark']) : ''; ?>">
            </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
</div>
</body>
</html>

<?php
$conn->close();
?>
