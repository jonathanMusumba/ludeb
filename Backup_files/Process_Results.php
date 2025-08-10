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

if (isset($_POST['download_sheet'])) {
    require('fpdf186/fpdf.php');
    require __DIR__ . '/vendor/autoload.php';

    class PDF extends FPDF {
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Luuka Mock Results - ' . date('Y') . ' Printed on ' . date('Y-m-d'), 0, 0, 'C');
        }

        function ChapterTitle($title) {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 7, $title, 0, 1, 'L');
            $this->Ln(2);
        }

        function ChapterBody($body) {
            $this->SetFont('Arial', '', 12);
            $this->MultiCell(0, 7, $body);
            $this->Ln();
        }
    }

    // Ensure the temp directory exists
    $tempDir = __DIR__ . '/temp/';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    // Fetch all schools with ResultsStatus = 'Declared'
    $schools_query = "SELECT id, School_Name FROM schools WHERE ResultsStatus = 'Declared'";
    $schools_result = $conn->query($schools_query);

    if ($schools_result === false) {
        die("Error fetching schools: " . $conn->error);
    }

    $pdf_files = [];

    while ($school = $schools_result->fetch_assoc()) {
        $school_id = $school['id'];
        $selected_school_name = $school['School_Name'];
        $sanitized_school_name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $selected_school_name);

        // Generate the PDF for each school
        $pdf = new PDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'LUUKA DISTRICT LOCAL GOVERNMENT EXAMINATIONS BOARD', 0, 1, 'C');
        $pdf->Cell(0, 10, 'PRIMARY LEAVING EXAMINATIONS', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'MOCK EXAMINATIONS - ' . date('Y'), 0, 1, 'C');
        $pdf->Cell(0, 10, 'SCHOOL: ' . $sanitized_school_name, 0, 1, 'C');
        $pdf->Ln(2);

        // Table Header
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(20, 8, 'Index No', 1);
        $pdf->Cell(60, 8, 'Candidate Name', 1);
        $pdf->Cell(20, 8, 'Gender', 1);

        $subjects_query = "SELECT id, Code FROM subjects";
        $subjects_result = $conn->query($subjects_query);

        if ($subjects_result === false) {
            die("Error fetching subjects: " . $conn->error);
        }

        $columnWidths = [];
        while ($subject = $subjects_result->fetch_assoc()) {
            $code = $subject['Code'] ?? 'Unknown';
            $width = max(15, strlen($code) * 2);
            $pdf->Cell($width, 8, $code, 1);
            $columnWidths[] = $width;
        }

        $pdf->Cell(20, 8, 'Agg', 1);
        $pdf->Cell(20, 8, 'Div', 1);
        $pdf->Ln();

        // Table Body
        $pdf->SetFont('Arial', '', 8);

        $candidates_query = "SELECT id, IndexNo, Candidate_Name, gender, aggregate, division FROM candidates WHERE school_id = $school_id";
        $candidates_result = $conn->query($candidates_query);

        if ($candidates_result === false) {
            die("Error fetching candidates: " . $conn->error);
        }

        $candidates = $candidates_result->fetch_all(MYSQLI_ASSOC);

        foreach ($candidates as $candidate) {
            $pdf->Cell(20, 8, $candidate['IndexNo'] ?? 'N/A', 1);
            $pdf->Cell(60, 8, $candidate['Candidate_Name'] ?? 'N/A', 1);
            $pdf->Cell(20, 8, $candidate['gender'] ?? 'N/A', 1);

            $marks_query = "SELECT subject_id, mark FROM marks WHERE candidate_id = " . $candidate['id'];
            $marks_result = $conn->query($marks_query);

            $marks = [];
            while ($mark_row = $marks_result->fetch_assoc()) {
                $marks[$mark_row['subject_id']] = $mark_row['mark'];
            }

            $subjects_result->data_seek(0);
            foreach ($columnWidths as $index => $width) {
                $subject = $subjects_result->fetch_assoc();
                if ($subject) {
                    $subject_id = $subject['id'];
                    $mark = $marks[$subject_id] ?? 'Abs';
                    $pdf->Cell($width, 8, $mark, 1);
                } else {
                    $pdf->Cell($width, 8, '', 1);
                }
            }

            $aggregate = $candidate['aggregate'] ?? 0;
            $division = $candidate['division'] ?? 'X';
            $pdf->Cell(20, 8, $aggregate, 1);
            $pdf->Cell(20, 8, $division, 1);
            $pdf->Ln();
        }

        // Summary Section
        $pdf->AddPage();
        $pdf->ChapterTitle('Summary');

        $total_female_query = "SELECT COUNT(*) AS count FROM candidates WHERE school_id = $school_id AND Gender = 'F'";
        $total_female = $conn->query($total_female_query)->fetch_assoc()['count'];

        $total_male_query = "SELECT COUNT(*) AS count FROM candidates WHERE school_id = $school_id AND Gender = 'M'";
        $total_male = $conn->query($total_male_query)->fetch_assoc()['count'];

        $total_candidates = $total_female + $total_male;

        $pdf->SetFont('Arial', '', 10);
        $summary_col_width = 60;
        $pdf->Cell($summary_col_width, 10, "Female: $total_female", 0, 0);
        $pdf->Cell($summary_col_width, 10, "Male: $total_male", 0, 0);
        $pdf->Cell($summary_col_width, 10, "Total: $total_candidates", 0, 1);

        // Divisions and Total Numbers
        $pdf->ChapterTitle('Divisions and Total Numbers');
        $divisions = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, 'U' => 0, 'X' => 0];
        foreach ($candidates as $candidate) {
            $division = $candidate['division'] ?? 'U';
            $divisions[$division]++;
        }

        $columnWidth = 60;
        $rowHeight = 10;
        $xStart = 10;
        $yStart = $pdf->GetY();

        $pdf->SetFont('Arial', '', 10);
        $index = 0;
        foreach ($divisions as $division => $count) {
            $x = $xStart + ($index % 3) * $columnWidth;
            $y = $yStart + floor($index / 3) * $rowHeight;

            $pdf->SetXY($x, $y);
            $pdf->Cell($columnWidth, $rowHeight, "Division $division: $count", 0, 0);

            $index++;
        }
        $pdf->Ln();

        // Average Aggregates
        $pdf->ChapterTitle('Average Aggregates');
        if (count($candidates) > 0) {
            $total_aggregate = array_sum(array_column($candidates, 'aggregate'));
            $average_aggregate = $total_aggregate / count($candidates);
        } else {
            $average_aggregate = 0;
        }
        $pdf->ChapterBody('Average Aggregate: ' . number_format($average_aggregate, 0));

        // Best Candidates
        $pdf->ChapterTitle('Best Candidates');

        $best_candidates_query = "SELECT id, IndexNo, Candidate_Name, gender, aggregate FROM candidates WHERE school_id = $school_id ORDER BY aggregate DESC LIMIT 5";
        $best_candidates_result = $conn->query($best_candidates_query);

        if ($best_candidates_result === false) {
            die("Error fetching best candidates: " . $conn->error);
        }

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(30, 10, 'IndexNo', 1);
        $pdf->Cell(80, 10, 'Candidate Name', 1);
        $pdf->Cell(30, 10, 'Gender', 1);
        $pdf->Cell(40, 10, 'Aggregates', 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 10);
        while ($candidate = $best_candidates_result->fetch_assoc()) {
            $pdf->Cell(30, 10, $candidate['IndexNo'], 1);
            $pdf->Cell(80, 10, $candidate['Candidate_Name'], 1);
            $pdf->Cell(30, 10, $candidate['gender'], 1);
            $pdf->Cell(40, 10, $candidate['aggregate'], 1);
            $pdf->Ln();
        }

        // Subject Performance Table
        $pdf->AddPage();
        $pdf->ChapterTitle('Subject Performance and Grade Distribution');

        $subject_performance_query = "
            SELECT s.Name AS subject_name, 
                   AVG(CASE WHEN m.mark != -1 THEN m.mark ELSE NULL END) AS average_mark, 
                   COUNT(CASE WHEN m.mark BETWEEN g.range_from AND g.range_to THEN 1 ELSE NULL END) AS grade_count, 
                   COUNT(*) AS total_marks
            FROM marks m
            JOIN subjects s ON m.subject_id = s.id
            JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to
            WHERE m.school_id = $school_id
            GROUP BY s.Name";

        $performance_result = $conn->query($subject_performance_query);

        if ($performance_result === false) {
            die("Error fetching subject performance: " . $conn->error);
        }

        $subject_col_width = 60;
        $avg_mark_col_width = 30;
        $grade_count_col_width = 30;
        $total_marks_col_width = 30;

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($subject_col_width, 10, 'Subject', 1);
        $pdf->Cell($avg_mark_col_width, 10, 'Avg Mark', 1);
        $pdf->Cell($grade_count_col_width, 10, 'Grade Count', 1);
        $pdf->Cell($total_marks_col_width, 10, 'Total Marks', 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 10);
        while ($row = $performance_result->fetch_assoc()) {
            $pdf->Cell($subject_col_width, 10, $row['subject_name'] ?? 'N/A', 1);
            $pdf->Cell($avg_mark_col_width, 10, number_format($row['average_mark'] ?? 0, 1), 1);
            $pdf->Cell($grade_count_col_width, 10, $row['grade_count'] ?? 0, 1);
            $pdf->Cell($total_marks_col_width, 10, $row['total_marks'] ?? 0, 1);
            $pdf->Ln();
        }

        // Grade Distribution Table
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($subject_col_width, 10, 'Subject', 1);

        $grades_query = "SELECT * FROM grading ORDER BY range_from DESC";
        $grades_result = $conn->query($grades_query);

        if ($grades_result === false) {
            die("Error fetching grading data: " . $conn->error);
        }

        $grade_columns = [];
        while ($grade_row = $grades_result->fetch_assoc()) {
            $grade_columns[] = $grade_row;
            $pdf->Cell(14, 10, $grade_row['grade'], 1);
        }
        $pdf->Cell(14, 10, 'X', 1); // Column for marks with -1
        $pdf->Ln();

        $grade_distribution_query = "
            SELECT 
                s.Name AS subject_name,
                g.grade,
                COUNT(CASE 
                    WHEN m.mark = -1 THEN 1 
                    WHEN m.mark BETWEEN g.range_from AND g.range_to THEN 1 
                    ELSE NULL 
                    END) AS grade_count,
                SUM(CASE WHEN m.mark = -1 THEN 1 ELSE 0 END) AS absent_count
            FROM marks m
            JOIN subjects s ON m.subject_id = s.id
            LEFT JOIN grading g ON m.mark != -1 AND m.mark BETWEEN g.range_from AND g.range_to
            WHERE m.school_id = $school_id
            GROUP BY s.Name, g.grade
            ORDER BY s.Name, g.range_from";

        $grade_distribution_result = $conn->query($grade_distribution_query);

        if ($grade_distribution_result === false) {
            die("Error fetching grade distribution: " . $conn->error);
        }

        $pdf->SetFont('Arial', '', 10);

        $current_subject = '';
        $subject_grades = array_fill_keys(array_column($grade_columns, 'grade'), 0);
        $absent_count = 0;

        while ($row = $grade_distribution_result->fetch_assoc()) {
            if ($current_subject !== $row['subject_name']) {
                if ($current_subject !== '') {
                    $pdf->Cell($subject_col_width, 10, $current_subject, 1);
                    foreach ($subject_grades as $grade_count) {
                        $pdf->Cell(14, 10, $grade_count, 1);
                    }
                    $pdf->Cell(14, 10, $absent_count, 1);
                    $pdf->Ln();
                }

                $current_subject = $row['subject_name'];
                $subject_grades = array_fill_keys(array_column($grade_columns, 'grade'), 0);
                $absent_count = $row['absent_count'];
            }

            $subject_grades[$row['grade']] = $row['grade_count'];
        }

        $pdf->Cell($subject_col_width, 10, $current_subject, 1);
        foreach ($subject_grades as $grade_count) {
            $pdf->Cell(14, 10, $grade_count, 1);
        }
        $pdf->Cell(14, 10, $absent_count, 1);
        $pdf->Ln();

        // Grading Table
        $pdf->AddPage();
        $pdf->ChapterTitle('Grading Table');

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(60, 10, 'Range', 1);
        $pdf->Cell(60, 10, 'Grade', 1);
        $pdf->Ln();

        $grading_query = "SELECT range_from, range_to, grade FROM grading";
        $grading_result = $conn->query($grading_query);

        if ($grading_result === false) {
            die("Error fetching grading data: " . $conn->error);
        }

        $pdf->SetFont('Arial', '', 10);
        while ($row = $grading_result->fetch_assoc()) {
            $range = $row['range_from'] . ' - ' . $row['range_to'];
            $pdf->Cell(60, 10, $range, 1);
            $pdf->Cell(60, 10, $row['grade'] ?? 'N/A', 1);
            $pdf->Ln();
        }

        $filename = $tempDir . $sanitized_school_name . "_results.pdf";

        // Try to output the PDF file
        try {
            $pdf->Output('F', $filename);
            $pdf_files[] = $filename;
        } catch (Exception $e) {
            error_log("Failed to write file: $filename. Error: " . $e->getMessage());
        }
    }

    // Merge PDFs
    $final_pdf = new \setasign\Fpdi\Fpdi();
    foreach ($pdf_files as $file) {
        $page_count = $final_pdf->setSourceFile($file);
        for ($i = 1; $i <= $page_count; $i++) {
            $final_pdf->AddPage();
            $tplIdx = $final_pdf->importPage($i);
            $final_pdf->useTemplate($tplIdx);
        }
    }

    $final_pdf_path = $tempDir . 'Luuka_Mock_Results_All_Schools.pdf';
    try {
        $final_pdf->Output('F', $final_pdf_path);

        // Serve the merged PDF for download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Luuka_Mock_Results_All_Schools.pdf"');
        readfile($final_pdf_path);

        // Clean up temporary files
        foreach ($pdf_files as $file) {
            unlink($file);
        }
        unlink($final_pdf_path);
    } catch (Exception $e) {
        error_log("Failed to output final PDF. Error: " . $e->getMessage());
        die("An error occurred while generating the final PDF.");
    }

    $conn->close();
    $_SESSION['success_message'] = "The PDF has been generated and downloaded successfully.";
    header("Location: download_district_results.php");
    exit();
}
?>
