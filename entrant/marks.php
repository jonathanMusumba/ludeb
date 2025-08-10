<?php
ob_start();
require_once 'db_connect.php';

// Restrict to Data Entrants
$allowed_roles = ['Data Entrant'];
$page_title = 'Enter Marks';
$log_action = 'Mark Entry Page Access';
$log_description = 'Accessed mark entry page';

// Log page access
$stmt = $conn->prepare("CALL log_action(?, ?, ?)");
$stmt->bind_param("sis", $log_action, $_SESSION['user_id'], $log_description);
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
    $error_message = "No active exam year configured in settings. Please contact the administrator.";
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

// Handle form submission for bulk saving
$success_message = '';
$error_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_marks'])) {
    $school_id = intval($_POST['school_id']);
    $subject_id = intval($_POST['subject_id']);
    
    try {
        $conn->begin_transaction();
        $skipped_candidates = [];
        $saved_candidates = [];
        $absent_candidates = [];
        foreach ($_POST['marks'] as $candidate_id => $mark_data) {
            $candidate_id = intval($candidate_id);
            $mark = isset($mark_data['value']) && $mark_data['value'] !== '' ? intval($mark_data['value']) : null;
            $status = isset($mark_data['absent']) && $mark_data['absent'] == '1' ? 'ABSENT' : 'PRESENT';

            if ($status === 'PRESENT' && ($mark === null || $mark < 0 || $mark > 100)) {
                continue; // Skip invalid marks
            }

            // Get candidate name for logging
            $stmt = $conn->prepare("SELECT candidate_name FROM candidates WHERE id = ?");
            $stmt->bind_param("i", $candidate_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $candidate = $result->fetch_assoc();
            $candidate_name = $candidate['candidate_name'] ?? 'Unknown Candidate';
            $stmt->close();

            // Check if mark exists
            $check_sql = "SELECT mark, status FROM marks WHERE candidate_id = ? AND subject_id = ? AND exam_year_id = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("iii", $candidate_id, $subject_id, $exam_year_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();

            if (!$existing) {
                // Insert new mark
                $insert_sql = "INSERT INTO marks (candidate_id, subject_id, school_id, exam_year_id, mark, status, submitted_by, submitted_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($insert_sql);
                $mark_to_insert = $status === 'ABSENT' ? 0 : $mark;
                $stmt->bind_param("iiiisii", $candidate_id, $subject_id, $school_id, $exam_year_id, $mark_to_insert, $status, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    if ($status === 'ABSENT') {
                        $absent_candidates[] = $candidate_name;
                    } else {
                        $saved_candidates[] = $candidate_name;
                    }
                }
                $stmt->close();
            } else {
                // Update existing mark
                $update_sql = "UPDATE marks SET mark = ?, status = ?, updated_at = NOW(), edited_by = ? 
                               WHERE candidate_id = ? AND subject_id = ? AND exam_year_id = ?";
                $stmt = $conn->prepare($update_sql);
                $mark_to_update = $status === 'ABSENT' ? 0 : $mark;
                $stmt->bind_param("isiiii", $mark_to_update, $status, $_SESSION['user_id'], $candidate_id, $subject_id, $exam_year_id);
                if ($stmt->execute()) {
                    if ($status === 'ABSENT') {
                        $absent_candidates[] = $candidate_name;
                    } else {
                        $saved_candidates[] = $candidate_name;
                    }
                }
                $stmt->close();
            }

            // Call stored procedures
            $stmt = $conn->prepare("CALL ComputeCandidateGrades(?, ?, ?, ?, ?)");
            $subcounty_id = intval($_POST['subcounty_id']);
            $stmt->bind_param("iiiii", $candidate_id, $school_id, $subcounty_id, $exam_year_id, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("CALL ComputeCandidateResults(?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiii", $candidate_id, $school_id, $subcounty_id, $exam_year_id, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

        // Prepare success message
        if ($saved_candidates || $absent_candidates) {
            $success_message = "Marks saved successfully.";
            if ($saved_candidates) {
                $success_message .= " Candidates updated: " . implode(", ", $saved_candidates) . ".";
            }
            if ($absent_candidates) {
                $success_message .= " Candidates marked absent: " . implode(", ", $absent_candidates) . ".";
            }
        }
        if ($skipped_candidates) {
            $error_message = "Some candidates were skipped due to invalid marks: " . implode(", ", $skipped_candidates);
        }

        // Log bulk save
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        $action = 'Bulk Mark Save';
        $details = "Bulk save for school_id: $school_id, subject_id: $subject_id, exam_year_id: $exam_year_id";
        $stmt->bind_param("sis", $action, $_SESSION['user_id'], $details);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        $stmt = $conn->prepare("CALL log_action(?, ?, ?)");
        $action = 'Mark Save Error';
        $details = "Bulk save error: " . $e->getMessage();
        $stmt->bind_param("sis", $action, $_SESSION['user_id'], $details);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch schools and subjects
$schools = $conn->query("SELECT id, school_name, subcounty_id FROM schools ORDER BY school_name");
$subjects = $conn->query("SELECT id, name FROM subjects ORDER BY name");

// Fetch candidates for selected school and subject
$candidates = [];
$selected_school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
$selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$selected_subcounty_id = 0;

if ($selected_school_id) {
    $stmt = $conn->prepare("SELECT subcounty_id FROM schools WHERE id = ?");
    $stmt->bind_param("i", $selected_school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $selected_subcounty_id = $row['subcounty_id'] ?? 0;
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

// Extra head content
$extra_head = '
    <style>
        .table-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .table-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .table-filter {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .table-filter label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }

        .table-filter select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            background: white;
            min-width: 160px;
        }

        .table-filter select:focus {
            border-color: var(--primary-color);
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
            color: var(--dark-color);
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

        .mark-input:disabled {
            background-color: #e9ecef;
            pointer-events: none;
        }

        .mark-input.is-invalid {
            border-color: var(--danger-color);
            box-shadow: 0 0 5px rgba(239, 68, 68, 0.3);
        }

        .absent-checkbox {
            margin-left: 10px;
        }

        .btn-enhanced {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
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
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
            }

            .table-filter {
                flex-direction: column;
                align-items: flex-start;
            }

            .table-filter select {
                width: 100%;
            }
        }
    </style>
';

// Page-specific content
?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-plus"></i>
        Enter Marks
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="home.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Enter Marks</li>
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

<!-- Table Container -->
<div class="table-container">
    <div class="table-header">
        <h5 class="table-title">
            <i class="fas fa-edit"></i>
            Enter Marks - Exam Year: <?php echo htmlspecialchars($exam_year); ?>
        </h5>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <form method="GET" action="">
                <div class="table-filter">
                    <label for="school_id">School:</label>
                    <select id="school_id" name="school_id" onchange="this.form.submit()" class="form-select" required>
                        <option value="">Select a school</option>
                        <?php while($row = $schools->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>" <?php echo ($selected_school_id == $row['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['school_name']); ?>
                            </option>
                        <?php endwhile; ?>
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
                        <?php $subjects->data_seek(0); while($row = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>" <?php echo ($selected_subject_id == $row['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
    <?php if (!empty($candidates)): ?>
        <p class="text-muted mb-3">
            <i class="fas fa-info-circle"></i>
            Marks can be edited at any time by Data Entrants. Ensure accuracy before saving.
        </p>
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
                    <tbody id="candidatesTable">
                        <?php foreach ($candidates as $candidate): 
                            $isLocked = false; // No time-based lock for Data Entrants
                            $isAbsent = isset($candidate['status']) && $candidate['status'] === 'ABSENT';
                            $hasMark = isset($candidate['mark']) && $candidate['mark'] !== null && !$isAbsent;
                        ?>
                            <tr class="candidate-row <?php echo ($hasMark || $isAbsent) ? 'saved' : ''; ?>" data-candidate-id="<?php echo $candidate['id']; ?>">
                                <td><?php echo htmlspecialchars($candidate['index_number']); ?></td>
                                <td><?php echo htmlspecialchars($candidate['candidate_name']); ?></td>
                                <td>
                                    <input type="number" step="1" name="marks[<?php echo $candidate['id']; ?>][value]" 
                                           class="mark-input <?php echo $hasMark ? 'has-mark' : ''; ?>" 
                                           value="<?php echo $hasMark ? htmlspecialchars($candidate['mark']) : ''; ?>"
                                           min="0" max="100" 
                                           data-candidate-id="<?php echo $candidate['id']; ?>" 
                                           data-subject-id="<?php echo $selected_subject_id; ?>" 
                                           data-school-id="<?php echo $selected_school_id; ?>" 
                                           data-exam-year-id="<?php echo $exam_year_id; ?>" 
                                           <?php echo $isAbsent ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <input type="checkbox" name="marks[<?php echo $candidate['id']; ?>][absent]" 
                                           class="absent-checkbox" value="1" 
                                           data-candidate-id="<?php echo $candidate['id']; ?>" 
                                           <?php echo $isAbsent ? 'checked' : ''; ?>>
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

<?php
$content = ob_get_clean();

// Page-specific scripts
$extra_scripts = '
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
    // Handle absent checkbox
    $(".absent-checkbox").on("change", function() {
        const checkbox = $(this);
        const candidateId = checkbox.data("candidate-id");
        const markInput = $(`input[name="marks[${candidateId}][value]"]`);
        const row = checkbox.closest(".candidate-row");
        const allRows = $(".candidate-row");
        const currentIndex = allRows.index(row);
        
        if (checkbox.is(":checked")) {
            markInput.val("0").prop("disabled", true);
            row.addClass("saved");
            saveMark(candidateId, markInput, checkbox, () => {
                // Focus next input after successful save
                if (currentIndex < allRows.length - 1) {
                    const nextRow = allRows.eq(currentIndex + 1);
                    const nextInput = nextRow.find(".mark-input");
                    if (!nextInput.prop("disabled")) {
                        nextInput.focus();
                    } else {
                        for (let i = currentIndex + 1; i < allRows.length; i++) {
                            const nextRow = allRows.eq(i);
                            const nextInput = nextRow.find(".mark-input");
                            if (!nextInput.prop("disabled")) {
                                nextInput.focus();
                                break;
                            }
                        }
                    }
                }
            });
        } else {
            markInput.prop("disabled", false);
            if (!markInput.val()) {
                row.removeClass("saved");
            }
        }
    });

    // Auto-save marks on blur
    $(".mark-input").on("blur", function() {
        const input = $(this);
        const candidateId = input.data("candidate-id");
        const checkbox = $(`input[name="marks[${candidateId}][absent]"]`);
        saveMark(candidateId, input, checkbox);
    });

    // Handle Enter key
    $(".mark-input").on("keydown", function(e) {
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
                if (!nextInput.prop("disabled")) {
                    nextInput.focus();
                } else {
                    for (let i = currentIndex + 1; i < allRows.length; i++) {
                        const nextRow = allRows.eq(i);
                        const nextInput = nextRow.find(".mark-input");
                        if (!nextInput.prop("disabled")) {
                            nextInput.focus();
                            break;
                        }
                    }
                }
            }
        }
    });

    // Function to save mark
    function saveMark(candidateId, input, checkbox, callback) {
        const mark = input.val().trim();
        const subjectId = input.data("subject-id");
        const schoolId = input.data("school-id");
        const isAbsent = checkbox.is(":checked");
        const row = input.closest(".candidate-row");

        if (!isAbsent && mark === "") {
            input.removeClass("is-invalid has-mark");
            row.removeClass("saved");
            return;
        }

        if (!isAbsent && (isNaN(mark) || mark < 0 || mark > 100)) {
            input.addClass("is-invalid");
            window.showNotification("Mark must be between 0 and 100", "error");
            return;
        }
        input.removeClass("is-invalid");

        input.prop("disabled", true).addClass("loading-skeleton");
        $.ajax({
            url: "save_marks.php",
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
                    row.addClass("saved");
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

    // Form validation for bulk submission
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

    // Table row animation
    $(".table-enhanced tr").each(function() {
        $(this).css("animation", "fadeIn 0.3s ease");
    });

    // Show notification for initial messages
    ' . ($success_message ? "window.showNotification('$success_message', 'success');" : '') . '
    ' . ($error_message ? "window.showNotification('$error_message', 'error');" : '') . '
    ' . (isset($_GET['status']) && $_GET['status'] === 'success' ? "window.showNotification('Marks saved successfully', 'success');" : '') . '
});
</script>
';

require 'layout.php';
?>