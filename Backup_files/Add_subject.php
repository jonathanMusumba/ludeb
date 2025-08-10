<?php
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "ludeb";
        
        $conn = new mysqli($servername, $username, $password, $dbname);

            // Check connection
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }

        // Check if the form is submitted
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Retrieve form data
            $name = $_POST['name'];
            $code = $_POST['code'];

            // Prepare and bind
            $stmt = $conn->prepare("INSERT INTO subjects (Name, Code) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $code);

            // Execute the statement
            if ($stmt->execute()) {
                echo '<div class="alert alert-success" role="alert">New subject created successfully</div>';
            } else {
                echo '<div class="alert alert-danger" role="alert">Error: ' . $stmt->error . '</div>';
            }

            // Close statement
            $stmt->close();
        }

        // Close connection
        $conn->close();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Creation Form</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Create New Subject</h1>
       
        <form action="" method="POST" class="mt-4">
            <div class="form-group">
                <label for="name">Subject Name:</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="code">Subject Code:</label>
                <input type="text" class="form-control" id="code" name="code" required>
            </div>
            <button type="submit" class="btn btn-primary">Create Subject</button>
        </form>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
