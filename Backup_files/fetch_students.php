<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $school_id = $_POST['school_id'];
    $subject_code = $_POST['subject_code'];

    $sql = "SELECT id, candidate_index_number, candidate_name FROM students WHERE school_id = ? AND subject_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $school_id, $subject_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<div class="form-group row">';
            echo '<label class="col-sm-4 col-form-label">' . htmlspecialchars($row['candidate_name']) . ' (' . htmlspecialchars($row['candidate_index_number']) . ')</label>';
            echo '<div class="col-sm-8">';
            echo '<input type="number" class="form-control" name="marks[' . $row['id'] . ']" min="0" max="100" required>';
            echo '</div>';
            echo '</div>';
        }
    } else {
        echo '<p>No students found for the selected school and subject.</p>';
    }

    $stmt->close();
}

$conn->close();
?>
