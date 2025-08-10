<?php
session_start();

// Database connection
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
// Make sure to include your database connection file

// Fetch data
$schoolsData = getSchoolPerformanceData($conn);
$rankedSchools = rankSchools($schoolsData);

// Helper functions (as defined previously)
include 'helpers.php'; 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generated Report</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <h1>Performance Report</h1>
    
    <button onclick="printReport()">Print Report</button>
    <a href="export.php?format=pdf" class="button">Export as PDF</a>
    <a href="export.php?format=docx" class="button">Export as Word</a>
    <a href="export.php?format=xlsx" class="button">Export as Excel</a>

    <h2>Top 20 Best Performing Schools</h2>
    <table id="school-performance" border="1">
        <thead>
            <tr>
                <th>Rank</th>
                <th>School</th>
                <th>Pass Rate (%)</th>
                <th>Fail Rate (%)</th>
                <th>Total Candidates</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rankedSchools as $rank => $school) { ?>
                <tr>
                    <td><?php echo $rank + 1; ?></td>
                    <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                    <td><?php echo htmlspecialchars($school['pass_rate']); ?></td>
                    <td><?php echo htmlspecialchars($school['fail_rate']); ?></td>
                    <td><?php echo htmlspecialchars($school['total_candidates']); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <h2>Charts</h2>
    <canvas id="passRateChart"></canvas>

    <script src="js/charts.js"></script>
    <script>
        // Example chart initialization
        const ctx = document.getElementById('passRateChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($rankedSchools, 'school_name')); ?>,
                datasets: [{
                    label: 'Pass Rate (%)',
                    data: <?php echo json_encode(array_column($rankedSchools, 'pass_rate')); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        function printReport() {
            window.print();
        }
    </script>
</body>
</html>
