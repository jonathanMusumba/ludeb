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
        $page_title = 'Process Results';
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
        if ($_POST['action'] === 'process_candidate') {
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
                echo json_encode(['success' => true, 'message' => 'Candidate processed successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid candidate']);
            }
        } elseif ($_POST['action'] === 'process_all') {
            $stmt = $conn->prepare("CALL ProcessAllCandidates(?, ?)");
            $stmt->bind_param('ii', $exam_year_id, $user_id);
            $stmt->execute();
            // Update all schools' results_status
            $stmt = $conn->prepare("SELECT id FROM schools");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $school_id = $row['id'];
                $stmt_update = $conn->prepare("CALL UpdateSchoolResultsStatus(?, ?, ?)");
                $stmt_update->bind_param('iii', $school_id, $exam_year_id, $user_id);
                $stmt_update->execute();
            }
            echo json_encode(['success' => true, 'message' => 'All candidates processed successfully']);
        } elseif ($_POST['action'] === 'update_school_status') {
            $school_id = intval($_POST['school_id']);
            $stmt = $conn->prepare("CALL UpdateSchoolResultsStatus(?, ?, ?)");
            $stmt->bind_param('iii', $school_id, $exam_year_id, $user_id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'School status updated']);
        }
    } catch (Exception $e) {
        $stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, details) VALUES ('Process Results Error', ?, ?)");
        $error_message = $e->getMessage();
        $stmt->bind_param('is', $user_id, $error_message);
        $stmt->execute();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $error_message]);
    }
    exit();
}

