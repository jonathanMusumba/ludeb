DELIMITER //

-- Updating ComputeCandidateResults to use 'Ungraded' and correctly assign divisions
CREATE OR REPLACE PROCEDURE ComputeCandidateResults (
    IN p_candidate_id INT,
    IN p_school_id INT,
    IN p_subcounty_id INT,
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_aggregates INT DEFAULT 0;
    DECLARE v_division VARCHAR(10) DEFAULT 'Ungraded';
    DECLARE v_subject_count INT;
    DECLARE v_eng_score INT DEFAULT 9;
    DECLARE v_mtc_score INT DEFAULT 9;
    DECLARE v_sci_score INT DEFAULT 9;
    DECLARE v_sst_score INT DEFAULT 9;
    DECLARE v_eng_grade VARCHAR(2) DEFAULT 'F9';
    DECLARE v_mtc_grade VARCHAR(2) DEFAULT 'F9';
    DECLARE v_sci_grade VARCHAR(2) DEFAULT 'F9';
    DECLARE v_sst_grade VARCHAR(2) DEFAULT 'F9';
    DECLARE v_subject_code VARCHAR(20);
    DECLARE v_mark INT;
    DECLARE v_score INT;
    DECLARE v_grade VARCHAR(2);
    DECLARE v_done INT DEFAULT FALSE;
    DECLARE v_pass_count INT DEFAULT 0;

    DECLARE result_cursor CURSOR FOR
        SELECT r.mark, r.score, r.grade, s.code
        FROM results r
        JOIN subjects s ON r.subject_id = s.id
        WHERE r.candidate_id = p_candidate_id
        AND r.exam_year_id = p_exam_year_id
        AND EXISTS (
            SELECT 1
            FROM marks m
            WHERE m.candidate_id = r.candidate_id
            AND m.subject_id = r.subject_id
            AND m.exam_year_id = r.exam_year_id
            AND m.status = 'PRESENT'
        );

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

    -- Log start of processing
    CALL log_action('Debug ComputeCandidateResults', p_user_id, CONCAT('Starting for candidate_id: ', p_candidate_id, ', exam_year_id: ', p_exam_year_id));

    -- Count PRESENT subjects
    SELECT COUNT(*) INTO v_subject_count
    FROM marks m
    WHERE m.candidate_id = p_candidate_id
    AND m.exam_year_id = p_exam_year_id
    AND m.status = 'PRESENT';

    CALL log_action('Debug ComputeCandidateResults', p_user_id, CONCAT('Candidate_id: ', p_candidate_id, ', PRESENT subjects: ', v_subject_count));

    -- Assign X if fewer than 3 subjects or all absent when others have marks
    IF v_subject_count < 3 THEN
        SET v_aggregates = 0;
        SET v_division = 'X';
    ELSE
        -- Process results
        OPEN result_cursor;
        read_loop: LOOP
            FETCH result_cursor INTO v_mark, v_score, v_grade, v_subject_code;
            IF v_done THEN
                LEAVE read_loop;
            END IF;

            SET v_aggregates = v_aggregates + v_score;
            IF v_subject_code = 'ENG' THEN
                SET v_eng_score = v_score;
                SET v_eng_grade = v_grade;
            END IF;
            IF v_subject_code = 'MTC' THEN
                SET v_mtc_score = v_score;
                SET v_mtc_grade = v_grade;
            END IF;
            IF v_subject_code = 'SCI' THEN
                SET v_sci_score = v_score;
                SET v_sci_grade = v_grade;
            END IF;
            IF v_subject_code = 'SST' THEN
                SET v_sst_score = v_score;
                SET v_sst_grade = v_grade;
            END IF;

            -- Insert into school_results
            INSERT INTO school_results (
                school_id, candidate_id, candidate_index_number, candidate_name,
                subject_code, marks, grade, exam_year_id
            )
            SELECT p_school_id, p_candidate_id, c.index_number, c.candidate_name,
                   v_subject_code, v_mark, v_grade, p_exam_year_id
            FROM candidates c
            WHERE c.id = p_candidate_id
            AND c.exam_year_id = p_exam_year_id
            ON DUPLICATE KEY UPDATE
                marks = v_mark,
                grade = v_grade,
                updated_at = CURRENT_TIMESTAMP;

            -- Insert into subcounty_results
            INSERT INTO subcounty_results (
                subcounty_id, candidate_id, candidate_index_number, candidate_name,
                subject_code, marks, grade, exam_year_id
            )
            SELECT p_subcounty_id, p_candidate_id, c.index_number, c.candidate_name,
                   v_subject_code, v_mark, v_grade, p_exam_year_id
            FROM candidates c
            WHERE c.id = p_candidate_id
            AND c.exam_year_id = p_exam_year_id
            ON DUPLICATE KEY UPDATE
                marks = v_mark,
                grade = v_grade,
                updated_at = CURRENT_TIMESTAMP;
        END LOOP;
        CLOSE result_cursor;

        -- Calculate pass count
        SET v_pass_count = (v_eng_score <= 8) + (v_mtc_score <= 8) + (v_sci_score <= 8) + (v_sst_score <= 8);

        -- Determine division based on grades table
        SELECT division INTO v_division
        FROM grades
        WHERE exam_year_id = p_exam_year_id
        AND v_aggregates BETWEEN aggregate_range_from AND aggregate_range_to
        AND (
            conditions IS NULL
            OR conditions = '{}'
            OR (
                JSON_EXTRACT(conditions, '$.must_pass_all') = TRUE
                AND v_pass_count = 4
                AND NOT (
                    JSON_CONTAINS(JSON_EXTRACT(conditions, '$.exceptions'), JSON_OBJECT('subject', 'ENG', 'grade', 9, 'demote_to', 'Division 2'), '$')
                    AND v_eng_score = 9
                )
                AND NOT (
                    JSON_CONTAINS(JSON_EXTRACT(conditions, '$.exceptions'), JSON_OBJECT('subject', 'MTC', 'grade', 9, 'demote_to', 'Division 2'), '$')
                    AND v_mtc_score = 9
                )
            )
            OR (
                JSON_EXTRACT(conditions, '$.min_pass_subjects') = 3
                AND v_pass_count >= 3
                AND NOT (
                    JSON_CONTAINS(JSON_EXTRACT(conditions, '$.exceptions'), JSON_OBJECT('subjects_failed', JSON_ARRAY('ENG', 'MTC'), 'grade', 9, 'demote_to', 'Division 3'), '$')
                    AND v_eng_score = 9
                    AND v_mtc_score = 9
                )
            )
            OR (
                JSON_EXTRACT(conditions, '$.min_pass_subjects') = 3
                AND v_aggregates BETWEEN 24 AND 28
                AND v_pass_count >= 3
            )
            OR (
                JSON_EXTRACT(conditions, '$.min_pass_subjects') = 3
                AND v_aggregates = 29
                AND v_pass_count >= 3
                AND (
                    JSON_CONTAINS(JSON_EXTRACT(conditions, '$.must_include'), '"ENG"', '$')
                    AND v_eng_score <= 8
                    OR JSON_CONTAINS(JSON_EXTRACT(conditions, '$.must_include'), '"MTC"', '$')
                    AND v_mtc_score <= 8
                )
            )
            OR (
                JSON_EXTRACT(conditions, '$.min_pass_subjects') = 2
                AND v_aggregates BETWEEN 29 AND 32
                AND v_pass_count >= 2
            )
            OR (
                JSON_EXTRACT(conditions, '$.min_pass_subjects') = 2
                AND v_aggregates = 33
                AND v_pass_count >= 2
                AND (
                    JSON_CONTAINS(JSON_EXTRACT(conditions, '$.must_include'), '"ENG"', '$')
                    AND v_eng_score <= 8
                    OR JSON_CONTAINS(JSON_EXTRACT(conditions, '$.must_include'), '"MTC"', '$')
                    AND v_mtc_score <= 8
                )
            )
            OR (
                JSON_EXTRACT(conditions, '$.max_pass_subjects') = 1
                AND v_pass_count <= 1
                AND v_aggregates BETWEEN 33 AND 36
            )
        )
        ORDER BY aggregate_range_from ASC
        LIMIT 1;

        IF v_division IS NULL THEN
            SET v_division = 'Ungraded';
        END IF;
    END IF;

    -- Insert or update candidate_results
    INSERT INTO candidate_results (
        candidate_id, school_id, exam_year_id, aggregates, division, processed_by
    )
    VALUES (
        p_candidate_id, p_school_id, p_exam_year_id, v_aggregates, v_division, p_user_id
    )
    ON DUPLICATE KEY UPDATE
        aggregates = v_aggregates,
        division = v_division,
        updated_by = p_user_id,
        updated_at = CURRENT_TIMESTAMP;

    -- Update school results status
    CALL UpdateSchoolResultsStatus(p_school_id, p_exam_year_id, p_user_id);

    -- Log completion
    CALL log_action('ComputeCandidateResults', p_user_id, 
        CONCAT('Processed candidate_id: ', p_candidate_id, 
               ', aggregates: ', v_aggregates, 
               ', division: ', v_division));
END //


DELIMITER ;