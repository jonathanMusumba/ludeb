DELIMITER //

DROP PROCEDURE IF EXISTS log_action;
CREATE PROCEDURE log_action (
    IN p_action VARCHAR(100),
    IN p_user_id INT,
    IN p_details TEXT
)
BEGIN
    INSERT INTO audit_logs (action, user_id, details, created_at)
    VALUES (p_action, p_user_id, p_details, CURRENT_TIMESTAMP);
END //

DROP PROCEDURE IF EXISTS ComputeCandidateGrades;
DROP PROCEDURE IF EXISTS ComputeCandidateResults;
DROP PROCEDURE IF EXISTS ProcessAllCandidates;
CREATE PROCEDURE ProcessAllCandidateResults (
    IN p_exam_year_id INT,
    IN p_processed_by INT
)
BEGIN
    DECLARE v_error_msg VARCHAR(255);
    DECLARE v_total_candidates INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION 
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
        CALL log_action('ProcessAllCandidateResults Error', p_processed_by, CONCAT('Error processing exam_year_id: ', p_exam_year_id, ', Error: ', v_error_msg));
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'An error occurred during batch processing';
    END;

    -- Validate inputs
    IF p_exam_year_id <= 0 OR p_processed_by <= 0 THEN
        CALL log_action('ProcessAllCandidateResults Invalid Input', p_processed_by, CONCAT('Invalid input: exam_year_id=', p_exam_year_id, ', processed_by=', p_processed_by));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid input parameters';
    END IF;

    START TRANSACTION;

    -- Create temporary table for candidate processing
    CREATE TEMPORARY TABLE temp_candidate_results (
        candidate_id INT,
        school_id INT,
        subcounty_id INT,
        candidate_name VARCHAR(255),
        candidate_index_number VARCHAR(255),
        subject_id INT,
        subject_code VARCHAR(20),
        mark INT,
        status ENUM('PRESENT', 'ABSENT'),
        grade VARCHAR(2),
        score INT,
        PRIMARY KEY (candidate_id, subject_id)
    ) ENGINE=MEMORY;

    -- Populate temporary table
    INSERT INTO temp_candidate_results (candidate_id, school_id, subcounty_id, candidate_name, candidate_index_number, subject_id, subject_code, mark, status)
    SELECT c.id, c.school_id, s.subcounty_id, c.candidate_name, c.index_number, 
           s2.id, s2.code, COALESCE(m.mark, 0), COALESCE(m.status, 'ABSENT')
    FROM candidates c
    JOIN schools s ON c.school_id = s.id
    JOIN subjects s2 ON s2.code IN ('ENG', 'MTC', 'SCI', 'SST')
    LEFT JOIN marks m ON m.candidate_id = c.id AND m.subject_id = s2.id AND m.school_id = c.school_id AND m.exam_year_id = p_exam_year_id
    JOIN subject_candidates sc ON sc.candidate_id = c.id AND sc.subject_id = s2.id
    WHERE c.exam_year_id = p_exam_year_id;

    -- Count total candidates
    SELECT COUNT(DISTINCT candidate_id) INTO v_total_candidates
    FROM temp_candidate_results;

    -- Compute grades and scores
    UPDATE temp_candidate_results t
    LEFT JOIN grading g ON t.mark BETWEEN g.range_from AND g.range_to
    SET t.grade = CASE 
        WHEN t.status = 'ABSENT' THEN 'X'
        WHEN g.grade IS NULL THEN 'X'
        ELSE g.grade
    END,
    t.score = CASE 
        WHEN t.status = 'ABSENT' THEN 9
        WHEN g.score IS NULL THEN 9
        ELSE g.score
    END;

    -- Log grading errors
    CALL log_action('ProcessAllCandidateResults Grading Error', p_processed_by, 
        CONCAT('No grade found for marks: ', GROUP_CONCAT(CONCAT('candidate_id: ', t.candidate_id, ', subject: ', t.subject_code, ', mark: ', t.mark) SEPARATOR '; '))
    )
    FROM temp_candidate_results t
    WHERE t.grade = 'X' AND t.status = 'PRESENT';

    -- Insert into results
    INSERT INTO results (candidate_id, subject_id, school_id, mark, score, processed_by, exam_year_id)
    SELECT t.candidate_id, t.subject_id, t.school_id, t.mark, t.score, p_processed_by, p_exam_year_id
    FROM temp_candidate_results t
    ON DUPLICATE KEY UPDATE
        mark = t.mark,
        score = t.score,
        updated_by = p_processed_by,
        updated_at = CURRENT_TIMESTAMP,
        exam_year_id = p_exam_year_id;

    -- Insert into school_results
    INSERT INTO school_results (candidate_id, school_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
    SELECT t.candidate_id, t.school_id, t.candidate_index_number, t.candidate_name, t.subject_code, t.mark, t.grade, p_exam_year_id
    FROM temp_candidate_results t
    ON DUPLICATE KEY UPDATE
        marks = t.mark,
        grade = t.grade,
        updated_at = CURRENT_TIMESTAMP;

    -- Insert into subcounty_results
    INSERT INTO subcounty_results (candidate_id, subcounty_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
    SELECT t.candidate_id, t.subcounty_id, t.candidate_index_number, t.candidate_name, t.subject_code, t.mark, t.grade, p_exam_year_id
    FROM temp_candidate_results t
    ON DUPLICATE KEY UPDATE
        marks = t.mark,
        grade = t.grade,
        updated_at = CURRENT_TIMESTAMP;

    -- Create temporary table for aggregates
    CREATE TEMPORARY TABLE temp_aggregates (
        candidate_id INT PRIMARY KEY,
        school_id INT,
        aggregates VARCHAR(10),
        division VARCHAR(10),
        present_count INT,
        pass_count INT,
        english_score INT,
        math_score INT
    ) ENGINE=MEMORY;

    -- Compute aggregates
    INSERT INTO temp_aggregates (candidate_id, school_id, aggregates, present_count, pass_count, english_score, math_score)
    SELECT 
        t.candidate_id, 
        t.school_id,
        SUM(CASE WHEN t.status = 'PRESENT' THEN t.score ELSE 0 END),
        SUM(CASE WHEN t.status = 'PRESENT' THEN 1 ELSE 0 END),
        SUM(CASE WHEN t.status = 'PRESENT' AND t.score <= 8 THEN 1 ELSE 0 END),
        MAX(CASE WHEN t.subject_code = 'ENG' THEN t.score ELSE 9 END),
        MAX(CASE WHEN t.subject_code = 'MTC' THEN t.score ELSE 9 END)
    FROM temp_candidate_results t
    GROUP BY t.candidate_id, t.school_id;

    -- Determine divisions
    UPDATE temp_aggregates ta
    SET ta.division = CASE
        WHEN ta.present_count != 4 THEN 'X'
        ELSE COALESCE((
            SELECT g.division
            FROM grades g
            WHERE ta.aggregates BETWEEN g.aggregate_range_from AND g.aggregate_range_to
            LIMIT 1
        ), 'X')
    END,
    ta.aggregates = CASE
        WHEN ta.present_count != 4 THEN 'X'
        ELSE ta.aggregates
    END;

    -- Apply special division rules
    UPDATE temp_aggregates
    SET division = CASE
        WHEN aggregates BETWEEN 4 AND 12 AND (english_score = 9 OR math_score = 9) THEN 'Division 2'
        WHEN aggregates BETWEEN 13 AND 23 AND english_score = 9 AND math_score = 9 THEN 'Division 3'
        WHEN aggregates = 29 AND pass_count >= 3 AND (english_score <= 8 OR math_score <= 8) THEN 'Division 3'
        WHEN aggregates = 33 AND pass_count >= 2 AND (english_score <= 8 OR math_score <= 8) THEN 'Division 4'
        WHEN aggregates BETWEEN 33 AND 36 AND pass_count <= 1 THEN 'Ungraded'
        ELSE division
    END
    WHERE present_count = 4;

    -- Log division errors
    CALL log_action('ProcessAllCandidateResults Division Error', p_processed_by, 
        CONCAT('No division found for aggregates: ', GROUP_CONCAT(CONCAT('candidate_id: ', ta.candidate_id, ', aggregate: ', ta.aggregates) SEPARATOR '; '))
    )
    FROM temp_aggregates ta
    WHERE ta.division = 'X' AND ta.present_count = 4;

    -- Insert into candidate_results
    INSERT INTO candidate_results (candidate_id, school_id, aggregates, division, processed_by, exam_year_id)
    SELECT ta.candidate_id, ta.school_id, ta.aggregates, ta.division, p_processed_by, p_exam_year_id
    FROM temp_aggregates ta
    ON DUPLICATE KEY UPDATE
        aggregates = ta.aggregates,
        division = ta.division,
        updated_by = p_processed_by,
        updated_at = CURRENT_TIMESTAMP,
        exam_year_id = p_exam_year_id;

    -- Handle candidates with fewer than 4 subjects or not enrolled
    INSERT INTO candidate_results (candidate_id, school_id, aggregates, division, processed_by, exam_year_id)
    SELECT c.id, c.school_id, 'X', 'X', p_processed_by, p_exam_year_id
    FROM candidates c
    LEFT JOIN (
        SELECT candidate_id, COUNT(*) AS subject_count
        FROM marks
        WHERE status = 'PRESENT' AND exam_year_id = p_exam_year_id
        GROUP BY candidate_id
        HAVING subject_count = 4
    ) m ON c.id = m.candidate_id
    LEFT JOIN (
        SELECT candidate_id, COUNT(*) AS enrolled_count
        FROM subject_candidates
        WHERE subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'))
        GROUP BY candidate_id
        HAVING enrolled_count = 4
    ) sc ON c.id = sc.candidate_id
    WHERE c.exam_year_id = p_exam_year_id
    AND (m.candidate_id IS NULL OR sc.candidate_id IS NULL)
    ON DUPLICATE KEY UPDATE
        aggregates = 'X',
        division = 'X',
        updated_by = p_processed_by,
        updated_at = CURRENT_TIMESTAMP,
        exam_year_id = p_exam_year_id;

    -- Drop temporary tables
    DROP TEMPORARY TABLE IF EXISTS temp_candidate_results;
    DROP TEMPORARY TABLE IF EXISTS temp_aggregates;

    -- Log success
    CALL log_action('ProcessAllCandidateResults Success', p_processed_by, CONCAT('Processed ', v_total_candidates, ' candidates for exam_year_id: ', p_exam_year_id));

    COMMIT;
END //

DELIMITER ;