<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate aggregates and other necessary details
function calculateAggregates($candidate_id) {
    global $conn;

    $marks_query = "SELECT m.mark, g.score, s.Name 
                    FROM marks m
                    JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to
                    JOIN subjects s ON m.subject_id = s.id
                    WHERE m.candidate_id = ?";

    $stmt = $conn->prepare($marks_query);
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $total_aggregate = 0;
    $english_score = null;
    $math_score = null;
    $subject_count = 0;
    $has_absence = false;

    while ($row = $result->fetch_assoc()) {
        if ($row['mark'] === -1) {
            $has_absence = true;
            break; // No need to process further if any mark is -1
        } else {
            $total_aggregate += $row['score'];
            if ($row['Name'] == 'English') {
                $english_score = $row['score'];
            }
            if ($row['Name'] == 'Mathematics') {
                $math_score = $row['score'];
            }
            $subject_count++;
        }
    }

    // Set total aggregate to 0 if there's any absence
    if ($has_absence) {
        $total_aggregate = 0;
    }

    return [
        'total_aggregate' => $total_aggregate,
        'english_score' => $english_score,
        'math_score' => $math_score,
        'subject_count' => $subject_count,
        'has_absence' => $has_absence
    ];
}

// Function to determine division based on aggregates and scores
function calculateDivision($candidate_id) {
    global $conn;

    $aggregates = calculateAggregates($candidate_id);

    $total_aggregate = $aggregates['total_aggregate'];
    $english_score = $aggregates['english_score'];
    $math_score = $aggregates['math_score'];
    $subject_count = $aggregates['subject_count'];
    $has_absence = $aggregates['has_absence'];

    // Division determination logic
if ($has_absence || $subject_count < 4) {
    return 'X'; // Absence in any subject or less than 4 subjects
} elseif ($total_aggregate >= 4 && $total_aggregate <= 12) {
    if ($english_score < 7 && $math_score <= 8) {
        return '1';
    } elseif ($english_score == 8 || $math_score == 9) {
        return '2';
    } elseif ($english_score == 9) {
        return '3';
    }
} elseif ($total_aggregate >= 13 && $total_aggregate <= 24) {
    return ($english_score <= 8) ? '2' : '3';
} elseif ($total_aggregate >= 25 && $total_aggregate <= 28) {
    return ($english_score <= 8) ? '3' : '4';
} elseif ($total_aggregate == 29) {
    if ($english_score <= 6) {
        return '3';
    } else {
        return '4';
    }
} elseif ($total_aggregate >= 30 && $total_aggregate <= 32) {
    return '4';
} elseif ($total_aggregate == 33) {
    if ($english_score < 8 && $math_score < 9) {
        return '4';
    } else {
        return 'U';
    }
} elseif ($total_aggregate > 33) {
    return 'U';
}

return 'X'; // Default to 'X' if none of the conditions are met
}

// Fetch exam year and board name
$current_year = date('Y');
$board_name = "MOCK EXAMINATIONS";

// Fetch schools for selection
$schools = null;
if (!isset($_GET['school_id']) && !isset($_GET['view_school'])) {
    $schools = $conn->query("SELECT id, school_Name FROM schools");
    if ($schools->num_rows == 0) {
        die("No schools found.");
    }
}

// Store selected school name
$selected_school_name = '';
$school_id = null;
if (isset($_GET['school_id']) || isset($_GET['view_school'])) {
    $school_id = intval($_GET['school_id'] ?? $_GET['view_school']);
    $school_query = $conn->query("SELECT school_Name FROM schools WHERE id = $school_id");
    if ($school_query->num_rows > 0) {
        $selected_school_name = $school_query->fetch_assoc()['school_Name'];
    }
}

// Fetch subjects for displaying in the table header
$subjects = $conn->query("SELECT id, Name, Code FROM subjects");

