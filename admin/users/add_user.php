<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../db_connect.php';

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Debug session
error_log("=== DEBUG add_user.php ===", 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("Session ID: " . session_id(), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("Session started: " . (session_status() === PHP_SESSION_ACTIVE ? 'YES' : 'NO'), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("Role: " . ($_SESSION['role'] ?? 'NOT SET'), 3, 'C:\xampp\htdocs\ludeb\debug.log');
error_log("=== END DEBUG ===", 3, 'C:\xampp\htdocs\ludeb\debug.log');

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header("Location: " . $root_url . "login.php");
    exit();
}

// Check connection
if (!$conn || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn ? $conn->connect_error : 'No connection object'), 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Generate CSRF token if not set (handled in layout.php, but ensure consistency)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Fetch schools for dropdown
$stmt = $conn->prepare("SELECT id, school_name AS name FROM schools WHERE NOT EXISTS (SELECT 1 FROM system_users WHERE school_id = schools.id AND role = 'School') ORDER BY name");
if ($stmt) {
    $stmt->execute();
    $schools = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    error_log("Failed to fetch schools: " . $conn->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
    $schools = [];
}

// Log page access
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("CALL log_action(?, ?, ?)");
if ($stmt) {
    $action = 'Add User Access';
    $details = 'System Admin accessed add user page';
    $stmt->bind_param("sis", $action, $user_id, $details);
    $stmt->execute();
    $stmt->close();
} else {
    error_log("Failed to prepare log_action query: " . $conn->error, 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
}

// Set page title
$page_title = 'Add User';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }

    // Handle username/email validation
    if (isset($_POST['validate']) && $_POST['validate'] === 'username_email') {
        $response = [];
        
        if (isset($_POST['username'])) {
            $username = trim($_POST['username']);
            $stmt = $conn->prepare("SELECT id FROM system_users WHERE username = ?");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $response['username'] = 'Username already exists';
                }
                $stmt->close();
            }
        }
        
        if (isset($_POST['email'])) {
            $email = trim($_POST['email']);
            $stmt = $conn->prepare("SELECT id FROM system_users WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $response['email'] = 'Email already exists';
                }
                $stmt->close();
            }
        }
        
        echo json_encode($response);
        exit();
    }

    // Handle school_id validation
    if (isset($_POST['validate']) && $_POST['validate'] === 'school_id') {
        $response = [];
        $school_id = (int)$_POST['school_id'];
        $edit_id = (int)($_POST['edit_id'] ?? 0);
        
        $stmt = $conn->prepare("SELECT id FROM system_users WHERE school_id = ? AND role = 'School' AND id != ?");
        if ($stmt) {
            $stmt->bind_param("ii", $school_id, $edit_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $response['school_id'] = 'This school already has a user account';
            }
            $stmt->close();
        }
        
        echo json_encode($response);
        exit();
    }

    // Handle creating users for all schools
    if (isset($_POST['create_school_users'])) {
        try {
            $conn->autocommit(false);
            
            $stmt = $conn->prepare("
                SELECT s.id, s.school_name, s.center_no 
                FROM schools s 
                WHERE s.status = 'Active' 
                AND NOT EXISTS (
                    SELECT 1 FROM system_users u 
                    WHERE u.school_id = s.id AND u.role = 'School'
                )
                ORDER BY s.school_name
            ");
            
            if (!$stmt) {
                throw new Exception("Failed to prepare schools query: " . $conn->error);
            }
            
            $stmt->execute();
            $schools_without_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            if (empty($schools_without_users)) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'All active schools already have user accounts']);
                exit();
            }
            
            $created_users = [];
            $errors = [];
            
            foreach ($schools_without_users as $school) {
                if (empty($school['center_no'])) {
                    $errors[] = "School '{$school['school_name']}' has no center number";
                    continue;
                }
                
                $username = trim($school['center_no']);
                $email = strtolower($username) . '@school.edu';
                $email_counter = 1;
                $original_email = $email;
                
                while (true) {
                    $check_stmt = $conn->prepare("SELECT id FROM system_users WHERE email = ?");
                    if ($check_stmt) {
                        $check_stmt->bind_param("s", $email);
                        $check_stmt->execute();
                        if ($check_stmt->get_result()->num_rows === 0) {
                            $check_stmt->close();
                            break;
                        }
                        $check_stmt->close();
                        $email = str_replace('@school.edu', $email_counter . '@school.edu', $original_email);
                        $email_counter++;
                    }
                }
                
                $plain_password = $username;
                $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
                
                $insert_stmt = $conn->prepare("
                    INSERT INTO system_users (username, email, password, role, school_id, status, created_at) 
                    VALUES (?, ?, ?, 'School', ?, 'Active', NOW())
                ");
                
                if (!$insert_stmt) {
                    $errors[] = "Failed to prepare insert for {$school['school_name']}: " . $conn->error;
                    continue;
                }
                
                $insert_stmt->bind_param("sssi", $username, $email, $hashed_password, $school['id']);
                
                if ($insert_stmt->execute()) {
                    $created_users[] = [
                        'school_name' => $school['school_name'],
                        'center_no' => $school['center_no'],
                        'username' => $username,
                        'email' => $email,
                        'password' => $plain_password
                    ];
                    
                    $log_stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, details, created_at) VALUES (?, ?, ?, NOW())");
                    if ($log_stmt) {
                        $action = 'User Created';
                        $details = "Created school user for {$school['school_name']} (Center: {$username})";
                        $log_stmt->bind_param("sis", $action, $user_id, $details);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                } else {
                    $errors[] = "Failed to create user for {$school['school_name']}: " . $insert_stmt->error;
                }
                
                $insert_stmt->close();
            }
            
            if (!empty($created_users)) {
                $conn->commit();
                
                $success_message = count($created_users) . " school user(s) created successfully!<br><br>";
                $success_message .= "<strong>Login Credentials (Please save this information):</strong><br>";
                $success_message .= "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace;'>";
                
                foreach ($created_users as $user) {
                    $success_message .= "<strong>{$user['school_name']} (Center: {$user['center_no']}):</strong><br>";
                    $success_message .= "Username: <strong>{$user['username']}</strong><br>";
                    $success_message .= "Password: <strong>{$user['password']}</strong><br>";
                    $success_message .= "Email: {$user['email']}<br><br>";
                }
                
                $success_message .= "</div><br>";
                $success_message .= "<strong>Note:</strong> Passwords are securely hashed in the database. ";
                $success_message .= "Schools should use their center number as both username and password to login.";
                
                if (!empty($errors)) {
                    $success_message .= "<br><br><strong>Warnings/Errors:</strong><br>" . implode('<br>', $errors);
                }
                
                echo json_encode(['success' => true, 'message' => $success_message]);
            } else {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'No users were created. Errors: ' . implode(' | ', $errors)]);
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error creating school users: " . $e->getMessage(), 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
            echo json_encode(['success' => false, 'message' => 'Failed to create school users: ' . $e->getMessage()]);
        } finally {
            $conn->autocommit(true);
        }
        exit();
    }

    // Handle regular user creation
    if (isset($_POST['username']) && is_array($_POST['username'])) {
        try {
            $conn->autocommit(false);
            
            $usernames = $_POST['username'];
            $emails = $_POST['email'];
            $passwords = $_POST['password'];
            $roles = $_POST['role'];
            $school_ids = $_POST['school_id'] ?? [];
            $statuses = $_POST['status'];
            
            $created_users = [];
            $errors = [];
            
            for ($i = 0; $i < count($usernames); $i++) {
                $username = trim($usernames[$i]);
                $email = trim($emails[$i]);
                $password = $passwords[$i];
                $role = $roles[$i];
                $school_id = ($roles[$i] === 'School' && !empty($school_ids[$i])) ? (int)$school_ids[$i] : null;
                $status = $statuses[$i];
                
                if (empty($username) || empty($email) || empty($password) || empty($role) || empty($status)) {
                    $errors[] = "All fields are required for user " . ($i + 1);
                    continue;
                }
                
                if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
                    $errors[] = "Password for user " . ($i + 1) . " does not meet requirements (8+ chars, letter, number, special char)";
                    continue;
                }
                
                $stmt = $conn->prepare("SELECT id FROM system_users WHERE username = ?");
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $errors[] = "Username '$username' already exists";
                        $stmt->close();
                        continue;
                    }
                    $stmt->close();
                }
                
                $stmt = $conn->prepare("SELECT id FROM system_users WHERE email = ?");
                if ($stmt) {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $errors[] = "Email '$email' already exists";
                        $stmt->close();
                        continue;
                    }
                    $stmt->close();
                }
                
                // Validate school_id only for School role, enforce NULL for others
                if ($role !== 'School' && !empty($school_ids[$i])) {
                    error_log("Invalid school_id provided for non-School role '$role' for user " . ($i + 1), 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
                    $errors[] = "School ID should not be set for role '$role' for user " . ($i + 1);
                    continue;
                }
                
                if ($role === 'School') {
                    if (!$school_id) {
                        $errors[] = "School selection is required for School role user " . ($i + 1);
                        continue;
                    }
                    
                    $stmt = $conn->prepare("SELECT id FROM system_users WHERE school_id = ? AND role = 'School'");
                    if ($stmt) {
                        $stmt->bind_param("i", $school_id);
                        $stmt->execute();
                        if ($stmt->get_result()->num_rows > 0) {
                            $errors[] = "Selected school already has a user account for user " . ($i + 1);
                            $stmt->close();
                            continue;
                        }
                        $stmt->close();
                    }
                    
                    $stmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ? AND status = 'Active'");
                    if ($stmt) {
                        $stmt->bind_param("i", $school_id);
                        $stmt->execute();
                        if ($stmt->get_result()->num_rows === 0) {
                            $errors[] = "Selected school does not exist or is not active for user " . ($i + 1);
                            $stmt->close();
                            continue;
                        }
                        $stmt->close();
                    }
                }
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $insert_stmt = $conn->prepare("
                    INSERT INTO system_users (username, email, password, role, school_id, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                if (!$insert_stmt) {
                    $errors[] = "Failed to prepare insert for user " . ($i + 1) . ": " . $conn->error;
                    continue;
                }
                
                $insert_stmt->bind_param("ssssis", $username, $email, $hashed_password, $role, $school_id, $status);
                
                if ($insert_stmt->execute()) {
                    $created_users[] = $username;
                    
                    $log_stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, details, created_at) VALUES (?, ?, ?, NOW())");
                    if ($log_stmt) {
                        $action = 'User Created';
                        $school_info = $school_id ? " for school ID: $school_id" : "";
                        $details = "Created user: $username with role: $role$school_info";
                        $log_stmt->bind_param("sis", $action, $user_id, $details);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                } else {
                    $errors[] = "Failed to create user '$username': " . $insert_stmt->error;
                }
                
                $insert_stmt->close();
            }
            
            if (!empty($created_users)) {
                $conn->commit();
                $success_message = count($created_users) . " user(s) created successfully: " . implode(', ', $created_users);
                if (!empty($errors)) {
                    $success_message .= "<br><br><strong>Errors/Warnings:</strong><br>" . implode('<br>', $errors);
                }
                echo json_encode(['success' => true, 'message' => $success_message]);
            } else {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => implode(' | ', $errors)]);
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error creating users: " . $e->getMessage(), 3, 'C:\xampp\htdocs\ludeb\logs\setup_errors.log');
            echo json_encode(['success' => false, 'message' => 'Failed to create users: ' . $e->getMessage()]);
        } finally {
            $conn->autocommit(true);
        }
        exit();
    }
}

