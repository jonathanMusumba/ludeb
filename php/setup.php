<?php
// Database credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";


// Create necessary directories
$directories = ['admin', 'entrant', 'inspection', 'uploads'];
$projectRoot = dirname(__DIR__); // Gets C:\xampp\htdocs\ludeb
foreach ($directories as $dir) {
    $fullPath = $projectRoot . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($fullPath)) {
        if (!mkdir($fullPath, 0755, true)) {
            error_log("Failed to create directory: $dir at $fullPath", 3, $projectRoot . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'setup_errors.log');
            echo "Failed to create directory: $dir<br>";
            continue; // Continue with the next directory
        }
        echo "Created directory: $dir<br>";
    } else {
        echo "Directory already exists: $dir<br>";
    }
}

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, null, $port);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
    if (!$conn->query($sql)) {
        throw new Exception("Error creating database: " . $conn->error);
    }
    echo "Database created successfully<br>";

    // Use the database
    $conn->select_db($dbname);

    // Create stored procedure for audit logging
    $sql = "DROP PROCEDURE IF EXISTS log_action";
    $conn->query($sql);
    $sql = "
        CREATE PROCEDURE log_action (
            IN p_action VARCHAR(255),
            IN p_user_id INT,
            IN p_details TEXT
        )
        BEGIN
            INSERT INTO audit_logs (action, user_id, details)
            VALUES (p_action, p_user_id, p_details);
        END
    ";
    if (!$conn->query($sql)) {
        throw new Exception("Error creating stored procedure log_action: " . $conn->error);
    }
    echo "Stored procedure created successfully<br>";

    // Create tables
    $tables = [
        // districts
        "CREATE TABLE IF NOT EXISTS districts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            district_name VARCHAR(100) NOT NULL,
            district_code VARCHAR(20) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_district_code (district_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // school_types
        "CREATE TABLE IF NOT EXISTS school_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // subcounties
        "CREATE TABLE IF NOT EXISTS subcounties (
            id INT AUTO_INCREMENT PRIMARY KEY,
            district_id INT NOT NULL,
            subcounty VARCHAR(255) NOT NULL,
            constituency VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE,
            INDEX idx_subcounty (subcounty)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // exam_years
        "CREATE TABLE IF NOT EXISTS exam_years (
            id INT AUTO_INCREMENT PRIMARY KEY,
            exam_year YEAR NOT NULL UNIQUE,
            status ENUM('Active', 'Not Active') DEFAULT 'Active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_exam_year (exam_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // subjects
        "CREATE TABLE IF NOT EXISTS subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(20) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // settings
        "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            board_name VARCHAR(100) NOT NULL,
            exam_year_id INT NOT NULL,
            logo VARCHAR(255),
            grading_scale VARCHAR(50) NOT NULL DEFAULT 'Standard',
            contact_email VARCHAR(100),
            district_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
            FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE,
            INDEX idx_district_id (district_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // schools
        "CREATE TABLE IF NOT EXISTS schools (
            id INT AUTO_INCREMENT PRIMARY KEY,
            center_no VARCHAR(20) NOT NULL UNIQUE,
            school_name VARCHAR(100) NOT NULL,
            subcounty_id INT NOT NULL,
            school_type_id INT NOT NULL,
            status ENUM('Active', 'Not Active') DEFAULT 'Active',
            results_status ENUM('Not Declared', 'Partially Declared', 'Declared') DEFAULT 'Not Declared',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (subcounty_id) REFERENCES subcounties(id) ON DELETE RESTRICT,
            FOREIGN KEY (school_type_id) REFERENCES school_types(id) ON DELETE RESTRICT,
            INDEX idx_center_no (center_no),
            INDEX idx_subcounty_id (subcounty_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // system_users
        "CREATE TABLE IF NOT EXISTS system_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('System Admin', 'Examination Administrator', 'Data Entrant') NOT NULL,
            school_id INT NULL,
            status ENUM('Active', 'Invalid') DEFAULT 'Active',
            last_login DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL,
            INDEX idx_username (username),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // grading
            "CREATE TABLE IF NOT EXISTS grading (
                id INT AUTO_INCREMENT PRIMARY KEY,
                range_from INT NOT NULL,
                range_to INT NOT NULL,
                grade VARCHAR(2) NOT NULL,
                score INT NOT NULL,
                subject_id INT NULL,
                exam_year_id INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
                FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE SET NULL,
                UNIQUE KEY uk_grading (range_from, range_to, subject_id, exam_year_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",


        // grades
        "CREATE TABLE IF NOT EXISTS grades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aggregate_range_from INT NOT NULL,
            aggregate_range_to INT NOT NULL,
            division VARCHAR(10) NOT NULL,
            conditions TEXT,
            exam_year_id INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_grades_exam_year FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // candidates
        "CREATE TABLE IF NOT EXISTS candidates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id INT NOT NULL,
            index_number VARCHAR(255) NOT NULL,
            candidate_name VARCHAR(255) NOT NULL,
            sex ENUM('Male', 'Female') NOT NULL,
            exam_year_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
            UNIQUE KEY uk_index_number_exam_year (index_number, exam_year_id),
            INDEX idx_index_number (index_number),
            INDEX idx_school_id (school_id),
            INDEX idx_exam_year_id (exam_year_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // marks
        "CREATE TABLE IF NOT EXISTS marks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            candidate_id INT NOT NULL,
            subject_id INT NOT NULL,
            school_id INT NOT NULL,
            exam_year_id INT NOT NULL,
            mark INT NOT NULL CHECK (mark >= 0 AND mark <= 100),
            status ENUM('PRESENT', 'ABSENT') DEFAULT 'PRESENT',
            submitted_by INT NOT NULL,
            edited_by INT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
            FOREIGN KEY (submitted_by) REFERENCES system_users(id) ON DELETE RESTRICT,
            FOREIGN KEY (edited_by) REFERENCES system_users(id) ON DELETE SET NULL,
            UNIQUE KEY uk_marks (candidate_id, subject_id, exam_year_id),
            INDEX idx_candidate_id (candidate_id),
            INDEX idx_subject_id (subject_id),
            INDEX idx_school_id (school_id),
            INDEX idx_exam_year_id (exam_year_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // results
        "CREATE TABLE IF NOT EXISTS results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            candidate_id INT NOT NULL,
            subject_id INT NOT NULL,
            school_id INT NOT NULL,
            exam_year_id INT NOT NULL,
            mark INT NOT NULL CHECK (mark >= 0 AND mark <= 100),
            score INT NOT NULL CHECK (score >= 1 AND score <= 9),
            processed_by INT NOT NULL,
            updated_by INT NULL,
            processed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
            FOREIGN KEY (processed_by) REFERENCES system_users(id) ON DELETE RESTRICT,
            FOREIGN KEY (updated_by) REFERENCES system_users(id) ON DELETE SET NULL,
            UNIQUE KEY uk_results (candidate_id, subject_id, exam_year_id),
            INDEX idx_candidate_id (candidate_id),
            INDEX idx_subject_id (subject_id),
            INDEX idx_school_id (school_id),
            INDEX idx_exam_year_id (exam_year_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // candidate_results
        "CREATE TABLE IF NOT EXISTS candidate_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            candidate_id INT NOT NULL,
            school_id INT NOT NULL,
            exam_year_id INT NOT NULL,
            aggregates VARCHAR(10) NOT NULL,
            division VARCHAR(10) NOT NULL,
            processed_by INT NOT NULL,
            updated_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
            FOREIGN KEY (processed_by) REFERENCES system_users(id) ON DELETE RESTRICT,
            FOREIGN KEY (updated_by) REFERENCES system_users(id) ON DELETE SET NULL,
            UNIQUE KEY uk_candidate_results (candidate_id, exam_year_id),
            INDEX idx_candidate_id (candidate_id),
            INDEX idx_school_id (school_id),
            INDEX idx_exam_year_id (exam_year_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // school_results
        "CREATE TABLE IF NOT EXISTS school_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id INT NOT NULL,
            candidate_id INT NOT NULL,
            candidate_index_number VARCHAR(255) NOT NULL,
            candidate_name VARCHAR(255) NOT NULL,
            subject_code VARCHAR(20) NOT NULL,
            marks INT NOT NULL CHECK (marks >= 0 AND marks <= 100),
            grade VARCHAR(2) NOT NULL,
            exam_year_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
            FOREIGN KEY (subject_code) REFERENCES subjects(code) ON DELETE CASCADE,
            UNIQUE KEY uk_school_results (candidate_id, subject_code, exam_year_id),
            INDEX idx_candidate_index_number (candidate_index_number),
            INDEX idx_exam_year_id (exam_year_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // subcounty_results
        "CREATE TABLE IF NOT EXISTS subcounty_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subcounty_id INT NOT NULL,
            candidate_id INT NOT NULL,
            candidate_index_number VARCHAR(255) NOT NULL,
            candidate_name VARCHAR(255) NOT NULL,
            subject_code VARCHAR(20) NOT NULL,
            marks INT NOT NULL CHECK (marks >= 0 AND marks <= 100),
            grade VARCHAR(2) NOT NULL,
            exam_year_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (subcounty_id) REFERENCES subcounties(id) ON DELETE CASCADE,
            FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
            FOREIGN KEY (subject_code) REFERENCES subjects(code) ON DELETE CASCADE,
            UNIQUE KEY uk_subcounty_results (candidate_id, subject_code, exam_year_id),
            INDEX idx_candidate_index_number (candidate_index_number),
            INDEX idx_exam_year_id (exam_year_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // daily_targets
        "CREATE TABLE IF NOT EXISTS daily_targets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            target_date DATE NOT NULL,
            target_entries INT NOT NULL,
            exam_year_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
            UNIQUE KEY uk_daily_targets (target_date, exam_year_id),
            INDEX idx_exam_year_id (exam_year_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // uploads
        "CREATE TABLE IF NOT EXISTS uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id INT NULL,
            filename VARCHAR(255) NOT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            uploaded_by INT NOT NULL,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL,
            FOREIGN KEY (uploaded_by) REFERENCES system_users(id) ON DELETE RESTRICT,
            INDEX idx_school_id (school_id),
            INDEX idx_uploaded_by (uploaded_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // audit_logs
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(255) NOT NULL,
            user_id INT NULL,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE SET NULL,
            INDEX idx_action (action),
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    // Execute table creation
    foreach ($tables as $table_sql) {
        if ($conn->query($table_sql)) {
            echo "Table created successfully<br>";
            if (strpos($table_sql, 'audit_logs') !== false) {
                $conn->query("CALL log_action('Table Creation', NULL, 'Created audit_logs table')");
            }
        } else {
            error_log("Table creation error: " . $conn->error, 3, __DIR__ . '/../logs/setup_errors.log');
            throw new Exception("Error creating table: " . $conn->error);
        }
    }

    // Insert default data
    $conn->query("INSERT IGNORE INTO school_types (type) VALUES ('Primary'), ('Secondary')");
    echo "School types inserted successfully<br>";

    $subjects = [
        ['English', 'ENG'],
        ['Mathematics', 'MTC'],
        ['Science', 'SCI'],
        ['Social Studies', 'SST']
    ];
    $stmt = $conn->prepare("INSERT IGNORE INTO subjects (name, code) VALUES (?, ?)");
    foreach ($subjects as $subject) {
        $stmt->bind_param("ss", $subject[0], $subject[1]);
        $stmt->execute();
    }
    $stmt->close();
    echo "Subjects inserted successfully<br>";

    // Insert grading data
    $grading = [
        [0, 20, 'F9', 9],
        [21, 34, 'P8', 8],
        [35, 49, 'P7', 7],
        [50, 59, 'C6', 6],
        [60, 69, 'C5', 5],
        [70, 79, 'C4', 4],
        [80, 84, 'C3', 3],
        [85, 89, 'D2', 2],
        [90, 100, 'D1', 1]
    ];
    $stmt = $conn->prepare("INSERT IGNORE INTO grading (range_from, range_to, grade, score) VALUES (?, ?, ?, ?)");
    foreach ($grading as $grade) {
        $stmt->bind_param("iisi", $grade[0], $grade[1], $grade[2], $grade[3]);
        $stmt->execute();
    }
    $stmt->close();
    echo "Grading scale inserted successfully<br>";

    // Insert grades data
    $grades = [
        [4, 12, 'Division 1', '{"must_pass_all": true, "subjects": ["ENG", "MTC", "SCI", "SST"], "max_grade_per_subject": 8, "exceptions": [{"subject": "ENG", "grade": 9, "demote_to": "Division 2"}, {"subject": "MTC", "grade": 9, "demote_to": "Division 2"}]}'],
        [13, 23, 'Division 2', '{"min_pass_subjects": 3, "subjects": ["ENG", "MTC", "SCI", "SST"], "max_grade_per_subject": 8, "exceptions": [{"subjects_failed": ["ENG", "MTC"], "grade": 9, "demote_to": "Division 3"}]}'],
        [24, 28, 'Division 3', '{"min_pass_subjects": 3, "subjects": ["ENG", "MTC", "SCI", "SST"], "max_grade_per_subject": 8}'],
        [29, 29, 'Division 3', '{"min_pass_subjects": 3, "subjects": ["ENG", "MTC", "SCI", "SST"], "max_grade_per_subject": 8, "must_include": ["ENG", "MTC"], "must_include_condition": "at least one"}'],
        [29, 32, 'Division 4', '{"min_pass_subjects": 2, "subjects": ["ENG", "MTC", "SCI", "SST"], "max_grade_per_subject": 8}'],
        [33, 33, 'Division 4', '{"min_pass_subjects": 2, "subjects": ["ENG", "MTC", "SCI", "SST"], "max_grade_per_subject": 8, "must_include": ["ENG", "MTC"], "must_include_condition": "at least one"}'],
        [33, 36, 'Ungraded', '{"max_pass_subjects": 1, "subjects": ["ENG", "MTC", "SCI", "SST"], "max_grade_per_subject": 8}']
    ];
    $stmt = $conn->prepare("INSERT IGNORE INTO grades (aggregate_range_from, aggregate_range_to, division, conditions) VALUES (?, ?, ?, ?)");
    foreach ($grades as $grade) {
        $stmt->bind_param("iiss", $grade[0], $grade[1], $grade[2], $grade[3]);
        $stmt->execute();
    }
    $stmt->close();
    echo "Grades inserted successfully<br>";

    // Insert Luuka District
        $districtName = 'Luuka District';
        $districtCode = '102';
        $stmt = $conn->prepare("INSERT IGNORE INTO districts (district_name, district_code) VALUES (?, ?)");
        $stmt->bind_param("ss", $districtName, $districtCode);
        $stmt->execute();

        // If no new row inserted, fetch the existing one
        if ($stmt->insert_id > 0) {
            $districtId = $stmt->insert_id;
        } else {
            $stmt2 = $conn->prepare("SELECT id FROM districts WHERE district_code = ?");
            $stmt2->bind_param("s", $districtCode);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $row = $result->fetch_assoc();
            $districtId = $row['id'] ?? null;
            $stmt2->close();
        }

        $stmt->close();

        echo "Luuka District inserted or retrieved successfully. ID: $districtId<br>";


    // Insert subcounties
    $subcounties = [
        ['Luuka Town Council', 'Luuka North'],
        ['Ikumbya', 'Luuka North'],
        ['Bulongo', 'Luuka North'],
        ['Bukoma', 'Luuka North'],
        ['Bukoova Town Council', 'Luuka North'],
        ['Bukanga', 'Luuka South'],
        ['Irongo', 'Luuka South'],
        ['Kyanvuma Town Council', 'Luuka South'],
        ['Busalamu Town Council', 'Luuka South'],
        ['Bulanga Town Council', 'Luuka South'],
        ['Nawampiti', 'Luuka South'],
        ['Waibuga', 'Luuka South']
    ];
    $stmt = $conn->prepare("INSERT IGNORE INTO subcounties (district_id, subcounty, constituency) VALUES (?, ?, ?)");
    foreach ($subcounties as $subcounty) {
        $stmt->bind_param("iss", $districtId, $subcounty[0], $subcounty[1]);
        $stmt->execute();
    }
    $stmt->close();
    echo "Subcounties inserted successfully<br>";

    // Process form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $boardName = filter_input(INPUT_POST, 'boardName', FILTER_SANITIZE_STRING);
        $examYear = filter_input(INPUT_POST, 'examYear', FILTER_VALIDATE_INT);
        $contactEmail = filter_input(INPUT_POST, 'contactEmail', FILTER_SANITIZE_EMAIL);
        $adminUsername = filter_input(INPUT_POST, 'adminUsername', FILTER_SANITIZE_STRING);
        $adminEmail = filter_input(INPUT_POST, 'adminEmail', FILTER_SANITIZE_EMAIL);
        $adminPassword = $_POST['adminPassword'] ?? '';

        if (!$boardName || !$examYear || !$contactEmail || !$adminUsername || !$adminEmail || !$adminPassword) {
            throw new Exception("Missing or invalid form data");
        }

        if ($examYear < 2000 || $examYear > 2099) {
            throw new Exception("Examination Year must be between 2000 and 2099");
        }

        // Upload logo
        $logo = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/';
            $logoName = uniqid('logo_') . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $logoPath = $uploadDir . $logoName;
            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath)) {
                throw new Exception("Error uploading logo");
            }
            $logo = 'uploads/' . $logoName;
        }

        // Insert exam year
        $stmt = $conn->prepare("INSERT IGNORE INTO exam_years (exam_year, status) VALUES (?, 'Active')");
        $stmt->bind_param("i", $examYear);
        $stmt->execute();
        $examYearId = $conn->insert_id ?: $conn->query("SELECT id FROM exam_years WHERE exam_year = $examYear")->fetch_assoc()['id'];
        $stmt->close();
        echo "Exam year inserted successfully<br>";

        // Insert settings
        $gradingScale = 'Standard';
        $stmt = $conn->prepare("INSERT INTO settings (board_name, exam_year_id, logo, grading_scale, contact_email, district_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisssi", $boardName, $examYearId, $logo, $gradingScale, $contactEmail, $districtId);
        $stmt->execute();
        $stmt->close();
        echo "Settings inserted successfully<br>";

        // Insert admin user
        $adminPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
        $adminRole = 'System Admin';
        $stmt = $conn->prepare("INSERT INTO system_users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $adminUsername, $adminEmail, $adminPassword, $adminRole);
        $stmt->execute();
        $adminId = $conn->insert_id;
        $conn->query("CALL log_action('User Creation', $adminId, 'Created system administrator: $adminUsername')");
        $stmt->close();
        echo "System administrator inserted successfully<br>";

        // Insert additional users
        $usernames = $_POST['username'] ?? [];
        $emails = $_POST['email'] ?? [];
        $passwords = $_POST['password'] ?? [];
        $roles = $_POST['role'] ?? [];
        $stmt = $conn->prepare("INSERT INTO system_users (username, email, password, role) VALUES (?, ?, ?, ?)");
        for ($i = 0; $i < count($usernames); $i++) {
            if (!empty($usernames[$i]) && !empty($emails[$i]) && !empty($passwords[$i]) && !empty($roles[$i])) {
                $hashedPassword = password_hash($passwords[$i], PASSWORD_DEFAULT);
                $stmt->bind_param("ssss", $usernames[$i], $emails[$i], $hashedPassword, $roles[$i]);
                if ($stmt->execute()) {
                    $userId = $conn->insert_id;
                    $conn->query("CALL log_action('User Creation', $userId, 'Created user: {$usernames[$i]}, Role: {$roles[$i]}')");
                } else {
                    error_log("Error inserting user {$usernames[$i]}: " . $stmt->error, 3, __DIR__ . '/../logs/setup_errors.log');
                }
            }
        }
        $stmt->close();
        echo "Additional users inserted successfully<br>";

        $conn->query("CALL log_action('System Setup', $adminId, 'Completed initial system setup')");

        header("Location: /ludeb/index.php");
        exit();
    }

} catch (Exception $e) {
    error_log("Setup error: " . $e->getMessage(), 3, __DIR__ . '/../logs/setup_errors.log');
    echo "An error occurred during setup: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<a href='../setup.php' class='btn btn-primary'>Back to Setup</a>";
}

$conn->close();
?>