<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Multiple Files</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        h2 {
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="file"] {
            width: 100%;
            padding: 10px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .alert {
            padding: 10px;
            color: white;
            background-color: #f44336;
            border-radius: 5px;
            margin-top: 15px;
            text-align: center;
        }
        .success {
            background-color: #4CAF50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Upload Multiple Files</h2>
        <?php if (isset($_GET['message'])): ?>
            <div class="alert <?php echo $_GET['type'] == 'success' ? 'success' : ''; ?>">
                <?php echo htmlspecialchars($_GET['message']); ?>
            </div>
        <?php endif; ?>
        <form action="upload_files.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="files">Select Excel Files:</label>
                <input type="file" name="files[]" id="files" multiple required>
            </div>
            <button type="submit">Upload Files</button>
        </form>
    </div>
</body>
</html>