// Define content
ob_start();
?>
<div class="page-header">
    <h1 class="page-title">Add User</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item">Users</li>
            <li class="breadcrumb-item active" aria-current="page">Add User</li>
        </ol>
    </nav>
</div>

<div class="dashboard-card">
    <div class="card-body">
        <h2 class="card-title">Add User</h2>
        <form id="add-user-form" method="post" class="mb-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div id="user-fields" class="user-fields">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" name="username[]" required onblur="validateField(this, 'username')">
                        <small class="validation-message"></small>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" name="email[]" required onblur="validateField(this, 'email')">
                        <small class="validation-message"></small>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="password">Password</label>
                        <input type="password" class="form-control" name="password[]" required onkeyup="validatePassword(this)">
                        <small class="password-requirements">Must be 8+ chars, include a letter, number, and special char.</small>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="role">Role</label>
                        <select name="role[]" class="form-control" required onchange="toggleSchoolField(this)">
                            <option value="System Admin">System Admin</option>
                            <option value="Examination Administrator">Examination Administrator</option>
                            <option value="Data Entrant" selected>Data Entrant</option>
                            <option value="School">School</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2 school-field" style="display: none;">
                        <label for="school_id">School</label>
                        <select name="school_id[]" class="form-control" onblur="validateSchoolField(this, 0)">
                            <option value="">Select School</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="validation-message"></small>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="status">Status</label>
                        <select name="status[]" class="form-control" required>
                            <option value="Active" selected>Active</option>
                            <option value="Invalid">Invalid</option>
                        </select>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-enhanced btn-secondary mb-3" id="add-user-button">Add Another User</button>
            <button type="submit" class="btn btn-enhanced btn-primary">Add User(s)</button>
        </form>

        <form id="create-school-users-form" method="post" class="mb-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="create_school_users" value="1">
            <button type="submit" class="btn btn-enhanced btn-primary">Create Users for All Schools</button>
        </form>

        <div id="alert-container" class="alert-container"></div>
    </div>
