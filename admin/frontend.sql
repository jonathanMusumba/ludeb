```

### Key Fixes and Changes

1. **Fixed Syntax Error**:
   - Removed the duplicate `detailed_reports` table definition at the end of the script to prevent conflicts.
   - Ensured `DELIMITER $$` is correctly set before all procedure definitions and `DELIMITER ;` at the end, resolving the `#1064` syntax error.

2. **Updated `UploadDetailedReport`**:
   - Modified to match the new `detailed_reports` table structure, accepting parameters for `introduction`, `registration_of_candidates`, `marking`, `assessment`, `performance`, `way_forward`, and `conclusion`.
   - Generates the `appendices` JSON by calling `GenerateGeneralSchoolsPerformance`, `GenerateSubcountyPerformance`, and `GenerateSummarySubcountiesPerformance`.
   - Validates all required fields (except `challenges` and `recommendations`) to ensure they are not null.

3. **Fixed `correct_candidate_marks`**:
   - Corrected the `INSERT INTO results` statement, which incorrectly used `v_subject_id` instead of `p_subject_id` in the `VALUES` clause. This ensures the correct subject ID is used when updating the `results` table.

4. **Aligned Comment in `ComputeCandidateResults`**:
   - Updated the comment from "if fewer than 3 subjects" to "if fewer than 4 subjects" to match the logic (`IF v_subject_count < 4`) for clarity and consistency.

5. **Retained and Updated Procedures**:
   - All procedures (`ComputeCandidateGrades`, `ComputeCandidateResults`, `correct_candidate_marks`, `delete_candidate_mark`, `initialize_results_from_marks`, `log_action`, `ProcessAllCandidateResults`, `ProcessAllCandidates`, `UpdateSchoolResultsStatus`, `update_all_results`, `ListSchools`, `ListRegisteredSchools`, `ListUnregisteredSchools`) are included with `CREATE OR REPLACE` to ensure they can be updated without errors.
   - Added the three helper procedures (`GenerateGeneralSchoolsPerformance`, `GenerateSubcountyPerformance`, `GenerateSummarySubcountiesPerformance`) to populate the `appendices` JSON in `detailed_reports`.

6. **Appendices JSON Structure**:
   - The `appendices` field in `detailed_reports` stores:
     - `general_schools_performance`: Array of school data (center number, name, type, results status, total candidates, candidates with four subjects, division counts).
     - `subcounty_performance`: Array of subcounty data (name, total schools, registered schools, total candidates, division counts).
     - `summary_subcounties_performance`: Object with district-wide totals (subcounties, schools, candidates, division counts).
   - Example JSON:
     ```json
     {
       "general_schools_performance": [
         {
           "center_number": "1234",
           "school_name": "Example School",
           "school_type": "Public",
           "results_status": "Declared",
           "total_candidates": 50,
           "candidates_with_four_subjects": 45,
           "division_counts": {
             "Division 1": 10,
             "Division 2": 15,
             "Division 3": 10,
             "Division 4": 5,
             "Ungraded": 0,
             "Absentees": 5
           }
         },
         ...
       ],
       "subcounty_performance": [
         {
           "subcounty_name": "Example Subcounty",
           "total_schools": 10,
           "registered_schools": 8,
           "total_candidates": 400,
           "division_counts": {
             "Division 1": 80,
             "Division 2": 120,
             "Division 3": 100,
             "Division 4": 50,
             "Ungraded": 20,
             "Absentees": 30
           }
         },
         ...
       ],
       "summary_subcounties_performance": {
         "total_subcounties": 5,
         "total_schools": 40,
         "total_candidates": 2000,
         "division_counts": {
           "Division 1": 400,
           "Division 2": 600,
           "Division 3": 500,
           "Division 4": 300,
           "Ungraded": 100,
           "Absentees": 100
         }
       }
     }
     ```

### Implementation Notes

