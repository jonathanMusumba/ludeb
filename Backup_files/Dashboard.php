<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch exam body and year
$exam_body_query = "SELECT board_name FROM settings WHERE id = 1";
$exam_body_result = $conn->query($exam_body_query);
$exam_body = ($exam_body_result->num_rows > 0) ? $exam_body_result->fetch_assoc()['board_name'] : 'N/A';

$current_year = date("Y");
$exam_year_query = "SELECT id, Exam_year FROM Exam_years WHERE Exam_year = ?";
$stmt = $conn->prepare($exam_year_query);
$stmt->bind_param('i', $current_year);
$stmt->execute();
$exam_year_result = $stmt->get_result();
$exam_year = ($exam_year_result->num_rows > 0) ? $exam_year_result->fetch_assoc()['Exam_year'] : 'N/A';

// Fetch school counts
$active_schools_query = "SELECT COUNT(*) AS active_schools_count FROM Schools WHERE Status = 'active'";
$active_schools_result = $conn->query($active_schools_query);
$active_schools_count = $active_schools_result->fetch_assoc()['active_schools_count'];

$with_results_query = "SELECT COUNT(id) AS with_results FROM Schools WHERE results_status = 'Declared' AND Status = 'active'";
$with_results_result = $conn->query($with_results_query);
$with_results = $with_results_result->fetch_assoc()['with_results'];

$without_results_query = "SELECT COUNT(*) AS without_results FROM Schools WHERE results_status IN ('Not Declared', 'Partially Declared') AND Status = 'active'";
$without_results_result = $conn->query($without_results_query);
$without_results = $without_results_result->fetch_assoc()['without_results'];

// Fetch username
$user_id = $_SESSION['user_id'];
$user_query = "SELECT username FROM system_users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$username = ($user_result->num_rows > 0) ? $user_result->fetch_assoc()['username'] : 'N/A';

// Function to get grade score
function getGradeScore($mark, $conn) {
    $query = "SELECT score FROM Grading WHERE ? BETWEEN range_from AND range_to";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $mark);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['score'] ?? 0;
}

// Function to calculate aggregates and division
function calculateAggregatesAndDivision($candidateId, $conn) {
    $query = "SELECT s.Name AS subject_name, m.mark FROM marks m JOIN subjects s ON m.subject_id = s.id WHERE m.candidate_id = ?";
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
        $score = getGradeScore($mark, $conn);
        $totalScore += $score;
        $subjectCount++;

        if ($subjectName === 'English') {
            $englishMark = $mark;
        } elseif ($subjectName === 'Mathematics') {
            $mathematicsMark = $mark;
        }
    }

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

    return ['total_aggregates' => $totalScore, 'division' => $division];
}

// Fetch candidates and calculate aggregates
$fetch_candidates_query = "SELECT id AS candidate_id FROM candidates";
$candidates_result = $conn->query($fetch_candidates_query);

$divisions = [
    'Division 1' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Division 2' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Division 3' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Division 4' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Failed' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0],
    'Absentee' => ['total_candidates' => 0, 'male_candidates' => 0, 'female_candidates' => 0]
];

while ($candidate = $candidates_result->fetch_assoc()) {
    $candidateId = $candidate['candidate_id'];
    $aggregatesAndDivision = calculateAggregatesAndDivision($candidateId, $conn);

    $division = $aggregatesAndDivision['division'];
    if (isset($divisions[$division])) {
        $divisions[$division]['total_candidates']++;
        $gender_query = "SELECT sex FROM candidates WHERE id = ?";
        $stmt = $conn->prepare($gender_query);
        $stmt->bind_param('i', $candidateId);
        $stmt->execute();
        $gender_result = $stmt->get_result();
        if ($gender_result->num_rows > 0) {
            $gender = $gender_result->fetch_assoc()['sex'];
            if ($gender === 'M') {
                $divisions[$division]['male_candidates']++;
            } elseif ($gender === 'F') {
                $divisions[$division]['female_candidates']++;
            }
        }
    }
}

// Prepare data for charts
$chart_data = [];
foreach ($divisions as $division => $data) {
    $chart_data['categories'][] = $division;
    $chart_data['male'][] = $data['male_candidates'];
    $chart_data['female'][] = $data['female_candidates'];
}

$pie_chart_data = [];
foreach ($divisions as $division => $data) {
    $pie_chart_data[] = ['name' => $division, 'y' => $data['total_candidates']];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <style>
        #pie-chart, #bar-chart {
            height: 400px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
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
                        <p>With <?php echo htmlspecialchars($exam_year); ?> Results</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h4><?php echo $without_results; ?></h4>
                        <p>Missing <?php echo htmlspecialchars($exam_year); ?> Results</p>
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
                    <div class="card-header">Division Breakdown</div>
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

    <script>
        $(document).ready(function() {
            Highcharts.chart('pie-chart', {
                chart: { type: 'pie' },
                title: { text: 'Summary of Exam Year <?php echo htmlspecialchars($exam_year); ?>' },
                series: [{
                    name: 'Results',
                    data: <?php echo json_encode($pie_chart_data); ?>,
                    dataLabels: { format: '{point.name}: {point.percentage:.1f}%' }
                }],
                plotOptions: {
                    pie: {
                        allowPointSelect: true,
                        cursor: 'pointer',
                        showInLegend: true
                    }
                }
            });

            Highcharts.chart('bar-chart', {
                chart: { type: 'bar' },
                title: { text: 'Results by Division and Gender' },
                xAxis: { categories: <?php echo json_encode($chart_data['categories']); ?> },
                series: [{
                    name: 'Male',
                    data: <?php echo json_encode($chart_data['male']); ?>
                }, {
                    name: 'Female',
                    data: <?php echo json_encode($chart_data['female']); ?>
                }]
            });
        });
    </script>
</body>
</html>
