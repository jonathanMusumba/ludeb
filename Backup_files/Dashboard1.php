<?php
session_start();
require_once 'connections/db.connection.php'; // Assume you have a file to handle DB connections

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// User data
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Fetch board name and exam year from the database
$board_query = $conn->query("SELECT board_name FROM examination_board WHERE id = 1");
$board_row = $board_query->fetch_assoc();
$board_name = $board_row['board_name'];

$exam_query = $conn->query("SELECT exam_year FROM exam_years WHERE id = 1");
$exam_row = $exam_query->fetch_assoc();
$exam_year = $exam_row['exam_year'];
// Query to get entries based on submitted_at
// Query to get entries based on submitted_at
$sql = "SELECT DATE(submitted_at) AS date, COUNT(*) AS count
        FROM marks
        GROUP BY DATE(submitted_at)
        ORDER BY DATE(submitted_at)";

// Execute the query
$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    // Format date as timestamp and ensure count is an integer
    $data[] = [strtotime($row['date']) * 1000, (int)$row['count']];
}

// Close connection
$conn->close();

// Output data in JSON format
echo json_encode($data);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Administrator Dashboard</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/export-data.js"></script>
    <style>
         #progressiveGraph {
            height: 400px;
            min-width: 320px;
            max-width: 800px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
<div class="topbar d-flex justify-content-between align-items-center">
        <button class="btn btn-outline-light d-lg-none" type="button" data-toggle="collapse" data-target="#sidebarCollapse">
            <i class="fas fa-bars"></i>
        </button>
        <div>
            <span class="mr-3">Board Name: <?php echo htmlspecialchars($board_name); ?></span>
            <span class="mr-3">Exam Year: <?php echo htmlspecialchars($exam_year); ?></span>
            <span class="mr-3">User: <?php echo htmlspecialchars($username); ?></span>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>
<div class="container-fluid">
    <div class="row">
        <nav id="sidebarCollapse" class="sidebar bg-light collapse d-lg-block col-lg-2">
            <div class="sidebar-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" id="dashboardLink">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#schoolsMenu" aria-expanded="false">
                            <i class="fas fa-school"></i> Schools
                        </a>
                        <div id="schoolsMenu" class="collapse">
                            <ul class="nav flex-column">
                                <li class="nav-item"><a class="nav-link" href="add_school.php">Add School</a></li>
                                <li class="nav-item"><a class="nav-link" href="edit_schools.php">Manage Schools</a></li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#usersMenu" aria-expanded="false">
                            <i class="fas fa-users"></i> Users
                        </a>
                        <div id="usersMenu" class="collapse">
                            <ul class="nav flex-column">
                                <li class="nav-item"><a class="nav-link" href="systemAdmin/add_user.php">Add User</a></li>
                                <li class="nav-item"><a class="nav-link" href="systemAdmin/manage_users.php">Manage Users</a></li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#studentsMenu" aria-expanded="false">
                            <i class="fas fa-user-graduate"></i> Students
                        </a>
                        <div id="studentsMenu" class="collapse">
                            <ul class="nav flex-column">
                                <li class="nav-item"><a class="nav-link" href="#">Add Student</a></li>
                                <li class="nav-item"><a class="nav-link" href="#">Manage Students</a></li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#resultsMenu" aria-expanded="false">
                            <i class="fas fa-file-alt"></i> Results
                        </a>
                        <div id="resultsMenu" class="collapse">
                            <ul class="nav flex-column">
                                <li class="nav-item"><a class="nav-link" href="marks.php">Add Results</a></li>
                                <li class="nav-item"><a class="nav-link" href="results_sheet.php">Manage Results</a></li>
                                <li class="nav-item"><a class="nav-link" href="candidates_list.php">Edit Results</a></li>
                                <li class="nav-item"><a class="nav-link" href="school_results.php">View Results</a></li>
                                <li class="nav-item"><a class="nav-link" href="results_sheet.php">Print Results</a></li>
                            </ul>
                        </div>
                        
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="marks.php">
                            <i class="fas fa-file-import"></i> Results Capture
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="marks.php">
                            <i class="fas fa-file-import"></i> Downlaod Results
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                    </ol>
                </nav>
            </div>

            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="card text-white bg-primary mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Total Number of Schools</h5>
                            <p class="card-text" id="totalSchools">Loading...</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Schools Results Declared</h5>
                            <p class="card-text" id="schoolsResultsDeclared">Loading...</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Total Candidates Registered</h5>
                            <p class="card-text" id="totalCandidates">Loading...</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Candidates Results Declared</h5>
                            <p class="card-text" id="candidatesResultsDeclared">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
        <div class="col-md-12">
        <div id="progressiveGraph"></div>
        </div>
    </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <h2>Summary Table</h2>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Division</th>
                                <th>Male</th>
                                <th>Female</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="summaryTable">
                            <!-- Dynamic data will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Bootstrap JS and dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
    // Fetch data dynamically
    fetch('dashboard_data.php') // Assume you have this endpoint to provide data
        .then(response => response.json())
        .then(data => {
            document.getElementById('totalSchools').textContent = data.totalSchools;
            document.getElementById('schoolsResultsDeclared').textContent = data.schoolsResultsDeclared;
            document.getElementById('totalCandidates').textContent = data.totalCandidates;
            document.getElementById('candidatesResultsDeclared').textContent = data.candidatesResultsDeclared;

            let tableBody = '';
            data.summaryTable.forEach(row => {
                tableBody += `
                    <tr>
                        <td>${row.division}</td>
                        <td>${row.male}</td>
                        <td>${row.female}</td>
                        <td>${row.total}</td>
                    </tr>
                `;
            });
            document.getElementById('summaryTable').innerHTML = tableBody;
        });

        document.addEventListener('DOMContentLoaded', function () {
            // Fetch data from the PHP script
            fetch('dashboard1.php')
                .then(response => response.json())
                .then(data => {
                    Highcharts.chart('progressiveGraph', {
                        chart: {
                            type: 'line'
                        },
                        title: {
                            text: 'Number of Entries Over Time'
                        },
                        xAxis: {
                            type: 'datetime',
                            title: {
                                text: 'Date'
                            }
                        },
                        yAxis: {
                            title: {
                                text: 'Number of Entries'
                            },
                            min: 0
                        },
                        series: [{
                            name: 'Entries',
                            data: data,
                            pointStart: data[0][0], // Start from the first data point
                            pointInterval: 24 * 3600 * 1000 // One day
                        }]
                    });
                })
                .catch(error => console.error('Error fetching data:', error));
        });
    // Sidebar toggle
    document.addEventListener('DOMContentLoaded', function() {
        var sidebarCollapseBtn = document.querySelector('.collapse-btn');
        var sidebar = document.getElementById('sidebarCollapse');

        sidebarCollapseBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });
    });
</script>
</body>
</html>