- **Frontend Integration**:
  - **Uploading Reports**:
    - Create a form with fields for `title`, `introduction`, `registration_of_candidates`, `marking`, `assessment`, `performance`, `challenges`, `recommendations`, `way_forward`, and `conclusion`.
    - Call `UploadDetailedReport`:
      ```php
      $stmt = $pdo->prepare("CALL UploadDetailedReport(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->execute([$exam_year_id, $title, $introduction, $registration_of_candidates, $marking, $assessment, $performance, $challenges, $recommendations, $way_forward, $conclusion, $user_id]);
      ```
  - **Displaying Reports**:
    - Query `detailed_reports`:
      ```sql
      SELECT id, title, introduction, registration_of_candidates, marking, assessment, performance, challenges, recommendations, way_forward, conclusion, appendices
      FROM detailed_reports
      WHERE exam_year_id = ?;
      ```
    - Parse the `appendices` JSON to display tables for General Schools Performance, Subcounty Performance, and Summary of Subcounties Performance.

- **LaTeX Template for Reports**:
  - Update the LaTeX template to include the new sections and appendices:
    ```latex
    \documentclass[a4paper,12pt]{article}
    \usepackage[utf8]{inputenc}
    \usepackage[T1]{fontenc}
    \usepackage{booktabs}
    \usepackage{geometry}
    \usepackage{pdflscape}
    \usepackage{noto}

    \geometry{margin=1in}

    \begin{document}

    % Title Page
    \begin{center}
        \textbf{\Large District Mock Results} \\
        \vspace{0.5cm}
        \textbf{Board Name: [Board Name]} \\
        \textbf{Mock Results [Active Year]} \\
        \vspace{1cm}
    \end{center}

    % Introduction
    \section*{Introduction}
    [Introduction Text]

    % Registration of Candidates
    \section*{Registration of Candidates}
    [Registration Text]

    % Marking
    \section*{Marking}
    [Marking Text]

    % Assessment
    \section*{Assessment}
    [Assessment Text]

    % Performance
    \section*{Performance}
    [Performance Text]

    % Challenges
    \section*{Challenges}
    [Challenges Text]

    % Recommendations
    \section*{Recommendations}
    [Recommendations Text]

    % Way Forward
    \section*{Way Forward}
    [Way Forward Text]

    % Conclusion
    \section*{Conclusion}
    [Conclusion Text]

    % Appendices
    \section*{Appendices}
    \subsection*{General Schools Performance}
    \begin{landscape}
    \begin{tabular}{l l l l r r r r r r r}
        \toprule
        \textbf{Center} & \textbf{School} & \textbf{Type} & \textbf{Status} & \textbf{Candidates} & \textbf{4 Subjects} & \textbf{Div 1} & \textbf{Div 2} & \textbf{Div 3} & \textbf{Div 4} & \textbf{Absentees} \\
        \midrule
        \foreach \school in {[general_schools_performance]} {
            \school.center_number & \school.school_name & \school.school_type & \school.results_status & \school.total_candidates & \school.candidates_with_four_subjects & \school.division_counts.Division 1 & \school.division_counts.Division 2 & \school.division_counts.Division 3 & \school.division_counts.Division 4 & \school.division_counts.Absentees \\
        }
        \bottomrule
    \end{tabular}
    \end{landscape}

    \subsection*{Subcounty Performance}
    \begin{landscape}
    \begin{tabular}{l r r r r r r r r}
        \toprule
        \textbf{Subcounty} & \textbf{Total Schools} & \textbf{Reg. Schools} & \textbf{Candidates} & \textbf{Div 1} & \textbf{Div 2} & \textbf{Div 3} & \textbf{Div 4} & \textbf{Absentees} \\
        \midrule
        \foreach \subcounty in {[subcounty_performance]} {
            \subcounty.subcounty_name & \subcounty.total_schools & \subcounty.registered_schools & \subcounty.total_candidates & \subcounty.division_counts.Division 1 & \subcounty.division_counts.Division 2 & \subcounty.division_counts.Division 3 & \subcounty.division_counts.Division 4 & \subcounty.division_counts.Absentees \\
        }
        \bottomrule
    \end{tabular}
    \end{landscape}

    \subsection*{Summary of Subcounties Performance}
    \begin{tabular}{r r r r r r r r}
        \toprule
        \textbf{Subcounties} & \textbf{Schools} & \textbf{Candidates} & \textbf{Div 1} & \textbf{Div 2} & \textbf{Div 3} & \textbf{Div 4} & \textbf{Absentees} \\
        \midrule
        [summary_subcounties_performance.total_subcounties] & [summary_subcounties_performance.total_schools] & [summary_subcounties_performance.total_candidates] & [summary_subcounties_performance.division_counts.Division 1] & [summary_subcounties_performance.division_counts.Division 2] & [summary_subcounties_performance.division_counts.Division 3] & [summary_subcounties_performance.division_counts.Division 4] & [summary_subcounties_performance.division_counts.Absentees] \\
        \bottomrule
    \end{tabular}

    \end{document}
    ```
  - Replace placeholders (e.g., `[Introduction Text]`, `[general_schools_performance]`) with data from `detailed_reports`.

