<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

require_once '../vendor/autoload.php'; // Include PHPWord autoload

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Table;
use PhpOffice\PhpWord\SimpleType\TblWidth;

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
            MIN(r.division) AS division
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

// Create a new PHPWord object
$phpWord = new PhpWord();
$section = $phpWord->addSection();

// Add a title to the document
$section->addTitle('Sub-County Performance Summary', 1);

// Define table style
$tableStyle = [
    'borderSize' => 6, 
    'borderColor' => '000000', 
    'cellMargin' => 50,
];
$phpWord->addTableStyle('Performance Table', $tableStyle);

// Add table to the document with defined style
$table = $section->addTable('Performance Table');

// Add table headers
$table->addRow();
$table->addCell(2000)->addText('Sub-County Name');
$table->addCell(1000)->addText('Div 1');
$table->addCell(1000)->addText('Div 2');
$table->addCell(1000)->addText('Div 3');
$table->addCell(1000)->addText('Div 4');
$table->addCell(1000)->addText('Div U');
$table->addCell(1000)->addText('Registered');
$table->addCell(1000)->addText('Sat');
$table->addCell(1000)->addText('%age Pass');
$table->addCell(1000)->addText('%age Fail');

// Add data rows
while ($row = $result_subcounty_performance->fetch_assoc()) {
    $table->addRow();
    $table->addCell(2000)->addText(htmlspecialchars($row['subcounty']));
    $table->addCell(1000)->addText(htmlspecialchars($row['Division_1']));
    $table->addCell(1000)->addText(htmlspecialchars($row['Division_2']));
    $table->addCell(1000)->addText(htmlspecialchars($row['Division_3']));
    $table->addCell(1000)->addText(htmlspecialchars($row['Division_4']));
    $table->addCell(1000)->addText(htmlspecialchars($row['Division_U']));
    $table->addCell(1000)->addText(htmlspecialchars($row['Total_Registered']));
    $table->addCell(1000)->addText(htmlspecialchars($row['Total_Sat']));
    $table->addCell(1000)->addText(htmlspecialchars($row['Percentage_Pass']));
    $table->addCell(1000)->addText(htmlspecialchars($row['Percentage_Fail']));
}

// Save the document as a Word file
$filename = "SubCounty_Performance_Summary.docx";
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');

// Close the database connection
$conn->close();
exit();
?>
