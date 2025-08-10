<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

// Database connection
$host = 'localhost';
$db = 'ludeb';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch user data
$userId = $_SESSION['user_id'];
$query = "SELECT username, role FROM users WHERE id = :id";
$stmt = $pdo->prepare($query);
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="#">Dashboard</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item"><a class="nav-link" href="#">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Profile</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>
    <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="sidebar-header">
                    <h4>Receptionist Dashboard</h4>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" id="dashboardLink">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="inmatesLink">Inmates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="visitorsLink">Visitors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="approvalsLink">Approvals</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="notificationsLink">Notifications</a>
                    </li>
                </ul>
            </nav>

    <div class="container">
        <h2>Welcome, <?php echo htmlspecialchars($user['username']); ?></h2>
        <p>Your role is: <?php echo htmlspecialchars($user['role']); ?></p>

        <?php
        // Display content based on role
        switch ($user['role']) {
            case 'System Admin':
                echo '<h3>System Admin Dashboard</h3>';
                // Fetch pending users
                $pendingQuery = "SELECT id, username, email, role FROM users WHERE status = 'pending'";
                $pendingStmt = $pdo->prepare($pendingQuery);
                $pendingStmt->execute();
                $pendingUsers = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

                if ($pendingUsers) {
                    echo '<h4>Pending User Approvals</h4>';
                    echo '<table class="table table-bordered">';
                    echo '<thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Action</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($pendingUsers as $pendingUser) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($pendingUser['username']) . '</td>';
                        echo '<td>' . htmlspecialchars($pendingUser['email']) . '</td>';
                        echo '<td>' . htmlspecialchars($pendingUser['role']) . '</td>';
                        echo '<td>';
                        echo '<form action="approve.php" method="post" style="display:inline;">';
                        echo '<input type="hidden" name="user_id" value="' . htmlspecialchars($pendingUser['id']) . '">';
                        echo '<button type="submit" class="btn btn-success">Approve</button>';
                        echo '</form> ';
                        echo '<form action="reject_user.php" method="post" style="display:inline;">';
                        echo '<input type="hidden" name="user_id" value="' . htmlspecialchars($pendingUser['id']) . '">';
                        echo '<button type="submit" class="btn btn-danger">Reject</button>';
                        echo '</form>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody>';
                    echo '</table>';
                } else {
                    echo '<p>No pending users for approval.</p>';
                }
                break;

                case 'Receptionist':
                    echo '<h3>Receptionist Dashboard</h3>';

                    // Display visitor form
                    ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h4>Register Visitor</h4>
                        </div>
                        <div class="card-body">
                            <form id="visitorForm">
                                <div class="form-group">
                                    <label for="firstName">First Name</label>
                                    <input type="text" class="form-control" id="firstName" name="firstName" required>
                                </div>
                                <div class="form-group">
                                    <label for="otherNames">Other Names</label>
                                    <input type="text" class="form-control" id="otherNames" name="otherNames">
                                </div>
                                <div class="form-group">
                                    <label for="sex">Sex</label>
                                    <select class="form-control" id="sex" name="sex" required>
                                        <option value="M">Male</option>
                                        <option value="F">Female</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="district">District/Municipality/City</label>
                                    <select class="form-control" id="district" name="district" required>
                                        <!-- Options will be populated dynamically -->
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="subcounty">Subcounty/Town Council/Division</label>
                                    <input type="text" class="form-control" id="subcounty" name="subcounty" required>
                                </div>
                                <div class="form-group">
                                    <label for="parish">Parish/Ward</label>
                                    <input type="text" class="form-control" id="parish" name="parish" required>
                                </div>
                                <div class="form-group">
                                    <label for="village">Village/Cell</label>
                                    <input type="text" class="form-control" id="village" name="village" required>
                                </div>
                                <div class="form-group">
                                    <label for="telephone">Telephone</label>
                                    <input type="text" class="form-control" id="telephone" name="telephone" required>
                                </div>
                                <div class="form-group">
                                    <label for="nationalId">National ID</label>
                                    <input type="text" class="form-control" id="nationalId" name="nationalId" required>
                                </div>
                                <div class="form-group">
                                    <label for="country">Country of Origin (For Foreigners)</label>
                                    <input type="text" class="form-control" id="country" name="country">
                                </div>
                                <div class="form-group">
                                    <label for="passportNumber">Passport Number (For Foreigners)</label>
                                    <input type="text" class="form-control" id="passportNumber" name="passportNumber">
                                </div>
                                <div class="form-group">
                                    <label for="reasonForVisit">Reason for Visit</label>
                                    <input type="text" class="form-control" id="reasonForVisit" name="reasonForVisit" required>
                                </div>
                                <div class="form-group" id="inmateSection" style="display: none;">
                                    <label for="inmateSearch">Inmate Name</label>
                                    <input type="text" class="form-control" id="inmateSearch" name="inmateSearch">
                                    <input type="hidden" id="inmateNumber" name="inmateNumber">
                                </div>
                                <button type="submit" class="btn btn-primary">Save Visitor</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Inmate Information Modal -->
                    <div class="modal fade" id="inmateModal" tabindex="-1" role="dialog" aria-labelledby="inmateModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="inmateModalLabel">Add Inmate</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="inmateForm">
                                        <div class="form-group">
                                            <label for="inmateNumber">Inmate Number</label>
                                            <input type="text" class="form-control" id="inmateNumber" name="inmateNumber" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="inmateName">Name</label>
                                            <input type="text" class="form-control" id="inmateName" name="inmateName" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="inmateDetails">Details</label>
                                            <textarea class="form-control" id="inmateDetails" name="inmateDetails"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Save Inmate</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Token Generation Section -->
                    <div id="tokenGeneration" class="card mb-3">
                        <div class="card-header">
                            <h4>Generate Token</h4>
                        </div>
                        <div class="card-body">
                            <p id="tokenDetails"></p>
                        </div>
                    </div>

                    <!-- Approvals Section -->
                    <div id="approvalsSection" class="card mb-3">
                        <div class="card-header">
                            <h4>Pending Approvals</h4>
                        </div>
                        <div class="card-body">
                            <!-- List of pending approvals will be loaded here -->
                        </div>
                    </div>

                    <!-- Notifications Section -->
                    <div id="notificationsSection" class="card mb-3">
                        <div class="card-header">
                            <h4>Notifications</h4>
                        </div>
                        <div class="card-body">
                            <!-- List of notifications will be loaded here -->
                        </div>
                    </div>

                    <?php
                    break;

            case 'Warden':
                echo '<h3>Warden Dashboard</h3>';
                // Warden specific content
                break;

            case 'Analyst':
                echo '<h3>Analyst Dashboard</h3>';
                // Analyst specific content
                break;

            case 'Staff':
                echo '<h3>Staff Dashboard</h3>';
                // Staff specific content
                break;

            default:
                echo '<h3>Dashboard</h3>';
                // Default content
                break;
        }
        ?>
                <!-- Toast notification -->
            <div class="toast" id="statusToast" style="position: absolute; top: 10px; right: 10px;" data-delay="3000">
            <div class="toast-header">
                <strong class="mr-auto">Notification</strong>
                <button type="button" class="ml-2 mb-1 close" data-dismiss="toast">&times;</button>
            </div>
            <div class="toast-body">
                <?php
                if (isset($_GET['action'])) {
                    $action = $_GET['action'];
                    if ($action == 'approved') {
                        echo 'User has been approved successfully.';
                    } elseif ($action == 'rejected') {
                        echo 'User has been rejected.';
                    }
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>
