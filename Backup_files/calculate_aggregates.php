function calculateAggregates($candidate_id) {
    global $conn;

    $marks_query = "SELECT m.mark, g.grade_score, s.subject_name 
                    FROM marks m
                    JOIN grades g ON m.mark BETWEEN g.range_start AND g.range_end
                    JOIN subjects s ON m.subject_id = s.id
                    WHERE m.candidate_id = ?";

    $stmt = $conn->prepare($marks_query);
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $total_aggregate = 0;
    $english_score = null;
    $math_score = null;
    $subject_count = 0;
    $has_zero_mark = false;

    while ($row = $result->fetch_assoc()) {
        if ($row['mark'] === 0) {
            $has_zero_mark = true;
        }
        $total_aggregate += $row['grade_score'];
        if ($row['subject_name'] == 'English') {
            $english_score = $row['grade_score'];
        }
        if ($row['subject_name'] == 'Mathematics') {
            $math_score = $row['grade_score'];
        }
        $subject_count++;
    }

    return [
        'total_aggregate' => $total_aggregate,
        'english_score' => $english_score,
        'math_score' => $math_score,
        'subject_count' => $subject_count,
        'has_zero_mark' => $has_zero_mark
    ];
}
