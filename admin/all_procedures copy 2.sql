DELIMITER //

-- 1. log_action (Unchanged)
CREATE OR REPLACE PROCEDURE log_action (
    IN p_action VARCHAR(255),
    IN p_user_id INT,
    IN p_details TEXT
)
BEGIN
    INSERT INTO audit_logs (action, user_id, details, created_at)
    VALUES (p_action, p_user_id, p_details, CURRENT_TIMESTAMP);
END //

-- 2. ComputeCandidateResults
CREATE OR REPLACE PROCEDURE ComputeCandidateResults (
    IN p_candidate_id INT,
    IN p_school_id INT,
    IN p_subcounty_id INT,
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_aggregates INT DEFAULT 0;
    DECLARE v_division VARCHAR(10) DEFAULT 'U';
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

    -- Log start
    CALL log_action('Debug ComputeCandidateResults', p_user_id, CONCAT('Starting for candidate_id: ', p_candidate_id, ', exam_year_id: ', p_exam_year_id));

    -- Count PRESENT subjects
    SELECT COUNT(*) INTO v_subject_count
    FROM marks m
    WHERE m.candidate_id = p_candidate_id
    AND m.exam_year_id = p_exam_year_id
    AND m.status = 'PRESENT';

    CALL log_action('Debug ComputeCandidateResults', p_user_id, CONCAT('Candidate_id: ', p_candidate_id, ', PRESENT subjects: ', v_subject_count));

    IF v_subject_count < 3 THEN
        SET v_aggregates = 0;
        SET v_division = 'X';
    ELSE
        -- Calculate aggregates and collect scores/grades
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

            -- Update school_results
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

            -- Update subcounty_results
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
                -- Division 1: Must pass all subjects
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
                -- Division 2: At least 3 passes
                JSON_EXTRACT(conditions, '$.min_pass_subjects') = 3
                AND v_pass_count >= 3
                AND NOT (
                    JSON_CONTAINS(JSON_EXTRACT(conditions, '$.exceptions'), JSON_OBJECT('subjects_failed', JSON_ARRAY('ENG', 'MTC'), 'grade', 9, 'demote_to', 'Division 3'), '$')
                    AND v_eng_score = 9
                    AND v_mtc_score = 9
                )
            )
            OR (
                -- Division 3 (24–28): At least 3 passes
                JSON_EXTRACT(conditions, '$.min_pass_subjects') = 3
                AND v_aggregates BETWEEN 24 AND 28
                AND v_pass_count >= 3
            )
            OR (
                -- Division 3 (29): At least 3 passes, including ENG or MTC
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
                -- Division 4 (29–32): At least 2 passes
                JSON_EXTRACT(conditions, '$.min_pass_subjects') = 2
                AND v_aggregates BETWEEN 29 AND 32
                AND v_pass_count >= 2
            )
            OR (
                -- Division 4 (33): At least 2 passes, including ENG or MTC
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
                -- Ungraded: At most 1 pass
                JSON_EXTRACT(conditions, '$.max_pass_subjects') = 1
                AND v_pass_count <= 1
            )
        )
        LIMIT 1;

        IF v_division IS NULL THEN
            SET v_division = 'U';
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

    -- Log completion
    CALL log_action('ComputeCandidateResults', p_user_id, CONCAT('Processed candidate_id: ', p_candidate_id, ', aggregates: ', v_aggregates, ', division: ', v_division));
END //

