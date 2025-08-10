<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results Capture Form</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center">Results Capture Form</h1>
    <form id="resultsForm" action="save_results.php" method="post">
        <div class="form-group">
            <label for="school">Select School:</label>
            <select class="form-control" id="school" name="school" required>
                <option value="">Select a School</option>
                <!-- Populate with schools from the database -->
            </select>
        </div>
        <div class="form-group">
            <label for="subject">Select Subject:</label>
            <select class="form-control" id="subject" name="subject" required>
                <option value="">Select a Subject</option>
                <!-- Populate with subjects from the database -->
            </select>
        </div>

        <h3 class="mt-4">Enter Marks</h3>
        <div id="studentsList">
            <!-- Dynamically populate student rows here -->
        </div>

        <button type="submit" class="btn btn-primary mt-4">Final Submit</button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
// JavaScript to populate students dynamically
$(document).ready(function () {
    $('#school, #subject').on('change', function () {
        const schoolId = $('#school').val();
        const subjectCode = $('#subject').val();

        if (schoolId && subjectCode) {
            // Fetch students for the selected school and subject via AJAX
            $.ajax({
                url: 'fetch_students.php',
                type: 'POST',
                data: { school_id: schoolId, subject_code: subjectCode },
                success: function (data) {
                    $('#studentsList').html(data);
                }
            });
        } else {
            $('#studentsList').html('');
        }
    });
});
</script>
</body>
</html>
