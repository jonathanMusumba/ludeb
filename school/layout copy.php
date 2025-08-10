<?php
// No need for session_start() here since db_connect.php handles it
require_once 'db_connect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>School Interface - <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body class="bg-gray-100 font-sans">
  <!-- Header -->
  <header class="bg-blue-600 text-white p-4">
    <div class="container mx-auto flex justify-between items-center">
      <div class="page-logo">
        <a href="./index.php"><h3 class="text-2xl"><?php
          $result = $conn->query("SELECT board_name FROM settings WHERE exam_year_id = (SELECT id FROM exam_years WHERE exam_year = YEAR(CURDATE()))");
          echo htmlspecialchars($result->fetch_assoc()['board_name'] ?: 'School Interface');
        ?></h3></a>
      </div>
      <div class="flex space-x-4">
        <a href="javascript:;" class="menu-toggler">Menu</a>
        <div class="top-menu">
          <ul class="flex space-x-4">
            <li class="dropdown">
              <a href="javascript:;" class="dropdown-toggle">Announcements <span class="badge bg-red-500"><?php
                $stmt = $conn->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = 'announcement' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
                $stmt->execute();
                echo $stmt->get_result()->fetch_row()[0];
              ?></span></a>
              <ul class="dropdown-menu hidden absolute bg-white text-black mt-2 p-2">
                <li><a href="announcements.php" class="block">View all</a></li>
              </ul>
            </li>
            <li class="dropdown">
              <a href="javascript:;" class="dropdown-toggle">Resources <span class="badge bg-red-500"><?php
                $stmt = $conn->prepare("SELECT COUNT(*) FROM uploads WHERE school_id = ?");
                $stmt->bind_param("i", $_SESSION['school_id']);
                $stmt->execute();
                echo $stmt->get_result()->fetch_row()[0];
              ?></span></a>
              <ul class="dropdown-menu hidden absolute bg-white text-black mt-2 p-2">
                <li><a href="resources.php" class="block">View all</a></li>
              </ul>
            </li>
            <li class="dropdown">
              <a href="javascript:;" class="dropdown-toggle">
                <img src="assets/avatar.png" alt="Profile" class="w-8 h-8 rounded-full inline-block mr-2">
                <span><?php
                  $stmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
                  $stmt->bind_param("i", $_SESSION['school_id']);
                  $stmt->execute();
                  echo htmlspecialchars($stmt->get_result()->fetch_row()[0] ?: 'School Name');
                ?></span>
              </a>
              <ul class="dropdown-menu hidden absolute bg-white text-black mt-2 p-2">
                <li><a href="profile.php" class="block">My Profile</a></li>
                <li><a href="uploads.php" class="block">My Uploads</a></li>
                <li><a href="?logout" class="block">Log Out</a></li>
              </ul>
            </li>
            <li><a href="?logout" class="hover:underline">Logout</a></li>
            <li><a href="feedback.php" class="hover:underline">Send Feedback</a></li>
          </ul>
        </div>
      </div>
    </div>
  </header>

  <!-- Page Head -->
  <div class="page-head bg-white p-4 shadow">
    <div class="container mx-auto">
      <div class="page-title">
        <h1 class="text-2xl font-bold"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?> <small class="text-gray-500">details</small></h1>
      </div>
      <div class="page-toolbar">
        <form id="yearSelectForm" class="flex items-center space-x-2" method="GET" action="">
          <label class="text-lg">Exam Year:</label>
          <select name="year" id="examYear" class="p-2 border rounded" onchange="this.form.submit()">
            <?php
              $result = $conn->query("SELECT id, exam_year FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC");
              while ($row = $result->fetch_assoc()) {
                $selected = (isset($_GET['year']) && $_GET['year'] == $row['exam_year']) ? 'selected' : '';
                echo "<option value='{$row['exam_year']}' $selected>{$row['exam_year']}</option>";
              }
            ?>
          </select>
        </form>
      </div>
    </div>
  </div>

  <!-- Page Content -->
  <div class="page-content container mx-auto p-6">
    <ul class="page-breadcrumb flex space-x-2 text-gray-500 mb-4">
      <li><a href="index.php" class="hover:underline">Home</a></li>
      <li><span><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></span></li>
    </ul>

    <main class="page-content-inner">
      <?php echo $content ?? ''; ?>
    </main>
  </div>

  <!-- Pre-Footer -->
  <div class="page-prefooter bg-gray-200 p-4">
    <div class="container mx-auto">
      <?php
        if (isset($_SESSION['feedback_response'])) {
          echo '<div class="text-green-600">' . htmlspecialchars($_SESSION['feedback_response']) . '</div>';
          unset($_SESSION['feedback_response']);
        }
      ?>
    </div>
  </div>

  <!-- Footer -->
  <footer class="page-footer bg-gray-800 text-white p-4 text-center">
    <div class="container mx-auto">
      <p>2025 © School Interface</p>
    </div>
  </footer>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', (e) => {
          e.preventDefault();
          const menu = toggle.nextElementSibling;
          menu.classList.toggle('hidden');
        });
      });
    });
  </script>
</body>
</html>
<?php
  if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../login.php');
    exit;
  }
?>