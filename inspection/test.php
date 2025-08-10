<?php
// Create this as test_detailed.php to test the direct access
session_start();

echo "<h2>Testing detailed_report.php Access</h2>";

// Test with exam_year_id parameter
echo "<p><a href='detailed_report.php?exam_year_id=1'>Test detailed_report.php with exam_year_id=1</a></p>";
echo "<p><a href='detailed_report.php'>Test detailed_report.php without exam_year_id (should redirect)</a></p>";

// Show current session info
echo "<h3>Current Session:</h3>";
echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";

// Show available exam years
require_once 'db_connect.php';
$exam_years_query = "SELECT id, exam_year FROM exam_years ORDER BY exam_year DESC";
$result = $conn->query($exam_years_query);

echo "<h3>Available Exam Years:</h3>";
if ($result && $result->num_rows > 0) {
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>ID: {$row['id']}, Year: {$row['exam_year']} - ";
        echo "<a href='detailed_report.php?exam_year_id={$row['id']}'>Test with this exam_year_id</a></li>";
    }
    echo "</ul>";
} else {
    echo "No exam years found.";
}
?>