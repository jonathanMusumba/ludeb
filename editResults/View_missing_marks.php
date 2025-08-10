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


// Define pagination variables
$limit = 20; // Limit of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page number
$offset = ($page - 1) * $limit; // Offset calculation

// Fetch distinct candidates with aggregates > 1 and Division = 'X' along with school names
$query = "
    SELECT DISTINCT r.candidate_id, c.candidate_name, r.aggregates, r.division, s.School_Name
    FROM Results r
    JOIN Candidates c ON r.candidate_id = c.id
    JOIN Schools s ON r.school_id = s.id
    WHERE r.aggregates > 9 AND r.division = 'X'
    LIMIT $limit OFFSET $offset
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates with Aggregates > 1 and Division X</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"> <!-- Bootstrap CSS -->
</head>
<body>
<div class="container">
    <h2 class="my-4">Candidates with Aggregates > 1 and Division X</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Candidate ID</th>
                <th>Candidate Name</th>
                <th>Aggregates</th>
                <th>Division</th>
                <th>School Name</th>
                <th>Edit</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['candidate_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['candidate_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['aggregates']); ?></td>
                    <td><?php echo htmlspecialchars($row['division']); ?></td>
                    <td><?php echo htmlspecialchars($row['School_Name']); ?></td>
                    <td>
                        <a href="edit_candidate.php?candidate_id=<?php echo urlencode($row['candidate_id']); ?>" class="btn btn-primary btn-sm">
                            Edit
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">No candidates found with aggregates > 1 and Division X.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php
    // Get total records
    $countQuery = "
        SELECT COUNT(DISTINCT r.candidate_id) as total
        FROM Results r
        JOIN Candidates c ON r.candidate_id = c.id
        WHERE r.aggregates > 9 AND r.division = 'X'
    ";
    $countResult = $conn->query($countQuery);
    $totalRows = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($totalRows / $limit);
    ?>
    <nav aria-label="Page navigation">
        <ul class="pagination">
            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php if($i == $page) echo 'active'; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>