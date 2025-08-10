<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch Board Name
$board_name_query = "SELECT Board_Name FROM examination_board LIMIT 1";
$board_name_result = $conn->query($board_name_query);
$board_name = $board_name_result->fetch_assoc()['Board_Name'];

// Fetch Exam Year
$exam_year_query = "SELECT Exam_Year FROM exam_years ORDER BY Exam_Year DESC LIMIT 1";
$exam_year_result = $conn->query($exam_year_query);
$exam_year = $exam_year_result->fetch_assoc()['Exam_Year'];

// Fetch Username
$user_id = $_SESSION['user_id'];
$username_query = "SELECT Username FROM system_users WHERE id = '$user_id'";
$username_result = $conn->query($username_query);
$username = $username_result->fetch_assoc()['Username'];


// Handle potential errors
if (!$board_name) $board_name = "Unknown Board";
if (!$exam_year) $exam_year = "Unknown Year";
if (!$username) $username = "Guest";
// Schools Summary
$schools_summary_query = "
    SELECT COUNT(DISTINCT subject_id) AS total_subjects, COUNT(DISTINCT school_id) AS total_schools
    FROM marks
    WHERE mark >= 1
";
$schools_summary_result = $conn->query($schools_summary_query);
$schools_summary = $schools_summary_result->fetch_assoc();

// Number of schools with 3 subjects, 2 subjects, 1 subject
$subject_count_query = "
    SELECT
        COUNT(CASE WHEN subject_count = 3 THEN 1 END) AS three_subjects,
        COUNT(CASE WHEN subject_count = 2 THEN 1 END) AS two_subjects,
        COUNT(CASE WHEN subject_count = 1 THEN 1 END) AS one_subject
    FROM (
        SELECT school_id, COUNT(DISTINCT subject_id) AS subject_count
        FROM marks
        WHERE mark >= 1
        GROUP BY school_id
    ) AS subject_counts
";
$subject_count_result = $conn->query($subject_count_query);
$subject_count_summary = $subject_count_result->fetch_assoc();

// Subject Summary
$subject_summary_query = "
    SELECT s.Code AS subject_code, COUNT(DISTINCT m.school_id) AS number_of_schools
    FROM marks m
    JOIN subjects s ON m.subject_id = s.id
    WHERE m.mark >= 1
    GROUP BY s.Code
";
$subject_summary_result = $conn->query($subject_summary_query);
$subject_summary = [];
while ($row = $subject_summary_result->fetch_assoc()) {
    $subject_summary[] = $row;
}
$sql = "
    SELECT
        s.CenterNo,
        s.School_Name AS school_name,
        sub.Code AS subject_code,
        SUM(CASE WHEN m.mark >= 1 THEN 1 ELSE 0 END) AS submitted_marks,
        COUNT(DISTINCT c.id) AS total_registered
    FROM
        marks m
    JOIN
        schools s ON m.school_id = s.id
    JOIN
        subjects sub ON m.subject_id = sub.id
    JOIN
        candidates c ON m.candidate_id = c.id
    GROUP BY
        s.CenterNo, s.School_Name, sub.Code
";
$result = $conn->query($sql);
$sql = "
    SELECT
        s.CenterNo,
        s.School_Name AS school_name,
        GROUP_CONCAT(DISTINCT sub.Code ORDER BY sub.Code ASC SEPARATOR ', ') AS subject_codes,
        GROUP_CONCAT(DISTINCT u.username ORDER BY u.username ASC SEPARATOR ', ') AS submitted_by
    FROM
        marks m
    JOIN
        schools s ON m.school_id = s.id
    JOIN
        subjects sub ON m.subject_id = sub.id
    JOIN
        system_users u ON m.submitted_by = u.id
    GROUP BY
        s.CenterNo, s.School_Name
";
$user_submission_result = $conn->query($sql);
$sql = "
    SELECT
        s.CenterNo,
        s.School_Name AS school_name,
        GROUP_CONCAT(DISTINCT sub.Code ORDER BY sub.Code ASC SEPARATOR ', ') AS subject_codes,
        GROUP_CONCAT(DISTINCT u.username ORDER BY u.username ASC SEPARATOR ', ') AS submitted_by
    FROM
        marks m
    JOIN
        schools s ON m.school_id = s.id
    JOIN
        subjects sub ON m.subject_id = sub.id
    JOIN
        system_users u ON m.submitted_by = u.id
    GROUP BY
        s.CenterNo, s.School_Name
