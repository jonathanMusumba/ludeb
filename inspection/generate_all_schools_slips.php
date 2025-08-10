<?php
session_start();
require_once 'db_connect.php';
require_once '../lib/fpdf.php';

// Restrict to Examination Administrators
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Examination Administrator') {
    header("Location: ../login.php");
    exit();
}

$exam_year_id = isset($_GET['exam_year_id']) ? (int)$_GET['exam_year_id'] : 0;

if (!$exam_year_id) {
    header("Location: list_schools.php");
    exit();
}

// Enable error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Fetch exam body and year details
$exam_body_query = "SELECT board_name FROM settings WHERE id = 1";
$exam_body_result = $conn->query($exam_body_query);
$exam_body = ($exam_body_result && $exam_body_result->num_rows > 0) ? $exam_body_result->fetch_assoc()['board_name'] : 'LUUKA EXAMINATION BOARD';

$exam_year_query = "SELECT exam_year FROM exam_years WHERE id = ?";
$stmt = $conn->prepare($exam_year_query);
if ($stmt === false) {
    error_log("Prepare failed for exam_year_query: " . $conn->error);
    die("Database error. Please try again later.");
}
$stmt->bind_param('i', $exam_year_id);
$stmt->execute();
$exam_year_result = $stmt->get_result();
$exam_year = ($exam_year_result && $exam_year_result->num_rows > 0) ? $exam_year_result->fetch_assoc()['exam_year'] : '2025';
$stmt->close();

// Fetch schools with complete results
$schools_query = "
    SELECT s.id, s.center_no, s.school_name
    FROM schools s
    LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    LEFT JOIN (
        SELECT candidate_id, exam_year_id, COUNT(DISTINCT subject_id) AS subject_count
        FROM marks
        WHERE exam_year_id = ?
        GROUP BY candidate_id, exam_year_id
    ) m ON c.id = m.candidate_id AND c.exam_year_id = m.exam_year_id
    WHERE s.results_status = 'Declared'
    GROUP BY s.id, s.center_no, s.school_name
    HAVING (COUNT(c.id) = 0 OR (MIN(m.subject_count) = 4 AND MAX(m.subject_count) = 4))
    ORDER BY s.center_no
";
$schools = [];
$stmt = $conn->prepare($schools_query);
if ($stmt === false) {
    error_log("Prepare failed for schools_query: " . $conn->error);
    die("Database error. Please try again later.");
}
$stmt->bind_param('ii', $exam_year_id, $exam_year_id);
$stmt->execute();
$schools_result = $stmt->get_result();
while ($row = $schools_result->fetch_assoc()) {
    $schools[] = $row;
}
$stmt->close();

// Enhanced PDF class with rotation support
class EnhancedPDF extends FPDF {
    var $angle = 0;
    
    function Header() {
        // No header - watermark will be added per slip
    }

    function Footer() {
        // Removed footer - no page numbers or generated timestamp
    }
    
    // Add rotation method for watermark
    function Rotate($angle, $x = -1, $y = -1) {
        if ($x == -1) $x = $this->x;
        if ($y == -1) $y = $this->y;
        if ($this->angle != 0) $this->_out('Q');
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }
    