-- 3. ProcessAllCandidates
CREATE OR REPLACE PROCEDURE ProcessAllCandidates (
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

-- 4. initialize_results_from_marks
CREATE OR REPLACE PROCEDURE initialize_results_from_marks (
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_exam_year_exists INT;
    DECLARE v_candidate_id INT;
    DECLARE v_subject_id INT;
    DECLARE v_mark INT;
    DECLARE v_score INT;
    DECLARE v_grade VARCHAR(2);
    DECLARE v_school_id INT;
    DECLARE v_done INT DEFAULT FALSE;
    DECLARE v_status VARCHAR(10);

    DECLARE mark_cursor CURSOR FOR
        SELECT m.candidate_id, m.subject_id, m.mark, m.status, m.school_id
        FROM marks m
        WHERE m.exam_year_id = p_exam_year_id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;

    -- Validate exam_year_id
    SELECT COUNT(*) INTO v_exam_year_exists
    FROM exam_years
    WHERE id = p_exam_year_id;
    IF v_exam_year_exists = 0 THEN
        CALL log_action('InitializeResults Error', p_user_id, CONCAT('Invalid exam_year_id: ', p_exam_year_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid exam_year_id';
    END IF;

    -- Log start
    CALL log_action('Debug InitializeResults', p_user_id, CONCAT('Starting for exam_year_id: ', p_exam_year_id));

    OPEN mark_cursor;

    read_loop: LOOP
        FETCH mark_cursor INTO v_candidate_id, v_subject_id, v_mark, v_status, v_school_id;
        IF v_done THEN
            LEAVE read_loop;
        END IF;

        IF v_status = 'PRESENT' THEN
            -- Get score and grade from grading table
            SELECT score, grade INTO v_score, v_grade
            FROM grading
            WHERE subject_id = v_subject_id
            AND exam_year_id = p_exam_year_id
            AND v_mark BETWEEN range_from AND range_to
            LIMIT 1;

            IF v_score IS NULL THEN
                CALL log_action('Debug InitializeResults', p_user_id, CONCAT('No grading found for candidate_id: ', v_candidate_id, ', subject_id: ', v_subject_id, ', mark: ', v_mark));
                SET v_score = 9;
                SET v_grade = 'F9';
            END IF;
        ELSE
            SET v_score = 9;
            SET v_grade = 'F9';
        END IF;

        -- Log score calculation
        CALL log_action('Debug InitializeResults', p_user_id, CONCAT('Processing candidate_id: ', v_candidate_id, ', subject_id: ', v_subject_id, ', mark: ', v_mark, ', score: ', v_score, ', grade: ', v_grade));

        -- Insert or update results
        INSERT INTO results (
            candidate_id, subject_id, school_id, exam_year_id, mark, score, grade, processed_by
        ) VALUES (
            v_candidate_id, v_subject_id, v_school_id, p_exam_year_id, v_mark, v_score, v_grade, p_user_id
        )
        ON DUPLICATE KEY UPDATE
            mark = v_mark,
            score = v_score,
            grade = v_grade,
            processed_by = p_user_id,
            updated_by = p_user_id,
            updated_at = CURRENT_TIMESTAMP;
    END LOOP;

    CLOSE mark_cursor;

    -- Log completion
    CALL log_action('InitializeResults', p_user_id, CONCAT('Initialized results for exam_year_id: ', p_exam_year_id));
END //

-- 5. update_results_after_grading_change
CREATE OR REPLACE PROCEDURE update_results_after_grading_change (
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    START TRANSACTION;

    -- Log start
    CALL log_action('Debug UpdateResultsAfterGradingChange', p_user_id, CONCAT('Starting for exam_year_id: ', p_exam_year_id));

    -- Update results with new grades and scores
    UPDATE results r
    JOIN marks m ON r.candidate_id = m.candidate_id 
        AND r.subject_id = m.subject_id 
        AND r.exam_year_id = m.exam_year_id
    JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to 
        AND g.subject_id = m.subject_id 
        AND g.exam_year_id = m.exam_year_id
    SET r.score = CASE WHEN m.status = 'PRESENT' THEN g.score ELSE 9 END,
        r.grade = CASE WHEN m.status = 'PRESENT' THEN g.grade ELSE 'F9' END,
        r.mark = m.mark,
        r.updated_by = p_user_id,
        r.updated_at = CURRENT_TIMESTAMP
    WHERE r.exam_year_id = p_exam_year_id;

    -- Log results update
    CALL log_action('Debug UpdateResultsAfterGradingChange', p_user_id, 'Updated results table');

    -- Temporary table for candidate results
    CREATE TEMPORARY TABLE temp_candidate_results (
        candidate_id INT,
        school_id INT,
        subcounty_id INT,
        aggregates INT,
        division VARCHAR(10),
        present_count INT,
        pass_count INT,
        eng_score INT,
        mtc_score INT,
        PRIMARY KEY (candidate_id)
    );

    -- Populate temp table
    INSERT INTO temp_candidate_results
    SELECT 
        c.id AS candidate_id,
        c.school_id,
        s.subcounty_id,
        COALESCE(SUM(CASE WHEN m.status = 'PRESENT' THEN r.score ELSE 9 END), 0) AS aggregates,
        'U' AS division,
        COUNT(CASE WHEN m.status = 'PRESENT' THEN 1 END) AS present_count,
        SUM(CASE WHEN r.score <= 8 THEN 1 ELSE 0 END) AS pass_count,
        MAX(CASE WHEN s2.code = 'ENG' THEN r.score ELSE 9 END) AS eng_score,
        MAX(CASE WHEN s2.code = 'MTC' THEN r.score ELSE 9 END) AS mtc_score
    FROM candidates c
    LEFT JOIN marks m ON c.id = m.candidate_id AND m.exam_year_id = p_exam_year_id
    LEFT JOIN results r ON c.id = r.candidate_id AND r.exam_year_id = p_exam_year_id
    JOIN schools s ON c.school_id = s.id
    LEFT JOIN subjects s2 ON r.subject_id = s2.id
    WHERE c.exam_year_id = p_exam_year_id
    GROUP BY c.id, c.school_id, s.subcounty_id;

    -- Update division for candidates with <3 PRESENT subjects
    UPDATE temp_candidate_results
    SET aggregates = 0, division = 'X'
    WHERE present_count < 3;

    -- Update division based on grades table
    UPDATE temp_candidate_results tcr
    JOIN grades g ON tcr.aggregates BETWEEN g.aggregate_range_from AND g.aggregate_range_to
        AND (g.exam_year_id = p_exam_year_id OR g.exam_year_id IS NULL)
    SET tcr.division = CASE
        WHEN JSON_EXTRACT(g.conditions, '$.must_pass_all') = TRUE AND tcr.pass_count < tcr.present_count THEN 'U'
        WHEN JSON_EXTRACT(g.conditions, '$.must_pass_all') = TRUE AND tcr.pass_count = 4 THEN
            CASE
                WHEN JSON_CONTAINS(JSON_EXTRACT(g.conditions, '$.exceptions'), JSON_OBJECT('subject', 'ENG', 'grade', 9, 'demote_to', 'Division 2'), '$') AND tcr.eng_score = 9 THEN 'Division 2'
                WHEN JSON_CONTAINS(JSON_EXTRACT(g.conditions, '$.exceptions'), JSON_OBJECT('subject', 'MTC', 'grade', 9, 'demote_to', 'Division 2'), '$') AND tcr.mtc_score = 9 THEN 'Division 2'
                ELSE g.division
            END
        WHEN JSON_EXTRACT(g.conditions, '$.min_pass_subjects') = 3 AND tcr.pass_count >= 3 THEN
            CASE
                WHEN JSON_CONTAINS(JSON_EXTRACT(g.conditions, '$.exceptions'), JSON_OBJECT('subjects_failed', JSON_ARRAY('ENG', 'MTC'), 'grade', 9, 'demote_to', 'Division 3'), '$') 
                     AND tcr.eng_score = 9 AND tcr.mtc_score = 9 THEN 'Division 3'
                WHEN tcr.aggregates = 29 AND JSON_EXTRACT(g.conditions, '$.must_include_condition') = '"at least one"'
                     AND NOT (
                         (JSON_CONTAINS(JSON_EXTRACT(g.conditions, '$.must_include'), '"ENG"', '$') AND tcr.eng_score <= 8)
                         OR (JSON_CONTAINS(JSON_EXTRACT(g.conditions, '$.must_include'), '"MTC"', '$') AND tcr.mtc_score <= 8)
                     ) THEN 'U'
                ELSE g.division
            END
        WHEN JSON_EXTRACT(g.conditions, '$.min_pass_subjects') = 2 AND tcr.pass_count >= 2 THEN
            CASE
                WHEN tcr.aggregates = 33 AND JSON_EXTRACT(g.conditions, '$.must_include_condition') = '"at least one"'
                     AND NOT (
                         (JSON_CONTAINS(JSON_EXTRACT(g.conditions, '$.must_include'), '"ENG"', '$') AND tcr.eng_score <= 8)
                         OR (JSON_CONTAINS(JSON_EXTRACT(g.conditions, '$.must_include'), '"MTC"', '$') AND tcr.mtc_score <= 8)
                     ) THEN 'U'
                ELSE g.division
            END
        WHEN JSON_EXTRACT(g.conditions, '$.max_pass_subjects') = 1 AND tcr.pass_count <= 1 THEN g.division
        ELSE 'U'
    END
    WHERE tcr.present_count >= 3;

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
    SELECT r.school_id, r.candidate_id, c.index_number, c.candidate_name, s.code, r.mark, r.grade, r.exam_year_id
    FROM results r
    JOIN candidates c ON r.candidate_id = c.id
    JOIN subjects s ON r.subject_id = s.id
    WHERE r.exam_year_id = p_exam_year_id
    ON DUPLICATE KEY UPDATE
        marks = r.mark,
        grade = r.grade,
        updated_at = CURRENT_TIMESTAMP;

    -- Update subcounty_results
    INSERT INTO subcounty_results (subcounty_id, candidate_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
    SELECT sc.id, r.candidate_id, c.index_number, c.candidate_name, s.code, r.mark, r.grade, r.exam_year_id
    FROM results r
    JOIN candidates c ON r.candidate_id = c.id
    JOIN subjects s ON r.subject_id = s.id
    JOIN schools sch ON r.school_id = sch.id
    JOIN subcounties sc ON sch.subcounty_id = sc.id
    WHERE r.exam_year_id = p_exam_year_id
    ON DUPLICATE KEY UPDATE
        marks = r.mark,
        grade = r.grade,
        updated_at = CURRENT_TIMESTAMP;

    -- Drop temp table
    DROP TEMPORARY TABLE temp_candidate_results;

    -- Log completion
    CALL log_action('UpdateResultsAfterGradingChange', p_user_id, CONCAT('Updated results for exam_year_id: ', p_exam_year_id));

    COMMIT;
END //

-- 6. update_all_results
CREATE OR REPLACE PROCEDURE update_all_results (
    IN p_exam_year_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_exam_year_exists INT;
    DECLARE v_candidate_count INT;
    DECLARE v_marks_count INT;

    -- Log input parameters
    CALL log_action('Debug UpdateAllResults', p_user_id, CONCAT('Starting with exam_year_id: ', p_exam_year_id, ', user_id: ', p_user_id));

    -- Validate exam_year_id
    SELECT COUNT(*) INTO v_exam_year_exists
    FROM exam_years
    WHERE id = p_exam_year_id;
    IF v_exam_year_exists = 0 THEN
        CALL log_action('UpdateAllResults Error', p_user_id, CONCAT('Invalid exam_year_id: ', p_exam_year_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid exam_year_id';
    END IF;

    -- Check prerequisite data
    SELECT COUNT(*) INTO v_candidate_count
    FROM candidates
    WHERE exam_year_id = p_exam_year_id;
    SELECT COUNT(*) INTO v_marks_count
    FROM marks
    WHERE exam_year_id = p_exam_year_id;

    CALL log_action('Debug UpdateAllResults', p_user_id, CONCAT('Candidates found: ', v_candidate_count, ', Marks found: ', v_marks_count));

    START TRANSACTION;

    -- Clear existing results
    DELETE FROM results WHERE exam_year_id = p_exam_year_id;
    DELETE FROM candidate_results WHERE exam_year_id = p_exam_year_id;
    DELETE FROM school_results WHERE exam_year_id = p_exam_year_id;
    DELETE FROM subcounty_results WHERE exam_year_id = p_exam_year_id;

    -- Log deletion
    CALL log_action('Debug UpdateAllResults', p_user_id, 'Cleared existing results');

    -- Reinitialize results
    CALL initialize_results_from_marks(p_exam_year_id, p_user_id);

    -- Process all candidates
    CALL ProcessAllCandidates(p_exam_year_id, p_user_id);

    -- Log completion
    CALL log_action('UpdateAllResults', p_user_id, CONCAT('Recalculated all results for exam_year_id: ', p_exam_year_id, ', processed ', v_candidate_count, ' candidates'));

    COMMIT;
END //

-- 7. correct_candidate_marks
CREATE OR REPLACE PROCEDURE correct_candidate_marks (
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
    DECLARE v_status VARCHAR(10);

    START TRANSACTION;

    -- Validate new mark
    IF p_new_mark < 0 OR p_new_mark > 100 THEN
        CALL log_action('CorrectCandidateMarks Error', p_user_id, CONCAT('Invalid mark: ', p_new_mark, ' for candidate_id: ', p_candidate_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mark must be between 0 and 100';
    END IF;

    -- Get school_id, subcounty_id, and status
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
        SET v_grade = 'F9';
        SET v_score = 9;
    END IF;

    SET v_status = 'PRESENT';

    -- Update or insert into marks
    INSERT INTO marks (candidate_id, subject_id, school_id, exam_year_id, mark, status, submitted_by, edited_by)
    VALUES (p_candidate_id, p_subject_id, v_school_id, p_exam_year_id, p_new_mark, v_status, p_user_id, p_user_id)
    ON DUPLICATE KEY UPDATE
        mark = p_new_mark,
        status = v_status,
        edited_by = p_user_id,
        updated_at = CURRENT_TIMESTAMP;

    -- Update results
    INSERT INTO results (
        candidate_id, subject_id, school_id, exam_year_id, mark, score, grade, processed_by
    )
    VALUES (
        p_candidate_id, p_subject_id, v_school_id, p_exam_year_id, p_new_mark, v_score, v_grade, p_user_id
    )
    ON DUPLICATE KEY UPDATE
        mark = p_new_mark,
        score = v_score,
        grade = v_grade,
        updated_by = p_user_id,
        updated_at = CURRENT_TIMESTAMP;

    -- Update related tables
    CALL ComputeCandidateResults(p_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, p_user_id);

    CALL log_action('CorrectCandidateMarks', p_user_id, CONCAT('Corrected mark for candidate_id: ', p_candidate_id, ', subject_id: ', p_subject_id, ', exam_year_id: ', p_exam_year_id, ', mark: ', p_new_mark));

    COMMIT;
END //

-- 8. ProcessAllCandidateResults
CREATE OR REPLACE PROCEDURE ProcessAllCandidateResults (
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