```sql
DELIMITER $$

-- Create table for Detailed Reports
CREATE TABLE IF NOT EXISTS detailed_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_year_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    introduction TEXT NOT NULL,
    registration_of_candidates TEXT NOT NULL,
    marking TEXT NOT NULL,
    assessment TEXT NOT NULL,
    performance TEXT NOT NULL,
    challenges TEXT,
    recommendations TEXT,
    way_forward TEXT NOT NULL,
    conclusion TEXT NOT NULL,
    appendices JSON, -- Stores General Schools Performance, Subcounty Performance, and Summary of Subcounties Performance
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_year_id) REFERENCES exam_years(id) ON DELETE RESTRICT,
    FOREIGN KEY (uploaded_by) REFERENCES system_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Procedure: ComputeCandidateGrades
CREATE OR REPLACE PROCEDURE `ComputeCandidateGrades` (
    IN `p_candidate_id` INT,
    IN `p_school_id` INT,
    IN `p_subcounty_id` INT,
    IN `p_exam_year_id` INT,
    IN `p_user_id` INT
)
BEGIN
    DECLARE v_score INT;
    DECLARE v_mark INT;
    DECLARE v_status ENUM('PRESENT', 'ABSENT');
    DECLARE v_subject_id INT;
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_division_id INT DEFAULT NULL;
    DECLARE v_aggregate INT DEFAULT 0;
    DECLARE v_total_marks INT DEFAULT 0;
    DECLARE v_subject_count INT DEFAULT 0;

    DECLARE cur CURSOR FOR
        SELECT m.mark, m.status, m.subject_id
        FROM marks m
        WHERE m.candidate_id = p_candidate_id 
        AND m.exam_year_id = p_exam_year_id 
        AND m.subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- Validate p_user_id
    IF p_user_id IS NULL OR p_user_id = 0 OR NOT EXISTS (SELECT 1 FROM system_users WHERE id = p_user_id) THEN
        CALL log_action('Compute Grades Error', NULL, CONCAT('Invalid user_id: ', COALESCE(p_user_id, 'NULL'), ' for candidate_id: ', p_candidate_id, ', exam_year_id: ', p_exam_year_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid user ID provided';
    END IF;

    -- Validate p_exam_year_id
    IF p_exam_year_id IS NULL OR p_exam_year_id = 0 OR NOT EXISTS (SELECT 1 FROM exam_years WHERE id = p_exam_year_id AND status = 'Active') THEN
        CALL log_action('Compute Grades Error', p_user_id, CONCAT('Invalid exam_year_id: ', COALESCE(p_exam_year_id, 'NULL'), ' for candidate_id: ', p_candidate_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid or inactive exam year ID provided';
    END IF;

    START TRANSACTION;

    -- Delete existing results for the candidate and exam year
    DELETE FROM results 
    WHERE candidate_id = p_candidate_id 
    AND exam_year_id = p_exam_year_id;

    SET v_aggregate = 0;
    SET v_total_marks = 0;
    SET v_subject_count = 0;

    -- Process marks for each subject
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_mark, v_status, v_subject_id;
        IF done THEN
            LEAVE read_loop;
        END IF;

        IF v_status = 'PRESENT' THEN
            SELECT score INTO v_score
            FROM grading
            WHERE v_mark BETWEEN range_from AND range_to
            LIMIT 1;

            IF v_score IS NOT NULL THEN
                INSERT INTO results (candidate_id, subject_id, school_id, exam_year_id, mark, score, processed_by)
                VALUES (p_candidate_id, v_subject_id, p_school_id, p_exam_year_id, v_mark, v_score, p_user_id);

                SET v_total_marks = v_total_marks + v_mark;
                SET v_aggregate = v_aggregate + v_score;
                SET v_subject_count = v_subject_count + 1;
            END IF;
        END IF;
    END LOOP;
    CLOSE cur;

    -- Insert into candidate_results
    IF v_subject_count < 4 THEN
        INSERT INTO candidate_results (
            candidate_id, school_id, exam_year_id, aggregates, division, processed_by
        )
        VALUES (
            p_candidate_id, 
            p_school_id, 
            p_exam_year_id,
            0, 
            'X',
            p_user_id
        )
        ON DUPLICATE KEY UPDATE
            aggregates = 0,
            division = 'X',
            updated_at = NOW(),
            updated_by = p_user_id;
    ELSE
        SELECT id INTO v_division_id
        FROM grades
        WHERE v_aggregate BETWEEN aggregate_range_from AND aggregate_range_to
        LIMIT 1;

        INSERT INTO candidate_results (
            candidate_id, school_id, exam_year_id, aggregates, division, processed_by
        )
        VALUES (
            p_candidate_id, 
            p_school_id, 
            p_exam_year_id,
            v_aggregate, 
            COALESCE((SELECT division FROM grades WHERE id = v_division_id), 'Ungraded'),
            p_user_id
        )
        ON DUPLICATE KEY UPDATE
            aggregates = v_aggregate,
            division = COALESCE((SELECT division FROM grades WHERE id = v_division_id), 'Ungraded'),
            updated_at = NOW(),
            updated_by = p_user_id;
    END IF;

    -- Log the action
    CALL log_action('Compute Grades', p_user_id, CONCAT('Computed grades for candidate ID ', p_candidate_id, ' for exam year ', p_exam_year_id));

    COMMIT;
END$$

-- Procedure: ComputeCandidateResults
CREATE OR REPLACE PROCEDURE `ComputeCandidateResults` (
    IN `p_candidate_id` INT,
    IN `p_school_id` INT,
    IN `p_subcounty_id` INT,
    IN `p_exam_year_id` INT,
    IN `p_user_id` INT
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

    -- Assign aggregates = 0 and division = 'X' if fewer than 4 subjects
    IF v_subject_count < 4 THEN
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
END$$

-- Procedure: correct_candidate_marks
CREATE OR REPLACE PROCEDURE `correct_candidate_marks` (
    IN `p_candidate_id` INT,
    IN `p_subject_id` INT,
    IN `p_exam_year_id` INT,
    IN `p_new_mark` INT,
    IN `p_user_id` INT
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

    -- Get school_id, subcounty_id
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
END$$

-- Procedure: delete_candidate_mark
CREATE OR REPLACE PROCEDURE `delete_candidate_mark` (
    IN `p_candidate_id` INT,
    IN `p_subject_id` INT,
    IN `p_exam_year_id` INT,
    IN `p_user_id` INT
)
BEGIN
    DECLARE v_school_id INT;
    DECLARE v_subcounty_id INT;

    START TRANSACTION;

    -- Get school_id and subcounty_id
    SELECT c.school_id, s.subcounty_id INTO v_school_id, v_subcounty_id
    FROM candidates c
    JOIN schools s ON c.school_id = s.id
    WHERE c.id = p_candidate_id AND c.exam_year_id = p_exam_year_id
    LIMIT 1;

    IF v_school_id IS NULL THEN
        CALL log_action('DeleteCandidateMark Error', p_user_id, CONCAT('Invalid candidate_id or exam_year_id: ', p_candidate_id, ', ', p_exam_year_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid candidate or exam year';
    END IF;

    -- Delete from marks
    DELETE FROM marks
    WHERE candidate_id = p_candidate_id
    AND subject_id = p_subject_id
    AND exam_year_id = p_exam_year_id;

    -- Delete from results
    DELETE FROM results
    WHERE candidate_id = p_candidate_id
    AND subject_id = p_subject_id
    AND exam_year_id = p_exam_year_id;

    -- Update related tables
    CALL ComputeCandidateResults(p_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, p_user_id);

    CALL log_action('DeleteCandidateMark', p_user_id, CONCAT('Deleted mark for candidate_id: ', p_candidate_id, ', subject_id: ', p_subject_id, ', exam_year_id: ', p_exam_year_id));

    COMMIT;
END$$

-- Procedure: initialize_results_from_marks
CREATE OR REPLACE PROCEDURE `initialize_results_from_marks` (
    IN `p_exam_year_id` INT,
    IN `p_user_id` INT
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
END$$

-- Procedure: log_action
CREATE OR REPLACE PROCEDURE `log_action` (
    IN `p_action` VARCHAR(255),
    IN `p_user_id` INT,
    IN `p_details` TEXT
)
BEGIN
    INSERT INTO audit_logs (action, user_id, details, created_at)
    VALUES (p_action, p_user_id, p_details, CURRENT_TIMESTAMP);
END$$

-- Procedure: ProcessAllCandidateResults
CREATE OR REPLACE PROCEDURE `ProcessAllCandidateResults` (
    IN `p_exam_year_id` INT,
    IN `p_user_id` INT
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
END$$

-- Procedure: ProcessAllCandidates
CREATE OR REPLACE PROCEDURE `ProcessAllCandidates` (
    IN `p_exam_year_id` INT,
    IN `p_user_id` INT
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
END$$

-- Procedure: UpdateSchoolResultsStatus
CREATE OR REPLACE PROCEDURE `UpdateSchoolResultsStatus` (
    IN `p_school_id` INT,
    IN `p_exam_year_id` INT,
    IN `p_user_id` INT
)
BEGIN
    DECLARE v_total_candidates INT DEFAULT 0;
    DECLARE v_candidates_with_marks INT DEFAULT 0;
    DECLARE v_candidates_with_four_subjects INT DEFAULT 0;
    DECLARE v_percentage_with_marks DECIMAL(5,2) DEFAULT 0;
    DECLARE v_status ENUM('Not Declared', 'Partially Declared', 'Declared') DEFAULT 'Not Declared';
    DECLARE v_error_message VARCHAR(255);

    START TRANSACTION;

    -- Validate inputs
    IF p_school_id IS NULL OR p_school_id <= 0 THEN
        SET v_error_message = CONCAT('Invalid school_id: ', COALESCE(p_school_id, 'NULL'));
        INSERT INTO audit_logs (action, user_id, details, created_at)
        VALUES ('UpdateSchoolResultsStatus Error', p_user_id, v_error_message, CURRENT_TIMESTAMP);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
    END IF;

    IF p_exam_year_id IS NULL OR p_exam_year_id <= 0 THEN
        SET v_error_message = CONCAT('Invalid exam_year_id: ', COALESCE(p_exam_year_id, 'NULL'));
        INSERT INTO audit_logs (action, user_id, details, created_at)
        VALUES ('UpdateSchoolResultsStatus Error', p_user_id, v_error_message, CURRENT_TIMESTAMP);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM schools WHERE id = p_school_id) THEN
        SET v_error_message = CONCAT('School not found for school_id: ', p_school_id);
        INSERT INTO audit_logs (action, user_id, details, created_at)
        VALUES ('UpdateSchoolResultsStatus Error', p_user_id, v_error_message, CURRENT_TIMESTAMP);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM exam_years WHERE id = p_exam_year_id AND status = 'Active') THEN
        SET v_error_message = CONCAT('Invalid or inactive exam_year_id: ', p_exam_year_id);
        INSERT INTO audit_logs (action, user_id, details, created_at)
        VALUES ('UpdateSchoolResultsStatus Error', p_user_id, v_error_message, CURRENT_TIMESTAMP);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
    END IF;

    -- Get total candidates
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

    -- Calculate percentage of candidates with marks
    IF v_total_candidates > 0 THEN
        SET v_percentage_with_marks = (v_candidates_with_marks / v_total_candidates) * 100;
    END IF;

    -- Determine results_status
    IF v_total_candidates = 0 OR v_candidates_with_marks = 0 THEN
        SET v_status = 'Not Declared';
    ELSEIF v_candidates_with_four_subjects > 0 OR 
           v_percentage_with_marks >= 25.0 OR 
           v_percentage_with_marks >= 14.0 THEN
        SET v_status = 'Declared';
    ELSE
        SET v_status = 'Partially Declared';
    END IF;

    -- Update schools table
    UPDATE schools
    SET results_status = v_status
    WHERE id = p_school_id;

    -- Log the action
    INSERT INTO audit_logs (action, user_id, details, created_at)
    VALUES (
        'UpdateSchoolResultsStatus',
        p_user_id,
        CONCAT('Updated results_status to ', v_status, 
               ' for school_id: ', p_school_id, 
               ', exam_year_id: ', p_exam_year_id, 
               ', total_candidates: ', v_total_candidates, 
               ', candidates_with_marks: ', v_candidates_with_marks, 
               ', candidates_with_four_subjects: ', v_candidates_with_four_subjects,
               ', percentage_with_marks: ', ROUND(v_percentage_with_marks, 2), '%'),
        CURRENT_TIMESTAMP
    );

    COMMIT;
END$$

-- Procedure: update_all_results
CREATE OR REPLACE PROCEDURE `update_all_results` (
    IN `p_exam_year_id` INT,
    IN `p_user_id` INT
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
END$$

-- Procedure: update_results_after_grading_change
CREATE OR REPLACE PROCEDURE `update_results_after_grading_change` (
    IN `p_exam_year_id` INT,
    IN `p_user_id` INT
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

    -- Update division for candidates with <4 PRESENT subjects
    UPDATE temp_candidate_results
    SET aggregates = 0, division = 'X'
    WHERE present_count < 4;

    -- Update division based on grades table
    UPDATE temp_candidate_results tcr
    JOIN grades g ON tcr.aggregates BETWEEN g.aggregate_range_from AND g.aggregate_range_to
        AND (g.exam_year_id = p_exam_year_id OR g.exam_year_id IS NULL)
    SET tcr.division = CASE
        WHEN tcr.present_count < 4 THEN 'X'
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
    WHERE tcr.present_count >= 4;

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
END$$

-- Procedure: ListSchools
CREATE OR REPLACE PROCEDURE `ListSchools` (
    IN `p_exam_year_id` INT,
    IN `p_user_id` INT
)
BEGIN
    -- Validate exam_year_id
    IF p_exam_year_id <= 0 THEN
        CALL log_action('ListSchools Error', p_user_id, CONCAT('Invalid exam_year_id: ', p_exam_year_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid exam_year_id';
    END IF;

    -- Retrieve schools ordered by center_number
    SELECT 
        s.center_number AS CenterNumber,
        s.name AS SchoolName,
        s.school_type AS SchoolType,
        s.status AS Status,
        COALESCE(s.results_status, 'Not Declared') AS ResultsStatus,
        'View' AS Action
    FROM schools s
    ORDER BY s.center_number;

    CALL log_action('ListSchools', p_user_id, CONCAT('Retrieved schools list for exam_year_id: ', p_exam_year_id));
END$$

-- Procedure: ListRegisteredSchools
CREATE OR REPLACE PROCEDURE `ListRegisteredSchools` (
    IN `p_exam_year_id` INT,
    IN `p_user_id` INT
)
BEGIN
    -- Validate exam_year_id
    IF p_exam_year_id <= 0 THEN
        CALL log_action('ListRegisteredSchools Error', p_user_id, CONCAT('Invalid exam_year_id: ', p_exam_year_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid exam_year_id';
    END IF;

    -- Retrieve schools with candidates
    SELECT 
        s.center_number AS CenterNumber,
        s.name AS SchoolName,
        COUNT(c.id) AS NumberOfCandidates,
        s.school_type AS SchoolType,
        s.status AS Status,
        COALESCE(s.results_status, 'Not Declared') AS ResultsStatus,
        'View' AS Action
    FROM schools s
    JOIN candidates c ON s.id = c.school_id
    WHERE c.exam_year_id = p_exam_year_id
    GROUP BY s.id, s.center_number, s.name, s.school_type, s.status, s.results_status
    ORDER BY s.center_number;

    CALL log_action('ListRegisteredSchools', p_user_id, CONCAT('Retrieved registered schools list for exam_year_id: ', p_exam_year_id));
END$$

-- Procedure: ListUnregisteredSchools
CREATE OR REPLACE PROCEDURE `ListUnregisteredSchools` (
    IN `p_exam_year_id` INT,
    IN `p_user_id` INT
)
BEGIN
    -- Validate exam_year_id
    IF p_exam_year_id <= 0 THEN
        CALL log_action('ListUnregisteredSchools Error', p_user_id, CONCAT('Invalid exam_year_id: ', p_exam_year_id));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid exam_year_id';
    END IF;

    -- Retrieve schools without candidates
    SELECT 
        s.center_number AS CenterNumber,
        s.name AS SchoolName,
        s.school_type AS SchoolType,
        s.status AS Status,
        COALESCE(s.results_status, 'Not Declared') AS ResultsStatus,
        'View' AS Action
    FROM schools s
    LEFT JOIN candidates c ON s.id = c.school_id AND c.exam_year_id = p_exam_year_id
    WHERE c.id IS NULL
    ORDER BY s.center_number;

    CALL log_action('ListUnregisteredSchools', p_user_id, CONCAT('Retrieved unregistered schools list for exam_year_id: ', p_exam_year_id));
END$$

-- Procedure: UploadDetailedReport
CREATE OR REPLACE PROCEDURE `UploadDetailedReport` (
    IN `p_exam_year_id` INT,
    IN `p_title` VARCHAR(255),
    IN `p_introduction` TEXT,
    IN `p_registration_of_candidates` TEXT,
    IN `p_marking` TEXT,
    IN `p_assessment` TEXT,
    IN `p_performance` TEXT,
    IN `p_challenges` TEXT,
    IN `p_recommendations` TEXT,
    IN `p_way_forward` TEXT,
    IN `p_conclusion` TEXT,
    IN `p_user_id` INT
)
BEGIN
    DECLARE v_appendices JSON;

    -- Validate inputs
    IF p_exam_year_id <= 0 OR p_user_id <= 0 OR p_title IS NULL OR p_introduction IS NULL OR 
       p_registration_of_candidates IS NULL OR p_marking IS NULL OR p_assessment IS NULL OR 
       p_performance IS NULL OR p_way_forward IS NULL OR p_conclusion IS NULL THEN
        CALL log_action('UploadDetailedReport Error', p_user_id, 
            CONCAT('Invalid input: exam_year_id=', p_exam_year_id, 
                   ', user_id=', p_user_id, 
                   ', title=', COALESCE(p_title, 'NULL')));
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid input parameters';
    END IF;

    -- Generate appendices data
    SET v_appendices = JSON_OBJECT(
        'general_schools_performance', (SELECT GenerateGeneralSchoolsPerformance(p_exam_year_id)),
        'subcounty_performance', (SELECT GenerateSubcountyPerformance(p_exam_year_id)),
        'summary_subcounties_performance', (SELECT GenerateSummarySubcountiesPerformance(p_exam_year_id))
    );

    -- Insert report
    INSERT INTO detailed_reports (
        exam_year_id, title, introduction, registration_of_candidates, marking, assessment,
        performance, challenges, recommendations, way_forward, conclusion, appendices, uploaded_by
    )
    VALUES (
        p_exam_year_id, p_title, p_introduction, p_registration_of_candidates, p_marking, 
        p_assessment, p_performance, p_challenges, p_recommendations, p_way_forward, 
        p_conclusion, v_appendices, p_user_id
    );

    CALL log_action('UploadDetailedReport', p_user_id, 
        CONCAT('Uploaded detailed report for exam_year_id: ', p_exam_year_id, ', title: ', p_title));
END$$

-- Procedure: GenerateGeneralSchoolsPerformance
CREATE OR REPLACE PROCEDURE `GenerateGeneralSchoolsPerformance` (
    IN `p_exam_year_id` INT
)
BEGIN
    DECLARE v_result JSON;

    -- Generate JSON array of school performance data
    SET v_result = (
        SELECT JSON_ARRAYAGG(
            JSON_OBJECT(
                'center_number', s.center_number,
                'school_name', s.name,
                'school_type', s.school_type,
                'results_status', COALESCE(s.results_status, 'Not Declared'),
                'total_candidates', (
                    SELECT COUNT(*) 
                    FROM candidates c 
                    WHERE c.school_id = s.id AND c.exam_year_id = p_exam_year_id
                ),
                'candidates_with_four_subjects', (
                    SELECT COUNT(*) 
                    FROM (
                        SELECT m.candidate_id
                        FROM marks m
                        WHERE m.school_id = s.id
                        AND m.exam_year_id = p_exam_year_id
                        AND m.status = 'PRESENT'
                        GROUP BY m.candidate_id
                        HAVING COUNT(DISTINCT m.subject_id) = 4
                    ) AS full_marks
                ),
                'division_counts', (
                    SELECT JSON_OBJECT(
                        'Division 1', SUM(CASE WHEN cr.division = 'Division 1' THEN 1 ELSE 0 END),
                        'Division 2', SUM(CASE WHEN cr.division = 'Division 2' THEN 1 ELSE 0 END),
                        'Division 3', SUM(CASE WHEN cr.division = 'Division 3' THEN 1 ELSE 0 END),
                        'Division 4', SUM(CASE WHEN cr.division = 'Division 4' THEN 1 ELSE 0 END),
                        'Ungraded', SUM(CASE WHEN cr.division = 'Ungraded' THEN 1 ELSE 0 END),
                        'Absentees', SUM(CASE WHEN cr.division = 'X' THEN 1 ELSE 0 END)
                    )
                    FROM candidate_results cr
                    WHERE cr.school_id = s.id AND cr.exam_year_id = p_exam_year_id
                )
            )
        )
        FROM schools s
        WHERE EXISTS (
            SELECT 1 FROM candidates c 
            WHERE c.school_id = s.id AND c.exam_year_id = p_exam_year_id
        )
        ORDER BY s.center_number
    );

    -- Return JSON result (or empty array if null)
    SELECT COALESCE(v_result, JSON_ARRAY());
END$$

-- Procedure: GenerateSubcountyPerformance
CREATE OR REPLACE PROCEDURE `GenerateSubcountyPerformance` (
    IN `p_exam_year_id` INT
)
BEGIN
    DECLARE v_result JSON;

    -- Generate JSON array of subcounty performance data
    SET v_result = (
        SELECT JSON_ARRAYAGG(
            JSON_OBJECT(
                'subcounty_name', sc.name,
                'total_schools', (
                    SELECT COUNT(*) 
                    FROM schools s 
                    WHERE s.subcounty_id = sc.id
                ),
                'registered_schools', (
                    SELECT COUNT(DISTINCT s.id)
                    FROM schools s
                    JOIN candidates c ON s.id = c.school_id
                    WHERE s.subcounty_id = sc.id AND c.exam_year_id = p_exam_year_id
                ),
                'total_candidates', (
                    SELECT COUNT(*) 
                    FROM candidates c
                    JOIN schools s ON c.school_id = s.id
                    WHERE s.subcounty_id = sc.id AND c.exam_year_id = p_exam_year_id
                ),
                'division_counts', (
                    SELECT JSON_OBJECT(
                        'Division 1', SUM(CASE WHEN cr.division = 'Division 1' THEN 1 ELSE 0 END),
                        'Division 2', SUM(CASE WHEN cr.division = 'Division 2' THEN 1 ELSE 0 END),
                        'Division 3', SUM(CASE WHEN cr.division = 'Division 3' THEN 1 ELSE 0 END),
                        'Division 4', SUM(CASE WHEN cr.division = 'Division 4' THEN 1 ELSE 0 END),
                        'Ungraded', SUM(CASE WHEN cr.division = 'Ungraded' THEN 1 ELSE 0 END),
                        'Absentees', SUM(CASE WHEN cr.division = 'X' THEN 1 ELSE 0 END)
                    )
                    FROM candidate_results cr
                    JOIN schools s ON cr.school_id = s.id
                    WHERE s.subcounty_id = sc.id AND cr.exam_year_id = p_exam_year_id
                )
            )
        )
        FROM subcounties sc
        WHERE EXISTS (
            SELECT 1 
            FROM schools s 
            JOIN candidates c ON s.id = c.school_id 
            WHERE s.subcounty_id = sc.id AND c.exam_year_id = p_exam_year_id
        )
        ORDER BY sc.name
    );

    -- Return JSON result (or empty array if null)
    SELECT COALESCE(v_result, JSON_ARRAY());
END$$

-- Procedure: GenerateSummarySubcountiesPerformance
CREATE OR REPLACE PROCEDURE `GenerateSummarySubcountiesPerformance` (
    IN `p_exam_year_id` INT
)
BEGIN
    DECLARE v_result JSON;

    -- Generate JSON object summarizing subcounties performance
    SET v_result = (
        SELECT JSON_OBJECT(
            'total_subcounties', (
                SELECT COUNT(DISTINCT sc.id)
                FROM subcounties sc
                JOIN schools s ON s.subcounty_id = sc.id
                JOIN candidates c ON s.id = c.school_id
                WHERE c.exam_year_id = p_exam_year_id
            ),
            'total_schools', (
                SELECT COUNT(DISTINCT s.id)
                FROM schools s
                JOIN candidates c ON s.id = c.school_id
                WHERE c.exam_year_id = p_exam_year_id
            ),
            'total_candidates', (
                SELECT COUNT(*) 
                FROM candidates c
                WHERE c.exam_year_id = p_exam_year_id
            ),
            'division_counts', (
                SELECT JSON_OBJECT(
                    'Division 1', SUM(CASE WHEN cr.division = 'Division 1' THEN 1 ELSE 0 END),
                    'Division 2', SUM(CASE WHEN cr.division = 'Division 2' THEN 1 ELSE 0 END),
                    'Division 3', SUM(CASE WHEN cr.division = 'Division 3' THEN 1 ELSE 0 END),
                    'Division 4', SUM(CASE WHEN cr.division = 'Division 4' THEN 1 ELSE 0 END),
                    'Ungraded', SUM(CASE WHEN cr.division = 'Ungraded' THEN 1 ELSE 0 END),
                    'Absentees', SUM(CASE WHEN cr.division = 'X' THEN 1 ELSE 0 END)
                )
                FROM candidate_results cr
                WHERE cr.exam_year_id = p_exam_year_id
            )
        )
    );

    -- Return JSON result (or empty object if null)
    SELECT COALESCE(v_result, JSON_OBJECT());
END$$

DELIMITER ;
