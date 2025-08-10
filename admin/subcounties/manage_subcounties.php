<?php
ob_start();
require_once '../db_connect.php';

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Restrict to System Admins and Examination Administrators
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['System Admin', 'Examination Administrator'])) {
    error_log("Unauthorized access attempt, User ID: " . ($_SESSION['user_id'] ?? 'Not set'), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header("Location: $root_url" . "login.php");
    exit;
}

// Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$username = htmlspecialchars($_SESSION['username']);
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$errors = [];
$success = '';

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        error_log("CSRF token validation failed: Received " . ($_POST['csrf_token'] ?? 'none') . ", Expected $csrf_token", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
        $errors[] = "Invalid CSRF token.";
    } else {
        $subcounty_id = intval($_POST['subcounty_id'] ?? 0);
        $district_id = intval($_POST['district_id'] ?? 0);
        $subcounty = trim($_POST['subcounty'] ?? '');
        $constituency = trim($_POST['constituency'] ?? '');

        if ($district_id <= 0) {
            $errors[] = "Please select a district.";
        }
        if (empty($subcounty)) {
            $errors[] = "Subcounty name is required.";
        } elseif (strlen($subcounty) > 255) {
            $errors[] = "Subcounty name must be 255 characters or less.";
        }
        if (empty($constituency)) {
            $errors[] = "Constituency is required.";
        } elseif (strlen($constituency) > 255) {
            $errors[] = "Constituency must be 255 characters or less.";
        }

        // Check for duplicate subcounty in district
        $stmt = $conn->prepare("SELECT COUNT(*) FROM subcounties WHERE subcounty = ? AND district_id = ? AND id != ?");
        $stmt->bind_param("sii", $subcounty, $district_id, $subcounty_id);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()[0] > 0) {
            $errors[] = "Subcounty name already exists in this district.";
        }
        $stmt->close();

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE subcounties SET district_id = ?, subcounty = ?, constituency = ? WHERE id = ?");
            $stmt->bind_param("issi", $district_id, $subcounty, $constituency, $subcounty_id);
            if ($stmt->execute()) {
                $success = "Subcounty updated successfully.";
                $conn->query("CALL log_action('Edit Subcounty', $user_id, 'Updated subcounty ID: $subcounty_id')");
            } else {
                error_log("Failed to update subcounty ID: $subcounty_id", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                $errors[] = "Failed to update subcounty.";
            }
            $stmt->close();
        }
    }
}

