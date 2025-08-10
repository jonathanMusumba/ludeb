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
    $school_id = $_POST['school'];
    $subject_code = $_POST['subject'];
    $marks = $_POST['marks'];

    foreach ($marks as $student_id => $mark) {
        // Check if the mark has already been entered
        $check_sql = "SELECT id FROM school_results WHERE school_id = ? AND subject_code = ? AND candidate_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("isi", $school_id, $subject_code, $student_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Update the mark if it exists
            $update_sql = "UPDATE school_results SET marks = ? WHERE school_id = ? AND subject_code = ? AND candidate_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("iisi", $mark, $school_id, $subject_code, $student_id);
            $update_stmt->execute();
        } else {
            // Insert the mark if it does not exist
            $insert_sql = "INSERT INTO school_results (school_id, candidate_id, subject_code, marks) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iisi", $school_id, $student_id, $subject_code, $mark);
            $insert_stmt->execute();
        }
    }

    echo "Results saved successfully!";
}

$conn->close();
?>
