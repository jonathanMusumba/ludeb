<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection (you'll need to include your database config)
require_once '../connections/db_connect.php';

$board_name = "Luuka Examination Board";
$current_year = date('Y');

// Handle form submission
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $school_name = trim($_POST['school_name'] ?? '');
    $class_level = $_POST['class_level'] ?? '';
    $subject_specialization = $_POST['subject_specialization'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $payment_reference = trim($_POST['payment_reference'] ?? '');
    
    // Validation
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    if (empty($role)) $errors[] = "Please select a role";
    
    // Role-specific validation
    if ($role === 'student' && empty($class_level)) $errors[] = "Class level is required for students";
    if ($role === 'teacher' && empty($subject_specialization)) $errors[] = "Subject specialization is required for teachers";
    if (!empty($payment_reference) && empty($payment_method)) $errors[] = "Payment method is required when providing payment reference";
    
    // Check if email already exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT email FROM public_users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email already registered. Please use a different email or login.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error. Please try again later.";
        }
    }
    
    // Insert new user if no errors
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $registration_date = date('Y-m-d H:i:s');
            
            // Determine access level based on payment
            $access_level = 'free'; // Default to free access
            $payment_status = 'pending';
            
            if (!empty($payment_reference)) {
                $access_level = 'premium_pending'; // Premium access pending verification
                $payment_status = 'pending_verification';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO public_users (
                    first_name, last_name, email, phone, password, role, 
                    school_name, class_level, subject_specialization, 
                    access_level, payment_status, payment_method, payment_reference,
                    registration_date, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            
            $stmt->execute([
                $first_name, $last_name, $email, $phone, $hashed_password, $role,
                $school_name, $class_level, $subject_specialization,
                $access_level, $payment_status, $payment_method, $payment_reference,
                $registration_date
            ]);
            
            $success_message = "Registration successful! Please check your email for verification instructions.";
            
            // Send welcome email (implement email function)
            // sendWelcomeEmail($email, $first_name, $access_level);
            
        } catch (PDOException $e) {
            $errors[] = "Registration failed. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo htmlspecialchars($board_name); ?></title>
    <meta name="description" content="Register for access to premium educational resources and study materials">
    
    <!-- External Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-gold: #ffd700;
            --secondary-gold: #ffc107;
            --accent-blue: #0066cc;
            --success-green: #28a745;
            --danger-red: #dc3545;
            --dark-blue: #1e3c72;
            --light-blue: #2a5298;
            --gradient-primary: linear-gradient(135deg, var(--primary-gold) 0%, var(--secondary-gold) 100%);
            --gradient-blue: linear-gradient(135deg, var(--dark-blue) 0%, var(--light-blue) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gradient-blue);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 800px;
            margin: 0 auto;
        }

        .register-header {
            background: var(--gradient-primary);
            padding: 2rem;
            text-align: center;
            color: #000;
        }

        .register-header h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .register-form {
            padding: 2.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.8rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fff;
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
            outline: none;
        }

        .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.8rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fff;
        }

        .form-select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
            outline: none;
        }

        .btn-register {
            background: var(--gradient-blue);
            border: none;
            color: #fff;
            padding: 1rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3);
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 60, 114, 0.4);
            color: #fff;
        }

        .back-link {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            text-decoration: none;
            padding: 0.8rem 1.2rem;
            border-radius: 25px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .back-link:hover {
            background: var(--primary-gold);
            color: #000;
            transform: translateX(-3px);
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: #fff;
        }

        .alert-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: #fff;
        }

        .role-dependent {
            display: none;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .role-dependent.show {
            display: block;
            opacity: 1;
        }

        .payment-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .payment-methods {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .payment-method {
            background: #fff;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 120px;
        }

        .payment-method:hover {
            border-color: var(--accent-blue);
            background: rgba(0, 102, 204, 0.05);
        }

        .payment-method.selected {
            border-color: var(--accent-blue);
            background: rgba(0, 102, 204, 0.1);
        }

        .password-strength {
            margin-top: 0.5rem;
        }

        .strength-bar {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            width: 0%;
        }

        .strength-weak { background: #dc3545; width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: #28a745; width: 100%; }

        @media (max-width: 768px) {
            .register-container {
                margin: 1rem;
                border-radius: 15px;
            }
            
            .register-form {
                padding: 2rem 1.5rem;
            }
            
            .back-link {
                top: 10px;
                left: 10px;
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
            
            .payment-methods {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Back Link -->
    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Back to Resources
    </a>

    <div class="container">
        <div class="register-container">
            <!-- Header -->
            <div class="register-header">
                <i class="fas fa-user-plus fa-3x mb-3"></i>
                <h2>Create Your Account</h2>
                <p class="mb-0">Join our educational community and access premium resources</p>
            </div>

            <!-- Registration Form -->
            <div class="register-form">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <hr class="my-3">
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="login.php" class="btn btn-light btn-sm">
                                <i class="fas fa-sign-in-alt me-1"></i>
                                Login Now
                            </a>
                            <a href="index.php" class="btn btn-light btn-sm">
                                <i class="fas fa-home me-1"></i>
                                Back to Resources
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($success_message)): ?>
                <form method="POST" action="" id="registerForm">
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="first_name" class="form-label">
                                    <i class="fas fa-user me-1"></i>
                                    First Name *
                                </label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="last_name" class="form-label">
                                    <i class="fas fa-user me-1"></i>
                                    Last Name *
                                </label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-1"></i>
                                    Email Address *
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone" class="form-label">
                                    <i class="fas fa-phone me-1"></i>
                                    Phone Number *
                                </label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       placeholder="+256 777 123 456"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Role Selection -->
                    <div class="form-group">
                        <label for="role" class="form-label">
                            <i class="fas fa-users me-1"></i>
                            I am a *
                        </label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select your role</option>
                            <option value="student" <?php echo ($_POST['role'] ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="parent" <?php echo ($_POST['role'] ?? '') === 'parent' ? 'selected' : ''; ?>>Parent/Guardian</option>
                            <option value="teacher" <?php echo ($_POST['role'] ?? '') === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="individual" <?php echo ($_POST['role'] ?? '') === 'individual' ? 'selected' : ''; ?>>Individual Learner</option>
                        </select>
                    </div>

                    <!-- School Information -->
                    <div class="form-group">
                        <label for="school_name" class="form-label">
                            <i class="fas fa-school me-1"></i>
                            School/Institution Name
                        </label>
                        <input type="text" class="form-control" id="school_name" name="school_name" 
                               placeholder="Enter your school or institution name"
                               value="<?php echo htmlspecialchars($_POST['school_name'] ?? ''); ?>">
                    </div>

                    <!-- Role-specific fields -->
                    <div id="student-fields" class="role-dependent">
                        <div class="form-group">
                            <label for="class_level" class="form-label">
                                <i class="fas fa-graduation-cap me-1"></i>
                                Current Class/Level *
                            </label>
                            <select class="form-select" id="class_level" name="class_level">
                                <option value="">Select your class</option>
                                <optgroup label="Primary">
                                    <option value="P1" <?php echo ($_POST['class_level'] ?? '') === 'P1' ? 'selected' : ''; ?>>Primary 1</option>
                                    <option value="P2" <?php echo ($_POST['class_level'] ?? '') === 'P2' ? 'selected' : ''; ?>>Primary 2</option>
                                    <option value="P3" <?php echo ($_POST['class_level'] ?? '') === 'P3' ? 'selected' : ''; ?>>Primary 3</option>
                                    <option value="P4" <?php echo ($_POST['class_level'] ?? '') === 'P4' ? 'selected' : ''; ?>>Primary 4</option>
                                    <option value="P5" <?php echo ($_POST['class_level'] ?? '') === 'P5' ? 'selected' : ''; ?>>Primary 5</option>
                                    <option value="P6" <?php echo ($_POST['class_level'] ?? '') === 'P6' ? 'selected' : ''; ?>>Primary 6</option>
                                    <option value="P7" <?php echo ($_POST['class_level'] ?? '') === 'P7' ? 'selected' : ''; ?>>Primary 7</option>
                                </optgroup>
                                <optgroup label="Secondary">
                                    <option value="S1" <?php echo ($_POST['class_level'] ?? '') === 'S1' ? 'selected' : ''; ?>>Senior 1</option>
                                    <option value="S2" <?php echo ($_POST['class_level'] ?? '') === 'S2' ? 'selected' : ''; ?>>Senior 2</option>
                                    <option value="S3" <?php echo ($_POST['class_level'] ?? '') === 'S3' ? 'selected' : ''; ?>>Senior 3</option>
                                    <option value="S4" <?php echo ($_POST['class_level'] ?? '') === 'S4' ? 'selected' : ''; ?>>Senior 4</option>
                                    <option value="S5" <?php echo ($_POST['class_level'] ?? '') === 'S5' ? 'selected' : ''; ?>>Senior 5</option>
                                    <option value="S6" <?php echo ($_POST['class_level'] ?? '') === 'S6' ? 'selected' : ''; ?>>Senior 6</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <div id="teacher-fields" class="role-dependent">
                        <div class="form-group">
                            <label for="subject_specialization" class="form-label">
                                <i class="fas fa-chalkboard-teacher me-1"></i>
                                Subject Specialization *
                            </label>
                            <input type="text" class="form-control" id="subject_specialization" name="subject_specialization" 
                                   placeholder="e.g., Mathematics, English, Science"
                                   value="<?php echo htmlspecialchars($_POST['subject_specialization'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Password Fields -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>
                                    Password *
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="password-strength">
                                    <div class="strength-bar">
                                        <div class="strength-fill" id="strengthFill"></div>
                                    </div>
                                    <small class="text-muted" id="strengthText">Minimum 6 characters</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>
                                    Confirm Password *
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <div id="passwordMatch" class="mt-1"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Premium Access Section -->
                    <div class="payment-info">
                        <h5 class="mb-3">
                            <i class="fas fa-crown me-2" style="color: var(--primary-gold);"></i>
                            Premium Access (Optional)
                        </h5>
                        <p class="mb-3">
                            <strong>Free Account:</strong> Access to basic resources and limited past papers<br>
                            <strong>Premium Account:</strong> Full access to all resources, past papers, video lessons, and practice tests
                        </p>
                        
                        <div class="form-group">
                            <label class="form-label">Payment Method (for Premium Access)</label>
                            <div class="payment-methods">
                                <div class="payment-method" data-method="mobile_money">
                                    <i class="fas fa-mobile-alt fa-2x mb-2" style="color: var(--accent-blue);"></i>
                                    <div>Mobile Money</div>
                                    <small>MTN/Airtel</small>
                                </div>
                                <div class="payment-method" data-method="bank_transfer">
                                    <i class="fas fa-university fa-2x mb-2" style="color: var(--accent-blue);"></i>
                                    <div>Bank Transfer</div>
                                    <small>All Banks</small>
                                </div>
                                <div class="payment-method" data-method="cash">
                                    <i class="fas fa-money-bill fa-2x mb-2" style="color: var(--accent-blue);"></i>
                                    <div>Cash Payment</div>
                                    <small>Office Visit</small>
                                </div>
                            </div>
                            <input type="hidden" id="payment_method" name="payment_method" value="<?php echo htmlspecialchars($_POST['payment_method'] ?? ''); ?>">
                        </div>

                        <div class="form-group" id="paymentDetails" style="display: none;">
                            <label for="payment_reference" class="form-label">
                                <i class="fas fa-receipt me-1"></i>
                                Payment Reference/Transaction ID
                            </label>
                            <input type="text" class="form-control" id="payment_reference" name="payment_reference" 
                                   placeholder="Enter transaction ID or reference number"
                                   value="<?php echo htmlspecialchars($_POST['payment_reference'] ?? ''); ?>">
                            <small class="text-muted">
                                Premium access will be activated after payment verification by admin
                            </small>
                        </div>

                        <div id="paymentInstructions" class="mt-3" style="display: none;">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-1"></i> Payment Instructions</h6>
                                <div id="instructionContent"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and 
                                <a href="privacy.php" target="_blank">Privacy Policy</a> *
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-register">
                        <i class="fas fa-user-plus me-2"></i>
                        Create Account
                    </button>

                    <div class="text-center mt-3">
                        <p class="mb-0">
                            Already have an account? 
                            <a href="login.php" style="color: var(--accent-blue); font-weight: 600;">
                                Login here
                            </a>
                        </p>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Role-dependent field visibility
        document.getElementById('role').addEventListener('change', function() {
            const role = this.value;
            const studentFields = document.getElementById('student-fields');
            const teacherFields = document.getElementById('teacher-fields');
            
            // Hide all role-dependent fields
            document.querySelectorAll('.role-dependent').forEach(field => {
                field.classList.remove('show');
            });
            
            // Show relevant fields
            if (role === 'student') {
                studentFields.classList.add('show');
            } else if (role === 'teacher') {
                teacherFields.classList.add('show');
            }
        });

        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                // Remove selected class from all methods
                document.querySelectorAll('.payment-method').forEach(m => {
                    m.classList.remove('selected');
                });
                
                // Add selected class to clicked method
                this.classList.add('selected');
                
                // Set hidden input value
                const methodValue = this.dataset.method;
                document.getElementById('payment_method').value = methodValue;
                
                // Show payment details field
                document.getElementById('paymentDetails').style.display = 'block';
                document.getElementById('paymentInstructions').style.display = 'block';
                
                // Update payment instructions
                const instructionContent = document.getElementById('instructionContent');
                switch(methodValue) {
                    case 'mobile_money':
                        instructionContent.innerHTML = `
                            <strong>Mobile Money Payment:</strong><br>
                            1. Dial *165# (MTN) or *185# (Airtel)<br>
                            2. Send UGX 50,000 to: <strong>+256 777 115 678</strong><br>
                            3. Copy the transaction ID and paste it above<br>
                            4. Premium access will be activated within 24 hours
                        `;
                        break;
                    case 'bank_transfer':
                        instructionContent.innerHTML = `
                            <strong>Bank Transfer:</strong><br>
                            Account Name: ILABS UGANDA LIMITED<br>
                            Account Number: [Bank Account Number]<br>
                            Bank: [Bank Name]<br>
                            Amount: UGX 50,000<br>
                            Reference: Your Email Address
                        `;
                        break;
                    case 'cash':
                        instructionContent.innerHTML = `
                            <strong>Cash Payment:</strong><br>
                            Visit our office in Kampala<br>
                            Amount: UGX 50,000<br>
                            Contact: +256 777 115 678 to schedule appointment<br>
                            Office Hours: Mon-Fri 8:00 AM - 6:00 PM
                        `;
                        break;
                }
            });
        });

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            let text = '';
            
            if (password.length >= 6) strength += 1;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
            if (password.match(/[0-9]/)) strength += 1;
            if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
            
            strengthFill.className = 'strength-fill';
            
            switch(strength) {
                case 0:
                case 1:
                    strengthFill.classList.add('strength-weak');
                    text = 'Weak password';
                    break;
                case 2:
                case 3:
                    strengthFill.classList.add('strength-medium');
                    text = 'Medium strength';
                    break;
                case 4:
                    strengthFill.classList.add('strength-strong');
                    text = 'Strong password';
                    break;
            }
            
            strengthText.textContent = text;
        });

        // Password confirmation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword === '') {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.innerHTML = '<small class="text-success"><i class="fas fa-check me-1"></i>Passwords match</small>';
            } else {
                matchDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times me-1"></i>Passwords do not match</small>';
            }
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const role = document.getElementById('role').value;
            const classLevel = document.getElementById('class_level').value;
            const subjectSpecialization = document.getElementById('subject_specialization').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            let hasErrors = false;
            
            // Role-specific validation
            if (role === 'student' && !classLevel) {
                alert('Please select your class level');
                hasErrors = true;
            }
            
            if (role === 'teacher' && !subjectSpecialization) {
                alert('Please enter your subject specialization');
                hasErrors = true;
            }
            
            // Password validation
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                hasErrors = true;
            }
            
            if (hasErrors) {
                e.preventDefault();
            }
        });

        // Initialize role-dependent fields on page load
        document.addEventListener('DOMContentLoaded', function() {
            const role = document.getElementById('role').value;
            if (role) {
                document.getElementById('role').dispatchEvent(new Event('change'));
            }
            
            const paymentMethod = document.getElementById('payment_method').value;
            if (paymentMethod) {
                document.querySelector(`[data-method="${paymentMethod}"]`)?.click();
            }
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            
            if (value.startsWith('256')) {
                value = '+' + value;
            } else if (value.startsWith('0')) {
                value = '+256' + value.substring(1);
            } else if (!value.startsWith('+256') && value.length > 0) {
                value = '+256' + value;
            }
            
            this.value = value;
        });

        // Email validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#dc3545';
                this.insertAdjacentHTML('afterend', '<small class="text-danger">Please enter a valid email address</small>');
            } else {
                this.style.borderColor = '';
                const errorMsg = this.parentNode.querySelector('.text-danger');
                if (errorMsg) errorMsg.remove();
            }
        });

        // Add loading animation to submit button
        document.getElementById('registerForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalContent = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
            submitBtn.disabled = true;
            
            // Re-enable after timeout (fallback)
            setTimeout(() => {
                submitBtn.innerHTML = originalContent;
                submitBtn.disabled = false;
            }, 10000);
        });
    </script>
</body>
</html>