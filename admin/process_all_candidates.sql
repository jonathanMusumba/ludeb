DELIMITER //

CREATE PROCEDURE ProcessAllCandidates (
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

    -- Validate exam_year_id
    SELECT COUNT(*) INTO v_exam_year_exists
    FROM exam_years
    WHERE id = p_exam_year_id;
    IF v_exam_year_exists = 0 THEN
        CALL log_action('ProcessAllCandidates Error', p_user_id, CONCAT('Invalid exam_year_id: ', p_exam_year_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid exam_year_id';
    END IF;

    START TRANSACTION;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_candidate_id, v_school_id, v_subcounty_id;
        IF done THEN
            LEAVE read_loop;
        END IF;

        CALL ComputeCandidateResults(v_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, p_user_id);
    END LOOP;
    CLOSE cur;

    CALL log_action('ProcessAllCandidates', p_user_id, CONCAT('Processed all candidates for exam year ID ', p_exam_year_id));

    COMMIT;
END //

DELIMITER ;