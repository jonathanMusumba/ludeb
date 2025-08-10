<?php
// Database connection
$host = 'localhost'; // Replace with your database host
$db = 'Ludeb'; // Replace with your database name
$user = 'root'; // Replace with your database username
$pass = ''; // Replace with your database password

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch subjects with names
    $subjects = $conn->query("
        SELECT DISTINCT s.id AS subject_id, s.name
        FROM results r
        JOIN subjects s ON r.subject_id = s.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch grades and their ranges
    $grades = $conn->query("SELECT * FROM grading")->fetchAll(PDO::FETCH_ASSOC);

    // Map grades to grade names
    $grade_map = [];
    foreach ($grades as $grade) {
        $grade_map[$grade['score']] = $grade['grade'];
    }

    // Prepare data
    $data = [];
    $summary = [];
    foreach ($subjects as $subject) {
        $subject_id = $subject['subject_id'];
        $subject_name = $subject['name'];

        // Query to count candidates per grade for each subject
        $result = $conn->prepare("
            SELECT 
                r.score,
                COUNT(r.id) AS total_count
            FROM results r
            WHERE r.subject_id = :subject_id
            AND r.score BETWEEN 1 AND 9  -- Filter scores within the defined range
            GROUP BY r.score
        ");
        $result->execute(['subject_id' => $subject_id]);
        $rows = $result->fetchAll(PDO::FETCH_ASSOC);

        // Initialize data array with default values
        $data[$subject_name] = [
            'D1' => 0, 'D2' => 0, 'C3' => 0, 'C4' => 0, 'C5' => 0, 'C6' => 0,
            'P7' => 0, 'P8' => 0, 'F9' => 0
        ];

        $total_candidates = 0;
        $total_passed = 0;
        $total_failed = 0;

        foreach ($rows as $row) {
            $grade = $grade_map[$row['score']] ?? 'X'; // Default to 'X' if grade not found
            if (array_key_exists($grade, $data[$subject_name])) {
                $data[$subject_name][$grade] += $row['total_count'];
            }

            // Calculate totals
            $total_candidates += $row['total_count'];
            if ($grade === 'D1' || $grade === 'D2' || $grade === 'C3' || $grade === 'C4' || $grade === 'C5' || $grade === 'C6' || $grade === 'P7' || $grade === 'P8') {
                $total_passed += $row['total_count'];
            } else {
                $total_failed += $row['total_count'];
            }
        }

        // Calculate percentages
        $percentage_pass = $total_candidates > 0 ? ($total_passed / $total_candidates) * 100 : 0;
        $percentage_fail = $total_candidates > 0 ? ($total_failed / $total_candidates) * 100 : 0;
        $variation = $total_passed - $total_failed;

        $summary[$subject_name] = [
            'passed' => $total_passed,
            'failed' => $total_failed,
            'total' => $total_candidates,
            'percentage_pass' => $percentage_pass,
            'percentage_fail' => $percentage_fail,
            'variation' => $variation
        ];
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject and Grades Report</title>
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
    </style>
</head>
<body>
    <h1>Subject and Grades Report</h1>
    <table>
        <thead>
            <tr>
                <th>Subject</th>
                <th>D1</th>
                <th>D2</th>
                <th>C3</th>
                <th>C4</th>
                <th>C5</th>
                <th>C6</th>
                <th>P7</th>
                <th>P8</th>
                <th>F9</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $subject_name => $grades): ?>
                <tr>
                    <td><?= htmlspecialchars($subject_name) ?></td>
                    <?php foreach (['D1', 'D2', 'C3', 'C4', 'C5', 'C6', 'P7', 'P8', 'F9'] as $grade): ?>
                        <td><?= htmlspecialchars($grades[$grade]) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h1>Subject Performance Summary</h1>
    <table>
        <thead>
            <tr>
                <th>Subject</th>
                <th>Number of Candidates Passed</th>
                <th>Number of Candidates Failed</th>
                <th>Total Who Sat</th>
                <th>Percentage Pass</th>
                <th>Percentage Fail</th>
                <th>Variation (Pass - Fail)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($summary as $subject_name => $stats): ?>
                <tr>
                    <td><?= htmlspecialchars($subject_name) ?></td>
                    <td><?= htmlspecialchars($stats['passed']) ?></td>
                    <td><?= htmlspecialchars($stats['failed']) ?></td>
                    <td><?= htmlspecialchars($stats['total']) ?></td>
                    <td><?= number_format($stats['percentage_pass'], 2) ?>%</td>
                    <td><?= number_format($stats['percentage_fail'], 2) ?>%</td>
                    <td><?= htmlspecialchars($stats['variation']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
