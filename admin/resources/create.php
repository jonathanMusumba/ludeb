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

$page_title = 'Create Resource';

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && isset($_POST['title']) && isset($_POST['class']) && isset($_POST['category'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        $uploader_id = $_SESSION['user_id'];
        $title = trim($_POST['title']);
        $class = trim($_POST['class']);
        $category = trim($_POST['category']);
        $type = $_POST['type'] ?? 'free';
        $amount = null;
        
        // Only System Admin can create premium resources
        if ($type === 'premium') {
            if ($_SESSION['role'] !== 'System Admin') {
                $message = 'Only System Administrators can create premium resources.';
                $message_type = 'error';
            } else {
                $amount = floatval($_POST['amount'] ?? 0);
                if ($amount <= 0) {
                    $message = 'Premium resources must have a valid amount greater than 0 UGX.';
                    $message_type = 'error';
                }
            }
        }
        
        if (empty($message)) {
            // Validate file
            $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
            $file_extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $max_file_size = 10 * 1024 * 1024; // 10MB
            
            if (!in_array($file_extension, $allowed_extensions)) {
                $message = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions);
                $message_type = 'error';
            } elseif ($_FILES['file']['size'] > $max_file_size) {
                $message = 'File size too large. Maximum allowed size is 10MB.';
                $message_type = 'error';
            } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $message = 'File upload failed. Please try again.';
                $message_type = 'error';
            } else {
                // Create upload directory structure: uploads/resources/class/
                $base_upload_dir = '../../uploads/resources/';
                $class_dir = strtolower(str_replace(' ', '_', $class)); // Convert class to lowercase and replace spaces
                $upload_dir = $base_upload_dir . $class_dir . '/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $file_name = $_FILES['file']['name'];
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $base_name = pathinfo($file_name, PATHINFO_FILENAME);
                $unique_name = $base_name . '_' . uniqid() . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $unique_name;
                
                if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
                    // Auto-approve for System Admin, require approval for others
                    $approved = ($_SESSION['role'] === 'System Admin') ? 1 : 0;
                    
                    $sql = "INSERT INTO resources (file_path, title, uploader_id, type, amount, class, category, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssissssi", $file_path, $title, $uploader_id, $type, $amount, $class, $category, $approved);
                    
                    if ($stmt->execute()) {
                        $message = $approved 
                            ? "Resource created and published successfully."
                            : "Resource uploaded successfully and is awaiting approval.";
                        $message_type = 'success';
                        
                        // Log the action
                        $action_desc = "Created resource: " . $title . " (Class: " . $class . ", Category: " . $category . ")";
                        $conn->query("CALL log_action('Resource Created', $uploader_id, '$action_desc')");
                    } else {
                        unlink($file_path); // Remove uploaded file if database insert fails
                        $message = 'Failed to create resource. Database error.';
                        $message_type = 'error';
                    }
                    $stmt->close();
                } else {
                    $message = 'Failed to upload file. Please try again.';
                    $message_type = 'error';
                }
            }
        }
    }
}
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-plus"></i> Create Resource</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="manage.php">Resources</a></li>
            <li class="breadcrumb-item active">Create Resource</li>
        </ol>
    </nav>
</div>

