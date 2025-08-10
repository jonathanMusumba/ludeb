function calculateDivision($candidate_id) {
    $aggregates = calculateAggregates($candidate_id);

    $total_aggregate = $aggregates['total_aggregate'];
    $english_score = $aggregates['english_score'];
    $math_score = $aggregates['math_score'];
    $subject_count = $aggregates['subject_count'];
    $has_zero_mark = $aggregates['has_zero_mark'];

    $division = '';

    if ($subject_count < 4 || $has_zero_mark) {
        $division = 'X'; // Or 'U' depending on the rules
    } elseif ($total_aggregate >= 4 && $total_aggregate <= 12) {
        if ($english_score < 7 && $math_score <= 8) {
            $division = '1';
        } elseif ($english_score == 8 || $math_score == 9) {
            $division = '2';
        } elseif ($english_score == 9) {
            $division = '3';
        }
    } elseif ($total_aggregate >= 13 && $total_aggregate <= 24) {
        $division = ($english_score < 7 && $math_score <= 8) ? '2' : '3';
    } elseif ($total_aggregate >= 24 && $total_aggregate <= 28) {
        if ($total_aggregate == 29 && $english_score <= 6) {
            $division = '3';
        } else {
            $division = '4';
        }
    } elseif ($total_aggregate == 33) {
        if ($english_score <= 6 || $math_score <= 6) {
            $division = '4';
        } else {
            $division = 'U';
        }
    } elseif ($total_aggregate > 32) {
        $division = 'U';
    }

    return $division;
}
