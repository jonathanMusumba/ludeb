<?php
session_start();
if (!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
    exit();
}

include 'database_connection.php';

// Sample data fetching queries
$totalSchools = $conn->query("SELECT COUNT(*) as count FROM schools")->fetch_assoc()['count'];

$totalCandidates = $conn->query("SELECT COUNT(*) as count FROM candidates")->fetch_assoc()['count'];
    $declaredMarksSchools = $conn->query("SELECT COUNT(DISTINCT school_id) as count FROM marks WHERE declared = 1")->fetch_assoc()['count'];
    $missingSchools = $totalSchools - $declaredMarksSchools;

    // Sample data for the line chart
    $chartDataQuery = "SELECT date, COUNT(*) as marks_count FROM marks GROUP BY date ORDER BY date";
    $chartDataResult = $conn->query($chartDataQuery);
    
    $chartLabels = [];
    $chartData = [];
    while ($row = $chartDataResult->fetch_assoc()) {
        $chartLabels[] = $row['date'];
        $chartData[] = $row['marks_count'];
    }

    // Prepare response
    $response = [
        'totalSchools' => $totalSchools,
        'totalCandidates' => $totalCandidates,
        'declaredMarksSchools' => $declaredMarksSchools,
        'missingSchools' => $missingSchools,
        'chartData' => [
            'labels' => $chartLabels,
            'data' => $chartData
        ]
    ];

    echo json_encode($response);
    ?>
