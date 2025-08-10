<?php
// Database credentials
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
$center_no = isset($_GET['CenterNo']) ? $_GET['CenterNo'] : '';
// Get current exam year
$current_year_id = null;
$current_year_name = null;
$year_result = $conn->query("SELECT id, Exam_year FROM exam_years WHERE YEAR(CURDATE()) = Exam_year LIMIT 1");
if ($year_result && $year_row = $year_result->fetch_assoc()) {
    $current_year_id = $year_row['id'];
    $current_year_name = $year_row['Exam_year'];
}

// Fetch other years for dropdown
$years_result = $conn->query("SELECT id, Exam_year FROM exam_years WHERE id != $current_year_id");
// Fetch school data from database
$stmt = $conn->prepare("SELECT * FROM schools WHERE CenterNo = ?");
$stmt->bind_param("s", $center_no);
$stmt->execute();
$result = $stmt->get_result();
$school = $result->fetch_assoc();

if (!$school) {
    die("School not found or invalid CenterNo.");
}

// Example data for Sub_county and School_type
$sub_counties = [
    1 => "Luuka Town Council",
    2 => "Ikumbya",
    3 => "Bulongo",
    4 => "Bukoma",
    5 => "Bukoova Town Council",
    6 => "Bukanga",
    7 => "Irongo",
    8 => "Kyanvuma Town Council",
    9 => "Busalamu Town Council",
    10 => "Bulanga Town Council",
    11 => "Nawampiti",
    12 => "Waibuga"
];

$school_types = [
    1 => "Government",
    2 => "Private",
    // Add more mappings as needed
];

// Define badge colors for resultsStatus
$status_badges = [
    'Not Declared' => 'danger',
    'Partially Declared' => 'warning',
    'Declared' => 'success'
];

// Determine the badge class for the results status
$status_class = isset($status_badges[$school['resultsStatus']]) ? $status_badges[$school['resultsStatus']] : 'secondary';

$school_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$school_name = "";
if ($school_id > 0) {
    $school_result = $conn->query("SELECT School_Name FROM schools WHERE id = $school_id");
    if ($school_result && $school_row = $school_result->fetch_assoc()) {
        $school_name = $school_row['School_Name'];
    }
}
// Fetch subjects for the school
$subjects_result = $conn->query("SELECT id, Name FROM subjects");

