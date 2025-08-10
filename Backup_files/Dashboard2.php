<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="path/to/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="path/to/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="path/to/custom.css">
    <title>Examination System Dashboard</title>
</head>
<body>

    <div id="app">
        <!-- Top Bar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <span class="navbar-brand" id="boardName"></span>
                <span class="navbar-text" id="loggedInUser"></span>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="examYearDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        Exam Year: <span id="currentExamYear"></span>
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="examYearDropdown" id="examYearOptions"></ul>
                </div>
                <a href="logout.php" class="btn btn-danger ms-auto">Logout</a>
            </div>
        </nav>

        <div class="d-flex">
            <!-- Side Navigation -->
            <div class="sidebar bg-light">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" id="dashboardLink"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="schoolsLink"><i class="fas fa-school"></i> Schools</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="resultsLink"><i class="fas fa-clipboard-list"></i> Capture Results</a>
                    </li>
                </ul>
            </div>

            <!-- Main Content Area -->
            <div class="main-content p-3" id="mainContent">
                <!-- Breadcrumbs -->
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb" id="breadcrumbs">
                        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                    </ol>
                </nav>

                <!-- Cards for Summary -->
                <div class="row mb-3" id="summaryCards">
                    <div class="col-lg-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Total Schools</h5>
                                <p class="card-text" id="totalSchools">0</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Total Candidates</h5>
                                <p class="card-text" id="totalCandidates">0</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Schools with Declared Marks</h5>
                                <p class="card-text" id="declaredMarksSchools">0</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Missing Schools</h5>
                                <p class="card-text" id="missingSchools">0</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Line Chart for Marks Entry -->
                <div class="mb-3" id="lineChartContainer">
                    <canvas id="dailyLineChart"></canvas>
                </div>

                <!-- Section 4: Notices, Daily Target, Summary of Results -->
                <div id="userSummary">
                    <h4>Notices</h4>
                    <p id="notices">No new notices</p>

                    <h4>Daily Target</h4>
                    <p id="dailyTarget">0</p>

                    <h4>Summary of Results Entered</h4>
                    <p id="summaryResults">0</p>
                </div>
            </div>
        </div>
    </div>

    <script src="path/to/jquery.min.js"></script>
    <script src="path/to/bootstrap.bundle.min.js"></script>
    <script src="path/to/chart.js"></script>
    <script src="custom.js"></script>
</body>
</html>
