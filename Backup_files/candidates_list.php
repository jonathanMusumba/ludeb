<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$search_query = "";
$candidates_query = "SELECT * FROM Candidates";

// Check if search was performed
if (isset($_GET['search'])) {
    $search_query = $_GET['search'];
    $candidates_query .= " WHERE IndexNo LIKE '%$search_query%' OR Candidate_Name LIKE '%$search_query%'";
}

// Fetch candidates based on the search query
$candidates_result = $conn->query($candidates_query);

// Fetch candidates for autocomplete (limit to 10 for performance)
$autocomplete_query = "SELECT IndexNo, Candidate_Name FROM Candidates WHERE IndexNo LIKE '%$search_query%' OR Candidate_Name LIKE '%$search_query%' LIMIT 10";
$autocomplete_result = $conn->query($autocomplete_query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates List</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .autocomplete-suggestions {
            border: 1px solid #e0e0e0;
            max-height: 200px;
            overflow-y: auto;
            background-color: #fff;
        }
        .autocomplete-suggestion {
            padding: 8px;
            cursor: pointer;
        }
        .autocomplete-suggestion:hover {
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Candidates List</h1>
        <form method="get" action="" class="form-inline mb-3">
            <input type="text" name="search" id="search" class="form-control mr-sm-2" placeholder="Search by Index No or Name" value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
        <div id="autocomplete-suggestions" class="autocomplete-suggestions"></div>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Index No</th>
                    <th>Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="candidates-list">
                <?php while ($row = $candidates_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['IndexNo']); ?></td>
                        <td><?php echo htmlspecialchars($row['Candidate_Name']); ?></td>
                        <td>
                            <a href="edit_results.php?candidate_id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                Edit Results
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#search').on('keyup', function() {
                var query = $(this).val();

                if (query.length > 0) {
                    $.ajax({
                        url: 'candidates_list.php',
                        type: 'GET',
                        data: {
                            search: query
                        },
                        success: function(data) {
                            $('#autocomplete-suggestions').html('');
                            $(data).find('#autocomplete-suggestions').children().each(function() {
                                $('#autocomplete-suggestions').append($(this));
                            });
                        }
                    });
                } else {
                    $('#autocomplete-suggestions').html('');
                }
            });

            $(document).on('click', '.autocomplete-suggestion', function() {
                var selectedValue = $(this).text();
                $('#search').val(selectedValue);
                $('#autocomplete-suggestions').html('');
            });
        });
    </script>
</body>
</html>