// Handle form submission for marks
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_marks'])) {
    $subject_id = $_POST['subject_id'];
    $school_id = $_POST['school_id'];

    // Fetch candidates for the selected school
    $candidates_result = $conn->query("SELECT id, Candidate_Name, IndexNo FROM candidates WHERE school_id = $school_id");
    $candidates = [];
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[$row['id']] = ['name' => $row['Candidate_Name'], 'index_number' => $row['IndexNo']];
    }

    // Process marks
    foreach ($_POST['marks'] as $candidate_id => $mark) {
        $mark = intval($mark); // Ensure mark is an integer

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
            $stmt->bind_param("iiisi", $candidate_id, $subject_id, $mark, $user_id, $school_id);
            $stmt->execute();
        } else {
            // Update existing mark
            $update_sql = "UPDATE marks SET mark = ?, updated_at = NOW(), edited_by = ? WHERE candidate_id = ? AND subject_id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("iiii", $mark, $user_id, $candidate_id, $subject_id);
            $stmt->execute();
        }
    }
    echo "<div class='alert alert-success'>Marks entered successfully!</div>";
}
$uploads_result = $conn->query("SELECT id, filename, uploaded_at FROM uploads WHERE school_id = $school_id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View School</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        .breadcrumb {
            display: flex;
            align-items: center;
        }
        .breadcrumb .dropdown-form {
            margin-left: auto; /* Push the form to the right */
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="home.php"><i class="fas fa-home"></i> Home</a></li>
                <li class="breadcrumb-item"><a href="schools.php">Schools</a></li>
                <li class="breadcrumb-item active" aria-current="page">View School - <?php echo htmlspecialchars($school_name); ?></li>
                <li class="breadcrumb-item dropdown-form">
                    <form method="get" action="" class="form-inline">
                        <label for="yearDropdown" class="mr-2">Year:</label>
                        <select name="year" id="yearDropdown" class="form-control" onchange="this.form.submit()">
                            <option value="<?php echo $current_year_id; ?>"><?php echo htmlspecialchars($current_year_name); ?></option>
                            <?php while ($year_row = $years_result->fetch_assoc()): ?>
                                <option value="<?php echo $year_row['id']; ?>">
                                    <?php echo htmlspecialchars($year_row['Exam_year']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </form>
                </li>
            </ol>
        </nav>
        <h2 class="text-center">SCHOOL PROFILE</h2>
        <div class="row">
            <div class="col-md-6">
                <h4>NAME: <?php echo htmlspecialchars($school['School_Name'] ?? 'Unknown'); ?></h4>
                <p>SUB COUNTY: <?php echo htmlspecialchars(isset($sub_counties[$school['Sub_county']]) ? $sub_counties[$school['Sub_county']] : 'Unknown'); ?></p>
                <p>SCHOOL TYPE: <?php echo htmlspecialchars(isset($school_types[$school['School_type']]) ? $school_types[$school['School_type']] : 'Unknown'); ?></p>
                <p>SCHOOL STATUS: <span class='badge badge-<?php echo $status_class; ?>'><?php echo htmlspecialchars($school['Status'] ?? 'Unknown'); ?></span></p>
                <p>RESULTS STATUS: <span class='badge badge-<?php echo $status_class; ?>'><?php echo htmlspecialchars($school['resultsStatus'] ?? 'Unknown'); ?></span></p>
            </div>
            <div class="col-md-6">
                <a href="download_results.php?center_no=<?php echo urlencode($center_no); ?>" class="btn btn-success mb-3">Download Results (Excel)</a>
                <a href="download_results_pdf.php?center_no=<?php echo urlencode($center_no); ?>" class="btn btn-primary mb-3">Download Results (PDF)</a>
                <a href="capture_results.php?center_no=<?php echo urlencode($center_no); ?>" class="btn btn-secondary mb-3">Capture Results</a>
                
                <a href="view_candidates.php?center_no=<?php echo urlencode($center_no); ?>" class="btn btn-info mb-3">View Candidates</a>
                <form action="downloads.php" method="post">
                    <button type="submit" name="download_sheet" class="btn btn-primary mb-4">Download Full Results Sheet</button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary mb-4">Print Results</button>
                </form>
                <form action="upload_candidates.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="school_id" value="<?php echo htmlspecialchars($center_no); ?>">
                    <div class="form-group">
                        <label for="file">Upload Candidates:</label>
                        <input type="file" class="form-control-file" id="file" name="file" required>
                    </div>
                    <button type="submit" class="btn btn-info">Upload</button>
                </form>
            </div>
        </div>
             <!-- Display uploaded files -->
             <h3 class="mt-5">Uploaded Files</h3>
        <table class="table table-bordered">
                   <thead>
                       <tr>
                           <th>File Name</th>
                           <th>Uploaded Date</th>
                           <th>Actions</th>
                       </tr>
                   </thead>
                   <tbody>
                       <?php while ($upload_row = $uploads_result->fetch_assoc()): ?>
                           <tr>
                               <td><?php echo htmlspecialchars($upload_row['filename']); ?></td>
                               <td><?php echo htmlspecialchars($upload_row['uploaded_at']); ?></td>
                               <td>
                                   <a href="download_file.php?id=<?php echo htmlspecialchars($upload_row['id']); ?>" class="btn btn-sm btn-primary">Download</a>
                                   <a href="delete_file.php?id=<?php echo htmlspecialchars($upload_row['id']); ?>" class="btn btn-sm btn-danger">Delete</a>
                               </td>
                           </tr>
                       <?php endwhile; ?>
                   </tbody>
               </table>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Script to dynamically load candidates for the selected subject
        $('#subject_id').change(function() {
            var subjectId = $(this).val();
            var schoolId = $('input[name="school_id"]').val();

            if (subjectId && schoolId) {
                $.ajax({
                    url: 'get_candidates.php', // Script to fetch candidates for the selected subject
                    type: 'GET',
                    data: { subject_id: subjectId, school_id: schoolId },
                    success: function(data) {
                        $('#marks_section').html(data);
                    }
                });
            } else {
                $('#marks_section').html('');
            }
        });
    </script>
</body>
</html>
    
