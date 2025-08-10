DELIMITER //

CREATE PROCEDURE update_results_after_grading_change (
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_candidate_id INT;
    DECLARE v_school_id INT;
    DECLARE v_subcounty_id INT;
    DECLARE v_total_aggregate INT;
    DECLARE v_subject_count INT;
    DECLARE v_pass_count INT;
    DECLARE v_english_score INT;
    DECLARE v_math_score INT;
    DECLARE v_candidate_name VARCHAR(255);
    DECLARE v_index_number VARCHAR(255);
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
    DECLARE cur CURSOR FOR 
        SELECT r.candidate_id, r.school_id, s.subcounty_id, SUM(r.score) as total_aggregate, 
               COUNT(*) as subject_count, 
               SUM(CASE WHEN r.score <= 8 THEN 1 ELSE 0 END) as pass_count,
               MAX(CASE WHEN s2.code = 'ENG' THEN r.score ELSE 9 END) as english_score,
               MAX(CASE WHEN s2.code = 'MTC' THEN r.score ELSE 9 END) as math_score,
               c.candidate_name, c.index_number
        FROM results r
        JOIN candidates c ON r.candidate_id = c.id
        JOIN schools s ON r.school_id = s.id
        JOIN subjects s2 ON r.subject_id = s2.id
        WHERE r.exam_year_id = p_exam_year_id
        GROUP BY r.candidate_id, r.school_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    START TRANSACTION;

    -- Temporary table for candidate results
    CREATE TEMPORARY TABLE temp_candidate_results (
        candidate_id INT,
        school_id INT,
        aggregates VARCHAR(10),
        division VARCHAR(10)
    );

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_candidate_id, v_school_id, v_subcounty_id, v_total_aggregate, v_subject_count, v_pass_count, v_english_score, v_math_score, v_candidate_name, v_index_number;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Calculate division
        SET v_division = 'U';
        SET @division_id = NULL;

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

        INSERT INTO temp_candidate_results (candidate_id, school_id, aggregates, division)
        VALUES (v_candidate_id, v_school_id, CAST(v_total_aggregate AS CHAR), v_division);
    END LOOP;
    CLOSE cur;

    -- Update candidate_results
    INSERT INTO candidate_results (candidate_id, school_id, exam_year_id, aggregates, division, processed_by)
    SELECT t.candidate_id, t.school_id, p_exam_year_id, t.aggregates, t.division, p_user_id
    FROM temp_candidate_results t
    ON DUPLICATE KEY UPDATE
        aggregates = t.aggregates,
        division = t.division,
        updated_by = p_user_id,
        updated_at = CURRENT_TIMESTAMP;

    -- Update school_results
    INSERT INTO school_results (school_id, candidate_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
    SELECT r.school_id, r.candidate_id, c.index_number, c.candidate_name, s.code, r.mark, g.grade, r.exam_year_id
    FROM results r
    JOIN candidates c ON r.candidate_id = c.id
    JOIN subjects s ON r.subject_id = s.id
    JOIN grading g ON r.mark BETWEEN g.range_from AND g.range_to
        AND (g.exam_year_id = r.exam_year_id OR g.exam_year_id IS NULL)
        AND (g.subject_id = r.subject_id OR g.subject_id IS NULL)
    WHERE r.exam_year_id = p_exam_year_id
    ON DUPLICATE KEY UPDATE
        marks = r.mark,
        grade = g.grade,
        updated_at = CURRENT_TIMESTAMP;

    -- Update subcounty_results
    INSERT INTO subcounty_results (subcounty_id, candidate_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
    SELECT sc.id, r.candidate_id, c.index_number, c.candidate_name, s.code, r.mark, g.grade, r.exam_year_id
    FROM results r
    JOIN candidates c ON r.candidate_id = c.id
    JOIN subjects s ON r.subject_id = s.id
    JOIN schools sch ON r.school_id = sch.id
    JOIN subcounties sc ON sch.subcounty_id = sc.id
    JOIN grading g ON r.mark BETWEEN g.range_from AND g.range_to
        AND (g.exam_year_id = r.exam_year_id OR g.exam_year_id IS NULL)
        AND (g.subject_id = r.subject_id OR g.subject_id IS NULL)
    WHERE r.exam_year_id = p_exam_year_id
    ON DUPLICATE KEY UPDATE
        marks = r.mark,
        grade = g.grade,
        updated_at = CURRENT_TIMESTAMP;

    DROP TEMPORARY TABLE temp_candidate_results;

    CALL log_action('UpdateResultsAfterGradingChange', p_user_id, CONCAT('Updated results for exam year ID ', p_exam_year_id));

    COMMIT;
END //

DELIMITER ;