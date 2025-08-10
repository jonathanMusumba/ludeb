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

$query_subcounty_performance = "
    WITH CandidateDivision AS (
        SELECT 
            c.id AS candidate_id,
            sc.subcounty,
            MIN(r.division) AS division -- Assuming the best division per candidate is the minimum division value
        FROM 
            results r
        JOIN 
            candidates c ON r.candidate_id = c.id
        JOIN 
            schools s ON c.school_id = s.id
        JOIN 
            sub_counties sc ON s.Sub_County = sc.id
        GROUP BY 
            c.id, sc.subcounty
    )
    SELECT 
        sc.subcounty,
        SUM(CASE WHEN cd.division = '1' THEN 1 ELSE 0 END) AS Division_1,
        SUM(CASE WHEN cd.division = '2' THEN 1 ELSE 0 END) AS Division_2,
        SUM(CASE WHEN cd.division = '3' THEN 1 ELSE 0 END) AS Division_3,
        SUM(CASE WHEN cd.division = '4' THEN 1 ELSE 0 END) AS Division_4,
        SUM(CASE WHEN cd.division = 'U' THEN 1 ELSE 0 END) AS Division_U,
        SUM(CASE WHEN cd.division = 'X' THEN 1 ELSE 0 END) AS Division_X,
        COUNT(DISTINCT cd.candidate_id) AS Total_Registered,
        COUNT(DISTINCT CASE WHEN cd.division IN ('1', '2', '3', '4', 'U', 'X') THEN cd.candidate_id ELSE NULL END) AS Total_Sat,
        ROUND(
            (COUNT(DISTINCT CASE WHEN cd.division IN ('1', '2', '3', '4') THEN cd.candidate_id ELSE NULL END) / COUNT(DISTINCT cd.candidate_id)) * 100, 
            1
        ) AS Percentage_Pass,
        ROUND(
            (COUNT(DISTINCT CASE WHEN cd.division = 'U' OR cd.division = 'X' THEN cd.candidate_id ELSE NULL END) / COUNT(DISTINCT cd.candidate_id)) * 100, 
            1
        ) AS Percentage_Fail
    FROM 
        CandidateDivision cd
    JOIN 
        sub_counties sc ON cd.subcounty = sc.subcounty
    GROUP BY 
        sc.subcounty
    ORDER BY 
        Percentage_Pass DESC;
";

$result_subcounty_performance = $conn->query($query_subcounty_performance);
if (!$result_subcounty_performance) {
    die("Query failed: " . $conn->error);
}
$query_overall_performance = "
    SELECT 
        s.CenterNo,
        s.SchoolName,
        s.SchoolType AS Funding,
        CONCAT(
            'Division 1: ', SUM(CASE WHEN r.division = '1' THEN 1 ELSE 0 END), ', ',
            'Division 2: ', SUM(CASE WHEN r.division = '2' THEN 1 ELSE 0 END), ', ',
            'Division 3: ', SUM(CASE WHEN r.division = '3' THEN 1 ELSE 0 END), ', ',
            'Division 4: ', SUM(CASE WHEN r.division = '4' THEN 1 ELSE 0 END), ', ',
            'Division U: ', SUM(CASE WHEN r.division = 'U' THEN 1 ELSE 0 END), ', ',
            'Division X: ', SUM(CASE WHEN r.division = 'X' THEN 1 ELSE 0 END)
        ) AS Divisions,
        ROUND(
            (COUNT(DISTINCT CASE WHEN r.division IN ('1', '2', '3', '4') THEN c.id ELSE NULL END) / COUNT(DISTINCT c.id)) * 100, 
            2
        ) AS Percentage_Pass,
        ROUND(
            (COUNT(DISTINCT CASE WHEN r.division = 'U' THEN c.id ELSE NULL END) / COUNT(DISTINCT c.id)) * 100, 
            2
        ) AS Percentage_Fail,
        CASE 
            WHEN ROUND(
                    (COUNT(DISTINCT CASE WHEN r.division IN ('1', '2', '3', '4') THEN c.id ELSE NULL END) / COUNT(DISTINCT c.id)) * 100, 
                    2
                ) >= 50 THEN '↑'
            ELSE '↓'
        END AS Variance
    FROM 
        results r
    JOIN 
        candidates c ON r.candidate_id = c.id
    JOIN 
        schools s ON c.school_id = s.id
    GROUP BY 
        s.CenterNo, s.SchoolName, s.SchoolType
    ORDER BY 
        Percentage_Pass DESC;
";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sub-County Performance Summary</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">Sub-County Performance Summary</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Sub-County Name</th>
                <th>Div 1</th>
                <th>Div 2</th>
                <th>Div 3</th>
                <th>Div 4</th>
                <th>Div U</th>
                <th>Registered</th>
                <th>Sat</th>
                <th>%age Pass</th>
                <th>%age Fail</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result_subcounty_performance->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['subcounty']); ?></td>
                <td><?php echo htmlspecialchars($row['Division_1']); ?></td>
                <td><?php echo htmlspecialchars($row['Division_2']); ?></td>
                <td><?php echo htmlspecialchars($row['Division_3']); ?></td>
                <td><?php echo htmlspecialchars($row['Division_4']); ?></td>
                <td><?php echo htmlspecialchars($row['Division_U']); ?></td>
                <td><?php echo htmlspecialchars($row['Total_Registered']); ?></td>
                <td><?php echo htmlspecialchars($row['Total_Sat']); ?></td>
                <td><?php echo htmlspecialchars($row['Percentage_Pass']); ?></td>
                <td><?php echo htmlspecialchars($row['Percentage_Fail']); ?></td>
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
