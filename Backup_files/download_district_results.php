<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Luuka Mock Results</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        #loading-message {
            display: none;
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            color: #fff;
            text-align: center;
            padding-top: 20%;
            font-size: 1.5rem;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">Generate Luuka District Mock Results</h2>
        <p class="text-center">Click the button below to generate and download the PDF results for all declared schools.</p>

        <form id="results-form" action="Process_results.php" method="POST">
            <div class="text-center">
                <button type="submit" name="download_sheet" class="btn btn-primary">Generate Results PDF</button>
            </div>
        </form>
    </div>

    <div id="loading-message">Processing your request, please wait...</div>

    <script>
        document.getElementById('results-form').addEventListener('submit', function() {
            document.getElementById('loading-message').style.display = 'block';
        });
    </script>
</body>
</html>

