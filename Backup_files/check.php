<?php
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

// Include TCPDF
require_once 'vendor/autoload.php'; // or 'tcpdf/tcpdf.php' if not using Composer

// Handle PDF Download
if (isset($_POST['download_sheet'])) {
    // Create new PDF document
    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);

    // Add title
    $pdf->Cell(0, 10, "$board_name - Exam Year: $current_year", 0, 1, 'C');
    $pdf->Cell(0, 10, "SCHOOL: $selected_school_name", 0, 1, 'C');
    $pdf->Ln(10);

    // Add table header
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(20, 10, 'Index Number', 1, 0, 'C', true);
    $pdf->Cell(50, 10, 'Candidate Name', 1, 0, 'C', true);
    $subjects = $conn->query("SELECT Code FROM subjects");
    while ($subject = $subjects->fetch_assoc()) {
        $pdf->Cell(25, 10, $subject['Code'] . ' (Grade)', 1, 0, 'C', true);
    }
    $pdf->Cell(25, 10, 'Aggregates', 1, 0, 'C', true);
    $pdf->Cell(25, 10, 'Division', 1, 1, 'C', true);

    // Add candidates data
    foreach ($candidates as $candidate) {
        $pdf->Cell(20, 10, $candidate['index_number'], 1);
        $pdf->Cell(50, 10, $candidate['name'], 1);
        foreach ($subjects->fetch_assoc() as $subject) {
            $subject_id = $subject['id'];
            $mark = $candidate['marks'][$subject_id] ?? 'NA';
            $grade = $candidate['grades'][$subject_id] ?? 'NA';
            $pdf->Cell(25, 10, "$mark ($grade)", 1);
        }
        $pdf->Cell(25, 10, $candidate['aggregate'], 1);
        $pdf->Cell(25, 10, determineDivision($candidate['aggregate']), 1);
        $pdf->Ln();
    }

    // Add summary
    $pdf->Ln(10);
    $pdf->Cell(0, 10, "Summary", 0, 1, 'L');
    $pdf->Cell(0, 10, "Males: $male_count, Females: $female_count", 0, 1, 'L');
    $pdf->Cell(0, 10, "Average Aggregates: " . number_format($average_aggregate, 2), 0, 1, 'L');

    // Add division summary
    $pdf->Cell(0, 10, "Division Summary:", 0, 1, 'L');
    foreach ($division_summary as $division => $count) {
        $pdf->Cell(0, 10, "$division: $count", 0, 1, 'L');
    }

    // Add average marks per subject
    $pdf->Ln(10);
    $pdf->Cell(0, 10, "Average Marks per Subject:", 0, 1, 'L');
    foreach ($subject_averages as $subject_id => $subject_stats) {
        $subject_code_query = $conn->query("SELECT Code FROM subjects WHERE id = $subject_id");
        $subject_code = $subject_code_query->fetch_assoc()['Code'] ?? 'Unknown';
        $pdf->Cell(0, 10, "$subject_code: " . number_format($subject_stats['average_marks'], 2), 0, 1, 'L');
    }

    $pdf->Output('D', 'results_sheet.pdf');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Retrieve Marks</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        th {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="home.php">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Retrieve Marks</li>
    </ol>
</nav>

<div class="container mt-5">
<?php if (!isset($_GET['view_school'])): ?>
        <form method="GET" action="" class="mb-4">
            <div class="form-group">
                <label for="school_name">Select School:</label>
                <input type="text" class="form-control" id="school_name" name="school_id" placeholder="Type school name..." autocomplete="off">
                <input type="hidden" id="school_id" name="school_id">
            </div>
            <button type="submit" class="btn btn-primary">View Marks</button>
        </form>
    <?php else: ?>
        <h2>Marks for School: <?= $selected_school_name ?></h2>
        <form method="POST" action="">
            <button type="submit" name="download_sheet" class="btn btn-primary">Download PDF</button>
        </form>
        <table>
            <thead>
                <tr>
                    <th>Index Number</th>
                    <th>Candidate Name</th>
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <th><?= $subject['Code'] ?> (Grade)</th>
                    <?php endwhile; ?>
                    <th>Aggregates</th>
                    <th>Division</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $candidate): ?>
                    <tr>
                        <td><?= $candidate['index_number'] ?></td>
                        <td><?= $candidate['name'] ?></td>
                        <?php foreach ($subjects as $subject): ?>
                            <td><?= $candidate['marks'][$subject['id']] ?? 'NA' ?> (<?= $candidate['grades'][$subject['id']] ?? 'NA' ?>)</td>
                        <?php endforeach; ?>
                        <td><?= $candidate['aggregate'] ?></td>
                        <td><?= determineDivision($candidate['aggregate']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    $("#school_name").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "school_get.php", // PHP file that returns school names and IDs
                type: "GET",
                dataType: "json",
                data: {
                    term: request.term // search term
                },
                success: function(data) {
                    response(data);
                }
            });
        },
        minLength: 2, // minimum length of the input before autocomplete triggers
        select: function(event, ui) {
            $("#school_id").val(ui.item.id); // set the hidden field with the selected school ID
        }
    });
});
</script>

