<?php
// ===== SCHOOL/PAYMENT.PHP =====
// This file handles the payment process for accessing premium resources
ob_start();
require_once 'db_connect.php';

// Define URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/school/';
$root_url = $protocol . $host . '/ludeb/';

// Check if user is logged in (regular users, not admin)
if (!isset($_SESSION['user_id'])) {
    header("Location: {$root_url}login.php");
    exit;
}

// Get resource ID
$resource_id = isset($_GET['resource_id']) ? intval($_GET['resource_id']) : 0;

if (!$resource_id) {
    header("Location: resources.php?error=invalid_resource");
    exit;
}

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$message = '';
$message_type = '';

// Get resource details
try {
    $stmt = $conn->prepare("
        SELECT r.*, u.username as uploader_name,
        CASE 
            WHEN r.type = 'free' THEN 1
            WHEN ura.id IS NOT NULL THEN 1 
            ELSE 0 
        END as has_access,
        pt.status as payment_status
        FROM resources r 
        LEFT JOIN system_users u ON r.uploader_id = u.id
        LEFT JOIN user_resource_access ura ON r.id = ura.resource_id AND ura.user_id = ?
        LEFT JOIN payment_transactions pt ON r.id = pt.resource_id AND pt.user_id = ? AND pt.status IN ('pending', 'verified')
        WHERE r.id = ? AND r.approved = 1
    ");
    
    $stmt->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $resource_id);
    $stmt->execute();
    $resource = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$resource) {
        header("Location: resources.php?error=resource_not_found");
        exit;
    }
    
    // If user already has access, redirect to download
    if ($resource['has_access']) {
        header("Location: download.php?resource_id=" . $resource_id);
        exit;
    }
    
    // If resource is free, grant access immediately
    if ($resource['type'] === 'free') {
        header("Location: download.php?resource_id=" . $resource_id);
        exit;
    }
    
} catch (Exception $e) {
    $message = "Error loading resource details.";
    $message_type = "error";
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $csrf_token) {
    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    $transaction_id = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : '';
    
    if (empty($phone_number) || empty($payment_method)) {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } else {
        try {
            // Insert payment transaction
            $stmt = $conn->prepare("
                INSERT INTO payment_transactions (user_id, resource_id, amount, payment_method, phone_number, transaction_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->bind_param("iidsss", $_SESSION['user_id'], $resource_id, $resource['price'], $payment_method, $phone_number, $transaction_id);
            $stmt->execute();
            $payment_id = $conn->insert_id;
            $stmt->close();
            
            // Log the payment submission
            $conn->query("CALL log_action('Payment Submitted', {$_SESSION['user_id']}, 'Submitted payment for resource: {$resource['title']} (ID: {$resource_id})')");
            
            $message = "Payment submitted successfully! Your payment is being verified and you will be granted access once approved.";
            $message_type = "success";
            
        } catch (Exception $e) {
            $message = "Error submitting payment. Please try again.";
            $message_type = "error";
        }
    }
}

$page_title = 'Resource Payment - ' . ($resource['title'] ?? 'Unknown Resource');
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-credit-card"></i> Resource Payment</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="resources.php">Resources</a></li>
            <li class="breadcrumb-item active">Payment</li>
        </ol>
    </nav>
</div>

<?php if (!empty($message)): ?>
<div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<?php if (isset($resource)): ?>
<!-- Resource Details Card -->
<div class="dashboard-card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-file-alt"></i> Resource Details</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <h4><?php echo htmlspecialchars($resource['title']); ?></h4>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($resource['description']); ?></p>
                <div class="mb-3">
                    <span class="badge bg-info me-2"><?php echo htmlspecialchars($resource['class']); ?></span>
                    <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($resource['category']); ?></span>
                    <span class="badge bg-warning"><?php echo ucfirst($resource['type']); ?></span>
                </div>
                <p><small class="text-muted">Uploaded by: <?php echo htmlspecialchars($resource['uploader_name']); ?></small></p>
            </div>
            <div class="col-md-4 text-end">
                <div class="price-display">
                    <h2 class="text-primary mb-0">UGX <?php echo number_format($resource['price'], 0); ?></h2>
                    <small class="text-muted">One-time payment</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($resource['payment_status'] === 'pending'): ?>
<!-- Pending Payment Notice -->
<div class="alert alert-info">
    <i class="fas fa-clock"></i>
    <strong>Payment Pending:</strong> You have already submitted a payment for this resource. 
    It is currently being verified by our administrators. You will be notified once approved.
</div>

<?php elseif ($resource['payment_status'] === 'verified'): ?>
<!-- Verified Payment Notice -->
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <strong>Payment Verified:</strong> Your payment has been approved! 
    <a href="download.php?resource_id=<?php echo $resource_id; ?>" class="btn btn-success btn-sm ms-2">
        <i class="fas fa-download"></i> Download Now
    </a>
</div>

<?php else: ?>
<!-- Payment Form -->
<div class="dashboard-card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-mobile-alt"></i> Mobile Money Payment</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Payment Instructions:</strong>
            <ol class="mb-0 mt-2">
                <li>Complete the mobile money transaction using your preferred method (MTN MoMo or Airtel Money)</li>
                <li>Fill in the form below with your payment details</li>
                <li>Submit for verification</li>
                <li>You'll receive access once payment is verified</li>
            </ol>
        </div>
        
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Select payment method</option>
                            <option value="mtn_mobile_money">MTN Mobile Money</option>
                            <option value="airtel_money">Airtel Money</option>
                        </select>
                        <div class="invalid-feedback">Please select a payment method.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                               placeholder="e.g., 0777123456" pattern="[0-9]{10}" required>
                        <div class="invalid-feedback">Please enter a valid 10-digit phone number.</div>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="transaction_id" class="form-label">Transaction ID (Optional)</label>
                <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                       placeholder="Mobile money transaction reference">
                <small class="form-text text-muted">If available, please provide the transaction reference from your mobile money provider.</small>
            </div>
            
            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I confirm that I have completed the payment of <strong>UGX <?php echo number_format($resource['price'], 0); ?></strong> 
                        for this resource and agree to the terms and conditions.
                    </label>
                    <div class="invalid-feedback">You must agree to the terms and conditions.</div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="resources.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Resources
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Payment
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
// Bootstrap form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Format phone number input
document.getElementById('phone_number').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
    if (value.length > 10) {
        value = value.slice(0, 10);
    }
    e.target.value = value;
});
</script>

<style>
.price-display {
    text-align: center;
    padding: 1rem;
    border: 2px solid #e9ecef;
    border-radius: 0.5rem;
    background-color: #f8f9fa;
}

.needs-validation .form-control:invalid {
    border-color: #dc3545;
}

.needs-validation .form-control:valid {
    border-color: #28a745;
}

.form-check-input:invalid ~ .form-check-label {
    color: #dc3545;
}

@media (max-width: 768px) {
    .price-display {
        margin-top: 1rem;
    }
}
</style>

<?php
$content = ob_get_clean();
include 'layout.php'; // Adjust path to your school layout file
?>