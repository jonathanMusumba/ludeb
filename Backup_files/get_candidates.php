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

// Retrieve parameters from the request
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;

// Prepare SQL to get all candidates for the school
$sql_all_candidates = "SELECT id, Candidate_Name, IndexNo FROM candidates WHERE school_id = ?";
$stmt_all = $conn->prepare($sql_all_candidates);
$stmt_all->bind_param("i", $school_id);
$stmt_all->execute();
$result_all = $stmt_all->get_result();

// Check if subject has been captured
$subject_captured = false;
if ($subject_id) {
    // Check if the subject has been captured (e.g., check a 'captured_subjects' table or similar)
$sql_check_captured = "SELECT 1 FROM captured_subjects WHERE subject_id = ? AND school_id = ?";
$stmt_check = $conn->prepare($sql_check_captured);
$stmt_check->bind_param("ii", $subject_id, $school_id); // Bind both parameters
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$subject_captured = $result_check->num_rows > 0;
}

// Prepare SQL to get candidates missing marks for the subject
$candidate_ids_with_marks = [];
if ($subject_captured) {
    $sql_missing_marks = "SELECT candidate_id FROM marks WHERE subject_id = ?";
    $stmt_missing = $conn->prepare($sql_missing_marks);
    $stmt_missing->bind_param("i", $subject_id);
    $stmt_missing->execute();
    $result_missing = $stmt_missing->get_result();

    while ($row = $result_missing->fetch_assoc()) {
        $candidate_ids_with_marks[] = $row['candidate_id'];
    }
}

// Generate the HTML for the candidates' marks input fields
if ($result_all->num_rows > 0) {
    while ($row = $result_all->fetch_assoc()) {
        $candidate_id = $row['id'];
        $candidate_name = htmlspecialchars($row['Candidate_Name']);
        $index_no = htmlspecialchars($row['IndexNo']);
        $has_marks = in_array($candidate_id, $candidate_ids_with_marks);

        if (!$subject_captured || !$has_marks) {
            echo "
                <div class='form-group'>
                    <label for='marks_$candidate_id'>$candidate_name (Index No: $index_no):</label>
                    <input type='number' name='marks[$candidate_id]' id='marks_$candidate_id' class='form-control' min='0' max='100'>
                </div>
            ";
        }
    }
} else {
    echo "<p>No candidates found for the selected school.</p>";
}

$conn->close();
?>
