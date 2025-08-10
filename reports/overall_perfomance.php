<?php
session_start();

// Include PHPWord library
require '../vendor/autoload.php';  // Adjust the path to your Composer autoload file

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

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

// SQL query to fetch overall performance of the schools where ResultsStatus = 'Declared'
$query_school_performance = "
    SELECT 
        s.CenterNo,
        s.School_Name AS SchoolName,
        st.type AS Funding,
        CONCAT(
            'Div 1: ', COUNT(DISTINCT CASE WHEN r.division = '1' THEN r.candidate_id ELSE NULL END), ', ',
            'Div 2: ', COUNT(DISTINCT CASE WHEN r.division = '2' THEN r.candidate_id ELSE NULL END), ', ',
            'Div 3: ', COUNT(DISTINCT CASE WHEN r.division = '3' THEN r.candidate_id ELSE NULL END), ', ',
            'Div 4: ', COUNT(DISTINCT CASE WHEN r.division = '4' THEN r.candidate_id ELSE NULL END), ', ',
            'Div U: ', COUNT(DISTINCT CASE WHEN r.division = 'U' THEN r.candidate_id ELSE NULL END), ', ',
            'Div X: ', COUNT(DISTINCT CASE WHEN r.division = 'X' THEN r.candidate_id ELSE NULL END)
        ) AS Divisions,
        ROUND(
            (COUNT(DISTINCT CASE WHEN r.division IN ('1', '2', '3', '4') THEN r.candidate_id ELSE NULL END) 
            / COUNT(DISTINCT c.id)) * 100, 
            1
        ) AS Percentage_Pass,
        ROUND(
            (COUNT(DISTINCT CASE WHEN r.division IN ('U', 'X') THEN r.candidate_id ELSE NULL END) 
            / COUNT(DISTINCT c.id)) * 100, 
            1
        ) AS Percentage_Fail,
        CONCAT(
            ROUND(
                (COUNT(DISTINCT CASE WHEN r.division IN ('1', '2', '3', '4') THEN r.candidate_id ELSE NULL END) 
                / COUNT(DISTINCT c.id)) * 100 - 50, 0
            ),
            ' ',
            IF(
                ROUND(
                    (COUNT(DISTINCT CASE WHEN r.division IN ('1', '2', '3', '4') THEN r.candidate_id ELSE NULL END) 
                    / COUNT(DISTINCT c.id)) * 100 - 50, 0
                ) > 0,
                '+', '-'
            )
        ) AS Variance
    FROM 
        results r
    JOIN 
        candidates c ON r.candidate_id = c.id
    JOIN 
        schools s ON c.school_id = s.id
    JOIN 
        school_types st ON s.school_type = st.id
    WHERE 
        s.ResultsStatus = 'Declared'
    GROUP BY 
        s.CenterNo, s.School_Name, st.type
    ORDER BY 
        Percentage_Pass DESC, 
        COUNT(DISTINCT CASE WHEN r.division = '1' THEN r.candidate_id ELSE NULL END) DESC,
        COUNT(DISTINCT CASE WHEN r.division = '2' THEN r.candidate_id ELSE NULL END) DESC;
";

$results = $conn->query($query_school_performance);

if ($results->num_rows > 0) {
    // Create a new PHPWord object
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    
    // Add title
    $section->addTitle('Overall School Performance', 1);

    // Define table style
    $tableStyle = array(
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 80,
    );
    $cellStyle = array(
        'borderSize' => 6,
        'borderColor' => '000000',
        'valign' => 'center'
    );

    // Add table with style
    $table = $section->addTable($tableStyle);

    // Add header row
    $table->addRow();
    $table->addCell(2000, $cellStyle)->addText('CenterNo');
    $table->addCell(4000, $cellStyle)->addText('School Name');
    $table->addCell(4000, $cellStyle)->addText('Funding');
    $table->addCell(6000, $cellStyle)->addText('Divisions');
    $table->addCell(3000, $cellStyle)->addText('%age Pass');
    $table->addCell(3000, $cellStyle)->addText('%age Fail');
    $table->addCell(3000, $cellStyle)->addText('Variance');

    // Add data rows
    while($row = $results->fetch_assoc()) {
        $variance_sign = ($row['Variance'][0] == '+') ? '+' : '-';
        $variance_value = str_replace(' ', '', $row['Variance']);
        $table->addRow();
        $table->addCell(2000, $cellStyle)->addText($row['CenterNo']);
        $table->addCell(4000, $cellStyle)->addText($row['SchoolName']);
        $table->addCell(4000, $cellStyle)->addText($row['Funding']);
        $table->addCell(6000, $cellStyle)->addText($row['Divisions']);
        $table->addCell(3000, $cellStyle)->addText($row['Percentage_Pass'] . '%');
        $table->addCell(3000, $cellStyle)->addText($row['Percentage_Fail'] . '%');
        $table->addCell(3000, $cellStyle)->addText($variance_sign . $variance_value);
    }

    // Save the document
    $filename = 'School_Performance_Overview.docx';
    $phpWord->save($filename, 'Word2007');

    // Provide a link to download the document
    echo '<html><body>';
    echo '<h1>Overall School Performance</h1>';
    echo '<p><a href="' . $filename . '" download>Click here to download the report</a></p>';
    echo '</body></html>';
} else {
    echo "<p>No results found.</p>";
}

$conn->close();
?>