- **Database Assumptions**:
  - Tables: `exam_years`, `system_users`, `schools`, `candidates`, `marks`, `results`, `school_results`, `subcounty_results`, `subjects`, `grades`, `subcounties`, `audit_logs`.
  - MariaDB 10.2 or later for JSON functions (`JSON_ARRAYAGG`, `JSON_OBJECT`, etc.).
  - `subjects` has a `code` column (e.g., 'ENG', 'MTC', 'SCI', 'SST').
  - `candidate_results` has a `division` column with values like 'Division 1', 'X', etc.

### Testing Recommendations

1. **Execute the Script**:
   - Run the SQL script in your MariaDB environment:
     ```sql
     source school_management_procedures.sql;
     ```
   - Verify no syntax errors occur.

2. **Test `detailed_reports` Table**:
   - Check the table structure:
     ```sql
     DESCRIBE detailed_reports;
     ```

3. **Test `UploadDetailedReport`**:
   - Call with sample data:
     ```sql
     CALL UploadDetailedReport(
         1, 
         '2025 Mock Report', 
         'Introduction text...', 
         'Registration details...', 
         'Marking process...', 
         'Assessment methods...', 
         'Performance overview...', 
         'Challenges faced...', 
         'Recommendations for improvement...', 
         'Way forward actions...', 
         'Conclusion text...', 
         1
     );
     ```
   - Verify the data:
     ```sql
     SELECT * FROM detailed_reports WHERE exam_year_id = 1;
     ```
   - Check the `appendices` JSON for valid data from the helper procedures.

4. **Test Helper Procedures**:
   - Call each individually:
     ```sql
     CALL GenerateGeneralSchoolsPerformance(1);
     CALL GenerateSubcountyPerformance(1);
     CALL GenerateSummarySubcountiesPerformance(1);
     ```
   - Ensure they return valid JSON with expected fields and counts.

5. **Test `correct_candidate_marks`**:
   - Insert a mark:
     ```sql
     CALL correct_candidate_marks(1, 1, 1, 85, 1);
     ```
   - Verify the `results` table is updated with the correct `subject_id`.

6. **Test LaTeX Compilation**:
   - Query a report, extract the `appendices` JSON, and populate the LaTeX template.
   - Compile with:
     ```bash
     latexmk -pdf report.tex
     ```
   - Ensure tables render correctly.

7. **Check Indexes**:
   - Add indexes for performance:
     ```sql
     CREATE INDEX idx_marks_candidate_exam ON marks(candidate_id, exam_year_id, school_id, status);
     CREATE INDEX idx_results_candidate_exam ON results(candidate_id, exam_year_id);
     CREATE INDEX idx_candidate_results_school_exam ON candidate_results(school_id, exam_year_id, division);
     ```

### Additional Notes

- **Performance**: The JSON-generating procedures may be slow for large datasets. Optimize by ensuring indexes and testing with realistic data.
- **Error Handling**: All procedures log errors to `audit_logs`. Check logs if issues arise:
  ```sql
  SELECT * FROM audit_logs WHERE action LIKE '%Error%';
  ```
- **Frontend**: Parse the `appendices` JSON in the frontend (e.g., PHP with `json_decode`) to display tables or generate charts.

If you need additional procedures, a specific LaTeX template, or frontend code snippets, let me know!