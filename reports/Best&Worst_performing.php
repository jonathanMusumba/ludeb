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

// Fetch total number of candidates registered
// Fetch total number of candidates registered
$query_total_candidates = "
    SELECT COUNT(DISTINCT id) AS total_candidates
    FROM candidates;
";

$result_total_candidates = $conn->query($query_total_candidates);
if (!$result_total_candidates) {
    die("Query failed: " . $conn->error);
}
$total_candidates = $result_total_candidates->fetch_assoc()['total_candidates'];


// Fetch best performing schools
$query_best_schools = "
    SELECT 
        s.CenterNo,
        s.School_Name,
        COUNT(DISTINCT c.id) AS Number_of_Candidates,
        COUNT(DISTINCT CASE WHEN r.division = '1' THEN c.id END) AS Division_1_Count,
        COUNT(DISTINCT CASE WHEN r.division = '2' THEN c.id END) AS Division_2_Count,
        COUNT(DISTINCT CASE WHEN r.division IN ('1', '2', '3', '4') THEN c.id END) AS Passed,
        COUNT(DISTINCT CASE WHEN r.division IN ('U', 'X') THEN c.id END) AS Failed,
        (COUNT(DISTINCT CASE WHEN r.division IN ('1', '2', '3', '4') THEN c.id END) / COUNT(DISTINCT c.id) * 100) AS Percentage_Pass,
        (COUNT(DISTINCT CASE WHEN r.division IN ('U', 'X') THEN c.id END) / COUNT(DISTINCT c.id) * 100) AS Percentage_Fail
    FROM 
        results r
    JOIN 
        candidates c ON r.candidate_id = c.id
    JOIN 
        schools s ON c.school_id = s.id
    WHERE 
        r.division IN ('1', '2', '3', '4', 'U', 'X')
        AND s.ResultsStatus = 'Declared'
    GROUP BY 
        s.CenterNo, s.School_Name
    HAVING 
        Percentage_Pass > 50
    ORDER BY 
        Percentage_Pass DESC, 
        Division_1_Count DESC,
        Division_2_Count DESC,
        Number_of_Candidates DESC
    LIMIT 20;
";

$result_best_schools = $conn->query($query_best_schools);
if (!$result_best_schools) {
    die("Query failed: " . $conn->error);
}


/// Fetch worst performing schools
$query_worst_schools = "
    SELECT 
        s.CenterNo,
        s.School_Name,
        COUNT(DISTINCT c.id) AS Number_of_Candidates,
        COUNT(DISTINCT CASE WHEN r.division IN ('U', 'X') THEN c.id END) AS Failed,
        COUNT(DISTINCT CASE WHEN r.division IN ('1', '2', '3', '4') THEN c.id END) AS Passed,
        (COUNT(DISTINCT CASE WHEN r.division IN ('U', 'X') THEN c.id END) / COUNT(DISTINCT c.id) * 100) AS Percentage_Fail,
        (COUNT(DISTINCT CASE WHEN r.division IN ('1', '2', '3', '4') THEN c.id END) / COUNT(DISTINCT c.id) * 100) AS Percentage_Pass
    FROM 
        results r
    JOIN 
        candidates c ON r.candidate_id = c.id
    JOIN 
        schools s ON c.school_id = s.id
    WHERE 
        r.division IN ('1', '2', '3', '4', 'U', 'X')
        AND s.ResultsStatus = 'Declared'
    GROUP BY 
        s.CenterNo, s.School_Name
    HAVING 
        Percentage_Fail > 50
    ORDER BY 
        Percentage_Fail DESC, Number_of_Candidates DESC
    LIMIT 20;
";

$result_worst_schools = $conn->query($query_worst_schools);
if (!$result_worst_schools) {
die("Query failed: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Best and Worst Performing Schools</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">Best Performing Schools</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Center No</th>
                <th>School Name</th>
                <th>Candidates</th>
                <th>Passed</th>
                <th>Failed</th>
                <th>%age Pass</th>
                <th>%age Fail</th>
                <th>Variation</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result_best_schools->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['CenterNo']); ?></td>
                <td><?php echo htmlspecialchars($row['School_Name']); ?></td>
                <td><?php echo htmlspecialchars($row['Number_of_Candidates']); ?></td>
                <td><?php echo htmlspecialchars($row['Passed']); ?></td>
                <td><?php echo htmlspecialchars($row['Failed']); ?></td>
                <td><?php echo number_format($row['Percentage_Pass'], 1); ?>%</td>
                <td><?php echo number_format($row['Percentage_Fail'], 1); ?>%</td>
                <td>
                    <?php
                    $variation = $row['Passed'] - $row['Failed'];
                    if ($variation > 0) {
                        echo '<span style="color: green;">↑</span>';
                    } elseif ($variation < 0) {
                        echo '<span style="color: red;">↓</span>';
                    } else {
                        echo '<span style="color: orange;">→</span>';
                    }
                    ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <h2 class="mt-5 mb-4">Worst Performing Schools</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Center No</th>
                <th>School Name</th>
                <th>Total Candidates</th>
                <th>Passed</th>
                <th>Failed</th>
                <th>Percentage Fail</th>
                <th>Percentage Pass</th>
                <th>Variation</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result_worst_schools->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['CenterNo']); ?></td>
                <td><?php echo htmlspecialchars($row['School_Name']); ?></td>
                <td><?php echo htmlspecialchars($row['Number_of_Candidates']); ?></td>
                <td><?php echo htmlspecialchars($row['Passed']); ?></td>
                <td><?php echo htmlspecialchars($row['Failed']); ?></td>
                <td><?php echo number_format($row['Percentage_Fail'], 1); ?>%</td>
                <td><?php echo number_format($row['Percentage_Pass'], 1); ?>%</td>
                <td>
                    <?php
                    $variation = $row['Failed'] - $row['Passed'];
                    if ($variation > 0) {
                        echo '<span style="color: red;">↓</span>';
                    } elseif ($variation < 0) {
                        echo '<span style="color: green;">↑</span>';
                    } else {
                        echo '<span style="color: orange;">→</span>';
                    }
                    ?>
                </td>
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
