<?php
ob_start();
session_start();
require_once 'db_connect.php';

// Restrict to System Admin
$allowed_roles = ['System Admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Admin Mark Management';
$log_action = 'Admin Mark Management Page Access';
$log_description = 'Accessed admin mark management page';
$user_id = $_SESSION['user_id'];

// Log page access
$stmt = $conn->prepare("CALL log_action(?, ?, ?)");
$stmt->bind_param("sis", $log_action, $user_id, $log_description);
$stmt->execute();
$stmt->close();

// Fetch board name and active exam year
$stmt = $conn->prepare("SELECT s.board_name, e.exam_year, e.id AS exam_year_id 
                        FROM settings s 
                        JOIN exam_years e ON s.exam_year_id = e.id 
                        WHERE e.status = 'Active' 
                        ORDER BY s.id DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
if (!$row = $result->fetch_assoc()) {
    $error_message = "No active exam year configured in settings. Please contact the system administrator.";
    error_log("No active exam year found in settings table.");
    $stmt->close();
    echo '<div class="alert-enhanced alert-danger"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($error_message) . '</div>';
    require 'layout.php';
    exit();
}
$board_name = $row['board_name'];
$exam_year = $row['exam_year'];
$exam_year_id = $row['exam_year_id'];
$stmt->close();

// Handle bulk mark submission
$success_message = '';
$error_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_marks'])) {
    $school_id = intval($_POST['school_id']);
    $subject_id = intval($_POST['subject_id']);
    $subcounty_id = intval($_POST['subcounty_id']);
    
    try {
        $conn->begin_transaction();
        $skipped_candidates = [];
        $saved_candidates = [];
        $absent_candidates = [];
        
        if (isset($_POST['marks']) && is_array($_POST['marks'])) {
            foreach ($_POST['marks'] as $candidate_id => $mark_data) {
                $candidate_id = intval($candidate_id);
                $mark = isset($mark_data['value']) && $mark_data['value'] !== '' ? intval($mark_data['value']) : null;
                $is_absent = isset($mark_data['absent']) && $mark_data['absent'] == '1';

                if (!$is_absent && $mark !== null && ($mark < 0 || $mark > 100)) {
                    throw new Exception("Invalid mark for candidate ID $candidate_id: $mark. Must be between 0 and 100.");
                }

                $check_sql = "SELECT mark, status, submitted_at FROM marks WHERE candidate_id = ? AND subject_id = ? AND exam_year_id = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("iii", $candidate_id, $subject_id, $exam_year_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing = $result->fetch_assoc();
                $stmt->close();

                if ($is_absent || $mark !== null) {
                    if ($existing) {
                        $update_sql = "UPDATE marks SET mark = ?, status = ?, updated_at = NOW(), edited_by = ? 
                                       WHERE candidate_id = ? AND subject_id = ? AND exam_year_id = ?";
                        $stmt = $conn->prepare($update_sql);
                        $mark_to_update = $is_absent ? 0 : $mark;
                        $status = $is_absent ? 'ABSENT' : 'PRESENT';
                        $stmt->bind_param("isiiii", $mark_to_update, $status, $user_id, $candidate_id, $subject_id, $exam_year_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update mark for candidate ID $candidate_id: " . $conn->error);
                        }
                        $stmt->close();
                        $saved_candidates[] = $candidate_id;
                    } else {
                        $insert_sql = "INSERT INTO marks (candidate_id, subject_id, school_id, exam_year_id, mark, status, submitted_by, submitted_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                        $stmt = $conn->prepare($insert_sql);
                        $mark_to_insert = $is_absent ? 0 : $mark;
                        $status = $is_absent ? 'ABSENT' : 'PRESENT';
                        $stmt->bind_param("iiiisii", $candidate_id, $subject_id, $school_id, $exam_year_id, $mark_to_insert, $status, $user_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to insert mark for candidate ID $candidate_id: " . $conn->error);
                        }
                        $stmt->close();
                        $saved_candidates[] = $candidate_id;
                    }

                    if ($is_absent) {
                        $absent_candidates[] = $candidate_id;
                    }

                    // Process grades and results
                    $stmt = $conn->prepare("CALL ComputeCandidateGrades(?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiiii", $candidate_id, $school_id, $subcounty_id, $exam_year_id, $user_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to process grades for candidate ID $candidate_id: " . $conn->error);
                    }
                    $stmt->close();

                    $stmt = $conn->prepare("CALL ComputeCandidateResults(?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiiii", $candidate_id, $school_id, $subcounty_id, $exam_year_id, $user_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to process results for candidate ID $candidate_id: " . $conn->error);
                    }
                    $stmt->close();
                } else {
                    $skipped_candidates[] = $candidate_id;
                }
            }
        }

        $conn->commit();
        $success_message = "Marks saved successfully. " . count($saved_candidates) . " candidates updated, " . 
                          count($absent_candidates) . " marked as absent, " . count($skipped_candidates) . " skipped.";
        
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        $action = 'Bulk Mark Save';
        $details = "Saved marks for " . count($saved_candidates) . " candidates, " . 
                   count($absent_candidates) . " marked as absent, " . count($skipped_candidates) . " skipped. Subject ID: $subject_id, School ID: $school_id";
        $stmt->bind_param("sis", $action, $user_id, $details);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        $action = 'Bulk Mark Save Error';
        $details = "Bulk save error: " . $e->getMessage();
        $stmt->bind_param("sis", $action, $user_id, $details);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch schools and subjects
$schools = $conn->query("SELECT id, school_name, subcounty_id FROM schools ORDER BY school_name");
$subjects = $conn->query("SELECT id, name, code FROM subjects WHERE code IN ('ENG', 'SST', 'MTC', 'SCI') ORDER BY name");

// Fetch candidates for bulk entry
$candidates = [];
$selected_school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
$selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$selected_subcounty_id = 0;

if ($selected_school_id) {
    $stmt = $conn->prepare("SELECT subcounty_id FROM schools WHERE id = ?");
    $stmt->bind_param("i", $selected_school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $selected_subcounty_id = $row['subcounty_id'] ?? 0;
    }
    $stmt->close();
}

if ($selected_school_id && $selected_subject_id && $exam_year_id) {
    $candidates_sql = "SELECT c.id, c.candidate_name, c.index_number, m.mark, m.status, m.submitted_at 
                      FROM candidates c 
                      LEFT JOIN marks m ON c.id = m.candidate_id AND m.subject_id = ? AND m.exam_year_id = ?
                      WHERE c.school_id = ? AND c.exam_year_id = ?
                      ORDER BY c.index_number";
    $stmt = $conn->prepare($candidates_sql);
    $stmt->bind_param("iiii", $selected_subject_id, $exam_year_id, $selected_school_id, $exam_year_id);
    $stmt->execute();
    $candidates_result = $stmt->get_result();
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[$row['id']] = $row;
    }
    $stmt->close();
}

// Handle single candidate search
$single_candidate = null;
$single_marks = [];
$selected_index_number = isset($_GET['index_number']) ? trim($_GET['index_number']) : '';

if ($selected_index_number) {
    $stmt = $conn->prepare("SELECT c.id, c.candidate_name, c.index_number, c.school_id, s.school_name, s.subcounty_id
                            FROM candidates c
                            JOIN schools s ON c.school_id = s.id
                            WHERE c.index_number = ? AND c.exam_year_id = ?");
    $stmt->bind_param("si", $selected_index_number, $exam_year_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $single_candidate = $row;
        $marks_sql = "SELECT m.subject_id, s.code, s.name, m.mark, m.status, m.submitted_at 
                      FROM marks m
                      JOIN subjects s ON m.subject_id = s.id
                      WHERE m.candidate_id = ? AND m.exam_year_id = ?";
        $stmt_marks = $conn->prepare($marks_sql);
        $stmt_marks->bind_param("ii", $single_candidate['id'], $exam_year_id);
        $stmt_marks->execute();
        $marks_result = $stmt_marks->get_result();
        while ($mark_row = $marks_result->fetch_assoc()) {
            $single_marks[$mark_row['code']] = $mark_row;
        }
        $stmt_marks->close();
    } else {
        $error_message = "No candidate found with index number '$selected_index_number' for the active exam year.";
    }
    $stmt->close();
}

// Extra head content
$extra_head = '
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        .tab-content-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border-radius: var(--border-radius, 8px);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .tab-content-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 8px 8px 0 0;
            padding: 0.75rem 1.5rem;
            color: #6b7280;
            font-weight: 500;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-color, #4f46e5);
            color: white;
        }

        .nav-tabs .nav-link:hover {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary-color, #4f46e5);
        }

        .tab-pane {
            padding: 1.5rem;
            background: white;
            border-radius: 0 0 8px 8px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }

        .table-container, .search-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border-radius: var(--border-radius, 8px);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .table-container:hover, .search-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .table-header, .search-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-title, .search-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color, #1f2937);
            margin: 0;
        }

        .table-filter, .search-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .table-filter label, .search-form label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }

        .table-filter select, .search-form input {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            background: white;
            min-width: 160px;
        }

        .table-filter select:focus, .search-form input:focus {
            border-color: var(--primary-color, #4f46e5);
            box-shadow: 0 0 5px rgba(79, 70, 229, 0.3);
            outline: none;
        }

        .table-enhanced {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .table-enhanced th {
            background: linear-gradient(135deg, #f8fafc, #e5e7eb);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color, #1f2937);
            border-bottom: 2px solid #e5e7eb;
        }

        .table-enhanced td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.3s ease;
        }

        .table-enhanced tr:hover td {
            background: rgba(79, 70, 229, 0.05);
        }

        .table-enhanced tr.saved {
            background: rgba(34, 197, 94, 0.1);
        }

        .mark-input {
            width: 100px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        .mark-input.is-invalid {
            border-color: var(--danger-color, #ef4444);
            box-shadow: 0 0 5px rgba(239, 68, 68, 0.3);
        }

        .absent-checkbox {
            margin-left: 10px;
        }

        .btn-enhanced {
            background: linear-gradient(135deg, var(--primary-color, #4f46e5), #6366f1);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .btn-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            color: white;
        }

        .alert-enhanced {
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            animation: fadeIn 0.5s ease;
        }

        .alert-success {
            background: linear-gradient(135deg, var(--success-color, #10b981), #059669);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, var(--danger-color, #ef4444), #dc2626);
            color: white;
        }

        .candidate-info {
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .tab-content-container, .table-container, .search-container {
                overflow-x: auto;
            }

            .table-filter, .search-form {
                flex-direction: column;
                align-items: flex-start;
            }

            .table-filter select, .search-form input {
                width: 100%;
            }

            .nav-tabs .nav-link {
                font-size: 0.75rem;
                padding: 0.5rem 1rem;
            }
        }
    </style>
';

// Page-specific content
?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-edit"></i>
        Admin Mark Management
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Mark Management</li>
        </ol>
    </nav>
</div>

<!-- Alerts -->
<?php if ($success_message): ?>
    <div class="alert-enhanced alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="alert-enhanced alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<!-- Tabbed Interface -->
<div class="tab-content-container">
    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk" type="button" role="tab" aria-controls="bulk" aria-selected="true">Bulk Entry</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab" aria-controls="single" aria-selected="false">Single Candidate</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit" type="button" role="tab" aria-controls="audit" aria-selected="false">Audit Logs</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="reprocess-tab" data-bs-toggle="tab" data-bs-target="#reprocess" type="button" role="tab" aria-controls="reprocess" aria-selected="false">Reprocess Results</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="candidates-tab" data-bs-toggle="tab" data-bs-target="#candidates" type="button" role="tab" aria-controls="candidates" aria-selected="false">Manage Candidates</button>
        </li>
    </ul>
    <div class="tab-content" id="adminTabsContent">
        <!-- Bulk Entry Tab -->
        <div class="tab-pane fade show active" id="bulk" role="tabpanel" aria-labelledby="bulk-tab">
            <div class="table-container">
                <div class="table-header">
                    <h5 class="table-title">
                        <i class="fas fa-edit"></i>
                        Bulk Mark Entry - Exam Year: <?php echo htmlspecialchars($exam_year); ?>
                    </h5>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <form method="GET" action="">
                            <div class="table-filter">
                                <label for="school_id">School:</label>
                                <select id="school_id" name="school_id" onchange="this.form.submit()" class="form-select" required>
                                    <option value="">Select a school</option>
                                    <?php if ($schools && $schools->num_rows > 0): ?>
                                        <?php while($row = $schools->fetch_assoc()): ?>
                                            <option value="<?php echo $row['id']; ?>" <?php echo ($selected_school_id == $row['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($row['school_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="GET" action="">
                            <input type="hidden" name="school_id" value="<?php echo htmlspecialchars($selected_school_id); ?>">
                            <div class="table-filter">
                                <label for="subject_id">Subject:</label>
                                <select id="subject_id" name="subject_id" onchange="this.form.submit()" class="form-select" required>
                                    <option value="">Select a subject</option>
                                    <?php if ($subjects && $subjects->num_rows > 0): ?>
                                        <?php $subjects->data_seek(0); while($row = $subjects->fetch_assoc()): ?>
                                            <option value="<?php echo $row['id']; ?>" <?php echo ($selected_subject_id == $row['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($row['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
                <?php if (!empty($candidates)): ?>
                    <form method="POST" action="" id="marksForm">
                        <div class="table-responsive">
                            <table class="table-enhanced">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-id-card"></i> Index Number</th>
                                        <th><i class="fas fa-user"></i> Candidate Name</th>
                                        <th><i class="fas fa-pen"></i> Mark</th>
                                        <th><i class="fas fa-user-times"></i> Absent</th>
                                    </tr>
                                </thead>
                                <tbody id="candidatesTableBulk">
                                    <?php foreach ($candidates as $candidate): 
                                        $is_absent = isset($candidate['status']) && $candidate['status'] === 'ABSENT';
                                        $has_mark = isset($candidate['mark']) && $candidate['mark'] !== null && !$is_absent;
                                    ?>
                                        <tr class="candidate-row <?php echo ($has_mark || $is_absent) ? 'saved' : ''; ?>" data-candidate-id="<?php echo $candidate['id']; ?>">
                                            <td><?php echo htmlspecialchars($candidate['index_number']); ?></td>
                                            <td><?php echo htmlspecialchars($candidate['candidate_name']); ?></td>
                                            <td>
                                                <input type="number" step="1" name="marks[<?php echo $candidate['id']; ?>][value]" 
                                                       class="mark-input <?php echo $has_mark ? 'has-mark' : ''; ?>" 
                                                       value="<?php echo $has_mark ? htmlspecialchars($candidate['mark']) : ''; ?>"
                                                       min="0" max="100" 
                                                       data-candidate-id="<?php echo $candidate['id']; ?>" 
                                                       data-subject-id="<?php echo $selected_subject_id; ?>" 
                                                       data-school-id="<?php echo $selected_school_id; ?>" 
                                                       data-subcounty-id="<?php echo $selected_subcounty_id; ?>" 
                                                       data-exam-year-id="<?php echo $exam_year_id; ?>" 
                                                       <?php echo $is_absent ? 'disabled' : ''; ?>>
                                            </td>
                                            <td>
                                                <input type="checkbox" name="marks[<?php echo $candidate['id']; ?>][absent]" 
                                                       class="absent-checkbox" value="1" 
                                                       data-candidate-id="<?php echo $candidate['id']; ?>" 
                                                       <?php echo $is_absent ? 'checked' : ''; ?>>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <input type="hidden" name="school_id" value="<?php echo htmlspecialchars($selected_school_id); ?>">
                        <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($selected_subject_id); ?>">
                        <input type="hidden" name="subcounty_id" value="<?php echo htmlspecialchars($selected_subcounty_id); ?>">
                        <input type="hidden" name="exam_year_id" value="<?php echo htmlspecialchars($exam_year_id); ?>">
                        <button type="submit" name="submit_marks" class="btn-enhanced mt-3">
                            <i class="fas fa-save"></i>
                            Submit All
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-center text-muted">
                        <i class="fas fa-inbox"></i>
                        No candidates found for this school, subject, and exam year.
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <!-- Single Candidate Tab -->
        <div class="tab-pane fade" id="single" role="tabpanel" aria-labelledby="single-tab">
            <div class="search-container">
                <div class="search-header">
                    <h5 class="search-title">
                        <i class="fas fa-search"></i>
                        Search Candidate by Index Number
                    </h5>
                </div>
                <form method="GET" action="" class="search-form">
                    <label for="index_number">Index Number:</label>
                    <input type="text" id="index_number" name="index_number" value="<?php echo htmlspecialchars($selected_index_number); ?>" class="form-control" placeholder="Enter candidate index number (e.g., U001/2023)" required>
                    <button type="submit" class="btn-enhanced">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
            <?php if ($single_candidate): ?>
                <div class="candidate-info">
                    <h6><strong>Candidate:</strong> <?php echo htmlspecialchars($single_candidate['candidate_name']); ?></h6>
                    <p><strong>Index Number:</strong> <?php echo htmlspecialchars($single_candidate['index_number']); ?> | 
                       <strong>School:</strong> <?php echo htmlspecialchars($single_candidate['school_name']); ?> | 
                       <strong>Exam Year:</strong> <?php echo htmlspecialchars($exam_year); ?></p>
                </div>
                <ul class="nav nav-tabs" id="subjectTabs" role="tablist">
                    <?php if ($subjects && $subjects->num_rows > 0): ?>
                        <?php $subjects->data_seek(0); $first = true; while ($subject = $subjects->fetch_assoc()): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $first ? 'active' : ''; ?>" id="<?php echo $subject['code']; ?>-tab" data-bs-toggle="tab" data-bs-target="#<?php echo $subject['code']; ?>" type="button" role="tab" aria-controls="<?php echo $subject['code']; ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                                    <?php echo htmlspecialchars($subject['name']); ?>
                                </button>
                            </li>
                            <?php $first = false; ?>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </ul>
                <div class="tab-content" id="subjectTabsContent">
                    <?php if ($subjects && $subjects->num_rows > 0): ?>
                        <?php $subjects->data_seek(0); $first = true; while ($subject = $subjects->fetch_assoc()): ?>
                            <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="<?php echo $subject['code']; ?>" role="tabpanel" aria-labelledby="<?php echo $subject['code']; ?>-tab">
                                <?php
                                $mark_data = isset($single_marks[$subject['code']]) ? $single_marks[$subject['code']] : [];
                                $is_absent = isset($mark_data['status']) && $mark_data['status'] === 'ABSENT';
                                $has_mark = isset($mark_data['mark']) && $mark_data['mark'] !== null && !$is_absent;
                                ?>
                                <form class="mark-form" data-subject-id="<?php echo $subject['id']; ?>">
                                    <div class="mb-3">
                                        <label for="mark_<?php echo $subject['code']; ?>" class="form-label"><i class="fas fa-pen"></i> Mark for <?php echo htmlspecialchars($subject['name']); ?> (0-100):</label>
                                        <input type="number" step="1" name="mark" id="mark_<?php echo $subject['code']; ?>" class="mark-input <?php echo $has_mark ? 'has-mark' : ''; ?>" value="<?php echo $has_mark ? htmlspecialchars($mark_data['mark']) : ''; ?>" min="0" max="100" <?php echo $is_absent ? 'disabled' : ''; ?> data-candidate-id="<?php echo $single_candidate['id']; ?>" data-subject-id="<?php echo $subject['id']; ?>" data-school-id="<?php echo $single_candidate['school_id']; ?>" data-subcounty-id="<?php echo $single_candidate['subcounty_id']; ?>" data-exam-year-id="<?php echo $exam_year_id; ?>">
                                        <input type="checkbox" name="absent" id="absent_<?php echo $subject['code']; ?>" class="absent-checkbox" value="1" <?php echo $is_absent ? 'checked' : ''; ?> data-candidate-id="<?php echo $single_candidate['id']; ?>">
                                        <label for="absent_<?php echo $subject['code']; ?>" class="ms-2">Absent</label>
                                    </div>
                                    <button type="submit" class="btn-enhanced">
                                        <i class="fas fa-save"></i> Save Mark
                                    </button>
                                </form>
                            </div>
                            <?php $first = false; ?>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">
                    <i class="fas fa-inbox"></i>
                    Please search for a candidate to edit their marks.
                </p>
            <?php endif; ?>
        </div>
        <!-- Audit Logs Tab -->
        <div class="tab-pane fade" id="audit" role="tabpanel" aria-labelledby="audit-tab">
            <div class="table-container">
                <div class="table-header">
                    <h5 class="table-title">
                        <i class="fas fa-history"></i>
                        Audit Logs
                    </h5>
                    <div class="table-filter">
                        <label for="audit_filter">Filter by Action:</label>
                        <select id="audit_filter" class="form-select">
                            <option value="">All Actions</option>
                            <option value="Mark Insert">Mark Insert</option>
                            <option value="Mark Update">Mark Update</option>
                            <option value="Mark Save Error">Mark Save Error</option>
                            <option value="Bulk Mark Save">Bulk Mark Save</option>
                            <option value="Bulk Mark Save Error">Bulk Mark Save Error</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table-enhanced" id="auditTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-clock"></i> Timestamp</th>
                                <th><i class="fas fa-user"></i> User ID</th>
                                <th><i class="fas fa-tasks"></i> Action</th>
                                <th><i class="fas fa-info-circle"></i> Details</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Reprocess Results Tab -->
        <div class="tab-pane fade" id="reprocess" role="tabpanel" aria-labelledby="reprocess-tab">
            <div class="table-container">
                <div class="table-header">
                    <h5 class="table-title">
                        <i class="fas fa-redo"></i>
                        Reprocess Results
                    </h5>
                </div>
                <form id="reprocessForm" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="reprocess_school_id" class="form-label">School:</label>
                            <select id="reprocess_school_id" name="school_id" class="form-select">
                                <option value="">Select a school (optional)</option>
                                <?php if ($schools && $schools->num_rows > 0): ?>
                                    <?php $schools->data_seek(0); while($row = $schools->fetch_assoc()): ?>
                                        <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['school_name']); ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="reprocess_index_number" class="form-label">Candidate Index Number:</label>
                            <input type="text" id="reprocess_index_number" name="index_number" class="form-control" placeholder="Enter index number (optional)">
                        </div>
                    </div>
                    <button type="submit" class="btn-enhanced mt-3">
                        <i class="fas fa-redo"></i> Reprocess
                    </button>
                </form>
                <p class="text-muted">Select a school to reprocess all candidates, or enter an index number to reprocess a single candidate. Leave both blank to reprocess all candidates in the exam year.</p>
            </div>
        </div>
        <!-- Manage Candidates Tab -->
        <div class="tab-pane fade" id="candidates" role="tabpanel" aria-labelledby="candidates-tab">
            <div class="table-container">
                <div class="table-header">
                    <h5 class="table-title">
                        <i class="fas fa-users"></i>
                        Manage Candidates
                    </h5>
                </div>
                <!-- Add/Edit Candidate Form -->
                <form id="candidateForm" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="candidate_index_number" class="form-label">Index Number:</label>
                            <input type="text" id="candidate_index_number" name="index_number" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="candidate_name" class="form-label">Candidate Name:</label>
                            <input type="text" id="candidate_name" name="candidate_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="candidate_sex" class="form-label">Sex:</label>
                            <select id="candidate_sex" name="sex" class="form-select" required>
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="candidate_school_id" class="form-label">School:</label>
                            <select id="candidate_school_id" name="school_id" class="form-select" required>
                                <option value="">Select a school</option>
                                <?php if ($schools && $schools->num_rows > 0): ?>
                                    <?php $schools->data_seek(0); while($row = $schools->fetch_assoc()): ?>
                                        <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['school_name']); ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="candidate_exam_year_id" class="form-label">Exam Year:</label>
                            <input type="text" id="candidate_exam_year_id" name="exam_year_id" class="form-control" value="<?php echo htmlspecialchars($exam_year_id); ?>" readonly>
                        </div>
                    </div>
                    <input type="hidden" id="candidate_id" name="candidate_id">
                    <button type="submit" class="btn-enhanced mt-3">
                        <i class="fas fa-save"></i> Save Candidate
                    </button>
                </form>
                <!-- Candidates Table -->
                <div class="table-responsive">
                    <table class="table-enhanced" id="candidatesManageTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-id-card"></i> Index Number</th>
                                <th><i class="fas fa-user"></i> Name</th>
                                <th><i class="fas fa-venus-mars"></i> Sex</th>
                                <th><i class="fas fa-school"></i> School</th>
                                <th><i class="fas fa-cog"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody id="candidatesTableBody">
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Page-specific scripts
$extra_scripts = '
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fallback for showNotification
if (typeof window.showNotification === "undefined") {
    window.showNotification = function(message, type) {
        const alertDiv = $("<div>", {
            class: `alert-enhanced alert-${type}`,
            html: `<i class="fas fa-${type === "success" ? "check-circle" : "exclamation-triangle"}"></i> ${message}`
        }).css({
            position: "fixed",
            top: "20px",
            right: "20px",
            zIndex: 1000,
            maxWidth: "400px"
        });
        $("body").append(alertDiv);
        setTimeout(() => alertDiv.fadeOut(500, () => alertDiv.remove()), 5000);
        console.log("Notification:", type, message);
    };
}

$(document).ready(function() {
    // Handle absent checkbox in bulk entry
    $("#bulk .absent-checkbox").on("change", function() {
        const checkbox = $(this);
        const candidateId = checkbox.data("candidate-id");
        const markInput = $(`input[name="marks[${candidateId}][value]"]`);
        const row = checkbox.closest(".candidate-row");
        const allRows = $(".candidate-row");
        const currentIndex = allRows.index(row);
        
        if (checkbox.is(":checked")) {
            markInput.val("0").prop("disabled", true).addClass("locked");
            row.addClass("saved");
            saveMark(candidateId, markInput, checkbox, () => {
                if (currentIndex < allRows.length - 1) {
                    const nextRow = allRows.eq(currentIndex + 1);
                    const nextInput = nextRow.find(".mark-input");
                    if (!nextInput.hasClass("locked") && !nextInput.prop("disabled")) {
                        nextInput.focus();
                    } else {
                        for (let i = currentIndex + 1; i < allRows.length; i++) {
                            const nextRow = allRows.eq(i);
                            const nextInput = nextRow.find(".mark-input");
                            if (!nextInput.hasClass("locked") && !nextInput.prop("disabled")) {
                                nextInput.focus();
                                break;
                            }
                        }
                    }
                }
            });
        } else {
            markInput.prop("disabled", false).removeClass("locked");
            if (!markInput.val()) {
                row.removeClass("saved");
            }
        }
    });

    // Auto-save marks on blur in bulk entry
    $("#bulk .mark-input").on("blur", function() {
        const input = $(this);
        const candidateId = input.data("candidate-id");
        const checkbox = $(`input[name="marks[${candidateId}][absent]"]`);
        saveMark(candidateId, input, checkbox);
    });

    // Handle Enter key in bulk entry
    $("#bulk .mark-input").on("keydown", function(e) {
        if (e.key === "Enter") {
            e.preventDefault();
            const input = $(this);
            const candidateId = input.data("candidate-id");
            const row = input.closest(".candidate-row");
            const allRows = $(".candidate-row");
            const currentIndex = allRows.index(row);
            const mark = input.val().trim();
            const checkbox = $(`input[name="marks[${candidateId}][absent]"]`);

            if (mark === "" && !checkbox.is(":checked")) {
                checkbox.prop("checked", true).trigger("change");
                return;
            }

            if (mark !== "" || checkbox.is(":checked")) {
                saveMark(candidateId, input, checkbox);
            }

            if (currentIndex < allRows.length - 1) {
                const nextRow = allRows.eq(currentIndex + 1);
                const nextInput = nextRow.find(".mark-input");
                if (!nextInput.hasClass("locked") && !nextInput.prop("disabled")) {
                    nextInput.focus();
                } else {
                    for (let i = currentIndex + 1; i < allRows.length; i++) {
                        const nextRow = allRows.eq(i);
                        const nextInput = nextRow.find(".mark-input");
                        if (!nextInput.hasClass("locked") && !nextInput.prop("disabled")) {
                            nextInput.focus();
                            break;
                        }
                    }
                }
            }
        }
    });

    // Save mark function for bulk and single entry
    function saveMark(candidateId, input, checkbox, callback) {
        const mark = input.val().trim();
        const subjectId = input.data("subject-id");
        const schoolId = input.data("school-id");
        const subcountyId = input.data("subcounty-id");
        const examYearId = input.data("exam-year-id");
        const isAbsent = checkbox.is(":checked");
        const row = input.closest(".candidate-row");

        if (!isAbsent && mark === "") {
            input.removeClass("is-invalid has-mark");
            if (row.length) row.removeClass("saved");
            return;
        }

        if (!isAbsent && (isNaN(mark) || mark < 0 || mark > 100)) {
            input.addClass("is-invalid");
            window.showNotification("Mark must be between 0 and 100", "error");
            return;
        }
        input.removeClass("is-invalid");

        input.prop("disabled", true).addClass("loading-skeleton");
        
        // Check if save_marks.php exists, otherwise use fallback
        const saveUrl = "save_marks.php";
        
        $.ajax({
            url: saveUrl,
            method: "POST",
            contentType: "application/x-www-form-urlencoded",
            data: {
                candidate_id: candidateId,
                subject_id: subjectId,
                school_id: schoolId,
                mark: isAbsent ? 0 : mark,
                status: isAbsent ? "ABSENT" : "PRESENT"
            },
            dataType: "json",
            success: function(data) {
                input.removeClass("loading-skeleton");
                if (data.status === "success") {
                    input.addClass("has-mark").prop("disabled", isAbsent);
                    if (row.length) row.addClass("saved");
                    window.showNotification(data.message, "success");
                    if (callback) callback();
                } else {
                    input.prop("disabled", false);
                    window.showNotification(data.message || "Save failed, please try again.", "error");
                }
            },
            error: function(xhr, status, error) {
                input.removeClass("loading-skeleton").prop("disabled", false);
                let errorMessage = "Save failed: " + error;
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = "Save failed: " + (response.message || error);
                } catch (e) {
                    errorMessage = "Save failed: Invalid response from server";
                    console.log("Raw response:", xhr.responseText);
                }
                window.showNotification(errorMessage, "error");
            }
        });
    }

    // Bulk form validation
    $("#marksForm").on("submit", function(e) {
        let isValid = true;
        let blankMarks = [];
        $(this).find(".mark-input").each(function() {
            const input = $(this);
            const mark = input.val().trim();
            const candidateId = input.data("candidate-id");
            const isAbsent = $(`input[name="marks[${candidateId}][absent]"]`).is(":checked");
            if (!isAbsent && mark !== "" && (isNaN(mark) || mark < 0 || mark > 100)) {
                isValid = false;
                input.addClass("is-invalid");
                window.showNotification("Marks must be between 0 and 100", "error");
            } else if (!isAbsent && mark === "") {
                blankMarks.push($(`tr[data-candidate-id="${candidateId}"] td:nth-child(2)`).text());
            } else {
                input.removeClass("is-invalid");
            }
        });

        if (blankMarks.length > 0) {
            const confirmMessage = `The following candidates have blank marks and will be skipped:\n${blankMarks.join("\n")}\nDo you want to proceed?`;
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        }

        if (!isValid) {
            e.preventDefault();
        }
    });

    // Single candidate absent checkbox
    $("#single .absent-checkbox").on("change", function() {
        const checkbox = $(this);
        const candidateId = checkbox.data("candidate-id");
        const markInput = checkbox.closest(".mb-3").find(".mark-input");
        if (checkbox.is(":checked")) {
            markInput.val("0").prop("disabled", true);
        } else {
            markInput.prop("disabled", false);
        }
    });

    // Single candidate form submission
    $("#single .mark-form").on("submit", function(e) {
        e.preventDefault();
        const form = $(this);
        const markInput = form.find(".mark-input");
        const checkbox = form.find(".absent-checkbox");
        saveMark(markInput.data("candidate-id"), markInput, checkbox);
    });

    // Single candidate Enter key
    $("#single .mark-input").on("keydown", function(e) {
        if (e.key === "Enter") {
            e.preventDefault();
            $(this).closest("form").submit();
        }
    });

    // Autocomplete for index number (with fallback)
    if ($.fn.autocomplete) {
        $("#index_number, #reprocess_index_number").autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: "search_candidates.php",
                    data: { term: request.term, exam_year_id: <?php echo $exam_year_id; ?> },
                    dataType: "json",
                    success: function(data) {
                        response(data);
                    },
                    error: function() {
                        response([]);
                    }
                });
            },
            minLength: 3
        });
    }

    // Load audit logs
    function loadAuditLogs(filter = "") {
        $.ajax({
            url: "get_audit_logs.php",
            method: "GET",
            data: { filter: filter },
            dataType: "json",
            success: function(data) {
                let tableBody = "";
                if (data.length === 0) {
                    tableBody = \'<tr><td colspan="4">No audit logs found</td></tr>\';
                } else {
                    data.forEach(log => {
                        tableBody += `
                            <tr>
                                <td>${escapeHtml(log.created_at)}</td>
                                <td>${escapeHtml(log.user_id)}</td>
                                <td>${escapeHtml(log.action)}</td>
                                <td>${escapeHtml(log.details)}</td>
                            </tr>
                        `;
                    });
                }
                $("#auditTableBody").html(tableBody);
            },
            error: function() {
                $("#auditTableBody").html(\'<tr><td colspan="4">Failed to load audit logs</td></tr>\');
            }
        });
    }

    // Audit filter change
    $("#audit_filter").on("change", function() {
        loadAuditLogs($(this).val());
    });

    // Initial load of audit logs
    loadAuditLogs();

    // Reprocess form submission
    $("#reprocessForm").on("submit", function(e) {
        e.preventDefault();
        const schoolId = $("#reprocess_school_id").val();
        const indexNumber = $("#reprocess_index_number").val();
        $.ajax({
            url: "reprocess_results.php",
            method: "POST",
            data: { school_id: schoolId, index_number: indexNumber, exam_year_id: <?php echo $exam_year_id; ?> },
            dataType: "json",
            success: function(data) {
                if (data.status === "success") {
                    window.showNotification(data.message, "success");
                } else {
                    window.showNotification(data.message || "Reprocessing failed", "error");
                }
            },
            error: function() {
                window.showNotification("Reprocessing failed: Server error", "error");
            }
        });
    });

    // Load candidates
    function loadCandidates() {
        $.ajax({
            url: "get_candidates.php",
            method: "GET",
            data: { exam_year_id: <?php echo $exam_year_id; ?> },
            dataType: "json",
            success: function(data) {
                let tableBody = "";
                if (data.length === 0) {
                    tableBody = \'<tr><td colspan="5">No candidates found</td></tr>\';
                } else {
                    data.forEach(candidate => {
                        tableBody += `
                            <tr>
                                <td>${escapeHtml(candidate.index_number)}</td>
                                <td>${escapeHtml(candidate.candidate_name)}</td>
                                <td>${escapeHtml(candidate.sex)}</td>
                                <td>${escapeHtml(candidate.school_name)}</td>
                                <td>
                                    <button class="btn-enhanced btn-sm edit-candidate" data-id="${candidate.id}" data-index-number="${escapeHtml(candidate.index_number)}" data-name="${escapeHtml(candidate.candidate_name)}" data-sex="${candidate.sex}" data-school-id="${candidate.school_id}">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn-enhanced btn-sm btn-danger delete-candidate" data-id="${candidate.id}">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                }
                $("#candidatesTableBody").html(tableBody);
            },
            error: function() {
                $("#candidatesTableBody").html(\'<tr><td colspan="5">Failed to load candidates</td></tr>\');
            }
        });
    }

    // Edit candidate
    $(document).on("click", ".edit-candidate", function() {
        const id = $(this).data("id");
        const indexNumber = $(this).data("index-number");
        const name = $(this).data("name");
        const sex = $(this).data("sex");
        const schoolId = $(this).data("school-id");
        $("#candidate_id").val(id);
        $("#candidate_index_number").val(indexNumber);
        $("#candidate_name").val(name);
        $("#candidate_sex").val(sex);
        $("#candidate_school_id").val(schoolId);
    });

    // Delete candidate
    $(document).on("click", ".delete-candidate", function() {
        const id = $(this).data("id");
        if (confirm("Are you sure you want to delete this candidate?")) {
            $.ajax({
                url: "manage_candidate.php",
                method: "POST",
                data: { candidate_id: id, action: "delete" },
                dataType: "json",
                success: function(data) {
                    if (data.status === "success") {
                        window.showNotification(data.message, "success");
                        loadCandidates();
                    } else {
                        window.showNotification(data.message || "Deletion failed", "error");
                    }
                },
                error: function() {
                    window.showNotification("Deletion failed: Server error", "error");
                }
            });
        }
    });

    // Candidate form submission
    $("#candidateForm").on("submit", function(e) {
        e.preventDefault();
        const data = $(this).serialize() + "&action=save";
        $.ajax({
            url: "manage_candidate.php",
            method: "POST",
            data: data,
            dataType: "json",
            success: function(data) {
                if (data.status === "success") {
                    window.showNotification(data.message, "success");
                    $("#candidateForm")[0].reset();
                    $("#candidate_id").val("");
                    $("#candidate_exam_year_id").val(<?php echo $exam_year_id; ?>);
                    loadCandidates();
                } else {
                    window.showNotification(data.message || "Save failed", "error");
                }
            },
            error: function() {
                window.showNotification("Save failed: Server error", "error");
            }
        });
    });

    // Initial load of candidates
    loadCandidates();

    // Escape HTML function
    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return "";
        return unsafe
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/\'/g, "&#039;");
    }

    // Show initial notifications
    ' . ($success_message ? 'window.showNotification("' . addslashes($success_message) . '", "success");' : '') . '
    ' . ($error_message ? 'window.showNotification("' . addslashes($error_message) . '", "error");' : '') . '
    ' . (isset($_GET['status']) && $_GET['status'] === 'success' ? 'window.showNotification("Action completed successfully", "success");' : '') . '
});
</script>
';

require 'layout.php';

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>