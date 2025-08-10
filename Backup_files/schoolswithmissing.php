<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define pagination variables
$items_per_page = 10; // Number of schools per page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

// Fetch all subjects
$subjects_query = "SELECT id, Code, Name FROM Subjects";
$subjects_result = $conn->query($subjects_query);
$all_subjects = [];
while ($row = $subjects_result->fetch_assoc()) {
    $all_subjects[$row['id']] = $row;
}

// Count total number of schools
$total_schools_query = "SELECT COUNT(*) AS total FROM Schools";
$total_schools_result = $conn->query($total_schools_query);
$total_schools = $total_schools_result->fetch_assoc()['total'];
$total_pages = ceil($total_schools / $items_per_page);

// Fetch paginated schools
$schools_query = "SELECT id, School_Name FROM Schools LIMIT $offset, $items_per_page";
$schools_result = $conn->query($schools_query);

$schools_data = [];
while ($school_row = $schools_result->fetch_assoc()) {
    $school_id = $school_row['id'];
    $school_name = $school_row['School_Name'];

    // Get subjects with recorded marks for each school
    $marks_query = "SELECT s.id AS subject_id, s.Code, s.Name, COUNT(m.id) AS count
                    FROM Subjects s
                    LEFT JOIN Marks m ON s.id = m.subject_id AND m.school_id = $school_id
                    GROUP BY s.id";
    $marks_result = $conn->query($marks_query);

    $subjects_with_marks = [];
    $missing_subjects = [];
    while ($subject_row = $marks_result->fetch_assoc()) {
        if ($subject_row['count'] > 0) {
            $subjects_with_marks[$subject_row['subject_id']] = $subject_row;
        } else {
            $missing_subjects[$subject_row['subject_id']] = $subject_row;
        }
    }

    // Include subjects that have not been recorded in the missing subjects list
    foreach ($all_subjects as $subject_id => $subject) {
        if (!isset($subjects_with_marks[$subject_id])) {
            $missing_subjects[$subject_id] = $subject;
        }
    }

    $schools_data[$school_id] = [
        'school_name' => $school_name,
        'subjects_with_marks' => $subjects_with_marks,
        'missing_subjects' => $missing_subjects
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Subjects Report</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">School Subjects Report</h1>

        <?php foreach ($schools_data as $school_id => $school_data): ?>
            <h3><?php echo htmlspecialchars($school_data['school_name']); ?></h3>

            <h4>Subjects with Entered Marks</h4>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Number of Candidates with Marks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($school_data['subjects_with_marks'] as $subject): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($subject['Code']); ?></td>
                            <td><?php echo htmlspecialchars($subject['Name']); ?></td>
                            <td><?php echo htmlspecialchars($subject['count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h4>Missing Subjects</h4>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($school_data['missing_subjects'] as $subject): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($subject['Code']); ?></td>
                            <td><?php echo htmlspecialchars($subject['Name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr>
        <?php endforeach; ?>

        <!-- Pagination controls -->
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>

    </div>
</body>
</html>
