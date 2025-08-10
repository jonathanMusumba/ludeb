<?php
session_start();
require_once 'db_connect.php';
require_once 'layout.php';
require_once '../lib/fpdf.php';
require_once '../lib/phpword/src/PhpWord/Autoloader.php';

// Register PHPWord autoloader
\PhpOffice\PhpWord\Autoloader::register();

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Restrict to Examination Administrators
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Examination Administrator') {
    header("Location: ../login.php");
    exit();
}

$exam_year_id = isset($_GET['exam_year_id']) ? (int)$_GET['exam_year_id'] : 0;
error_log("detailed_report.php: Accessing with exam_year_id=$exam_year_id", 3, __DIR__ . '/logs/setup_errors.log');

if (!$exam_year_id) {
    $content = '<div class="alert alert-danger fade-in">Error: No exam year selected. Please select an exam year from the District Results page.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => 'Luuka Examination Board',
        'exam_year' => 'N/A',
        'username' => $_SESSION['username'] ?? 'Admin',
        'exam_year_id' => 0
    ]);
    exit();
}

// Check database connection
if ($conn->connect_error) {
    error_log("detailed_report.php: Database connection failed: " . $conn->connect_error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Database connection failed. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => 'Luuka Examination Board',
        'exam_year' => 'N/A',
        'username' => $_SESSION['username'] ?? 'Admin',
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}

// Fetch exam body and year details
$exam_body_query = "SELECT board_name FROM settings WHERE id = 1";
$exam_body_result = $conn->query($exam_body_query);
$exam_body = ($exam_body_result->num_rows > 0) ? $exam_body_result->fetch_assoc()['board_name'] : 'Luuka Examination Board';

$exam_year_query = "SELECT exam_year FROM exam_years WHERE id = ?";
$stmt = $conn->prepare($exam_year_query);
if ($stmt === false) {
    error_log("detailed_report.php: Prepare failed for exam_year_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to fetch exam year data. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => 'N/A',
        'username' => $_SESSION['username'] ?? 'Admin',
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$stmt->bind_param('i', $exam_year_id);
if (!$stmt->execute()) {
    error_log("detailed_report.php: Execute failed for exam_year_query: " . $stmt->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to execute exam year query. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => 'N/A',
        'username' => $_SESSION['username'] ?? 'Admin',
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$exam_year_result = $stmt->get_result();
$exam_year = ($exam_year_result->num_rows > 0) ? $exam_year_result->fetch_assoc()['exam_year'] : 'N/A';
$stmt->close();

// Fetch username
$username_query = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($username_query);
if ($stmt === false) {
    error_log("detailed_report.php: Prepare failed for username_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    $username = 'Admin';
} else {
    $stmt->bind_param('i', $_SESSION['user_id']);
    if (!$stmt->execute()) {
        error_log("detailed_report.php: Execute failed for username_query: " . $stmt->error, 3, __DIR__ . '/logs/setup_errors.log');
        $username = 'Admin';
    } else {
        $username_result = $stmt->get_result();
        $username = ($username_result->num_rows > 0) ? $username_result->fetch_assoc()['username'] : 'Admin';
    }
    $stmt->close();
}

// Fetch division summary
$division_query = "
    SELECT 
        cr.division,
        COUNT(*) as total_candidates,
        SUM(CASE WHEN c.sex = 'Male' THEN 1 ELSE 0 END) as male,
        SUM(CASE WHEN c.sex = 'Female' THEN 1 ELSE 0 END) as female
    FROM candidate_results cr
    JOIN candidates c ON cr.candidate_id = c.id AND cr.exam_year_id = c.exam_year_id
    WHERE cr.exam_year_id = ?
    GROUP BY cr.division
";
$divisions = [
    'Division 1' => ['total' => 0, 'male' => 0, 'female' => 0],
    'Division 2' => ['total' => 0, 'male' => 0, 'female' => 0],
    'Division 3' => ['total' => 0, 'male' => 0, 'female' => 0],
    'Division 4' => ['total' => 0, 'male' => 0, 'female' => 0],
    'Ungraded' => ['total' => 0, 'male' => 0, 'female' => 0],
    'X' => ['total' => 0, 'male' => 0, 'female' => 0]
];

// Check if tables exist
$tables_check = $conn->query("SHOW TABLES LIKE 'candidate_results'");
if ($tables_check->num_rows == 0) {
    error_log("detailed_report.php: Table candidate_results does not exist", 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Database table candidate_results is missing. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}

$stmt = $conn->prepare($division_query);
if ($stmt === false) {
    error_log("detailed_report.php: Prepare failed for division_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to fetch division data. MySQL Error: ' . htmlspecialchars($conn->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$stmt->bind_param('i', $exam_year_id);
if (!$stmt->execute()) {
    error_log("detailed_report.php: Execute failed for division_query: " . $stmt->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to execute division query. MySQL Error: ' . htmlspecialchars($stmt->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $division = $row['division'] ?: 'X';
    if (isset($divisions[$division])) {
        $divisions[$division]['total'] = $row['total_candidates'];
        $divisions[$division]['male'] = $row['male'];
        $divisions[$division]['female'] = $row['female'];
    }
}
$stmt->close();

// ENHANCED: Fetch school performance with proper ranking by pass percentage
$school_query = "
    SELECT 
        s.id, s.school_name, st.type,
        COUNT(CASE WHEN cr.division = 'Division 1' AND c.sex = 'Male' THEN 1 END) as div1_male,
        COUNT(CASE WHEN cr.division = 'Division 1' AND c.sex = 'Female' THEN 1 END) as div1_female,
        COUNT(CASE WHEN cr.division = 'Division 2' AND c.sex = 'Male' THEN 1 END) as div2_male,
        COUNT(CASE WHEN cr.division = 'Division 2' AND c.sex = 'Female' THEN 1 END) as div2_female,
        COUNT(CASE WHEN cr.division = 'Division 3' AND c.sex = 'Male' THEN 1 END) as div3_male,
        COUNT(CASE WHEN cr.division = 'Division 3' AND c.sex = 'Female' THEN 1 END) as div3_female,
        COUNT(CASE WHEN cr.division = 'Division 4' AND c.sex = 'Male' THEN 1 END) as div4_male,
        COUNT(CASE WHEN cr.division = 'Division 4' AND c.sex = 'Female' THEN 1 END) as div4_female,
        COUNT(CASE WHEN cr.division = 'Ungraded' AND c.sex = 'Male' THEN 1 END) as ungraded_male,
        COUNT(CASE WHEN cr.division = 'Ungraded' AND c.sex = 'Female' THEN 1 END) as ungraded_female,
        COUNT(CASE WHEN cr.division = 'X' AND c.sex = 'Male' THEN 1 END) as x_male,
        COUNT(CASE WHEN cr.division = 'X' AND c.sex = 'Female' THEN 1 END) as x_female,
        COUNT(CASE WHEN cr.division IN ('Division 1', 'Division 2', 'Division 3', 'Division 4') THEN 1 END) as pass_count,
        COUNT(CASE WHEN cr.division = 'Ungraded' THEN 1 END) as ungraded_count,
        COUNT(CASE WHEN cr.division = 'X' THEN 1 END) as x_count,
        COUNT(*) as total_candidates,
        MIN(CASE WHEN cr.division = 'Division 1' AND cr.aggregates > 0 THEN cr.aggregates END) as best_aggregate,
        COUNT(CASE WHEN cr.division = 'Division 1' THEN 1 END) as division_1_count
    FROM schools s
    JOIN school_types st ON s.school_type_id = st.id
    LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
    WHERE s.status = 'Active'
    GROUP BY s.id, s.school_name, st.type
    HAVING COUNT(*) > 0
    ORDER BY (COUNT(CASE WHEN cr.division IN ('Division 1', 'Division 2', 'Division 3', 'Division 4') THEN 1 END) / COUNT(*)) DESC,
             COUNT(CASE WHEN cr.division = 'Division 1' THEN 1 END) DESC,
             s.school_name ASC
";
$schools = [];
$stmt = $conn->prepare($school_query);
if ($stmt === false) {
    error_log("detailed_report.php: Prepare failed for school_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to fetch school data. MySQL Error: ' . htmlspecialchars($conn->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$stmt->bind_param('ii', $exam_year_id, $exam_year_id);
if (!$stmt->execute()) {
    error_log("detailed_report.php: Execute failed for school_query: " . $stmt->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to execute school query. MySQL Error: ' . htmlspecialchars($stmt->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$result = $stmt->get_result();
$rank = 1;
while ($row = $result->fetch_assoc()) {
    $total = $row['total_candidates'];
    $row['pass_percentage'] = $total > 0 ? round(($row['pass_count'] / $total) * 100, 1) : 0;
    $row['fail_percentage'] = $total > 0 ? round(($row['ungraded_count'] + $row['x_count']) / $total * 100, 1) : 0;
    $row['rank'] = $rank++; // Add ranking based on pass percentage
    $schools[] = $row;
}
$stmt->close();

// Derive best schools (top 10)
$best_schools = array_slice($schools, 0, 10);

// Derive worst schools (bottom 10 by fail percentage)
$worst_schools_temp = $schools;
usort($worst_schools_temp, function($a, $b) {
    return $b['fail_percentage'] <=> $a['fail_percentage'];
});
$worst_schools = array_slice($worst_schools_temp, 0, 10);

// Derive schools with first grades, ordered by Division 1 count and best aggregate
$first_grade_schools_query = "
    SELECT 
        s.id,
        s.school_name,
        st.type,
        COUNT(CASE WHEN cr.division = 'Division 1' THEN 1 END) as division_1_count,
        MIN(CASE WHEN cr.division = 'Division 1' AND cr.aggregates > 0 THEN cr.aggregates END) as best_aggregate,
        COUNT(*) as total_candidates
    FROM schools s
    JOIN school_types st ON s.school_type_id = st.id
    LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
    WHERE s.status = 'Active' AND cr.division = 'Division 1'
    GROUP BY s.id, s.school_name, st.type
    HAVING COUNT(CASE WHEN cr.division = 'Division 1' THEN 1 END) > 0
    ORDER BY division_1_count DESC, best_aggregate ASC
";
$first_grade_schools = [];
$stmt = $conn->prepare($first_grade_schools_query);
if ($stmt === false) {
    error_log("detailed_report.php: Prepare failed for first_grade_schools_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to fetch schools with first grades. MySQL Error: ' . htmlspecialchars($conn->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$stmt->bind_param('ii', $exam_year_id, $exam_year_id);
if (!$stmt->execute()) {
    error_log("detailed_report.php: Execute failed for first_grade_schools_query: " . $stmt->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to execute first grade schools query. MySQL Error: ' . htmlspecialchars($stmt->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$result = $stmt->get_result();
$rank = 1;
while ($row = $result->fetch_assoc()) {
    $row['rank'] = $rank++;
    $first_grade_schools[] = $row;
}
$stmt->close();

// ENHANCED: Fetch best candidates with proper tie-breaking using English and Mathematics marks
$best_candidates_query = "
    SELECT 
        c.index_number,
        c.candidate_name,
        cr.aggregates,
        s.school_name,
        COALESCE(m_eng.mark, 0) as eng_mark,
        COALESCE(m_mtc.mark, 0) as mtc_mark
    FROM candidates c
    JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = c.exam_year_id
    JOIN schools s ON c.school_id = s.id
    LEFT JOIN marks m_eng ON c.id = m_eng.candidate_id 
        AND m_eng.exam_year_id = c.exam_year_id 
        AND m_eng.subject_id = (SELECT id FROM subjects WHERE code = 'ENG' LIMIT 1)
    LEFT JOIN marks m_mtc ON c.id = m_mtc.candidate_id 
        AND m_mtc.exam_year_id = c.exam_year_id 
        AND m_mtc.subject_id = (SELECT id FROM subjects WHERE code = 'MTC' LIMIT 1)
    WHERE cr.exam_year_id = ? AND cr.aggregates >= 4 AND cr.aggregates <= 36
    ORDER BY cr.aggregates ASC, 
             m_eng.mark DESC, 
             m_mtc.mark DESC,
             c.candidate_name ASC
    LIMIT 10
";
$best_candidates = [];
$stmt = $conn->prepare($best_candidates_query);
if ($stmt === false) {
    error_log("detailed_report.php: Prepare failed for best_candidates_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to fetch best candidates data. MySQL Error: ' . htmlspecialchars($conn->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$stmt->bind_param('i', $exam_year_id);
if (!$stmt->execute()) {
    error_log("detailed_report.php: Execute failed for best_candidates_query: " . $stmt->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to execute best candidates query. MySQL Error: ' . htmlspecialchars($stmt->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$result = $stmt->get_result();
$rank = 1;
while ($row = $result->fetch_assoc()) {
    $row['rank'] = $rank++;
    $best_candidates[] = $row;
    // Log the fetched candidates for debugging
    error_log("detailed_report.php: Fetched candidate: " . $row['candidate_name'] . " with aggregate: " . $row['aggregates'] . ", ENG: " . $row['eng_mark'] . ", MTC: " . $row['mtc_mark'], 3, __DIR__ . '/logs/setup_errors.log');
}
$stmt->close();

// Performance by Gender
$gender_query = "
    SELECT 
        c.sex AS gender,
        COUNT(CASE WHEN cr.division = 'Division 1' THEN 1 END) as div1,
        COUNT(CASE WHEN cr.division = 'Division 2' THEN 1 END) as div2,
        COUNT(CASE WHEN cr.division = 'Division 3' THEN 1 END) as div3,
        COUNT(CASE WHEN cr.division = 'Division 4' THEN 1 END) as div4,
        COUNT(CASE WHEN cr.division = 'Ungraded' THEN 1 END) as ungraded,
        COUNT(CASE WHEN cr.division = 'X' THEN 1 END) as x,
        COUNT(*) as total
    FROM candidates c
    LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
    WHERE c.exam_year_id = ?
    GROUP BY c.sex
";
$gender_performance = ['Male' => [], 'Female' => []];
$stmt = $conn->prepare($gender_query);
if ($stmt === false) {
    error_log("detailed_report.php: Prepare failed for gender_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to fetch gender performance data due to a database issue. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$stmt->bind_param('ii', $exam_year_id, $exam_year_id);
if (!$stmt->execute()) {
    error_log("detailed_report.php: Execute failed for gender_query: " . $stmt->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to execute gender query. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $gender = $row['gender'] == 'Male' ? 'Male' : 'Female';
    $gender_performance[$gender] = $row;
}
$stmt->close();

// Subject Performance
$subject_query = "
    SELECT 
        s.code,
        SUM(CASE WHEN g.grade IN ('D1', 'D2') AND c.sex = 'Male' THEN 1 ELSE 0 END) as male_dist,
        SUM(CASE WHEN g.grade IN ('D1', 'D2') AND c.sex = 'Female' THEN 1 ELSE 0 END) as female_dist,
        SUM(CASE WHEN g.grade IN ('D1', 'D2') THEN 1 ELSE 0 END) as dist_total,
        SUM(CASE WHEN g.grade IN ('C3', 'C4', 'C5', 'C6') AND c.sex = 'Male' THEN 1 ELSE 0 END) as male_credit,
        SUM(CASE WHEN g.grade IN ('C3', 'C4', 'C5', 'C6') AND c.sex = 'Female' THEN 1 ELSE 0 END) as female_credit,
        SUM(CASE WHEN g.grade IN ('C3', 'C4', 'C5', 'C6') THEN 1 ELSE 0 END) as credit_total,
        SUM(CASE WHEN g.grade IN ('P7', 'P8') AND c.sex = 'Male' THEN 1 ELSE 0 END) as male_pass,
        SUM(CASE WHEN g.grade IN ('P7', 'P8') AND c.sex = 'Female' THEN 1 ELSE 0 END) as female_pass,
        SUM(CASE WHEN g.grade IN ('P7', 'P8') THEN 1 ELSE 0 END) as pass_total,
        SUM(CASE WHEN g.grade = 'F9' AND c.sex = 'Male' THEN 1 ELSE 0 END) as male_fail,
        SUM(CASE WHEN g.grade = 'F9' AND c.sex = 'Female' THEN 1 ELSE 0 END) as female_fail,
        SUM(CASE WHEN g.grade = 'F9' THEN 1 ELSE 0 END) as fail_total
    FROM marks m
    JOIN subjects s ON m.subject_id = s.id
    JOIN candidates c ON m.candidate_id = c.id
    LEFT JOIN grading g ON m.mark >= g.range_from AND m.mark <= g.range_to AND g.subject_id = s.id AND g.exam_year_id = m.exam_year_id
    WHERE m.exam_year_id = ?
    GROUP BY s.code
";
$subject_performance = [];
$stmt = $conn->prepare($subject_query);
if ($stmt === false) {
    error_log("detailed_report.php: Prepare failed for subject_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to fetch subject performance data. MySQL Error: ' . htmlspecialchars($conn->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$stmt->bind_param('i', $exam_year_id);
if (!$stmt->execute()) {
    error_log("detailed_report.php: Execute failed for subject_query: " . $stmt->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to execute subject query. MySQL Error: ' . htmlspecialchars($stmt->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subject_performance[$row['code']] = $row;
}
$stmt->close();

// Subcounty Performance
$subcounty_query = "
    SELECT 
        sc.subcounty,
        COUNT(CASE WHEN cr.division = 'Division 1' THEN 1 END) as div1,
        COUNT(CASE WHEN cr.division = 'Division 2' THEN 1 END) as div2,
        COUNT(CASE WHEN cr.division = 'Division 3' THEN 1 END) as div3,
        COUNT(CASE WHEN cr.division = 'Division 4' THEN 1 END) as div4,
        COUNT(CASE WHEN cr.division = 'Ungraded' THEN 1 END) as ungraded,
        COUNT(CASE WHEN cr.division = 'X' THEN 1 END) as absentees,
        COUNT(*) as total_candidates,
        SUM(CASE WHEN cr.division IN ('Division 1', 'Division 2', 'Division 3', 'Division 4') THEN 1 ELSE 0 END) as pass_count,
        SUM(CASE WHEN cr.division IN ('Ungraded', 'X') THEN 1 ELSE 0 END) as fail_count
    FROM subcounties sc
    JOIN schools s ON sc.id = s.subcounty_id
    JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
    WHERE c.exam_year_id = ?
    GROUP BY sc.id, sc.subcounty
    HAVING COUNT(*) > 0
    ORDER BY (SUM(CASE WHEN cr.division IN ('Division 1', 'Division 2', 'Division 3', 'Division 4') THEN 1 ELSE 0 END) / COUNT(*)) DESC
";
$subcounties_performance = [];
$stmt = $conn->prepare($subcounty_query);
if ($stmt === false) {
    error_log("detailed_report.php: Prepare failed for subcounty_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to fetch subcounty performance data. MySQL Error: ' . htmlspecialchars($conn->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$stmt->bind_param('iii', $exam_year_id, $exam_year_id, $exam_year_id);
if (!$stmt->execute()) {
    error_log("detailed_report.php: Execute failed for subcounty_query: " . $stmt->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to execute subcounty query. MySQL Error: ' . htmlspecialchars($stmt->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$result = $stmt->get_result();
$rank = 1;
while ($row = $result->fetch_assoc()) {
    $row['pass_percentage'] = $row['total_candidates'] > 0 ? round(($row['pass_count'] / $row['total_candidates']) * 100, 1) : 0;
    $row['fail_percentage'] = $row['total_candidates'] > 0 ? round(($row['fail_count'] / $row['total_candidates']) * 100, 1) : 0;
    $row['rank'] = $rank++;
    $subcounties_performance[] = $row;
}
$stmt->close();

// Performance of Schools in Their Subcounties
$schools_by_subcounty_query = "
    SELECT 
        sc.subcounty,
        s.id as school_id,
        s.school_name,
        st.type,
        COUNT(CASE WHEN cr.division = 'Division 1' THEN 1 END) as div1,
        COUNT(CASE WHEN cr.division = 'Division 2' THEN 1 END) as div2,
        COUNT(CASE WHEN cr.division = 'Division 3' THEN 1 END) as div3,
        COUNT(CASE WHEN cr.division = 'Division 4' THEN 1 END) as div4,
        COUNT(CASE WHEN cr.division = 'Ungraded' THEN 1 END) as ungraded,
        COUNT(CASE WHEN cr.division = 'X' THEN 1 END) as absentees,
        COUNT(*) as total_candidates,
        SUM(CASE WHEN cr.division IN ('Division 1', 'Division 2', 'Division 3', 'Division 4') THEN 1 ELSE 0 END) as pass_count,
        SUM(CASE WHEN cr.division IN ('Ungraded', 'X') THEN 1 ELSE 0 END) as fail_count
    FROM subcounties sc
    JOIN schools s ON sc.id = s.subcounty_id
    JOIN school_types st ON s.school_type_id = st.id
    JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
    LEFT JOIN candidate_results cr ON c.id = cr.candidate_id AND cr.exam_year_id = ?
    WHERE c.exam_year_id = ?
    GROUP BY sc.id, sc.subcounty, s.id, s.school_name, st.type
    HAVING COUNT(*) > 0
    ORDER BY sc.subcounty, (SUM(CASE WHEN cr.division IN ('Division 1', 'Division 2', 'Division 3', 'Division 4') THEN 1 ELSE 0 END) / COUNT(*)) DESC
";
$schools_by_subcounty = [];
$stmt = $conn->prepare($schools_by_subcounty_query);
if ($stmt === false) {
    error_log("detailed_report.php: Prepare failed for schools_by_subcounty_query: " . $conn->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to fetch schools by subcounty data. MySQL Error: ' . htmlspecialchars($conn->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$stmt->bind_param('iii', $exam_year_id, $exam_year_id, $exam_year_id);
if (!$stmt->execute()) {
    error_log("detailed_report.php: Execute failed for schools_by_subcounty_query: " . $stmt->error, 3, __DIR__ . '/logs/setup_errors.log');
    $content = '<div class="alert alert-danger fade-in">Error: Unable to execute schools by subcounty query. MySQL Error: ' . htmlspecialchars($stmt->error) . '. Please contact the administrator.</div>';
    renderLayout('Error', [
        'title' => 'Error',
        'content' => $content,
        'current_page' => 'detailed_report',
        'exam_body' => $exam_body,
        'exam_year' => $exam_year,
        'username' => $username,
        'exam_year_id' => $exam_year_id
    ]);
    exit();
}
$result = $stmt->get_result();
$current_subcounty = '';
$subcounty_schools = [];
while ($row = $result->fetch_assoc()) {
    if ($row['subcounty'] !== $current_subcounty) {
        if ($current_subcounty !== '') {
            $schools_by_subcounty[$current_subcounty] = $subcounty_schools;
            $subcounty_schools = [];
        }
        $current_subcounty = $row['subcounty'];
    }
    $row['pass_percentage'] = $row['total_candidates'] > 0 ? round(($row['pass_count'] / $row['total_candidates']) * 100, 1) : 0;
    $row['fail_percentage'] = $row['total_candidates'] > 0 ? round(($row['fail_count'] / $row['total_candidates']) * 100, 1) : 0;
    $subcounty_schools[] = $row;
}
if ($current_subcounty !== '') {
    $schools_by_subcounty[$current_subcounty] = $subcounty_schools;
}
$stmt->close();

// Chart data for layout
$chart_data = [
    'categories' => ['Division 1', 'Division 2', 'Division 3', 'Division 4', 'Ungraded', 'X'],
    'male' => array_column($divisions, 'male'),
    'female' => array_column($divisions, 'female')
];
$pie_chart_data = array_map(function($div, $data) {
    return ['name' => $div, 'y' => $data['total']];
}, array_keys($divisions), $divisions);
$subject_chart_data = [
    'categories' => array_keys($subject_performance),
    'data' => array_map(function($sub) {
        $total = $sub['dist_total'] + $sub['credit_total'] + $sub['pass_total'] + $sub['fail_total'];
        return $total > 0 ? round(($sub['dist_total'] + $sub['credit_total']) / $total * 100, 1) : 0;
    }, $subject_performance)
];
$subcounty_chart_data = [
    'categories' => array_column($subcounties_performance, 'subcounty'),
    'pass_percentages' => array_column($subcounties_performance, 'pass_percentage')
];

// Handle PDF download
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    class DetailedReportPDF extends FPDF {
        public $exam_body, $exam_year;
        public function Header() {
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 8, strtoupper($this->exam_body), 0, 1, 'L');
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 6, "PLE MOCK EXAMINATIONS $this->exam_year - Detailed Report", 0, 1, 'L');
            $this->Ln(8);
        }
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $download_time = date('d M, Y H:i');
            $this->Cell(60, 10, "Mock Results $this->exam_year", 0, 0, 'L');
            $this->Cell(70, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
            $this->Cell(60, 10, "Download time: $download_time", 0, 0, 'R');
        }
    }
    $pdf = new DetailedReportPDF();
    $pdf->exam_body = $exam_body;
    $pdf->exam_year = $exam_year;
    $pdf->AliasNbPages();
    $pdf->SetMargins(10, 15, 10);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    // General Performance: Divisions Summary
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'General Performance', 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'Divisions Summary', 0, 1, 'L');
    $pdf->SetFillColor(200, 220, 255);
    $headers = ['Division', '1', '2', '3', '4', 'U', 'X', 'Total'];
    $widths = [25, 15, 15, 15, 15, 15, 15, 20];
    foreach ($headers as $i => $header) {
        $pdf->Cell($widths[$i], 6, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 9);
    $total_candidates = array_sum(array_column($divisions, 'total'));
    $data = [
        'Candidates',
        $divisions['Division 1']['total'],
        $divisions['Division 2']['total'],
        $divisions['Division 3']['total'],
        $divisions['Division 4']['total'],
        $divisions['Ungraded']['total'],
        $divisions['X']['total'],
        $total_candidates
    ];
    foreach ($data as $i => $value) {
        $pdf->Cell($widths[$i], 6, $value, 1, 0, 'C');
    }
    $pdf->Ln(10);

    // Division Summary by Gender
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'Division Summary by Gender', 0, 1, 'L');
    $headers = ['Division', 'Male', 'Female', 'Total'];
    $widths = [25, 25, 25, 25];
    $pdf->SetFillColor(200, 220, 255);
    foreach ($headers as $i => $header) {
        $pdf->Cell($widths[$i], 6, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 9);
    foreach ($divisions as $div => $data) {
        $pdf->Cell($widths[0], 6, $div, 1, 0, 'L');
        $pdf->Cell($widths[1], 6, $data['male'], 1, 0, 'C');
        $pdf->Cell($widths[2], 6, $data['female'], 1, 0, 'C');
        $pdf->Cell($widths[3], 6, $data['total'], 1, 0, 'C');
        $pdf->Ln();
    }
    $pdf->Ln(10);

    // Subcounty Performance
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Subcounty Performance', 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'Performance by Subcounty', 0, 1, 'L');
    $headers = ['Rank', 'Subcounty', 'Div 1', 'Div 2', 'Div 3', 'Div 4', 'U', 'X', 'Total', '% Pass', '% Fail'];
    $widths = [15, 40, 15, 15, 15, 15, 15, 15, 20, 20, 20];
    $pdf->SetFillColor(200, 220, 255);
    foreach ($headers as $i => $header) {
        $pdf->Cell($widths[$i], 6, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 9);
    foreach ($subcounties_performance as $subcounty) {
        $pdf->Cell($widths[0], 6, $subcounty['rank'], 1, 0, 'C');
        $pdf->Cell($widths[1], 6, substr($subcounty['subcounty'], 0, 20), 1, 0, 'L');
        $pdf->Cell($widths[2], 6, $subcounty['div1'], 1, 0, 'C');
        $pdf->Cell($widths[3], 6, $subcounty['div2'], 1, 0, 'C');
        $pdf->Cell($widths[4], 6, $subcounty['div3'], 1, 0, 'C');
        $pdf->Cell($widths[5], 6, $subcounty['div4'], 1, 0, 'C');
        $pdf->Cell($widths[6], 6, $subcounty['ungraded'], 1, 0, 'C');
        $pdf->Cell($widths[7], 6, $subcounty['absentees'], 1, 0, 'C');
        $pdf->Cell($widths[8], 6, $subcounty['total_candidates'], 1, 0, 'C');
        $pdf->Cell($widths[9], 6, $subcounty['pass_percentage'], 1, 0, 'C');
        $pdf->Cell($widths[10], 6, $subcounty['fail_percentage'], 1, 0, 'C');
        $pdf->Ln();
    }
    $pdf->Ln(10);

    // School Performance: General Performance by Schools
    $pdf->AddPage('L');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'School Performance', 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'General Performance by Schools (Ranked by Pass %)', 0, 1, 'L');
    $headers = ['Rank', 'School Name', 'School Type', 'Div 1 (M|F)', 'Div 2 (M|F)', 'Div 3 (M|F)', 'Div 4 (M|F)', 'U (M|F)', 'X (M|F)', 'Total', '% Pass', '% Fail'];
    $widths = [15, 50, 20, 25, 25, 25, 25, 25, 25, 20, 20, 20];
    $pdf->SetFillColor(200, 220, 255);
    foreach ($headers as $i => $header) {
        $pdf->Cell($widths[$i], 6, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 9);
    foreach ($schools as $school) {
        $pdf->Cell($widths[0], 6, $school['rank'], 1, 0, 'C');
        $pdf->Cell($widths[1], 6, substr($school['school_name'], 0, 25), 1, 0, 'L');
        $pdf->Cell($widths[2], 6, $school['type'], 1, 0, 'C');
        $pdf->Cell($widths[3], 6, $school['div1_male'] . '|' . $school['div1_female'], 1, 0, 'C');
        $pdf->Cell($widths[4], 6, $school['div2_male'] . '|' . $school['div2_female'], 1, 0, 'C');
        $pdf->Cell($widths[5], 6, $school['div3_male'] . '|' . $school['div3_female'], 1, 0, 'C');
        $pdf->Cell($widths[6], 6, $school['div4_male'] . '|' . $school['div4_female'], 1, 0, 'C');
        $pdf->Cell($widths[7], 6, $school['ungraded_male'] . '|' . $school['ungraded_female'], 1, 0, 'C');
        $pdf->Cell($widths[8], 6, $school['x_male'] . '|' . $school['x_female'], 1, 0, 'C');
        $pdf->Cell($widths[9], 6, $school['total_candidates'], 1, 0, 'C');
        $pdf->Cell($widths[10], 6, $school['pass_percentage'], 1, 0, 'C');
        $pdf->Cell($widths[11], 6, $school['fail_percentage'], 1, 0, 'C');
        $pdf->Ln();
    }

    // ENHANCED: Best Candidates with improved ranking
    $pdf->AddPage('P');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Candidates Performance', 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'Best Candidates (Top 10 by Aggregates with Tie-breakers)', 0, 1, 'L');
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 6, 'Note: Ranked by Aggregates (4=best), then English marks, then Mathematics marks', 0, 1, 'L');
    $headers = ['Rank', 'Index No', 'Candidate Name', 'Aggregates', 'ENG', 'MTC', 'School'];
    $widths = [12, 20, 45, 18, 15, 15, 45];
    $pdf->SetFillColor(200, 220, 255);
    $pdf->SetFont('Arial', 'B', 10);
    foreach ($headers as $i => $header) {
        $pdf->Cell($widths[$i], 6, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 9);
    if (empty($best_candidates)) {
        $pdf->Cell(0, 6, 'No top candidates available.', 1, 1, 'C');
    } else {
        foreach ($best_candidates as $candidate) {
            $pdf->Cell($widths[0], 6, $candidate['rank'], 1, 0, 'C');
            $pdf->Cell($widths[1], 6, $candidate['index_number'], 1, 0, 'C');
            $pdf->Cell($widths[2], 6, substr($candidate['candidate_name'], 0, 25), 1, 0, 'L');
            $pdf->Cell($widths[3], 6, $candidate['aggregates'], 1, 0, 'C');
            $pdf->Cell($widths[4], 6, $candidate['eng_mark'], 1, 0, 'C');
            $pdf->Cell($widths[5], 6, $candidate['mtc_mark'], 1, 0, 'C');
            $pdf->Cell($widths[6], 6, substr($candidate['school_name'], 0, 25), 1, 0, 'L');
            $pdf->Ln();
        }
    }

    // Continue with other sections...
    $filename = "Detailed_Report_$exam_year.pdf";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    ob_clean();
    $pdf->Output($filename, 'D');
    exit;
}

// HTML content for the page
$content = '
<div class="welcome-header fade-in">
    <h2>Detailed Performance Report</h2>
    <p>Comprehensive analysis of examination results for ' . htmlspecialchars($exam_year) . '</p>
</div>

<div class="d-flex justify-content-end mb-4">
    <a href="?exam_year_id=' . $exam_year_id . '&download=pdf" class="btn btn-primary me-2">
        <i class="fas fa-file-pdf me-1"></i> Download PDF
    </a>
    <a href="?exam_year_id=' . $exam_year_id . '&download=word" class="btn btn-primary">
        <i class="fas fa-file-word me-1"></i> Download Word
    </a>
</div>

<!-- General Performance -->
<div class="chart-card fade-in">
    <h5>General Performance</h5>
    <h6>Divisions Summary</h6>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Division</th>
                    <th>1</th>
                    <th>2</th>
                    <th>3</th>
                    <th>4</th>
                    <th>U</th>
                    <th>X</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Candidates</td>';
foreach (['Division 1', 'Division 2', 'Division 3', 'Division 4', 'Ungraded', 'X'] as $div) {
    $content .= '<td>' . $divisions[$div]['total'] . '</td>';
}
$content .= '<td>' . array_sum(array_column($divisions, 'total')) . '</td>
                </tr>
            </tbody>
        </table>
    </div>
    <h6 class="mt-4">Division Summary by Sex</h6>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Division</th>
                    <th>Male</th>
                    <th>Female</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';
foreach ($divisions as $div => $data) {
    $content .= '
                <tr>
                    <td>' . htmlspecialchars($div) . '</td>
                    <td>' . $data['male'] . '</td>
                    <td>' . $data['female'] . '</td>
                    <td>' . $data['total'] . '</td>
                </tr>';
}
$content .= '
            </tbody>
        </table>
    </div>
</div>

<!-- ENHANCED: General Performance by Schools with Ranking -->
<div class="chart-card fade-in">
    <h5>General Performance by Schools (Ranked by Pass %)</h5>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>School Name</th>
                    <th>School Type</th>
                    <th>Div 1 (M|F)</th>
                    <th>Div 2 (M|F)</th>
                    <th>Div 3 (M|F)</th>
                    <th>Div 4 (M|F)</th>
                    <th>Ungraded (M|F)</th>
                    <th>X (M|F)</th>
                    <th>Total Candidates</th>
                    <th>% Pass</th>
                    <th>% Fail</th>
                </tr>
            </thead>
            <tbody>';
foreach ($schools as $school) {
    $content .= '
                <tr class="' . ($school['rank'] <= 10 ? 'table-success' : '') . '">
                    <td><strong>' . $school['rank'] . '</strong></td>
                    <td>' . htmlspecialchars($school['school_name']) . '</td>
                    <td>' . htmlspecialchars($school['type']) . '</td>
                    <td>' . $school['div1_male'] . '|' . $school['div1_female'] . '</td>
                    <td>' . $school['div2_male'] . '|' . $school['div2_female'] . '</td>
                    <td>' . $school['div3_male'] . '|' . $school['div3_female'] . '</td>
                    <td>' . $school['div4_male'] . '|' . $school['div4_female'] . '</td>
                    <td>' . $school['ungraded_male'] . '|' . $school['ungraded_female'] . '</td>
                    <td>' . $school['x_male'] . '|' . $school['x_female'] . '</td>
                    <td>' . $school['total_candidates'] . '</td>
                    <td><strong>' . $school['pass_percentage'] . '%</strong></td>
                    <td>' . $school['fail_percentage'] . '%</td>
                </tr>';
}
$content .= '
            </tbody>
        </table>
    </div>
</div>

<!-- ENHANCED: Candidates Performance with improved ranking -->
<div class="chart-card fade-in">
    <h5>Candidates Performance</h5>
    <h6>Best Candidates (Top 10 with Tie-breakers)</h6>
    <p class="text-muted small">
        <i class="fas fa-info-circle"></i> 
        Ranked by: 1) Aggregates (4 = best, 36 = worst), 2) English marks (highest), 3) Mathematics marks (highest)
    </p>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Index No</th>
                    <th>Candidate Name</th>
                    <th>Aggregates</th>
                    <th>English</th>
                    <th>Mathematics</th>
                    <th>School</th>
                </tr>
            </thead>
            <tbody>';
foreach ($best_candidates as $candidate) {
    $content .= '
                <tr class="' . ($candidate['rank'] <= 3 ? 'table-warning' : '') . '">
                    <td><strong>' . $candidate['rank'] . '</strong></td>
                    <td>' . htmlspecialchars($candidate['index_number']) . '</td>
                    <td>' . htmlspecialchars($candidate['candidate_name']) . '</td>
                    <td><strong>' . $candidate['aggregates'] . '</strong></td>
                    <td>' . $candidate['eng_mark'] . '</td>
                    <td>' . $candidate['mtc_mark'] . '</td>
                    <td>' . htmlspecialchars($candidate['school_name']) . '</td>
                </tr>';
}
$content .= '
            </tbody>
        </table>
    </div>
    
    <h6 class="mt-4">Performance by Sex</h6>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Sex</th>
                    <th>Div 1</th>
                    <th>Div 2</th>
                    <th>Div 3</th>
                    <th>Div 4</th>
                    <th>U</th>
                    <th>X</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';
foreach ($gender_performance as $gender => $data) {
    $content .= '
                <tr>
                    <td>' . htmlspecialchars($gender) . '</td>
                    <td>' . ($data['div1'] ?? 0) . '</td>
                    <td>' . ($data['div2'] ?? 0) . '</td>
                    <td>' . ($data['div3'] ?? 0) . '</td>
                    <td>' . ($data['div4'] ?? 0) . '</td>
                    <td>' . ($data['ungraded'] ?? 0) . '</td>
                    <td>' . ($data['x'] ?? 0) . '</td>
                    <td>' . ($data['total'] ?? 0) . '</td>
                </tr>';
}
$content .= '
            </tbody>
        </table>
    </div>
</div>

<!-- Subcounty Performance -->
<div class="chart-card fade-in">
    <h5>Subcounty Performance</h5>
    <h6>Performance by Subcounty</h6>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Subcounty</th>
                    <th>Div 1</th>
                    <th>Div 2</th>
                    <th>Div 3</th>
                    <th>Div 4</th>
                    <th>Ungraded</th>
                    <th>Absentees</th>
                    <th>Total</th>
                    <th>% Pass</th>
                    <th>% Fail</th>
                </tr>
            </thead>
            <tbody>';
foreach ($subcounties_performance as $subcounty) {
    $content .= '
                <tr class="' . ($subcounty['rank'] <= 5 ? 'table-success' : '') . '">
                    <td><strong>' . $subcounty['rank'] . '</strong></td>
                    <td>' . htmlspecialchars($subcounty['subcounty']) . '</td>
                    <td>' . $subcounty['div1'] . '</td>
                    <td>' . $subcounty['div2'] . '</td>
                    <td>' . $subcounty['div3'] . '</td>
                    <td>' . $subcounty['div4'] . '</td>
                    <td>' . $subcounty['ungraded'] . '</td>
                    <td>' . $subcounty['absentees'] . '</td>
                    <td>' . $subcounty['total_candidates'] . '</td>
                    <td><strong>' . $subcounty['pass_percentage'] . '%</strong></td>
                    <td>' . $subcounty['fail_percentage'] . '%</td>
                </tr>';
}
$content .= '
            </tbody>
        </table>
    </div>
    <h6 class="mt-4">Subcounty Pass Percentage Chart</h6>
    <div style="height: 400px;">
        <canvas id="subcountyChart"></canvas>
    </div>
</div>

<!-- Performance of Schools in Their Subcounties -->
<div class="chart-card fade-in">
    <h5>Performance of Schools in Their Subcounties</h5>';
foreach ($schools_by_subcounty as $subcounty => $schools) {
    $content .= '
    <h6 class="mt-4">' . htmlspecialchars($subcounty) . '</h6>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>SN</th>
                    <th>School Name</th>
                    <th>School Type</th>
                    <th>Div 1</th>
                    <th>Div 2</th>
                    <th>Div 3</th>
                    <th>Div 4</th>
                    <th>Ungraded</th>
                    <th>Absentees</th>
                    <th>Total</th>
                    <th>% Pass</th>
                    <th>% Fail</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($schools as $i => $school) {
        $content .= '
                <tr>
                    <td>' . ($i + 1) . '</td>
                    <td>' . htmlspecialchars($school['school_name']) . '</td>
                    <td>' . htmlspecialchars($school['type']) . '</td>
                    <td>' . $school['div1'] . '</td>
                    <td>' . $school['div2'] . '</td>
                    <td>' . $school['div3'] . '</td>
                    <td>' . $school['div4'] . '</td>
                    <td>' . $school['ungraded'] . '</td>
                    <td>' . $school['absentees'] . '</td>
                    <td>' . $school['total_candidates'] . '</td>
                    <td>' . $school['pass_percentage'] . '%</td>
                    <td>' . $school['fail_percentage'] . '%</td>
                </tr>';
    }
    $content .= '
            </tbody>
        </table>
    </div>';
}
$content .= '
</div>

<!-- School Performance -->
<div class="chart-card fade-in">
    <h5>School Performance</h5>
    <h6>Best Schools (Top 10)</h6>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>School Name</th>
                    <th>School Type</th>
                    <th>Div 1-4</th>
                    <th>Div U</th>
                    <th>% Pass</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';
foreach ($best_schools as $i => $school) {
    $content .= '
                <tr class="table-success">
                    <td><strong>' . ($i + 1) . '</strong></td>
                    <td>' . htmlspecialchars($school['school_name']) . '</td>
                    <td>' . htmlspecialchars($school['type']) . '</td>
                    <td>' . $school['pass_count'] . '</td>
                    <td>' . $school['ungraded_count'] . '</td>
                    <td><strong>' . $school['pass_percentage'] . '%</strong></td>
                    <td>' . $school['total_candidates'] . '</td>
                </tr>';
}
$content .= '
            </tbody>
        </table>
    </div>
    
    <h6 class="mt-4">Worst Performing Schools (Bottom 10)</h6>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>SN</th>
                    <th>School Name</th>
                    <th>Div 1-4</th>
                    <th>Div U</th>
                    <th>% Fail</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';
foreach ($worst_schools as $i => $school) {
    $content .= '
                <tr class="table-danger">
                    <td>' . ($i + 1) . '</td>
                    <td>' . htmlspecialchars($school['school_name']) . '</td>
                    <td>' . $school['pass_count'] . '</td>
                    <td>' . $school['ungraded_count'] . '</td>
                    <td><strong>' . $school['fail_percentage'] . '%</strong></td>
                    <td>' . $school['total_candidates'] . '</td>
                </tr>';
}
$content .= '
            </tbody>
        </table>
    </div>

    <h6 class="mt-4">Schools with First Grades</h6>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>School Name</th>
                    <th>School Type</th>
                    <th>Div 1</th>
                    <th>Best Agg</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';
foreach ($first_grade_schools as $school) {
    $content .= '
                <tr class="table-info">
                    <td><strong>' . $school['rank'] . '</strong></td>
                    <td>' . htmlspecialchars($school['school_name']) . '</td>
                    <td>' . htmlspecialchars($school['type']) . '</td>
                    <td><strong>' . $school['division_1_count'] . '</strong></td>
                    <td>' . ($school['best_aggregate'] ?? '-') . '</td>
                    <td>' . $school['total_candidates'] . '</td>
                </tr>';
}
$content .= '
            </tbody>
        </table>
    </div>
</div>

<!-- Subject Performance -->
<div class="chart-card fade-in">
    <h5>Subject Performance</h5>';
// Define level keys before the loop
$level_keys = [
    'Distinction (D1-D2)' => 'dist',
    'Credit (C3-C6)' => 'credit',
    'Pass (P7-P8)' => 'pass',
    'Fail (F9)' => 'fail'
];
if (!is_array($level_keys) || empty($level_keys)) {
    $content .= '
    <div class="alert alert-danger fade-in">Error: Subject performance levels are not properly configured.</div>';
} else {
    foreach ($level_keys as $level => $key) {
        $content .= '
        <h6 class="mt-4">At ' . htmlspecialchars($level) . ' Level</h6>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>';
        if (empty($subject_performance)) {
            $content .= '
                    <tr>
                        <td colspan="4">No subject performance data available.</td>
                    </tr>';
        } else {
            foreach ($subject_performance as $sub) {
                $content .= '
                    <tr>
                        <td>' . htmlspecialchars($sub['code']) . '</td>
                        <td>' . ($sub["male_$key"] ?? 0) . '</td>
                        <td>' . ($sub["female_$key"] ?? 0) . '</td>
                        <td>' . ($sub["{$key}_total"] ?? 0) . '</td>
                    </tr>';
            }
        }
        $content .= '
                </tbody>
            </table>
        </div>';
    }
}
$content .= '
    <h6 class="mt-4">Subject Pass Rate (%)</h6>
    <div style="height: 400px;">
        <canvas id="subjectChart"></canvas>
    </div>
</div>

<!-- Charts Section -->
<div class="chart-card fade-in">
    <h5>Performance Charts</h5>
    <h6 class="mt-4">Division Distribution</h6>
    <div style="height: 400px;">
        <canvas id="divisionChart"></canvas>
    </div>
    <h6 class="mt-4">Division Distribution (Pie Chart)</h6>
    <div style="height: 400px;">
        <canvas id="divisionPieChart"></canvas>
    </div>
</div>';

// JavaScript for Charts
$content .= '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Division Bar Chart
    var ctx1 = document.getElementById("divisionChart").getContext("2d");
    new Chart(ctx1, {
        type: "bar",
        data: {
            labels: ' . json_encode($chart_data['categories']) . ',
            datasets: [
                {
                    label: "Male",
                    data: ' . json_encode($chart_data['male']) . ',
                    backgroundColor: "rgba(54, 162, 235, 0.6)",
                    borderColor: "rgba(54, 162, 235, 1)",
                    borderWidth: 1
                },
                {
                    label: "Female",
                    data: ' . json_encode($chart_data['female']) . ',
                    backgroundColor: "rgba(255, 99, 132, 0.6)",
                    borderColor: "rgba(255, 99, 132, 1)",
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: "Number of Candidates"
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: "Division"
                    }
                }
            }
        }
    });

    // Division Pie Chart
    var ctx2 = document.getElementById("divisionPieChart").getContext("2d");
    new Chart(ctx2, {
        type: "pie",
        data: {
            labels: ' . json_encode(array_column($pie_chart_data, 'name')) . ',
            datasets: [{
                data: ' . json_encode(array_column($pie_chart_data, 'y')) . ',
                backgroundColor: [
                    "rgba(54, 162, 235, 0.6)",
                    "rgba(255, 99, 132, 0.6)",
                    "rgba(75, 192, 192, 0.6)",
                    "rgba(255, 205, 86, 0.6)",
                    "rgba(153, 102, 255, 0.6)",
                    "rgba(255, 159, 64, 0.6)"
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Subject Bar Chart
    var ctx3 = document.getElementById("subjectChart").getContext("2d");
    new Chart(ctx3, {
        type: "bar",
        data: {
            labels: ' . json_encode($subject_chart_data['categories']) . ',
            datasets: [{
                label: "Pass Rate (Distinction + Credit)",
                data: ' . json_encode($subject_chart_data['data']) . ',
                backgroundColor: "rgba(75, 192, 192, 0.6)",
                borderColor: "rgba(75, 192, 192, 1)",
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: "Pass Rate (%)"
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: "Subject"
                    }
                }
            }
        }
    });

    // Subcounty Bar Chart
    var ctx4 = document.getElementById("subcountyChart").getContext("2d");
    new Chart(ctx4, {
        type: "bar",
        data: {
            labels: ' . json_encode($subcounty_chart_data['categories']) . ',
            datasets: [{
                label: "Pass Rate",
                data: ' . json_encode($subcounty_chart_data['pass_percentages']) . ',
                backgroundColor: "rgba(153, 102, 255, 0.6)",
                borderColor: "rgba(153, 102, 255, 1)",
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: "Pass Rate (%)"
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: "Subcounty"
                    }
                }
            }
        }
    });
</script>';

// Render the layout
$data = [
    'title' => 'Detailed Report',
    'content' => $content,
    'current_page' => 'detailed_report',
    'exam_body' => $exam_body,
    'exam_year' => $exam_year,
    'username' => $username,
    'chart_data' => $chart_data,
    'pie_chart_data' => $pie_chart_data,
    'subject_chart_data' => $subject_chart_data,
    'divisions' => $divisions,
    'exam_year_id' => $exam_year_id
];

renderLayout('Detailed Report', $data);
?>