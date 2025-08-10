<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch sub-counties
$subCountiesResult = $conn->query("SELECT id, subcounty FROM sub_counties");
$subCounties = $subCountiesResult->fetch_all(MYSQLI_ASSOC);

// Fetch school types
$schoolTypesResult = $conn->query("SELECT id, type FROM school_types");
$schoolTypes = $schoolTypesResult->fetch_all(MYSQLI_ASSOC);


$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add School</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
    <style>
        .form-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-section h2 {
            margin-top: 0;
        }
        .form-section hr {
            border-top: 1px solid #dee2e6;
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
        <div class="form-section">
            <h1>Add School</h1>
            <form id="addSchoolForm" action="php/add_school.php" method="post">
                <div class="form-group">
                    <label for="centerNo">Center Number</label>
                    <input type="text" class="form-control" id="centerNo" name="centerNo" required>
                </div>
                <div class="form-group">
                    <label for="schoolName">School Name</label>
                    <input type="text" class="form-control" id="schoolName" name="schoolName" required>
                </div>
                <div class="form-group">
                    <label for="subCounty">Subcounty</label>
                    <select class="form-control" id="subCounty" name="subCounty" required>
                        <!-- Options will be fetched from the database -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="schoolType">School Type</label>
                    <select class="form-control" id="schoolType" name="schoolType" required>
                        <!-- Options will be fetched from the database -->
                    </select>
                </div>
                <button type="submit" class="btn btn-custom">Add School</button>
            </form>
        </div>

        <hr>

        <div class="form-section mt-4">
            <h2>Import Schools from Excel</h2>
            <form method="POST" action="php/import_schools.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="excelFile">Upload Excel File</label>
                    <input type="file" class="form-control-file" id="excelFile" name="excelFile" required>
                </div>
                <button type="submit" class="btn btn-custom">Import Schools</button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        window.onload = function() {
        var subCountySelect = document.getElementById('subCounty');
        var schoolTypeSelect = document.getElementById('schoolType');
        var subCountyImportSelect = document.getElementById('subCountyImport');
        var schoolTypeImportSelect = document.getElementById('schoolTypeImport');

        <?php foreach ($subCounties as $subCounty): ?>
            var option = document.createElement('option');
            option.value = '<?php echo $subCounty['id']; ?>';
            option.text = '<?php echo $subCounty['subcounty']; ?>';
            subCountySelect.add(option);
            subCountyImportSelect.add(option.cloneNode(true));
        <?php endforeach; ?>

        <?php foreach ($schoolTypes as $schoolType): ?>
            var option = document.createElement('option');
            option.value = '<?php echo $schoolType['id']; ?>';
            option.text = '<?php echo $schoolType['type']; ?>';
            schoolTypeSelect.add(option);
            schoolTypeImportSelect.add(option.cloneNode(true));
        <?php endforeach; ?>
    };
    </script>
</body>
</html>
