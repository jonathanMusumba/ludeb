<?php
$page_title = 'Correct Candidate Marks';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display for production
ini_set('log_errors', 1);
ini_set('error_log', 'C:\xampp\htdocs\ludeb\setup_errors.log');

// Start output buffering
ob_start();

try {
    require_once '../db_connect.php'; // MySQLi connection
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict to authorized roles
$allowed_roles = ['System Admin', 'Examination Administrator', 'Data Entrant'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: " . $root_url . "login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Initialize variables
$errors = [];
$success = '';
$edit_mark = null;
$is_absent = false;
$candidates = [];
$subjects = [];
$exam_years = [];
$active_exam_year_id = null;

// Cache subjects and exam years in session to reduce queries
if (!isset($_SESSION['subjects']) || !isset($_SESSION['exam_years'])) {
    try {
        // Test database connection
        if (!$conn || $conn->connect_error) {
            throw new Exception("Database connection failed: " . ($conn ? $conn->connect_error : "Connection object is null"));
        }

        // Fetch subjects
        $subjects_query = "SELECT id, COALESCE(name, 'Unknown Subject') AS name, COALESCE(code, 'N/A') AS code 
                          FROM subjects ORDER BY name";
        $result = $conn->query($subjects_query);
        if (!$result) {
            throw new Exception("Subjects query failed: " . $conn->error);
        }
        $_SESSION['subjects'] = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        // Fetch exam years
        $exam_years_query = "SELECT id, COALESCE(exam_year, 'Unknown Year') AS exam_year 
                            FROM exam_years ORDER BY exam_year DESC";
        $result = $conn->query($exam_years_query);
        if (!$result) {
            throw new Exception("Exam years query failed: " . $conn->error);
        }
        $_SESSION['exam_years'] = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        // Fetch active exam year
        $active_year_query = "SELECT id FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC LIMIT 1";
        $result = $conn->query($active_year_query);
        if ($result) {
            $active_exam = $result->fetch_assoc();
            $_SESSION['active_exam_year_id'] = $active_exam ? $active_exam['id'] : null;
            $result->free();
        } else {
            error_log("Warning: Could not fetch active exam year: " . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Error caching data: " . $e->getMessage());
        $errors[] = "Database error: Unable to load subjects or exam years.";
    }
}

$subjects = $_SESSION['subjects'] ?? [];
$exam_years = $_SESSION['exam_years'] ?? [];
$active_exam_year_id = $_SESSION['active_exam_year_id'] ?? null;

// Fetch candidates
try {
    $candidates_query = "SELECT c.id, COALESCE(c.candidate_name, 'Unknown') AS name, 
                        COALESCE(c.index_number, 'N/A') AS index_number, 
                        COALESCE(s.school_name, 'Unknown School') AS school_name, 
                        s.id AS school_id, COALESCE(s.subcounty_id, 0) AS subcounty_id 
                        FROM candidates c LEFT JOIN schools s ON c.school_id = s.id 
                        ORDER BY c.candidate_name";
    $result = $conn->query($candidates_query);
    if (!$result) {
        throw new Exception("Candidates query failed: " . $conn->error);
    }
    $candidates = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
} catch (Exception $e) {
    error_log("Error fetching candidates: " . $e->getMessage());
    $errors[] = "Database error: Unable to load candidates.";
}

// Log page access
try {
    $stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, description, created_at) VALUES (?, ?, ?, NOW())");
    if ($stmt) {
        $log_action = 'Correct Marks Page Access';
        $log_description = 'Accessed correct marks page';
        $stmt->bind_param("sis", $log_action, $user_id, $log_description);
        $stmt->execute();
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Logging error in audit_logs: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $candidate_id = filter_input(INPUT_POST, 'candidate_id', FILTER_VALIDATE_INT);
    $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
    $exam_year_id = filter_input(INPUT_POST, 'exam_year_id', FILTER_VALIDATE_INT);
    $new_mark = filter_input(INPUT_POST, 'new_mark', FILTER_VALIDATE_INT);
    $is_absent = isset($_POST['absent']) && $_POST['absent'] == '1';
    $delete_mark = isset($_POST['delete']) && $_POST['delete'] == '1';

    // Validation
    if (!$candidate_id) {
        $errors[] = 'Please select a candidate.';
    }
    if (!$subject_id) {
        $errors[] = 'Please select a subject.';
    }
    if (!$exam_year_id) {
        $errors[] = 'Please select an exam year.';
    }
    if (!$is_absent && !$delete_mark && ($new_mark === false || $new_mark < 0 || $new_mark > 100)) {
        $errors[] = 'Mark must be between 0 and 100.';
    }

    if (empty($errors)) {
        try {
            // Get candidate and school details
            $stmt = $conn->prepare("SELECT c.candidate_name, s.id AS school_id, s.subcounty_id 
                                    FROM candidates c JOIN schools s ON c.school_id = s.id 
                                    WHERE c.id = ?");
            $stmt->bind_param("i", $candidate_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $candidate = $result->fetch_assoc();
            $candidate_name = $candidate['candidate_name'] ?? 'Unknown Candidate';
            $school_id = $candidate['school_id'] ?? 0;
            $subcounty_id = $candidate['subcounty_id'] ?? 0;
            $stmt->close();

            if (!$school_id || !$subcounty_id) {
                throw new Exception('Invalid candidate or school details.');
            }

            $conn->begin_transaction();

            // Check if mark exists and apply lock rule
            $stmt = $conn->prepare("SELECT mark, status, submitted_at FROM marks 
                                    WHERE candidate_id = ? AND subject_id = ? AND exam_year_id = ?");
            $stmt->bind_param("iii", $candidate_id, $subject_id, $exam_year_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();

            if ($existing && $_SESSION['role'] !== 'System Admin') {
                $lockTime = $existing['submitted_at'];
                $isLocked = $lockTime && (strtotime($lockTime) < strtotime('-3 minutes'));
                if ($isLocked) {
                    throw new Exception('Marks are locked; only System Admin can edit after 3 minutes.');
                }
            }

            if ($delete_mark) {
                $stmt = $conn->prepare("DELETE FROM marks WHERE candidate_id = ? AND subject_id = ? AND exam_year_id = ?");
                $stmt->bind_param("iii", $candidate_id, $subject_id, $exam_year_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, description, created_at) VALUES (?, ?, ?, NOW())");
                $log_desc = "Deleted mark for candidate ID: $candidate_id, subject ID: $subject_id, exam year ID: $exam_year_id";
                $stmt->bind_param("sis", 'Delete Mark', $user_id, $log_desc);
                $stmt->execute();
                $stmt->close();

                $success = 'Mark deleted successfully.';
            } else {
                if ($existing) {
                    $stmt = $conn->prepare("UPDATE marks SET mark = ?, status = ?, edited_by = ?, updated_at = NOW() 
                                            WHERE candidate_id = ? AND subject_id = ? AND exam_year_id = ?");
                    $status = $is_absent ? 'ABSENT' : 'PRESENT';
                    $mark_value = $is_absent ? 0 : $new_mark;
                    $stmt->bind_param("isiiii", $mark_value, $status, $user_id, $candidate_id, $subject_id, $exam_year_id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO marks (candidate_id, subject_id, school_id, exam_year_id, mark, status, submitted_by) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $status = $is_absent ? 'ABSENT' : 'PRESENT';
                    $mark_value = $is_absent ? 0 : $new_mark;
                    $stmt->bind_param("iiiiisi", $candidate_id, $subject_id, $school_id, $exam_year_id, $mark_value, $status, $user_id);
                }
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, description, created_at) VALUES (?, ?, ?, NOW())");
                $log_desc = "Corrected mark for candidate ID: $candidate_id, subject ID: $subject_id, exam year ID: $exam_year_id, new mark: " . ($is_absent ? 'ABSENT' : $new_mark);
                $stmt->bind_param("sis", 'Correct Mark', $user_id, $log_desc);
                $stmt->execute();
                $stmt->close();

                $success = $is_absent ? 'Candidate marked as absent successfully.' : 'Mark corrected successfully.';
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
            error_log("Mark correction error: " . $e->getMessage());
        }
    }

    // Redirect to clear POST data and show messages
    if (!empty($errors) || !empty($success)) {
        $query = http_build_query(['error' => $errors ? implode('|', $errors) : null, 'success' => $success]);
        header("Location: correct_results.php?$query");
        exit;
    }
}

// Fetch existing mark if editing
$edit_candidate_id = isset($_GET['candidate_id']) ? filter_input(INPUT_GET, 'candidate_id', FILTER_VALIDATE_INT) : null;
$edit_subject_id = isset($_GET['subject_id']) ? filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT) : null;
$edit_exam_year_id = isset($_GET['exam_year_id']) ? filter_input(INPUT_GET, 'exam_year_id', FILTER_VALIDATE_INT) : null;

if ($edit_candidate_id && $edit_subject_id && $edit_exam_year_id) {
    try {
        $stmt = $conn->prepare("SELECT mark, status FROM marks WHERE candidate_id = ? AND subject_id = ? AND exam_year_id = ?");
        $stmt->bind_param("iii", $edit_candidate_id, $edit_subject_id, $edit_exam_year_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_mark = $result->fetch_assoc();
        $is_absent = isset($edit_mark['status']) && $edit_mark['status'] === 'ABSENT';
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching edit mark: " . $e->getMessage());
        $errors[] = "Unable to load existing mark.";
    }
}

// Generate new CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

// Extra head content
$extra_head = '
    <style>
        .correct-marks-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(245, 245, 245, 0.85));
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .correct-marks-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        .marks-table-container {
            margin-top: 2rem;
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .marks-table-container h5 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .marks-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .marks-table th, .marks-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .marks-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        .marks-table tr:hover {
            background: #f3f4f6;
            cursor: pointer;
        }
        .marks-table .action-btn {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .marks-table .action-btn:hover {
            background: #7c3aed;
        }
        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
        }
        .form-control.search-match {
            border-color: #10b981;
            background: rgba(34, 197, 94, 0.05);
        }
        .form-control.no-match {
            border-color: #ef4444;
            background: rgba(239, 68, 68, 0.05);
        }
        .btn-enhanced {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-enhanced:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #9ca3af);
        }
        .btn-secondary:hover {
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }
        @media (max-width: 576px) {
            .correct-marks-container {
                padding: 1rem;
            }
            .marks-table th, .marks-table td {
                font-size: 0.8rem;
                padding: 0.5rem;
            }
            .marks-table .action-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.75rem;
            }
        }
    </style>
';

// Page-specific content
$content = '
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-edit"></i> Correct Candidate Marks
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="home.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Correct Marks</li>
        </ol>
    </nav>
</div>';

// Display messages
if (isset($_GET['success']) || !empty($success)) {
    $message = isset($_GET['success']) ? $_GET['success'] : $success;
    $content .= '
    <div class="alert-enhanced alert-success">
        <i class="fas fa-check-circle"></i>
        <span>' . htmlspecialchars($message) . '</span>
        <button class="dismiss-btn">&times;</button>
    </div>';
}

if (isset($_GET['error']) || !empty($errors)) {
    $message = isset($_GET['error']) ? str_replace('|', '<br>', $_GET['error']) : implode('<br>', $errors);
    $content .= '
    <div class="alert-enhanced alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <span>' . htmlspecialchars($message) . '</span>
        <button class="dismiss-btn">&times;</button>
    </div>';
}

$content .= '
<div class="correct-marks-container">
    <h5 class="mb-4"><i class="fas fa-cog"></i> Correct Mark</h5>
    <form method="POST" action="" id="correctMarksForm">
        <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf_token) . '">
        <div class="mb-3">
            <label for="candidate_search" class="form-label">Search Candidate by Name or Index Number</label>
            <input type="text" id="candidate_search" class="form-control" placeholder="Enter name or index number">
        </div>
        <div class="mb-3">
            <label for="candidate_id" class="form-label">Candidate</label>
            <select name="candidate_id" id="candidate_id" class="form-control" required>
                <option value="">Select Candidate</option>';
foreach ($candidates as $candidate) {
    $content .= '
                <option value="' . $candidate['id'] . '" 
                        data-index-number="' . htmlspecialchars($candidate['index_number']) . '" 
                        data-school-name="' . htmlspecialchars($candidate['school_name']) . '" 
                        data-candidate-name="' . htmlspecialchars($candidate['name']) . '"
                        ' . ($edit_candidate_id == $candidate['id'] ? 'selected' : '') . '>
                    ' . htmlspecialchars($candidate['name'] . ' (' . $candidate['index_number'] . ') - ' . $candidate['school_name']) . '
                </option>';
}
$content .= '
            </select>
        </div>
        <div class="mb-3">
            <label for="exam_year_id" class="form-label">Exam Year</label>
            <select name="exam_year_id" id="exam_year_id" class="form-control" required>
                <option value="">Select Exam Year</option>';
foreach ($exam_years as $year) {
    $content .= '
                <option value="' . $year['id'] . '" ' . ($edit_exam_year_id == $year['id'] || $active_exam_year_id == $year['id'] ? 'selected' : '') . '>
                    ' . htmlspecialchars($year['exam_year']) . '
                </option>';
}
$content .= '
            </select>
        </div>
        <div class="mb-3">
            <label for="subject_id" class="form-label">Subject</label>
            <select name="subject_id" id="subject_id" class="form-control" required>
                <option value="">Select Subject</option>';
foreach ($subjects as $subject) {
    $content .= '
                <option value="' . $subject['id'] . '" ' . ($edit_subject_id == $subject['id'] ? 'selected' : '') . '>
                    ' . htmlspecialchars($subject['name'] . ' (' . $subject['code'] . ')') . '
                </option>';
}
$content .= '
            </select>
        </div>
        <div class="mb-3">
            <label for="new_mark" class="form-label">New Mark</label>
            <input type="number" name="new_mark" id="new_mark" class="form-control" placeholder="Enter new mark (0-100)" 
                   value="' . htmlspecialchars($edit_mark['mark'] ?? '') . '" min="0" max="100" ' . ($is_absent ? 'disabled' : '') . '>
        </div>
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="absent" id="absent" value="1" ' . ($is_absent ? 'checked' : '') . '>
                <label class="form-check-label" for="absent">Mark as Absent</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="delete" id="delete" value="1">
                <label class="form-check-label" for="delete">Delete Mark</label>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn-enhanced">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <button type="button" class="btn-enhanced btn-secondary" id="resetForm">
                <i class="fas fa-undo"></i> Reset Form
            </button>
        </div>
    </form>
    <div class="marks-table-container" id="marksTableContainer" style="display: none;">
        <h5><i class="fas fa-table"></i> Existing Marks</h5>
        <div class="table-responsive">
            <table class="marks-table table table-hover">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Mark</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                        <th>Updated At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="marksTableBody"></tbody>
            </table>
        </div>
    </div>
</div>';

$extra_scripts = '
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    // Candidate search
    $("#candidate_search").on("input", function() {
        var searchValue = $(this).val().toLowerCase().trim();
        var $dropdown = $("#candidate_id");
        var $options = $dropdown.find("option");
        var matchCount = 0;
        var matchedOption = null;

        $options.each(function() {
            var indexNumber = $(this).data("index-number")?.toLowerCase() || "";
            var candidateName = $(this).data("candidate-name")?.toLowerCase() || "";
            var isMatch = indexNumber.includes(searchValue) || candidateName.includes(searchValue) || $(this).val() === "";
            $(this).toggle(isMatch);
            if (isMatch && $(this).val() !== "") {
                matchCount++;
                matchedOption = $(this);
            }
        });

        if (matchCount === 1) {
            $dropdown.val(matchedOption.val()).trigger("change");
            showNotification("Candidate found: " + matchedOption.data("candidate-name"), "success");
        } else if (matchCount > 1) {
            $dropdown.val("").removeClass("search-match").addClass("no-match");
            showNotification("Multiple candidates found. Please select one.", "danger");
        } else if (searchValue !== "") {
            $dropdown.val("").removeClass("search-match").addClass("no-match");
            showNotification("No candidates found for: " + searchValue, "danger");
        } else {
            $dropdown.val("").removeClass("search-match no-match");
        }
    });

    // Fetch marks when candidate or exam year changes
    function fetchMarks() {
        var candidateId = $("#candidate_id").val();
        var examYearId = $("#exam_year_id").val();
        var $tableContainer = $("#marksTableContainer");
        var $tableBody = $("#marksTableBody");

        if (!candidateId || !examYearId) {
            $tableContainer.hide();
            $tableBody.empty();
            return;
        }

        $.ajax({
            url: "fetch_marks.php",
            method: "POST",
            data: {
                candidate_id: candidateId,
                exam_year_id: examYearId,
                csrf_token: "' . htmlspecialchars($csrf_token) . '"
            },
            dataType: "json",
            success: function(response) {
                $tableBody.empty();
                if (response.success && response.marks && response.marks.length > 0) {
                    $.each(response.marks, function(i, mark) {
                        var row = `
                            <tr data-subject-id="${mark.subject_id}" data-mark="${mark.mark}" data-status="${mark.status}">
                                <td>${mark.subject_name} (${mark.subject_code})</td>
                                <td>${mark.status === "ABSENT" ? "Absent" : mark.mark}</td>
                                <td>${mark.status}</td>
                                <td>${mark.submitted_at}</td>
                                <td>${mark.updated_at || "-"}</td>
                                <td><button class="action-btn edit-mark">Edit</button></td>
                            </tr>`;
                        $tableBody.append(row);
                    });
                    $tableContainer.show();
                } else {
                    $tableBody.append("<tr><td colspan=\"6\">No marks found for this candidate.</td></tr>");
                    $tableContainer.show();
                }
            },
            error: function(xhr, status, error) {
                showNotification("Error fetching marks: " + (xhr.responseJSON?.error || "Unknown error"), "danger");
                $tableContainer.hide();
            }
        });
    }

    // Trigger fetch on candidate or exam year change
    $("#candidate_id, #exam_year_id").on("change", function() {
        $("#candidate_search").val("");
        $("#candidate_id").removeClass("search-match no-match");
        fetchMarks();
    });

    // Handle edit button click
    $(document).on("click", ".edit-mark", function() {
        var $row = $(this).closest("tr");
        $("#subject_id").val($row.data("subject-id"));
        $("#new_mark").val($row.data("status") === "ABSENT" ? "" : $row.data("mark"));
        $("#absent").prop("checked", $row.data("status") === "ABSENT");
        $("#delete").prop("checked", false);
        $("#new_mark").prop("disabled", $row.data("status") === "ABSENT");
        showNotification("Mark selected for editing.", "success");
    });

    // Handle absent/delete checkboxes
    $("#absent, #delete").on("change", function() {
        var isAbsent = $("#absent").is(":checked");
        var isDelete = $("#delete").is(":checked");
        if (isAbsent && isDelete) {
            if ($(this).attr("id") === "absent") {
                $("#delete").prop("checked", false);
            } else {
                $("#absent").prop("checked", false);
            }
        }
        $("#new_mark").prop("disabled", isAbsent || isDelete).val(isAbsent || isDelete ? "" : $("#new_mark").val());
    });

    // Reset form
    $("#resetForm").on("click", function() {
        $("#correctMarksForm")[0].reset();
        $("#candidate_id").val("").trigger("change");
        $("#subject_id").val("");
        $("#exam_year_id").val("' . htmlspecialchars($active_exam_year_id ?? '') . '");
        $("#new_mark").prop("disabled", false);
        $("#marksTableContainer").hide();
        showNotification("Form reset.", "success");
    });

    // Form submission confirmation
    $("#correctMarksForm").on("submit", function(e) {
        var candidateId = $("#candidate_id").val();
        var subjectId = $("#subject_id").val();
        var examYearId = $("#exam_year_id").val();
        var mark = $("#new_mark").val();
        var isAbsent = $("#absent").is(":checked");
        var isDelete = $("#delete").is(":checked");

        if (!candidateId || !subjectId || !examYearId) {
            showNotification("Please fill all required fields.", "danger");
            e.preventDefault();
            return false;
        }

        var candidateName = $("#candidate_id option:selected").data("candidate-name") || "Unknown";
        var schoolName = $("#candidate_id option:selected").data("school-name") || "Unknown";
        var subjectName = $("#subject_id option:selected").text() || "Unknown";
        var examYear = $("#exam_year_id option:selected").text() || "Unknown";

        var confirmMessage = `Confirm action:\n\n` +
                             `Candidate: ${candidateName}\n` +
                             `School: ${schoolName}\n` +
                             `Subject: ${subjectName}\n` +
                             `Exam Year: ${examYear}\n\n` +
                             (isDelete ? "Action: Delete mark\n" :
                              isAbsent ? "Action: Mark as Absent\n" :
                              `Action: Set mark to ${mark}\n`) +
                             `This will affect division and aggregates.\n\nProceed?`;

        if (!confirm(confirmMessage)) {
            e.preventDefault();
            return false;
        }
    });

    // Initial fetch if editing
    if (' . ($edit_candidate_id ? 'true' : 'false') . ' && ' . ($edit_exam_year_id ? 'true' : 'false') . ') {
        fetchMarks();
    }
});
</script>';

try {
    require_once '../layout.php';
} catch (Exception $e) {
    error_log("Layout file error: " . $e->getMessage());
    echo "Error loading page layout.";
}
?>