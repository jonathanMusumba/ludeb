<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Generation</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        @media print {
            .no-print {
                display: none;
            }
            #report-header {
                display: block;
            }
        }
        #report-header {
            display: none;
            text-align: center;
            margin-bottom: 20px;
        }
        .export-buttons {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="no-print">
            <h1>Generate School Performance Report</h1>
            <form id="reportForm">
                <!-- Include any necessary form fields for report criteria -->
                <div class="form-group">
                    <label for="academicYear">Academic Year</label>
                    <select class="form-control" id="academicYear" name="academicYear">
                        <option value="2023">2023</option>
                        <option value="2022">2022</option>
                        <!-- Add more years as needed -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="schoolId">School ID</label>
                    <input type="text" class="form-control" id="schoolId" name="schoolId">
                </div>
                <button type="button" class="btn btn-primary" onclick="generateReport()">Generate Report</button>
            </form>
        </div>

        <div id="report-content" class="mt-5">
            <div id="report-header">
                <h2>School Performance Report</h2>
                <p>Academic Year: <span id="headerAcademicYear"></span></p>
                <p>Generated on: <span id="headerDate"></span></p>
            </div>
            <div id="report-body">
                <!-- Report content will be dynamically inserted here -->
            </div>
        </div>

        <div class="export-buttons no-print">
            <button class="btn btn-success" onclick="printReport()">Print Report</button>
            <button class="btn btn-danger" onclick="exportToPDF()">Export to PDF</button>
            <button class="btn btn-info" onclick="exportToWord()">Export to Word</button>
        </div>
    </div>

    <script>
        function generateReport() {
            const academicYear = document.getElementById('academicYear').value;
            const schoolId = document.getElementById('schoolId').value;
            
            // Set header details
            document.getElementById('headerAcademicYear').innerText = academicYear;
            document.getElementById('headerDate').innerText = new Date().toLocaleDateString();

            // Generate the report content (you can fetch this data from the server)
            const reportBody = document.getElementById('report-body');
            reportBody.innerHTML = `
                <h3>General Performance Overview</h3>
                <p>Performance data for the school ID ${schoolId} for the academic year ${academicYear}.</p>
                <!-- Include more sections as needed -->
            `;
        }

        function printReport() {
            window.print();
        }

        function exportToPDF() {
            // Export to PDF functionality using a library like jsPDF
            // Example with jsPDF:
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            doc.text(document.getElementById('report-content').innerText, 10, 10);
            doc.save('report.pdf');
        }

        function exportToWord() {
            // Export to Word functionality using libraries like FileSaver.js
            const reportContent = document.getElementById('report-content').innerHTML;
            const blob = new Blob(['\ufeff', reportContent], {
                type: 'application/msword'
            });

            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'report.doc';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>

    <!-- Add jsPDF for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>

</body>
</html>
