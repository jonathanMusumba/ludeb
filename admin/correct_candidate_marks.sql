DELIMITER //

CREATE PROCEDURE correct_candidate_marks (
    IN p_candidate_id INT,
    IN p_subject_id INT,
    IN p_exam_year_id INT,
    IN p_new_mark INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_score INT;
    DECLARE v_grade VARCHAR(2);
    DECLARE v_school_id INT;
    DECLARE v_subcounty_id INT;

    START TRANSACTION;

    -- Validate new mark
    IF p_new_mark < 0 OR p_new_mark > 100 THEN
        CALL log_action('CorrectCandidateMarks Error', p_user_id, CONCAT('Invalid mark: ', p_new_mark, ' for candidate_id: ', p_candidate_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mark must be between 0 and 100';
    END IF;

    -- Get school_id and subcounty_id
    SELECT c.school_id, s.subcounty_id INTO v_school_id, v_subcounty_id
    FROM candidates c
    JOIN schools s ON c.school_id = s.id
    WHERE c.id = p_candidate_id AND c.exam_year_id = p_exam_year_id
    LIMIT 1;

    IF v_school_id IS NULL THEN
        CALL log_action('CorrectCandidateMarks Error', p_user_id, CONCAT('Invalid candidate_id or exam_year_id: ', p_candidate_id, ', ', p_exam_year_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid candidate or exam year';
    END IF;

    -- Get grade and score
    SELECT grade, score INTO v_grade, v_score
    FROM grading
    WHERE p_new_mark BETWEEN range_from AND range_to
    AND (exam_year_id = p_exam_year_id OR exam_year_id IS NULL)
    AND (subject_id = p_subject_id OR subject_id IS NULL)
    ORDER BY exam_year_id DESC, subject_id DESC, range_from
    LIMIT 1;

    IF v_grade IS NULL THEN
        SET v_grade = 'F';
        SET v_score = 9;
    END IF;

    -- Update or insert into marks
    INSERT INTO marks (candidate_id, subject_id, school_id, exam_year_id, mark, status, submitted_by, edited_by)
    VALUES (p_candidate_id, p_subject_id, v_school_id, p_exam_year_id, p_new_mark, 'PRESENT', p_user_id, p_user_id)
    ON DUPLICATE KEY UPDATE
        mark = p_new_mark,
        status = 'PRESENT',
        edited_by = p_user_id,
        updated_at = CURRENT_TIMESTAMP;

    -- Update results and related tables
    CALL ComputeCandidateResults(p_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, p_user_id);

    CALL log_action('CorrectCandidateMarks', p_user_id, CONCAT('Corrected mark for candidate_id: ', p_candidate_id, ', subject_id: ', p_subject_id, ', exam_year_id: ', p_exam_year_id));

    COMMIT;
END //

DELIMITER ;