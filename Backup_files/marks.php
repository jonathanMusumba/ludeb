<?php
session_start();
$user_id = $_SESSION['user_id']; 
// Check if the user is logged in and has a role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_marks'])) {
    $school_id = $_POST['school_id'];
    $subject_id = $_POST['subject_id'];
    $current_user_id = $_SESSION['user_id'];

    foreach ($_POST['marks'] as $candidate_id => $mark) {
        // Determine if the mark is an absence
        $mark = intval($mark); // Ensure mark is an integer
        if ($mark === 0) {
            $mark = -1; // Use -1 to denote "Absent"
        }

        // Check if mark already exists
        $check_sql = "SELECT * FROM marks WHERE candidate_id = ? AND subject_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $candidate_id, $subject_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            // Insert new mark
            $insert_sql = "INSERT INTO marks (candidate_id, subject_id, mark, submitted_at, submitted_by, school_id) VALUES (?, ?, ?, NOW(), ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("iiisi", $candidate_id, $subject_id, $mark, $current_user_id, $school_id);
            $stmt->execute();
        } else {
            // Update existing mark
            $update_sql = "UPDATE marks SET mark = ?, updated_at = NOW(), edited_by = ? WHERE candidate_id = ? AND subject_id = ?";
            $stmt->prepare($update_sql);
            $stmt->bind_param("iiii", $mark, $current_user_id, $candidate_id, $subject_id);
            $stmt->execute();
        }
    }

    // Redirect to the same page with a success message to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?school_id=" . urlencode($school_id) . "&status=success");
    exit();
}

        $subject_count_sql = "SELECT COUNT(DISTINCT subject_id) AS subject_count FROM marks WHERE school_id = ?";
        $stmt = $conn->prepare($subject_count_sql);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $subject_count_result = $stmt->get_result();
        $subject_count_row = $subject_count_result->fetch_assoc();
        $subject_count = $subject_count_row['subject_count'];

        // Update resultsStatus if subject count is 4
        if ($subject_count >= 4) {
            $update_school_sql = "UPDATE schools SET resultsStatus = 'Declared' WHERE id = ?";
            $stmt = $conn->prepare($update_school_sql);
            $stmt->bind_param("i", $school_id);
            if ($stmt->execute()) {
                echo "<div class='alert alert-success'>School status updated to Declared!</div>";
            } else {
                echo "<div class='alert alert-danger'>Failed to update school status.</div>";
            }
        }



// Fetch current year for exam year
$current_year = date('Y');

// Fetch schools for selection
$schools = $conn->query("SELECT id, school_Name FROM schools");
if ($schools->num_rows == 0) {
    die("No schools found.");
}

// Fetch subjects for selection
$subjects = $conn->query("SELECT id, Name FROM subjects");

// Fetch candidates based on selected school
$candidates = [];
if (isset($_GET['school_id']) && isset($_GET['subject_id'])) {
    $school_id = intval($_GET['school_id']);
    $subject_id = intval($_GET['subject_id']);
    $candidates_result = $conn->query("SELECT id, Candidate_Name, IndexNo FROM candidates WHERE school_id = $school_id");
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[$row['id']] = ['name' => $row['Candidate_Name'], 'index_number' => $row['IndexNo']];
    }
}