    function _endpage() {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
}

function drawEnhancedResultSlip($pdf, $result, $school, $exam_body, $exam_year, $x, $y, $width, $height) {
    // Removed decorative border around the slip
    // Removed corner decorations
    
    // ======== ENHANCED WATERMARK SECTION ========
    // Multiple security watermarks with diagonal rotation
    $pdf->SetFont('Arial', 'B', 30);
    $pdf->SetTextColor(248, 248, 248); // Almost invisible but still readable
    
    // Calculate center position for main watermark
    $center_x = $x + ($width / 2);
    $center_y = $y + ($height / 2);
    
    // Main diagonal watermark - LPSMEB
    $text = 'LPSMEB';
    $text_width = $pdf->GetStringWidth($text);
    $watermark_x = $center_x - ($text_width / 2);
    $watermark_y = $center_y + 3; // Slight offset for better positioning
    
    // Apply diagonal rotation (-45 degrees)
    $pdf->Rotate(-45, $center_x, $center_y);
    $pdf->Text($watermark_x, $watermark_y, $text);
    $pdf->Rotate(0); // Reset rotation
    
    // Secondary watermark with exam year (smaller, different position)
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(250, 250, 250); // Even lighter
    $secondary_text = "PLE $exam_year";
    $secondary_width = $pdf->GetStringWidth($secondary_text);
    
    // Position in upper right area
    $sec_x = $x + ($width * 0.75) - ($secondary_width / 2);
    $sec_y = $y + ($height * 0.25);
    
    $pdf->Rotate(-30, $sec_x + ($secondary_width / 2), $sec_y); // Different angle
    $pdf->Text($sec_x, $sec_y, $secondary_text);
    $pdf->Rotate(0); // Reset rotation
    
    // Third watermark with center number (bottom left area)
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(252, 252, 252); // Ultra light
    $center_text = "CTR-" . $school['center_no'];
    $center_width = $pdf->GetStringWidth($center_text);
    
    // Position in lower left area
    $ctr_x = $x + ($width * 0.25) - ($center_width / 2);
    $ctr_y = $y + ($height * 0.75);
    
    $pdf->Rotate(-60, $ctr_x + ($center_width / 2), $ctr_y); // Different angle
    $pdf->Text($ctr_x, $ctr_y, $center_text);
    $pdf->Rotate(0); // Reset rotation
    
    // Add security pattern (diagonal lines) - enhanced
    $pdf->SetDrawColor(252, 252, 252); // Very light gray
    $pdf->SetLineWidth(0.05);
    
    // Diagonal security lines pattern
    for ($i = 0; $i < 30; $i += 3) {
        $line_x1 = $x + ($i * 7);
        $line_y1 = $y;
        $line_x2 = $line_x1 - 15;
        $line_y2 = $y + 15;
        
        if ($line_x2 >= $x && $line_y2 <= $y + $height) {
            $pdf->Line($line_x1, $line_y1, $line_x2, $line_y2);
        }
    }
    
    // Reverse diagonal pattern
    for ($i = 0; $i < 30; $i += 3) {
        $line_x1 = $x + ($i * 7);
        $line_y1 = $y + $height;
        $line_x2 = $line_x1 + 15;
        $line_y2 = $y + $height - 15;
        
        if ($line_x2 <= $x + $width && $line_y2 >= $y) {
            $pdf->Line($line_x1, $line_y1, $line_x2, $line_y2);
        }
    }
    // ======== END WATERMARK SECTION ========
    
    // Add unique slip ID/serial number with security hash
    $security_hash = substr(md5($result['index_no'] . $exam_year . $school['center_no'] . date('Y-m-d')), 0, 8);
    $slip_id = 'SLP-' . date('Y') . '-' . str_pad($result['index_no'] ?? rand(1000, 9999), 4, '0', STR_PAD_LEFT) . '-' . strtoupper($security_hash);
    
    $pdf->SetFont('Arial', 'I', 5);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetXY($x + $width - 50, $y + 2);
    $pdf->Cell(45, 3, $slip_id, 0, 0, 'R');
    
    // Reset colors and continue with rest of slip content
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);
    
    $header_height = 24;
    $info_height = 18;
    $table_height = 40;
    $summary_height = 12;
    $footer_height = 6;
    
    $total_content_height = $header_height + $info_height + $table_height + $summary_height + $footer_height;
    $available_padding = $height - $total_content_height;
    $section_spacing = max(1, $available_padding / 4);
    
    $current_y = $y;
    
    // Header section with better design
    $pdf->SetXY($x, $current_y);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell($width, 7, strtoupper($exam_body), 0, 1, 'C');
    
    $pdf->SetX($x);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell($width, 6, "PLE MOCK EXAMINATIONS $exam_year", 0, 1, 'C');
    
    $pdf->SetX($x);
    $pdf->SetFont('Arial', 'U', 10);
    $pdf->Cell($width, 5, 'CANDIDATE RESULT SLIP', 0, 1, 'C');
    
    $header_bottom_y = $current_y + $header_height - 2;
    $pdf->SetLineWidth(0.5);
    $pdf->Line($x, $header_bottom_y, $x + $width, $header_bottom_y);
    $pdf->SetLineWidth(0.2);
    
    $current_y += $header_height + $section_spacing;
    
    // Candidate information section with better alignment
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetXY($x, $current_y);
    $pdf->Cell($width/2 - 5, 6, "Index Number: " . ($result['index_no'] ?? '-'), 0, 0, 'L');
    $pdf->Cell($width/2 + 5, 6, "Center No: " . $school['center_no'], 0, 1, 'R');
    
