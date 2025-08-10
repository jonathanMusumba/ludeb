<?php
$servername = "localhost";
$username = "root";
$password = ""; // Update with your database password
$dbname = "LUDEB";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Fetch total number of schools
$totalQuery = "SELECT COUNT(*) AS total FROM schools";
$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();
$total_schools = $totalRow['total'];

// Fetch schools with pagination
$query = "SELECT * FROM schools LIMIT $offset, $per_page";
$result = $conn->query($query);

$schools = [];
while ($row = $result->fetch_assoc()) {
    $schoolId = $row['id'];

    // Fetch subjects with counts of marks > 0
    $subjectQuery = "
        SELECT subjects.code, COUNT(marks.id) AS count
        FROM subjects
        LEFT JOIN marks ON subjects.id = marks.subject_id
        WHERE marks.school_id = $schoolId AND marks.mark > 0
        GROUP BY subjects.code
    ";
    $subjectResult = $conn->query($subjectQuery);
    $subjects = [];
    while ($subjectRow = $subjectResult->fetch_assoc()) {
        $subjects[] = $subjectRow;
    }

    // Fetch total candidates
    $totalCandidatesQuery = "SELECT COUNT(DISTINCT candidate_id) AS total FROM marks WHERE school_id = $schoolId";
    $totalCandidatesResult = $conn->query($totalCandidatesQuery);
    $totalCandidatesRow = $totalCandidatesResult->fetch_assoc();
    $total_candidates = $totalCandidatesRow['total'];

    $schools[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'code' => $row['code'],
        'subjects' => $subjects,
        'total_candidates' => $total_candidates,
    ];
}

$conn->close();

$response = [
    'page' => $page,
    'total_pages' => ceil($total_schools / $per_page),
    'schools' => $schools,
];

header('Content-Type: application/json');
echo json_encode($response);
?>
