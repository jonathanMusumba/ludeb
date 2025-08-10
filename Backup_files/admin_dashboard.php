<?php
session_start();
require_once 'connections/db.connection.php';
// Check if the user is logged in and is an Examination Administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Examination Administrator') {
    header("Location: login.php");
    exit();
}

// Fetch the examination board name from the database
$query = "SELECT board_name FROM examination_board WHERE id = 1"; // Adjust query as needed
$result = mysqli_query($conn, $query);
$examination_board = mysqli_fetch_assoc($result)['board_name'];

// Fetch the username of the logged-in user
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examination Administrator Dashboard</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="path/to/your/custom.css">
</head>
<body>
    <!-- Top Bar -->
    <div class="topbar bg-light p-3 d-flex justify-content-between align-items-center">
        <button class="btn btn-outline-secondary collapse-btn" type="button" data-toggle="collapse" data-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
            <i class="fas fa-bars"></i>
        </button>
        <div>
            <span class="mr-3"><?php echo htmlspecialchars($examination_board); ?></span>
            <span class="mr-3">Welcome: <?php echo htmlspecialchars($username); ?></span>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>

    <div class="d-flex">
        <!-- Side Navigation -->
        <nav class="bg-dark text-white p-3" id="sidebar">
            <ul class="nav flex-column">
                <li class="nav-item"><a href="dashboard.php" class="nav-link text-white">Dashboard</a></li>
                <li class="nav-item">
                    <a href="#schoolsSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle nav-link text-white">Schools</a>
                    <ul class="collapse list-unstyled" id="schoolsSubmenu">
                        <li class="nav-item"><a href="view_schools.php" class="nav-link text-white">View Schools</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="#subcountiesSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle nav-link text-white">Subcounties</a>
                    <ul class="collapse list-unstyled" id="subcountiesSubmenu">
                        <li class="nav-item"><a href="view_subcounties.php" class="nav-link text-white">View Subcounties</a></li>
                        <li class="nav-item"><a href="schools_subcounties.php" class="nav-link text-white">Schools under Subcounties</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="#resultsSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle nav-link text-white">Results</a>
                    <ul class="collapse list-unstyled" id="resultsSubmenu">
                        <li class="nav-item"><a href="district_results.php" class="nav-link text-white">District Results</a></li>
                        <li class="nav-item"><a href="school_results.php" class="nav-link text-white">School Results</a></li>
                    </ul>
                </li>
            </ul>
        </nav>

        <!-- Main Content Area -->
        <div class="container-fluid p-4">
            <h1>Examination Administrator Dashboard</h1>

            <!-- Capture 22 content -->
            <div class="card mb-4">
                <div class="card-header">
                    Examination Results Summary
                    <a href="download_district_results.php" class="btn btn-primary float-right">Download District Results</a>
                </div>
                <div class="card-body">
                    <img src="path/to/Capture22.JPG" alt="Examination Results Summary" class="img-fluid">
                </div>
            </div>

            <!-- Capture 23 content -->
            <div class="card mb-4">
                <div class="card-header">Examination Results by Division and Gender</div>
                <div class="card-body">
                    <img src="path/to/Capture23.JPG" alt="Examination Results by Division and Gender" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
