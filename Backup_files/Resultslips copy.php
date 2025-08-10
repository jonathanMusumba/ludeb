<?php
require('fpdf186/fpdf.php');
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate aggregates
// Function to calculate aggregates
function calculateAggregates($candidate_id) {
    global $conn;

    $marks_query = "SELECT m.mark, g.score, s.Name 
                    FROM marks m
                    JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to
                    JOIN subjects s ON m.subject_id = s.id
                    WHERE m.candidate_id = ?";

    $stmt = $conn->prepare($marks_query);
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $total_aggregate = 0;
    $english_score = null;
    $math_score = null;
    $subject_count = 0;
    $has_absence = false;
    $total_marks = 0;
    $marks = []; // Array to store marks for aggregate calculation

    while ($row = $result->fetch_assoc()) {
        if ($row['mark'] == -1) {
            $has_absence = true;
            break; // Exit loop as soon as an absence is found
        } else {
            $total_aggregate += $row['score'];
            $total_marks += $row['mark'];
            if ($row['Name'] == 'English') {
                $english_score = $row['score'];
            }
            if ($row['Name'] == 'Mathematics') {
                $math_score = $row['score'];
            }
            $subject_count++;
            $marks[] = $row['mark']; // Store mark
        }
    }

    // Ensure aggregates are set to 'X' if there are less than 4 subjects or if any subject has mark -1
    if ($has_absence || $subject_count < 4) {
        $total_aggregate = 'X';
    } else {
        $average_mark = ($subject_count > 0) ? ($total_marks / $subject_count) : 0;
    }

    return [
        'total_aggregate' => $total_aggregate,
        'english_score' => $english_score,
        'math_score' => $math_score,
        'subject_count' => $subject_count,
        'has_absence' => $has_absence,
        'average_mark' => $average_mark ?? 0 // Ensure default average mark value
    ];
}

// Function to determine division
function calculateDivision($candidate_id) {
    global $conn;

    $aggregates = calculateAggregates($candidate_id);

    $total_aggregate = $aggregates['total_aggregate'];
    $english_score = $aggregates['english_score'];
    $math_score = $aggregates['math_score'];
    $subject_count = $aggregates['subject_count'];
    $has_absence = $aggregates['has_absence'];

    if ($has_absence || $subject_count < 4) {
        return 'X';
    } elseif ($total_aggregate >= 4 && $total_aggregate <= 12) {
        if ($english_score < 7 && $math_score <= 8) {
            return '1';
        } elseif ($english_score == 8 || $math_score == 9) {
            return '2';
        } elseif ($english_score == 9) {
            return '3';
        }
    } elseif ($total_aggregate >= 13 && $total_aggregate <= 24) {
        return ($english_score <= 8) ? '2' : '3';
    } elseif ($total_aggregate >= 25 && $total_aggregate <= 28) {
        return ($english_score <= 8) ? '3' : '4';
    } elseif ($total_aggregate == 29) {
        if ($english_score <= 6) {
            return '3';
        } else {
            return '4';
        }
    } elseif ($total_aggregate >= 30 && $total_aggregate <= 32) {
        return '4';
    } elseif ($total_aggregate == 33) {
        if ($english_score < 8 && $math_score < 9) {
            return '4';
        } else {
            return 'U';
        }
    } elseif ($total_aggregate > 33) {
        return 'U';
    }

    return 'X';
}

