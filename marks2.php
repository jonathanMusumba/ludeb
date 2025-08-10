<?php
// Database credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_marks'])) {
    $school_id = $_POST['school_id'];
    $subject_id = $_POST['subject_id'];

    foreach ($_POST['marks'] as $candidate_id => $mark) {
        // Check if mark already exists
        $check_sql = "SELECT * FROM marks WHERE candidate_id = ? AND subject_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $candidate_id, $subject_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Insert new mark
            $insert_sql = "INSERT INTO marks (candidate_id, subject_id, mark) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("iii", $candidate_id, $subject_id, $mark);
            $stmt->execute();
        }
    }
    echo "Marks entered successfully!";
}

// Fetch schools for selection
$schools = $conn->query("SELECT id, school_Name FROM schools");

// Fetch subjects for selection
$subjects = $conn->query("SELECT id, Name FROM subjects");

// Fetch candidates based on selected school
$candidates = [];
if (isset($_GET['school_id'])) {
    $school_id = intval($_GET['school_id']);
    $candidates_result = $conn->query("SELECT id, Candidate_Name, IndexNo FROM candidates WHERE school_id = $school_id");
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[$row['id']] = ['name' => $row['Candidate_Name'], 'index_number' => $row['IndexNo']];
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Enter Marks</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .form-group {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<h1>Enter Marks</h1>

<form method="GET" action="">
    <div class="form-group">
        <label for="school_id">Select School:</label>
        <select id="school_id" name="school_id" onchange="this.form.submit()">
            <option value="">Select a school</option>
            <?php while($row = $schools->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= (isset($_GET['school_id']) && $_GET['school_id'] == $row['id']) ? 'selected' : '' ?>>
                    <?= $row['school_Name'] ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
</form>

<form method="POST" action="">
    <div class="form-group">
        <label for="subject_id">Select Subject:</label>
        <select id="subject_id" name="subject_id" required>
            <option value="">Select a subject</option>
            <?php while($row = $subjects->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>"><?= $row['Name'] ?></option>
            <?php endwhile; ?>
        </select>
    </div>

    <?php if (!empty($candidates)): ?>
        <table>
            <thead>
                <tr>
                    <th>Index Number</th>
                    <th>Candidate Name</th>
                    <th>Mark</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $candidate_id => $candidate_info): ?>
                    <tr>
                        <td><?= htmlspecialchars($candidate_info['index_number']) ?></td>
                        <td><?= htmlspecialchars($candidate_info['name']) ?></td>
                        <td>
                            <input type="number" name="marks[<?= $candidate_id ?>]" min="0" max="100">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <input type="hidden" name="school_id" value="<?= htmlspecialchars($_GET['school_id']) ?>">
        <button type="submit" name="submit_marks">Submit Marks</button>
    <?php else: ?>
        <p>No candidates available for the selected school.</p>
    <?php endif; ?>
</form>

<?php
// Close the database connection
$conn->close();
?>

</body>
</html>
