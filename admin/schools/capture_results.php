<?php
//session_start();
require_once 'db_connect.php';

// Restrict to System Admins and Data Entrants
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['System Admin', 'Data Entrant'])) {
    header("Location: ../login.php");
    exit();
}

$center_no = isset($_GET['center_no']) ? trim($_GET['center_no']) : '';
if (empty($center_no)) {
    die("Invalid center number");
}

// Fetch school data
$stmt = $conn->prepare("SELECT id, school_name FROM schools WHERE center_no = ?");
$stmt->bind_param("s", $center_no);
$stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
if (!$school) {
    die("School not found");
}

$school_id = htmlspecialchars($school['id']);

// Fetch subjects
$stmt = $conn->query("SELECT id, name FROM schools ORDER BY name");
$subjects = $stmt->fetch_all(MYSQLI_ASSOC);

// Fetch subjects
$stmt = $conn->query("SELECT id, name FROM subjects ORDER BY name");
$subjects = $stmt->fetch_all(MYSQLI_ASSOC);

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Set page title
$page_title = "Capture Results";

// Define content
ob_start();
?>
<div class="page-header">
    <h1 class="page-title">Capture Results for <?php echo htmlspecialchars($school['school_name']); ?></h1>
    <nav aria-label="breadcrumb">
        <ol class="nav-item">
            <li class="nav-link"><a href="<?php echo $_SESSION['role'] === 'System Admin' ? 'home.php' : '../Entrant/Home.php'; ?>">Dashboard</a></li>
            <?php if ($_SESSION['role'] === 'System Admin'): ?>
                <li class="nav-link"><a href="schools/manage_schools.php">Manage Schools</a></li>
                <li class="nav-link"><a href="schools/view_school.php?id=<?php echo $school_id; ?>">View School</a></li>
            <?php endif; ?>
            <li class="active" aria-current="page">Capture Results</li>
        </ol>
    </nav>
</div>

<div class="dashboard-card">
    <div class="card-body">
        <form id="resultsForm" action="schools/save_results.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
            <div class="form-group mb-3">
                <label for="subject_id" class="form-label">Select Subject:</label>
                <select class="form-control filter-container select" id="subject_id" name="subject_id" required>
                    <option value="">Select a Subject</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <h3 class="mt-4">Enter Marks</h3>
            <div id="candidatesList">
                <p>Select a subject to load candidates.</p>
            </div>
            <button type="submit" class="btn btn-enhanced mt-4">Submit Marks</button>
        </form>
    </div>
</div>
<script>
$(document).ready(function() {
    // Load candidates when subject is selected
    $('#subject_id').change(function() {
        const subjectId = $(this).val();
        const schoolId = $('input[name="school_id"]').val();
        const csrfToken = '<?php echo $csrf_token; ?>';

        if (subjectId && schoolId) {
            $.ajax({
                url: 'schools/fetch_candidates.php',
                type: 'POST',
                data: { 
                    subject_id: subjectId, 
                    school_id: schoolId,
                    csrf_token: csrfToken
                },
                success: function(data) {
                    try {
                        const response = JSON.parse(data);
                        if (response.error) {
                            $('#candidatesList').html('<p class="text-danger">' + response.error + '</p>');
                            return;
                        }
                        let html = '<table class="table-enhanced"><thead><tr><th>Index Number</th><th>Candidate Name</th><th>Mark</th></tr></thead><tbody>';
                        response.candidates.forEach(candidate => {
                            html += `<tr>
                                <td>${candidate.index_number}</td>
                                <td>${candidate.candidate_name}</td>
                                <td><input type="number" name="marks[${candidate.id}]" class="form-control" min="0" max="100" value="${candidate.mark || ''}"></td>
                            </tr>`;
                        });
                        html += '</tbody></table>';
                        $('#candidatesList').html(html);
                    } catch (e) {
                        $('#candidatesList').html('<p class="text-danger">Failed to load candidates: ' + e.message + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#candidatesList').html('<p class="text-danger">Error loading candidates: ' + error + '</p>');
                }
            });
        } else {
            $('#candidatesList').html('<p>Select a subject to load candidates.</p>');
        }
    });
});
</script>
<?php
$content = ob_get_clean();

// Include layout
require_once '../layout.php';
?>