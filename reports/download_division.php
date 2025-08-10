<?php
require '../vendor/autoload.php'; // Include PHPWord autoload

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

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

// Fetch total number of candidates
$total_candidates_query = "SELECT COUNT(DISTINCT id) AS total FROM candidates";
$total_candidates_result = $conn->query($total_candidates_query);
if (!$total_candidates_result) {
    die("Query failed: " . $conn->error);
}
$total_candidates_row = $total_candidates_result->fetch_assoc();
$total_candidates = $total_candidates_row['total'];

// Fetch division summaries
$query_division_summary = "
    SELECT 
        r.division, 
        COUNT(DISTINCT c.id) AS unique_candidates,
        ROUND(
            (COUNT(DISTINCT c.id) / $total_candidates) * 100, 
            1
        ) AS percentage
    FROM 
        results r
    JOIN 
        candidates c ON r.candidate_id = c.id
    GROUP BY 
        r.division;
";

$result_division_summary = $conn->query($query_division_summary);
if (!$result_division_summary) {
    die("Query failed: " . $conn->error);
}

// Fetch division summaries by gender
$query_division_by_gender = "
    SELECT 
        r.division,
        COUNT(DISTINCT CASE WHEN c.gender = 'M' THEN c.id END) AS male_count,
        COUNT(DISTINCT CASE WHEN c.gender = 'F' THEN c.id END) AS female_count,
        COUNT(DISTINCT c.id) AS total_count
    FROM 
        results r
    JOIN 
        candidates c ON r.candidate_id = c.id
    GROUP BY 
        r.division;
";

$result_division_by_gender = $conn->query($query_division_by_gender);
if (!$result_division_by_gender) {
    die("Query failed: " . $conn->error);
}

// Fetch schools with first grades
$query_schools_first_grades = "
    SELECT 
        s.CenterNo,
        s.School_Name,
        COUNT(DISTINCT c.id) AS Number_of_First_Grades
    FROM 
        results r
    JOIN 
        candidates c ON r.candidate_id = c.id
    JOIN 
        schools s ON c.school_id = s.id
    WHERE 
        r.division = '1'
    GROUP BY 
        s.CenterNo, s.School_Name
    ORDER BY 
        Number_of_First_Grades DESC;
";

$result_schools_first_grades = $conn->query($query_schools_first_grades);
if (!$result_schools_first_grades) {
    die("Query failed: " . $conn->error);
}
// Fetch total number of registered candidates by gender
$total_registered_query = "
    SELECT 
        gender,
        COUNT(DISTINCT id) AS total_registered
    FROM 
        candidates
    GROUP BY 
        gender;
";
$total_registered_result = $conn->query($total_registered_query);
if (!$total_registered_result) {
    die("Query failed: " . $conn->error);
}

// Fetch candidates who sat for the exam by gender
$query_total_sat = "
    SELECT 
        c.gender,
        COUNT(DISTINCT c.id) AS total_sat
    FROM 
        candidates c
    JOIN 
        results r ON c.id = r.candidate_id
    WHERE 
        r.division IN ('1', '2', '3', '4', 'U')
    GROUP BY 
        c.gender;
";
$result_total_sat = $conn->query($query_total_sat);
if (!$result_total_sat) {
    die("Query failed: " . $conn->error);
}

// Fetch absentees by gender
$query_absentees = "
    SELECT
        c.gender,
        COUNT(DISTINCT c.id) AS absentees
    FROM
        candidates c
    JOIN
        results r ON c.id = r.candidate_id
    WHERE
        r.division = 'X'
    GROUP BY
        c.gender;
";
$result_absentees = $conn->query($query_absentees);
if (!$result_absentees) {
    die("Query failed: " . $conn->error);
}

$total_registered = [];
$total_sat_by_gender = [];
$absentees = [];

// Store registered counts
while ($row = $total_registered_result->fetch_assoc()) {
    $total_registered[$row['gender']] = $row['total_registered'];
}

// Store sat counts
while ($row = $result_total_sat->fetch_assoc()) {
    $total_sat_by_gender[$row['gender']] = $row['total_sat'];
}

