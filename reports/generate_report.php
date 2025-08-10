<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ludeb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}// Make sure to include your database connection file

include 'helpers.php'; // Include helper functions

// Fetch user data (assuming user is logged in)
$user_id = $_SESSION['user_id']; // Adjust as necessary
$user_query = "SELECT * FROM system_users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Fetch general statistics
$totalSchools = getTotalSchools($conn);
$activeSchools = getActiveSchools($conn);
$totalCandidates = getTotalCandidates($conn);
$maleCandidates = getMaleCandidates($conn);
$femaleCandidates = getFemaleCandidates($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Reports</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/charts.js" defer></script>
</head>
<body>
    <div class="top-bar">
        <div class="logo">Performance Reports</div>
        <div class="user-info">
            <img src="images/user-icon.png" alt="User Icon">
            <span><?php echo htmlspecialchars($user['username']); ?></span>
            <a href="logout.php">Logout</a>
        </div>
        <div class="top-links">
            <a href="#general" class="tab-link active">General Performance</a>
            <a href="#subject" class="tab-link">Subject Performance</a>
            <a href="#subcounty" class="tab-link">Sub County Performance</a>
            <a href="#best" class="tab-link">Best Performers</a>
            <a href="#worst" class="tab-link">Worst Performing Schools</a>
        </div>
    </div>
    
    <div class="container">
        <div class="tab-list">
            <div class="tab active" data-target="general">General Performance</div>
            <div class="tab" data-target="subject">Subject Performance</div>
            <div class="tab" data-target="subcounty">Sub County Performance</div>
            <div class="tab" data-target="best">Best Performers</div>
            <div class="tab" data-target="worst">Worst Performing Schools</div>
        </div>

        <div class="content">
            <!-- General Performance Section -->
            <div id="general" class="tab-content active">
                <div class="card">
                    <h3>General Overview</h3>
                    <p>Total Schools: <?php echo $totalSchools; ?></p>
                    <p>Active Schools: <?php echo $activeSchools; ?></p>
                    <p>Total Registered Candidates: <?php echo $totalCandidates; ?></p>
                    <p>Male Candidates: <?php echo $maleCandidates; ?></p>
                    <p>Female Candidates: <?php echo $femaleCandidates; ?></p>
                </div>
                <div class="chart-container">
                    <canvas id="performanceByDivision"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="performanceByGender"></canvas>
                </div>
                <!-- Additional charts and tables can be added here -->
            </div>
            
            <!-- Subject Performance Section -->
            <div id="subject" class="tab-content">
                <!-- Add subject performance details here -->
            </div>
            
            <!-- Sub County Performance Section -->
            <div id="subcounty" class="tab-content">
                <!-- Add sub county performance details here -->
            </div>
            
            <!-- Best Performers Section -->
            <div id="best" class="tab-content">
                <!-- Add best performers details here -->
            </div>
            
            <!-- Worst Performing Schools Section -->
            <div id="worst" class="tab-content">
                <!-- Add worst performing schools details here -->
            </div>
        </div>
    </div>
    
    <script>
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
                
                this.classList.add('active');
                document.getElementById(this.dataset.target).classList.add('active');
                
                document.querySelectorAll('.top-links a').forEach(link => link.classList.remove('active'));
                document.querySelector(`.top-links a[href='#${this.dataset.target}']`).classList.add('active');
            });
        });
    </script>
</body>
</html>
