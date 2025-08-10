<?php
session_start();
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LUDEB";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get parameters from query
$username = isset($_GET['username']) ? $conn->real_escape_string($_GET['username']) : '';
$subject_code = isset($_GET['subject_code']) ? $conn->real_escape_string($_GET['subject_code']) : '';

if (empty($username) || empty($subject_code)) {
    die("Missing parameters.");
}

// SQL query for detailed schools list
$sql = "
    SELECT
        s.CenterNo,
        s.School_Name AS school_name,
        COUNT(m.id) AS number_of_entries
    FROM
        marks m
    JOIN
        schools s ON m.school_id = s.id
    JOIN
        subjects sub ON m.subject_id = sub.id
    JOIN
        system_users u ON m.submitted_by = u.id
    WHERE
        m.mark >= 1
        AND u.username = '$username'
        AND sub.Code = '$subject_code'
    GROUP BY
        s.CenterNo, s.School_Name
";

$school_details_result = $conn->query($sql);

if (!$school_details_result) {
    die("Error in query: " . $conn->error);
}
?>

<section class="mt-5">
    <h3 class="text-center">Schools with Marks Greater Than or Equal to 1</h3>
    <p class="text-muted text-center">(User: <?php echo htmlspecialchars($username); ?>, Subject: <?php echo htmlspecialchars($subject_code); ?>)</p>
    
    <div class="table-responsive">
        <table class="table table-striped table-hover table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th scope="col">Center No</th>
                    <th scope="col">School Name</th>
                    <th scope="col">Number of Entries (≥ 1)</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $school_details_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['CenterNo']); ?></td>
                        <td><?php echo htmlspecialchars($row['school_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['number_of_entries']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>


<?php
// Close the database connection
$conn->close();
?>
