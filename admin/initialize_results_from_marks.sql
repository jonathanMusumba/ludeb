DELIMITER //

CREATE PROCEDURE initialize_results_from_marks (
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_candidate_id INT;
    DECLARE v_subject_id INT;
    DECLARE v_school_id INT;
    DECLARE v_subcounty_id INT;
    DECLARE v_mark INT;
    DECLARE v_score INT;
    DECLARE v_grade VARCHAR(2);
    DECLARE v_candidate_name VARCHAR(255);
    DECLARE v_index_number VARCHAR(255);
    DECLARE cur CURSOR FOR 
        SELECT m.candidate_id, m.subject_id, m.school_id, s.subcounty_id, m.mark, c.candidate_name, c.index_number
        FROM marks m
        JOIN candidates c ON m.candidate_id = c.id
        JOIN schools s ON m.school_id = s.id
        WHERE m.exam_year_id = p_exam_year_id AND m.status = 'PRESENT';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    START TRANSACTION;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_candidate_id, v_subject_id, v_school_id, v_subcounty_id, v_mark, v_candidate_name, v_index_number;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Get grade and score
        SELECT grade, score INTO v_grade, v_score
        FROM grading
        WHERE v_mark BETWEEN range_from AND range_to
        AND (exam_year_id = p_exam_year_id OR exam_year_id IS NULL)
        AND (subject_id = v_subject_id OR subject_id IS NULL)
        ORDER BY exam_year_id DESC, subject_id DESC, range_from
        LIMIT 1;

        IF v_grade IS NULL THEN
            SET v_grade = 'F';
            SET v_score = 9;
        END IF;

        -- Insert into results
        INSERT INTO results (candidate_id, subject_id, school_id, exam_year_id, mark, score, processed_by)
        VALUES (v_candidate_id, v_subject_id, v_school_id, p_exam_year_id, v_mark, v_score, p_user_id)
        ON DUPLICATE KEY UPDATE
            mark = v_mark,
            score = v_score,
            updated_by = p_user_id,
            updated_at = CURRENT_TIMESTAMP;

        -- Insert into school_results
        INSERT INTO school_results (school_id, candidate_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
        SELECT v_school_id, v_candidate_id, v_index_number, v_candidate_name, s.code, v_mark, v_grade, p_exam_year_id
        FROM subjects s
        WHERE s.id = v_subject_id
        ON DUPLICATE KEY UPDATE
            marks = v_mark,
            grade = v_grade,
            updated_at = CURRENT_TIMESTAMP;

        -- Insert into subcounty_results
        INSERT INTO subcounty_results (subcounty_id, candidate_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
        SELECT v_subcounty_id, v_candidate_id, v_index_number, v_candidate_name, s.code, v_mark, v_grade, p_exam_year_id
        FROM subjects s
        WHERE s.id = v_subject_id
        ON DUPLICATE KEY UPDATE
            marks = v_mark,
            grade = v_grade,
            updated_at = CURRENT_TIMESTAMP;
    END LOOP;
    CLOSE cur;

    -- Update candidate_results
    CALL update_results_after_grading_change(p_exam_year_id, p_user_id);

    CALL log_action('InitializeResultsFromMarks', p_user_id, CONCAT('Initialized results for exam year ID ', p_exam_year_id));

    COMMIT;
END //

DELIMITER ;