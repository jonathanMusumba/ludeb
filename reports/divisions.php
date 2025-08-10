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

// Fetch total number of candidates
$total_candidates_query = "SELECT COUNT(DISTINCT id) AS total FROM candidates";
$total_candidates_result = $conn->query($total_candidates_query);
if (!$total_candidates_result) {
    die("Query failed: " . $conn->error);
}
$total_candidates_row = $total_candidates_result->fetch_assoc();
$total_candidates = $total_candidates_row['total'];

// Fetch division summaries
$query_division_summary = "
    SELECT 
        r.division, 
        COUNT(DISTINCT c.id) AS unique_candidates,
        ROUND(
            (COUNT(DISTINCT c.id) / $total_candidates) * 100, 
            1
        ) AS percentage
    FROM 
        results r
    JOIN 
        candidates c ON r.candidate_id = c.id
    GROUP BY 
        r.division;
";

$result_division_summary = $conn->query($query_division_summary);
if (!$result_division_summary) {
    die("Query failed: " . $conn->error);
}

// Fetch division summaries by gender
$query_division_by_gender = "
    SELECT 
        r.division,
        COUNT(DISTINCT CASE WHEN c.gender = 'M' THEN c.id END) AS male_count,
        COUNT(DISTINCT CASE WHEN c.gender = 'F' THEN c.id END) AS female_count,
        COUNT(DISTINCT c.id) AS total_count
    FROM 
        results r
    JOIN 
        candidates c ON r.candidate_id = c.id
    GROUP BY 
        r.division;
";

$result_division_by_gender = $conn->query($query_division_by_gender);
if (!$result_division_by_gender) {
    die("Query failed: " . $conn->error);
}

// Fetch schools with first grades
$query_schools_first_grades = "
    SELECT 
        s.CenterNo,
        s.School_Name,
        COUNT(DISTINCT c.id) AS Number_of_First_Grades
    FROM 
        results r
    JOIN 
        candidates c ON r.candidate_id = c.id
    JOIN 
        schools s ON c.school_id = s.id
    WHERE 
        r.division = '1'
    GROUP BY 
        s.CenterNo, s.School_Name
    ORDER BY 
        Number_of_First_Grades DESC;
";

$result_schools_first_grades = $conn->query($query_schools_first_grades);
if (!$result_schools_first_grades) {
    die("Query failed: " . $conn->error);
}
// Fetch total number of registered candidates by gender
$total_registered_query = "
    SELECT 
        gender,
        COUNT(DISTINCT id) AS total_registered
    FROM 
        candidates
    GROUP BY 
        gender;
";
$total_registered_result = $conn->query($total_registered_query);
if (!$total_registered_result) {
    die("Query failed: " . $conn->error);
}

// Fetch candidates who sat for the exam by gender
$query_total_sat = "
    SELECT 
        c.gender,
        COUNT(DISTINCT c.id) AS total_sat
    FROM 
        candidates c
    JOIN 
        results r ON c.id = r.candidate_id
    WHERE 
        r.division IN ('1', '2', '3', '4', 'U')
    GROUP BY 
        c.gender;
";
$result_total_sat = $conn->query($query_total_sat);
if (!$result_total_sat) {
    die("Query failed: " . $conn->error);
}

// Fetch absentees by gender
// Fetch absentees by gender
$query_absentees = "
    SELECT
        c.gender,
        COUNT(DISTINCT c.id) AS absentees
    FROM
        candidates c
    JOIN
        results r ON c.id = r.candidate_id
    WHERE
        r.division = 'X'
    GROUP BY
        c.gender;
";
$result_absentees = $conn->query($query_absentees);
if (!$result_absentees) {
    die("Query failed: " . $conn->error);
}

$total_registered = [];
$total_sat_by_gender = [];
$absentees = [];

// Store registered counts
while ($row = $total_registered_result->fetch_assoc()) {
    $total_registered[$row['gender']] = $row['total_registered'];
}

// Store sat counts
while ($row = $result_total_sat->fetch_assoc()) {
    $total_sat_by_gender[$row['gender']] = $row['total_sat'];
}

// Store absentees counts
while ($row = $result_absentees->fetch_assoc()) {
    $absentees[$row['gender']] = $row['absentees'];
}

// Calculate totals
$total_registered_all = array_sum($total_registered);
$total_sat_all = array_sum($total_sat_by_gender);
$absentees_all = array_sum($absentees);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Division Summaries and Schools with First Grades</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">Division Summaries</h2>
    <h2 class="mb-4">Candidates Summary by Gender</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Gender</th>
                <th>Total Registered</th>
                <th>Total Sat</th>
                <th>Absentees</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Display data for each gender
            foreach (['M' => 'Male', 'F' => 'Female'] as $gender_code => $gender_label) {
                $total_sat = isset($total_sat_by_gender[$gender_code]) ? $total_sat_by_gender[$gender_code] : 0;
                $absentees_count = isset($absentees[$gender_code]) ? $absentees[$gender_code] : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($gender_label); ?></td>
                    <td><?php echo htmlspecialchars(isset($total_registered[$gender_code]) ? $total_registered[$gender_code] : 0); ?></td>
                    <td><?php echo htmlspecialchars($total_sat); ?></td>
                    <td><?php echo htmlspecialchars($absentees_count); ?></td>
                </tr>
                <?php
            }
            ?>
        </tbody>
        <tfoot>
            <tr>
                <td><strong>Total</strong></td>
                <td><strong><?php echo htmlspecialchars($total_registered_all); ?></strong></td>
                <td><strong><?php echo htmlspecialchars($total_sat_all); ?></strong></td>
                <td><strong><?php echo htmlspecialchars($absentees_all); ?></strong></td>
            </tr>
        </tfoot>
    </table>
    <h3>Overall Division Summary</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Division</th>
                <th>Unique Candidates</th>
                <th>Percentage (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result_division_summary->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['division']); ?></td>
                <td><?php echo htmlspecialchars($row['unique_candidates']); ?></td>
                <td><?php echo htmlspecialchars($row['percentage']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <td><strong>Total</strong></td>
                <td><strong><?php echo htmlspecialchars($total_candidates); ?></strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    
    <!-- Division Summary by Gender -->
    <h3>Division Summary by Gender</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Division</th>
                <th>Male (M)</th>
                <th>Female (F)</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result_division_by_gender->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['division']); ?></td>
                <td><?php echo htmlspecialchars($row['male_count']); ?></td>
                <td><?php echo htmlspecialchars($row['female_count']); ?></td>
                <td><?php echo htmlspecialchars($row['total_count']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <!-- Schools with First Grades -->
    <h3>Schools with First Grades</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Center No</th>
                <th>School Name</th>
                <th>Candidates with First Grades</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result_schools_first_grades->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['CenterNo']); ?></td>
                <td><?php echo htmlspecialchars($row['School_Name']); ?></td>
                <td><?php echo htmlspecialchars($row['Number_of_First_Grades']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>

<?php
$conn->close();
?>
