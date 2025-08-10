DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `ComputeCandidateGrades`(
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

    
    START TRANSACTION;

    
    DELETE FROM results 
    WHERE candidate_id = p_candidate_id 
    AND exam_year_id = p_exam_year_id;

    
    SET v_aggregate = 0;
    SET v_total_marks = 0;
    SET v_subject_count = 0;

    
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_mark, v_status, v_subject_id;
        IF done THEN
            LEAVE read_loop;
        END IF;

        IF v_status = 'PRESENT' THEN
            
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

    
    IF v_subject_count = 4 THEN
        SELECT id INTO v_division_id
        FROM grades
        WHERE v_aggregate BETWEEN aggregate_range_from AND aggregate_range_to
        LIMIT 1;

        
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

    
    CALL log_action('Compute Grades', p_user_id, CONCAT('Computed grades for candidate ID ', p_candidate_id, ' for exam year ', p_exam_year_id));

    COMMIT;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `ComputeCandidateResults`(
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

    
    START TRANSACTION;

    
    SELECT candidate_name, index_number INTO v_candidate_name, v_index_number
    FROM candidates
    WHERE id = p_candidate_id;

    
    SELECT COUNT(*) INTO v_subject_count
    FROM marks m
    WHERE m.candidate_id = p_candidate_id 
    AND m.exam_year_id = p_exam_year_id
    AND m.status = 'PRESENT'
    AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));

    
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

        
        SELECT id INTO v_division_id
        FROM grades
        WHERE v_aggregate BETWEEN aggregate_range_from AND aggregate_range_to
        LIMIT 1;

        
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

    
    CALL log_action('Compute Results', p_user_id, CONCAT('Computed results for candidate ID ', p_candidate_id, ' for exam year ', p_exam_year_id));

    COMMIT;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `ProcessAllCandidates`(
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_candidate_id INT;
    DECLARE v_school_id INT;
    DECLARE v_subcounty_id INT;
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_exam_year_exists INT;

    DECLARE cur CURSOR FOR
        SELECT c.id, c.school_id, s.subcounty_id
        FROM candidates c
        JOIN schools s ON c.school_id = s.id
        WHERE c.exam_year_id = p_exam_year_id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    
    SELECT COUNT(*) INTO v_exam_year_exists
    FROM exam_years
    WHERE id = p_exam_year_id;

    IF v_exam_year_exists = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid exam_year_id: The specified exam year does not exist.';
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

    
    CALL log_action(
        'Process All Candidates', 
        p_user_id, 
        CONCAT('Processed all candidates for exam year ID ', p_exam_year_id)
    );

    COMMIT;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `log_action`(
    IN p_action VARCHAR(100),
    IN p_user_id INT,
    IN p_details TEXT
)
BEGIN
    INSERT INTO audit_logs (action, user_id, details, created_at)
    VALUES (p_action, p_user_id, p_details, CURRENT_TIMESTAMP);
END$$
DELIMITER ;