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

// Get current exam year
$current_year_id = null;
$current_year_name = null;
$year_result = $conn->query("SELECT id, Exam_year FROM exam_years WHERE YEAR(CURDATE()) = Exam_year LIMIT 1");
if ($year_result && $year_row = $year_result->fetch_assoc()) {
    $current_year_id = $year_row['id'];
    $current_year_name = $year_row['Exam_year'];
}

// Pagination and search parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch total number of records
$total_result = $conn->query("SELECT COUNT(*) as total FROM schools");
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];

$sql = "SELECT s.id, s.CenterNo, s.school_Name, s.Sub_county, s.School_type, s.Status, s.resultsStatus, 
               IFNULL(COUNT(c.school_id), 0) AS candidate_count,
               st.type AS SchoolTypeName
        FROM schools s
        LEFT JOIN candidates c ON s.id = c.school_id
        LEFT JOIN school_types st ON s.School_type = st.id
        WHERE s.CenterNo LIKE '%$search%' OR s.school_Name LIKE '%$search%'
        GROUP BY s.id
        LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Fetch other years for dropdown
$years_result = $conn->query("SELECT id, Exam_year FROM exam_years");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schools List</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid #dee2e6;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
        .action-button {
            background-color: #4CAF50; /* Green */
            color: white;
            border: none;
            padding: 5px 10px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin: 4px 2px;
            cursor: pointer;
        }
        .status-active {
            color: green;
        }
        .status-inactive {
            color: red;
        }
        .badge-danger {
            background-color: #dc3545;
        }
        .badge-warning {
            background-color: #ffc107;
        }
        .badge-success {
            background-color: #28a745;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="home.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Schools</li>
        </ol>
    </nav>

    <div class="mb-4">
        <form method="get" action="">
            <div class="form-row align-items-center">
                <div class="col-auto">
                    <select class="custom-select mr-sm-2" name="year">
                        <option value="">Select Year</option>
                        <?php while ($year_row = $years_result->fetch_assoc()): ?>
                            <option value="<?php echo $year_row['id']; ?>" <?php echo ($current_year_id == $year_row['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year_row['Exam_year']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <select class="custom-select mr-sm-2" name="limit">
                        <option value="25" <?php echo ($limit == 25) ? 'selected' : ''; ?>>25 entries</option>
                        <option value="50" <?php echo ($limit == 50) ? 'selected' : ''; ?>>50 entries</option>
                        <option value="100" <?php echo ($limit == 100) ? 'selected' : ''; ?>>100 entries</option>
                    </select>
                </div>
                <div class="col-auto">
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search CenterNo or School Name">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Apply</button>
                </div>
            </div>
        </form>
    </div>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Center No</th>
                <th>School Name</th>
                <th>School Type</th>
                <th>Status</th>
                <th>Results Status</th>
                <th>Number of Candidates</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row["CenterNo"]); ?></td>
                        <td><?php echo htmlspecialchars($row["school_Name"]); ?></td>
                        <td><?php echo htmlspecialchars($row["SchoolTypeName"]); ?></td>
                        <td>
                            <!-- Status Badge -->
                            <span class="badge <?php echo ($row["Status"] == 'Active') ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo htmlspecialchars($row["Status"]); ?>
                            </span>
                        </td>
                        <td>
                            <!-- Results Status Badge -->
                            <?php
                            $results_status_class = 'badge-secondary'; // Default class
                            switch ($row["resultsStatus"]) {
                                case 'Not Declared':
                                    $results_status_class = 'badge-danger';
                                    break;
                                case 'Partially Declared':
                                    $results_status_class = 'badge-warning';
                                    break;
                                case 'Declared':
                                    $results_status_class = 'badge-success';
                                    break;
                            }
                            ?>
                            <span class="badge <?php echo $results_status_class; ?>">
                                <?php echo htmlspecialchars($row["resultsStatus"]); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row["candidate_count"]); ?></td>
                        <td>
                            <a href="view_school.php?id=<?php echo urlencode($row['id']); ?>&CenterNo=<?php echo urlencode($row['CenterNo']); ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No schools found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="d-flex justify-content-between">
        <div>
            <?php
            $start = ($page - 1) * $limit + 1;
            $end = min($page * $limit, $total_records);
            ?>
            Showing <?php echo $start; ?> to <?php echo $end; ?> of <?php echo $total_records; ?> entries
        </div>
        <nav>
            <ul class="pagination">
                <?php
                $total_pages = ceil($total_records / $limit);
                $prev_page = $page - 1;
                $next_page = $page + 1;

                if ($total_pages > 1) {
                    if ($page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1&limit=' . $limit . '&search=' . urlencode($search) . '">First</a></li>';
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $prev_page . '&limit=' . $limit . '&search=' . urlencode($search) . '">Previous</a></li>';
                    }

                    // Page numbers
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        echo '<li class="page-item' . ($i == $page ? ' active' : '') . '"><a class="page-link" href="?page=' . $i . '&limit=' . $limit . '&search=' . urlencode($search) . '">' . $i . '</a></li>';
                    }

                    if ($page < $total_pages) {
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $next_page . '&limit=' . $limit . '&search=' . urlencode($search) . '">Next</a></li>';
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&limit=' . $limit . '&search=' . urlencode($search) . '">Last</a></li>';
                    }
                }
                ?>
            </ul>
        </nav>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    $(function() {
        $('input[name="search"]').autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: 'autocomplete.php',
                    dataType: 'json',
                    data: {
                        term: request.term
                    },
                    success: function(data) {
                        response(data);
                    }
                });
            }
        });
    });
</script>
</body>
</html>
