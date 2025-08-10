<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] !== 'System Admin' && $_SESSION['role'] !== 'Examination Admin')) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$exam_year = date('Y');

// Fetch the list of schools
$school_query = $conn->query("SELECT id, school_name FROM schools");
$schools = [];
while ($row = $school_query->fetch_assoc()) {
    $schools[] = $row;
}

// Get the selected school ID from the form submission
$selected_school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : 0;

if ($selected_school_id > 0) {
    // Fetch candidate details and marks for the selected school
    $results_query = $conn->prepare("
        SELECT c.indexno, c.candidate_name AS candidate_name, s.code AS subject_code, m.mark 
        FROM marks m
        JOIN candidates c ON m.candidate_id = c.id
        JOIN subjects s ON m.subject_id = s.id
        WHERE c.school_id = ? AND c.exam_year = ?
    ");
    $results_query->bind_param("is", $selected_school_id, $exam_year);
    $results_query->execute();
    $results_result = $results_query->get_result();

    // Fetch grading ranges
    $grading_query = $conn->query("SELECT range_from, range_to, grade FROM grading");
    $grading_ranges = [];
    while ($row = $grading_query->fetch_assoc()) {
        $grading_ranges[] = $row;
    }

    $results = [];
    while ($row = $results_result->fetch_assoc()) {
        $results[] = $row;
    }

    if (!empty($results)) {
        // Process results to compute aggregates and divisions
        $candidates = [];
        foreach ($results as $result) {
            $index_no = $result['index_no'];
            $candidate_name = $result['candidate_name'];
            $subject_code = $result['subject_code'];
            $mark = $result['mark'];

            if (!isset($candidates[$index_no])) {
                $candidates[$index_no] = [
                    'name' => $candidate_name,
                    'marks' => [],
                    'total_marks' => 0,
                    'subject_count' => 0,
                    'grades' => []
                ];
            }

            $candidates[$index_no]['marks'][$subject_code] = $mark;
            $candidates[$index_no]['total_marks'] += $mark;
            $candidates[$index_no]['subject_count']++;

            // Calculate grade
            $grade = getGrade($mark, $grading_ranges);
            $candidates[$index_no]['grades'][$subject_code] = $grade;
        }

        // Calculate aggregates and divisions
        foreach ($candidates as $index_no => $data) {
            $total_marks = $data['total_marks'];
            $subject_count = $data['subject_count'];
            $aggregate = $subject_count > 0 ? $total_marks / $subject_count : 0;
            $division = determineDivision($aggregate);
            $candidates[$index_no]['aggregate'] = number_format($aggregate, 2);
            $candidates[$index_no]['division'] = $division;
        }
    } else {
        $error_message = "No results captured for the selected school.";
    }
} else {
    $error_message = "Please select a school.";
}

$conn->close();

function getGrade($mark, $grading_ranges) {
    foreach ($grading_ranges as $range) {
        if ($mark >= $range['range_from'] && $mark <= $range['range_to']) {
            return $range['grade'];
        }
    }
    return 'N/A';
}

function determineDivision($aggregate) {
    if ($aggregate >= 75) {
        return 'First Division';
    } elseif ($aggregate >= 50) {
        return 'Second Division';
    } elseif ($aggregate >= 35) {
        return 'Third Division';
    } else {
        return 'Fail';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Sheet</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
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
        .summary {
            margin-top: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>General Sheet</h1>

        <form method="POST" action="">
            <label for="school">Select School:</label>
            <select id="school" name="school_id">
                <option value="0">-- Select School --</option>
                <?php foreach ($schools as $school): ?>
                    <option value="<?php echo htmlspecialchars($school['id']); ?>"><?php echo htmlspecialchars($school['school_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="submit" value="Generate Report">
        </form>

        <?php if (isset($error_message)): ?>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        <?php elseif (!empty($candidates)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Index No</th>
                        <th>Candidate Name</th>
                        <th>Subject Code</th>
                        <th>Mark</th>
                        <th>Grade</th>
                        <th>Aggregate</th>
                        <th>Division</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidates as $index_no => $data): ?>
                        <?php foreach ($data['marks'] as $subject_code => $mark): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($index_no); ?></td>
                                <td><?php echo htmlspecialchars($data['name']); ?></td>
                                <td><?php echo htmlspecialchars($subject_code); ?></td>
                                <td><?php echo htmlspecialchars($mark); ?></td>
                                <td><?php echo htmlspecialchars($data['grades'][$subject_code]); ?></td>
                                <td><?php echo htmlspecialchars($data['aggregate']); ?></td>
                                <td><?php echo htmlspecialchars($data['division']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
