DELIMITER //

CREATE PROCEDURE ComputeCandidateResults (
    IN p_candidate_id INT,
    IN p_school_id INT,
    IN p_subcounty_id INT,
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_mark INT;
    DECLARE v_status ENUM('PRESENT', 'ABSENT');
    DECLARE v_subject_id INT;
    DECLARE v_subject_code VARCHAR(20);
    DECLARE v_score INT;
    DECLARE v_grade VARCHAR(2);
    DECLARE v_total_aggregate INT DEFAULT 0;
    DECLARE v_subject_count INT DEFAULT 0;
    DECLARE v_pass_count INT DEFAULT 0;
    DECLARE v_english_score INT DEFAULT 9;
    DECLARE v_math_score INT DEFAULT 9;
    DECLARE v_candidate_name VARCHAR(255);
    DECLARE v_index_number VARCHAR(255);
    DECLARE v_exam_year_exists INT;
    DECLARE v_division VARCHAR(10);
    DECLARE v_conditions JSON;
    DECLARE v_must_pass_all BOOLEAN;
    DECLARE v_min_pass_subjects INT;
    DECLARE v_max_pass_subjects INT;
    DECLARE v_must_include JSON;
    DECLARE v_must_include_condition VARCHAR(20);
    DECLARE v_exceptions JSON;
    DECLARE v_exception_count INT;
    DECLARE v_exception_index INT DEFAULT 0;
    DECLARE v_exception_subject VARCHAR(20);
    DECLARE v_exception_grade INT;
    DECLARE v_demote_to VARCHAR(10);
    DECLARE done INT DEFAULT FALSE;
    DECLARE cur CURSOR FOR
        SELECT m.mark, m.status, m.subject_id, s.code
        FROM marks m
        JOIN subjects s ON m.subject_id = s.id
        WHERE m.candidate_id = p_candidate_id 
        AND m.exam_year_id = p_exam_year_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- Validate inputs
    SELECT COUNT(*) INTO v_exam_year_exists
    FROM exam_years
    WHERE id = p_exam_year_id;
    IF v_exam_year_exists = 0 THEN
        CALL log_action('ComputeCandidateResults Error', p_user_id, CONCAT('Invalid exam_year_id: ', p_exam_year_id, ' for candidate_id: ', p_candidate_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid exam_year_id';
    END IF;

    -- Get candidate details
    SELECT candidate_name, index_number INTO v_candidate_name, v_index_number
    FROM candidates
    WHERE id = p_candidate_id AND exam_year_id = p_exam_year_id;
    IF v_candidate_name IS NULL THEN
        CALL log_action('ComputeCandidateResults Error', p_user_id, CONCAT('Invalid candidate_id: ', p_candidate_id, ' for exam_year_id: ', p_exam_year_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid candidate_id';
    END IF;

    START TRANSACTION;

    -- Clear existing results
    DELETE FROM results 
    WHERE candidate_id = p_candidate_id AND exam_year_id = p_exam_year_id;

    -- Process marks
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_mark, v_status, v_subject_id, v_subject_code;
        IF done THEN
            LEAVE read_loop;
        END IF;

        IF v_status = 'PRESENT' THEN
            -- Get score and grade
            SELECT score, grade INTO v_score, v_grade
            FROM grading
            WHERE v_mark BETWEEN range_from AND range_to
            AND (exam_year_id = p_exam_year_id OR exam_year_id IS NULL)
            AND (subject_id = v_subject_id OR subject_id IS NULL)
            ORDER BY exam_year_id DESC, subject_id DESC, range_from
            LIMIT 1;

            IF v_score IS NULL THEN
                SET v_score = 9;
                SET v_grade = 'F';
            END IF;

            -- Track English and Math scores
            IF v_subject_code = 'ENG' THEN
                SET v_english_score = v_score;
            END IF;
            IF v_subject_code = 'MTC' THEN
                SET v_math_score = v_score;
            END IF;

            -- Insert into results
            INSERT INTO results (candidate_id, subject_id, school_id, exam_year_id, mark, score, processed_by)
            VALUES (p_candidate_id, v_subject_id, p_school_id, p_exam_year_id, v_mark, v_score, p_user_id);

            SET v_total_aggregate = v_total_aggregate + v_score;
            SET v_subject_count = v_subject_count + 1;
            IF v_score <= 8 THEN
                SET v_pass_count = v_pass_count + 1;
            END IF;

            -- Insert into school_results
            INSERT INTO school_results (school_id, candidate_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
            VALUES (p_school_id, p_candidate_id, v_index_number, v_candidate_name, v_subject_code, v_mark, v_grade, p_exam_year_id)
            ON DUPLICATE KEY UPDATE
                marks = v_mark,
                grade = v_grade,
                updated_at = CURRENT_TIMESTAMP;

            -- Insert into subcounty_results
            INSERT INTO subcounty_results (subcounty_id, candidate_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
            VALUES (p_subcounty_id, p_candidate_id, v_index_number, v_candidate_name, v_subject_code, v_mark, v_grade, p_exam_year_id)
            ON DUPLICATE KEY UPDATE
                marks = v_mark,
                grade = v_grade,
                updated_at = CURRENT_TIMESTAMP;
        END IF;
    END LOOP;
    CLOSE cur;

    -- Calculate division
    IF v_subject_count > 0 THEN
        SET v_division = 'U';
        SET @division_id = NULL;

        -- Find matching grade rule
        SELECT id, division, conditions INTO @division_id, v_division, v_conditions
        FROM grades
        WHERE v_total_aggregate BETWEEN aggregate_range_from AND aggregate_range_to
        AND (exam_year_id = p_exam_year_id OR exam_year_id IS NULL)
        ORDER BY exam_year_id DESC, aggregate_range_from
        LIMIT 1;

        IF v_conditions IS NOT NULL THEN
            SET v_must_pass_all = JSON_UNQUOTE(JSON_EXTRACT(v_conditions, '$.must_pass_all'));
            SET v_min_pass_subjects = JSON_UNQUOTE(JSON_EXTRACT(v_conditions, '$.min_pass_subjects'));
            SET v_max_pass_subjects = JSON_UNQUOTE(JSON_EXTRACT(v_conditions, '$.max_pass_subjects'));
            SET v_must_include = JSON_EXTRACT(v_conditions, '$.must_include');
            SET v_must_include_condition = JSON_UNQUOTE(JSON_EXTRACT(v_conditions, '$.must_include_condition'));
            SET v_exceptions = JSON_EXTRACT(v_conditions, '$.exceptions');

            -- Check must_pass_all
            IF v_must_pass_all = TRUE AND v_pass_count < v_subject_count THEN
                SET v_division = 'U';
            END IF;

            -- Check min_pass_subjects
            IF v_min_pass_subjects IS NOT NULL AND v_pass_count < v_min_pass_subjects THEN
                SET v_division = 'U';
            END IF;

            -- Check max_pass_subjects
            IF v_max_pass_subjects IS NOT NULL AND v_pass_count > v_max_pass_subjects THEN
                SET v_division = 'U';
            END IF;

            -- Check must_include
            IF v_must_include IS NOT NULL AND v_must_include_condition = 'at least one' THEN
                SET @must_include_pass = 0;
                IF JSON_CONTAINS(v_must_include, '"ENG"') AND v_english_score <= 8 THEN
                    SET @must_include_pass = 1;
                END IF;
                IF JSON_CONTAINS(v_must_include, '"MTC"') AND v_math_score <= 8 THEN
                    SET @must_include_pass = 1;
                END IF;
                IF @must_include_pass = 0 THEN
                    SET v_division = 'U';
                END IF;
            END IF;

            -- Check exceptions
            IF v_exceptions IS NOT NULL THEN
                SET v_exception_count = JSON_LENGTH(v_exceptions);
                WHILE v_exception_index < v_exception_count DO
                    SET v_exception_subject = JSON_UNQUOTE(JSON_EXTRACT(v_exceptions, CONCAT('$[', v_exception_index, '].subject')));
                    SET v_exception_grade = JSON_UNQUOTE(JSON_EXTRACT(v_exceptions, CONCAT('$[', v_exception_index, '].grade')));
                    SET v_demote_to = JSON_UNQUOTE(JSON_EXTRACT(v_exceptions, CONCAT('$[', v_exception_index, '].demote_to')));
                    IF (v_exception_subject = 'ENG' AND v_english_score = v_exception_grade) OR
                       (v_exception_subject = 'MTC' AND v_math_score = v_exception_grade) THEN
                        SET v_division = v_demote_to;
                    END IF;
                    SET v_exception_index = v_exception_index + 1;
                END WHILE;
            END IF;
        END IF;

        -- Update candidate_results
        INSERT INTO candidate_results (candidate_id, school_id, exam_year_id, aggregates, division, processed_by)
        VALUES (p_candidate_id, p_school_id, p_exam_year_id, CAST(v_total_aggregate AS CHAR), v_division, p_user_id)
        ON DUPLICATE KEY UPDATE
            aggregates = CAST(v_total_aggregate AS CHAR),
            division = v_division,
            updated_by = p_user_id,
            updated_at = CURRENT_TIMESTAMP;
    END IF;

    CALL log_action('ComputeCandidateResults', p_user_id, CONCAT('Computed results for candidate ID ', p_candidate_id, ' for exam year ', p_exam_year_id));

    COMMIT;
END //

DELIMITER ;