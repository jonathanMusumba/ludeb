<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();
require_once 'db_connect.php';

// Debug: Log session status
error_log("Payment.php - Session check: user_id=" . ($_SESSION['user_id'] ?? 'NOT_SET') . ", role=" . ($_SESSION['role'] ?? 'NOT_SET') . ", request_uri=" . $_SERVER['REQUEST_URI']);

// Define URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/school/';
$root_url = $protocol . $host . '/ludeb/';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
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
        pt.status as payment_status,
        pt.created_at as payment_date
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
    $message = "Error loading resource details: " . $e->getMessage();
    $message_type = "error";
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $csrf_token) {
    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    $transaction_id = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : '';
    
    // Validate inputs
    if (empty($phone_number) || empty($payment_method)) {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone_number)) {
        $message = "Please enter a valid 10-digit phone number.";
        $message_type = "error";
    } else {
        try {
            // Check if user already has a pending payment
            $stmt = $conn->prepare("SELECT id FROM payment_transactions WHERE user_id = ? AND resource_id = ? AND status = 'pending'");
            $stmt->bind_param("ii", $_SESSION['user_id'], $resource_id);
            $stmt->execute();
            $existing_payment = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($existing_payment) {
                $message = "You already have a pending payment for this resource. Please wait for verification.";
                $message_type = "warning";
            } else {
                // Insert payment transaction
                $stmt = $conn->prepare("
                    INSERT INTO payment_transactions (user_id, resource_id, amount, payment_method, phone_number, transaction_id, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->bind_param("iidsss", $_SESSION['user_id'], $resource_id, $resource['amount'], $payment_method, $phone_number, $transaction_id);
                $stmt->execute();
                $payment_id = $conn->insert_id;
                $stmt->close();
                
                // Log the payment submission
                $log_message = "Submitted payment for resource: {$resource['title']} (ID: {$resource_id}, Amount: UGX " . number_format($resource['amount']) . ")";
                $stmt = $conn->prepare("CALL log_action('Payment Submitted', ?, ?)");
                $stmt->bind_param("is", $_SESSION['user_id'], $log_message);
                $stmt->execute();
                $stmt->close();
                
                $message = "Payment submitted successfully! Your payment is being verified and you will be granted access once approved. This usually takes a few minutes.";
                $message_type = "success";
                
                // Refresh resource data
                $stmt = $conn->prepare("
                    SELECT r.*, u.username as uploader_name,
                    CASE 
                        WHEN r.type = 'free' THEN 1
                        WHEN ura.id IS NOT NULL THEN 1 
                        ELSE 0 
                    END as has_access,
                    pt.status as payment_status,
                    pt.created_at as payment_date
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
            }
            
        } catch (Exception $e) {
            error_log("Payment submission error: " . $e->getMessage());
            $message = "Error submitting payment. Please try again or contact support.";
            $message_type = "error";
        }
    }
}

$pageTitle = 'Resource Payment - ' . ($resource['title'] ?? 'Unknown Resource');
?>

<!-- Page Header -->
<div class="page-head bg-white p-4 shadow">
    <div class="container mx-auto flex justify-between items-center">
        <div class="page-title">
            <h1 class="text-2xl font-bold"><i class="fas fa-credit-card mr-2"></i>Resource Payment</h1>
            <nav aria-label="breadcrumb" class="text-sm text-gray-500">
                <ol class="flex space-x-2">
                    <li><a href="dashboard.php" class="hover:text-blue-600">Dashboard</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li><a href="resources.php" class="hover:text-blue-600">Resources</a></li>
                    <li><span class="mx-1">/</span></li>
                    <li class="text-gray-700">Payment</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<!-- Alerts -->
<?php if (!empty($message)): ?>
<div class="alert <?php echo $message_type === 'success' ? 'alert-success' : ($message_type === 'warning' ? 'alert-warning' : 'alert-danger'); ?> alert-dismissible fade show" role="alert">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?> me-2"></i>
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($resource)): ?>
<!-- Resource Details Card -->
<div class="bg-white p-6 shadow rounded-lg mb-6">
    <h3 class="text-xl font-semibold mb-4"><i class="fas fa-file-alt mr-2"></i>Resource Details</h3>
    <div class="flex flex-col md:flex-row">
        <div class="flex-1">
            <h4 class="text-lg font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($resource['title']); ?></h4>
            <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($resource['title']); ?></p>
            <div class="flex flex-wrap gap-2 mb-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    <i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars($resource['class']); ?>
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($resource['category']); ?>
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $resource['type'] === 'free' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'; ?>">
                    <i class="fas fa-<?php echo $resource['type'] === 'free' ? 'gift' : 'crown'; ?> mr-1"></i><?php echo ucfirst($resource['type']); ?>
                </span>
            </div>
            <p class="text-sm text-gray-500">Uploaded by: <?php echo htmlspecialchars($resource['uploader_name']); ?></p>
        </div>
        <div class="md:w-1/3 mt-4 md:mt-0">
            <div class="text-center p-4 border border-gray-300 rounded-lg bg-gradient-to-br from-gray-50 to-gray-100">
                <h2 class="text-2xl font-bold text-blue-600 mb-1">UGX <?php echo number_format($resource['amount'], 0); ?></h2>
                <p class="text-sm text-gray-500">One-time payment</p>
            </div>
        </div>
    </div>
</div>

<?php if ($resource['payment_status'] === 'pending'): ?>
<!-- Pending Payment Notice -->
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="fas fa-clock me-2"></i>
    <strong>Payment Pending:</strong> You submitted a payment for this resource on <?php echo date('M j, Y \a\t H:i', strtotime($resource['payment_date'])); ?>. 
    It is currently being verified by our administrators. You will be granted access once approved.
    <div class="mt-3">
        <a href="resources.php" class="btn bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Back to Resources
        </a>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<?php elseif ($resource['payment_status'] === 'verified'): ?>
<!-- Verified Payment Notice -->
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <strong>Payment Verified:</strong> Your payment has been approved! You can now download this resource.
    <div class="mt-3 flex gap-2">
        <a href="download.php?resource_id=<?php echo $resource_id; ?>" class="btn bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-download mr-2"></i>Download Now
        </a>
        <a href="resources.php" class="btn bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Back to Resources
        </a>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<?php else: ?>
<!-- Payment Form -->
<div class="bg-white p-6 shadow rounded-lg">
    <h3 class="text-xl font-semibold mb-4"><i class="fas fa-mobile-alt mr-2"></i>Mobile Money Payment</h3>
    <div class="bg-blue-50 p-4 rounded-lg mb-4">
        <p class="text-blue-800 font-medium mb-2"><i class="fas fa-info-circle mr-2"></i>Payment Instructions:</p>
        <ol class="list-decimal pl-5 text-sm text-gray-700">
            <li>Send <strong>UGX <?php echo number_format($resource['amount'], 0); ?></strong> via MTN Mobile Money or Airtel Money</li>
            <li>Note down your transaction reference (if provided)</li>
            <li>Fill in the form below with your payment details</li>
            <li>Submit for verification</li>
            <li>You'll receive access once payment is verified (usually within a few minutes)</li>
        </ol>
        <div class="mt-3 text-sm">
            <p><strong>For MTN:</strong> 0787842061</p>
            <p><strong>For Airtel:</strong> 0743470506</p>
            <p><strong>Account Name:</strong> Musumba Jonathan</p>
        </div>
    </div>
    
    <form method="POST" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method <span class="text-red-500">*</span></label>
                <select class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="payment_method" name="payment_method" required>
                    <option value="">Select payment method</option>
                    <option value="mtn_mobile_money">MTN Mobile Money</option>
                    <option value="airtel_money">Airtel Money</option>
                </select>
                <div class="invalid-feedback text-red-500 text-sm mt-1">Please select a payment method.</div>
            </div>
            <div>
                <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                <input type="tel" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="phone_number" name="phone_number" 
                       placeholder="0777123456" maxlength="10" required>
                <div class="invalid-feedback text-red-500 text-sm mt-1">Please enter a valid 10-digit phone number.</div>
                <p class="text-xs text-gray-500 mt-1">The phone number you used for the payment</p>
            </div>
        </div>
        
        <div class="mb-4">
            <label for="transaction_id" class="block text-sm font-medium text-gray-700 mb-1">Transaction Reference (Optional)</label>
            <input type="text" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="transaction_id" name="transaction_id" 
                   placeholder="e.g., MP240809.1234.A12345">
            <p class="text-xs text-gray-500 mt-1">If you received a transaction reference from your mobile money provider, please enter it here.</p>
        </div>
        
        <div class="mb-4">
            <div class="flex items-center">
                <input class="form-check-input h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" type="checkbox" id="terms" required>
                <label class="ml-2 text-sm text-gray-700" for="terms">
                    I confirm that I have completed the payment of <strong>UGX <?php echo number_format($resource['amount'], 0); ?></strong> 
                    for this resource using the selected payment method.
                </label>
            </div>
            <div class="invalid-feedback text-red-500 text-sm mt-1">Please confirm that you have completed the payment.</div>
        </div>
        
        <div class="flex justify-end gap-2">
            <a href="resources.php" class="btn bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Back to Resources
            </a>
            <button type="submit" class="btn bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-paper-plane mr-2"></i>Submit Payment Details
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php else: ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    Resource not found or access denied.
    <div class="mt-3">
        <a href="resources.php" class="btn bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Back to Resources
        </a>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<script>
// Bootstrap form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.forEach.call(forms, function(form) {
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
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 10) {
        value = value.slice(0, 10);
    }
    e.target.value = value;
    
    if (value.length === 10 && /^0[7-9][0-9]{8}$/.test(value)) {
        e.target.classList.remove('is-invalid');
        e.target.classList.add('is-valid');
    } else if (value.length > 0) {
        e.target.classList.remove('is-valid');
        e.target.classList.add('is-invalid');
    }
});

// Payment method change handler
document.getElementById('payment_method').addEventListener('change', function(e) {
    const phoneInput = document.getElementById('phone_number');
    const method = e.target.value;
    
    if (method === 'mtn_mobile_money') {
        phoneInput.placeholder = '0777123456 (MTN)';
    } else if (method === 'airtel_money') {
        phoneInput.placeholder = '0704123456 (Airtel)';
    } else {
        phoneInput.placeholder = '0777123456';
    }
});

// Auto-dismiss success alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 500);
        }, 5000);
    });
});
</script>

<style>
.alert-warning {
    background-color: #fefce8;
    border-left-color: #facc15;
    color: #854d0e;
}
.is-valid {
    border-color: #10b981;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2310b981' d='m2.3 6.73.8-.86-.7-.86c-.78.86-.78 2.07 0 2.93zm.8-.86 1.95-2.14c.78-.86.78-2.07 0-2.93l-.8.86c.39.47.39 1.07 0 1.54l-1.15 1.28-.8-.86z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}
.is-invalid {
    border-color: #ef4444;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23ef4444'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 4.6.6.6.6-.6M5.8 7.4l.6-.6.6.6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}
</style>

<?php
$content = ob_get_clean();
require_once 'layout.php';
?>