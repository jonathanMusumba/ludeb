DELIMITER //

CREATE PROCEDURE update_all_results (
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_exam_year_exists INT;

    -- Validate exam_year_id
    SELECT COUNT(*) INTO v_exam_year_exists
    FROM exam_years
    WHERE id = p_exam_year_id;
    IF v_exam_year_exists = 0 THEN
        CALL log_action('UpdateAllResults Error', p_user_id, CONCAT('Invalid exam_year_id: ', p_exam_year_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid exam_year_id';
    END IF;

    START TRANSACTION;

    -- Clear existing results
    DELETE FROM results WHERE exam_year_id = p_exam_year_id;
    DELETE FROM candidate_results WHERE exam_year_id = p_exam_year_id;
    DELETE FROM school_results WHERE exam_year_id = p_exam_year_id;
    DELETE FROM subcounty_results WHERE exam_year_id = p_exam_year_id;

    -- Reinitialize results
    CALL initialize_results_from_marks(p_exam_year_id, p_user_id);

    CALL log_action('UpdateAllResults', p_user_id, CONCAT('Recalculated all results for exam year ID ', p_exam_year_id));

    COMMIT;
END //

DELIMITER ;