    $pdf->SetX($x);
    $candidate_name = strtoupper(substr($result['candidate_name'] ?? '', 0, 35));
    $pdf->Cell($width * 0.7, 6, "Name: $candidate_name", 0, 0, 'L');
    $pdf->Cell($width * 0.3, 6, "Year: $exam_year", 0, 1, 'R');
    
    $pdf->SetX($x);
    $school_name = strtoupper(substr($school['school_name'], 0, 45));
    $pdf->Cell($width, 6, "School: $school_name", 0, 1, 'L');
    
    $current_y += $info_height + $section_spacing;
    
    // Enhanced table design with alternating backgrounds
    $table_start_y = $current_y;
    $row_height = 8;
    
    // Table header with background
    $pdf->SetFillColor(230, 240, 250);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetXY($x, $table_start_y);
    $pdf->Cell($width * 0.5, $row_height, 'Subject', 0, 0, 'L', true);
    $pdf->Cell($width * 0.25, $row_height, 'Marks', 0, 0, 'C', true);
    $pdf->Cell($width * 0.25, $row_height, 'Grade', 0, 1, 'C', true);
    
    // Table rows with alternating colors
    $pdf->SetFont('Arial', '', 8);
    $subjects = [
        ['name' => 'English', 'marks' => $result['eng_marks'], 'grade' => $result['eng_grade']],
        ['name' => 'Science', 'marks' => $result['sci_marks'], 'grade' => $result['sci_grade']],
        ['name' => 'Mathematics', 'marks' => $result['mtc_marks'], 'grade' => $result['mtc_grade']],
        ['name' => 'Social Studies', 'marks' => $result['sst_marks'], 'grade' => $result['sst_grade']]
    ];
    
    $fill = false;
    foreach ($subjects as $subject) {
        $pdf->SetX($x);
        $marks = ($subject['marks'] !== null && $result['division'] !== 'X') ? $subject['marks'] : '-';
        $grade = ($subject['grade'] !== null && $result['division'] !== 'X') ? $subject['grade'] : 'X';
        
        // Alternating row colors
        if ($fill) {
            $pdf->SetFillColor(248, 252, 255);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($width * 0.5, $row_height, $subject['name'], 0, 0, 'L', true);
        $pdf->Cell($width * 0.25, $row_height, $marks, 0, 0, 'C', true);
        $pdf->Cell($width * 0.25, $row_height, $grade, 0, 1, 'C', true);
        $fill = !$fill;
    }
    
    $current_y += $table_height + $section_spacing;
    
    // Summary section with enhanced design and security features
    $summary_y = $current_y;
    $pdf->SetLineWidth(0.5);
    $pdf->Line($x, $summary_y, $x + $width, $summary_y);
    $pdf->SetLineWidth(0.2);
    
    $pdf->SetXY($x, $summary_y + 2);
    $pdf->SetFont('Arial', 'B', 10);
    $division = $result['division'] ?? 'X';
    $displayDivision = ($division === 'Ungraded' ? 'U' : ($division === 'X' ? 'X' : substr($division, -1)));
    $aggregates = ($result['division'] === 'X') ? '-' : ($result['aggregates'] ?? '-');
    
    // Position all elements at the SEC line location with enhanced security hash
    $enhanced_security_hash = substr(md5($result['index_no'] . $exam_year . $school['center_no'] . $slip_id), 0, 10);
    $pdf->SetXY($x, $y + $height - 4);

    // Summary with better spacing and alignment - all on same horizontal line
    $pdf->Cell($width/2 - 25, 3, "AGGREGATES: " . $aggregates, 0, 0, 'L');
    $pdf->Cell(50, 3, 'SEC: ' . strtoupper($enhanced_security_hash), 0, 0, 'C');
    $pdf->Cell($width/2 - 25, 3, "DIVISION: " . $displayDivision, 0, 1, 'R');
    
    $pdf->SetTextColor(0, 0, 0);
}

// Create PDF instance
$pdf = new EnhancedPDF();
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false);

// Slip dimensions
$page_height = 297; // A4 height in mm
$page_width = 210;  // A4 width in mm
$margin_top = 8;
$margin_bottom = 8;
$margin_left = 10;
$margin_right = 10;

$available_height = $page_height - $margin_top - $margin_bottom;
$slip_height = ($available_height - 6) / 3; // 6mm total for separators (3mm each)
$slip_width = $page_width - $margin_left - $margin_right;

$slips_per_page = 3;
$current_slip_on_page = 0;