// Function to generate PDF result slip
// Function to generate PDF result slip
function generateResultSlips($school_id) {
    global $conn;

    $candidates_query = "SELECT c.id, c.Candidate_Name, c.Gender, c.IndexNo, s.School_Name AS school_name 
                         FROM candidates c 
                         JOIN schools s ON c.school_id = s.id 
                         WHERE s.id = ? AND s.status = 'Active'";
    $stmt = $conn->prepare($candidates_query);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $candidates = $stmt->get_result();

    $pdf = new FPDF();
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 10);

    $slip_width = 190; // Width for the slip, considering margins
    $slip_height = 90; // Height for the slip with increased space
    $x_start = 10;
    $y_start = 10;
    $line_height = 5;
    $current_year = date('Y');
    $count = 0;

     // Watermark as a background image
    

    while ($candidate = $candidates->fetch_assoc()) {
        if ($count % 3 == 0 && $count > 0) {
            $pdf->AddPage();
            $y_start = 10;
        }

        $candidate_id = $candidate['id'];
        $aggregates = calculateAggregates($candidate_id);
        $division = calculateDivision($candidate_id);
        $average_mark = $aggregates['average_mark'];
        $index_no_last_two = substr($candidate['IndexNo'], -2);
        $serial_number = $current_year . $candidate_id . $index_no_last_two;

        // Determine if the aggregate should be displayed or not
        $show_aggregates = $aggregates['total_aggregate'] !== 'X';
        $show_average = $show_aggregates;

        // Draw slip content
        $pdf->SetXY($x_start, $y_start);
        $pdf->Cell(0, $line_height, 'LUUKA DISTRICT LOCAL GOVERNMENT EXAMINATIONS BOARD', 0, 1, 'C');
        $pdf->Cell(0, $line_height, 'PRIMARY LEAVING EXAMINATIONS', 0, 1, 'C');
        $pdf->Cell(0, $line_height, 'MOCK RESULT SLIP - ' . $current_year, 0, 1, 'C');
        $pdf->Cell(0, $line_height, 'Serial No: ' . $serial_number, 0, 1, 'C');

        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, $line_height, 'School Name: ' . $candidate['school_name'], 0, 1);
        $pdf->Cell(0, $line_height, 'Candidate Name: ' . $candidate['Candidate_Name'] . ' Index No: ' . $candidate['IndexNo'] . ' Sex: ' . $candidate['Gender'], 0, 1);

        $pdf->Ln(5);

        // Table Header
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(70, $line_height, 'Subject', 1);
        $pdf->Cell(30, $line_height, 'Score', 1);
        $pdf->Cell(30, $line_height, 'Grade', 1);
        $pdf->Ln();

        // Table Content
        $marks_query = "SELECT s.Name, m.mark, g.score 
                        FROM marks m 
                        JOIN subjects s ON m.subject_id = s.id 
                        LEFT JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to 
                        WHERE m.candidate_id = ?";
        $stmt = $conn->prepare($marks_query);
        $stmt->bind_param("i", $candidate_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $pdf->Cell(70, $line_height, $row['Name'] ?? 'N/A', 1);
            $pdf->Cell(30, $line_height, ($row['mark'] == -1 ? 'Abs' : $row['mark']), 1);
            $pdf->Cell(30, $line_height, ($row['score'] ?? 'X'), 1);
            $pdf->Ln();
        }

        $pdf->Ln(5);

        // Aggregates, Division and Average Mark
        $pdf->SetFont('Arial', 'B', 9);

        // Ensure these variables are defined
        $show_aggregates = isset($aggregates['total_aggregate']) && $aggregates['total_aggregate'] !== 'X';
        $show_average = $show_aggregates;

        if ($show_aggregates) {
            $pdf->Cell(70, $line_height, 'Total Aggregate:', 0);
            $pdf->Cell(30, $line_height, $aggregates['total_aggregate'] ?? 'N/A', 0);
            $pdf->Cell(40, $line_height, 'Division:', 0);
            $pdf->Cell(30, $line_height, $division, 0);
            $pdf->Ln();
        } else {
            $pdf->Cell(70, $line_height, 'Total Aggregate:', 0);
            $pdf->Cell(30, $line_height, 'X', 0);
            $pdf->Cell(40, $line_height, 'Division:', 0);
            $pdf->Cell(30, $line_height, 'X', 0);
            $pdf->Ln();
        }

        if ($show_average) {
            $pdf->Cell(70, $line_height, 'Average Mark:', 0);
            $pdf->Cell(30, $line_height, number_format($average_mark, 1), 0);
        } else {
            $pdf->Cell(70, $line_height, 'Average Mark:', 0);
            $pdf->Cell(30, $line_height, 'N/A', 0);
        }
        
        $pdf->Ln(10);

        // Draw cut lines
        if (($count + 1) % 3 == 0) {
            $pdf->Line($x_start, $y_start + $slip_height - 1, $x_start + $slip_width, $y_start + $slip_height - 1); // Bottom line
        }

        $y_start += $slip_height + 10; // Adjust for new slip
        $count++;
    }

    // Output the PDF
    $pdf->Output('I', 'result_slips.pdf');
}

// Example usage
if (isset($_GET['school_id'])) {
    $school_id = intval($_GET['school_id']);
    generateResultSlips($school_id);
} else {
    echo "No school selected.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Mock Result Slips</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
</head>
<body>
<div class="container mt-5">
    <h1>Generate Mock Result Slips</h1>
    <form method="get" action="">
        <div class="form-group">
            <label for="school">Select School:</label>
            <input type="text" name="school" id="school" class="form-control" placeholder="Enter School Name or Center No" required>
            <input type="hidden" name="school_id" id="school_id">
        </div>
        <button type="submit" class="btn btn-primary">Generate Slips</button>
    </form>
</div>
<script>
$(document).ready(function() {
    $('#school').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: 'fetch_schools.php',
                type: 'GET',
                data: { search: request.term },
                success: function(data) {
                    response($.map(JSON.parse(data), function(item) {
                        return {
                            label: item.name,
                            value: item.id
                        };
                    }));
                }
            });
        },
        select: function(event, ui) {
            $('#school_id').val(ui.item.value);
        }
    });
});
</script>
</body>
</html>