<?php if (!empty($message)): ?>
<div class="alert-enhanced alert-<?php echo $message_type === 'success' ? 'success' : 'warning'; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8 col-md-10 mx-auto">
        <div class="dashboard-card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-cloud-upload-alt"></i>
                    Upload New Resource
                </h5>
                <p class="text-muted mb-4">Share educational materials with other users. Files will be reviewed before publishing.</p>
                
                <form method="POST" action="" enctype="multipart/form-data" id="resourceForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">
                            <i class="fas fa-heading"></i>
                            Resource Title <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="title" name="title" required maxlength="255" 
                               placeholder="Enter a descriptive title for your resource">
                        <div class="form-text">Choose a clear, descriptive title that helps others understand the content.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="class" class="form-label">
                                    <i class="fas fa-graduation-cap"></i>
                                    Class Level <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="class" name="class" required>
                                    <option value="">-- Select Class --</option>
                                    <option value="Baby">Baby</option>
                                    <option value="Middle">Middle</option>
                                    <option value="Top">Top</option>
                                    <option value="P1">Primary 1</option>
                                    <option value="P2">Primary 2</option>
                                    <option value="P3">Primary 3</option>
                                    <option value="P4">Primary 4</option>
                                    <option value="P5">Primary 5</option>
                                    <option value="P6">Primary 6</option>
                                    <option value="P7">Primary 7</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">
                                    <i class="fas fa-tags"></i>
                                    Category <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">-- Select Category --</option>
                                    <option value="Notes">Study Notes</option>
                                    <option value="Exam">Exam Papers</option>
                                    <option value="Other">Other Materials</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">
                                    <i class="fas fa-dollar-sign"></i>
                                    Resource Type
                                </label>
                                <select class="form-control" id="type" name="type">
                                    <option value="free">Free Resource</option>
                                    <?php if ($_SESSION['role'] === 'System Admin'): ?>
                                    <option value="premium">Premium Resource</option>
                                    <?php endif; ?>
                                </select>
                                <?php if ($_SESSION['role'] !== 'System Admin'): ?>
                                <div class="form-text">Only System Administrators can create premium resources.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($_SESSION['role'] === 'System Admin'): ?>
                        <div class="col-md-6">
                            <div class="mb-3 premium-field" style="display:none;">
                                <label for="amount" class="form-label">
                                    <i class="fas fa-money-bill"></i>
                                    Price (UGX) <span class="text-danger">*</span>
                                </label>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" 
                                       placeholder="0.00">
                                <div class="form-text">Set the price for this premium resource in UGX.</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="file" class="form-label">
                            <i class="fas fa-file-upload"></i>
                            File Upload <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control" id="file" name="file" required 
                               accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.gif">
                        <div class="form-text">
                            Allowed file types: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, JPG, PNG, GIF<br>
                            Maximum file size: 10MB
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="manage.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Resources
                        </a>
                        <button type="submit" class="btn-enhanced" id="submitBtn">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span class="btn-text">Upload Resource</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add jQuery first, then custom JavaScript -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
// Use vanilla JavaScript to ensure it works regardless of jQuery loading
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    const typeSelect = document.getElementById('type');
    const premiumField = document.querySelector('.premium-field');
    const amountInput = document.getElementById('amount');
    
    // Check if elements exist
    if (!typeSelect) {
        console.error('Type select not found');
        return;
    }
    
    console.log('Elements found:', {
        typeSelect: !!typeSelect,
        premiumField: !!premiumField,
        amountInput: !!amountInput
    });
    
    // Function to toggle premium field
    function togglePremiumField() {
        const selectedType = typeSelect.value;
        const isSystemAdmin = <?php echo ($_SESSION['role'] === 'System Admin') ? 'true' : 'false'; ?>;
        
        console.log('Toggle function called:', {
            selectedType: selectedType,
            isSystemAdmin: isSystemAdmin,
            premiumFieldExists: !!premiumField
        });
        
        if (selectedType === 'premium' && isSystemAdmin && premiumField) {
            premiumField.style.display = 'block';
            if (amountInput) {
                amountInput.required = true;
            }
            console.log('Premium field shown');
        } else if (premiumField) {
            premiumField.style.display = 'none';
            if (amountInput) {
                amountInput.required = false;
                amountInput.value = '';
            }
            console.log('Premium field hidden');
        }
    }
    
    // Add event listener for type change
    typeSelect.addEventListener('change', togglePremiumField);
    
    // Initialize on page load
    togglePremiumField();
    
    // File validation
    const fileInput = document.getElementById('file');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const fileSize = file.size;
                const maxSize = 10 * 1024 * 1024; // 10MB
                
                if (fileSize > maxSize) {
                    alert("File size too large. Maximum allowed size is 10MB.");
                    this.value = '';
                    return;
                }
                
                const allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
                const fileExtension = file.name.split('.').pop().toLowerCase();
                
                if (!allowedTypes.includes(fileExtension)) {
                    alert("Invalid file type. Please select a valid file.");
                    this.value = '';
                    return;
                }
            }
        });
    }
    
    // Form submission with loading state
    const resourceForm = document.getElementById('resourceForm');
    if (resourceForm) {
        resourceForm.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            
            if (submitBtn && btnText) {
                // Show loading state
                submitBtn.disabled = true;
                btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                
                // Restore button after 10 seconds if still processing
                setTimeout(function() {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        btnText.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Upload Resource';
                    }
                }, 10000);
            }
        });
    }
    
    // Form validation for title
    const titleInput = document.getElementById('title');
    if (titleInput) {
        titleInput.addEventListener('input', function() {
            const value = this.value.trim();
            if (value.length < 3) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    }
});

