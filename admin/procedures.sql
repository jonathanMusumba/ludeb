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
    DECLARE v_exam_year_exists INT;

    -- Validate exam_year_id
    SELECT COUNT(*) INTO v_exam_year_exists
    FROM exam_years
    WHERE id = p_exam_year_id;
    
    IF v_exam_year_exists = 0 THEN
        CALL log_action('ComputeCandidateGrades Error', p_user_id, CONCAT('Invalid exam_year_id: ', p_exam_year_id, ' for candidate_id: ', p_candidate_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid exam_year_id: The specified exam year does not exist.';
    END IF;

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
            candidate_id, school_id, exam_year_id, aggregates, division, processed_by
        )
        VALUES (
            p_candidate_id, 
            p_school_id, 
            p_exam_year_id,
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
    DECLARE v_exam_year_exists INT;

    -- Validate exam_year_id
    SELECT COUNT(*) INTO v_exam_year_exists
    FROM exam_years
    WHERE id = p_exam_year_id;
    
    IF v_exam_year_exists = 0 THEN
        CALL log_action('ComputeCandidateResults Error', p_user_id, CONCAT('Invalid exam_year_id: ', p_exam_year_id, ' for candidate_id: ', p_candidate_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid exam_year_id: The specified exam year does not exist.';
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
            candidate_id, school_id, exam_year_id, aggregates, division, processed_by
        )
        VALUES (
            p_candidate_id, 
            p_school_id, 
            p_exam_year_id,
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

DELIMITER ;