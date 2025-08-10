<?php
require '../vendor/autoload.php'; // Include Composer's autoloader if using Composer
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Section;
use PhpOffice\PhpWord\Style\Table as TableStyle;
use PhpOffice\PhpWord\Style\Cell as CellStyle;

// Create new PhpWord object
$phpWord = new PhpWord();

// Add section with landscape orientation
$section = $phpWord->addSection([
    'orientation' => 'landscape', // Set landscape orientation
]);

// Add title for best performing schools
$section->addTitle('Best Performing Schools', 1);

// Define table style
$tableStyle = [
    'borderSize' => 6, // Border size
    'cellMargin' => 80, // Margin around cells
    'alignment' => 'center'
];

// Add table for best performing schools
$table = $section->addTable($tableStyle);

// Add table header
$table->addRow();
$table->addCell(2000, ['borderSize' => 6])->addText('Center No');
$table->addCell(3000, ['borderSize' => 6])->addText('School Name');
$table->addCell(2000, ['borderSize' => 6])->addText('Candidates');
$table->addCell(2000, ['borderSize' => 6])->addText('Passed');
$table->addCell(2000, ['borderSize' => 6])->addText('Failed');
$table->addCell(2000, ['borderSize' => 6])->addText('%age Pass');
$table->addCell(2000, ['borderSize' => 6])->addText('%age Fail');
$table->addCell(2000, ['borderSize' => 6])->addText('Variation');

// Fetch best performing schools data
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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

// Populate table rows
while ($row = $result_best_schools->fetch_assoc()) {
    $table->addRow();
    $table->addCell(2000, ['borderSize' => 6])->addText(htmlspecialchars($row['CenterNo']));
    $table->addCell(3000, ['borderSize' => 6])->addText(htmlspecialchars($row['School_Name']));
    $table->addCell(2000, ['borderSize' => 6])->addText(htmlspecialchars($row['Number_of_Candidates']));
    $table->addCell(2000, ['borderSize' => 6])->addText(htmlspecialchars($row['Passed']));
    $table->addCell(2000, ['borderSize' => 6])->addText(htmlspecialchars($row['Failed']));
    $table->addCell(2000, ['borderSize' => 6])->addText(number_format($row['Percentage_Pass'], 1) . '%');
    $table->addCell(2000, ['borderSize' => 6])->addText(number_format($row['Percentage_Fail'], 1) . '%');
    $variation = $row['Passed'] - $row['Failed'];
    $variation_text = $variation > 0 ? '↑' : ($variation < 0 ? '↓' : '→');
    $variation_color = $variation > 0 ? 'green' : ($variation < 0 ? 'red' : 'orange');
    $table->addCell(2000, ['borderSize' => 6])->addText($variation_text, ['color' => $variation_color]);
}

// Add a page break
$section->addPageBreak();

// Add title for worst performing schools
$section->addTitle('Worst Performing Schools', 1);

// Add table for worst performing schools
$table = $section->addTable($tableStyle);

// Add table header
$table->addRow();
$table->addCell(2000, ['borderSize' => 6])->addText('Center No');
$table->addCell(3000, ['borderSize' => 6])->addText('School Name');
$table->addCell(2000, ['borderSize' => 6])->addText('Total Candidates');
$table->addCell(2000, ['borderSize' => 6])->addText('Passed');
$table->addCell(2000, ['borderSize' => 6])->addText('Failed');
$table->addCell(2000, ['borderSize' => 6])->addText('Percentage Fail');
$table->addCell(2000, ['borderSize' => 6])->addText('Percentage Pass');
$table->addCell(2000, ['borderSize' => 6])->addText('Variation');

// Fetch worst performing schools data
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

// Populate table rows
while ($row = $result_worst_schools->fetch_assoc()) {
    $table->addRow();
    $table->addCell(2000, ['borderSize' => 6])->addText(htmlspecialchars($row['CenterNo']));
    $table->addCell(3000, ['borderSize' => 6])->addText(htmlspecialchars($row['School_Name']));
    $table->addCell(2000, ['borderSize' => 6])->addText(htmlspecialchars($row['Number_of_Candidates']));
    $table->addCell(2000, ['borderSize' => 6])->addText(htmlspecialchars($row['Passed']));
    $table->addCell(2000, ['borderSize' => 6])->addText(htmlspecialchars($row['Failed']));
    $table->addCell(2000, ['borderSize' => 6])->addText(number_format($row['Percentage_Fail'], 1) . '%');
    $table->addCell(2000, ['borderSize' => 6])->addText(number_format($row['Percentage_Pass'], 1) . '%');
    $variation = $row['Failed'] - $row['Passed'];
    $variation_text = $variation > 0 ? '↑' : ($variation < 0 ? '↓' : '→');
    $variation_color = $variation > 0 ? 'red' : ($variation < 0 ? 'green' : 'orange');
    $table->addCell(2000, ['borderSize' => 6])->addText($variation_text, ['color' => $variation_color]);
}

// Save the document
$filename = 'Performance_Report.docx';
$phpWord->save($filename, 'Word2007', true);

// Close database connection
$conn->close();
