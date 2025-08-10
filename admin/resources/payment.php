<?php
// ===== ADMIN/RESOURCES/MANAGE_PAYMENTS.PHP =====

ob_start();
require_once '../db_connect.php';

// Define URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Authentication check - only System Admin and Examination Administrator
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['System Admin', 'Examination Administrator'])) {
    header("Location: $root_url" . "login.php");
    exit;
}

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$page_title = 'Payment Management';
$message = '';
$message_type = '';

// Handle payment verification/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $csrf_token) {
    $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    if ($payment_id && in_array($action, ['verify', 'reject'])) {
        try {
            $conn->begin_transaction();
            
            if ($action === 'verify') {
                // Use stored procedure to grant access
                $stmt = $conn->prepare("CALL grant_resource_access(?, ?)");
                $stmt->bind_param("ii", $payment_id, $_SESSION['user_id']);
                $stmt->execute();
                $stmt->close();
                
                $message = 'Payment verified successfully and access granted to user.';
                $message_type = 'success';
            } else {
                // Reject payment
                $stmt = $conn->prepare("UPDATE payment_transactions SET status = 'failed', notes = ?, verified_by = ?, verified_at = NOW() WHERE id = ? AND status = 'pending'");
                $stmt->bind_param("sii", $notes, $_SESSION['user_id'], $payment_id);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    $stmt->close();
                    
                    // Log the rejection
                    $conn->query("CALL log_action('Payment Rejected', {$_SESSION['user_id']}, 'Rejected payment ID: $payment_id. Reason: $notes')");
                    
                    $message = 'Payment rejected successfully.';
                    $message_type = 'success';
                } else {
                    throw new Exception('Payment not found or already processed.');
                }
            }
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t'); // Last day of current month

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];
$param_types = "";

if ($status_filter !== 'all') {
    $where_conditions[] = "pt.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($date_from) {
    $where_conditions[] = "DATE(pt.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if ($date_to) {
    $where_conditions[] = "DATE(pt.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM payment_transactions pt $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_payments = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_payments / $per_page);

// Get payments
$sql = "SELECT pt.*, u.username, r.title as resource_title, r.class, r.category,
        v.username as verified_by_name
        FROM payment_transactions pt
        LEFT JOIN system_users u ON pt.user_id = u.id
        LEFT JOIN resources r ON pt.resource_id = r.id
        LEFT JOIN system_users v ON pt.verified_by = v.id
        $where_clause
        ORDER BY pt.created_at DESC
        LIMIT ? OFFSET ?";

$all_params = [...$params, $per_page, $offset];
$all_param_types = $param_types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($all_param_types, ...$all_params);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) as total_verified_amount
    FROM payment_transactions pt";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-credit-card"></i> Payment Management</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="manage.php">Resources</a></li>
            <li class="breadcrumb-item active">Payments</li>
        </ol>
    </nav>
</div>

<?php if (!empty($message)): ?>
<div class="alert-enhanced alert-<?php echo $message_type === 'success' ? 'success' : 'warning'; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card bg-info">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Payments</div>
            <i class="fas fa-receipt stat-icon"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-warning">
            <div class="stat-number"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Pending Verification</div>
            <i class="fas fa-clock stat-icon"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-success">
            <div class="stat-number"><?php echo $stats['verified']; ?></div>
            <div class="stat-label">Verified</div>
            <i class="fas fa-check-circle stat-icon"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-primary">
            <div class="stat-number">UGX <?php echo number_format($stats['total_verified_amount'], 0); ?></div>
            <div class="stat-label">Total Revenue</div>
            <i class="fas fa-money-bill-wave stat-icon"></i>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="dashboard-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                    <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed/Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn-enhanced me-2">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="?" class="btn btn-secondary">
                        <i class="fas fa-refresh"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Payments Table -->
