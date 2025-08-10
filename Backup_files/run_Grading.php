<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate aggregates and other necessary details
function calculateAggregates($candidate_id) {
    global $conn;

    $marks_query = "SELECT m.mark, g.score, s.Name 
                    FROM marks m
                    JOIN grading g ON m.mark BETWEEN g.range_from AND g.range_to
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
    $has_absence = false;

    while ($row = $result->fetch_assoc()) {
        if ($row['mark'] === -1) {
            $has_absence = true;
            break; // No need to process further if any mark is -1
        } else {
            $total_aggregate += $row['score'];
            if ($row['Name'] == 'English') {
                $english_score = $row['score'];
            }
            if ($row['Name'] == 'Mathematics') {
                $math_score = $row['score'];
            }
            $subject_count++;
        }
    }

    // Set total aggregate to 0 if there's any absence
    if ($has_absence) {
        $total_aggregate = 0;
    }

    return [
        'total_aggregate' => $total_aggregate,
        'english_score' => $english_score,
        'math_score' => $math_score,
        'subject_count' => $subject_count,
        'has_absence' => $has_absence
    ];
}

// Function to determine division based on aggregates and scores
function calculateDivision($candidate_id) {
    global $conn;

    $aggregates = calculateAggregates($candidate_id);

    $total_aggregate = $aggregates['total_aggregate'];
    $english_score = $aggregates['english_score'];
    $math_score = $aggregates['math_score'];
    $subject_count = $aggregates['subject_count'];
    $has_absence = $aggregates['has_absence'];

    // Division determination logic
    if ($has_absence || $subject_count < 4) {
        return 'X'; // Absence in any subject or less than 4 subjects
    } elseif ($total_aggregate >= 4 && $total_aggregate <= 12) {
        if ($english_score < 7 && $math_score <= 8) {
            return '1';
        } elseif ($english_score == 8 || $math_score == 9) {
            return '2';
        } elseif ($english_score == 9) {
            return '3';
        }
    } elseif ($total_aggregate >= 13 && $total_aggregate <= 24) {
        return ($english_score <= 8) ? '2' : '3';
    } elseif ($total_aggregate >= 25 && $total_aggregate <= 28) {
        return ($english_score <= 8) ? '3' : '4';
    } elseif ($total_aggregate == 29) {
        if ($english_score <= 6) {
            return '3';
        } else {
            return '4';
        }
    } elseif ($total_aggregate >= 30 && $total_aggregate <= 32) {
        return '4';
    } elseif ($total_aggregate == 33) {
        if ($english_score < 8 && $math_score < 9) {
            return '4';
        } else {
            return 'U';
        }
    } elseif ($total_aggregate > 33) {
        return 'U';
    }

    return 'X'; // Default to 'X' if none of the conditions are met
}

// Update aggregates and division for all candidates
$candidates_query = "SELECT DISTINCT candidate_id FROM marks";
$candidates_result = $conn->query($candidates_query);

while ($row = $candidates_result->fetch_assoc()) {
    $candidate_id = $row['candidate_id'];

    // Calculate aggregates and division
    $aggregates = calculateAggregates($candidate_id);
    $division = calculateDivision($candidate_id);

    // Update the marks table with aggregates and division
    $update_query = "
        UPDATE marks
        SET Aggregates = ?, Division = ?
        WHERE candidate_id = ?
    ";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("isi", $aggregates['total_aggregate'], $division, $candidate_id);
    $stmt->execute();
}

// Close connection
$stmt->close();
$conn->close();
?>
