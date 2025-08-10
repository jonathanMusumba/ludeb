// Step 1: Fetch Candidate Data
$candidates_query = "SELECT c.IndexNo, c.Candidate_Name, c.Gender, m.subject_id, m.mark 
                     FROM candidates c 
                     LEFT JOIN marks m ON c.id = m.candidate_id 
                     WHERE c.school_id = ? AND c.exam_year = ?";
$stmt = $conn->prepare($candidates_query);
$stmt->bind_param("ii", $school_id, $exam_year_id); // Use the correct variables for school_id and exam_year
$stmt->execute();
$candidates_result = $stmt->get_result();

$candidates_data = [];
while ($row = $candidates_result->fetch_assoc()) {
    $candidates_data[$row['IndexNo']]['info'] = [
        'Candidate_Name' => $row['Candidate_Name'],
        'Gender' => $row['Gender']
    ];
    $candidates_data[$row['IndexNo']]['marks'][$row['subject_id']] = $row['mark'];
}

// Step 2: Fetch Subjects
$subjects_query = "SELECT id, subject_code, subject_name FROM subjects";
$subjects_result = $conn->query($subjects_query);
$subjects = [];
while ($subject = $subjects_result->fetch_assoc()) {
    $subjects[$subject['id']] = $subject;
}

// Step 3: Compute Grades and Aggregates (This part involves more detailed logic based on your grading criteria)
// Assuming you have a function getGrade($mark) that returns the grade and grade score

foreach ($candidates_data as &$candidate) {
    $total_aggregates = 0;
    $grades = [];

    foreach ($candidate['marks'] as $subject_id => $mark) {
        list($grade, $grade_score) = getGrade($mark); // Example function to determine grade
        $grades[$subject_id] = ['mark' => $mark, 'grade' => $grade];
        $total_aggregates += $grade_score;
    }

    $candidate['grades'] = $grades;
    $candidate['aggregates'] = $total_aggregates;

    // Step 4: Determine Division based on aggregates and specific criteria
    $candidate['division'] = determineDivision($candidate['grades'], $total_aggregates); // Example function for division determination
}

// Step 5: Display the Results
echo "<table class='table'>";
echo "<thead>
        <tr>
            <th>Index Number</th>
            <th>Candidate Name</th>
            <th>Gender</th>";
foreach ($subjects as $subject) {
    echo "<th>{$subject['subject_code']}</th>"; // Or use $subject['subject_name']
}
echo "<th>Aggregates</th>";
echo "<th>Division</th>";
echo "</tr>
      </thead>";
echo "<tbody>";

foreach ($candidates_data as $index_no => $candidate) {
    echo "<tr>";
    echo "<td>{$index_no}</td>";
    echo "<td>{$candidate['info']['Candidate_Name']}</td>";
    echo "<td>{$candidate['info']['Gender']}</td>";
    foreach ($subjects as $subject_id => $subject) {
        $mark = $candidate['grades'][$subject_id]['mark'] ?? 'X'; // 'X' for missing marks
        $grade = $candidate['grades'][$subject_id]['grade'] ?? 'X';
        echo "<td>{$mark} ({$grade})</td>";
    }
    echo "<td>{$candidate['aggregates']}</td>";
    echo "<td>{$candidate['division']}</td>";
    echo "</tr>";
}
echo "</tbody>";
echo "</table>";