// Fetch subcounties with metrics
$subcounties = [];
$result = $conn->query("
    SELECT 
        sc.id,
        sc.district_id,
        d.district_name,
        sc.subcounty,
        sc.constituency,
        COUNT(s.id) AS school_count,
        COUNT(c.id) AS candidate_count,
        SUM(CASE WHEN s.results_status = 'Declared' THEN 1 ELSE 0 END) AS declared_schools
    FROM subcounties sc
    JOIN districts d ON sc.district_id = d.id
    LEFT JOIN schools s ON sc.id = s.subcounty_id
    LEFT JOIN candidates c ON s.id = c.school_id
    GROUP BY sc.id
");
while ($row = $result->fetch_assoc()) {
    $subcounties[] = $row;
}

// Fetch districts for edit modal
$districts = $conn->query("SELECT id, district_name FROM districts ORDER BY district_name")->fetch_all(MYSQLI_ASSOC);

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');

// Set page title
$page_title = "Manage Subcounties";

// Define content
ob_start();
?>
<div class="page-header">
    <h1 class="page-title">Manage Subcounties
        <small class="text-muted ms-2">(<?php echo number_format(count($subcounties)); ?> total)</small>
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>Dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Manage Subcounties</li>
        </ol>
    </nav>
</div>

<div class="dashboard-card mb-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-2">
            <a href="<?php echo $base_url; ?>subcounties/add_subcounty.php" class="btn btn-enhanced">Add Subcounty</a>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <div class="card-body">
        <?php if ($success): ?>
            <div class="alert alert-success alert-enhanced alert-dismissible fade show">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger alert-enhanced alert-dismissible fade show">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table-enhanced">
                <thead>
                    <tr>
                        <th>District</th>
                        <th>Subcounty</th>
                        <th>Constituency</th>
                        <th>Schools</th>
                        <th>Candidates</th>
                        <th>Declared Schools</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subcounties)): ?>
                        <tr><td colspan="7" class="text-center py-4">No subcounties found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($subcounties as $subcounty): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subcounty['district_name']); ?></td>
                                <td><?php echo htmlspecialchars($subcounty['subcounty']); ?></td>
                                <td><?php echo htmlspecialchars($subcounty['constituency']); ?></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($subcounty['school_count']); ?></span></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($subcounty['candidate_count']); ?></span></td>
                                <td><span class="badge bg-success"><?php echo htmlspecialchars($subcounty['declared_schools']); ?></span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-enhanced btn-sm" data-toggle="modal" data-target="#editModal<?php echo $subcounty['id']; ?>" 
                                                style="background: linear-gradient(135deg, #3b82f6, #6366f1);" title="Edit Subcounty">Edit</button>
                                        <?php if ($role === 'System Admin'): ?>
                                            <button class="btn btn-enhanced btn-sm delete-btn" 
                                                    data-subcounty-id="<?php echo $subcounty['id']; ?>" 
                                                    data-subcounty-name="<?php echo htmlspecialchars($subcounty['subcounty']); ?>" 
                                                    data-csrf="<?php echo htmlspecialchars($csrf_token); ?>" 
                                                    style="background: linear-gradient(135deg, #ef4444, #dc2626);" title="Delete Subcounty">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?php echo $subcounty['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editModalLabel<?php echo $subcounty['id']; ?>">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" class="edit-subcounty-form">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editModalLabel<?php echo $subcounty['id']; ?>">Edit Subcounty</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="subcounty_id" value="<?php echo $subcounty['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <div class="form-group">
                                                    <label for="district_id_<?php echo $subcounty['id']; ?>">District</label>
                                                    <select class="form-control" id="district_id_<?php echo $subcounty['id']; ?>" name="district_id" required>
                                                        <option value="">Select District</option>
                                                        <?php foreach ($districts as $district): ?>
                                                            <option value="<?php echo $district['id']; ?>" <?php echo $subcounty['district_id'] == $district['id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($district['district_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="subcounty_<?php echo $subcounty['id']; ?>">Subcounty Name</label>
                                                    <input type="text" class="form-control" id="subcounty_<?php echo $subcounty['id']; ?>" name="subcounty" 
                                                           value="<?php echo htmlspecialchars($subcounty['subcounty']); ?>" required maxlength="255">
                                                </div>
                                                <div class="form-group">
                                                    <label for="constituency_<?php echo $subcounty['id']; ?>">Constituency</label>
                                                    <input type="text" class="form-control" id="constituency_<?php echo $subcounty['id']; ?>" name="constituency" 
                                                           value="<?php echo htmlspecialchars($subcounty['constituency']); ?>" required maxlength="255">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-enhanced btn-secondary" data-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-enhanced">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Schools per Subcounty -->
        <?php foreach ($subcounties as $subcounty): ?>
            <div class="dashboard-card mt-3">
                <div class="card-body">
                    <h5><?php echo htmlspecialchars($subcounty['subcounty']); ?> Schools (<?php echo htmlspecialchars($subcounty['district_name']); ?>)</h5>
                    <div class="table-responsive">
                        <table class="table-enhanced">
                            <thead>
                                <tr>
                                    <th>Center No</th>
                                    <th>School Name</th>
                                    <th>Type</th>
                                    <th>Candidates</th>
                                    <th>Results Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $schools = $conn->query("
                                    SELECT 
                                        s.id,
                                        s.center_no,
                                        s.school_name,
                                        st.type AS school_type,
                                        COUNT(c.id) AS candidate_count,
                                        s.results_status
                                    FROM schools s
                                    JOIN school_types st ON s.school_type_id = st.id
                                    LEFT JOIN candidates c ON s.id = c.school_id
                                    WHERE s.subcounty_id = {$subcounty['id']}
                                    GROUP BY s.id
                                ");
                                if ($schools->num_rows === 0): ?>
                                    <tr><td colspan="5" class="text-center py-4">No schools found.</td></tr>
                                <?php else: ?>
                                    <?php while ($school = $schools->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($school['center_no']); ?></td>
                                            <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                                            <td><span class="badge bg-info"><?php echo htmlspecialchars($school['school_type']); ?></span></td>
                                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($school['candidate_count']); ?></span></td>
                                            <td>
                                                <?php
                                                $status_class = match($school['results_status']) {
                                                    'Complete' => 'bg-success',
                                                    'Partial' => 'bg-warning text-dark',
                                                    'Pending' => 'bg-secondary',
                                                    default => 'bg-light text-dark'
                                                };
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo htmlspecialchars($school['results_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
jQuery.noConflict();
(function($) {
    $(document).ready(function() {
        console.log('Manage Subcounties page loaded with jQuery version:', $.fn.jquery);

        // Client-side validation for edit forms
        $('.edit-subcounty-form').on('submit', function(e) {
            const $form = $(this);
            const districtId = $form.find('select[name="district_id"]').val();
            const subcounty = $form.find('input[name="subcounty"]').val().trim();
            const constituency = $form.find('input[name="constituency"]').val().trim();

            if (!districtId) {
                e.preventDefault();
                window.showNotification('Please select a district.', 'error');
                return false;
            }
            if (!subcounty) {
                e.preventDefault();
                window.showNotification('Subcounty name is required.', 'error');
                return false;
            }
            if (subcounty.length > 255) {
                e.preventDefault();
                window.showNotification('Subcounty name must be 255 characters or less.', 'error');
                return false;
            }
            if (!constituency) {
                e.preventDefault();
                window.showNotification('Constituency is required.', 'error');
                return false;
            }
            if (constituency.length > 255) {
                e.preventDefault();
                window.showNotification('Constituency must be 255 characters or less.', 'error');
                return false;
            }

            // Show loading state
            const $submitBtn = $form.find('button[type="submit"]');
            $submitBtn.prop('disabled', true).text('Saving...');
        });

        // AJAX delete functionality
        $('.delete-btn').click(function() {
            const subcountyId = $(this).data('subcounty-id');
            const subcountyName = $(this).data('subcounty-name');
            const csrfToken = $(this).data('csrf');

            const confirmMessage = `Are you sure you want to delete "${subcountyName}"?\n\n` +
                                  `⚠️ This action will also remove associated data (if any).\n` +
                                  `This action cannot be undone!`;

            if (confirm(confirmMessage)) {
                const $button = $(this);
                $button.prop('disabled', true).text('Deleting...');

                $.ajax({
                    url: '<?php echo $base_url; ?>subcounties/delete_subcounty.php',
                    type: 'POST',
                    data: { 
                        subcounty_id: subcountyId, 
                        csrf_token: csrfToken 
                    },
                    timeout: 30000,
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                window.showNotification(`Subcounty "${subcountyName}" has been deleted successfully.`, 'success');
                                setTimeout(() => location.reload(), 2000);
                            } else {
                                window.showNotification(result.error || 'Failed to delete subcounty.', 'error');
                                $button.prop('disabled', false).text('Delete');
                            }
                        } catch (e) {
                            console.error('JSON Parse Error:', e, response);
                            window.showNotification('Invalid response from server.', 'error');
                            $button.prop('disabled', false).text('Delete');
                            error_log('JSON Parse Error in delete subcounty: ' + e.message, 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        window.showNotification(`Failed to delete subcounty: ${error}`, 'error');
                        $button.prop('disabled', false).text('Delete');
                        error_log('AJAX Error in delete subcounty: ' + status + ' - ' + error, 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                    }
                });
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert.alert-dismissible').fadeOut('slow');
        }, 5000);
    });
})(jQuery);
</script>
<?php
$content = ob_get_clean();

// Include layout
require_once '../layout.php';
?>