</div>

<script>
function toggleSchoolField(select) {
    const schoolField = $(select).closest('.form-row').find('.school-field');
    schoolField.toggle(select.value === 'School');
    if (select.value !== 'School') {
        schoolField.find('select').val('');
        schoolField.find('.validation-message').hide();
    }
}

function validatePassword(input) {
    const requirements = input.nextElementSibling;
    const regex = /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
    if (regex.test(input.value)) {
        requirements.classList.remove('invalid');
        requirements.classList.add('valid');
        requirements.textContent = 'Password meets requirements.';
    } else {
        requirements.classList.remove('valid');
        requirements.classList.add('invalid');
        requirements.textContent = 'Must be 8+ chars, include a letter, number, and special char.';
    }
    requirements.style.display = 'block';
}

function validateField(input, type) {
    const message = input.nextElementSibling;
    if (!input.value) return;

    $.ajax({
        url: 'add_user.php',
        type: 'POST',
        data: { validate: 'username_email', [type]: input.value, csrf_token: '<?php echo $csrf_token; ?>' },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.username || result.email) {
                message.classList.remove('valid');
                message.classList.add('invalid');
                message.textContent = result.username || result.email;
            } else {
                message.classList.remove('invalid');
                message.classList.add('valid');
                message.textContent = type.charAt(0).toUpperCase() + type.slice(1) + ' is available.';
            }
            message.style.display = 'block';
        },
        error: function() {
            message.classList.add('invalid');
            message.textContent = 'Error validating ' + type;
            message.style.display = 'block';
        }
    });
}

