<?php
ini_set('max_execution_time', 300);
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role']; // Ensure this is set in the session

if ($user_role !== 'System Admin') {
    die("Access denied: You must be a System Admin to perform this action.");
}

$admin_user_id = $user_id; // Use the logged-in user's ID
;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate aggregates and other necessary details
function calculateAggregates($candidate_id) {
    global $conn;

    $marks_query = "SELECT m.subject_id, m.mark, g.score, s.Name 
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

    $subject_grades = [];

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

            // Store the subject grade details
            $subject_grades[] = [
                'subject_id' => $row['subject_id'],
                'mark' => $row['mark'],
                'score' => $row['score'],
            ];
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
        'has_absence' => $has_absence,
        'subject_grades' => $subject_grades
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

// Function to process marks and transfer to the Results table
// Set the batch size (e.g., 20 candidates per batch)
$batch_size = 500; // Process 100 candidates per batch
$offset = isset($_SESSION['processed_count']) ? $_SESSION['processed_count'] : 0;

// Add an offset parameter to the function to handle batching
function processMarksToResults($admin_user_id, $offset, $batch_size) {
    global $conn;

    // Fetch candidates in the batch
    $candidates_query = "SELECT DISTINCT candidate_id, school_id 
                         FROM marks 
                         LIMIT ?, ?";
    $stmt = $conn->prepare($candidates_query);
    $stmt->bind_param("ii", $offset, $batch_size);
    $stmt->execute();
    $candidates_result = $stmt->get_result();

    $processed_count = 0;

    while ($candidate = $candidates_result->fetch_assoc()) {
        $candidate_id = $candidate['candidate_id'];
        $school_id = $candidate['school_id'];

        // Calculate aggregate and division for the candidate
        $aggregates = calculateAggregates($candidate_id);
        $division = calculateDivision($candidate_id);
        $total_aggregate = $aggregates['total_aggregate'];

        // Insert subject-specific results into the results table
        foreach ($aggregates['subject_grades'] as $subject_grade) {
            $insert_subject_query = "INSERT INTO results (candidate_id, subject_id, mark, score, processed_at, updated_at, processed_by, updated_by, school_id, aggregates, division) 
                                     VALUES (?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?)
                                     ON DUPLICATE KEY UPDATE 
                                     mark = VALUES(mark),
                                     score = VALUES(score),
                                     updated_at = NOW(),
                                     updated_by = VALUES(updated_by),
                                     aggregates = VALUES(aggregates),
                                     division = VALUES(division)";

            $insert_subject_stmt = $conn->prepare($insert_subject_query);
            $insert_subject_stmt->bind_param("iiiiiiiis", 
                $candidate_id, 
                $subject_grade['subject_id'], 
                $subject_grade['mark'], 
                $subject_grade['score'], 
                $admin_user_id, 
                $admin_user_id, 
                $school_id, 
                $total_aggregate, 
                $division
            );
            $insert_subject_stmt->execute();
        }

        $processed_count++;
    }

    // Return the count of processed candidates in this batch
    return $processed_count;
}

// Fetch the total number of distinct candidates
$total_candidates_query = "SELECT COUNT(DISTINCT candidate_id) AS total_candidates FROM marks";
$total_candidates_result = $conn->query($total_candidates_query);
$total_candidates_row = $total_candidates_result->fetch_assoc();
$total_candidates = $total_candidates_row['total_candidates'];

// Store the total candidates count in the session
$_SESSION['total_candidates'] = $total_candidates;

// Process candidates in batches
$offset = 0;
while ($offset < $total_candidates) {
    $processed_count = processMarksToResults($admin_user_id, $offset, $batch_size);
    $offset += $batch_size;

    // Store the progress
    file_put_contents("progress.json", json_encode([
        'processed_count' => $offset,
        'total_candidates' => $total_candidates,
        'message' => "Processed up to candidate ID: " . ($offset)
    ]));

    // Flush the output buffer
    ob_flush();
    flush();

    // Optionally, add a short sleep to allow for UI updates
    sleep(1);
}

// Final update when processing is complete
file_put_contents("progress.json", json_encode([
    'processed_count' => $total_candidates,
    'total_candidates' => $total_candidates,
    'message' => "Update Complete"
]));


// Assuming $admin_user_id is fetched from session or other means
$admin_user_id = $user_id; // Use the logged-in user's ID from session

// Call the function to process marks
processMarksToResults($admin_user_id, $offset, $batch_size);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Progress</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
       .progress-container {
    width: 100%;
    background-color: #f3f3f3;
    border-radius: 5px;
    overflow: hidden;
    margin: 20px 0;
}

.progress-bar {
    width: 0;
    height: 30px;
    background-color: #4caf50;
    text-align: center;
    line-height: 30px;
    color: white;
    border-radius: 5px;
}

    </style>
</head>
<body>
<div class="progress-container">
    <div id="progress-bar" class="progress-bar" style="width: 0%;"></div>
</div>
<p id="progress-status">Starting processing...</p>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Start checking the progress immediately after the page loads
    checkProgress();

    function checkProgress() {
        // Fetch the progress JSON file
        fetch("progress.json")
            .then(response => response.json())
            .then(data => {
                // Update the progress bar and status text based on the progress data
                updateProgress(data);
            })
            .catch(error => {
                console.error("Error fetching progress:", error);
            });
    }

    function updateProgress(data) {
        const progressBar = document.getElementById("progress-bar");
        const progressStatus = document.getElementById("progress-status");

        if (data.total_candidates > 0) {
            // Calculate percentage of candidates processed
            const percentage = Math.round((data.processed_count / data.total_candidates) * 100);

            // Update progress bar width and text
            progressBar.style.width = percentage + "%";
            progressBar.innerText = percentage + "%";

            // Update status text
            progressStatus.innerText = `${data.message} (${data.processed_count}/${data.total_candidates})`;

            // Continue checking progress if not yet complete
            if (data.processed_count < data.total_candidates) {
                setTimeout(checkProgress, 10000); // Check progress every 2 seconds
            } else {
                // If processing is complete, show a completion message
                progressStatus.innerText = "Processing complete!";
            }
        } else {
            progressStatus.innerText = "No candidates to process.";
        }
    }
});
</script>


</body>
</html>
