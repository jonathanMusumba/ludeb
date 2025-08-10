DELIMITER //

-- Updating the results_status column in schools table
CREATE OR REPLACE PROCEDURE UpdateSchoolResultsStatus (
    IN p_school_id INT,
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_total_candidates INT;
    DECLARE v_candidates_with_marks INT;
    DECLARE v_candidates_with_four_subjects INT;
    DECLARE v_status ENUM('Not Declared', 'Partially Declared', 'Declared');

    -- Get total candidates for the school and exam year
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

    -- Update the schools table
    UPDATE schools
    SET results_status = v_status
    WHERE id = p_school_id;

    -- Log the action
    CALL log_action('UpdateSchoolResultsStatus', p_user_id, 
        CONCAT('Updated results_status to ', v_status, 
               ' for school_id: ', p_school_id, 
               ', exam_year_id: ', p_exam_year_id, 
               ', total_candidates: ', v_total_candidates, 
               ', candidates_with_marks: ', v_candidates_with_marks, 
               ', candidates_with_four_subjects: ', v_candidates_with_four_subjects));
END //

DELIMITER ;