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

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function fetchData($conn) {
    // Fetch schools with at least one candidate with marks
    $school_query = "
        SELECT DISTINCT s.id AS school_id, s.CenterNo, s.School_Name
        FROM schools s
        JOIN candidates c ON s.id = c.school_id
        LEFT JOIN marks m ON c.id = m.candidate_id
        WHERE m.mark IS NOT NULL
    ";

    $schools_result = $conn->query($school_query);

    $output = '';

    if ($schools_result->num_rows > 0) {
        while ($school = $schools_result->fetch_assoc()) {
            $school_id = $school['school_id'];

            $output .= "<h2>School: " . htmlspecialchars($school['School_Name']) . " (CenterNo: " . htmlspecialchars($school['CenterNo']) . ")</h2>";

            // Fetch candidates with missing or zero marks in the current school
            $candidates_query = "
                SELECT c.id AS candidate_id, c.IndexNo, c.Candidate_Name, c.Gender, c.exam_year, e.Exam_year AS exam_year_name
                FROM candidates c
                LEFT JOIN marks m ON c.id = m.candidate_id AND m.mark >= 1
                LEFT JOIN exam_years e ON c.exam_year = e.id
                WHERE c.school_id = $school_id
            ";

            $candidates_result = $conn->query($candidates_query);

            if ($candidates_result->num_rows > 0) {
                $output .= "<div class='table-responsive'>";
                $output .= "<table class='table table-bordered table-striped'>";
                $output .= "<thead class='thead-dark'>
                            <tr>
                                <th>IndexNo</th>
                                <th>Candidate Name</th>
                                <th>Gender</th>
                                <th>Exam Year</th>
                                <th>Missing Subjects</th>
                            </tr>
                          </thead>";
                $output .= "<tbody>";

                while ($candidate = $candidates_result->fetch_assoc()) {
                    $candidate_id = $candidate['candidate_id'];

                    // Determine missing or zero marks for the current candidate
                    $missing_subjects_query = "
                        SELECT s.Code AS subject_code
                        FROM subjects s
                        LEFT JOIN marks m ON s.id = m.subject_id AND m.candidate_id = $candidate_id
                        WHERE m.mark IS NULL OR m.mark = 0
                    ";

                    $missing_subjects_result = $conn->query($missing_subjects_query);
                    $missing_subjects = [];

                    while ($subject = $missing_subjects_result->fetch_assoc()) {
                        $missing_subjects[] = $subject['subject_code'];
                    }

                    $missing_subjects_str = implode(", ", $missing_subjects);

                    $output .= "<tr>
                                <td>" . htmlspecialchars($candidate['IndexNo']) . "</td>
                                <td>" . htmlspecialchars($candidate['Candidate_Name']) . "</td>
                                <td>" . htmlspecialchars($candidate['Gender']) . "</td>
                                <td>" . htmlspecialchars($candidate['exam_year_name']) . "</td>
                                <td>" . htmlspecialchars($missing_subjects_str) . "</td>
                              </tr>";
                }

                $output .= "</tbody>";
                $output .= "</table>";
                $output .= "</div>";
            } else {
                $output .= "<p>No candidates with missing marks found for this school.</p>";
            }
        }
    } else {
        $output .= "<p>No schools with marks found.</p>";
    }

    return $output;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic School Data</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h1>Dynamic School Data</h1>
        <div id="data-container">
            <!-- Initial data load -->
            <?php echo fetchData($conn); ?>
        </div>
    </div>

    <script>
        function fetchData() {
            $.ajax({
                url: 'missing_marks.php', // This will call the same file to get updated data
                method: 'GET',
                success: function(data) {
                    $('#data-container').html($(data).find('#data-container').html());
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching data:', error);
                }
            });
        }

        // Fetch data every 30 seconds
        setInterval(fetchData, 30000);

        // Initial data load
        fetchData();
    </script>
</body>
</html>
