<?php
// Include database connection
include('connections/db.connection.php');

// Start session to get user details
session_start();
$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'];
$boardName = 'LUUKA DISTRICT LOCAL GOVERNMENT EXAMINATIONS BOARD'; // This would be fetched from your database

// Fetch total candidates and gender distribution
$totalCandidatesResult = $conn->query("SELECT COUNT(*) AS total FROM candidates");
$totalCandidates = $totalCandidatesResult->fetch_assoc()['total'];

$femaleCandidatesResult = $conn->query("SELECT COUNT(*) AS total FROM candidates WHERE sex = 'F'");
$totalFemale = $femaleCandidatesResult->fetch_assoc()['total'];

$maleCandidatesResult = $conn->query("SELECT COUNT(*) AS total FROM candidates WHERE sex = 'M'");
$totalMale = $maleCandidatesResult->fetch_assoc()['total'];

// Fetch total schools and type distribution
$totalSchoolsResult = $conn->query("SELECT COUNT(*) AS total FROM schools");
$totalSchools = $totalSchoolsResult->fetch_assoc()['total'];

// Count the total number of private schools
$privateSchoolsResult = $conn->query("SELECT COUNT(*) AS total FROM schools WHERE school_type_id = 2");
$totalPrivateSchools = $privateSchoolsResult->fetch_assoc()['total'];

// Count the total number of government schools
$governmentSchoolsResult = $conn->query("SELECT COUNT(*) AS total FROM schools WHERE School_type_id = 1");
$totalGovernmentSchools = $governmentSchoolsResult->fetch_assoc()['total'];


// Fetch the number of declared schools
$declaredResultsQuery = "
    SELECT COUNT(*) AS declared_count
    FROM (
        SELECT school_id
        FROM marks
        GROUP BY school_id
        HAVING COUNT(DISTINCT subject_id) = 4
    ) AS declared_schools
";
$declaredResultsResult = $conn->query($declaredResultsQuery);
$declaredResultsData = $declaredResultsResult->fetch_assoc();
$totalDeclaredResults = $declaredResultsData['declared_count'];


// Fetch the number of undeclared schools
$undeclaredResultsQuery = "
    SELECT COUNT(*) AS undeclared_count
    FROM (
        SELECT s.id
        FROM schools s
        LEFT JOIN candidates c ON s.id = c.school_id
        LEFT JOIN marks m ON c.id = m.candidate_id
        GROUP BY s.id
        HAVING COUNT(DISTINCT m.subject_id) < 4
    ) AS undeclared_schools
";
$undeclaredResultsResult = $conn->query($undeclaredResultsQuery);
$undeclaredResultsData = $undeclaredResultsResult->fetch_assoc();
$totalUndeclaredResults = $undeclaredResultsData['undeclared_count'];


