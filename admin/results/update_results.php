<?php
session_start();
require_once '../db_connect.php';

// Restrict to System Admin and Examination Administrator
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['System Admin', 'Examination Administrator'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$exam_year_id = isset($_GET['exam_year_id']) ? intval($_GET['exam_year_id']) : 0;

// Validate exam_year_id
if ($exam_year_id <= 0) {
    $stmt = $conn->query("SELECT id FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC LIMIT 1");
    $row = $stmt->fetch_assoc();
    $exam_year_id = $row['id'] ?? 0;
    if ($exam_year_id <= 0) {
        $content = '<div class="alert alert-danger">No active exam year found. Please set an active exam year in Settings.</div>';
        $page_title = 'Update Results';
        $template_vars = [
            'board_name' => 'Luuka Examination Board',
            'exam_year' => date('Y'),
            'username' => htmlspecialchars($_SESSION['username']),
            'role' => $_SESSION['role']
        ];
        require_once '../layout.php';
        exit();
    }
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }

    try {
        if ($_POST['action'] === 'fetch_candidate_marks') {
            $index_number = trim($_POST['index_number']);
            $stmt = $conn->prepare("SELECT c.id, c.candidate_name, c.school_id, s.school_name, s.subcounty_id
                                    FROM candidates c
                                    JOIN schools s ON c.school_id = s.id
                                    WHERE c.index_number = ? AND c.exam_year_id = ?");
            $stmt->bind_param('si', $index_number, $exam_year_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $candidate_id = $row['id'];
                $school_id = $row['school_id'];
                $subcounty_id = $row['subcounty_id'];
                $stmt = $conn->prepare("SELECT m.subject_id, s.code, s.name, m.mark, m.status
                                        FROM marks m
                                        JOIN subjects s ON m.subject_id = s.id
                                        WHERE m.candidate_id = ? AND m.exam_year_id = ?
                                        UNION
                                        SELECT s.id, s.code, s.name, NULL, 'ABSENT'
                                        FROM subjects s
                                        WHERE s.code IN ('ENG', 'MTC', 'SCI', 'SST')
                                        AND s.id NOT IN (SELECT subject_id FROM marks WHERE candidate_id = ? AND exam_year_id = ?)");
                $stmt->bind_param('iiii', $candidate_id, $exam_year_id, $candidate_id, $exam_year_id);
                $stmt->execute();
                $marks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                echo json_encode([
                    'success' => true,
                    'candidate_id' => $candidate_id,
                    'candidate_name' => $row['candidate_name'],
                    'school_name' => $row['school_name'],
                    'school_id' => $school_id,
                    'subcounty_id' => $subcounty_id,
                    'marks' => $marks
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Candidate not found']);
            }
        } elseif ($_POST['action'] === 'update_marks') {
            $candidate_id = intval($_POST['candidate_id']);
            $school_id = intval($_POST['school_id']);
            $subcounty_id = intval($_POST['subcounty_id']);
            $marks = json_decode($_POST['marks'], true);
            
            foreach ($marks as $mark_data) {
                $subject_id = intval($mark_data['subject_id']);
                $status = $mark_data['status'] === 'ABSENT' ? 'ABSENT' : 'PRESENT';
                $mark = $status === 'PRESENT' ? intval($mark_data['mark']) : null;
                
                if ($status === 'PRESENT' && ($mark < 0 || $mark > 100)) {
                    echo json_encode(['success' => false, 'message' => "Invalid mark for subject {$mark_data['code']}"]);
                    exit();
                }

                if ($status === 'PRESENT') {
                    $stmt = $conn->prepare("CALL correct_candidate_marks(?, ?, ?, ?, ?)");
                    $stmt->bind_param('iiiii', $candidate_id, $subject_id, $exam_year_id, $mark, $user_id);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("CALL delete_candidate_mark(?, ?, ?, ?)");
                    $stmt->bind_param('iiii', $candidate_id, $subject_id, $exam_year_id, $user_id);
                    $stmt->execute();
                }
            }
            
            // Reprocess candidate and update school status
            $stmt = $conn->prepare("CALL ComputeCandidateResults(?, ?, ?, ?, ?)");
            $stmt->bind_param('iiiii', $candidate_id, $school_id, $subcounty_id, $exam_year_id, $user_id);
            $stmt->execute();
            $stmt = $conn->prepare("CALL UpdateSchoolResultsStatus(?, ?, ?)");
            $stmt->bind_param('iii', $school_id, $exam_year_id, $user_id);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Marks updated and results reprocessed']);
        } elseif ($_POST['action'] === 'reprocess_candidate') {
            $candidate_id = intval($_POST['candidate_id']);
            $stmt = $conn->prepare("SELECT c.id, c.school_id, s.subcounty_id 
                                    FROM candidates c 
                                    JOIN schools s ON c.school_id = s.id 
                                    WHERE c.id = ? AND c.exam_year_id = ?");
            $stmt->bind_param('ii', $candidate_id, $exam_year_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $school_id = $row['school_id'];
                $subcounty_id = $row['subcounty_id'];
                $stmt = $conn->prepare("CALL ComputeCandidateResults(?, ?, ?, ?, ?)");
                $stmt->bind_param('iiiii', $candidate_id, $school_id, $subcounty_id, $exam_year_id, $user_id);
                $stmt->execute();
                $stmt = $conn->prepare("CALL UpdateSchoolResultsStatus(?, ?, ?)");
                $stmt->bind_param('iii', $school_id, $exam_year_id, $user_id);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Candidate reprocessed successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid candidate']);
            }
        } elseif ($_POST['action'] === 'reprocess_all') {
            $stmt = $conn->prepare("CALL ProcessAllCandidates(?, ?)");
            $stmt->bind_param('ii', $exam_year_id, $user_id);
            $stmt->execute();
            $stmt = $conn->prepare("SELECT id FROM schools WHERE id IN (SELECT DISTINCT school_id FROM candidates WHERE id IN (SELECT DISTINCT candidate_id FROM marks WHERE exam_year_id = ?))");
            $stmt->bind_param('i', $exam_year_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $updated = 0;
            while ($row = $result->fetch_assoc()) {
                $school_id = $row['id'];
                $stmt_update = $conn->prepare("CALL UpdateSchoolResultsStatus(?, ?, ?)");
                $stmt_update->bind_param('iii', $school_id, $exam_year_id, $user_id);
                $stmt_update->execute();
                $updated++;
            }
            echo json_encode(['success' => true, 'message' => "Reprocessed all candidates and updated $updated schools"]);
        } elseif ($_POST['action'] === 'update_schools_declared') {
            $stmt = $conn->prepare("SELECT s.id
                                    FROM schools s
                                    JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
                                    JOIN (
                                        SELECT candidate_id, COUNT(*) AS subject_count
                                        FROM marks
                                        WHERE exam_year_id = ? AND status = 'PRESENT'
                                        GROUP BY candidate_id
                                        HAVING subject_count = 4
                                    ) m ON c.id = m.candidate_id
                                    GROUP BY s.id
                                    HAVING COUNT(DISTINCT c.id) / (SELECT COUNT(*) FROM candidates c2 WHERE c2.school_id = s.id AND c2.exam_year_id = ?) >= 0.5");
            $stmt->bind_param('iii', $exam_year_id, $exam_year_id, $exam_year_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $updated = 0;
            while ($row = $result->fetch_assoc()) {
                $school_id = $row['id'];
                $stmt_update = $conn->prepare("CALL UpdateSchoolResultsStatus(?, ?, ?)");
                $stmt_update->bind_param('iii', $school_id, $exam_year_id, $user_id);
                $stmt_update->execute();
                $updated++;
            }
            echo json_encode(['success' => true, 'message' => "Updated $updated schools to Declared"]);
        } elseif ($_POST['action'] === 'update_schools_partially') {
            $stmt = $conn->prepare("SELECT DISTINCT s.id
                                    FROM schools s
                                    JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
                                    WHERE s.id IN (SELECT DISTINCT school_id FROM candidates WHERE id IN (SELECT DISTINCT candidate_id FROM marks WHERE exam_year_id = ?))
                                    AND s.id NOT IN (
                                        SELECT s2.id
                                        FROM schools s2
                                        JOIN candidates c2 ON s2.id = c2.school_id AND c2.exam_year_id = ?
                                        JOIN (
                                            SELECT candidate_id, COUNT(*) AS subject_count
                                            FROM marks
                                            WHERE exam_year_id = ? AND status = 'PRESENT'
                                            GROUP BY candidate_id
                                            HAVING subject_count = 4
                                        ) m ON c2.id = m.candidate_id
                                        GROUP BY s2.id
                                        HAVING COUNT(DISTINCT c2.id) / (SELECT COUNT(*) FROM candidates c3 WHERE c3.school_id = s2.id AND c3.exam_year_id = ?) >= 0.5
                                    )");
            $stmt->bind_param('iiiii', $exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id, $exam_year_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $updated = 0;
            while ($row = $result->fetch_assoc()) {
                $school_id = $row['id'];
                $stmt_update = $conn->prepare("CALL UpdateSchoolResultsStatus(?, ?, ?)");
                $stmt_update->bind_param('iii', $school_id, $exam_year_id, $user_id);
                $stmt_update->execute();
                $updated++;
            }
            echo json_encode(['success' => true, 'message' => "Updated $updated schools to Partially Declared"]);
        } elseif ($_POST['action'] === 'fetch_pending_candidates') {
            $draw = intval($_POST['draw']);
            $start = intval($_POST['start']);
            $length = intval($_POST['length']);
            $search = $_POST['search']['value'];

            $query = "SELECT c.id, c.index_number, c.candidate_name, s.school_name,
                             COUNT(CASE WHEN m.status = 'PRESENT' THEN m.id END) AS subject_count,
                             MAX(m.updated_at) AS last_updated
                      FROM candidates c
                      LEFT JOIN marks m ON c.id = m.candidate_id AND m.exam_year_id = ?
                      JOIN schools s ON c.school_id = s.id
                      WHERE c.exam_year_id = ?
                      AND (m.updated_at > NOW() - INTERVAL 1 DAY OR 
                           (SELECT COUNT(*) FROM marks m2 WHERE m2.candidate_id = c.id AND m2.exam_year_id = ? AND m2.status = 'PRESENT') < 4)";
            $count_query = "SELECT COUNT(DISTINCT c.id) AS total
                            FROM candidates c
                            LEFT JOIN marks m ON c.id = m.candidate_id AND m.exam_year_id = ?
                            WHERE c.exam_year_id = ?
                            AND (m.updated_at > NOW() - INTERVAL 1 DAY OR 
                                 (SELECT COUNT(*) FROM marks m2 WHERE m2.candidate_id = c.id AND m2.exam_year_id = ? AND m2.status = 'PRESENT') < 4)";

            if (!empty($search)) {
                $query .= " AND (c.index_number LIKE ? OR c.candidate_name LIKE ? OR s.school_name LIKE ?)";
                $count_query .= " AND (c.index_number LIKE ? OR c.candidate_name LIKE ? OR s.school_name LIKE ?)";
            }
            $query .= " GROUP BY c.id LIMIT ?, ?";

            $stmt = $conn->prepare($count_query);
            $search_param = "%$search%";
            if (!empty($search)) {
                $stmt->bind_param('iiisss', $exam_year_id, $exam_year_id, $exam_year_id, $search_param, $search_param, $search_param);
            } else {
                $stmt->bind_param('iii', $exam_year_id, $exam_year_id, $exam_year_id);
            }
            $stmt->execute();
            $total_filtered = $stmt->get_result()->fetch_assoc()['total'];

            $stmt = $conn->prepare($query);
            if (!empty($search)) {
                $stmt->bind_param('iiisssi', $exam_year_id, $exam_year_id, $exam_year_id, $search_param, $search_param, $search_param, $start, $length);
            } else {
                $stmt->bind_param('iiii', $exam_year_id, $exam_year_id, $exam_year_id, $start, $length);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'index_number' => htmlspecialchars($row['index_number']),
                    'candidate_name' => htmlspecialchars($row['candidate_name']),
                    'school_name' => htmlspecialchars($row['school_name']),
                    'subject_count' => $row['subject_count'] . '/4',
                    'last_updated' => $row['last_updated'] ? htmlspecialchars($row['last_updated']) : '-',
                    'action' => "<button class='btn btn-sm btn-primary reprocessCandidateBtn' data-candidate-id='{$row['id']}'>Reprocess</button>"
                ];
            }

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $total_filtered,
                'recordsFiltered' => $total_filtered,
                'data' => $data
            ]);
        }
    } catch (Exception $e) {
        $stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, details) VALUES ('Update Results Error', ?, ?)");
        $error_message = $e->getMessage();
        $stmt->bind_param('is', $user_id, $error_message);
        $stmt->execute();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $error_message]);
    }
    exit();
}

// Fetch counts for badges
$stmt = $conn->prepare("SELECT COUNT(DISTINCT c.id) AS pending_count
                        FROM candidates c
                        LEFT JOIN marks m ON c.id = m.candidate_id AND m.exam_year_id = ?
                        WHERE c.exam_year_id = ?
                        AND (m.updated_at > NOW() - INTERVAL 1 DAY OR 
                             (SELECT COUNT(*) FROM marks m2 WHERE m2.candidate_id = c.id AND m2.exam_year_id = ? AND m2.status = 'PRESENT') < 4)");
$stmt->bind_param('iii', $exam_year_id, $exam_year_id, $exam_year_id);
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['pending_count'];

$stmt = $conn->prepare("SELECT COUNT(*) AS pending_schools 
                        FROM schools 
                        WHERE id IN (SELECT DISTINCT school_id FROM candidates WHERE id IN (SELECT DISTINCT candidate_id FROM marks WHERE exam_year_id = ?))");
$stmt->bind_param('i', $exam_year_id);
$stmt->execute();
$pending_schools = $stmt->get_result()->fetch_assoc()['pending_schools'];

// Build page content
ob_start();
?>
<div class="container mt-4">
    <h2 class="mb-4">Update Results</h2>
    <!-- Toast Container -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1050;">
        <div id="toastContainer" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>
    <!-- Index Number Input -->
    <div class="mb-3">
        <form id="fetchCandidateForm">
            <div class="input-group">
                <input type="text" class="form-control" id="index_number" name="index_number" placeholder="Enter Candidate Index Number" required>
                <button class="btn btn-primary" type="submit">Fetch Marks</button>
            </div>
        </form>
    </div>
    <!-- Tabs -->
    <ul class="nav nav-tabs" id="resultsTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="pending-tab" data-bs-toggle="tab" href="#pending" role="tab">
                Pending Updates <span class="badge bg-danger"><?php echo $pending_count; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="schools-tab" data-bs-toggle="tab" href="#schools" role="tab">
                Update School Status <span class="badge bg-warning"><?php echo $pending_schools; ?></span>
            </a>
        </li>
    </ul>
    <div class="tab-content">
        <!-- Pending Updates Tab -->
        <div class="tab-pane fade show active" id="pending" role="tabpanel">
            <button class="btn btn-primary mb-3" id="reprocessAllBtn">Reprocess All Candidates</button>
            <table id="pendingTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>Index Number</th>
                        <th>Candidate Name</th>
                        <th>School</th>
                        <th>Subjects Entered</th>
                        <th>Last Updated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <!-- Update School Status Tab -->
        <div class="tab-pane fade" id="schools" role="tabpanel">
            <div class="mb-3">
                <button class="btn btn-primary" id="updateSchoolsDeclaredBtn">Update All Schools (Eligible for Declared)</button>
                <button class="btn btn-secondary" id="updateSchoolsPartiallyBtn">Update All Schools (Eligible for Partially Declared)</button>
            </div>
            <table id="schoolsTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>Center No</th>
                        <th>School Name</th>
                        <th>Total Candidates</th>
                        <th>With 4 Subjects</th>
                        <th>With 1-3 Subjects</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT s.id, s.center_no, s.school_name, s.results_status,
                                            COUNT(DISTINCT c.id) AS total_candidates,
                                            COUNT(DISTINCT CASE WHEN subject_count = 4 THEN m.candidate_id END) AS with_marks_4,
                                            COUNT(DISTINCT CASE WHEN subject_count IN (1, 2, 3) THEN m.candidate_id END) AS with_marks_1to3
                                            FROM schools s
                                            JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
                                            LEFT JOIN (
                                                SELECT candidate_id, COUNT(*) AS subject_count
                                                FROM marks
                                                WHERE exam_year_id = ? AND status = 'PRESENT'
                                                GROUP BY candidate_id
                                            ) m ON c.id = m.candidate_id
                                            WHERE s.id IN (SELECT DISTINCT school_id FROM candidates WHERE id IN (SELECT DISTINCT candidate_id FROM marks WHERE exam_year_id = ?))
                                            GROUP BY s.id");
                    $stmt->bind_param('iii', $exam_year_id, $exam_year_id, $exam_year_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['center_no']) . "</td>
                            <td>" . htmlspecialchars($row['school_name']) . "</td>
                            <td>" . $row['total_candidates'] . "</td>
                            <td>" . ($row['with_marks_4'] ?? 0) . "</td>
                            <td>" . ($row['with_marks_1to3'] ?? 0) . "</td>
                            <td>" . htmlspecialchars($row['results_status']) . "</td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Modal for Updating Marks -->
    <div class="modal fade" id="updateMarksModal" tabindex="-1" aria-labelledby="updateMarksModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateMarksModalLabel">Update Marks</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="updateMarksForm">
                        <input type="hidden" name="action" value="update_marks">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="candidate_id" id="modal_candidate_id">
                        <input type="hidden" name="school_id" id="modal_school_id">
                        <input type="hidden" name="subcounty_id" id="modal_subcounty_id">
                        <p><strong>Candidate:</strong> <span id="modal_candidate_name"></span></p>
                        <p><strong>School:</strong> <span id="modal_school_name"></span></p>
                        <div id="marks_container"></div>
                        <button type="submit" class="btn btn-primary mt-3">Update Marks</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .tab-content { padding: 20px; }
    .badge { font-size: 0.9em; }
    .toast { min-width: 300px; }
    .btn + .btn { margin-left: 10px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script>
    $(document).ready(function() {
        // Debug: Check if jQuery and Bootstrap are loaded
        console.log('jQuery loaded:', typeof jQuery !== 'undefined');
        console.log('Bootstrap loaded:', typeof bootstrap !== 'undefined');

        // Initialize Toast
        const toastEl = $('#toastContainer');
        const toast = new bootstrap.Toast(toastEl);

        function showToast(message, isSuccess) {
            toastEl.find('.toast-body').text(message);
            toastEl.removeClass('bg-success bg-danger').addClass(isSuccess ? 'bg-success' : 'bg-danger');
            toast.show();
        }

        // Initialize DataTables with server-side processing for Pending Updates
        const pendingTable = $('#pendingTable').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: 'update_results.php',
                type: 'POST',
                data: function(d) {
                    d.action = 'fetch_pending_candidates';
                    d.csrf_token = '<?php echo $csrf_token; ?>';
                },
                error: function(xhr, status, error) {
                    showToast('Error loading pending updates: ' + error, false);
                }
            },
            columns: [
                { data: 'index_number' },
                { data: 'candidate_name' },
                { data: 'school_name' },
                { data: 'subject_count' },
                { data: 'last_updated' },
                { data: 'action' }
            ],
            dom: 'Bfrtip',
            buttons: ['csv', 'excel'],
            pageLength: 10
        });

        // Initialize Schools Table
        const schoolsTable = $('#schoolsTable').DataTable({
            dom: 'Bfrtip',
            buttons: ['csv', 'excel'],
            pageLength: 10
        });

        // Fetch candidate marks
        $('#fetchCandidateForm').on('submit', function(e) {
            e.preventDefault();
            const indexNumber = $('#index_number').val().trim();
            if (!indexNumber) {
                showToast('Please enter an index number', false);
                return;
            }
            console.log('Fetching marks for index:', indexNumber);
            $.ajax({
                url: 'update_results.php',
                type: 'POST',
                data: {
                    action: 'fetch_candidate_marks',
                    index_number: indexNumber,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                timeout: 10000,
                success: function(response) {
                    console.log('Fetch candidate response:', response);
                    if (response.success) {
                        $('#modal_candidate_id').val(response.candidate_id);
                        $('#modal_school_id').val(response.school_id);
                        $('#modal_subcounty_id').val(response.subcounty_id);
                        $('#modal_candidate_name').text(response.candidate_name);
                        $('#modal_school_name').text(response.school_name);
                        let marksHtml = '';
                        response.marks.forEach(function(mark, index) {
                            marksHtml += `
                                <div class="mb-3">
                                    <label class="form-label">${mark.name} (${mark.code})</label>
                                    <input type="hidden" name="marks[${index}][subject_id]" value="${mark.subject_id}">
                                    <div class="input-group">
                                        <input type="number" name="marks[${index}][mark]" class="form-control mark-input" min="0" max="100" value="${mark.mark || ''}" ${mark.status === 'ABSENT' ? 'disabled' : ''}>
                                        <select name="marks[${index}][status]" class="form-select status-select" style="width: auto;">
                                            <option value="PRESENT" ${mark.status === 'PRESENT' ? 'selected' : ''}>PRESENT</option>
                                            <option value="ABSENT" ${mark.status === 'ABSENT' ? 'selected' : ''}>ABSENT</option>
                                        </select>
                                    </div>
                                </div>`;
                        });
                        $('#marks_container').html(marksHtml);
                        $('#updateMarksModal').modal('show');
                    } else {
                        showToast(response.message, false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Fetch marks error:', status, error);
                    showToast('Error fetching candidate marks: ' + error, false);
                }
            });
        });

        // Handle status change in modal
        $(document).on('change', '.status-select', function() {
            const input = $(this).closest('.input-group').find('.mark-input');
            input.prop('disabled', this.value === 'ABSENT');
            if (this.value === 'ABSENT') {
                input.val('');
            }
        });

        // Update marks
        $('#updateMarksForm').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            console.log('Updating marks with data:', formData);
            $.ajax({
                url: 'update_results.php',
                type: 'POST',
                data: formData,
                timeout: 10000,
                success: function(response) {
                    console.log('Update marks response:', response);
                    if (response.success) {
                        $('#updateMarksModal').modal('hide');
                        showToast(response.message, true);
                        pendingTable.ajax.reload();
                        schoolsTable.ajax.reload();
                    } else {
                        showToast(response.message, false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Update marks error:', status, error);
                    showToast('Error updating marks: ' + error, false);
                }
            });
        });

        // Reprocess single candidate
        $(document).on('click', '.reprocessCandidateBtn', function() {
            const candidateId = $(this).data('candidate-id');
            console.log('Reprocessing candidate:', candidateId);
            $.ajax({
                url: 'update_results.php',
                type: 'POST',
                data: {
                    action: 'reprocess_candidate',
                    candidate_id: candidateId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                timeout: 10000,
                success: function(response) {
                    console.log('Reprocess candidate response:', response);
                    if (response.success) {
                        showToast(response.message, true);
                        pendingTable.ajax.reload();
                        schoolsTable.ajax.reload();
                    } else {
                        showToast(response.message, false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Reprocess candidate error:', status, error);
                    showToast('Error reprocessing candidate: ' + error, false);
                }
            });
        });

        // Reprocess all candidates
        $('#reprocessAllBtn').on('click', function() {
            if (confirm('Reprocess all candidates for exam year <?php echo $exam_year_id; ?>?')) {
                console.log('Reprocessing all candidates');
                $.ajax({
                    url: 'update_results.php',
                    type: 'POST',
                    data: {
                        action: 'reprocess_all',
                        csrf_token: '<?php echo $csrf_token; ?>'
                    },
                    timeout: 30000,
                    success: function(response) {
                        console.log('Reprocess all response:', response);
                        if (response.success) {
                            showToast(response.message, true);
                            pendingTable.ajax.reload();
                            schoolsTable.ajax.reload();
                        } else {
                            showToast(response.message, false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Reprocess all error:', status, error);
                        showToast('Error reprocessing all candidates: ' + error, false);
                    }
                });
            }
        });

        // Update schools eligible for Declared
        $('#updateSchoolsDeclaredBtn').on('click', function() {
            if (confirm('Update all schools eligible for Declared status for exam year <?php echo $exam_year_id; ?>?')) {
                console.log('Updating schools to Declared');
                $.ajax({
                    url: 'update_results.php',
                    type: 'POST',
                    data: {
                        action: 'update_schools_declared',
                        csrf_token: '<?php echo $csrf_token; ?>'
                    },
                    timeout: 30000,
                    success: function(response) {
                        console.log('Update Declared response:', response);
                        if (response.success) {
                            showToast(response.message, true);
                            schoolsTable.ajax.reload();
                        } else {
                            showToast(response.message, false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Update Declared error:', status, error);
                        showToast('Error updating schools: ' + error, false);
                    }
                });
            }
        });

        // Update schools eligible for Partially Declared
        $('#updateSchoolsPartiallyBtn').on('click', function() {
            if (confirm('Update all schools eligible for Partially Declared status for exam year <?php echo $exam_year_id; ?>?')) {
                console.log('Updating schools to Partially Declared');
                $.ajax({
                    url: 'update_results.php',
                    type: 'POST',
                    data: {
                        action: 'update_schools_partially',
                        csrf_token: '<?php echo $csrf_token; ?>'
                    },
                    timeout: 30000,
                    success: function(response) {
                        console.log('Update Partially response:', response);
                        if (response.success) {
                            showToast(response.message, true);
                            schoolsTable.ajax.reload();
                        } else {
                            showToast(response.message, false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Update Partially error:', status, error);
                        showToast('Error updating schools: ' + error, false);
                    }
                });
            }
        });
    });
</script>
<?php
$content = ob_get_clean();
$page_title = 'Update Results';
$template_vars = [
    'board_name' => 'Luuka Examination Board',
    'exam_year' => date('Y'),
    'username' => htmlspecialchars($_SESSION['username']),
    'role' => $_SESSION['role']
];
require_once '../layout.php';
?>