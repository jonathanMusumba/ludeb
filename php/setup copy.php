<?php
// Database credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LUDEB";

// Create necessary directories
$directories = ['Admin', 'Entrant', 'Inspection', 'Common/Uploads'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true)) {
            die("Failed to create directory: $dir");
        }
    }
}

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname";
    if (!$conn->query($sql)) {
        throw new Exception("Error creating database: " . $conn->error);
    }
    echo "Database created successfully<br>";

    // Use the database
    $conn->select_db($dbname);

    // Create stored procedure for audit logging
    try {
        $sql = "DROP PROCEDURE IF EXISTS log_action";
        if (!$conn->query($sql)) {
            error_log("Warning: Failed to drop stored procedure log_action: " . $conn->error, 3, '../setup_errors.log');
        }
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
    } catch (Exception $e) {
        error_log("Stored procedure error: " . $e->getMessage(), 3, '../setup_errors.log');
        throw $e;
    }

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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // grades
        "CREATE TABLE IF NOT EXISTS grades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aggregate_range_from INT NOT NULL,
            aggregate_range_to INT NOT NULL,
            division VARCHAR(10) NOT NULL,
            conditions TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // candidates
        "CREATE TABLE IF NOT EXISTS candidates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id INT NOT NULL,
            index_number VARCHAR(255) NOT NULL UNIQUE,
            candidate_name VARCHAR(255) NOT NULL,
            sex ENUM('Male', 'Female') NOT NULL,
            exam_year_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
            INDEX idx_index_number (index_number),
            INDEX idx_school_id (school_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // subject_candidates
        "CREATE TABLE IF NOT EXISTS subject_candidates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            candidate_id INT NOT NULL,
            subject_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            UNIQUE (candidate_id, subject_id),
            INDEX idx_candidate_id (candidate_id),
            INDEX idx_subject_id (subject_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // captured_subjects
        "CREATE TABLE IF NOT EXISTS captured_subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject_id INT NOT NULL,
            school_id INT NOT NULL,
            captured_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            UNIQUE (subject_id, school_id),
            INDEX idx_school_id (school_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // marks
        "CREATE TABLE IF NOT EXISTS marks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            candidate_id INT NOT NULL,
            subject_id INT NOT NULL,
            school_id INT NOT NULL,
            mark DECIMAL(5,2) NOT NULL CHECK (mark >= 0 AND mark <= 100),
            submitted_by INT NOT NULL,
            edited_by INT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            FOREIGN KEY (submitted_by) REFERENCES system_users(id) ON DELETE RESTRICT,
            FOREIGN KEY (edited_by) REFERENCES system_users(id) ON DELETE SET NULL,
            UNIQUE (candidate_id, subject_id),
            INDEX idx_candidate_id (candidate_id),
            INDEX idx_subject_id (subject_id),
            INDEX idx_school_id (school_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // results
        "CREATE TABLE IF NOT EXISTS results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            candidate_id INT NOT NULL,
            subject_id INT NOT NULL,
            school_id INT NOT NULL,
            mark DECIMAL(5,2) NOT NULL CHECK (mark >= 0 AND mark <= 100),
            score INT NOT NULL,
            aggregates INT,
            division VARCHAR(10),
            processed_by INT NOT NULL,
            updated_by INT NULL,
            processed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            FOREIGN KEY (processed_by) REFERENCES system_users(id) ON DELETE RESTRICT,
            FOREIGN KEY (updated_by) REFERENCES system_users(id) ON DELETE SET NULL,
            INDEX idx_candidate_id (candidate_id),
            INDEX idx_subject_id (subject_id),
            INDEX idx_school_id (school_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // school_results
        "CREATE TABLE IF NOT EXISTS school_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id INT NOT NULL,
            candidate_index_number VARCHAR(255) NOT NULL,
            candidate_name VARCHAR(255) NOT NULL,
            subject_code VARCHAR(20) NOT NULL,
            marks DECIMAL(5,2) NOT NULL CHECK (marks >= 0 AND marks <= 100),
            grade VARCHAR(2) NOT NULL,
            exam_year_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
            FOREIGN KEY (subject_code) REFERENCES subjects(code) ON DELETE CASCADE,
            INDEX idx_candidate_index_number (candidate_index_number),
            INDEX idx_exam_year_id (exam_year_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // subcounty_results
        "CREATE TABLE IF NOT EXISTS subcounty_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subcounty_id INT NOT NULL,
            candidate_index_number VARCHAR(255) NOT NULL,
            candidate_name VARCHAR(255) NOT NULL,
            subject_code VARCHAR(20) NOT NULL,
            marks DECIMAL(5,2) NOT NULL CHECK (marks >= 0 AND marks <= 100),
            grade VARCHAR(2) NOT NULL,
            exam_year_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (subcounty_id) REFERENCES subcounties(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
            FOREIGN KEY (subject_code) REFERENCES subjects(code) ON DELETE CASCADE,
            INDEX idx_candidate_index_number (candidate_index_number),
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
            INDEX idx_school_id (school_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // audit_logs
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(255) NOT NULL,
            user_id INT NULL,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE SET NULL,
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT DEFAULT NULL,
            chat_type ENUM('group', 'private') DEFAULT 'group',
            parent_id INT DEFAULT NULL,
            message TEXT NOT NULL,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME DEFAULT NULL,
            FOREIGN KEY (sender_id) REFERENCES system_users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES system_users(id) ON DELETE SET NULL,
            INDEX idx_sender_id (sender_id),
            INDEX idx_receiver_id (receiver_id),
            INDEX idx_chat_type (chat_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS daily_targets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            target_date DATE NOT NULL,
            target_entries INT NOT NULL,
            actual_entries INT DEFAULT 0,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES system_users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    // Execute table creation
    foreach ($tables as $table_sql) {
        try {
            if ($conn->query($table_sql)) {
                echo "Table created successfully<br>";
                if (strpos($table_sql, 'audit_logs') !== false) {
                    $conn->query("CALL log_action('Table Creation', NULL, 'Created audit_logs table')");
                }
            } else {
                throw new Exception("Error creating table: " . $conn->error);
            }
        } catch (Exception $e) {
            error_log("Table creation error: " . $e->getMessage(), 3, '../setup_errors.log');
            throw $e;
        }
    }

    // Insert default data
    try {
        if ($conn->query("INSERT IGNORE INTO school_types (type) VALUES ('Government'), ('Private')")) {
            echo "School types inserted successfully<br>";
        } else {
            throw new Exception("Error inserting school types: " . $conn->error);
        }

        $subjects = [
            ['English', 'ENG'],
            ['Mathematics', 'MATH'],
            ['Science', 'SCI'],
            ['Social Studies', 'SST']
        ];
        $stmt = $conn->prepare("INSERT IGNORE INTO subjects (name, code) VALUES (?, ?)");
        foreach ($subjects as $subject) {
            $stmt->bind_param("ss", $subject[0], $subject[1]);
            if (!$stmt->execute()) {
                throw new Exception("Error inserting subject {$subject[0]}: " . $stmt->error);
            }
        }
        $stmt->close();
        echo "Subjects inserted successfully<br>";
    } catch (Exception $e) {
        error_log("Default data insertion error: " . $e->getMessage(), 3, '../setup_errors.log');
        echo "Warning: Failed to insert default data. You can add these later.<br>";
    }

    // Process form
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        try {
            $districtName = filter_input(INPUT_POST, 'districtName', FILTER_SANITIZE_STRING);
            $districtCode = filter_input(INPUT_POST, 'districtCode', FILTER_SANITIZE_STRING);
            $boardName = filter_input(INPUT_POST, 'boardName', FILTER_SANITIZE_STRING);
            $examYear = filter_input(INPUT_POST, 'examYear', FILTER_VALIDATE_INT);
            $contactEmail = filter_input(INPUT_POST, 'contactEmail', FILTER_SANITIZE_EMAIL);

            if (!$districtName || !$districtCode || !$boardName || !$examYear || !$contactEmail) {
                throw new Exception("Invalid or missing form data");
            }

            $logo = NULL;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
                $uploadDir = 'Common/Uploads/';
                $logo = $uploadDir . basename($_FILES["logo"]["name"]);
                if (!move_uploaded_file($_FILES["logo"]["tmp_name"], $logo)) {
                    throw new Exception("Error uploading logo");
                }
            }

            $stmt = $conn->prepare("INSERT IGNORE INTO districts (district_name, district_code) VALUES (?, ?)");
            $stmt->bind_param("ss", $districtName, $districtCode);
            if (!$stmt->execute()) {
                throw new Exception("Error inserting district: " . $stmt->error);
            }
            $districtId = $conn->insert_id ?: $conn->query("SELECT id FROM districts WHERE district_code = '$districtCode'")->fetch_assoc()['id'];
            $stmt->close();
            echo "District inserted successfully<br>";

            $stmt = $conn->prepare("INSERT IGNORE INTO exam_years (exam_year, status) VALUES (?, 'Active')");
            $stmt->bind_param("i", $examYear);
            if (!$stmt->execute()) {
                throw new Exception("Error inserting exam year: " . $stmt->error);
            }
            $examYearId = $conn->insert_id ?: $conn->query("SELECT id FROM exam_years WHERE exam_year = $examYear")->fetch_assoc()['id'];
            $stmt->close();
            echo "Exam year inserted successfully<br>";

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
                if (!$stmt->execute()) {
                    throw new Exception("Error inserting subcounty {$subcounty[0]}: " . $stmt->error);
                }
            }
            $stmt->close();
            echo "Subcounties inserted successfully<br>";

            $stmt = $conn->prepare("INSERT INTO settings (board_name, exam_year_id, logo, contact_email, district_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sissi", $boardName, $examYearId, $logo, $contactEmail, $districtId);
            if (!$stmt->execute()) {
                throw new Exception("Error inserting settings: " . $stmt->error);
            }
            $stmt->close();
            echo "Settings inserted successfully<br>";

            $adminUsername = filter_input(INPUT_POST, 'adminUsername', FILTER_SANITIZE_STRING);
            $adminEmail = filter_input(INPUT_POST, 'adminEmail', FILTER_SANITIZE_EMAIL);
            $adminPassword = $_POST['adminPassword'] ?? '';
            if (!$adminUsername || !$adminEmail || !$adminPassword) {
                throw new Exception("Invalid or missing admin user data");
            }
            $adminPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
            $adminRole = 'System Admin';
            $adminStatus = 'Active';

            $stmt = $conn->prepare("INSERT INTO system_users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $adminUsername, $adminEmail, $adminPassword, $adminRole, $adminStatus);
            if (!$stmt->execute()) {
                throw new Exception("Error inserting system administrator: " . $stmt->error);
            }
            $adminId = $conn->insert_id;
            $details = "Created system administrator: $adminUsername";
            $conn->query("CALL log_action('User Creation', $adminId, '$details')");
            $stmt->close();
            echo "System administrator inserted successfully<br>";

            $usernames = $_POST['username'] ?? [];
            $emails = $_POST['email'] ?? [];
            $passwords = $_POST['password'] ?? [];
            $roles = $_POST['role'] ?? [];

            $stmt = $conn->prepare("INSERT INTO system_users (username, email, password, role, status) VALUES (?, ?, ?, ?, 'Active')");
            for ($i = 0; $i < count($usernames); $i++) {
                if (!empty($usernames[$i]) && !empty($emails[$i]) && !empty($passwords[$i])) {
                    $hashedPassword = password_hash($passwords[$i], PASSWORD_DEFAULT);
                    $stmt->bind_param("ssss", $usernames[$i], $emails[$i], $hashedPassword, $roles[$i]);
                    if (!$stmt->execute()) {
                        error_log("Warning: Error inserting user {$usernames[$i]}: " . $stmt->error, 3, '../setup_errors.log');
                        echo "Warning: Failed to insert user {$usernames[$i]}. Continuing setup.<br>";
                        continue;
                    }
                    $userId = $conn->insert_id;
                    $details = "Created user: $usernames[$i], Role: $roles[$i]";
                    $conn->query("CALL log_action('User Creation', $userId, '$details')");
                }
            }
            $stmt->close();
            echo "Additional users inserted successfully<br>";

            $conn->query("CALL log_action('System Setup', $adminId, 'Completed initial system setup')");

            header("Location: ../index.php");
            exit();
        } catch (Exception $e) {
            error_log("Form processing error: " . $e->getMessage(), 3, '../setup_errors.log');
            echo "Error during form processing: " . htmlspecialchars($e->getMessage()) . "<br>";
            echo "<a href='setup.html'>Back to Setup</a>";
            exit();
        }
    }

} catch (Exception $e) {
    error_log("Setup error: " . $e->getMessage(), 3, '../setup_errors.log');
    echo "An error occurred during setup: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<a href='setup.html'>Back to Setup</a>";
}

$conn->close();
?>