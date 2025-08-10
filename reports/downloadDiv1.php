<?php
require '../vendor/autoload.php'; 

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

// Query to fetch the top candidates for download
$download_query = "
    SELECT c.IndexNo, c.Candidate_Name, s.School_Name, MAX(r.aggregates) AS aggregates, 
           MAX(r.division) AS division
    FROM candidates c
    JOIN schools s ON c.school_id = s.id
    JOIN results r ON c.id = r.candidate_id
    WHERE r.division = '1' AND r.aggregates BETWEEN 4 AND 8
    GROUP BY c.id
    ORDER BY aggregates ASC
";
$download_result = $conn->query($download_query);

if (!$download_result) {
    die("Query failed: " . $conn->error);
}

// Handle Word document download
if (isset($_POST['download_word'])) {
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();

    // Add title
    $section->addTitle('Top Candidates (Aggregates 4 to 8)', 1);

    // Define table style with borders
    $tableStyle = [
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 50,
    ];
    $phpWord->addTableStyle('Table', $tableStyle);

    // Add table with style
    $table = $section->addTable('Table');

    // Add table headers
    $table->addRow();
    $table->addCell()->addText('Index No.');
    $table->addCell()->addText('Candidate Name');
    $table->addCell()->addText('School Name');
    $table->addCell()->addText('Aggregates');
    $table->addCell()->addText('Division');

    // Add rows with data
    while ($row = $download_result->fetch_assoc()) {
        $table->addRow();
        $table->addCell()->addText($row['IndexNo']);
        $table->addCell()->addText($row['Candidate_Name']);
        $table->addCell()->addText($row['School_Name']);
        $table->addCell()->addText($row['aggregates']);
        $table->addCell()->addText($row['division']);
    }

    // Save the Word document
    $filename = 'Top_Candidates.docx';
    $temp_file = tempnam(sys_get_temp_dir(), $filename);
    $phpWord->save($temp_file, 'Word2007');

    // Send the file to the browser for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    header('Pragma: public');
    readfile($temp_file);
    unlink($temp_file); // Delete the temporary file
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Division 1 Results</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">CANDIDATES WITH FIRST GRADES</h2>
    
    <!-- Existing table for display -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Index No.</th>
                <th>Candidate Name</th>
                <th>School Name</th>
                <th>Aggregates</th>
                <th>Division</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Fetch the display data
            $display_query = "
                SELECT c.IndexNo, c.Candidate_Name, s.School_Name, MAX(r.aggregates) AS aggregates, 
                       MAX(r.division) AS division
                FROM candidates c
                JOIN schools s ON c.school_id = s.id
                JOIN results r ON c.id = r.candidate_id
                WHERE r.division = '1' AND r.aggregates BETWEEN 4 AND 8
                GROUP BY c.id
                ORDER BY aggregates ASC
            ";
            $display_result = $conn->query($display_query);

            while ($row = $display_result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['IndexNo']}</td>";
                echo "<td>{$row['Candidate_Name']}</td>";
                echo "<td>{$row['School_Name']}</td>";
                echo "<td>{$row['aggregates']}</td>";
                echo "<td>{$row['division']}</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
    
    <!-- Download Word button -->
    <form method="post">
        <button type="submit" name="download_word" class="btn btn-success">Download Top Candidates (Aggregates 4 to 8) as Word</button>
    </form>
</div>
</body>
</html>

<?php
$conn->close();
?>
