<?php
session_start();
require_once '../db_connect.php';
require_once '../../vendor/autoload.php';

use Mpdf\Mpdf;

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header("Location: ../..login.php");
    exit();
}

$center_no = isset($_GET['center_no']) ? trim($_GET['center_no']) : '';
if (empty($center_no)) {
    die("Invalid Center Number");
}

try {
    // Fetch school data
    $stmt = $conn->prepare("SELECT id, school_name FROM schools WHERE center_no = ?");
    $stmt->bind_param("s", $center_no);
    $stmt->execute();
    $school = $stmt->get_result()->fetch_assoc();
    if (!$school) {
        throw new Exception("School not found");
    }
    $school_id = $school['id'];

    // Fetch results
    $stmt = $conn->prepare("SELECT c.index_number, c.candidate_name, s.name as subject_name, 
                            r.mark, r.score, r.division
                            FROM results r
                            JOIN candidates c ON r.candidate_id = c.id
                            JOIN subjects s ON r.subject_id = s.id
                            WHERE r.school_id = ?
                            ORDER BY c.index_number, s.name");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Create PDF
    $mpdf = new Mpdf(['format' => 'A4']);
    $mpdf->SetTitle("Results - {$school['school_name']}");

    $html = '
    <h1 style="text-align: center;">Results - ' . htmlspecialchars($school['school_name']) . '</h1>
    <h3 style="text-align: center;">Center Number: ' . htmlspecialchars($center_no) . '</h3>
    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;" border="1">
        <thead>
            <tr style="background-color: #ffd700;">
                <th style="padding: 8px;">Index Number</th>
                <th style="padding: 8px;">Candidate Name</th>
                <th style="padding: 8px;">Subject</th>
                <th style="padding: 8px;">Mark</th>
                <th style="padding: 8px;">Score</th>
                <th style="padding: 8px;">Division</th>
            </tr>
        </thead>
        <tbody>';

    while ($row = $result->fetch_assoc()) {
        $html .= '
        <tr>
            <td style="padding: 8px;">' . htmlspecialchars($row['index_number']) . '</td>
            <td style="padding: 8px;">' . htmlspecialchars($row['candidate_name']) . '</td>
            <td style="padding: 8px;">' . htmlspecialchars($row['subject_name']) . '</td>
            <td style="padding: 8px;">' . $row['mark'] . '</td>
            <td style="padding: 8px;">' . $row['score'] . '</td>
            <td style="padding: 8px;">' . $row['division'] . '</td>
        </tr>';
    }

    $html .= '
        </tbody>
    </table>';

    $mpdf->WriteHTML($html);

    // Log action
    $user_id = $_SESSION['user_id'];
    $conn->query("CALL log_action('Download Results PDF', $user_id, 'Downloaded PDF results for school ID $school_id')");

    // Output PDF
    $mpdf->Output("results_{$center_no}.pdf", 'D');
} catch (Exception $e) {
    error_log("Download Results PDF Error: " . $e->getMessage(), 3, '../../../setup_errors.log');
    die("Error: " . $e->getMessage());
} finally {
    $conn->close();
}
?>