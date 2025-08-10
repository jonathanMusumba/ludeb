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

// Pagination settings
$limit = 20; // Number of candidates per page
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch candidates with one or two missing marks and their subjects
$sql = "
    SELECT c.id AS candidate_id, c.IndexNo, c.Candidate_Name AS candidate_name, s.CenterNo, s.School_Name, 
           GROUP_CONCAT(DISTINCT CASE WHEN m.mark = -1 THEN sub.Name END ORDER BY sub.Name) AS missing_subjects
    FROM Candidates c
    JOIN Schools s ON c.school_id = s.id
    JOIN Subjects sub ON 1=1
    LEFT JOIN Marks m ON c.id = m.candidate_id AND sub.id = m.subject_id
    WHERE s.ResultsStatus <> 'Not Declared'
    AND c.id IN (
        SELECT m1.candidate_id
        FROM Marks m1
        WHERE m1.mark = -1
        GROUP BY m1.candidate_id
        HAVING COUNT(*) <= 2
    )
    GROUP BY c.id
    LIMIT $limit OFFSET $offset;
";
$result = $conn->query($sql);

// Count total candidates with one or two missing marks for pagination
$count_sql = "
    SELECT COUNT(DISTINCT c.id) AS total
    FROM Candidates c
    JOIN Schools s ON c.school_id = s.id
    JOIN Subjects sub ON 1=1
    LEFT JOIN Marks m ON c.id = m.candidate_id AND sub.id = m.subject_id
    WHERE s.ResultsStatus <> 'Not Declared'
    AND c.id IN (
        SELECT m1.candidate_id
        FROM Marks m1
        WHERE m1.mark = -1
        GROUP BY m1.candidate_id
        HAVING COUNT(*) <= 2
    );
";
$count_result = $conn->query($count_sql);
$total_candidates = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_candidates / $limit);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates with Missing Marks</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Candidates with Missing Marks</h1>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Index No</th>
                    <th>Candidate Name</th>
                    <th>School Name</th>
                    <th>Subjects Missing Marks</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['IndexNo']); ?></td>
                            <td><?php echo htmlspecialchars($row['candidate_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['School_Name']); ?></td>
                            <td><?php echo htmlspecialchars($row['missing_subjects']); ?></td>
                            <td>
                                <a href="edit_results.php?candidate_id=<?php echo $row['candidate_id']; ?>" class="btn btn-primary btn-sm">
                                    Edit Marks
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No candidates with missing marks found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <nav aria-label="Page navigation example">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</body>
</html>