function validateSchoolField(select, editId) {
    const message = select.nextElementSibling;
    if (!select.value) return;

    $.ajax({
        url: 'add_user.php',
        type: 'POST',
        data: { validate: 'school_id', school_id: select.value, edit_id: editId, csrf_token: '<?php echo $csrf_token; ?>' },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.school_id) {
                message.classList.remove('valid');
                message.classList.add('invalid');
                message.textContent = result.school_id;
            } else {
                message.classList.remove('invalid');
                message.classList.add('valid');
                message.textContent = 'School is available.';
            }
            message.style.display = 'block';
        },
        error: function() {
            message.classList.add('invalid');
            message.textContent = 'Error validating school';
            message.style.display = 'block';
        }
    });
}

document.getElementById('add-user-button').addEventListener('click', function() {
    const userFields = document.getElementById('user-fields');
    const newFields = userFields.firstElementChild.cloneNode(true);
    newFields.querySelectorAll('input').forEach(input => input.value = '');
    newFields.querySelector('select[name="role[]"]').value = 'Data Entrant';
    newFields.querySelector('select[name="status[]"]').value = 'Active';
    newFields.querySelector('.school-field').style.display = 'none';
    newFields.querySelector('select[name="school_id[]"]').value = '';
    newFields.querySelectorAll('.password-requirements, .validation-message').forEach(el => {
        el.style.display = 'none';
        el.textContent = '';
    });
    userFields.appendChild(newFields);
});

$('#add-user-form').submit(function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    $.ajax({
        url: 'add_user.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                showNotification(result.message, 'success');
                $('#add-user-form')[0].reset();
                $('#user-fields').html($('#user-fields .form-row').first().clone());
                $('.password-requirements, .validation-message').hide();
                $('.school-field').hide();
            } else {
                showNotification(result.message, 'error');
            }
        },
        error: function(xhr) {
            showNotification('An unexpected error occurred', 'error');
        }
    });
});

$('#create-school-users-form').submit(function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    $.ajax({
        url: 'add_user.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                showNotification(result.message, 'success');
            } else {
                showNotification(result.message, 'error');
            }
        },
        error: function(xhr) {
            showNotification('An unexpected error occurred', 'error');
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../layout.php';
?>