// Store absentees counts
while ($row = $result_absentees->fetch_assoc()) {
    $absentees[$row['gender']] = $row['absentees'];
}

// Calculate totals
$total_registered_all = array_sum($total_registered);
$total_sat_all = array_sum($total_sat_by_gender);
$absentees_all = array_sum($absentees);

// Create a new PHPWord object
$phpWord = new PhpWord();

// Add a new section
$section = $phpWord->addSection();

// Add a table for the candidates summary by gender
$table = $section->addTable(['borderSize' => 6, 'cellMargin' => 80]);
$table->addRow();
$table->addCell(2000)->addText('Gender');
$table->addCell(4000)->addText('Total Registered');
$table->addCell(4000)->addText('Total Sat');
$table->addCell(4000)->addText('Absentees');

foreach (['M' => 'Male', 'F' => 'Female'] as $gender_code => $gender_label) {
    $total_sat = isset($total_sat_by_gender[$gender_code]) ? $total_sat_by_gender[$gender_code] : 0;
    $absentees_count = isset($absentees[$gender_code]) ? $absentees[$gender_code] : 0;
    $table->addRow();
    $table->addCell(2000)->addText($gender_label);
    $table->addCell(4000)->addText(isset($total_registered[$gender_code]) ? $total_registered[$gender_code] : 0);
    $table->addCell(4000)->addText($total_sat);
    $table->addCell(4000)->addText($absentees_count);
}

$table->addRow();
$table->addCell(2000)->addText('Total');
$table->addCell(4000)->addText($total_registered_all);
$table->addCell(4000)->addText($total_sat_all);
$table->addCell(4000)->addText($absentees_all);

// Add a table for the overall division summary
$section->addTextBreak();
$table = $section->addTable(['borderSize' => 6, 'cellMargin' => 80]);
$table->addRow();
$table->addCell(3000)->addText('Division');
$table->addCell(4000)->addText('Unique Candidates');
$table->addCell(4000)->addText('Percentage (%)');

while ($row = $result_division_summary->fetch_assoc()) {
    $table->addRow();
    $table->addCell(3000)->addText($row['division']);
    $table->addCell(4000)->addText($row['unique_candidates']);
    $table->addCell(4000)->addText($row['percentage']);
}

$table->addRow();
$table->addCell(3000)->addText('Total');
$table->addCell(4000)->addText($total_candidates);
$table->addCell(4000);

// Add a table for division summary by gender
$section->addTextBreak();
$table = $section->addTable(['borderSize' => 6, 'cellMargin' => 80]);
$table->addRow();
$table->addCell(3000)->addText('Division');
$table->addCell(3000)->addText('Male (M)');
$table->addCell(3000)->addText('Female (F)');
$table->addCell(3000)->addText('Total');

while ($row = $result_division_by_gender->fetch_assoc()) {
    $table->addRow();
    $table->addCell(3000)->addText($row['division']);
    $table->addCell(3000)->addText($row['male_count']);
    $table->addCell(3000)->addText($row['female_count']);
    $table->addCell(3000)->addText($row['total_count']);
}

// Add a table for schools with first grades
$section->addTextBreak();
$table = $section->addTable(['borderSize' => 6, 'cellMargin' => 80]);
$table->addRow();
$table->addCell(3000)->addText('Center No');
$table->addCell(5000)->addText('School Name');
$table->addCell(3000)->addText('Number of First Grades');

while ($row = $result_schools_first_grades->fetch_assoc()) {
    $table->addRow();
    $table->addCell(3000)->addText($row['CenterNo']);
    $table->addCell(5000)->addText($row['School_Name']);
    $table->addCell(3000)->addText($row['Number_of_First_Grades']);
}

// Save the document
$filename = 'results_summary.docx';
$phpWord->save($filename, 'Word2007');

// Output the file to download
header("Content-Description: File Transfer");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Transfer-Encoding: binary");
header("Expires: 0");
header("Cache-Control: must-revalidate");
header("Pragma: public");
header("Content-Length: " . filesize($filename));

readfile($filename);

// Delete the file after download
unlink($filename);

// Close the database connection
$conn->close();
?>
