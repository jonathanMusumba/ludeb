-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Table: districts
-- Stores district information for grouping subcounties
CREATE TABLE districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    district_name VARCHAR(100) NOT NULL,
    district_code VARCHAR(20) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_district_code (district_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: school_types
-- Stores types of schools (e.g., Primary, Secondary)
CREATE TABLE school_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: subcounties
-- Stores subcounty information, linked to districts
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
-- Stores examination years with status
CREATE TABLE exam_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_year YEAR NOT NULL UNIQUE,
    status ENUM('Active', 'Not Active') DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_exam_year (exam_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: subjects
-- Stores subject information
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: settings
-- Stores district-wide settings, previously examination_board
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
    FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
    FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE,
    INDEX idx_district_id (district_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: schools
-- Stores school information
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
-- Stores user accounts for the system
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
-- Stores score-to-grade mapping
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
-- Stores aggregate ranges and conditions for divisions
CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aggregate_range_from INT NOT NULL,
    aggregate_range_to INT NOT NULL,
    division VARCHAR(10) NOT NULL,
    conditions TEXT, -- JSON or text for conditions like English<=6, Math<=8
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: candidates
-- Stores candidate information
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
-- Tracks candidates enrolled in subjects
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
-- Tracks subjects captured per school
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
-- Stores individual subject marks for candidates
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
-- Stores processed results with subject-level grades
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
-- Stores candidate aggregates and division
CREATE TABLE candidate_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    school_id INT NOT NULL,
    aggregates VARCHAR(10) NOT NULL, -- Numeric (4-36) or 'X'
    division VARCHAR(10) NOT NULL, -- Division 1-4, Ungraded, or 'X'
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
-- Stores school-level results for an exam year
CREATE TABLE school_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    candidate_id INT NOT NULL,
    candidate_index_number VARCHAR(255) NOT NULL,
    candidate_name VARCHAR(255) NOT NULL,
    subject_code VARCHAR(20) NOT NULL,
    marks INT NOT NULL CHECK (marks >= 0 AND marks <= 100),
    grade VARCHAR(2) NOT NULL, -- D1-D8, F9, or 'X'
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
-- Stores subcounty-level results for an exam year
CREATE TABLE subcounty_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subcounty_id INT NOT NULL,
    candidate_id INT NOT NULL,
    candidate_index_number VARCHAR(255) NOT NULL,
    candidate_name VARCHAR(255) NOT NULL,
    subject_code VARCHAR(20) NOT NULL,
    marks INT NOT NULL CHECK (marks >= 0 AND marks <= 100),
    grade VARCHAR(2) NOT NULL, -- D1-D8, F9, or 'X'
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

-- Create daily_targets table if it doesn't exist
CREATE TABLE IF NOT EXISTS daily_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_date DATE NOT NULL,
    target_entries INT NOT NULL,
    exam_year_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
    UNIQUE (target_date, exam_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add exam_year_id to candidates

-- Add exam_year_id to marks
ALTER TABLE marks
ADD exam_year_id INT NOT NULL,
ADD FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT;

-- Add exam_year_id to candidate_results
ALTER TABLE candidate_results
ADD exam_year_id INT NOT NULL,
ADD FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT;
-- Table: uploads
-- Stores uploaded files (e.g., candidate lists, subcounties)
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

-- Stored Procedure: ComputeCandidateGrades
DELIMITER //

DROP PROCEDURE IF EXISTS ComputeCandidateGrades;
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
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_division_id INT DEFAULT NULL;
    DECLARE v_aggregate INT DEFAULT 0;
    DECLARE v_total_marks INT DEFAULT 0;
    DECLARE v_subject_count INT DEFAULT 0;

    DECLARE cur CURSOR FOR
        SELECT m.mark, m.status, m.subject_id
        FROM marks m
        WHERE m.candidate_id = p_candidate_id 
        AND m.exam_year_id = p_exam_year_id 
        AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- Start transaction
    START TRANSACTION;

    -- Delete existing results for this candidate and exam year
    DELETE FROM results 
    WHERE candidate_id = p_candidate_id 
    AND exam_year_id = p_exam_year_id;

    -- Initialize variables
    SET v_aggregate = 0;
    SET v_total_marks = 0;
    SET v_subject_count = 0;

    -- Process each subject mark
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_mark, v_status, v_subject_id;
        IF done THEN
            LEAVE read_loop;
        END IF;

        IF v_status = 'PRESENT' THEN
            -- Determine score based on mark using grading table
            SELECT score INTO v_score
            FROM grading
            WHERE v_mark BETWEEN range_from AND range_to
            LIMIT 1;

            IF v_score IS NOT NULL THEN
                INSERT INTO results (candidate_id, subject_id, school_id, exam_year_id, mark, score, processed_by)
                VALUES (p_candidate_id, v_subject_id, p_school_id, p_exam_year_id, v_mark, v_score, p_user_id);

                SET v_total_marks = v_total_marks + v_mark;
                SET v_aggregate = v_aggregate + v_score;
                SET v_subject_count = v_subject_count + 1;
            END IF;
        END IF;
    END LOOP;
    CLOSE cur;

    -- Determine division using grades table
    IF v_subject_count = 4 THEN
        SELECT id INTO v_division_id
        FROM grades
        WHERE v_aggregate BETWEEN aggregate_range_from AND aggregate_range_to
        LIMIT 1;

        -- Insert or update candidate results
        INSERT INTO candidate_results (
            candidate_id, school_id, aggregates, division, processed_by
        )
        VALUES (
            p_candidate_id, 
            p_school_id, 
            v_aggregate, 
            COALESCE((SELECT division FROM grades WHERE id = v_division_id), 'Ungraded'),
            p_user_id
        )
        ON DUPLICATE KEY UPDATE
            aggregates = v_aggregate,
            division = COALESCE((SELECT division FROM grades WHERE id = v_division_id), 'Ungraded'),
            updated_at = NOW(),
            updated_by = p_user_id;
    END IF;

    -- Log action
    CALL log_action('Compute Grades', p_user_id, CONCAT('Computed grades for candidate ID ', p_candidate_id, ' for exam year ', p_exam_year_id));

    COMMIT;
END //

DROP PROCEDURE IF EXISTS ComputeCandidateResults;
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
    DECLARE v_division_id INT DEFAULT NULL;
    DECLARE v_subject_count INT DEFAULT 0;
    DECLARE v_candidate_name VARCHAR(255);
    DECLARE v_index_number VARCHAR(255);

    -- Start transaction
    START TRANSACTION;

    -- Get candidate details
    SELECT candidate_name, index_number INTO v_candidate_name, v_index_number
    FROM candidates
    WHERE id = p_candidate_id;

    -- Calculate subject count
    SELECT COUNT(*) INTO v_subject_count
    FROM marks m
    WHERE m.candidate_id = p_candidate_id 
    AND m.exam_year_id = p_exam_year_id
    AND m.status = 'PRESENT'
    AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));

    -- Calculate total marks and aggregate
    IF v_subject_count = 4 THEN
        SELECT 
            COALESCE(SUM(m.mark), 0) INTO v_total_marks
        FROM marks m
        WHERE m.candidate_id = p_candidate_id 
        AND m.exam_year_id = p_exam_year_id
        AND m.status = 'PRESENT'
        AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));

        SELECT 
            COALESCE(SUM(g.score), 0) INTO v_aggregate
        FROM marks m
        JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to
        WHERE m.candidate_id = p_candidate_id 
        AND m.exam_year_id = p_exam_year_id
        AND m.status = 'PRESENT'
        AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));

        -- Determine division
        SELECT id INTO v_division_id
        FROM grades
        WHERE v_aggregate BETWEEN aggregate_range_from AND aggregate_range_to
        LIMIT 1;

        -- Insert or update candidate results
        INSERT INTO candidate_results (
            candidate_id, school_id, aggregates, division, processed_by
        )
        VALUES (
            p_candidate_id, 
            p_school_id, 
            v_aggregate, 
            COALESCE((SELECT division FROM grades WHERE id = v_division_id), 'Ungraded'),
            p_user_id
        )
        ON DUPLICATE KEY UPDATE
            aggregates = v_aggregate,
            division = COALESCE((SELECT division FROM grades WHERE id = v_division_id), 'Ungraded'),
            updated_at = NOW(),
            updated_by = p_user_id;

        -- Update school results
        INSERT INTO school_results (
            school_id, candidate_id, candidate_index_number, candidate_name, 
            subject_code, marks, grade, exam_year_id
        )
        SELECT 
            p_school_id, 
            m.candidate_id, 
            v_index_number, 
            v_candidate_name, 
            s.code, 
            m.mark, 
            g.grade, 
            p_exam_year_id
        FROM marks m
        JOIN subjects s ON m.subject_id = s.id
        JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to
        WHERE m.candidate_id = p_candidate_id 
        AND m.exam_year_id = p_exam_year_id
        AND m.status = 'PRESENT'
        AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'))
        ON DUPLICATE KEY UPDATE
            marks = VALUES(marks),
            grade = VALUES(grade),
            updated_at = NOW();

        -- Update subcounty results
        INSERT INTO subcounty_results (
            subcounty_id, candidate_id, candidate_index_number, candidate_name, 
            subject_code, marks, grade, exam_year_id
        )
        SELECT 
            p_subcounty_id, 
            m.candidate_id, 
            v_index_number, 
            v_candidate_name, 
            s.code, 
            m.mark, 
            g.grade, 
            p_exam_year_id
        FROM marks m
        JOIN subjects s ON m.subject_id = s.id
        JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to
        WHERE m.candidate_id = p_candidate_id 
        AND m.exam_year_id = p_exam_year_id
        AND m.status = 'PRESENT'
        AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'))
        ON DUPLICATE KEY UPDATE
            marks = VALUES(marks),
            grade = VALUES(grade),
            updated_at = NOW();
    END IF;

    -- Log action
    CALL log_action('Compute Results', p_user_id, CONCAT('Computed results for candidate ID ', p_candidate_id, ' for exam year ', p_exam_year_id));

    COMMIT;
END //

DROP PROCEDURE IF EXISTS ProcessAllCandidates;
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
        SELECT c.id, c.school_id, s.subcounty_id
        FROM candidates c
        JOIN schools s ON c.school_id = s.id
        WHERE c.exam_year_id = p_exam_year_id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- Start transaction
    START TRANSACTION;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_candidate_id, v_school_id, v_subcounty_id;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Compute grades and results for each candidate
        CALL ComputeCandidateGrades(v_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, p_user_id);
        CALL ComputeCandidateResults(v_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, p_user_id);
    END LOOP;
    CLOSE cur;

    -- Log action
    CALL log_action('Process All Candidates', p_user_id, CONCAT('Processed all candidates for exam year ', p_exam_year_id));

    COMMIT;
END //

DELIMITER ;