// Fetch schools handled by the user
$schoolsHandled = $conn->prepare("
  SELECT 
    s.School_Name AS name, 
    COUNT(c.id) AS candidate_count,
    COUNT(DISTINCT CASE WHEN m.mark > 0 THEN c.id ELSE NULL END) AS candidates_with_marks,
    GROUP_CONCAT(DISTINCT subj.name ORDER BY subj.name ASC SEPARATOR ', ') AS subjects,
    CASE 
      WHEN SUM(m.subject_id IS NOT NULL) = (SELECT COUNT(*) FROM subjects) * COUNT(c.id) THEN 'Complete'
      ELSE 'Incomplete'
    END AS status,
    MIN(m.submitted_at) AS handled_when
  FROM schools s
  JOIN candidates c ON s.id = c.school_id
  LEFT JOIN marks m ON c.id = m.candidate_id
  LEFT JOIN subjects subj ON m.subject_id = subj.id
  WHERE m.submitted_by = ?
  GROUP BY s.id
");
$schoolsHandled->bind_param("i", $userId);
$schoolsHandled->execute();
$schoolsResult = $schoolsHandled->get_result();
$schools = $schoolsResult->fetch_all(MYSQLI_ASSOC);

$userSummary = $conn->prepare("
  SELECT 
    subj.name AS subject, 
    COUNT(m.id) AS entries,
    COUNT(CASE WHEN m.mark > 0 THEN 1 END) AS marks_submitted
  FROM marks m
  JOIN subjects subj ON m.subject_id = subj.id
  WHERE m.submitted_by = ?
  GROUP BY subj.id
");

$userSummary->bind_param("i", $userId);
$userSummary->execute();
$summaryResult = $userSummary->get_result();
$summary = $summaryResult->fetch_all(MYSQLI_ASSOC);
// Prepare the query to fetch number of candidates with marks for each subject
$subjectsQuery = $conn->prepare("
    SELECT 
        subj.name AS subject, 
        COUNT(DISTINCT c.id) AS candidates_with_marks
    FROM subjects subj
    LEFT JOIN marks m ON subj.id = m.subject_id
    LEFT JOIN candidates c ON m.candidate_id = c.id
    WHERE m.mark > 0
    GROUP BY subj.id
");
$subjectsQuery->execute();
$subjectsResult = $subjectsQuery->get_result();
$subjectsWithMarks = $subjectsResult->fetch_all(MYSQLI_ASSOC);

// Fetch daily progress data
$dailyProgress = $conn->prepare("
  SELECT 
    DATE(m.submitted_at) AS submission_date,
    COUNT(m.id) AS entries
  FROM marks m
  WHERE m.submitted_by = ?
  GROUP BY DATE(m.submitted_at)
");
$dailyProgress->bind_param("i", $userId);
$dailyProgress->execute();
$progressResult = $dailyProgress->get_result();
$progress = $progressResult->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .sidebar {
            width: 200px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            background-color: #343a40;
            padding-top: 20px;
            z-index: 1000;
        }
        .sidebar .nav-link {
            color: #fff;
            margin-bottom: 10px;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .topbar {
            width: calc(100% - 200px);
            background-color: #007bff;
            color: #fff;
            padding: 10px;
            position: fixed;
            top: 0;
            left: 200px;
            height: 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
        }
        .main-content {
            margin-left: 200px;
            padding-top: 80px;
            padding-right: 20px;
            padding-left: 20px;
        }
        .card h5 {
            font-size: 1.2rem;
            font-weight: bold;
        }
        canvas {
            width: 100% !important;
            height: 400px !important;
        }
        .chat-container {
            position: fixed;
            bottom: 0;
            right: 0;
            width: 300px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .chat-header {
            background-color: #007bff;
            color: #fff;
            padding: 10px;
            border-radius: 5px 5px 0 0;
            text-align: center;
        }
        .chat-messages {
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
        }
        .chat-form {
            padding: 10px;
            border-top: 1px solid #dee2e6;
        }
        .chat-form input {
            width: calc(100% - 80px);
            display: inline-block;
        }
        .chat-form button {
            display: inline-block;
            width: 70px;
        }
        .card-icon {
            font-size: 3rem;
            color: #007bff;
        }
        .table th, .table td {
            text-align: center;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <ul class="nav flex-column">
        <!-- Schools Menu -->
        <li class="nav-item">
            <a class="nav-link" href="#schoolsMenu" data-toggle="collapse">
                <i class="fas fa-school"></i> Schools
            </a>
            <div id="schoolsMenu" class="collapse">
                <a class="nav-link" href="schools.php">
                    <i class="fas fa-list"></i> List Schools
                </a>
            </div>
        </li>

        <!-- Capture Marks Menu -->
        <li class="nav-item">
            <a class="nav-link" href="#marksMenu" data-toggle="collapse">
                <i class="fas fa-marker"></i> Capture Marks
            </a>
            <div id="marksMenu" class="collapse">
                <a class="nav-link" href="marks.php">
                    <i class="fas fa-edit"></i> Enter Marks
                </a>
                <a class="nav-link" href="see_marks.php">
                    <i class="fas fa-eye"></i> View Results
                </a>
                <a class="nav-link" href="check_missing.php">
                    <i class="fas fa-eye"></i> Check Missing
                </a>
            </div>
        </li>

        <!-- Messages Menu -->
        <li class="nav-item">
            <a class="nav-link" href="#messagesMenu" data-toggle="collapse">
                <i class="fas fa-envelope"></i> Messages
            </a>
            <div id="messagesMenu" class="collapse">
                <a class="nav-link" href="team_chat.php">
                    <i class="fas fa-users"></i> Team Chat
                </a>
                <a class="nav-link" href="my_chats.php">
                    <i class="fas fa-comments"></i> My Chats
                </a>
            </div>
        </li>
    </ul>
</div>

<div class="topbar">
    <div class="board-name"><?php echo htmlspecialchars($boardName); ?></div>
    <div class="user-info">
        <span><?php echo htmlspecialchars($userName); ?></span>
        <span><?php echo date('Y-m-d'); ?></span>
        <span>Logged in at: <?php echo date('H:i'); ?></span>
        <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <i class="fas fa-users card-icon"></i>
                    <h5>Total Candidates</h5>
                    <p><?php echo htmlspecialchars($totalCandidates); ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <i class="fas fa-female card-icon"></i>
                    <h5>Total Female</h5>
                    <p><?php echo htmlspecialchars($totalFemale); ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <i class="fas fa-male card-icon"></i>
                    <h5>Total Male</h5>
                    <p><?php echo htmlspecialchars($totalMale); ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <i class="fas fa-school card-icon"></i>
                    <h5>Total Schools</h5>
                    <p><?php echo htmlspecialchars($totalSchools); ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <i class="fas fa-building card-icon"></i>
                    <h5>Private Schools</h5>
                    <p><?php echo htmlspecialchars($totalPrivateSchools); ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <i class="fas fa-building card-icon"></i>
                    <h5>Government Schools</h5>
                    <p><?php echo htmlspecialchars($totalGovernmentSchools); ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <i class="fas fa-check card-icon"></i>
                    <h5>Declared Results</h5>
                    <p><?php echo htmlspecialchars($totalDeclaredResults); ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <i class="fas fa-times card-icon"></i>
                    <h5>Undeclared Results</h5>
                    <p><?php echo htmlspecialchars($totalUndeclaredResults); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
    <div class="col-lg-12">
        <div class="card mb-3">
            <div class="card-body">
                <h5>Daily Progress</h5>
                <canvas id="progressChart"></canvas>
            </div>
        </div>
    </div>
</div>
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <h5>My Schools</h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>School Name</th>
                                <th>Total Candidates</th>
                                <th>Candidates with Marks</th>
                                <th>Subjects</th>
                                <th>Status</th>
                                <th>Handled When</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($schools as $school): ?>
                            <tr>
                                <td><?php echo $school['name']; ?></td>
                                <td><?php echo $school['candidate_count']; ?></td>
                                <td><?php echo $school['candidates_with_marks']; ?></td>
                                <td><?php echo $school['subjects']; ?></td>
                                <td><?php echo $school['status']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($school['handled_when'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
    <div class="col-lg-12">
    <div class="card">
        <div class="card-body">
            <h5>Summary of Entries</h5>
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>Subject</th>
                    <th>Number of Entries</th>
                    <th>Marks Submitted</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($summary as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['subject']); ?></td>
                        <td><?php echo htmlspecialchars($item['entries']); ?></td>
                        <td><?php echo htmlspecialchars($item['marks_submitted']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

                <!--
    <div class="chat-container">
        <div class="chat-header">Chat</div>
        <div class="chat-messages">
           Messages will appear here 
        </div>
        <div class="chat-form">
            <input type="text" placeholder="Type a message..." />
            <button class="btn btn-primary">Send</button>
        </div>
    </div>
     -->
    
               
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Include Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
    const ctx = document.getElementById('progressChart').getContext('2d');
const progressChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [<?php foreach ($progress as $day) { echo "'" . $day['submission_date'] . "',"; } ?>],
        datasets: [{
            label: 'Entries per Day',
            data: [<?php foreach ($progress as $day) { echo $day['entries'] . ","; } ?>],
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 2,
            fill: false,
            tension: 0.4  // This adds a curve to the line
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            tooltip: {
                enabled: true,  // Ensure tooltips are enabled
                callbacks: {
                    label: function(context) {
                        return 'Entries: ' + context.raw;  // Customize the tooltip label
                    }
                }
            }
        },
        interaction: {
            mode: 'nearest',  // Ensure the tooltip shows when hovering near the point
            axis: 'x',
            intersect: false
        },
        hover: {
            mode: 'nearest',
            intersect: false
        }
    }
});

    const socket = io();

// Handle chat messages
const chatMessages = document.getElementById('chat-messages');
const chatForm = document.getElementById('chat-form');
const chatInput = document.getElementById('chat-input');

chatForm.addEventListener('submit', (event) => {
    event.preventDefault();
    const message = chatInput.value;
    socket.emit('chat message', message);
    chatInput.value = '';
});

socket.on('chat message', (msg) => {
    const item = document.createElement('div');
    item.textContent = msg;
    chatMessages.appendChild(item);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    // Play notification sound
    const audio = new Audio('https://www.soundjay.com/button/beep-07.wav');
    audio.play();
    
    // Browser notification
    if (Notification.permission === 'granted') {
        new Notification('New message', { body: msg });
    } else if (Notification.permission !== 'denied') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                new Notification('New message', { body: msg });
            }
        });
    }
});
</script>
</body>
</html>
