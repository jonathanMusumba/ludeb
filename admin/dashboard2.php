<?php
session_start();
require_once '../db_connect.php';

// Role-based access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header("Location: ../../Common/login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');

// Log dashboard access
$conn->query("CALL log_action('Dashboard Access', {$_SESSION['user_id']}, 'System Admin accessed dashboard')");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Admin Dashboard - Results Management System</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/export-data.js"></script>
    <style>
        body {
            background: url('../../Common/background.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            font-family: Arial, sans-serif;
            margin: 0;
        }
        .container-fluid {
            background-color: rgba(0, 0, 0, 0.3); /* Slight tint for readability */
            min-height: 100vh;
        }
        .sidebar {
            background-color: rgba(255, 255, 255, 0.9);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            padding-top: 60px;
            transition: transform 0.3s ease;
        }
        .sidebar.collapsed {
            transform: translateX(-250px);
        }
        .sidebar .nav-link {
            color: #333;
            padding: 10px 20px;
        }
        .sidebar .nav-link:hover {
            background-color: #ffd700;
            color: #000;
        }
        .sidebar .nav-link.active {
            background-color: #ffd700;
            color: #000;
        }
        .sidebar .collapse .nav-link {
            padding-left: 40px;
            font-size: 0.9em;
        }
        .topbar {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 10px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        .topbar .btn-danger {
            background-color: #dc3545;
        }
        .main-content {
            margin-left: 250px;
            padding: 80px 20px 20px;
        }
        .card {
            background-color: rgba(255, 255, 255, 0.9);
            color: #333;
            border: none;
            border-radius: 10px;
        }
        .card-title {
            font-weight: bold;
        }
        .btn-primary {
            background-color: #ffd700;
            border: none;
            color: #000;
        }
        .btn-primary:hover {
            background-color: #ffc107;
        }
        h1, h2 {
            color: #ffd700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        .table {
            background-color: rgba(255, 255, 255, 0.9);
            color: #333;
        }
        #progressiveGraph, #divisionChart, #subjectChart, #topSchoolsChart {
            height: 400px;
            max-width: 100%;
            margin: 20px auto;
        }
        .progress-bar {
            background-color: #ffd700;
        }
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-250px);
            }
            .sidebar.collapsed {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="topbar d-flex justify-content-between align-items-center">
        <button class="btn btn-outline-light d-lg-none" type="button" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div>
            <span class="mr-3">Board: <?php echo htmlspecialchars($board_name); ?></span>
            <span class="mr-3">Year: <?php echo htmlspecialchars($exam_year); ?></span>
            <span class="mr-3">User: <?php echo $username; ?></span>
            <a href="../../Common/logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebar" class="sidebar bg-light col-lg-2">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" id="dashboardLink">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#schoolsMenu">
                                <i class="fas fa-school"></i> Schools
                            </a>
                            <div id="schoolsMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="schools/add_school.php">Add School</a></li>
                                    <li class="nav-item"><a class="nav-link" href="schools/manage_schools.php">Manage Schools</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#usersMenu">
                                <i class="fas fa-users"></i> Users
                            </a>
                            <div id="usersMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="users/add_user.php">Add User</a></li>
                                    <li class="nav-item"><a class="nav-link" href="users/manage_users.php">Manage Users</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#subcountiesMenu">
                                <i class="fas fa-map-marker-alt"></i> Subcounties
                            </a>
                            <div id="subcountiesMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="subcounties/add_subcounty.php">Add Subcounty</a></li>
                                    <li class="nav-item"><a class="nav-link" href="subcounties/manage_subcounties.php">Manage Subcounties</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#targetsMenu">
                                <i class="fas fa-bullseye"></i> Daily Targets
                            </a>
                            <div id="targetsMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="targets/set_targets.php">Set Targets</a></li>
                                    <li class="nav-item"><a class="nav-link" href="targets/manage_targets.php">Manage Targets</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#gradingMenu">
                                <i class="fas fa-table"></i> Grading
                            </a>
                            <div id="gradingMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="grading/manage_grading.php">Manage Grading Table</a></li>
                                    <li class="nav-item"><a class="nav-link" href="grading/manage_grades.php">Manage Grade Table</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="analysis/run_analysis.php">
                                <i class="fas fa-chart-bar"></i> Run Analysis
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#resultsMenu">
                                <i class="fas fa-file-alt"></i> Results
                            </a>
                            <div id="resultsMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="results/view_results.php">View Results</a></li>
                                    <li class="nav-item"><a class="nav-link" href="results/audit_results.php">Audit Results</a></li>
                                    <li class="nav-item"><a class="nav-link" href="results/generate_school_results.php">School Results</a></li>
                                    <li class="nav-item"><a class="nav-link" href="results/generate_subcounty_results.php">Subcounty Results</a></li>
                                    <li class="nav-item"><a class="nav-link" href="results/generate_general_results.php">General Results</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="audit_logs.php">
                                <i class="fas fa-history"></i> Audit Logs
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content col-lg-10">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>System Admin Dashboard</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent">
                            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                        </ol>
                    </nav>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-school"></i> Total Schools</h5>
                                <p class="card-text" id="totalSchools">Loading...</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-check-circle"></i> Results Declared</h5>
                                <p class="card-text" id="schoolsResultsDeclared">Loading...</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-users"></i> Total Candidates</h5>
                                <p class="card-text" id="totalCandidates">Loading...</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-graduation-cap"></i> Pass Rate</h5>
                                <p class="card-text" id="passRate">Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-bullseye"></i> Daily Target Progress (Today)</h5>
                                <div class="progress">
                                    <div id="targetProgressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                </div>
                                <p class="mt-2">Target: <span id="targetEntries">0</span> | Actual: <span id="actualEntries">0</span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <div id="divisionChart"></div>
                    </div>
                    <div class="col-md-6">
                        <div id="subjectChart"></div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <div id="topSchoolsChart"></div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <div id="progressiveGraph"></div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <h2>Division Summary</h2>
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
                                <tr><td colspan="4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <h2>Data Entrant Monitoring</h2>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Total Candidates</th>
                                    <th>4 Subjects</th>
                                    <th>3 Subjects</th>
                                    <th>2 Subjects</th>
                                    <th>1 Subject</th>
                                </tr>
                            </thead>
                            <tbody id="entrantTable">
                                <tr><td colspan="6">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        // Fetch dashboard data
        fetch('data/dashboard_data.php')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error loading data: ' + data.error);
                    return;
                }

                // Update widgets
                document.getElementById('totalSchools').textContent = data.totalSchools;
                document.getElementById('schoolsResultsDeclared').textContent = data.schoolsResultsDeclared;
                document.getElementById('totalCandidates').textContent = data.totalCandidates;
                document.getElementById('passRate').textContent = data.passRate + '%';

                // Daily Target Progress
                document.getElementById('targetProgressBar').style.width = data.targetProgress.percentage + '%';
                document.getElementById('targetProgressBar').setAttribute('aria-valuenow', data.targetProgress.percentage);
                document.getElementById('targetProgressBar').textContent = data.targetProgress.percentage + '%';
                document.getElementById('targetEntries').textContent = data.targetProgress.target;
                document.getElementById('actualEntries').textContent = data.targetProgress.actual;

                // Division Summary Table
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

                // Data Entrant Monitoring Table
                let entrantBody = '';
                data.entrantData.forEach(row => {
                    entrantBody += `
                        <tr>
                            <td>${row.username}</td>
                            <td>${row.total_candidates}</td>
                            <td>${row.four_subjects}</td>
                            <td>${row.three_subjects}</td>
                            <td>${row.two_subjects}</td>
                            <td>${row.one_subject}</td>
                        </tr>
                    `;
                });
                document.getElementById('entrantTable').innerHTML = entrantBody;

                // Progressive Line Chart
                Highcharts.chart('progressiveGraph', {
                    chart: { type: cómo, backgroundColor: 'rgba(255, 255, 255, 0.9)' },
                    title: { text: 'Data Entries Over Time' },
                    xAxis: { type: 'datetime', title: { text: 'Date' } },
                    yAxis: { title: { text: 'Number of Entries' }, min: 0 },
                    series: [{
                        name: 'Entries',
                        data: data.progressiveData,
                        color: '#ffd700'
                    }],
                    credits: { enabled: false }
                });

                // Division Donut Chart
                Highcharts.chart('divisionChart', {
                    chart: { type: 'pie', backgroundColor: 'rgba(255, 255, 255, 0.9)' },
                    title: { text: 'Results by Division' },
                    plotOptions: {
                        pie: {
                            innerSize: '50%',
                            dataLabels: { enabled: true, format: '{point.name}: {point.percentage:.1f}%' }
                        }
                    },
                    series: [{
                        name: 'Candidates',
                        data: data.divisionData,
                        colors: ['#007bff', '#28a745', '#ffc107', '#dc3545']
                    }],
                    credits: { enabled: false }
                });

                // Subject Performance Donut Chart
                Highcharts.chart('subjectChart', {
                    chart: { type: 'pie', backgroundColor: 'rgba(255, 255, 255, 0.9)' },
                    title: { text: 'Average Subject Performance' },
                    plotOptions: {
                        pie: {
                            innerSize: '50%',
                            dataLabels: { enabled: true, format: '{point.name}: {point.y:.1f}' }
                        }
                    },
                    series: [{
                        name: 'Average Mark',
                        data: data.subjectData,
                        colors: ['#17a2b8', '#6f42c1', '#fd7e14', '#20c997']
                    }],
                    credits: { enabled: false }
                });

                // Top Schools Bar Chart
                Highcharts.chart('topSchoolsChart', {
                    chart: { type: 'bar', backgroundColor: 'rgba(255, 255, 255, 0.9)' },
                    title: { text: 'Top Performing Schools' },
                    xAxis: { type: 'category', title: { text: 'School' } },
                    yAxis: { title: { text: 'Average Mark' }, min: 0 },
                    series: [{
                        name: 'Average Mark',
                        data: data.topSchools,
                        color: '#ffd700'
                    }],
                    credits: { enabled: false }
                });
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                alert('Failed to load dashboard data');
            });
    </script>
</body>
</html>