// Search candidates for autocomplete
$search_candidates = [];
if (isset($_GET['term'])) {
    $term = $conn->real_escape_string($_GET['term']);
    // Search by candidate_name or IndexNo
    $search_sql = "SELECT id, Candidate_Name, IndexNo 
                    FROM candidates 
                    WHERE Candidate_Name LIKE '%$term%' OR IndexNo LIKE '%$term%'";
    $search_result = $conn->query($search_sql);
    while ($row = $search_result->fetch_assoc()) {
        $search_candidates[] = [
            'id' => $row['id'],
            'name' => $row['Candidate_Name'],
            'index_number' => $row['IndexNo']
        ];
    }
    echo json_encode($search_candidates);
    exit;
}
// Fetch candidates based on selected school
$candidates = [];
if (isset($_GET['school_id']) && isset($_GET['subject_id'])) {
    $school_id = intval($_GET['school_id']);
    $subject_id = intval($_GET['subject_id']);
    $search_term = $_GET['term'] ?? ''; // Get search term from query params

    $candidates_result = $conn->query(
        "SELECT id, Candidate_Name, IndexNo 
         FROM candidates 
         WHERE school_id = $school_id 
         AND (Candidate_Name LIKE '%$search_term%' OR IndexNo LIKE '%$search_term%')"
    );
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[$row['id']] = ['name' => $row['Candidate_Name'], 'index_number' => $row['IndexNo']];
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Enter Marks</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        th {
            background-color: #f8f9fa;
            cursor: pointer;
        }
    </style>
</head>
<body>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="home.php">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Enter Marks</li>
    </ol>
</nav>

<div class="container mt-5">
    <h1 class="mb-4">Enter Marks - Exam Year: <?= $current_year ?></h1>
    <div class="container mt-5">
    <form method="GET" action="">
        <div class="form-group">
            <label for="school_name">School Name:</label>
            <input id="school_name" name="school_name" class="form-control" placeholder="Start typing school name..." autocomplete="off">
            <input type="hidden" id="school_id" name="school_id">
        </div>
    </form>
        </div>

    <form method="GET" action="">
        <input type="hidden" name="school_id" value="<?= htmlspecialchars($_GET['school_id'] ?? '') ?>">
        <div class="form-group">
            <label for="subject_id">Select Subject:</label>
            <select id="subject_id" name="subject_id" onchange="this.form.submit()" required>
                <option value="">Select a subject</option>
                <?php while($row = $subjects->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= (isset($_GET['subject_id']) && $_GET['subject_id'] == $row['id']) ? 'selected' : '' ?>>
                        <?= $row['Name'] ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
    </form>

    <button onclick="location.reload();" class="btn btn-secondary mb-3">Reload</button>

    <form method="POST" action="">
    <div class="form-group">
        <label for="search_student">Search Student:</label>
        <input type="hidden" id="school_id" value="">
        <input type="text" id="search_student" class="form-control" placeholder="Enter candidate name or Index Number">
    </div>

        <?php if (!empty($candidates)): ?>
            <table class="table table-bordered" id="marksTable">
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">Index Number</th>
                        <th onclick="sortTable(1)">Candidate Name</th>
                        <th>Mark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidates as $candidate_id => $candidate_info): ?>
                        <tr id="candidate_<?= $candidate_id ?>">
                            <td><?= htmlspecialchars($candidate_info['index_number']) ?></td>
                            <td><?= htmlspecialchars($candidate_info['name']) ?></td>
                            <td>
                                <input type="number" 
                                    name="marks[<?= $candidate_id ?>]" 
                                    class="form-control mark-input" 
                                    min="0" 
                                    max="100" 
                                    data-candidate-id="<?= $candidate_id ?>"
                                    onchange="saveMark(this, <?= $candidate_id ?>)"
                                    >
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <input type="hidden" name="school_id" value="<?= htmlspecialchars($_GET['school_id']) ?>">
            <input type="hidden" name="subject_id" value="<?= htmlspecialchars($_GET['subject_id']) ?>">
            <button type="submit" name="submit_marks" class="btn btn-primary">Submit Marks</button>
        <?php else: ?>
            <p>No candidates available for the selected school.</p>
        <?php endif; ?>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script>
$(document).ready(function () {
    // Load saved marks from local storage when the page loads
    $('.mark-input').each(function() {
        var candidate_id = $(this).data('candidate-id');
        var saved_mark = localStorage.getItem('marks_' + candidate_id);
        if (saved_mark) {
            $(this).val(saved_mark);
        }
    });

    // Save marks to local storage and sync with server
    $('.mark-input').on('change', function() {
        var candidate_id = $(this).data('candidate-id');
        var mark = $(this).val();
        var subject_id = $('#subject_id').val();
        var school_id = $('#school_id').val();

        // Save to local storage
        localStorage.setItem('marks_' + candidate_id, mark);

        // Try to sync with the server
        syncMarkWithServer(candidate_id, mark, subject_id, school_id);
    });

    // Event listener for online status
    window.addEventListener('online', function() {
        console.log('Back online, syncing marks with server...');
        syncAllMarksWithServer();
    });
});

// Function to sync marks with the server
function syncMarkWithServer(candidate_id, mark, subject_id, school_id) {
    $.ajax({
        url: 'save_marks.php', // Your PHP endpoint to handle saving marks
        type: 'POST',
        data: {
            candidate_id: candidate_id,
            mark: mark,
            subject_id: subject_id,
            school_id: school_id
        },
        dataType: 'json', // Expecting JSON response
        success: function(response) {
            console.log('Server response:', response); // Log server response
            if (response.status === 'success') {
                console.log('Marks saved successfully for candidate ID: ' + candidate_id);
                localStorage.removeItem('marks_' + candidate_id); // Clean up localStorage
            } else {
                console.error('Error saving marks:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error saving marks: ' + error);
        }
    });
}

// Function to sync all local marks with the server
function syncAllMarksWithServer() {
    for (var i = 0; i < localStorage.length; i++) {
        var key = localStorage.key(i);
        if (key.startsWith('marks_')) {
            var candidate_id = key.split('_')[1]; // Extract candidate ID from the key
            var mark = localStorage.getItem(key);
            var subject_id = $('#subject_id').val(); // Get the current subject ID
            var school_id = $('#school_id').val(); // Get the current school ID

            // Sync each mark with the server
            syncMarkWithServer(candidate_id, mark, subject_id, school_id);
        }
    }

    // Optionally clear local storage after syncing
    localStorage.clear();
}

// Sorting function for table
function sortTable(n) {
    var table = document.getElementById("marksTable");
    var rows = table.rows;
    var switching = true;
    var shouldSwitch, i;
    var dir = "asc"; 
    var switchcount = 0;

    while (switching) {
        switching = false;
        var rowsArray = Array.from(rows).slice(1); // Skip header row

        for (i = 0; i < rowsArray.length - 1; i++) {
            shouldSwitch = false;
            var x = rowsArray[i].getElementsByTagName("TD")[n];
            var y = rowsArray[i + 1].getElementsByTagName("TD")[n];

            if (dir == "asc") {
                if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                    shouldSwitch = true;
                    break;
                }
            } else if (dir == "desc") {
                if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                    shouldSwitch = true;
                    break;
                }
            }
        }
        if (shouldSwitch) {
            rowsArray[i].parentNode.insertBefore(rowsArray[i + 1], rowsArray[i]);
            switching = true;
            switchcount++; 
        } else {
            if (switchcount == 0 && dir == "asc") {
                dir = "desc";
                switching = true;
            }
        }
    }
}
        $("#school_name").autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: "search_schools.php",
                    dataType: "json",
                    data: {
                        term: request.term
                    },
                    success: function(data) {
                        response(data);
                    }
                });
            },
            select: function(event, ui) {
                // Set the selected school ID to the hidden input
                $("#school_id").val(ui.item.value);
                // Submit the form
                $(this).closest('form').submit();
            }
        });

    $("#search_student").autocomplete({
        source: function (request, response) {
            $.ajax({
                url: "search_candidate.php",
                dataType: "json",
                data: {
                    term: request.term
                },
                success: function (data) {
                    response(data.map(item => ({
                        label: item.name + ' (' + item.index_number + ')',
                        value: item.id
                    })));
                }
            });
        },
        select: function (event, ui) {
            filterTable(ui.item.value);
        }
    });

    function filterTable(candidate_id) {
        $('#marksTable tbody tr').each(function () {
            var row = $(this);
            if (row.attr('id') === 'candidate_' + candidate_id) {
                row.show();
            } else {
                row.hide();
            }
        });
    }
    

</script>

</body>
</html>
