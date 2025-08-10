<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

require_once '../vendor/autoload.php'; // Include PHPWord autoload

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

$user_id = $_SESSION['user_id'];

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

// Create a new PHPWord object
$phpWord = new PhpWord();
$section = $phpWord->addSection();

// Add a title to the document
$section->addTitle('Subject and Grades Report', 1);

// Add table for subjects and grades
$table = $section->addTable(['borderSize' => 6, 'cellMargin' => 80]);

// Add table headers
$table->addRow();
$table->addCell(2000)->addText('Subject');
foreach (['D1', 'D2', 'C3', 'C4', 'C5', 'C6', 'P7', 'P8', 'F9'] as $grade) {
    $table->addCell(1000)->addText($grade);
}

// Add data rows
foreach ($data as $subject_name => $grades) {
    $table->addRow();
    $table->addCell(2000)->addText(htmlspecialchars($subject_name));
    foreach (['D1', 'D2', 'C3', 'C4', 'C5', 'C6', 'P7', 'P8', 'F9'] as $grade) {
        $table->addCell(1000)->addText(htmlspecialchars($grades[$grade]));
    }
}

// Add a new section for summary
$section->addPageBreak();
$section->addTitle('Subject Performance Summary', 1);

// Add table for performance summary
$table = $section->addTable(['borderSize' => 6, 'cellMargin' => 80]);

// Add table headers
$table->addRow();
$table->addCell(2000)->addText('Subject');
$table->addCell(1000)->addText('Number of Candidates Passed');
$table->addCell(1000)->addText('Number of Candidates Failed');
$table->addCell(1000)->addText('Total Who Sat');
$table->addCell(1000)->addText('Percentage Pass');
$table->addCell(1000)->addText('Percentage Fail');
$table->addCell(1000)->addText('Variation (Pass - Fail)');

// Add data rows
foreach ($summary as $subject_name => $stats) {
    $table->addRow();
    $table->addCell(2000)->addText(htmlspecialchars($subject_name));
    $table->addCell(1000)->addText(htmlspecialchars($stats['passed']));
    $table->addCell(1000)->addText(htmlspecialchars($stats['failed']));
    $table->addCell(1000)->addText(htmlspecialchars($stats['total']));
    $table->addCell(1000)->addText(number_format($stats['percentage_pass'], 2) . '%');
    $table->addCell(1000)->addText(number_format($stats['percentage_fail'], 2) . '%');
    $table->addCell(1000)->addText(htmlspecialchars($stats['variation']));
}

// Save the document as a Word file
$filename = "Subject_Performance_Report.docx";
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');

// Close the database connection
$conn = null;
exit();
?>
