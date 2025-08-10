DELIMITER //

-- Ensure audit_logs table exists
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    user_id INT NULL,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE SET NULL,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure log_action procedure exists
DROP PROCEDURE IF EXISTS log_action;
CREATE PROCEDURE log_action (
    IN p_action VARCHAR(255),
    IN p_user_id INT,
    IN p_details TEXT
)
BEGIN
    INSERT INTO audit_logs (action, user_id, details)
    VALUES (p_action, p_user_id, p_details);
END //

-- Fix NULL or invalid submitted_by in marks
UPDATE marks 
SET submitted_by = (SELECT id FROM system_users WHERE role = 'System Admin' LIMIT 1)
WHERE submitted_by IS NULL 
   OR submitted_by NOT IN (SELECT id FROM system_users);

-- Procedure to update missing results
DROP PROCEDURE IF EXISTS UpdateMissingResults;
CREATE PROCEDURE UpdateMissingResults (
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_candidate_id INT;
    DECLARE v_school_id INT;
    DECLARE v_subcounty_id INT;
    DECLARE v_valid_user_id INT;
    DECLARE v_subject_id INT;
    DECLARE v_mark INT;
    DECLARE v_status ENUM('PRESENT', 'ABSENT');
    DECLARE v_submitted_by INT;
    DECLARE done INT DEFAULT FALSE;

    -- Cursor to find all marks not in results
    DECLARE cur CURSOR FOR
        SELECT DISTINCT m.candidate_id, m.school_id, s.subcounty_id, m.subject_id, m.mark, m.status, m.submitted_by
        FROM marks m
        JOIN schools s ON m.school_id = s.id
        LEFT JOIN results r ON m.candidate_id = r.candidate_id 
            AND m.subject_id = r.subject_id 
            AND m.exam_year_id = r.exam_year_id
        WHERE m.exam_year_id = p_exam_year_id
        AND r.id IS NULL;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- Validate p_user_id
    IF p_user_id IS NULL THEN
        SELECT id INTO v_valid_user_id
        FROM system_users
        WHERE role = 'System Admin'
        LIMIT 1;
        IF v_valid_user_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No valid user_id provided and no default System Admin found';
        END IF;
    ELSE
        SET v_valid_user_id = p_user_id;
    END IF;

    -- Start transaction
    START TRANSACTION;

    -- Open cursor to process missing candidates
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_candidate_id, v_school_id, v_subcounty_id, v_subject_id, v_mark, v_status, v_submitted_by;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Validate data
        IF v_submitted_by IS NULL OR v_submitted_by NOT IN (SELECT id FROM system_users) THEN
            SET v_submitted_by = v_valid_user_id;
            CALL log_action('Invalid Submitted By', v_valid_user_id, 
                CONCAT('Invalid or NULL submitted_by for candidate_id ', v_candidate_id, 
                       ', subject_id ', v_subject_id, ', exam_year_id ', p_exam_year_id));
        END IF;

        IF v_candidate_id NOT IN (SELECT id FROM candidates) OR 
           v_subject_id NOT IN (SELECT id FROM subjects) OR 
           v_school_id NOT IN (SELECT id FROM schools) THEN
            CALL log_action('Invalid Data', v_valid_user_id, 
                CONCAT('Invalid candidate_id ', v_candidate_id, 
                       ', subject_id ', v_subject_id, 
                       ', school_id ', v_school_id, 
                       ' for exam_year_id ', p_exam_year_id));
            CONTINUE;
        END IF;

        -- Process grades and results for missing candidates
        CALL ComputeCandidateGrades(v_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, v_valid_user_id);
        CALL ComputeCandidateResults(v_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, v_valid_user_id);
    END LOOP;
    CLOSE cur;

    -- Log action
    CALL log_action('Update Missing Results', v_valid_user_id, 
        CONCAT('Processed missing results for exam year ', p_exam_year_id));

    COMMIT;
END //

-- Updated ComputeCandidateGrades
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
    DECLARE v_submitted_by INT;
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_division_id INT DEFAULT NULL;
    DECLARE v_aggregate INT DEFAULT 0;
    DECLARE v_total_marks INT DEFAULT 0;
    DECLARE v_subject_count INT DEFAULT 0;

    DECLARE cur CURSOR FOR
        SELECT m.mark, m.status, m.subject_id, m.submitted_by
        FROM marks m
        WHERE m.candidate_id = p_candidate_id 
        AND m.exam_year_id = p_exam_year_id 
        AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- Validate p_user_id
    IF p_user_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'User ID cannot be NULL';
    END IF;

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
        FETCH cur INTO v_mark, v_status, v_subject_id, v_submitted_by;
        IF done THEN
            LEAVE read_loop;
        END IF;

        IF v_status = 'PRESENT' THEN
            -- Validate submitted_by
            IF v_submitted_by IS NULL OR v_submitted_by NOT IN (SELECT id FROM system_users) THEN
                SET v_submitted_by = p_user_id;
                CALL log_action('Invalid Submitted By', p_user_id, 
                    CONCAT('Using p_user_id for submitted_by for candidate_id ', p_candidate_id, 
                           ', subject_id ', v_subject_id, ', exam_year_id ', p_exam_year_id));
            END IF;

            -- Determine score based on mark using grading table
            SELECT score INTO v_score
            FROM grading
            WHERE v_mark BETWEEN range_from AND range_to
            LIMIT 1;

            IF v_score IS NOT NULL THEN
                INSERT INTO results (candidate_id, subject_id, school_id, exam_year_id, mark, score, processed_by)
                VALUES (p_candidate_id, v_subject_id, p_school_id, p_exam_year_id, v_mark, v_score, v_submitted_by)
                ON DUPLICATE KEY UPDATE
                    mark = v_mark,
                    score = v_score,
                    processed_by = v_submitted_by,
                    updated_by = p_user_id,
                    updated_at = NOW();

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
    CALL log_action('Compute Grades', p_user_id, 
        CONCAT('Computed grades for candidate_id ', p_candidate_id, ' for exam_year_id ', p_exam_year_id));

    COMMIT;
END //

-- Updated ComputeCandidateResults
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

    -- Validate p_user_id
    IF p_user_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'User ID cannot be NULL';
    END IF;

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
    CALL log_action('Compute Results', p_user_id, 
        CONCAT('Computed results for candidate_id ', p_candidate_id, ' for exam_year_id ', p_exam_year_id));

    COMMIT;
END //

-- ProcessAllCandidates
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

    -- Validate p_user_id
    IF p_user_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'User ID cannot be NULL';
    END IF;

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
    CALL log_action('Process All Candidates', p_user_id, 
        CONCAT('Processed all candidates for exam year ', p_exam_year_id));

    COMMIT;
END //

DELIMITER ;

-- Execute the update for missing results
CALL UpdateMissingResults(1, (SELECT id FROM system_users WHERE username = 'dataentrant' LIMIT 1));