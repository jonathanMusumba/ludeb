DELIMITER //

CREATE PROCEDURE ProcessAllCandidateResults (
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_total_candidates INT;

    -- Validate inputs
    IF p_exam_year_id <= 0 OR p_user_id <= 0 THEN
        CALL log_action('ProcessAllCandidateResults Error', p_user_id, CONCAT('Invalid input: exam_year_id=', p_exam_year_id, ', user_id=', p_user_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid input parameters';
    END IF;

    START TRANSACTION;

    -- Count total candidates
    SELECT COUNT(*) INTO v_total_candidates
    FROM candidates
    WHERE exam_year_id = p_exam_year_id;

    -- Process all candidates
    CALL ProcessAllCandidates(p_exam_year_id, p_user_id);

    CALL log_action('ProcessAllCandidateResults Success', p_user_id, CONCAT('Processed ', v_total_candidates, ' candidates for exam_year_id: ', p_exam_year_id));

    COMMIT;
END //

DELIMITER ;