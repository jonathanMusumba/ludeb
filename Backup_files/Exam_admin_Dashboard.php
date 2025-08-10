<?php
session_start();
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

$exam_body_query = "SELECT board_name FROM Examination_board WHERE id = 1"; // Assuming id = 1 for demonstration
$exam_body_result = $conn->query($exam_body_query);

if ($exam_body_result->num_rows > 0) {
    $exam_body = $exam_body_result->fetch_assoc()['board_name'];
}

$current_year = date("Y");
$exam_year_query = "SELECT id, Exam_year FROM Exam_years WHERE Exam_year = $current_year";
$exam_year_result = $conn->query($exam_year_query);

if ($exam_year_result->num_rows > 0) {
    $exam_year = $exam_year_result->fetch_assoc()['Exam_year'];
}

// Fetch active schools count
$active_schools_query = "SELECT COUNT(*) AS active_schools_count FROM Schools WHERE Status = 'active'";
$active_schools_result = mysqli_query($conn, $active_schools_query);
$active_schools_row = mysqli_fetch_assoc($active_schools_result);
$active_schools_count = $active_schools_row['active_schools_count'];

$with_results_query = "SELECT COUNT(id) AS with_results FROM Schools WHERE resultsStatus = 'Declared' AND Status = 'active'";
$with_results_result = $conn->query($with_results_query);
$with_results = $with_results_result->fetch_assoc()['with_results'];

// Fetch schools with results not declared or partially declared
$without_results_query = "SELECT COUNT(*) AS without_results FROM Schools WHERE resultsStatus IN ('Not Declared', 'Partially Declared') AND Status = 'active'";
$without_results_result = $conn->query($without_results_query);
$without_results = $without_results_result->fetch_assoc()['without_results'];

$user_id = $_SESSION['user_id']; // Assuming the user ID is stored in the session
$user_query = "SELECT username FROM system_users WHERE id = $user_id";
$user_result = $conn->query($user_query);

if ($user_result->num_rows > 0) {
    $username = $user_result->fetch_assoc()['username'];
}

// Function to get grade score from grading table
function getGradeScore($mark, $conn) {
    $query = "
        SELECT score
        FROM Grading
        WHERE ? BETWEEN range_from AND range_to
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $mark);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row['score'] ?? 0;
}

// Function to calculate total aggregates and determine division
function calculateAggregatesAndDivision($candidateId, $conn) {
    // Get marks for each subject
    $query = "
        SELECT s.Name AS subject_name, m.mark
        FROM marks m
        JOIN subjects s ON m.subject_id = s.id
        WHERE m.candidate_id = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $candidateId);
    $stmt->execute();
    $result = $stmt->get_result();

    $totalScore = 0;
    $englishMark = 0;
    $mathematicsMark = 0;
    $subjectCount = 0;

    while ($row = $result->fetch_assoc()) {
        $mark = $row['mark'];
        $subjectName = $row['subject_name'];
        
        // Get grade score from grading table
        $score = getGradeScore($mark, $conn);

        $totalScore += $score;
        $subjectCount++;

        if ($subjectName === 'English') {
            $englishMark = $mark;
        } elseif ($subjectName === 'Mathematics') {
            $mathematicsMark = $mark;
        }
    }

    // Determine division
    $division = 'U';
    if ($subjectCount < 4) {
        $division = 'Absentee';
    } elseif ($totalScore <= 12 && $englishMark <= 6 && $mathematicsMark <= 8) {
        $division = 'Division 1';
    } elseif ($totalScore <= 24 && $englishMark <= 6 && $mathematicsMark <= 8) {
        $division = 'Division 2';
    } elseif ($totalScore <= 28 && $englishMark <= 8) {
        $division = 'Division 3';
    } elseif ($totalScore <= 32) {
        $division = 'Division 4';
    }

    return [
        'total_aggregates' => $totalScore,
        'division' => $division
    ];
}

// Fetch candidate aggregates and summary
$fetch_candidates_query = "
    SELECT c.id AS candidate_id
    FROM candidates c
";

$candidates_result = $conn->query($fetch_candidates_query);

// Initialize arrays
$divisions = [
    'Division 1' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Division 2' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Division 3' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Division 4' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Failed' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Absentee' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0]
];

// Fetch candidate aggregates and summary
$fetch_candidates_query = "
    SELECT c.id AS candidate_id
    FROM candidates c
";

$candidates_result = $conn->query($fetch_candidates_query);