<div class="dashboard-card">
    <div class="card-header">
        <h5 class="mb-0">Payment Transactions (<?php echo $total_payments; ?> total)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($payments)): ?>
        <div class="text-center p-5">
            <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
            <h6>No payments found</h6>
            <p class="text-muted">No payment transactions match the selected criteria.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Transaction Details</th>
                        <th>Resource</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($payment['username']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($payment['phone_number']); ?>
                                    <?php if ($payment['transaction_id']): ?>
                                    <br>ID: <?php echo htmlspecialchars($payment['transaction_id']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($payment['resource_title']); ?></strong>
                                <br>
                                <span class="badge bg-info me-1"><?php echo htmlspecialchars($payment['class']); ?></span>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($payment['category']); ?></span>
                            </div>
                        </td>
                        <td>
                            <strong>UGX <?php echo number_format($payment['amount'], 0); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $payment['payment_method'] === 'mtn_mobile_money' ? 'warning' : 'danger'; ?>">
                                <?php echo $payment['payment_method'] === 'mtn_mobile_money' ? 'MTN MoMo' : 'Airtel Money'; ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $status_colors = [
                                'pending' => 'warning',
                                'verified' => 'success',
                                'failed' => 'danger'
                            ];
                            $color = $status_colors[$payment['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                            <?php if ($payment['verified_by_name']): ?>
                            <br><small class="text-muted">by <?php echo htmlspecialchars($payment['verified_by_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small>
                                <?php echo date('M j, Y', strtotime($payment['created_at'])); ?>
                                <br>
                                <?php echo date('H:i', strtotime($payment['created_at'])); ?>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group-vertical">
                                <button type="button" class="btn btn-sm btn-outline-info" 
                                        onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                
                                <?php if ($payment['status'] === 'pending'): ?>
                                <button type="button" class="btn btn-sm btn-success" 
                                        onclick="showVerificationModal(<?php echo $payment['id']; ?>, 'verify')">
                                    <i class="fas fa-check"></i> Verify
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="showVerificationModal(<?php echo $payment['id']; ?>, 'reject')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="paymentDetailsContent">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Verification Modal -->
<div class="modal fade" id="verificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="payment_id" id="verifyPaymentId">
                <input type="hidden" name="action" id="verifyAction">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="verificationTitle">Verify Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span id="verificationMessage">Are you sure you want to verify this payment?</span>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Add any notes about this verification..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="verificationBtn">Verify Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewPaymentDetails(paymentId) {
    $('#paymentDetailsModal').modal('show');
    $('#paymentDetailsContent').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>');
    
    // You would implement an API endpoint to fetch payment details
    // For now, showing a placeholder
    setTimeout(() => {
        $('#paymentDetailsContent').html(`
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Payment details for ID: ${paymentId} would be loaded here via AJAX.
            </div>
        `);
    }, 1000);
}

function showVerificationModal(paymentId, action) {
    $('#verifyPaymentId').val(paymentId);
    $('#verifyAction').val(action);
    
    if (action === 'verify') {
        $('#verificationTitle').text('Verify Payment');
        $('#verificationMessage').text('Are you sure you want to verify this payment? This will grant the user access to the resource.');
        $('#verificationBtn').removeClass('btn-danger').addClass('btn-success').html('<i class="fas fa-check"></i> Verify Payment');
        $('#notes').attr('placeholder', 'Add any verification notes...');
    } else {
        $('#verificationTitle').text('Reject Payment');
        $('#verificationMessage').text('Are you sure you want to reject this payment? Please provide a reason.');
        $('#verificationBtn').removeClass('btn-success').addClass('btn-danger').html('<i class="fas fa-times"></i> Reject Payment');
        $('#notes').attr('placeholder', 'Please provide a reason for rejection...').prop('required', true);
    }
    
    $('#notes').val('');
    $('#verificationModal').modal('show');
}

// Auto-dismiss alerts
$(document).ready(function() {
    $('.alert-enhanced').delay(5000).fadeOut();
});
</script>

<style>
.stat-card {
    background: linear-gradient(135deg, var(--bs-primary) 0%, var(--bs-primary-dark) 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 0.5rem;
    position: relative;
    overflow: hidden;
    margin-bottom: 1rem;
}

.stat-card.bg-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
}

.stat-card.bg-warning {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    color: #212529;
}

.stat-card.bg-success {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
}

.stat-card.bg-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
    margin-top: 0.5rem;
}

.stat-icon {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 3rem;
    opacity: 0.2;
}

.btn-group-vertical .btn {
    margin-bottom: 2px;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-size: 0.75em;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn-group-vertical .btn {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    .stat-card {
        text-align: center;
        margin-bottom: 1rem;
    }
    
    .stat-icon {
        display: none;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../layout.php';
?>