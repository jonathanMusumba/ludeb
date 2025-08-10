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
$errors = [];
$success = '';

// Fetch districts for dropdown
$districts = $conn->query("SELECT id, district_name FROM districts ORDER BY district_name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        error_log("CSRF token validation failed: Received " . ($_POST['csrf_token'] ?? 'none') . ", Expected $csrf_token", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
        $errors[] = "Invalid CSRF token.";
    } else {
        $district_id = intval($_POST['district_id'] ?? 0);
        $subcounty = trim($_POST['subcounty'] ?? '');
        $constituency = trim($_POST['constituency'] ?? '');

        // Validation
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
        $stmt = $conn->prepare("SELECT COUNT(*) FROM subcounties WHERE subcounty = ? AND district_id = ?");
        $stmt->bind_param("si", $subcounty, $district_id);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()[0] > 0) {
            $errors[] = "Subcounty name already exists in this district.";
        }
        $stmt->close();

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO subcounties (district_id, subcounty, constituency) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $district_id, $subcounty, $constituency);
            if ($stmt->execute()) {
                $success = "Subcounty added successfully.";
                $conn->query("CALL log_action('Add Subcounty', $user_id, 'Added subcounty: $subcounty in district ID: $district_id')");
            } else {
                error_log("Failed to add subcounty: $subcounty, District ID: $district_id", 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                $errors[] = "Failed to add subcounty. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');

// Set page title
$page_title = "Add Subcounty";

// Define content
ob_start();
?>
<div class="page-header">
    <h1 class="page-title">Add Subcounty</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>Dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>subcounties/manage_subcounties.php">Manage Subcounties</a></li>
            <li class="breadcrumb-item active" aria-current="page">Add Subcounty</li>
        </ol>
    </nav>
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

        <form method="POST" id="addSubcountyForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="form-group">
                <label for="district_id">District</label>
                <select class="form-control" id="district_id" name="district_id" required>
                    <option value="">Select District</option>
                    <?php foreach ($districts as $district): ?>
                        <option value="<?php echo $district['id']; ?>" <?php echo (isset($_POST['district_id']) && $_POST['district_id'] == $district['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($district['district_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="subcounty">Subcounty Name</label>
                <input type="text" class="form-control" id="subcounty" name="subcounty" value="<?php echo isset($_POST['subcounty']) ? htmlspecialchars($_POST['subcounty']) : ''; ?>" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="constituency">Constituency</label>
                <input type="text" class="form-control" id="constituency" name="constituency" value="<?php echo isset($_POST['constituency']) ? htmlspecialchars($_POST['constituency']) : ''; ?>" required maxlength="255">
            </div>
            <button type="submit" class="btn btn-enhanced">Add Subcounty</button>
            <a href="<?php echo $base_url; ?>subcounties/manage_subcounties.php" class="btn btn-enhanced btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<script>
jQuery.noConflict();
(function($) {
    $(document).ready(function() {
        console.log('Add Subcounty page loaded with jQuery version:', $.fn.jquery);

        // Client-side form validation
        $('#addSubcountyForm').on('submit', function(e) {
            const districtId = $('#district_id').val();
            const subcounty = $('#subcounty').val().trim();
            const constituency = $('#constituency').val().trim();

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
            const $submitBtn = $(this).find('button[type="submit"]');
            $submitBtn.prop('disabled', true).text('Adding...');
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