// Check if no schools are found
if (empty($schools)) {
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, strtoupper($exam_body), 0, 1, 'C');
    $pdf->Cell(0, 10, "PLE MOCK EXAMINATIONS $exam_year", 0, 1, 'C');
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 10, 'No schools with complete results found for the specified exam year.', 0, 1, 'C');
} else {
    foreach ($schools as $school) {
        $school_id = $school['id'];

        // Modified query to exclude candidates with all subjects marked as ABSENT
        $results_query = "
            SELECT 
                sr.candidate_index_number AS index_no,
                sr.candidate_name,
                MAX(CASE WHEN s.code = 'ENG' THEN sr.marks END) AS eng_marks,
                MAX(CASE WHEN s.code = 'ENG' THEN sr.grade END) AS eng_grade,
                MAX(CASE WHEN s.code = 'SCI' THEN sr.marks END) AS sci_marks,
                MAX(CASE WHEN s.code = 'SCI' THEN sr.grade END) AS sci_grade,
                MAX(CASE WHEN s.code = 'MTC' THEN sr.marks END) AS mtc_marks,
                MAX(CASE WHEN s.code = 'MTC' THEN sr.grade END) AS mtc_grade,
                MAX(CASE WHEN s.code = 'SST' THEN sr.marks END) AS sst_marks,
                MAX(CASE WHEN s.code = 'SST' THEN sr.grade END) AS sst_grade,
                CASE 
                    WHEN absent.candidate_id IS NOT NULL THEN 'X' 
                    ELSE COALESCE(cr.aggregates, '0') 
                END AS aggregates,
                CASE 
                    WHEN absent.candidate_id IS NOT NULL THEN 'X' 
                    ELSE COALESCE(cr.division, 'Ungraded') 
                END AS division,
                absent.absent_count
            FROM school_results sr
            JOIN subjects s ON sr.subject_code = s.code
            LEFT JOIN candidate_results cr ON sr.candidate_id = cr.candidate_id AND sr.exam_year_id = cr.exam_year_id
            LEFT JOIN (
                SELECT candidate_id, exam_year_id, COUNT(*) as absent_count
                FROM marks
                WHERE exam_year_id = ? AND status = 'ABSENT'
                GROUP BY candidate_id, exam_year_id
            ) absent ON sr.candidate_id = absent.candidate_id AND sr.exam_year_id = absent.exam_year_id
            WHERE sr.school_id = ? AND sr.exam_year_id = ?
            GROUP BY sr.candidate_id, sr.candidate_index_number, sr.candidate_name, cr.aggregates, cr.division, absent.candidate_id, absent.absent_count
            HAVING COUNT(DISTINCT sr.subject_code) = 4 
                AND (absent.absent_count IS NULL OR absent.absent_count < 4)
            ORDER BY sr.candidate_index_number
        ";
        $results = [];
        $stmt = $conn->prepare($results_query);
        if ($stmt === false) {
            error_log("Prepare failed for results_query (school_id: $school_id): " . $conn->error);
            continue;
        }
        $stmt->bind_param('iii', $exam_year_id, $school_id, $exam_year_id);
        $stmt->execute();
        $results_result = $stmt->get_result();
        while ($row = $results_result->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();

        if (empty($results)) {
            continue; // Skip schools with no candidates (after excluding all-absent)
        }

        // Generate result slips
        foreach ($results as $index => $result) {
            if ($current_slip_on_page == 0) {
                $pdf->AddPage();
            }
            
            $separator_spacing = 3; // 3mm between slips
            $y_position = $margin_top + ($current_slip_on_page * ($slip_height + $separator_spacing));
            
            drawEnhancedResultSlip($pdf, $result, $school, $exam_body, $exam_year, $margin_left, $y_position, $slip_width, $slip_height);
            
            if ($current_slip_on_page < 2 && ($index + 1) < count($results)) {
                $separator_y = $y_position + $slip_height + 1;
                $pdf->SetLineWidth(0.2);
                $pdf->SetDrawColor(200, 200, 200);
                $pdf->Line($margin_left, $separator_y, $page_width - $margin_right, $separator_y);
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->SetLineWidth(0.2);
            }
            
            $current_slip_on_page++;
            if ($current_slip_on_page >= $slips_per_page) {
                $current_slip_on_page = 0;
            }
        }
    }
}

// Generate filename
$filename = "All_Schools_Slips_{$exam_year}.pdf";

// Clear output buffer and set headers
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf->Output('S')));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output PDF
echo $pdf->Output('S');
exit();
?>