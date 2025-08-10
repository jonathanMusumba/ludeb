<?php
ob_start();
require_once '../db_connect.php';

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Restrict to authorized users
if (!isset($_SESSION['user_id'])) {
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

$page_title = 'Manage Resources';

// Handle AJAX requests for approval/decline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    $resource_id = intval($_POST['resource_id']);
    $action = $_POST['action'];
    
    if (!in_array($_SESSION['role'], ['System Admin', 'Examination Administrator'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
    
    if ($action === 'approve') {
        $sql = "UPDATE resources SET approved = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $resource_id);
        
        if ($stmt->execute()) {
            // Log the action
            $conn->query("CALL log_action('Resource Approved', {$_SESSION['user_id']}, 'Approved resource ID: $resource_id')");
            echo json_encode(['success' => true, 'message' => 'Resource approved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to approve resource']);
        }
        $stmt->close();
        
    } elseif ($action === 'decline') {
        // Get file path before deletion
        $sql = "SELECT file_path FROM resources WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $resource = $result->fetch_assoc();
        $stmt->close();
        
        if ($resource) {
            // Delete the resource from database
            $sql = "DELETE FROM resources WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $resource_id);
            
            if ($stmt->execute()) {
                // Delete the file
                if (file_exists($resource['file_path'])) {
                    unlink($resource['file_path']);
                }
                // Log the action
                $conn->query("CALL log_action('Resource Declined', {$_SESSION['user_id']}, 'Declined and deleted resource ID: $resource_id')");
                echo json_encode(['success' => true, 'message' => 'Resource declined and removed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to decline resource']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Resource not found']);
        }
        
    } elseif ($action === 'delete') {
        // Only allow deletion of own resources or by admin
        $where_clause = "id = ?";
        $params = [$resource_id];
        $types = "i";
        
        if (!in_array($_SESSION['role'], ['System Admin', 'Examination Administrator'])) {
            $where_clause .= " AND uploader_id = ?";
            $params[] = $_SESSION['user_id'];
            $types .= "i";
        }
        
        // Get file path before deletion
        $sql = "SELECT file_path FROM resources WHERE $where_clause";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $resource = $result->fetch_assoc();
        $stmt->close();
        
        if ($resource) {
            $sql = "DELETE FROM resources WHERE $where_clause";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                // Delete the file
                if (file_exists($resource['file_path'])) {
                    unlink($resource['file_path']);
                }
                // Log the action
                $conn->query("CALL log_action('Resource Deleted', {$_SESSION['user_id']}, 'Deleted resource ID: $resource_id')");
                echo json_encode(['success' => true, 'message' => 'Resource deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete resource']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Resource not found or unauthorized']);
        }
    }
    exit;
}

// Filter parameters
$filter_class = isset($_GET['class']) ? trim($_GET['class']) : '';
$filter_category = isset($_GET['category']) ? trim($_GET['category']) : '';
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build query based on user role
$is_admin = in_array($_SESSION['role'], ['System Admin', 'Examination Administrator']);

$sql = "SELECT r.id, r.title, r.file_path, r.type, r.amount, r.class, r.category, r.created_at, 
               u.username AS uploader, r.approved, r.uploader_id
        FROM resources r 
        JOIN system_users u ON r.uploader_id = u.id";

$where_conditions = [];
$params = [];
$types = "";

// Add filters
if (!empty($filter_class)) {
    $where_conditions[] = "r.class = ?";
    $params[] = $filter_class;
    $types .= "s";
}
if (!empty($filter_category)) {
    $where_conditions[] = "r.category = ?";
    $params[] = $filter_category;
    $types .= "s";
}
if (!empty($filter_type)) {
    $where_conditions[] = "r.type = ?";
    $params[] = $filter_type;
    $types .= "s";
}
if (!empty($filter_status)) {
    if ($filter_status === 'approved') {
        $where_conditions[] = "r.approved = 1";
    } elseif ($filter_status === 'pending') {
        $where_conditions[] = "r.approved = 0";
    }
}

// Non-admin users can only see their own resources
if (!$is_admin) {
    $where_conditions[] = "r.uploader_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN approved = 1 THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN approved = 0 THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN type = 'premium' THEN 1 ELSE 0 END) as premium
    FROM resources";

if (!$is_admin) {
    $stats_sql .= " WHERE uploader_id = " . $_SESSION['user_id'];
}

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-box-open"></i> Manage Resources</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Manage Resources</li>
        </ol>
    </nav>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="dashboard-card">
            <i class="fas fa-box-open"></i>
            <div class="card-title">Total Resources</div>
            <div class="card-text"><?php echo $stats['total']; ?></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="dashboard-card">
            <i class="fas fa-check-circle text-success"></i>
            <div class="card-title">Approved</div>
            <div class="card-text"><?php echo $stats['approved']; ?></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="dashboard-card">
            <i class="fas fa-clock text-warning"></i>
            <div class="card-title">Pending Approval</div>
            <div class="card-text"><?php echo $stats['pending']; ?></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="dashboard-card">
            <i class="fas fa-star text-primary"></i>
            <div class="card-title">Premium Resources</div>
            <div class="card-text"><?php echo $stats['premium']; ?></div>
        </div>
    </div>
</div>

<!-- Filters and Actions -->
<div class="dashboard-card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">
                <i class="fas fa-filter"></i>
                Filter Resources
            </h5>
            <a href="create.php" class="btn-enhanced">
                <i class="fas fa-plus"></i>
                Create Resource
            </a>
        </div>
        
        <form method="GET" class="row g-3" id="filterForm">
            <div class="col-md-2">
                <label class="form-label">Class</label>
                <select name="class" class="form-control">
                    <option value="">All Classes</option>
                    <option value="Baby" <?php echo ($filter_class === 'Baby') ? 'selected' : ''; ?>>Baby</option>
                    <option value="Middle" <?php echo ($filter_class === 'Middle') ? 'selected' : ''; ?>>Middle</option>
                    <option value="Top" <?php echo ($filter_class === 'Top') ? 'selected' : ''; ?>>Top</option>
                    <option value="P1" <?php echo ($filter_class === 'P1') ? 'selected' : ''; ?>>P1</option>
                    <option value="P2" <?php echo ($filter_class === 'P2') ? 'selected' : ''; ?>>P2</option>
                    <option value="P3" <?php echo ($filter_class === 'P3') ? 'selected' : ''; ?>>P3</option>
                    <option value="P4" <?php echo ($filter_class === 'P4') ? 'selected' : ''; ?>>P4</option>
                    <option value="P5" <?php echo ($filter_class === 'P5') ? 'selected' : ''; ?>>P5</option>
                    <option value="P6" <?php echo ($filter_class === 'P6') ? 'selected' : ''; ?>>P6</option>
                    <option value="P7" <?php echo ($filter_class === 'P7') ? 'selected' : ''; ?>>P7</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select name="category" class="form-control">
                    <option value="">All Categories</option>
                    <option value="Notes" <?php echo ($filter_category === 'Notes') ? 'selected' : ''; ?>>Notes</option>
                    <option value="Exam" <?php echo ($filter_category === 'Exam') ? 'selected' : ''; ?>>Exam</option>
                    <option value="Other" <?php echo ($filter_category === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="type" class="form-control">
                    <option value="">All Types</option>
                    <option value="free" <?php echo ($filter_type === 'free') ? 'selected' : ''; ?>>Free</option>
                    <option value="premium" <?php echo ($filter_type === 'premium') ? 'selected' : ''; ?>>Premium</option>
                </select>
            </div>
            <?php if ($is_admin): ?>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="approved" <?php echo ($filter_status === 'approved') ? 'selected' : ''; ?>>Approved</option>
                    <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i>
                        Filter
                    </button>
                    <a href="manage.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i>
                        Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Resources Table -->
<div class="dashboard-card">
    <div class="card-body">
        <h5 class="card-title">
            <i class="fas fa-list"></i>
            Resources List
        </h5>
        
        <?php if ($result->num_rows === 0): ?>
        <div class="text-center py-5">
            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
            <p class="text-muted">No resources found matching your criteria.</p>
            <a href="create.php" class="btn-enhanced">
                <i class="fas fa-plus"></i>
                Create Your First Resource
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table-enhanced" id="resourcesTable">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Class</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <?php if ($is_admin): ?>
                        <th>Uploader</th>
                        <?php endif; ?>
                        <th>Created</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-alt me-2 text-primary"></i>
                                <div>
                                    <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                                    <?php if ($row['type'] === 'premium'): ?>
                                    <span class="badge bg-warning ms-1">Premium</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($row['class']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $row['category'] === 'Exam' ? 'danger' : ($row['category'] === 'Notes' ? 'info' : 'secondary'); ?>">
                                <?php echo htmlspecialchars($row['category']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['type'] === 'premium'): ?>
                            <span class="badge bg-warning">Premium</span>
                            <?php else: ?>
                            <span class="badge bg-success">Free</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $row['amount'] ? '$' . number_format($row['amount'], 2) : 'Free'; ?>
                        </td>
                        <?php if ($is_admin): ?>
                        <td><?php echo htmlspecialchars($row['uploader']); ?></td>
                        <?php endif; ?>
                        <td>
                            <small class="text-muted">
                                <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($row['approved']): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check"></i> Approved
                            </span>
                            <?php else: ?>
                            <span class="badge bg-warning">
                                <i class="fas fa-clock"></i> Pending
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <!-- View/Download Button -->
                                <button class="btn-enhanced btn-sm view-btn" 
                                        data-id="<?php echo $row['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($row['title']); ?>"
                                        data-class="<?php echo htmlspecialchars($row['class']); ?>"
                                        data-category="<?php echo htmlspecialchars($row['category']); ?>"
                                        data-type="<?php echo htmlspecialchars($row['type']); ?>"
                                        data-amount="<?php echo $row['amount']; ?>"
                                        data-uploader="<?php echo htmlspecialchars($row['uploader']); ?>"
                                        data-created="<?php echo $row['created_at']; ?>"
                                        data-approved="<?php echo $row['approved']; ?>"
                                        data-file="../../<?php echo htmlspecialchars($row['file_path']); ?>"
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <!-- Admin Actions for Pending Resources -->
                                <?php if ($is_admin && !$row['approved']): ?>
                                <button class="btn btn-success btn-sm approve-btn" 
                                        data-id="<?php echo $row['id']; ?>" 
                                        title="Approve Resource">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-danger btn-sm decline-btn" 
                                        data-id="<?php echo $row['id']; ?>" 
                                        title="Decline & Delete Resource">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                                
                                <!-- Delete Button (Own resources or Admin) -->
                                <?php if ($is_admin || $row['uploader_id'] == $_SESSION['user_id']): ?>
                                <button class="btn btn-danger btn-sm delete-btn" 
                                        data-id="<?php echo $row['id']; ?>" 
                                        title="Delete Resource">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Resource Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalLabel">
                    <i class="fas fa-eye"></i>
                    Resource Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><i class="fas fa-heading me-2"></i>Title:</strong></p>
                        <p class="ms-3" id="view_title"></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong><i class="fas fa-graduation-cap me-2"></i>Class:</strong></p>
                        <p class="ms-3" id="view_class"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><i class="fas fa-tags me-2"></i>Category:</strong></p>
                        <p class="ms-3" id="view_category"></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong><i class="fas fa-dollar-sign me-2"></i>Type:</strong></p>
                        <p class="ms-3" id="view_type"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><i class="fas fa-money-bill me-2"></i>Amount:</strong></p>
                        <p class="ms-3" id="view_amount"></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong><i class="fas fa-user me-2"></i>Uploader:</strong></p>
                        <p class="ms-3" id="view_uploader"></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><i class="fas fa-calendar me-2"></i>Created:</strong></p>
                        <p class="ms-3" id="view_created_at"></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong><i class="fas fa-check-circle me-2"></i>Status:</strong></p>
                        <p class="ms-3" id="view_approved"></p>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <a href="#" id="view_file_link" class="btn-enhanced" target="_blank">
                        <i class="fas fa-download"></i>
                        Download File
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmAction">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let currentAction = '';
    let currentResourceId = '';
    
    // View Resource Modal
    $(".view-btn").on("click", function() {
        const data = $(this).data();
        $("#view_title").text(data.title);
        $("#view_class").text(data.class);
        $("#view_category").html(`<span class="badge bg-${data.category === 'Exam' ? 'danger' : (data.category === 'Notes' ? 'info' : 'secondary')}">${data.category}</span>`);
        $("#view_type").html(`<span class="badge bg-${data.type === 'premium' ? 'warning' : 'success'}">${data.type === 'premium' ? 'Premium' : 'Free'}</span>`);
        $("#view_amount").text(data.amount ? '$' + parseFloat(data.amount).toFixed(2) : 'Free');
        $("#view_uploader").text(data.uploader);
        $("#view_created_at").text(new Date(data.created).toLocaleDateString('en-US', {
            year: 'numeric', month: 'long', day: 'numeric'
        }));
        $("#view_approved").html(`<span class="badge bg-${data.approved == '1' ? 'success' : 'warning'}">${data.approved == '1' ? 'Approved' : 'Pending'}</span>`);
        $("#view_file_link").attr("href", data.file);
        $("#viewModal").modal('show');
    });
    
    // Approve Resource
    $(".approve-btn").on("click", function() {
        currentResourceId = $(this).data("id");
        currentAction = 'approve';
        $("#confirmMessage").text("Are you sure you want to approve this resource? It will be made available to all users.");
        $("#confirmAction").removeClass('btn-danger').addClass('btn-success').text('Approve');
        $("#confirmModal").modal('show');
    });
    
    // Decline Resource
    $(".decline-btn").on("click", function() {
        currentResourceId = $(this).data("id");
        currentAction = 'decline';
        $("#confirmMessage").text("Are you sure you want to decline this resource? This will permanently delete the resource and its file.");
        $("#confirmAction").removeClass('btn-success').addClass('btn-danger').text('Decline & Delete');
        $("#confirmModal").modal('show');
    });
    
    // Delete Resource
    $(".delete-btn").on("click", function() {
        currentResourceId = $(this).data("id");
        currentAction = 'delete';
        $("#confirmMessage").text("Are you sure you want to delete this resource? This action cannot be undone.");
        $("#confirmAction").removeClass('btn-success').addClass('btn-danger').text('Delete');
        $("#confirmModal").modal('show');
    });
    
    // Confirm Action
    $("#confirmAction").on("click", function() {
        if (!currentResourceId || !currentAction) return;
        
        const button = $(this);
        const originalText = button.text();
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        $.ajax({
            url: "manage.php",
            type: "POST",
            data: {
                action: currentAction,
                resource_id: currentResourceId,
                csrf_token: "<?php echo $csrf_token; ?>"
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification(response.message, "success");
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(response.message, "error");
                }
            },
            error: function() {
                showNotification("An error occurred while processing your request.", "error");
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
                $("#confirmModal").modal('hide');
                currentAction = '';
                currentResourceId = '';
            }
        });
    });
    
    // Auto-submit filter form when changed
    $("#filterForm select").on("change", function() {
        $(this).closest("form").submit();
    });
    
    // Initialize tooltips
    $('[title]').each(function() {
        $(this).tooltip();
    });
});
</script>

<style>
.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}

.badge {
    font-size: 0.75em;
}

.table-enhanced td {
    vertical-align: middle;
}

.modal-body p {
    margin-bottom: 0.5rem;
}

.modal-body .ms-3 {
    margin-left: 1rem !important;
    color: #6c757d;
}

.btn-enhanced:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .btn-group {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .btn-group .btn {
        margin: 0;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
}
</style>

<?php
$stmt->close();
$content = ob_get_clean();
include '../layout.php';
?>