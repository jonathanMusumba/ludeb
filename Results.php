<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] !== 'System Admin' && $_SESSION['role'] !== 'Examination Admin')) {
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

// Fetch schools and subjects for autocomplete
$schools_query = $conn->query("SELECT id, school_name FROM schools");
$subjects_query = $conn->query("SELECT id, Name FROM subjects");

$schools = [];
while ($row = $schools_query->fetch_assoc()) {
    $schools[] = $row;
}

$subjects = [];
while ($row = $subjects_query->fetch_assoc()) {
    $subjects[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marksheets</title>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            background-color: #4CAF50;
            color: white;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script>
        $(function() {
            var schools = <?php echo json_encode($schools); ?>;
            var subjects = <?php echo json_encode($subjects); ?>;

            $("#school").autocomplete({
                source: schools.map(function(school) {
                    return {
                        label: school.school_name,
                        value: school.id
                    };
                }),
                select: function(event, ui) {
                    $("#school").val(ui.item.value);
                }
            });

            $("#subject").autocomplete({
                source: subjects.map(function(subject) {
                    return {
                        label: subject.Name,
                        value: subject.id
                    };
                }),
                select: function(event, ui) {
                    $("#subject").val(ui.item.value);
                }
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>Marksheets and General Sheet</h1>

        <form action="Marksheet_subject.php" method="get">
            <div class="form-group">
                <label for="school">Select School</label>
                <input type="text" id="school" name="school_id" placeholder="Enter school name">
            </div>
            <div class="form-group">
                <label for="subject">Select Subject</label>
                <input type="text" id="subject" name="subject_id" placeholder="Enter subject name">
            </div>
            <button type="submit">View Marksheet</button>
        </form>

        <form action="General_sheet.php" method="get">
            <button type="submit" style="margin-top: 20px;">View General Sheet</button>
        </form>
    </div>
</body>
</html>
