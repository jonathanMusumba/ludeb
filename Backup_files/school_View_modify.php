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

// Fetch candidate summary
$candidate_summary_result = $conn->query("SELECT Gender, COUNT(*) as total FROM candidates WHERE School_id = '$center_no' GROUP BY Gender");

$candidate_summary = [];
while ($row = $candidate_summary_result->fetch_assoc()) {
    $candidate_summary[$row['Gender']] = $row['total'];
}

// Fetch results summary
$results_summary_result = $conn->query("
    SELECT Division, Gender, COUNT(*) as total 
    FROM marks
    WHERE CenterNo = '$center_no' 
    GROUP BY Division, Gender
");

$results_summary = [];
while ($row = $results_summary_result->fetch_assoc()) {
    $results_summary[$row['Division']][$row['Gender']] = $row['total'];
}

$school_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$school_name = "";
if ($school_id > 0) {
    $school_result = $conn->query("SELECT School_Name FROM schools WHERE id = $school_id");
    if ($school_result && $school_row = $school_result->fetch_assoc()) {
        $school_name = $school_row['School_Name'];
    }
}
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
                <li class="breadcrumb-item"><a href="schools.php"><i class="fas fa-home"></i> Home</a></li>
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

        <!-- Candidates Summary Section -->
        <h4 class="mt-5">Candidates Summary</h4>
        <?php if (!empty($candidate_summary)): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Gender</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Male (M)</td>
                        <td><?php echo $candidate_summary['M'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <td>Female (F)</td>
                        <td><?php echo $candidate_summary['F'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <td>Total</td>
                        <td><?php echo array_sum($candidate_summary); ?></td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <p>No candidates found.</p>
        <?php endif; ?>

        <!-- Results Summary Section -->
        <h4 class="mt-5">Results Summary</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Division</th>
                    <th>Male (M)</th>
                    <th>Female (F)</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $divisions = ['One', 'Two', 'Three', 'Four', 'Ungraded', 'Absentees'];
                $no_results = true;
                foreach ($divisions as $division):
                    $male_count = $results_summary[$division]['M'] ?? 0;
                    $female_count = $results_summary[$division]['F'] ?? 0;
                    $total = $male_count + $female_count;
                    if ($total > 0) $no_results = false;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($division); ?></td>
                            <td><?php echo $male_count; ?></td>
                            <td><?php echo $female_count; ?></td>
                            <td><?php echo $total; ?></td>
                        </tr>
                    <?php endforeach; ?>
    
                    <?php if ($no_results): ?>
                        <tr>
                            <td colspan="4" class="text-center">No results declared for this school.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
    
            <!-- Button to capture results -->
            <a href="capture_results.php?center_no=<?php echo urlencode($center_no); ?>" class="btn btn-warning mt-3">Capture Results</a>
        </div>
        <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    </body>
    </html>
    