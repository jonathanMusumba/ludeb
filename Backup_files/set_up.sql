-- Luuka District Examination Board (ludeb) Database Schema
-- Preserves all tables as provided, including grades and grading tables
-- Adds exam_year_id to candidates, marks, and candidate_results
-- Optimized for MySQL 5.7/8.0, XAMPP, port 3307
-- Compatible with marks.php and save_marks.php
-- Supports candidate-specific notifications and large datasets
-- Last updated: July 28, 2025

SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS ludeb;
CREATE DATABASE ludeb CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE ludeb;

-- Table: districts
CREATE TABLE districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    district_name VARCHAR(100) NOT NULL,
    district_code VARCHAR(20) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_district_code (district_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: school_types
CREATE TABLE school_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: subcounties
CREATE TABLE subcounties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    district_id INT NOT NULL,
    subcounty VARCHAR(255) NOT NULL,
    constituency VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE,
    INDEX idx_subcounty (subcounty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: exam_years
CREATE TABLE exam_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_year YEAR NOT NULL UNIQUE,
    status ENUM('Active', 'Not Active') DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_exam_year (exam_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: subjects
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    board_name VARCHAR(100) NOT NULL,
    exam_year_id INT NOT NULL,
    logo VARCHAR(255),
    grading_scale VARCHAR(50) NOT NULL,
    contact_email VARCHAR(100),
    district_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE CASCADE,
    FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE,
    INDEX idx_district_id (district_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: schools
CREATE TABLE schools (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: system_users
CREATE TABLE system_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('System Admin', 'Examination Administrator', 'Data Entrant') NOT NULL,
    school_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: grading
CREATE TABLE grading (
    id INT AUTO_INCREMENT PRIMARY KEY,
    range_from INT NOT NULL,
    range_to INT NOT NULL,
    grade VARCHAR(2) NOT NULL,
    score INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: grades
CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aggregate_range_from INT NOT NULL,
    aggregate_range_to INT NOT NULL,
    division VARCHAR(10) NOT NULL,
    conditions TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: candidates
CREATE TABLE candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    index_number VARCHAR(255) NOT NULL UNIQUE,
    candidate_name VARCHAR(255) NOT NULL,
    sex ENUM('Male', 'Female') NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    INDEX idx_index_number (index_number),
    INDEX idx_school_id (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: subject_candidates
CREATE TABLE subject_candidates (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: captured_subjects
CREATE TABLE captured_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    school_id INT NOT NULL,
    captured_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE (subject_id, school_id),
    INDEX idx_school_id (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: marks
CREATE TABLE marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    subject_id INT NOT NULL,
    school_id INT NOT NULL,
    mark INT NOT NULL CHECK (mark >= 0 AND mark <= 100),
    status ENUM('PRESENT', 'ABSENT') DEFAULT 'PRESENT',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: results
CREATE TABLE results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    subject_id INT NOT NULL,
    school_id INT NOT NULL,
    mark INT NOT NULL CHECK (mark >= 0 AND mark <= 100),
    score INT NOT NULL CHECK (score >= 1 AND score <= 9),
    processed_by INT NOT NULL,
    updated_by INT NULL,
    processed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES system_users(id) ON DELETE RESTRICT,
    FOREIGN KEY (updated_by) REFERENCES system_users(id) ON DELETE SET NULL,
    UNIQUE (candidate_id, subject_id),
    INDEX idx_candidate_id (candidate_id),
    INDEX idx_subject_id (subject_id),
    INDEX idx_school_id (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: candidate_results
CREATE TABLE candidate_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    school_id INT NOT NULL,
    aggregates VARCHAR(10) NOT NULL,
    division VARCHAR(10) NOT NULL,
    processed_by INT NOT NULL,
    updated_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES system_users(id) ON DELETE RESTRICT,
    FOREIGN KEY (updated_by) REFERENCES system_users(id) ON DELETE SET NULL,
    UNIQUE (candidate_id),
    INDEX idx_candidate_id (candidate_id),
    INDEX idx_school_id (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: school_results
CREATE TABLE school_results (
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
    UNIQUE (candidate_id, subject_code, exam_year_id),
    INDEX idx_candidate_index_number (candidate_index_number),
    INDEX idx_exam_year_id (exam_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: subcounty_results
CREATE TABLE subcounty_results (
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
    UNIQUE (candidate_id, subject_code, exam_year_id),
    INDEX idx_candidate_index_number (candidate_index_number),
    INDEX idx_exam_year_id (exam_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: daily_targets
CREATE TABLE daily_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_date DATE NOT NULL,
    target_entries INT NOT NULL,
    exam_year_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
    UNIQUE (target_date, exam_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: uploads
CREATE TABLE uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NULL,
    filename VARCHAR(255) NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT NOT NULL,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES system_users(id) ON DELETE RESTRICT,
    INDEX idx_school_id (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: audit_logs
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    user_id INT NULL,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE SET NULL,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add exam_year_id to candidates
ALTER TABLE candidates
ADD exam_year_id INT NOT NULL,
ADD FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
ADD UNIQUE KEY uk_index_number_exam_year (index_number, exam_year_id),
ADD INDEX idx_exam_year_id (exam_year_id);

-- Add exam_year_id to marks
ALTER TABLE marks
ADD exam_year_id INT NOT NULL,
ADD FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
DROP INDEX candidate_id,
ADD UNIQUE KEY uk_marks (candidate_id, subject_id, exam_year_id),
ADD INDEX idx_exam_year_id (exam_year_id);

-- Add exam_year_id to candidate_results
ALTER TABLE candidate_results
ADD exam_year_id INT NOT NULL,
ADD FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
DROP INDEX candidate_id,
ADD UNIQUE KEY uk_candidate_results (candidate_id, exam_year_id),
ADD INDEX idx_exam_year_id (exam_year_id);

-- Add exam_year_id to subject_candidates
ALTER TABLE subject_candidates
ADD exam_year_id INT NOT NULL,
ADD FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
DROP INDEX candidate_id,
ADD UNIQUE KEY uk_subject_candidates (candidate_id, subject_id, exam_year_id),
ADD INDEX idx_exam_year_id (exam_year_id);

-- Add exam_year_id to captured_subjects
ALTER TABLE captured_subjects
ADD exam_year_id INT NOT NULL,
ADD FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
DROP INDEX subject_id,
ADD UNIQUE KEY uk_captured_subjects (subject_id, school_id, exam_year_id),
ADD INDEX idx_exam_year_id (exam_year_id);

-- Add exam_year_id to results
ALTER TABLE results
ADD exam_year_id INT NOT NULL,
ADD FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
DROP INDEX candidate_id,
ADD UNIQUE KEY uk_results (candidate_id, subject_id, exam_year_id),
ADD INDEX idx_exam_year_id (exam_year_id);

-- Stored Procedure: log_action
DELIMITER //

CREATE PROCEDURE log_action (
    IN p_action VARCHAR(255),
    IN p_user_id INT,
    IN p_details TEXT
)
BEGIN
    INSERT INTO audit_logs (action, user_id, details)
    VALUES (p_action, p_user_id, p_details);
END //

-- Stored Procedure: ComputeCandidateGrades
CREATE PROCEDURE ComputeCandidateGrades (
    IN p_candidate_id INT,
    IN p_school_id INT,
    IN p_subcounty_id INT,
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_score INT;
    DECLARE v_mark INT;
    DECLARE v_status ENUM('PRESENT', 'ABSENT');
    DECLARE v_subject_id INT;
    DECLARE v_grade VARCHAR(2);
    DECLARE done INT DEFAULT FALSE;

    DECLARE cur CURSOR FOR
        SELECT m.mark, m.status, m.subject_id
        FROM marks m
        WHERE m.candidate_id = p_candidate_id 
        AND m.exam_year_id = p_exam_year_id
        AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    IF p_user_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User ID cannot be NULL';
    END IF;

    START TRANSACTION;

    DELETE FROM results WHERE candidate_id = p_candidate_id AND exam_year_id = p_exam_year_id;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_mark, v_status, v_subject_id;
        IF done THEN
            LEAVE read_loop;
        END IF;

        IF v_status = 'PRESENT' THEN
            SELECT score, grade INTO v_score, v_grade
            FROM grading
            WHERE v_mark BETWEEN range_from AND range_to
            LIMIT 1;

            IF v_score IS NOT NULL THEN
                INSERT INTO results (
                    candidate_id, subject_id, school_id, exam_year_id, mark, score, processed_by
                )
                VALUES (
                    p_candidate_id, v_subject_id, p_school_id, p_exam_year_id, v_mark, v_score, p_user_id
                )
                ON DUPLICATE KEY UPDATE
                    mark = v_mark,
                    score = v_score,
                    processed_by = p_user_id,
                    updated_at = NOW();
            END IF;
        END IF;
    END LOOP;
    CLOSE cur;

    CALL log_action('Compute Grades', p_user_id, 
        CONCAT('Computed grades for candidate_id: ', p_candidate_id, ', exam_year_id: ', p_exam_year_id));

    COMMIT;
END //

-- Stored Procedure: ComputeCandidateResults
CREATE PROCEDURE ComputeCandidateResults (
    IN p_candidate_id INT,
    IN p_school_id INT,
    IN p_subcounty_id INT,
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_total_marks INT DEFAULT 0;
    DECLARE v_aggregate INT DEFAULT 0;
    DECLARE v_subject_count INT DEFAULT 0;
    DECLARE v_candidate_name VARCHAR(255);
    DECLARE v_index_number VARCHAR(255);
    DECLARE v_division VARCHAR(10);
    DECLARE v_mark INT;
    DECLARE v_status ENUM('PRESENT', 'ABSENT');
    DECLARE v_subject_id INT;
    DECLARE v_score INT;
    DECLARE v_grade VARCHAR(2);
    DECLARE done INT DEFAULT FALSE;

    DECLARE cur CURSOR FOR
        SELECT m.mark, m.status, m.subject_id
        FROM marks m
        WHERE m.candidate_id = p_candidate_id 
        AND m.exam_year_id = p_exam_year_id
        AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    IF p_user_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User ID cannot be NULL';
    END IF;

    START TRANSACTION;

    SELECT candidate_name, index_number INTO v_candidate_name, v_index_number
    FROM candidates WHERE id = p_candidate_id;

    SELECT COUNT(*) INTO v_subject_count
    FROM marks m
    WHERE m.candidate_id = p_candidate_id 
    AND m.exam_year_id = p_exam_year_id
    AND m.status = 'PRESENT'
    AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));

    IF v_subject_count = 4 THEN
        SELECT COALESCE(SUM(m.mark), 0) INTO v_total_marks
        FROM marks m
        WHERE m.candidate_id = p_candidate_id 
        AND m.exam_year_id = p_exam_year_id
        AND m.status = 'PRESENT'
        AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));

        SELECT COALESCE(SUM(g.score), 0) INTO v_aggregate
        FROM marks m
        JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to
        WHERE m.candidate_id = p_candidate_id 
        AND m.exam_year_id = p_exam_year_id
        AND m.status = 'PRESENT'
        AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));

        SELECT division INTO v_division
        FROM grades
        WHERE v_aggregate BETWEEN aggregate_range_from AND aggregate_range_to
        LIMIT 1;

        INSERT INTO candidate_results (
            candidate_id, school_id, exam_year_id, aggregates, division, processed_by
        )
        VALUES (
            p_candidate_id, p_school_id, p_exam_year_id, v_aggregate, 
            COALESCE(v_division, 'Ungraded'), p_user_id
        )
        ON DUPLICATE KEY UPDATE
            aggregates = v_aggregate,
            division = COALESCE(v_division, 'Ungraded'),
            processed_by = p_user_id,
            updated_at = NOW();

        OPEN cur;
        read_loop: LOOP
            FETCH cur INTO v_mark, v_status, v_subject_id;
            IF done THEN
                LEAVE read_loop;
            END IF;

            IF v_status = 'PRESENT' THEN
                SELECT score, grade INTO v_score, v_grade
                FROM grading
                WHERE v_mark BETWEEN range_from AND range_to
                LIMIT 1;

                IF v_score IS NOT NULL THEN
                    INSERT INTO school_results (
                        school_id, candidate_id, candidate_index_number, candidate_name, 
                        subject_code, marks, grade, exam_year_id
                    )
                    SELECT 
                        p_school_id, p_candidate_id, v_index_number, v_candidate_name, 
                        s.code, v_mark, v_grade, p_exam_year_id
                    FROM subjects s WHERE s.id = v_subject_id
                    ON DUPLICATE KEY UPDATE
                        marks = v_mark,
                        grade = v_grade,
                        updated_at = NOW();

                    INSERT INTO subcounty_results (
                        subcounty_id, candidate_id, candidate_index_number, candidate_name, 
                        subject_code, marks, grade, exam_year_id
                    )
                    SELECT 
                        p_subcounty_id, p_candidate_id, v_index_number, v_candidate_name, 
                        s.code, v_mark, v_grade, p_exam_year_id
                    FROM subjects s WHERE s.id = v_subject_id
                    ON DUPLICATE KEY UPDATE
                        marks = v_mark,
                        grade = v_grade,
                        updated_at = NOW();
                END IF;
            END IF;
        END LOOP;
        CLOSE cur;
    END IF;

    CALL log_action('Compute Results', p_user_id, 
        CONCAT('Computed results for candidate_id: ', p_candidate_id, ', exam_year_id: ', p_exam_year_id));

    COMMIT;
END //

-- Stored Procedure: UpdateMissingResults
CREATE PROCEDURE UpdateMissingResults (
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_candidate_id INT;
    DECLARE v_school_id INT;
    DECLARE v_subcounty_id INT;
    DECLARE v_submitted_by INT;
    DECLARE done INT DEFAULT FALSE;

    DECLARE cur CURSOR FOR
        SELECT DISTINCT m.candidate_id, m.school_id, s.subcounty_id, m.submitted_by
        FROM marks m
        JOIN schools s ON m.school_id = s.id
        LEFT JOIN results r ON m.candidate_id = r.candidate_id 
            AND m.subject_id = r.subject_id 
            AND m.exam_year_id = r.exam_year_id
        WHERE m.exam_year_id = p_exam_year_id
        AND r.id IS NULL;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    IF p_user_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User ID cannot be NULL';
    END IF;

    START TRANSACTION;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_candidate_id, v_school_id, v_subcounty_id, v_submitted_by;
        IF done THEN
            LEAVE read_loop;
        END IF;

        IF v_submitted_by IS NULL OR v_submitted_by NOT IN (SELECT id FROM system_users) THEN
            SET v_submitted_by = p_user_id;
            CALL log_action('Invalid Submitted By', p_user_id, 
                CONCAT('Using p_user_id for submitted_by for candidate_id: ', v_candidate_id, 
                       ', exam_year_id: ', p_exam_year_id));
        END IF;

        CALL ComputeCandidateGrades(v_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, v_submitted_by);
        CALL ComputeCandidateResults(v_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, v_submitted_by);
    END LOOP;
    CLOSE cur;

    CALL log_action('Update Missing Results', p_user_id, 
        CONCAT('Processed missing results for exam_year_id: ', p_exam_year_id));

    COMMIT;
END //

-- Stored Procedure: ProcessAllCandidates
CREATE PROCEDURE ProcessAllCandidates (
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_candidate_id INT;
    DECLARE v_school_id INT;
    DECLARE v_subcounty_id INT;
    DECLARE done INT DEFAULT FALSE;

    DECLARE cur CURSOR FOR
        SELECT DISTINCT c.id, c.school_id, s.subcounty_id
        FROM candidates c
        JOIN schools s ON c.school_id = s.id
        WHERE c.exam_year_id = p_exam_year_id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    IF p_user_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User ID cannot be NULL';
    END IF;

    START TRANSACTION;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_candidate_id, v_school_id, v_subcounty_id;
        IF done THEN
            LEAVE read_loop;
        END IF;

        CALL ComputeCandidateGrades(v_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, p_user_id);
        CALL ComputeCandidateResults(v_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, p_user_id);
    END LOOP;
    CLOSE cur;

    CALL log_action('Process All Candidates', p_user_id, 
        CONCAT('Processed all candidates for exam_year_id: ', p_exam_year_id));

    COMMIT;
END //

-- Event: log_backup
CREATE EVENT IF NOT EXISTS log_backup
ON SCHEDULE EVERY 30 MINUTE
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    CALL log_action('Backup Attempt', NULL, CONCAT('Backup initiated at ', NOW()));
END //

DELIMITER ;

-- Sample Data
INSERT INTO districts (district_name, district_code) VALUES ('Luuka', 'LUK001');
INSERT INTO school_types (type) VALUES ('Primary');
INSERT INTO subcounties (district_id, subcounty, constituency) 
    VALUES (1, 'Bukanga', 'Luuka North');
INSERT INTO exam_years (exam_year, status) VALUES (2025, 'Active');
INSERT INTO subjects (name, code) 
    VALUES 
        ('English', 'ENG'),
        ('Mathematics', 'MTC'),
        ('Science', 'SCI'),
        ('Social Studies', 'SST');
INSERT INTO settings (board_name, exam_year_id, grading_scale, contact_email, district_id) 
    VALUES ('Luuka Examination Board', 1, 'Standard', 'info@ludeb.org', 1);
INSERT INTO schools (center_no, school_name, subcounty_id, school_type_id, status) 
    VALUES ('U001', 'Bukanga Primary', 1, 1, 'Active');
INSERT INTO system_users (username, email, password, role) 
    VALUES ('admin', 'admin@ludeb.org', 'hashed_password', 'System Admin');
INSERT INTO grading (range_from, range_to, grade, score) 
    VALUES 
        (80, 100, 'D1', 1),
        (70, 79, 'D2', 2),
        (0, 69, 'F9', 9);
INSERT INTO grades (aggregate_range_from, aggregate_range_to, division, conditions) 
    VALUES 
        (4, 12, 'Division 1', '{"English": {"max_score": 6}, "Math": {"max_score": 8}}'),
        (13, 24, 'Division 2', NULL),
        (25, 32, 'Division 3', NULL),
        (33, 36, 'Division 4', NULL);
INSERT INTO candidates (school_id, index_number, candidate_name, sex, exam_year_id) 
    VALUES (1, 'U001/001/2025', 'John Doe', 'Male', 1);
INSERT INTO subject_candidates (candidate_id, subject_id, exam_year_id) 
    VALUES (1, 1, 1), (1, 2, 1), (1, 3, 1), (1, 4, 1);
INSERT INTO captured_subjects (subject_id, school_id, exam_year_id) 
    VALUES (1, 1, 1), (2, 1, 1), (3, 1, 1), (4, 1, 1);
INSERT INTO daily_targets (target_date, target_entries, exam_year_id) 
    VALUES ('2025-07-28', 100, 1);

SET FOREIGN_KEY_CHECKS = 1;
SET GLOBAL event_scheduler = ON;