";
$user_submission_result = $conn->query($sql);
// SQL query to get user activity summary
$sql = "
    SELECT
        u.username,
        sub.Code AS subject_code,
        COUNT(CASE WHEN m.mark >= 1 THEN 1 END) AS marks_entered,
        COUNT(DISTINCT s.id) AS school_count
    FROM
        marks m
    JOIN
        subjects sub ON m.subject_id = sub.id
    JOIN
        schools s ON m.school_id = s.id
    JOIN
        system_users u ON m.submitted_by = u.id
    GROUP BY
        u.username, sub.Code
";

$user_activity_result = $conn->query($sql);

if (!$user_activity_result) {
    die("Error in query: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Top Bar -->
    <div class="container mt-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4">
        <div class="mb-3 mb-md-0">
            <h2 class="mb-0">Board Name: <strong><?php echo $board_name; ?></strong> | Exam Year: <strong><?php echo $exam_year; ?></strong></h2>
        </div>
        <div class="d-flex align-items-center">
            <span class="mr-3">User: <strong><?php echo $username; ?></strong></span>
            <span class="mr-3">Logged in at: <strong><?php echo date('H:i'); ?></strong></span>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
        </div>
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Summary</li>
            </ol>
        </nav>

        <!-- Summary Section -->
        <section>
            <h3>Summary of Schools by Subjects</h3>
            <div class="row">
                <div class="col-md-6">
                    <h4>Schools Summary</h4>
                    <table class="table table-bordered">
                        <thead class="thead-dark">
                            <tr>
                                <th>Total Subjects Entered</th>
                                <th>Total Schools</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo $schools_summary['total_subjects']; ?></td>
                                <td><?php echo $schools_summary['total_schools']; ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <h4>Subject Count per School</h4>
                    <table class="table table-bordered">
                        <thead class="thead-dark">
                            <tr>
                                <th>3 Subjects</th>
                                <th>2 Subjects</th>
                                <th>1 Subject</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo $subject_count_summary['three_subjects']; ?></td>
                                <td><?php echo $subject_count_summary['two_subjects']; ?></td>
                                <td><?php echo $subject_count_summary['one_subject']; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h4>Subject Summary by Schools</h4>
                    <table class="table table-bordered">
                        <thead class="thead-dark">
                            <tr>
                                <th>Subject Code</th>
                                <th>Number of Schools</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subject_summary as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($item['number_of_schools']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Candidate Marks Summary -->
        <section class="mt-5">
    <h3>Candidate Marks Summary</h3>
    <table class="table table-bordered">
        <thead class="thead-dark">
            <tr>
                <th>CenterNo</th>
                <th>School Name</th>
                <th>Subject Code</th>
                <th>Marks Submitted (≥ 1)</th>
                <th>Total Number Registered</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['CenterNo']); ?></td>
                    <td><?php echo htmlspecialchars($row['school_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                    <td><?php echo htmlspecialchars($row['submitted_marks']); ?></td>
                    <td><?php echo htmlspecialchars($row['total_registered']); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</section>

<section class="mt-5">
    <h3>Marks Submission Summary by User</h3>
    <table class="table table-bordered">
        <thead class="thead-dark">
            <tr>
                <th>CenterNo</th>
                <th>School Name</th>
                <th>Subject Codes</th>
                <th>Submitted By</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $user_submission_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['CenterNo']); ?></td>
                    <td><?php echo htmlspecialchars($row['school_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['subject_codes']); ?></td>
                    <td><?php echo htmlspecialchars($row['submitted_by']); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</section>

<section class="mt-5">
    <h3>User Activity Summary</h3>
    <table class="table table-bordered">
        <thead class="thead-dark">
            <tr>
                <th>User</th>
                <th>Subject</th>
                <th>Number of Marks Entered (≥ 1)</th>
                <th>Number of Schools</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $user_activity_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                    <td><?php echo htmlspecialchars($row['marks_entered']); ?></td>
                    <td>
                        <a href="school_details.php?username=<?php echo urlencode($row['username']); ?>&subject_code=<?php echo urlencode($row['subject_code']); ?>">
                            <?php echo htmlspecialchars($row['school_count']); ?>
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</section>
        <!-- Integrated Chat Section -->
        <section class="mt-5">
            <h3>Real-Time Chat</h3>
            <div id="chat-container" class="border p-3" style="height: 300px; overflow-y: auto;">
                <!-- Chat messages will go here -->
            </div>
            <input type="text" id="chat-input" class="form-control mt-3" placeholder="Type your message...">
            <button id="send-btn" class="btn btn-primary mt-2">Send</button>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.min.js"></script>
    <script src="path/to/your/websocket.js"></script> <!-- WebSocket script -->
</body>
</html>
