<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define the number of results per page
$results_per_page = 50; 

// Get the sorting parameters from URL or set defaults
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'aggregates';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Validate sorting parameters
$valid_columns = ['IndexNo', 'Candidate_Name', 'School_Name', 'aggregates', 'division'];
if (!in_array($sort_column, $valid_columns)) {
    $sort_column = 'aggregates';
}
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

// Find out the number of results stored in the database
$count_query = "
    SELECT COUNT(DISTINCT c.id) AS total
    FROM candidates c
    JOIN results r ON c.id = r.candidate_id
    WHERE r.division = '1'
";
$count_result = $conn->query($count_query);
if (!$count_result) {
    die("Query failed: " . $conn->error);
}
$row = $count_result->fetch_assoc();
$total_records = $row['total'];
$total_pages = ceil($total_records / $results_per_page);

// Determine which page number visitor is currently on
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $total_pages) $page = $total_pages;
$starting_limit = ($page - 1) * $results_per_page;

// Fetch the records for Division 1 with sorting
$query = "
    SELECT c.IndexNo, c.Candidate_Name, s.School_Name, MAX(r.aggregates) AS aggregates, 
           MAX(r.division) AS division,
           MAX(CASE WHEN r.subject_id = 1 THEN r.score ELSE 0 END) AS score1,
           MAX(CASE WHEN r.subject_id = 2 THEN r.score ELSE 0 END) AS score2,
           MAX(CASE WHEN r.subject_id = 3 THEN r.score ELSE 0 END) AS score3,
           MAX(CASE WHEN r.subject_id = 4 THEN r.score ELSE 0 END) AS score4
    FROM candidates c
    JOIN schools s ON c.school_id = s.id
    JOIN results r ON c.id = r.candidate_id
    WHERE r.division = '1'
    GROUP BY c.id
    ORDER BY aggregates ASC, score1 ASC, score2 ASC, score3 ASC, score4 ASC
    LIMIT ?, ?
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ii", $starting_limit, $results_per_page);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Division 1 Results</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">CANDIDATES WITH FIRST GRADES</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th><a href="?page=<?php echo $page; ?>&sort=IndexNo&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>">IndexNo</a></th>
                <th><a href="?page=<?php echo $page; ?>&sort=Candidate_Name&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>">Candidate Name</a></th>
                <th><a href="?page=<?php echo $page; ?>&sort=School_Name&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>">School Name</a></th>
                <th><a href="?page=<?php echo $page; ?>&sort=aggregates&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>">Aggregates</a></th>
                <th><a href="?page=<?php echo $page; ?>&sort=division&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>">Division</a></th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['IndexNo']); ?></td>
                <td><?php echo htmlspecialchars($row['Candidate_Name']); ?></td>
                <td><?php echo htmlspecialchars($row['School_Name']); ?></td>
                <td><?php echo htmlspecialchars($row['aggregates']); ?></td>
                <td><?php echo htmlspecialchars($row['division']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($total_pages > 1): ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&sort=<?php echo $sort_column; ?>&order=<?php echo $sort_order; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            <?php endif; ?>
        </ul>
    </nav>
</div>
</body>
</html>

<?php
$conn->close();
?>
