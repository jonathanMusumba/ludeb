<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Files</title>
</head>
<body>
    <h1>Upload Multiple Excel Files</h1>
    <form action="Process_Update_Candidates.php" method="post" enctype="multipart/form-data">
        <label for="files">Select files to upload:</label>
        <input type="file" name="files[]" id="files" multiple>
        <input type="submit" value="Upload Files" name="submit">
    </form>
</body>
</html>
