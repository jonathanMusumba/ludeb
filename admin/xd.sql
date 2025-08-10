DELIMITER //

DROP PROCEDURE IF EXISTS UpdateSchoolResultsStatus//

CREATE PROCEDURE UpdateSchoolResultsStatus (
    IN p_school_id INT,
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_total_candidates INT DEFAULT 0;
    DECLARE v_candidates_with_marks INT DEFAULT 0;
    DECLARE v_candidates_with_four_subjects INT DEFAULT 0;
    DECLARE v_status ENUM('Not Declared', 'Partially Declared', 'Declared') DEFAULT 'Not Declared';
    DECLARE v_error_message VARCHAR(255);

    -- Start transaction
    START TRANSACTION;

    -- Validate inputs
    IF p_school_id IS NULL OR p_school_id <= 0 THEN
        SET v_error_message = CONCAT('Invalid school_id: ', COALESCE(p_school_id, 'NULL'));
        INSERT INTO audit_logs (action, user_id, details, created_at)
        VALUES ('UpdateSchoolResultsStatus Error', p_user_id, v_error_message, CURRENT_TIMESTAMP);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
    END IF;

    IF p_exam_year_id IS NULL OR p_exam_year_id <= 0 THEN
        SET v_error_message = CONCAT('Invalid exam_year_id: ', COALESCE(p_exam_year_id, 'NULL'));
        INSERT INTO audit_logs (action, user_id, details, created_at)
        VALUES ('UpdateSchoolResultsStatus Error', p_user_id, v_error_message, CURRENT_TIMESTAMP);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM schools WHERE id = p_school_id) THEN
        SET v_error_message = CONCAT('School not found for school_id: ', p_school_id);
        INSERT INTO audit_logs (action, user_id, details, created_at)
        VALUES ('UpdateSchoolResultsStatus Error', p_user_id, v_error_message, CURRENT_TIMESTAMP);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM exam_years WHERE id = p_exam_year_id AND status = 'Active') THEN
        SET v_error_message = CONCAT('Invalid or inactive exam_year_id: ', p_exam_year_id);
        INSERT INTO audit_logs (action, user_id, details, created_at)
        VALUES ('UpdateSchoolResultsStatus Error', p_user_id, v_error_message, CURRENT_TIMESTAMP);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
    END IF;

    -- Get total candidates
    SELECT COUNT(*) INTO v_total_candidates
    FROM candidates
    WHERE school_id = p_school_id AND exam_year_id = p_exam_year_id;

    -- Get candidates with any marks
    SELECT COUNT(DISTINCT m.candidate_id) INTO v_candidates_with_marks
    FROM marks m
    WHERE m.school_id = p_school_id
    AND m.exam_year_id = p_exam_year_id
    AND m.status = 'PRESENT';

    -- Get candidates with marks in all four subjects
    SELECT COUNT(*) INTO v_candidates_with_four_subjects
    FROM (
        SELECT m.candidate_id
        FROM marks m
        WHERE m.school_id = p_school_id
        AND m.exam_year_id = p_exam_year_id
        AND m.status = 'PRESENT'
        GROUP BY m.candidate_id
        HAVING COUNT(DISTINCT m.subject_id) = 4
    ) AS full_marks;

    -- Determine results_status
    IF v_total_candidates = 0 OR v_candidates_with_marks = 0 THEN
        SET v_status = 'Not Declared';
    ELSEIF v_candidates_with_four_subjects > (v_total_candidates * 0.5) THEN
        SET v_status = 'Declared';
    ELSE
        SET v_status = 'Partially Declared';
    END IF;

    -- Update schools table
    UPDATE schools
    SET results_status = v_status
    WHERE id = p_school_id;

    -- Log the action
    INSERT INTO audit_logs (action, user_id, details, created_at)
    VALUES (
        'UpdateSchoolResultsStatus',
        p_user_id,
        CONCAT('Updated results_status to ', v_status, 
               ' for school_id: ', p_school_id, 
               ', exam_year_id: ', p_exam_year_id, 
               ', total_candidates: ', v_total_candidates, 
               ', candidates_with_marks: ', v_candidates_with_marks, 
               ', candidates_with_four_subjects: ', v_candidates_with_four_subjects),
        CURRENT_TIMESTAMP
    );

    COMMIT;
END//

DELIMITER ;