<?php
session_start();
$user_id = $_SESSION['user_id']; 
// Check if the user is logged in and has a role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login page if not logged in
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

// Fetch exam year and board name
$current_year = date('Y');
$board_name = "MOCK EXAMINATIONS";

// Fetch schools for selection
$schools = $conn->query("SELECT id, school_Name FROM schools");
if ($schools->num_rows == 0) {
    die("No schools found.");
}

// Store selected school name
$selected_school_name = '';
if (isset($_GET['school_id'])) {
    $school_id = intval($_GET['school_id']);
    $school_query = $conn->query("SELECT school_Name FROM schools WHERE id = $school_id");
    if ($school_query->num_rows > 0) {
        $selected_school_name = $school_query->fetch_assoc()['school_Name'];
    }
}

// Fetch subjects for displaying in the table header
$subjects = $conn->query("SELECT id, Name, Code FROM subjects");

// Fetch candidates and their marks based on the selected school
$candidates = [];
if (isset($_GET['school_id'])) {
    $school_id = intval($_GET['school_id']);
    $candidates_result = $conn->query("SELECT id, Candidate_Name, IndexNo FROM candidates WHERE school_id = $school_id");
    
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[$row['id']] = [
            'name' => $row['Candidate_Name'],
            'index_number' => $row['IndexNo'],
            'marks' => []
        ];
    }

    // Fetch marks for the candidates
    $marks_result = $conn->query("SELECT candidate_id, subject_id, mark FROM marks WHERE school_id = $school_id");

    while ($row = $marks_result->fetch_assoc()) {
        $candidates[$row['candidate_id']]['marks'][$row['subject_id']] = $row['mark'];
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
    <form method="GET" action="">
        <div class="form-group">
            <label for="school_name">Select School:</label>
            <input type="text" id="school_name" name="school_name" class="form-control" value="<?= isset($_GET['school_name']) ? htmlspecialchars($_GET['school_name']) : '' ?>" required>
            <input type="hidden" id="school_id" name="school_id" value="<?= isset($_GET['school_id']) ? htmlspecialchars($_GET['school_id']) : '' ?>">
        </div>
    </form>

    <?php if (!empty($candidates)): ?>
        <h1 class="mb-4"><?= $board_name ?> - Exam Year: <?= $current_year ?></h1>
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
                        <th><?= htmlspecialchars($subject['Code']) ?></th>
                    <?php endwhile; ?>
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
                            $mark = isset($candidate['marks'][$subject['id']]) ? $candidate['marks'][$subject['id']] : 'NA';
                        ?>
                            <td><?= htmlspecialchars($mark) ?></td>
                        <?php endwhile; ?>
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
