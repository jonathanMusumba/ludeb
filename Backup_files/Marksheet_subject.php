<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

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

$exam_year = date('Y'); // Example: fetch current year or set as needed

// Check if school_id and subject_id are set in the URL
$school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

// Validate parameters
if ($school_id <= 0 || $subject_id <= 0) {
    die("Invalid parameters provided.");
}

// Fetch school details
$school_query = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
$school_query->bind_param("i", $school_id);
$school_query->execute();
$school_result = $school_query->get_result();
$school = $school_result->fetch_assoc();

// Check if school exists
if (!$school) {
    die("School not found.");
}

// Fetch subject details
$subject_query = $conn->prepare("SELECT Name FROM subjects WHERE id = ?");
$subject_query->bind_param("i", $subject_id);
$subject_query->execute();
$subject_result = $subject_query->get_result();
$subject = $subject_result->fetch_assoc();

// Check if subject exists
if (!$subject) {
    die("Subject not found.");
}

// Fetch grading ranges
$grading_query = $conn->query("SELECT range_from, range_to, grade FROM grading");
$grading_ranges = [];
while ($row = $grading_query->fetch_assoc()) {
    $grading_ranges[] = $row;
}

// Fetch marks for the subject
$marks_query = $conn->prepare("
    SELECT c.IndexNo, c.Candidate_Name, m.mark 
    FROM marks m
    JOIN candidates c ON m.candidate_id = c.id
    WHERE m.subject_id = ? AND m.school_id = ? AND c.exam_year = ?
");
$marks_query->bind_param("iis", $subject_id, $school_id, $exam_year);
$marks_query->execute();
$marks_result = $marks_query->get_result();

$marks_data = [];
$total_marks = 0;
$count = 0;

while ($row = $marks_result->fetch_assoc()) {
    $marks_data[] = $row;
    $total_marks += $row['mark'];
    $count++;
}

$average_mark = ($count > 0) ? ($total_marks / $count) : 0;

// Check if there are no marks data
if (empty($marks_data)) {
    echo "<p>No results captured for " . htmlspecialchars($school['school_name']) . " for the selected subject.</p>";
} else {
    echo "<p>Results for " . htmlspecialchars($school['school_name']) . " for the selected subject:</p>";
}

$conn->close();

function getGrade($mark, $grading_ranges) {
    foreach ($grading_ranges as $range) {
        if ($mark >= $range['range_from'] && $mark <= $range['range_to']) {
            return $range['grade'];
        }
    }
    return 'N/A'; // Default grade if mark does not fall into any range
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Sheet</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        .average {
            text-align: right;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>MOCK EXAMINATIONS <?php echo htmlspecialchars($exam_year); ?></h1>
        <h2><?php echo htmlspecialchars($school['school_name']); ?></h2>
        <h3><?php echo htmlspecialchars($subject['Name']); ?> MARKSHEET</h3>

        <?php if (!empty($marks_data)): ?>
            <table>
                <thead>
                    <tr>
                        <th>IndexNo</th>
                        <th>Candidate Name</th>
                        <th>Marks</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marks_data as $data): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($data['IndexNo']); ?></td>
                        <td><?php echo htmlspecialchars($data['Candidate_Name']); ?></td>
                        <td><?php echo htmlspecialchars($data['mark']); ?></td>
                        <td><?php echo getGrade($data['mark'], $grading_ranges); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="average">Average Mark:</td>
                        <td colspan="2" class="average"><?php echo number_format($average_mark, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
