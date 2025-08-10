<?php
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

// Fetch candidates with the same name, gender, and exam year but different IndexNo
$query = "
    SELECT c1.IndexNo AS IndexNo1, c2.IndexNo AS IndexNo2, c1.Candidate_Name, c1.Gender, c1.exam_year,
           (SELECT COUNT(*) FROM marks m WHERE m.candidate_id = c1.id) AS marks_count1,
           (SELECT COUNT(*) FROM marks m WHERE m.candidate_id = c2.id) AS marks_count2,
           c1.id AS candidate_id1, c2.id AS candidate_id2
    FROM Candidates c1
    INNER JOIN Candidates c2 ON c1.Candidate_Name = c2.Candidate_Name 
                              AND c1.Gender = c2.Gender 
                              AND c1.exam_year = c2.exam_year 
                              AND c1.IndexNo <> c2.IndexNo
    WHERE c1.school_id = c2.school_id
    ORDER BY c1.Candidate_Name, c1.exam_year";
    
$result = $conn->query($query);

$candidates = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Similar Candidates</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Review Similar Candidates</h2>
    <?php if (count($candidates) > 0): ?>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>IndexNo 1</th>
                <th>IndexNo 2</th>
                <th>Candidate Name</th>
                <th>Gender</th>
                <th>Exam Year</th>
                <th>Marks Count 1</th>
                <th>Marks Count 2</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($candidates as $candidate): ?>
                <tr>
                    <td><?php echo $candidate['IndexNo1']; ?></td>
                    <td><?php echo $candidate['IndexNo2']; ?></td>
                    <td><?php echo $candidate['Candidate_Name']; ?></td>
                    <td><?php echo $candidate['Gender']; ?></td>
                    <td><?php echo $candidate['exam_year']; ?></td>
                    <td><?php echo $candidate['marks_count1']; ?></td>
                    <td><?php echo $candidate['marks_count2']; ?></td>
                    <td>
                        <a href="merge_candidates.php?cid1=<?php echo $candidate['candidate_id1']; ?>&cid2=<?php echo $candidate['candidate_id2']; ?>" class="btn btn-info">Merge</a>
                        <a href="delete_candidate.php?id=<?php echo $candidate['candidate_id2']; ?>" class="btn btn-danger">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No similar candidates found.</p>
    <?php endif; ?>
</div>
</body>
</html>