// Fetch pending candidates count
$stmt = $conn->prepare("SELECT COUNT(DISTINCT c.id) AS pending_count
                        FROM candidates c
                        LEFT JOIN marks m ON c.id = m.candidate_id AND m.exam_year_id = ?
                        WHERE c.exam_year_id = ?
                        GROUP BY c.id
                        HAVING COUNT(CASE WHEN m.status = 'PRESENT' THEN m.id END) < 4");
$stmt->bind_param('ii', $exam_year_id, $exam_year_id);
$stmt->execute();
$result = $stmt->get_result();
$pending_count = 0;
while ($row = $result->fetch_assoc()) {
    $pending_count += 1;
}

// Fetch processed candidates count
$stmt = $conn->prepare("SELECT COUNT(*) AS processed_count FROM candidate_results WHERE exam_year_id = ?");
$stmt->bind_param('i', $exam_year_id);
$stmt->execute();
$processed_count = $stmt->get_result()->fetch_assoc()['processed_count'];

// Fetch schools with pending status
$stmt = $conn->prepare("SELECT COUNT(*) AS pending_schools FROM schools WHERE results_status IN ('Not Declared', 'Partially Declared')");
$stmt->execute();
$pending_schools = $stmt->get_result()->fetch_assoc()['pending_schools'];

// Build page content
ob_start();
?>
<div class="container mt-4">
    <h2 class="mb-4">Process Results</h2>
    <ul class="nav nav-tabs" id="resultsTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="pending-tab" data-bs-toggle="tab" href="#pending" role="tab">
                Pending Candidates <span class="badge bg-danger"><?php echo $pending_count; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="processed-tab" data-bs-toggle="tab" href="#processed" role="tab">
                Processed Results <span class="badge bg-success"><?php echo $processed_count; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="schools-tab" data-bs-toggle="tab" href="#schools" role="tab">
                School Results Status <span class="badge bg-warning"><?php echo $pending_schools; ?></span>
            </a>
        </li>
    </ul>
    <div class="tab-content">
        <!-- Pending Candidates Tab -->
        <div class="tab-pane fade show active" id="pending" role="tabpanel">
            <button class="btn btn-primary mb-3" id="processAllBtn">Process All Candidates</button>
            <table id="pendingTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>Index Number</th>
                        <th>Candidate Name</th>
                        <th>School</th>
                        <th>Subjects Entered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT c.id, c.index_number, c.candidate_name, s.school_name,
                                            COUNT(CASE WHEN m.status = 'PRESENT' THEN m.id END) AS subject_count
                                            FROM candidates c
                                            LEFT JOIN marks m ON c.id = m.candidate_id AND m.exam_year_id = ?
                                            JOIN schools s ON c.school_id = s.id
                                            WHERE c.exam_year_id = ?
                                            GROUP BY c.id
                                            HAVING subject_count < 4");
                    $stmt->bind_param('ii', $exam_year_id, $exam_year_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['index_number']) . "</td>
                            <td>" . htmlspecialchars($row['candidate_name']) . "</td>
                            <td>" . htmlspecialchars($row['school_name']) . "</td>
                            <td>" . $row['subject_count'] . "/4</td>
                            <td><button class='btn btn-sm btn-primary processCandidateBtn' data-candidate-id='{$row['id']}'>Process</button></td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <!-- Processed Results Tab -->
        <div class="tab-pane fade" id="processed" role="tabpanel">
            <table id="processedTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>Index Number</th>
                        <th>Candidate Name</th>
                        <th>School</th>
                        <th>Aggregates</th>
                        <th>Division</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT c.index_number, c.candidate_name, s.school_name, cr.aggregates, cr.division
                                            FROM candidate_results cr
                                            JOIN candidates c ON cr.candidate_id = c.id
                                            JOIN schools s ON cr.school_id = s.id
                                            WHERE cr.exam_year_id = ?");
                    $stmt->bind_param('i', $exam_year_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['index_number']) . "</td>
                            <td>" . htmlspecialchars($row['candidate_name']) . "</td>
                            <td>" . htmlspecialchars($row['school_name']) . "</td>
                            <td>" . htmlspecialchars($row['aggregates']) . "</td>
                            <td>" . htmlspecialchars($row['division']) . "</td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <!-- School Results Status Tab -->
        <div class="tab-pane fade" id="schools" role="tabpanel">
            <table id="schoolsTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>Center No</th>
                        <th>School Name</th>
                        <th>Total Candidates</th>
                        <th>With Marks (3+ Subjects)</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT s.id, s.center_no, s.school_name, s.results_status,
                                            COUNT(DISTINCT c.id) AS total_candidates,
                                            COUNT(DISTINCT CASE WHEN subject_count >= 3 THEN m.candidate_id END) AS with_marks
                                            FROM schools s
                                            LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = ?
                                            LEFT JOIN (
                                                SELECT candidate_id, COUNT(*) AS subject_count
                                                FROM marks
                                                WHERE exam_year_id = ? AND status = 'PRESENT'
                                                GROUP BY candidate_id
                                            ) m ON c.id = m.candidate_id
                                            GROUP BY s.id");
                    $stmt->bind_param('ii', $exam_year_id, $exam_year_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['center_no']) . "</td>
                            <td>" . htmlspecialchars($row['school_name']) . "</td>
                            <td>" . $row['total_candidates'] . "</td>
                            <td>" . ($row['with_marks'] ?? 0) . "</td>
                            <td>" . htmlspecialchars($row['results_status']) . "</td>
                            <td><button class='btn btn-sm btn-primary updateStatusBtn' data-school-id='{$row['id']}'>Update Status</button></td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .tab-content { padding: 20px; }
    .badge { font-size: 0.9em; }
    .action-btn { margin-left: 10px; }
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
        // Initialize DataTables
        $('#pendingTable').DataTable({
            dom: 'Bfrtip',
            buttons: ['csv', 'excel'],
            pageLength: 10
        });
        $('#processedTable').DataTable({
            dom: 'Bfrtip',
            buttons: ['csv', 'excel'],
            pageLength: 10
        });
        $('#schoolsTable').DataTable({
            dom: 'Bfrtip',
            buttons: ['csv', 'excel'],
            pageLength: 10
        });

        // Process single candidate
        $('.processCandidateBtn').on('click', function() {
            const candidateId = $(this).data('candidate-id');
            $.ajax({
                url: 'process_results.php',
                type: 'POST',
                data: {
                    action: 'process_candidate',
                    candidate_id: candidateId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                timeout: 10000,
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error processing candidate. Please try again.');
                }
            });
        });

        // Process all candidates
        $('#processAllBtn').on('click', function() {
            if (confirm('Process all candidates for exam year <?php echo $exam_year_id; ?>?')) {
                $.ajax({
                    url: 'process_results.php',
                    type: 'POST',
                    data: {
                        action: 'process_all',
                        csrf_token: '<?php echo $csrf_token; ?>'
                    },
                    timeout: 30000,
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error processing all candidates. Please try again.');
                    }
                });
            }
        });

        // Update school status
        $('.updateStatusBtn').on('click', function() {
            const schoolId = $(this).data('school-id');
            $.ajax({
                url: 'process_results.php',
                type: 'POST',
                data: {
                    action: 'update_school_status',
                    school_id: schoolId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                timeout: 10000,
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error updating school status. Please try again.');
                }
            });
        });
    });
</script>
<?php
$content = ob_get_clean();
$page_title = 'Process Results';
$template_vars = [
    'board_name' => 'Luuka Examination Board',
    'exam_year' => date('Y'),
    'username' => htmlspecialchars($_SESSION['username']),
    'role' => $_SESSION['role']
];
require_once '../layout.php';
?>