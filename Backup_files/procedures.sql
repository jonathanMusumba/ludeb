DELIMITER //

-- Computes subject-level grades and populates results, school_results, subcounty_results
CREATE PROCEDURE ComputeCandidateGrades(
    IN p_candidate_id INT,
    IN p_school_id INT,
    IN p_subcounty_id INT,
    IN p_exam_year_id INT,
    IN p_processed_by INT
)
BEGIN
    DECLARE v_subject_id INT;
    DECLARE v_mark INT;
    DECLARE v_status ENUM('PRESENT', 'ABSENT');
    DECLARE v_grade VARCHAR(2);
    DECLARE v_score INT;
    DECLARE v_subject_code VARCHAR(20);
    DECLARE v_candidate_name VARCHAR(255);
    DECLARE v_candidate_index_number VARCHAR(255);
    DECLARE v_enrolled_subjects INT;
    DECLARE done INT DEFAULT FALSE;

    -- Cursor to iterate over all 4 subjects, including missing/absent
    DECLARE mark_cursor CURSOR FOR
        SELECT s.id, COALESCE(m.mark, 0) AS mark, COALESCE(m.status, 'ABSENT') AS status, s.code
        FROM subjects s
        LEFT JOIN marks m ON m.subject_id = s.id AND m.candidate_id = p_candidate_id AND m.school_id = p_school_id AND m.exam_year_id = p_exam_year_id
        WHERE s.code IN ('ENG', 'MTC', 'SCI', 'SST');

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- Validate candidate enrollment
    SELECT COUNT(*) INTO v_enrolled_subjects
    FROM subject_candidates
    WHERE candidate_id = p_candidate_id AND subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));
    IF v_enrolled_subjects != 4 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Candidate not enrolled in all 4 subjects';
    END IF;

    -- Get candidate details
    SELECT name, index_number INTO v_candidate_name, v_candidate_index_number
    FROM candidates
    WHERE id = p_candidate_id AND exam_year_id = p_exam_year_id;

    OPEN mark_cursor;

    mark_loop: LOOP
        FETCH mark_cursor INTO v_subject_id, v_mark, v_status, v_subject_code;
        IF done THEN
            LEAVE mark_loop;
        END IF;

        -- Determine grade and score
        IF v_status = 'ABSENT' THEN
            SET v_grade = 'X';
            SET v_score = 9; -- Use 9 for consistency, but won't count for passes
        ELSE
            SELECT grade, score INTO v_grade, v_score
            FROM grading
            WHERE v_mark BETWEEN range_from AND range_to
            LIMIT 1;
        END IF;

        -- Insert into results table with exam_year_id
        INSERT INTO results (candidate_id, subject_id, school_id, mark, score, processed_by, exam_year_id)
        VALUES (p_candidate_id, v_subject_id, p_school_id, v_mark, v_score, p_processed_by, p_exam_year_id)
        ON DUPLICATE KEY UPDATE
            mark = v_mark,
            score = v_score,
            updated_by = p_processed_by,
            updated_at = CURRENT_TIMESTAMP,
            exam_year_id = p_exam_year_id;

        -- Insert into school_results table
        INSERT INTO school_results (candidate_id, school_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
        VALUES (p_candidate_id, p_school_id, v_candidate_index_number, v_candidate_name, v_subject_code, v_mark, v_grade, p_exam_year_id)
        ON DUPLICATE KEY UPDATE
            marks = v_mark,
            grade = v_grade,
            updated_at = CURRENT_TIMESTAMP;

        -- Insert into subcounty_results table
        INSERT INTO subcounty_results (candidate_id, subcounty_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
        VALUES (p_candidate_id, p_subcounty_id, v_candidate_index_number, v_candidate_name, v_subject_code, v_mark, v_grade, p_exam_year_id)
        ON DUPLICATE KEY UPDATE
            marks = v_mark,
            grade = v_grade,
            updated_at = CURRENT_TIMESTAMP;
    END LOOP;

    CLOSE mark_cursor;
END //

-- Computes aggregates and divisions, handles absent candidates
CREATE PROCEDURE ComputeCandidateResults(
    IN p_candidate_id INT,
    IN p_school_id INT,
    IN p_subcounty_id INT,
    IN p_exam_year_id INT,
    IN p_processed_by INT
)
BEGIN
    DECLARE v_subject_id INT;
    DECLARE v_mark INT;
    DECLARE v_status ENUM('PRESENT', 'ABSENT');
    DECLARE v_grade VARCHAR(2);
    DECLARE v_score INT;
    DECLARE v_subject_code VARCHAR(20);
    DECLARE v_candidate_name VARCHAR(255);
    DECLARE v_candidate_index_number VARCHAR(255);
    DECLARE v_aggregate INT DEFAULT 0;
    DECLARE v_division VARCHAR(10);
    DECLARE v_english_score INT DEFAULT 9; -- Default F9 if absent
    DECLARE v_math_score INT DEFAULT 9; -- Default F9 if absent
    DECLARE v_pass_count INT DEFAULT 0;
    DECLARE v_present_count INT DEFAULT 0;
    DECLARE v_enrolled_subjects INT;
    DECLARE done INT DEFAULT FALSE;

    -- Cursor to iterate over all 4 subjects, including missing/absent
    DECLARE mark_cursor CURSOR FOR
        SELECT s.id, COALESCE(m.mark, 0) AS mark, COALESCE(m.status, 'ABSENT') AS status, s.code
        FROM subjects s
        LEFT JOIN marks m ON m.subject_id = s.id AND m.candidate_id = p_candidate_id AND m.school_id = p_school_id AND m.exam_year_id = p_exam_year_id
        WHERE s.code IN ('ENG', 'MTC', 'SCI', 'SST');

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- Validate candidate enrollment
    SELECT COUNT(*) INTO v_enrolled_subjects
    FROM subject_candidates
    WHERE candidate_id = p_candidate_id AND subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'));
    IF v_enrolled_subjects != 4 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Candidate not enrolled in all 4 subjects';
    END IF;

    -- Get candidate details
    SELECT name, index_number INTO v_candidate_name, v_candidate_index_number
    FROM candidates
    WHERE id = p_candidate_id AND exam_year_id = p_exam_year_id;

    OPEN mark_cursor;

    mark_loop: LOOP
        FETCH mark_cursor INTO v_subject_id, v_mark, v_status, v_subject_code;
        IF done THEN
            LEAVE mark_loop;
        END IF;

        -- Determine grade and score
        IF v_status = 'ABSENT' THEN
            SET v_grade = 'X';
            SET v_score = 9; -- Use 9 for aggregate but exclude from passes
        ELSE
            SELECT grade, score INTO v_grade, v_score
            FROM grading
            WHERE v_mark BETWEEN range_from AND range_to
            LIMIT 1;
            SET v_aggregate = v_aggregate + v_score;
            IF v_score <= 8 THEN
                SET v_pass_count = v_pass_count + 1;
            END IF;
            SET v_present_count = v_present_count + 1;
        END IF;

        -- Track English and Math scores
        IF v_subject_code = 'ENG' THEN
            SET v_english_score = v_score;
        END IF;
        IF v_subject_code = 'MTC' THEN
            SET v_math_score = v_score;
        END IF;

        -- Insert into results table with exam_year_id
        INSERT INTO results (candidate_id, subject_id, school_id, mark, score, processed_by, exam_year_id)
        VALUES (p_candidate_id, v_subject_id, p_school_id, v_mark, v_score, p_processed_by, p_exam_year_id)
        ON DUPLICATE KEY UPDATE
            mark = v_mark,
            score = v_score,
            updated_by = p_processed_by,
            updated_at = CURRENT_TIMESTAMP,
            exam_year_id = p_exam_year_id;

        -- Insert into school_results table
        INSERT INTO school_results (candidate_id, school_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
        VALUES (p_candidate_id, p_school_id, v_candidate_index_number, v_candidate_name, v_subject_code, v_mark, v_grade, p_exam_year_id)
        ON DUPLICATE KEY UPDATE
            marks = v_mark,
            grade = v_grade,
            updated_at = CURRENT_TIMESTAMP;

        -- Insert into subcounty_results table
        INSERT INTO subcounty_results (candidate_id, subcounty_id, candidate_index_number, candidate_name, subject_code, marks, grade, exam_year_id)
        VALUES (p_candidate_id, p_subcounty_id, v_candidate_index_number, v_candidate_name, v_subject_code, v_mark, v_grade, p_exam_year_id)
        ON DUPLICATE KEY UPDATE
            marks = v_mark,
            grade = v_grade,
            updated_at = CURRENT_TIMESTAMP;
    END LOOP;

    CLOSE mark_cursor;

    -- Check if candidate sat all 4 subjects
    IF v_present_count != 4 THEN
        SET v_aggregate = 'X';
        SET v_division = 'X';
    ELSE
        -- Determine division based on grades table
        SELECT division INTO v_division
        FROM grades
        WHERE v_aggregate BETWEEN aggregate_range_from AND aggregate_range_to
        LIMIT 1;

        -- Apply special conditions
        IF v_aggregate BETWEEN 4 AND 12 THEN
            IF v_english_score = 9 OR v_math_score = 9 THEN
                SET v_division = 'Division 2';
            END IF;
        END IF;

        IF v_aggregate BETWEEN 13 AND 23 THEN
            IF v_english_score = 9 AND v_math_score = 9 THEN
                SET v_division = 'Division 3';
            END IF;
        END IF;

        IF v_aggregate = 29 AND v_pass_count >= 3 AND (v_english_score <= 8 OR v_math_score <= 8) THEN
            SET v_division = 'Division 3';
        END IF;

        IF v_aggregate = 33 AND v_pass_count >= 2 AND (v_english_score <= 8 OR v_math_score <= 8) THEN
            SET v_division = 'Division 4';
        END IF;

        IF v_aggregate BETWEEN 33 AND 36 AND v_pass_count <= 1 THEN
            SET v_division = 'Ungraded';
        END IF;
    END IF;

    -- Insert into candidate_results with exam_year_id
    INSERT INTO candidate_results (candidate_id, school_id, aggregates, division, processed_by, exam_year_id)
    VALUES (p_candidate_id, p_school_id, v_aggregate, v_division, p_processed_by, p_exam_year_id)
    ON DUPLICATE KEY UPDATE
        aggregates = v_aggregate,
        division = v_division,
        updated_by = p_processed_by,
        updated_at = CURRENT_TIMESTAMP,
        exam_year_id = p_exam_year_id;
END //

-- Processes all candidates with 4 present subjects for a given exam year
CREATE PROCEDURE ProcessAllCandidates(
    IN p_exam_year_id INT,
    IN p_processed_by INT
)
BEGIN
    DECLARE v_candidate_id INT;
    DECLARE v_school_id INT;
    DECLARE v_subcounty_id INT;
    DECLARE done INT DEFAULT FALSE;

    -- Cursor to select candidates with 4 present subjects
    DECLARE candidate_cursor CURSOR FOR
        SELECT DISTINCT c.id, c.school_id, s.subcounty_id
        FROM candidates c
        JOIN schools s ON c.school_id = s.id
        JOIN marks m ON m.candidate_id = c.id AND m.exam_year_id = p_exam_year_id
        JOIN subject_candidates sc ON sc.candidate_id = c.id
        WHERE m.status = 'PRESENT' AND c.exam_year_id = p_exam_year_id
        GROUP BY c.id, c.school_id
        HAVING COUNT(DISTINCT m.subject_id) = 4
        AND COUNT(DISTINCT sc.subject_id) = 4;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN candidate_cursor;

    candidate_loop: LOOP
        FETCH candidate_cursor INTO v_candidate_id, v_school_id, v_subcounty_id;
        IF done THEN
            LEAVE candidate_loop;
        END IF;

        -- Process grades and results
        CALL ComputeCandidateGrades(v_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, p_processed_by);
        CALL ComputeCandidateResults(v_candidate_id, v_school_id, v_subcounty_id, p_exam_year_id, p_processed_by);
    END LOOP;

    CLOSE candidate_cursor;

    -- Handle candidates with fewer than 4 subjects or not enrolled
    INSERT INTO candidate_results (candidate_id, school_id, aggregates, division, processed_by, exam_year_id)
    SELECT c.id, c.school_id, 'X', 'X', p_processed_by, p_exam_year_id
    FROM candidates c
    LEFT JOIN (
        SELECT candidate_id, COUNT(*) AS subject_count
        FROM marks
        WHERE status = 'PRESENT' AND exam_year_id = p_exam_year_id
        GROUP BY candidate_id
        HAVING subject_count = 4
    ) m ON c.id = m.candidate_id
    LEFT JOIN (
        SELECT candidate_id, COUNT(*) AS enrolled_count
        FROM subject_candidates
        WHERE subject_id IN (SELECT id FROM subjects WHERE code IN ('ENG', 'MTC', 'SCI', 'SST'))
        GROUP BY candidate_id
        HAVING enrolled_count = 4
    ) sc ON c.id = sc.candidate_id
    WHERE c.exam_year_id = p_exam_year_id
    AND (m.candidate_id IS NULL OR sc.candidate_id IS NULL)
    ON DUPLICATE KEY UPDATE
        aggregates = 'X',
        division = 'X',
        updated_by = p_processed_by,
        updated_at = CURRENT_TIMESTAMP,
        exam_year_id = p_exam_year_id;
END //

DELIMITER ;