while ($candidate = $candidates_result->fetch_assoc()) {
    $candidateId = $candidate['candidate_id'];
    $aggregatesAndDivision = calculateAggregatesAndDivision($candidateId, $conn);

    $division = $aggregatesAndDivision['division'];
    if (isset($divisions[$division])) {
        $divisions[$division]['total_candidates']++;
        // Count male and female candidates
        $gender_query = "SELECT Gender FROM candidates WHERE id = $candidateId";
        $gender_result = $conn->query($gender_query);
        if ($gender_result->num_rows > 0) {
            $gender = $gender_result->fetch_assoc()['Gender'];
            if ($gender === 'M') {
                $divisions[$division]['male_candidates']++;
            } elseif ($gender === 'F') {
                $divisions[$division]['female_candidates']++;
            }
        }
    }
}
$chart_data = [];
foreach ($divisions as $division => $data) {
    $chart_data['categories'][] = $division;
    $chart_data['male'][] = $data['male_candidates'];
    $chart_data['female'][] = $data['female_candidates'];
}
// Prepare data for the pie chart
$pie_chart_data = [];
foreach ($divisions as $division => $data) {
    $pie_chart_data[] = [
        'name' => $division,
        'y' => $data['total_candidates']
    ];
}
// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - UNEB Registration</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .topbar {
            background-color: #a1cf42;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar {
            background-color: #2b3e50;
            min-height: 100vh;
            color: white;
            padding-top: 20px;
        }

        .sidebar h2 {
            font-size: 20px;
            text-align: center;
        }

        .sidebar ul {
            list-style-type: none;
            padding-left: 0;
        }

        .sidebar ul li {
            padding: 10px 20px;
        }

        .sidebar ul li a {
            color: white;
            text-decoration: none;
        }

        .sidebar ul li a:hover {
            text-decoration: underline;
        }

        .container-fluid {
            padding: 20px;
        }

        .card {
            margin-bottom: 20px;
        }

        .card-header {
            font-weight: bold;
            background-color: #f8f9fa;
        }

        .btn-primary {
            background-color: #28a745;
            border-color: #28a745;
        }

        .btn-primary:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .table-responsive {
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <!-- Top Bar -->
    <div class="topbar">
        <button class="btn btn-outline-secondary collapse-btn" type="button">
            <i class="fas fa-bars"></i>
        </button>
        <div>
            <span class="mr-3"><?php echo $exam_body; ?></span>
            <span class="mr-3"><?php echo $username; ?></span>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>

    <div class="d-flex">
        <!-- Sidebar -->
        <nav class="sidebar">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="#" id="dashboard-link" class="nav-link">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="#schoolsSubmenu" data-toggle="collapse" aria-expanded="false"
                        class="dropdown-toggle nav-link">Schools</a>
                    <ul class="collapse list-unstyled" id="schoolsSubmenu">
                        <li class="nav-item">
                            <a href="view_schools.php" class="nav-link">View Schools</a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="#resultsSubmenu" data-toggle="collapse" aria-expanded="false"
                        class="dropdown-toggle nav-link">Results</a>
                    <ul class="collapse list-unstyled" id="resultsSubmenu">
                        <li class="nav-item">
                            <a href="district_results.php" class="nav-link">District Results</a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="invoices.php" class="nav-link">Invoices</a>
                </li>
                <li class="nav-item">
                    <a href="registration_reports.php" class="nav-link">Registration Reports</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid" id="main-content">
            <!-- This section will load by default -->
            <h1>Dashboard</h1>

            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                        <h4><?php echo $active_schools_count; ?></h4>
                        <p>Total Exam Centers</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                        <h4><?php echo $with_results; ?></h4>
                        <p>With <?php echo $exam_year; ?> Results</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                        <h4><?php echo $without_results; ?></h4>
                        <p>Missing <?php echo $exam_year; ?> Results</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">Results Summary</div>
                        <div class="card-body">
                            <div id="pie-chart"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header"></div>
                        <div class="card-body">
                            <div id="bar-chart"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="table-responsive">
                    <h2>Division Summary</h2>
                    <h3>Division Counts</h3>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Division</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($divisions as $division => $data): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($division); ?></td>
                        <td><?php echo $data['total_candidates']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Division</th>
                    <th>Male</th>
                    <th>Female</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($divisions as $division => $data): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($division); ?></td>
                        <td><?php echo $data['male_candidates']; ?></td>
                        <td><?php echo $data['female_candidates']; ?></td>
                        <td><?php echo $data['total_candidates']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>

    <script>
        // Load summaries on page load
        $(document).ready(function () {
            $('#dashboard-link').click(function (e) {
                e.preventDefault();
                $('#main-content').load('dashboard.php');
            });

            // Load the dashboard content by default
            $('#main-content').load('dashboard.php');

            // Example of loading other pages
            $('.nav-link').click(function (e) {
                e.preventDefault();
                var targetUrl = $(this).attr('href');
                $('#main-content').load(targetUrl);
            });

            // Pie chart example
            const pieChartData = <?php echo json_encode($pie_chart_data); ?>;
    const currentYear = <?php echo json_encode($current_year); ?>;

    // Create the Highcharts pie chart
    Highcharts.chart('pie-chart', {
        chart: {
            type: 'pie'
        },
        title: {
            text: `Summary of Exam Year ${currentYear}`
        },
        series: [{
            name: 'Results',
            data: pieChartData,
            // Format percentages with one decimal place
            dataLabels: {
                format: '{point.name}: {point.percentage:.1f}%'
            }
        }],
        plotOptions: {
            pie: {
                allowPointSelect: true,
                cursor: 'pointer',
                showInLegend: true
            }
        }
    });
            // Bar chart example
            const chartData = <?php echo json_encode($chart_data); ?>;

    // Create the Highcharts chart
    Highcharts.chart('bar-chart', {
        chart: {
            type: 'bar'
        },
        title: {
            text: 'Results by Division and Gender'
        },
        xAxis: {
            categories: chartData.categories
        },
        yAxis: {
            title: {
                text: 'Number of Candidates'
            }
        },
        series: [{
            name: 'Male',
            data: chartData.male
        }, {
            name: 'Female',
            data: chartData.female
        }]
    });
        });
    </script>
</body>

</html>
