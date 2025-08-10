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

// Handle form submission for updating school details
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_school'])) {
    $school_id = intval($_POST['school_id']);
    $school_name = $_POST['school_name'];
    $sub_county_id = intval($_POST['sub_county_id']);
    $school_type_id = intval($_POST['school_type_id']);

    // Update school details
    $update_sql = "UPDATE schools SET school_Name = ?, Sub_county = ?, School_type = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("siii", $school_name, $sub_county_id, $school_type_id, $school_id);
    $stmt->execute();
    echo "School details updated successfully!";
}

// Fetch schools with names instead of IDs and the number of candidates
$sql = "SELECT 
            s.id, s.CenterNo, s.school_Name, s.Sub_county, s.School_type, 
            IFNULL(sc.subcounty, '') AS SubCountyName, 
            IFNULL(st.type, '') AS SchoolTypeName,
            IFNULL((SELECT COUNT(*) FROM candidates c WHERE c.school_id = s.id), 0) AS NumCandidates
        FROM schools s
        LEFT JOIN sub_counties sc ON s.Sub_county = sc.id
        LEFT JOIN school_types st ON s.School_type = st.id";

$schools_result = $conn->query($sql);

// Fetch sub-counties
$sub_counties_result = $conn->query("SELECT id, subcounty FROM sub_counties");

// Fetch school types
$school_types_result = $conn->query("SELECT id, type FROM school_types");

// Get the details of a school to edit
$school_details = [];
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $school_details_result = $conn->query("SELECT id, school_Name, Sub_county, School_type FROM schools WHERE id = $edit_id");
    $school_details = $school_details_result->fetch_assoc();
}

// Get candidates for a specific school
$candidates = [];
if (isset($_GET['view_candidates_id'])) {
    $view_candidates_id = intval($_GET['view_candidates_id']);
    $candidates_result = $conn->query("SELECT id, IndexNo, Candidate_Name, Gender FROM candidates WHERE school_id = $view_candidates_id");
    $candidates = $candidates_result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Schools</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <h1>Manage Schools</h1>

    <!-- Display schools list with edit and view candidates links -->
    <h2>Schools List</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>CenterNo</th>
                <th>School Name</th>
                <th>Sub County</th>
                <th>School Type</th>
                <th>Number of Candidates</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $schools_result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['CenterNo']) ?></td>
                    <td><?= htmlspecialchars($row['school_Name']) ?></td>
                    <td><?= htmlspecialchars($row['SubCountyName']) ?></td>
                    <td><?= htmlspecialchars($row['SchoolTypeName']) ?></td>
                    <td><?= htmlspecialchars($row['NumCandidates']) ?></td>
                    <td>
                        <!-- Edit button triggers modal -->
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#editSchoolModal"
                                data-id="<?= $row['id'] ?>"
                                data-name="<?= htmlspecialchars($row['school_Name']) ?>"
                                data-subcounty="<?= $row['Sub_county'] ?>"
                                data-type="<?= $row['School_type'] ?>">
                            Edit
                        </button>
                        <!-- View Candidates button triggers modal -->
                        <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#viewCandidatesModal"
                                data-id="<?= $row['id'] ?>">
                            View
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Edit School Modal -->
<div class="modal fade" id="editSchoolModal" tabindex="-1" role="dialog" aria-labelledby="editSchoolModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSchoolModalLabel">Edit School</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="school_id" id="school_id">
                    
                    <div class="form-group">
                        <label for="school_name">School Name:</label>
                        <input type="text" id="school_name" name="school_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="sub_county_id">Sub County:</label>
                        <select id="sub_county_id" name="sub_county_id" class="form-control" required>
                            <?php while ($row = $sub_counties_result->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['subcounty']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="school_type_id">School Type:</label>
                        <select id="school_type_id" name="school_type_id" class="form-control" required>
                            <?php while ($row = $school_types_result->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['type']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="update_school" class="btn btn-primary">Update School</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Candidates Modal -->
<div class="modal fade" id="viewCandidatesModal" tabindex="-1" role="dialog" aria-labelledby="viewCandidatesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewCandidatesModalLabel">View Candidates</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Index No</th>
                            <th>Candidate Name</th>
                            <th>Gender</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="candidatesTableBody">
                        <!-- Candidates will be loaded here by AJAX -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS and dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
    // Populate edit modal with school data
    $('#editSchoolModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var schoolId = button.data('id');
        var schoolName = button.data('name');
        var subCounty = button.data('subcounty');
        var schoolType = button.data('type');

        var modal = $(this);
        modal.find('#school_id').val(schoolId);
        modal.find('#school_name').val(schoolName);
        modal.find('#sub_county_id').val(subCounty);
        modal.find('#school_type_id').val(schoolType);
    });

    // Populate candidates modal with candidates data
    $('#viewCandidatesModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var schoolId = button.data('id');
        
        var modal = $(this);
        var tableBody = modal.find('#candidatesTableBody');

        // Make an AJAX request to fetch candidates
        $.ajax({
            url: 'get_candidates.php',
            type: 'GET',
            data: { school_id: schoolId },
            success: function (response) {
                tableBody.html(response);
            }
        });
    });
</script>

</body>
</html>
