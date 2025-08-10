<?php
require_once 'db_connect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LUDEB Portal - <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <style>
    /* Admin Dashboard Styles */
    header#topbar .topbar-left .year_filter_fm #year_select_form .form-group { margin-bottom: 0 !important; }
    header#topbar .topbar-left .year_filter_fm #year_select_form .form-group .col-md-12 { padding-right: 0 !important; }
    header#topbar .topbar-left .year_filter_fm #year_select_form .form-group select {
      height: 27px !important; padding: 4px 8px !important; font-size: 12px !important; line-height: 1.0 !important;
    }
    .sidebar-menu > li > a { border-bottom: 1px solid #556b2f !important; }
    .page-header .page-header-top .top-menu .navbar-nav > li.dropdown-inbox > .dropdown-menu .dropdown-menu-list > li .subject,
    .page-header .page-header-top .top-menu .navbar-nav > li.dropdown-inbox > .dropdown-menu .dropdown-menu-list > li .message { margin-left: 0px !important; }
    .uneb_sum .table-scrollable, .ereg_sum .table-scrollable { margin-top: 0 !important; }
    .uneb_sum, .ereg_sum { padding-top: 0 !important; }
    td { font-size: 13px !important; }
    td .label { display: inline-block !important; margin-bottom: 5px !important; }
    .form-horizontal .form-group { margin-left: 0 !important; margin-right: 0 !important; }
    .nav-tabs > li > a, .nav-pills > li > a { font-size: 13px !important; padding-bottom: 3px !important; }
    .student-profile img.student-img { text-align: center; border-radius: 200px !important; width: 140px; height: 140px; margin-bottom: 25px; }
    .student-profile .col-md-12 { margin-bottom: 10px; text-align: center; }
    .student-profile .std-det { font-weight: bold; font-size: 14px; color: #00aba9; }
    .ereg-table thead th { background: #428BCA !important; color: #fff !important; }
    .paid_banner { position: relative; left: 25%; top: 14%; margin-bottom: 0; width: 45%; padding: 8px 7px; text-align: center; border: 2px solid green; border-radius: 15px !important; color: green; font-size: 35px; font-weight: bold; margin-top: -50px; transform: rotate(-30deg); }
    .paid_banner .paid_banner_date { font-size: 11px !important; }
    .paid_banner span { display: block; width: 98%; }
    .paid_banner.half { width: 50%; }
    .invoice .invoice-logo, .invoice .invoice-logo-space { margin-bottom: 0 !important; }
    .invoice .invoice-logo-space h1 { margin-bottom: 0 !important; margin-top: 0 !important; }
    .invoice hr { margin: 10px 0 !important; }
    .invoice .well { padding: 8px 19px; margin-bottom: 10px; }
    .invoice .list-unstyled.amounts { margin-top: 0 !important; }
    .invoice table { margin: 10px 0 20px 0 !important; }
    td .btn-group { min-width: 105px !important; }
    td .btn-group .btn { margin-right: 0 !important; }
    tr.row-clickable { cursor: pointer; }
    .progress-bar-pink { background-color: #9f00a7 !important; }
    .progress-bar-greenDark { background-color: #1e7145 !important; }
    /* Custom Styles */
    .badge-circle { display: inline-flex; justify-content: center; align-items: center; width: 24px; height: 24px; border-radius: 50%; background-color: #b91d47; color: #ffffff; font-weight: bold; margin-left: 5px; }
    .fixed-footer { position: fixed; bottom: 0; width: 100%; background-color: #1d1d1d; color: #ffffff; text-align: center; padding: 10px 0; z-index: 1000; }
  </style>
</head>
<body class="bg-gray-100 font-sans page-container-bg-solid page-boxed">
  <!-- Top Bar -->
  <div class="page-header-menu" style="display: none;">
    <div class="container">
      <div class="hor-menu">
        <ul class="nav navbar-nav">
          <li class="active"><a href="./index.php?year=2025">Home</a></li>
          <li><a href="./resources.php?year=2025">Resources</a></li>
          <li><a href="./announcements.php?year=2025">Announcements</a></li>
          <li><a href="./uploads.php?year=2025">Uploads</a></li>
          <li><a href="./feedback.php">Send Feedback</a></li>
          <!-- Additional items can be added as needed -->
        </ul>
      </div>
    </div>
  </div>

  <!-- Header -->
  <header id="topbar" class="bg-blue-600 text-white p-4">
    <div class="container mx-auto flex justify-between items-center">
      <div class="page-logo">
        <a href="./index.php"><h3 class="text-2xl">LUDEB Portal</h3></a>
      </div>
      <div class="flex space-x-4 items-center">
        <a href="javascript:;" class="menu-toggler">Menu</a>
        <div class="top-menu">
          <ul class="flex space-x-4 items-center">
            <li class="dropdown">
              <a href="javascript:;" class="dropdown-toggle">Announcements <span class="badge-circle"><?php
                $result = $conn->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'announcement' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
                echo $result->fetch_row()[0];
              ?></span></a>
              <ul class="dropdown-menu hidden absolute bg-white text-black mt-2 p-2">
                <li><a href="announcements.php" class="block">View all</a></li>
              </ul>
            </li>
            <li class="dropdown">
              <a href="javascript:;" class="dropdown-toggle">Resources <span class="badge-circle"><?php
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
                <li><a href="?logout" class="block">Log Out <i class="fas fa-sign-out-alt"></i></a></li>
              </ul>
            </li>
            <li><a href="?logout" class="hover:underline">Logout <i class="fas fa-sign-out-alt"></i></a></li>
          </ul>
        </div>
      </div>
    </div>
  </header>

  <!-- Page Head -->
  <div class="page-head">
    <div class="container">
      <div class="page-title">
        <h1><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?>
          <small>summaries</small>
        </h1>
      </div>
      <div class="page-toolbar">
        <form action="" id="year_select_form" style="min-width: 200px;">
          <div class="form-group">
            <label class="col-md-6" style="padding-top: 5px">Exam Year</label>
            <div class="col-md-6" style="padding-left: 0;">
              <select name="year" class="form-control" onchange="$('#year_select_form').submit()">
                <?php
                $result = $conn->query("SELECT exam_year FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC");
                while ($row = $result->fetch_assoc()) {
                  $selected = (isset($_GET['year']) && $_GET['year'] == $row['exam_year']) ? 'selected' : '';
                  echo "<option value='{$row['exam_year']}' $selected>{$row['exam_year']}</option>";
                }
                ?>
              </select>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Page Content -->
  <div class="page-content container mx-auto p-6">
    <main class="page-content-inner">
      <?php echo $content ?? ''; ?>
    </main>
  </div>

  <!-- Fixed Footer -->
  <footer class="fixed-footer">
    <div class="container mx-auto">
      <p>© 2024-<?php echo date('Y'); ?> LUDEB Portal supported by iLabs Technologies Uganda</p>
    </div>
  </footer>

  <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', (e) => {
          e.preventDefault();
          const menu = toggle.nextElementSibling;
          menu.classList.toggle('hidden');
        });
      });
      // Toggle top menu on menu-toggler click
      document.querySelector('.menu-toggler').addEventListener('click', () => {
        document.querySelector('.page-header-menu').style.display = (document.querySelector('.page-header-menu').style.display === 'block') ? 'none' : 'block';
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