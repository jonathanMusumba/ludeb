<?php
// Database connection
$host = 'localhost'; // Replace with your database host
$db = 'Ludeb'; // Replace with your database name
$user = 'root'; // Replace with your database username
$pass = ''; // Replace with your database password

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch school performance summary
    $performance_summary = $conn->query("
        SELECT 
            s.CenterNo,
            s.School_Name,
            st.type AS School_Type,
            COUNT(CASE WHEN r.division IN ('1', '2', '3', '4') THEN 1 END) AS Divisions_Count,
            ROUND(COUNT(CASE WHEN r.division IN ('1', '2', '3', '4') THEN 1 END) / COUNT(r.id) * 100, 2) AS Percentage_Pass,
            ROUND(COUNT(CASE WHEN r.division IN ('U', 'X') THEN 1 END) / COUNT(r.id) * 100, 2) AS Percentage_Fail
        FROM schools s
        LEFT JOIN results r ON s.CenterNo = r.school_id
        JOIN school_types st ON s.School_type = st.id
        GROUP BY s.CenterNo, s.School_Name, st.type
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch best performing schools
    $best_schools = $conn->query("
        SELECT 
            s.CenterNo,
            s.School_Name,
            ROUND(COUNT(CASE WHEN r.division IN ('1', '2', '3', '4') THEN 1 END) / COUNT(r.id) * 100, 1) AS Percentage_Pass
        FROM schools s
        LEFT JOIN results r ON s.CenterNo = r.school_id
        GROUP BY s.CenterNo, s.School_Name
        HAVING Percentage_Pass > 0
        ORDER BY Percentage_Pass DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch worst performing schools
    $worst_schools = $conn->query("
        SELECT 
            s.CenterNo,
            s.School_Name,
            ROUND(COUNT(CASE WHEN r.division IN ('U', 'X') THEN 1 END) / COUNT(r.id) * 100, 2) AS Percentage_Fail
        FROM schools s
        LEFT JOIN results r ON s.CenterNo = r.school_id
        GROUP BY s.CenterNo, s.School_Name
        HAVING Percentage_Fail > 0
        ORDER BY Percentage_Fail DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch performance by sub-county
    $performance_by_subcounty = $conn->query("
        SELECT 
            sc.subcounty AS Subcounty_Name,
            COUNT(CASE WHEN r.division IN ('1', '2', '3', '4') THEN 1 END) AS Divisions_Count,
            COUNT(c.id) AS Total_Registered,
            COUNT(DISTINCT r.id) AS Total_Sat,
            ROUND(COUNT(CASE WHEN r.division IN ('1', '2', '3', '4') THEN 1 END) / COUNT(DISTINCT r.id) * 100, 2) AS Percentage_Pass,
            ROUND(COUNT(CASE WHEN r.division IN ('U', 'X') THEN 1 END) / COUNT(DISTINCT r.id) * 100, 2) AS Percentage_Fail
        FROM sub_counties sc
        LEFT JOIN schools s ON sc.id = s.Sub_county
        LEFT JOIN results r ON s.CenterNo = r.school_id
        LEFT JOIN candidates c ON r.school_id = c.school_id
        GROUP BY sc.subcounty
        ORDER BY Percentage_Pass DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

   // Fetch overall performance
   $overall_performance = $conn->query("
   SELECT 
       s.CenterNo,
       s.School_Name,
       CONCAT(
           SUM(CASE WHEN r.division IN ('1', '2', '3', '4') THEN 1 ELSE 0 END),
           ' Divisions'
       ) AS Divisions,
       COUNT(c.id) AS Total_Registered,
       COUNT(DISTINCT r.id) AS Total_Sat,
       ROUND(COUNT(CASE WHEN r.division IN ('1', '2', '3', '4') THEN 1 END) / COUNT(DISTINCT r.id) * 100, 2) AS Percentage_Pass,
       ROUND(COUNT(CASE WHEN r.division IN ('U', 'X') THEN 1 END) / COUNT(DISTINCT r.id) * 100, 2) AS Percentage_Fail
   FROM schools s
   LEFT JOIN candidates c ON s.CenterNo = c.school_id
   LEFT JOIN results r ON s.CenterNo = r.school_id
   GROUP BY s.CenterNo, s.School_Name
   ORDER BY Percentage_Pass DESC
")->fetchAll(PDO::FETCH_ASSOC);


    // Fetch schools whose candidates did not sit for exams
    $no_exams_schools = $conn->query("
        SELECT 
            s.CenterNo,
            s.School_Name,
            COUNT(c.id) AS Number_of_Candidates,
            sc.subcounty AS Subcounty_Name
        FROM schools s
        LEFT JOIN candidates c ON s.CenterNo = c.school_id
        LEFT JOIN sub_counties sc ON s.Sub_county = sc.id
        WHERE s.resultsStatus IN ('Not Declared', 'Partially Declared')
        GROUP BY s.CenterNo, s.School_Name, sc.subcounty
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Performance Report</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            text-align: center;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .arrow-up {
            color: green;
        }
        .arrow-down {
            color: red;
        }
    </style>
</head>
<body>
    <h1>School Performance Summary</h1>
    <table>
        <thead>
            <tr>
                <th>Center No</th>
                <th>School Name</th>
                <th>School Type</th>
                <th>Divisions Count</th>
                <th>% Pass</th>
                <th>% Fail</th>
                <th>Variation</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($performance_summary)): ?>
                <?php foreach ($performance_summary as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['CenterNo']) ?></td>
                        <td><?= htmlspecialchars($row['School_Name']) ?></td>
                        <td><?= htmlspecialchars($row['School_Type']) ?></td>
                        <td><?= htmlspecialchars($row['Divisions_Count']) ?></td>
                        <td><?= number_format($row['Percentage_Pass'], 2) ?>%</td>
                        <td><?= number_format($row['Percentage_Fail'], 2) ?>%</td>
                        <td>
                            <?= ($row['Percentage_Pass'] > $row['Percentage_Fail']) ? '<span class="arrow-up">↑</span>' : '<span class="arrow-down">↓</span>' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">No data available</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h1>Best Performing Schools</h1>
    <table>
        <thead>
            <tr>
                <th>Center No</th>
                <th>School Name</th>
                <th>% Pass</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($best_schools)): ?>
                <?php foreach ($best_schools as $school): ?>
                    <tr>
                        <td><?= htmlspecialchars($school['CenterNo']) ?></td>
                        <td><?= htmlspecialchars($school['School_Name']) ?></td>
                        <td><?= number_format($school['Percentage_Pass'], 2) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">No data available</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h1>Worst Performing Schools</h1>
    <table>
        <thead>
            <tr>
                <th>Center No</th>
                <th>School Name</th>
                <th>% Fail</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($worst_schools)): ?>
                <?php foreach ($worst_schools as $school): ?>
                    <tr>
                        <td><?= htmlspecialchars($school['CenterNo']) ?></td>
                        <td><?= htmlspecialchars($school['School_Name']) ?></td>
                        <td><?= number_format($school['Percentage_Fail'], 2) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">No data available</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h1>Performance by Subcounty</h1>
    <table>
        <thead>
            <tr>
                <th>Subcounty Name</th>
                <th>Divisions Count</th>
                <th>Total Registered</th>
                <th>Total Sat</th>
                <th>% Pass</th>
                <th>% Fail</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($performance_by_subcounty)): ?>
                <?php foreach ($performance_by_subcounty as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['Subcounty_Name']) ?></td>
                        <td><?= htmlspecialchars($row['Divisions_Count']) ?></td>
                        <td><?= htmlspecialchars($row['Total_Registered']) ?></td>
                        <td><?= htmlspecialchars($row['Total_Sat']) ?></td>
                        <td><?= number_format($row['Percentage_Pass'], 2) ?>%</td>
                        <td><?= number_format($row['Percentage_Fail'], 2) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No data available</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h1>Overall Performance</h1>
    <table>
        <thead>
            <tr>
                <th>Center No</th>
                <th>School Name</th>
                <th>Divisions</th>
                <th>Total Registered</th>
                <th>Total Sat</th>
                <th>% Pass</th>
                <th>% Fail</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($overall_performance)): ?>
                <?php foreach ($overall_performance as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['CenterNo']) ?></td>
                        <td><?= htmlspecialchars($row['School_Name']) ?></td>
                        <td><?= htmlspecialchars($row['Divisions']) ?></td>
                        <td><?= htmlspecialchars($row['Total_Registered']) ?></td>
                        <td><?= htmlspecialchars($row['Total_Sat']) ?></td>
                        <td><?= number_format($row['Percentage_Pass'], 2) ?>%</td>
                        <td><?= number_format($row['Percentage_Fail'], 2) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">No data available</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h1>Schools Whose Candidates Did Not Sit for Exams</h1>
    <table>
        <thead>
            <tr>
                <th>Center No</th>
                <th>School Name</th>
                <th>Number of Candidates</th>
                <th>Subcounty Name</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($no_exams_schools)): ?>
                <?php foreach ($no_exams_schools as $school): ?>
                    <tr>
                        <td><?= htmlspecialchars($school['CenterNo']) ?></td>
                        <td><?= htmlspecialchars($school['School_Name']) ?></td>
                        <td><?= htmlspecialchars($school['Number_of_Candidates']) ?></td>
                        <td><?= htmlspecialchars($school['Subcounty_Name']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No data available</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
