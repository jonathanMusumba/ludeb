<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schools</title>
    <!-- Bootstrap CSS -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .table-container {
            margin-top: 20px;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .btn-custom {
            background-color: #007bff;
            color: white;
        }
        .btn-custom:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Manage Schools</h1>

        <div class="search-box">
            <div class="form-group">
                <input type="text" id="searchBox" class="form-control" placeholder="Search Schools...">
            </div>
        </div>

        <div class="table-container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Center No</th>
                        <th>School Name</th>
                        <th>Subcounty</th>
                        <th>School Type</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="schoolsTableBody">
                    <!-- Rows will be inserted here dynamically -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
       $(document).ready(function() {
    function fetchSchools() {
        $.ajax({
            url: 'php/get_schools.php',
            method: 'GET',
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.error) {
                        console.error('Server Error:', data.error);
                        return;
                    }
                    const schools = data.schools;

                    $('#schoolsTableBody').empty();

                    schools.forEach(school => {
                        $('#schoolsTableBody').append(`
                            <tr>
                                <td>${school.CenterNo}</td>
                                <td>${school.School_Name}</td>
                                <td>${school.Sub_county}</td>
                                <td>${school.School_type}</td>
                                <td><a href="view_school.php?id=${school.id}" class="btn btn-link"><i class="fas fa-eye"></i> View</a></td>
                            </tr>
                        `);
                    });
                } catch (e) {
                    console.error('Failed to parse response:', e);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX request failed:', status, error);
            }
        });
    }

    fetchSchools();
});
    </script>
</body>
</html>
