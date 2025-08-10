<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/// Fetch the current exam year (largest year in the table)
$current_exam_year_result = $conn->query("SELECT MAX(Exam_year) as current_exam_year FROM exam_years");
$current_exam_year = $current_exam_year_result->fetch_assoc()['current_exam_year'];

// Check if a current year was fetched
if ($current_exam_year === null) {
    echo "No exam year found.";
}

// Fetch previous exam years (all years smaller than the current year)
$previous_exam_years_result = $conn->query("SELECT Exam_year FROM exam_years WHERE Exam_year < $current_exam_year ORDER BY Exam_year DESC");

// Check if any previous years were fetched
$previous_exam_years = [];
while ($row = $previous_exam_years_result->fetch_assoc()) {
    $previous_exam_years[] = $row['Exam_year'];
}

if (empty($previous_exam_years)) {
    echo "No previous exam years found.";
}



if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_user'])) {
    $usernames = $_POST['username'];
    $emails = $_POST['email'];
    $passwords = $_POST['password'];
    $roles = $_POST['role'];

    for ($i = 0; $i < count($usernames); $i++) {
        $hashedPassword = password_hash($passwords[$i], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO system_users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $usernames[$i], $emails[$i], $hashedPassword, $roles[$i]);
        $stmt->execute();
    }
    
    echo "<div class='alert alert-success'>User(s) added successfully!</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .password-requirements {
            font-size: 0.9em;
            color: red;
            display: none;
        }
        .valid {
            color: green;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="users.php">Users</a></li>
            <li class="breadcrumb-item active" aria-current="page">Add User</li>
            <li class="ml-auto">
                <form method="GET" action="">
                    <label for="exam_year">Exam Year: </label>
                    <select id="exam_year" name="exam_year" onchange="this.form.submit()">
                        <option value="<?= $current_exam_year ?>" selected><?= $current_exam_year ?></option>
                        <?php while ($row = $previous_exam_years_result->fetch_assoc()): ?>
                            <option value="<?= $row['exam_year'] ?>"><?= $row['exam_year'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </li>
        </ol>
    </nav>

    <h1 class="mb-4">Add User</h1>

    <form method="POST" action="" id="add-user-form">
        <div id="user-fields">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username[]" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email[]" required>
                </div>
                <div class="form-group col-md-3">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password[]" required onkeyup="validatePassword(this)">
                    <small class="password-requirements" id="password-requirements">
                        Password must be at least 6 characters long.
                    </small>
                </div>
                <div class="form-group col-md-3">
                    <label for="role">Role</label>
                    <select id="role" name="role[]" class="form-control" required>
                        <option value="System Admin">System Admin</option>
                        <option value="Data Entrant">Data Entrant</option>
                        <option value="Examination Administrator ">Exams Admin</option>
                    </select>
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-secondary mb-3" id="add-user-button">Add Another User</button>
        <button type="submit" name="submit_user" class="btn btn-primary">Submit</button>
    </form>
</div>

<script>
    function validatePassword(input) {
        const passwordRequirements = document.getElementById('password-requirements');
        if (input.value.length >= 6) {
            passwordRequirements.classList.add('valid');
            passwordRequirements.textContent = 'Password meets the requirements.';
        } else {
            passwordRequirements.classList.remove('valid');
            passwordRequirements.textContent = 'Password must be at least 6 characters long.';
        }
        passwordRequirements.style.display = 'block';
    }

    document.getElementById('add-user-button').addEventListener('click', function() {
        const userFields = document.getElementById('user-fields');
        const newFields = userFields.firstElementChild.cloneNode(true);
        newFields.querySelectorAll('input').forEach(input => input.value = '');
        userFields.appendChild(newFields);
    });
</script>

</body>
</html>
