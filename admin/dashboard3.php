<?php
session_start();
require_once 'db_connect.php';

// Restrict to System Admins and Examination Administrators
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['System Admin', 'Examination Administrator'])) {
    header("Location: ../../login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);
$user_id = $_SESSION['user_id'];

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');

// Log dashboard access
$conn->query("CALL log_action('Dashboard Access', $user_id, 'Accessed dashboard')");

// Fetch schools for filter
$schools = $conn->query("SELECT id, school_name AS name FROM schools ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Results Management System</title>
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
            background-color: rgba(0, 0, 0, 0.3);
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
        .btn-primary, .btn-secondary {
            background-color: #ffd700;
            border: none;
            color: #000;
        }
        .btn-primary:hover, .btn-secondary:hover {
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
        #progressiveGraph, #divisionChart, #subjectChart, #topSchoolsChart, #targetProgressChart {
            height: 400px;
            max-width: 100%;
            margin: 20px auto;
        }
        .progress-bar {
            background-color: #ffd700;
        }
        .alerts {
            margin-bottom: 20px;
        }
        .filter-container {
            margin-bottom: 15px;
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
                        <li class="nav-item"><a class="nav-link active" href="#" id="dashboardLink"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#schoolsMenu"><i class="fas fa-school"></i> Schools</a>
                            <div id="schoolsMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="schools/add_school.php">Add School</a></li>
                                    <li class="nav-item"><a class="nav-link" href="schools/manage_schools.php">Manage Schools</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#usersMenu"><i class="fas fa-users"></i> Users</a>
                            <div id="usersMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="users/add_user.php">Add User</a></li>
                                    <li class="nav-item"><a class="nav-link" href="users/manage_users.php">Manage Users</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#subcountiesMenu"><i class="fas fa-map-marker-alt"></i> Subcounties</a>
                            <div id="subcountiesMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="subcounties/add_subcounty.php">Add Subcounty</a></li>
                                    <li class="nav-item"><a class="nav-link" href="subcounties/manage_subcounties.php">Manage Subcounties</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#targetsMenu"><i class="fas fa-bullseye"></i> Daily Targets</a>
                            <div id="targetsMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="targets/set_targets.php">Set Targets</a></li>
                                    <li class="nav-item"><a class="nav-link" href="targets/manage_targets.php">Manage Targets</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#gradingMenu"><i class="fas fa-table"></i> Grading</a>
                            <div id="gradingMenu" class="collapse">
                                <ul class="nav flex-column">
                                    <li class="nav-item"><a class="nav-link" href="grading/manage_grading.php">Manage Grading Table</a></li>
                                    <li class="nav-item"><a class="nav-link" href="grading/manage_grades.php">Manage Grade Table</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="analysis/run_analysis.php"><i class="fas fa-chart-bar"></i> Run Analysis</a></li>
                        <li class="nav-item">
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#resultsMenu"><i class="fas fa-file-alt"></i> Results</a>
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
                        <li class="nav-item"><a class="nav-link" href="chat.php"><i class="fas fa-comments"></i> Team Chat</a></li>
                        <li class="nav-item"><a class="nav-link" href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                        <li class="nav-item"><a class="nav-link" href="audit_logs.php"><i class="fas fa-history"></i> Audit Logs</a></li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="main-content col-lg-10">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Dashboard</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent">
                            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                        </ol>
                    </nav>
                </div>

                <div class="alerts" id="alertContainer"></div>

                <div class="row mb-3">
                    <div class="col-md-2"><div class="card text-center"><div class="card-body"><h5 class="card-title"><i class="fas fa-map-marker-alt"></i> Total Subcounties</h5><p class="card-text" id="totalSubcounties">Loading...</p></div></div></div>
                    <div class="col-md-2"><div class="card text-center"><div class="card-body"><h5 class="card-title"><i class="fas fa-school"></i> Total Schools</h5><p class="card-text" id="totalSchools">Loading...</p></div></div></div>
                    <div class="col-md-2"><div class="card text-center"><div class="card-body"><h5 class="card-title"><i class="fas fa-building"></i> Private Schools</h5><p class="card-text" id="privateSchools">Loading...</p></div></div></div>
                    <div class="col-md-2"><div class="card text-center"><div class="card-body"><h5 class="card-title"><i class="fas fa-building"></i> UPE Schools</h5><p class="card-text" id="governmentSchools">Loading...</p></div></div></div>
                    <div class="col-md-2"><div class="card text-center"><div class="card-body"><h5 class="card-title"><i class="fas fa-check-circle"></i> Results Declared</h5><p class="card-text" id="schoolsResultsDeclared">Loading...</p></div></div></div>
                    <div class="col-md-2"><div class="card text-center"><div class="card-body"><h5 class="card-title"><i class="fas fa-times-circle"></i> Undeclared Results</h5><p class="card-text" id="undeclaredResults">Loading...</p></div></div></div>
                    <div class="col-md-2"><div class="card text-center"><div class="card-body"><h5 class="card-title"><i class="fas fa-users"></i> Total Candidates</h5><p class="card-text" id="totalCandidates">Loading...</p></div></div></div>
                    <div class="col-md-2"><div class="card text-center"><div class="card-body"><h5 class="card-title"><i class="fas fa-female"></i> Female Candidates</h5><p class="card-text" id="femaleCandidates">Loading...</p></div></div></div>
                    <div class="col-md-2"><div class="card text-center"><div class="card-body"><h5 class="card-title"><i class="fas fa-male"></i> Male Candidates</h5><p class="card-text" id="maleCandidates">Loading...</p></div></div></div>
                    <div class="col-md-2"><div class="card text-center"><div class="card-body"><h5 class="card-title"><i class="fas fa-graduation-cap"></i> Pass Rate</h5><p class="card-text" id="passRate">Loading...</p></div></div></div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
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
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-chart-bar"></i> Target Progress (Last 7 Days)</h5>
                                <div id="targetProgressChart"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6"><div id="divisionChart"></div></div>
                    <div class="col-md-6"><div id="subjectChart"></div></div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12"><div id="topSchoolsChart"></div></div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12"><div id="progressiveGraph"></div></div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <h2>Missing Marks</h2>
                        <div class="filter-container">
                            <label for="missingSchoolFilter">Filter by School:</label>
                            <select id="missingSchoolFilter" class="form-control d-inline-block w-auto">
                                <option value="">All Schools</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <table class="table table-striped">
                            <thead><tr><th>School Name</th><th>Candidate Index</th><th>Missing Subjects</th></tr></thead>
                            <tbody id="missingTable"><tr><td colspan="3">Loading...</td></tr></tbody>
                        </table>
                        <button id="exportMissingCsv" class="btn btn-secondary mt-2">Export to CSV</button>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <h2>Division Summary</h2>
                        <div class="filter-container">
                            <label for="divisionSchoolFilter">Filter by School:</label>
                            <select id="divisionSchoolFilter" class="form-control d-inline-block w-auto">
                                <option value="">All Schools</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <table class="table table-striped">
                            <thead><tr><th>Division</th><th>Male</th><th>Female</th><th>Total</th></tr></thead>
                            <tbody id="summaryTable"><tr><td colspan="4">Loading...</td></tr></tbody>
                        </table>
                        <button id="exportSummaryCsv" class="btn btn-secondary mt-2">Export to CSV</button>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <h2>Data Entrant Monitoring</h2>
                        <div class="filter-container">
                            <label for="entrantSchoolFilter">Filter by School:</label>
                            <select id="entrantSchoolFilter" class="form-control d-inline-block w-auto">
                                <option value="">All Schools</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <table class="table table-striped">
                            <thead><tr><th>Username</th><th>Total Candidates</th><th>4 Subjects</th><th>3 Subjects</th><th>2 Subjects</th><th>1 Subject</th></tr></thead>
                            <tbody id="entrantTable"><tr><td colspan="6">Loading...</td></tr></tbody>
                        </table>
                        <button id="exportEntrantCsv" class="btn btn-secondary mt-2">Export to CSV</button>
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
                document.getElementById('totalSubcounties').textContent = data.totalSubcounties;
                document.getElementById('totalSchools').textContent = data.totalSchools;
                document.getElementById('privateSchools').textContent = data.privateSchools;
                document.getElementById('governmentSchools').textContent = data.governmentSchools;
                document.getElementById('schoolsResultsDeclared').textContent = data.schoolsResultsDeclared;
                document.getElementById('undeclaredResults').textContent = data.undeclaredResults;
                document.getElementById('totalCandidates').textContent = data.totalCandidates;
                document.getElementById('femaleCandidates').textContent = data.femaleCandidates;
                document.getElementById('maleCandidates').textContent = data.maleCandidates;
                document.getElementById('passRate').textContent = data.passRate + '%';

                // Alerts
                if (data.undeclaredResults > 0) {
                    document.getElementById('alertContainer').innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            ${data.undeclaredResults} school(s) have undeclared results. Please review missing marks.
                        </div>
                    `;
                }

                // Daily Target Progress
                document.getElementById('targetProgressBar').style.width = data.targetProgress.today.percentage + '%';
                document.getElementById('targetProgressBar').setAttribute('aria-valuenow', data.targetProgress.today.percentage);
                document.getElementById('targetProgressBar').textContent = data.targetProgress.today.percentage + '%';
                document.getElementById('targetEntries').textContent = data.targetProgress.today.target;
                document.getElementById('actualEntries').textContent = data.targetProgress.today.actual;

                // Target Progress Chart
                Highcharts.chart('targetProgressChart', {
                    chart: { type: 'column', backgroundColor: 'rgba(255, 255, 255, 0.9)' },
                    title: { text: 'Target vs Actual Entries (Last 7 Days)' },
                    xAxis: { type: 'datetime', title: { text: 'Date' } },
                    yAxis: { title: { text: 'Entries' }, min: 0 },
                    series: [
                        { name: 'Target', data: data.targetProgress.history.map(h => [h.date, h.target]), color: '#007bff' },
                        { name: 'Actual', data: data.targetProgress.history.map(h => [h.date, h.actual]), color: '#ffd700' }
                    ],
                    credits: { enabled: false }
                });

                // Missing Marks Table
                let missingBody = data.missingMarks.length ? '' : '<tr><td colspan="3">No missing marks found</td></tr>';
                data.missingMarks.forEach(row => {
                    missingBody += `
                        <tr data-school-id="${row.school_id}">
                            <td>${row.school_name}</td>
                            <td>${row.index_number}</td>
                            <td>${row.missing_subjects}</td>
                        </tr>
                    `;
                });
                document.getElementById('missingTable').innerHTML = missingBody;

                // Division Summary Table
                let summaryBody = data.summaryTable.length ? '' : '<tr><td colspan="4">No data available</td></tr>';
                data.summaryTable.forEach(row => {
                    summaryBody += `
                        <tr data-school-id="${row.school_id || ''}">
                            <td>${row.division}</td>
                            <td>${row.male}</td>
                            <td>${row.female}</td>
                            <td>${row.total}</td>
                        </tr>
                    `;
                });
                document.getElementById('summaryTable').innerHTML = summaryBody;

                // Data Entrant Monitoring Table
                let entrantBody = data.entrantData.length ? '' : '<tr><td colspan="6">No data available</td></tr>';
                data.entrantData.forEach(row => {
                    entrantBody += `
                        <tr data-school-id="${row.school_id || ''}">
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
                    chart: { type: 'line', backgroundColor: 'rgba(255, 255, 255, 0.9)' },
                    title: { text: 'Data Entries Over Time' },
                    xAxis: { type: 'datetime', title: { text: 'Date' } },
                    yAxis: { title: { text: 'Number of Entries' }, min: 0 },
                    series: [{ name: 'Entries', data: data.progressiveData, color: '#ffd700' }],
                    credits: { enabled: false }
                });

                // Division Donut Chart
                Highcharts.chart('divisionChart', {
                    chart: { type: 'pie', backgroundColor: 'rgba(255, 255, 255, 0.9)' },
                    title: { text: 'Results by Division' },
                    plotOptions: { pie: { innerSize: '50%', dataLabels: { enabled: true, format: '{point.name}: {point.percentage:.1f}%' } } },
                    series: [{ name: 'Candidates', data: data.divisionData, colors: ['#007bff', '#28a745', '#ffc107', '#dc3545'] }],
                    credits: { enabled: false }
                });

                // Subject Performance Donut Chart
                Highcharts.chart('subjectChart', {
                    chart: { type: 'pie', backgroundColor: 'rgba(255, 255, 255, 0.9)' },
                    title: { text: 'Average Subject Performance' },
                    plotOptions: { pie: { innerSize: '50%', dataLabels: { enabled: true, format: '{point.name}: {point.y:.1f}' } } },
                    series: [{ name: 'Average Mark', data: data.subjectData, colors: ['#17a2b8', '#6f42c1', '#fd7e14', '#20c997'] }],
                    credits: { enabled: false }
                });

                // Top Schools Bar Chart
                Highcharts.chart('topSchoolsChart', {
                    chart: { type: 'bar', backgroundColor: 'rgba(255, 255, 255, 0.9)' },
                    title: { text: 'Top Performing Schools' },
                    xAxis: { type: 'category', title: { text: 'School' } },
                    yAxis: { title: { text: 'Average Mark' }, min: 0, max: 100 },
                    series: [{ name: 'Average Mark', data: data.topSchools, color: '#ffd700' }],
                    credits: { enabled: false }
                });

                // School Filters
                function applyFilter(tableId, filterId) {
                    const schoolId = document.getElementById(filterId).value;
                    document.querySelectorAll(`#${tableId} tr[data-school-id]`).forEach(row => {
                        row.style.display = !schoolId || row.dataset.schoolId == schoolId ? '' : 'none';
                    });
                }
                document.getElementById('missingSchoolFilter').addEventListener('change', () => applyFilter('missingTable', 'missingSchoolFilter'));
                document.getElementById('divisionSchoolFilter').addEventListener('change', () => applyFilter('summaryTable', 'divisionSchoolFilter'));
                document.getElementById('entrantSchoolFilter').addEventListener('change', () => applyFilter('entrantTable', 'entrantSchoolFilter'));

                // Export to CSV
                function exportTableToCsv(tableId, filename) {
                    let csv = [];
                    const headers = Array.from(document.querySelectorAll(`#${tableId} thead th`)).map(th => th.textContent);
                    csv.push(headers.join(','));
                    document.querySelectorAll(`#${tableId} tbody tr:not([style*="display: none"])`).forEach(row => {
                        const rowData = Array.from(row.querySelectorAll('td')).map(td => `"${td.textContent.replace(/"/g, '""')}"`);
                        csv.push(rowData.join(','));
                    });
                    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = filename;
                    a.click();
                    window.URL.revokeObjectURL(url);
                }
                document.getElementById('exportMissingCsv').addEventListener('click', () => exportTableToCsv('missingTable', 'missing_marks.csv'));
                document.getElementById('exportSummaryCsv').addEventListener('click', () => exportTableToCsv('summaryTable', 'division_summary.csv'));
                document.getElementById('exportEntrantCsv').addEventListener('click', () => exportTableToCsv('entrantTable', 'entrant_monitoring.csv'));
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                alert('Failed to load dashboard data');
            });
    </script>
</body>
</html>
<?php $conn->close(); ?>