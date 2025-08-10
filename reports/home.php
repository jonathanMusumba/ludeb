<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
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

// Fetch data for best performing candidates
$query = "
    SELECT 
        r.candidate_id, 
        c.Candidate_Name AS name, 
        c.Gender AS gender, 
        r.Aggregates AS aggregates,
        st.Type AS funding, 
        s.School_Name AS school
    FROM 
        results r
    JOIN 
        candidates c ON r.candidate_id = c.id
    JOIN 
        schools s ON s.id = c.school_id
    JOIN 
        school_types st ON st.id = s.School_type
    WHERE 
        st.exam_year = ?
    ORDER BY 
        r.aggregates ASC
";

$year = 2024;  // Example year
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$candidates = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Performance</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.3.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/0.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">General Performance Report</h2>

        <!-- Export and Print Buttons -->
        <div class="text-center mb-4">
            <button class="btn btn-primary" onclick="exportToPDF()">Export to PDF</button>
            <button class="btn btn-success" onclick="exportToWord()">Export to Word</button>
            <button class="btn btn-warning" onclick="window.print()">Print</button>
        </div>

        <!-- Table 1.0: General Performance -->
        <h4>Table 1.0: General Performance</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Divisions</th>
                    <th>Number of Candidates</th>
                    <th>%Age</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populate this section based on the required logic -->
            </tbody>
        </table>

        <!-- Table 1.1: Performance by Gender -->
        <h4>Table 1.1: Performance by Gender</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Gender</th>
                    <th>Div 1</th>
                    <th>Div 2</th>
                    <th>Div 3</th>
                    <th>Div 4</th>
                    <th>Div U</th>
                    <th>Div X</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populate this section based on the required logic -->
            </tbody>
        </table>

        <!-- Table 1.2: Best Performing Schools -->
        <h4>Table 1.2: Best Performing Schools</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>CenterNo</th>
                    <th>School Name</th>
                    <th>Funding (School Type)</th>
                    <th>Number of First Grades</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populate this section based on the required logic -->
            </tbody>
        </table>

        <!-- Table 1.3: Worst Performing Schools -->
        <h4>Table 1.3: Worst Performing Schools</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>CenterNo</th>
                    <th>School Name</th>
                    <th>Number of Failures</th>
                    <th>Total Candidates Registered</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populate this section based on the required logic -->
            </tbody>
        </table>

        <!-- Table 1.4: Best Performing Candidates -->
        <h4>Table 1.4: Best Performing Candidates</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>IndexNo</th>
                    <th>Candidate Name</th>
                    <th>Gender</th>
                    <th>Aggregates</th>
                    <th>Funding</th>
                    <th>School Name</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $candidate): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($candidate['candidate_id']); ?></td>
                        <td><?php echo htmlspecialchars($candidate['name']); ?></td>
                        <td><?php echo htmlspecialchars($candidate['gender']); ?></td>
                        <td><?php echo htmlspecialchars($candidate['aggregates']); ?></td>
                        <td><?php echo htmlspecialchars($candidate['funding']); ?></td>
                        <td><?php echo htmlspecialchars($candidate['school']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>

    <script>
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            html2canvas(document.body).then(function(canvas) {
                var imgData = canvas.toDataURL('image/png');
                var imgWidth = 210;
                var pageHeight = 295;
                var imgHeight = canvas.height * imgWidth / canvas.width;
                var heightLeft = imgHeight;

                var position = 0;

                doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;

                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    doc.addPage();
                    doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                doc.save('General_Performance_Report.pdf');
            });
        }

        function exportToWord() {
            const content = document.body.innerHTML;
            const blob = new Blob(['\ufeff', content], {
                type: 'application/msword'
            });
            saveAs(blob, 'General_Performance_Report.doc');
        }
    </script>
</body>
</html>