// Fallback jQuery version (will run after jQuery loads from layout.php)
$(document).ready(function() {
    console.log('jQuery ready function executed');
    
    // Only run jQuery version if vanilla JS version didn't work
    if (typeof window.premiumFieldInitialized === 'undefined') {
        console.log('Running jQuery fallback');
        
        // Toggle premium fields
        $("#type").on("change", function() {
            const selectedType = $(this).val();
            const isSystemAdmin = <?php echo ($_SESSION['role'] === 'System Admin') ? 'true' : 'false'; ?>;
            
            console.log('jQuery - Type changed:', selectedType, 'Admin:', isSystemAdmin);
            
            if (selectedType === "premium" && isSystemAdmin) {
                $(".premium-field").slideDown(300);
                $("#amount").prop("required", true);
                console.log('jQuery - Premium field shown');
            } else {
                $(".premium-field").slideUp(300);
                $("#amount").prop("required", false).val('');
                console.log('jQuery - Premium field hidden');
            }
        });
        
        // Initialize form state on page load
        const currentType = $("#type").val();
        const isSystemAdmin = <?php echo ($_SESSION['role'] === 'System Admin') ? 'true' : 'false'; ?>;
        
        if (currentType === "premium" && isSystemAdmin) {
            $(".premium-field").show();
            $("#amount").prop("required", true);
        }
        
        window.premiumFieldInitialized = true;
    }
});
</script>

<style>
.premium-field {
    transition: all 0.3s ease;
}

.form-control.is-valid {
    border-color: var(--success-color);
}

.form-control.is-invalid {
    border-color: var(--danger-color);
}

.btn-enhanced:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.card-title {
    color: var(--primary-color);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.form-label {
    font-weight: 500;
    color: var(--dark-color);
    margin-bottom: 0.5rem;
}

.form-label i {
    margin-right: 0.5rem;
    color: var(--primary-color);
}

.text-danger {
    color: var(--danger-color) !important;
}

/* Alert styles for success */
.alert-success {
    background: linear-gradient(135deg, var(--success-color), #059669);
    color: white;
    border: none;
}

.alert-warning {
    background: linear-gradient(135deg, var(--warning-color), #d97706);
    color: white;
    border: none;
}

/* Debug styles */
.debug-info {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px;
    margin: 10px 0;
    font-family: monospace;
    font-size: 12px;
}
</style>

<!-- Debug Information (remove this in production) -->
<?php if (isset($_GET['debug'])): ?>
<div class="debug-info">
    <strong>Debug Info:</strong><br>
    User Role: <?php echo $_SESSION['role']; ?><br>
    Is System Admin: <?php echo ($_SESSION['role'] === 'System Admin') ? 'YES' : 'NO'; ?><br>
    POST Data: <?php echo !empty($_POST) ? 'Present' : 'None'; ?><br>
    Type Value: <?php echo $_POST['type'] ?? 'Not set'; ?><br>
    Amount Value: <?php echo $_POST['amount'] ?? 'Not set'; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include '../layout.php';
?>