// Fetch candidates and their marks based on the selected school
$candidates = [];
if (isset($school_id)) {
    $candidates_result = $conn->query("SELECT id, Candidate_Name, IndexNo FROM candidates WHERE school_id = $school_id");
    
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[$row['id']] = [
            'id' => $row['id'],
            'name' => $row['Candidate_Name'],
            'index_number' => $row['IndexNo'],
            'marks' => [],
            'grades' => [],
            'aggregate' => 0,
            'subjectCount' => 0,
            'division' => ''
        ];
    }

    // Fetch marks for the candidates
    $marks_result = $conn->query("SELECT candidate_id, subject_id, mark FROM marks WHERE school_id = $school_id");

    while ($row = $marks_result->fetch_assoc()) {
        $candidate_id = $row['candidate_id'];
        $subject_id = $row['subject_id'];
        $mark = $row['mark'];

        // Determine the grade based on the mark
        $grade_query = $conn->query("SELECT grade, score FROM grading WHERE $mark BETWEEN range_from AND range_to");
        $grade_data = $grade_query->fetch_assoc();
        $grade = $grade_data['grade'] ?? 'NA';
        $score = $grade_data['score'] ?? 0;

        $candidates[$candidate_id]['marks'][$subject_id] = $mark;
        $candidates[$candidate_id]['grades'][$subject_id] = $grade;
        $candidates[$candidate_id]['aggregate'] += $score;
        $candidates[$candidate_id]['subjectCount']++;
    }

    // Apply the functions to each candidate
    foreach ($candidates as &$candidate) {
        // Check if 'id' key exists in the candidate array
        if (!isset($candidate['id'])) {
            continue; // Skip this iteration if ID is not set
        }

        $division = calculateDivision($candidate['id']);
        $candidate['division'] = $division;
    }
}
?>





<!DOCTYPE html>
<html>
<head>
    <title>Retrieve Marks</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        th {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="home.php">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Retrieve Marks</li>
    </ol>
</nav>

<div class="container mt-5">
    <?php if (!isset($_GET['view_school'])): ?>
        <form method="GET" action="">
            <div class="form-group">
                <label for="school_name">Select School:</label>
                <input type="text" id="school_name" name="school_name" class="form-control" value="<?= isset($_GET['school_name']) ? htmlspecialchars($_GET['school_name']) : '' ?>" required>
                <input type="hidden" id="school_id" name="school_id" value="<?= isset($_GET['school_id']) ? htmlspecialchars($_GET['school_id']) : '' ?>">
            </div>
        </form>
    <?php endif; ?>

    <?php if (!empty($candidates)): ?>
        <h1 class="mb-4"><?= htmlspecialchars($board_name) ?>  <?= htmlspecialchars($current_year) ?></h1>
        <h3>SCHOOL: <?= htmlspecialchars($selected_school_name) ?></h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Index Number</th>
                    <th>Candidate Name</th>
                    <?php
                    // Reset subject fetch
                    $subjects->data_seek(0);
                    while($subject = $subjects->fetch_assoc()): ?>
                        <th><?= htmlspecialchars($subject['Code']) ?> (Grade)</th>
                    <?php endwhile; ?>
                    <th>Aggregates</th>
                    <th>Division</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $candidate): ?>
                    <tr>
                        <td><?= htmlspecialchars($candidate['index_number']) ?></td>
                        <td><?= htmlspecialchars($candidate['name']) ?></td>
                        <?php
                        // Reset subject fetch
                        $subjects->data_seek(0);
                        while($subject = $subjects->fetch_assoc()):
                            $subject_id = $subject['id'];
                            $mark = $candidate['marks'][$subject_id] ?? 'NA';
                            $grade = $candidate['grades'][$subject_id] ?? 'NA';
                        ?>
                            <td><?= htmlspecialchars($mark) ?> (<?= htmlspecialchars($grade) ?>)</td>
                        <?php endwhile; ?>
                        <td><?= htmlspecialchars($candidate['aggregate']) ?></td>
                        <td><?= htmlspecialchars($candidate['division']) ?></td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No candidates or marks found for the selected school.</p>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script>
$(document).ready(function() {
    $("#school_name").autocomplete({
        source: "get_school.php",
        minLength: 2,
        select: function(event, ui) {
            $("#school_name").val(ui.item.value);
            $("#school_id").val(ui.item.id);
            $(this.form).submit();
        }
    });
});
</script>

</body>
</html>

<?php
$conn->close();
?>
