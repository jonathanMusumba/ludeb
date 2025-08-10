
<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the CenterNo from the URL
$center_no = isset($_GET['center_no']) ? $_GET['center_no'] : '';

// Fetch school information
$school_stmt = $conn->prepare("SELECT id, School_Name FROM schools WHERE CenterNo = ?");
$school_stmt->bind_param("s", $center_no);
$school_stmt->execute();
$school_result = $school_stmt->get_result();
$school = $school_result->fetch_assoc();

// Check if the school was found
if ($school) {
    $school_id = $school['id'];

    // Fetch candidates for the school
    $candidates_stmt = $conn->prepare("SELECT * FROM candidates WHERE school_id = ?");
    $candidates_stmt->bind_param("i", $school_id);
    $candidates_stmt->execute();
    $candidates_result = $candidates_stmt->get_result();
} else {
    echo "<div class='container mt-5'><div class='alert alert-danger' role='alert'>School not found!</div></div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Candidates</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="home.php"><i class="fas fa-home"></i> Home</a></li>
                <li class="breadcrumb-item"><a href="schools.php">Schools</a></li>
                <li class="breadcrumb-item"><a href="view_school.php?CenterNo=<?php echo urlencode($center_no); ?>">View School</a></li>
                <li class="breadcrumb-item active" aria-current="page">View Candidates</li>
            </ol>
        </nav>
        <h2 class="text-center">Candidates for <?php echo htmlspecialchars($school['School_Name']); ?></h2>
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>Index Number</th>
                    <th>Candidate Name</th>
                    <th>Gender</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($candidate = $candidates_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($candidate['IndexNo']); ?></td>
                        <td><?php echo htmlspecialchars($candidate['Candidate_Name']); ?></td>
                        <td><?php echo htmlspecialchars($candidate['Gender']); ?></td>
                        <td>
                            <!-- Add relevant actions here, e.g., Edit, Delete -->
                            <a href="edit_candidate.php?id=<?php echo htmlspecialchars($candidate['id']); ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="delete_candidate.php?id=<?php echo htmlspecialchars($candidate['id']); ?>" class="btn btn-sm btn-danger">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php
// Close statements and connection
$school_stmt->close();
$candidates_stmt->close();
$conn->close();
?>
