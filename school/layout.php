<?php
// ===== LAYOUT.PHP FIXES =====

require_once 'db_connect.php';

// Handle logout at the top before any output
if (isset($_GET['logout'])) {
    // Include logout functionality here or redirect
    header('Location: ../logout.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LUDEB Portal - <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    .badge-circle { display: inline-flex; justify-content: center; align-items: center; width: 20px; height: 20px; border-radius: 50%; background-color: #b91d47; color: #ffffff; font-weight: bold; font-size: 10px; margin-left: 3px; }
    .fixed-footer { position: fixed; bottom: 0; width: 100%; background-color: #1d1d1d; color: #ffffff; text-align: center; padding: 10px 0; z-index: 1000; }
    .top-links { background: none; padding: 10px 0; }
    .top-links a { color: #333; margin-right: 15px; text-decoration: none; }
    .top-links a:hover { text-decoration: underline; }
    .user-image { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
    .dropdown-menu { display: none; position: absolute; z-index: 1000; background: white; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); min-width: 150px; }
    .dropdown-menu.show { display: block; }
    .page-head { border-bottom: 1px solid #ddd; }
    
    /* Mobile Menu Styles */
    .mobile-menu-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 998; display: none; }
    .mobile-menu-overlay.show { display: block; }
    
    .mobile-menu { position: fixed; top: 0; left: -100%; width: 280px; height: 100%; background: white; z-index: 999; transition: left 0.3s ease; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
    .mobile-menu.show { left: 0; }
    
    .mobile-menu-header { background: #2563eb; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
    .mobile-menu-close { background: none; border: none; color: white; font-size: 18px; cursor: pointer; }
    
    .mobile-menu-items { padding: 20px 0; }
    .mobile-menu-items a { display: flex; align-items: center; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #eee; }
    .mobile-menu-items a:hover { background: #f5f5f5; }
    .mobile-menu-items a i { width: 20px; margin-right: 10px; }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .top-links { padding: 8px 0; }
      .top-links .flex { flex-wrap: wrap; gap: 8px; }
      .user-image { width: 28px; height: 28px; }
      .badge-circle { width: 16px; height: 16px; font-size: 9px; margin-left: 2px; }
      .dropdown-menu { right: 0; left: auto; min-width: 120px; }
      .page-head { padding: 12px 16px !important; }
      .page-head .flex { flex-wrap: wrap; gap: 10px; }
      .page-content { padding: 16px !important; margin-top: 16px !important; }
      .container { padding-left: 16px; padding-right: 16px; }
      
      /* Top links mobile adjustments */
      .top-links > div:first-child { flex: 1; }
      .top-links > div:last-child { flex: 2; justify-content: flex-end; flex-wrap: wrap; }
    }
    
    @media (max-width: 480px) {
      .top-links .flex > div:last-child { justify-content: center; width: 100%; margin-top: 8px; }
      .page-head .page-title h1 { font-size: 1.5rem !important; }
    }
  </style>
</head>
<body class="bg-gray-100 font-sans page-container-bg-solid page-boxed">
  <!-- Mobile Menu Overlay -->
  <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
  
  <!-- Mobile Menu -->
  <div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu-header">
      <span class="font-semibold">Menu</span>
      <button class="mobile-menu-close" id="mobileMenuClose">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="mobile-menu-items">
      <a href="./resources.php?year=2025">
        <i class="fas fa-folder-open text-blue-600"></i>
        <span>Resources</span>
      </a>
      <a href="./announcements.php?year=2025">
        <i class="fas fa-bullhorn text-green-600"></i>
        <span>Announcements</span>
      </a>
      <a href="./uploads.php?year=2025">
        <i class="fas fa-cloud-upload-alt text-purple-600"></i>
        <span>Uploads</span>
      </a>
      <a href="./feedback.php">
        <i class="fas fa-comment-dots text-orange-600"></i>
        <span>Send Feedback</span>
      </a>
      <a href="#" class="text-gray-400 cursor-not-allowed">
        <i class="fas fa-cog text-gray-400"></i>
        <span>ERP</span>
      </a>
    </div>
  </div>

  <!-- Top Links -->
  <div class="top-links container mx-auto flex justify-between items-center">
    <div class="flex items-center">
      <!-- Mobile Menu Button -->
      <button class="md:hidden mr-3 p-2" id="mobileMenuBtn">
        <i class="fas fa-bars text-xl"></i>
      </button>
      <a href="./index.php" class="text-lg font-semibold">LUDEB Portal</a>
    </div>
    <div class="flex items-center gap-3 flex-wrap">
      <div class="dropdown relative">
        <a href="javascript:;" class="dropdown-toggle flex items-center text-sm md:text-base">
          <i class="fas fa-bullhorn mr-1 text-green-600"></i>
          <span class="hidden sm:inline">Announcements</span>
          <span class="sm:hidden">Ann.</span>
          <span class="badge-circle"><?php
            $result = $conn->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'announcement' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
            echo $result ? $result->fetch_row()[0] : 0;
          ?></span>
        </a>
        <ul class="dropdown-menu">
          <li><a href="announcements.php" class="block p-2 hover:bg-gray-100">View all</a></li>
        </ul>
      </div>
      
      <div class="dropdown relative">
        <a href="javascript:;" class="dropdown-toggle flex items-center text-sm md:text-base">
          <i class="fas fa-folder-open mr-1 text-blue-600"></i>
          <span class="hidden sm:inline">Resources</span>
          <span class="sm:hidden">Res.</span>
          <span class="badge-circle"><?php
            // Fixed: Check if session exists before using it
            if (isset($_SESSION['school_id'])) {
              $stmt = $conn->prepare("SELECT COALESCE((SELECT COUNT(*) FROM uploads WHERE school_id = ?), 0) + COALESCE((SELECT COUNT(*) FROM resources WHERE school_id = ?), 0) AS total");
              if ($stmt) {
                $stmt->bind_param("ii", $_SESSION['school_id'], $_SESSION['school_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                echo $result->fetch_row()[0];
                $stmt->close();
              } else {
                echo 0;
              }
            } else {
              echo 0;
            }
          ?></span>
        </a>
        <ul class="dropdown-menu">
          <li><a href="resources.php" class="block p-2 hover:bg-gray-100">View all</a></li>
        </ul>
      </div>
      
      <img src="../lib/avatar.png" alt="User Image" class="user-image">
      
      <div class="dropdown relative">
        <a href="javascript:;" class="dropdown-toggle flex items-center text-sm md:text-base">
          <span class="hidden sm:inline">
            <?php
              // Fixed: Check if session exists and handle null values
              if (isset($_SESSION['school_id'])) {
                $stmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
                if ($stmt) {
                  $stmt->bind_param("i", $_SESSION['school_id']);
                  $stmt->execute();
                  $result = $stmt->get_result();
                  $row = $result->fetch_row();
                  echo htmlspecialchars($row && $row[0] ? $row[0] : 'School Name');
                  $stmt->close();
                } else {
                  echo 'School Name';
                }
              } else {
                echo 'School Name';
              }
            ?>
          </span>
          <span class="sm:hidden">School</span>
          <i class="fas fa-chevron-down ml-1 text-xs"></i>
        </a>
        <ul class="dropdown-menu">
          <li><a href="profile.php" class="block p-2 hover:bg-gray-100">
            <i class="fas fa-user mr-2"></i>My Profile
          </a></li>
          <li><a href="uploads.php" class="block p-2 hover:bg-gray-100">
            <i class="fas fa-cloud-upload-alt mr-2"></i>My Uploads 
            <span class="badge-circle"><?php
              if (isset($_SESSION['school_id'])) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM uploads WHERE school_id = ?");
                if ($stmt) {
                  $stmt->bind_param("i", $_SESSION['school_id']);
                  $stmt->execute();
                  echo $stmt->get_result()->fetch_row()[0];
                  $stmt->close();
                } else {
                  echo 0;
                }
              } else {
                echo 0;
              }
            ?></span>
          </a></li>
          <li><a href="../logout.php" class="block p-2 hover:bg-gray-100">
            <i class="fas fa-sign-out-alt mr-2"></i>Log Out
          </a></li>
        </ul>
      </div>
      
      <a href="../logout.php" class="flex items-center hover:underline text-sm md:text-base">
        <i class="fas fa-sign-out-alt mr-1"></i>
        <span class="hidden sm:inline">Logout</span>
        <span class="sm:hidden">Out</span>
      </a>
    </div>
  </div>

  <!-- Menu Strip (Hidden on Mobile) -->
  <div class="page-header-menu bg-blue-600 text-white p-4 hidden md:block">
    <div class="container mx-auto">
      <ul class="flex justify-between items-center">
        <div class="flex space-x-6">
          <li>
            <a href="./resources.php?year=2025" class="hover:underline flex items-center">
              <i class="fas fa-folder-open mr-2"></i>Resources
            </a>
          </li>
          <li>
            <a href="./announcements.php?year=2025" class="hover:underline flex items-center">
              <i class="fas fa-bullhorn mr-2"></i>Announcements
            </a>
          </li>
          <li>
            <a href="./uploads.php?year=2025" class="hover:underline flex items-center">
              <i class="fas fa-cloud-upload-alt mr-2"></i>Uploads
            </a>
          </li>
          <li>
            <a href="./feedback.php" class="hover:underline flex items-center">
              <i class="fas fa-comment-dots mr-2"></i>Send Feedback
            </a>
          </li>
        </div>
        <div>
          <li>
            <span class="cursor-not-allowed text-blue-200 flex items-center">
              <i class="fas fa-cog mr-2"></i>ERP
            </span>
          </li>
        </div>
      </ul>
    </div>
  </div>

  <!-- Page Head -->
  <div class="page-head bg-white p-4 shadow">
    <div class="container mx-auto flex justify-between items-center">
      <div class="page-title">
        <h1 class="text-2xl font-bold"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?>
          <small class="text-gray-500 ml-2">summaries</small>
        </h1>
      </div>
      <div class="page-toolbar">
        <form action="" id="year_select_form" class="inline-block min-w-[150px] md:min-w-[200px]">
          <div class="form-group flex items-center">
            <label class="block text-sm font-medium text-gray-700 pr-2 whitespace-nowrap" style="padding-top: 5px">Exam Year</label>
            <div class="flex-1">
              <select name="year" class="form-control w-full p-1 text-sm border border-gray-300 rounded" onchange="document.getElementById('year_select_form').submit()">
                <?php
                $result = $conn->query("SELECT exam_year FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC");
                if ($result) {
                  while ($row = $result->fetch_assoc()) {
                    $selected = (isset($_GET['year']) && $_GET['year'] == $row['exam_year']) ? 'selected' : '';
                    echo "<option value='{$row['exam_year']}' $selected>{$row['exam_year']}</option>";
                  }
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
  <div class="page-content container mx-auto p-6 mt-4 pb-20">
    <main class="page-content-inner">
      <?php echo $content ?? ''; ?>
    </main>
  </div>

  <!-- Fixed Footer -->
  <footer class="fixed-footer">
    <div class="container mx-auto">
      <p class="text-sm">© 2024-<?php echo date('Y'); ?> LUDEB Portal supported by iLabs Technologies Uganda</p>
    </div>
  </footer>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // Mobile menu functionality
      const mobileMenuBtn = document.getElementById('mobileMenuBtn');
      const mobileMenu = document.getElementById('mobileMenu');
      const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
      const mobileMenuClose = document.getElementById('mobileMenuClose');

      function showMobileMenu() {
        mobileMenu.classList.add('show');
        mobileMenuOverlay.classList.add('show');
        document.body.style.overflow = 'hidden';
      }

      function hideMobileMenu() {
        mobileMenu.classList.remove('show');
        mobileMenuOverlay.classList.remove('show');
        document.body.style.overflow = '';
      }

      if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', showMobileMenu);
      }

      if (mobileMenuClose) {
        mobileMenuClose.addEventListener('click', hideMobileMenu);
      }

      if (mobileMenuOverlay) {
        mobileMenuOverlay.addEventListener('click', hideMobileMenu);
      }

      // Dropdown functionality
      const dropdowns = document.querySelectorAll('.dropdown');
      dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        if (toggle && menu) {
          toggle.addEventListener('click', (e) => {
            e.preventDefault();
            // Close other dropdowns
            dropdowns.forEach(otherDropdown => {
              if (otherDropdown !== dropdown) {
                const otherMenu = otherDropdown.querySelector('.dropdown-menu');
                if (otherMenu) {
                  otherMenu.classList.remove('show');
                  otherMenu.style.display = 'none';
                }
              }
            });
            // Toggle current dropdown
            menu.classList.toggle('show');
            menu.style.display = menu.classList.contains('show') ? 'block' : 'none';
          });
        }
      });

      // Hide dropdowns when clicking outside
      document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown')) {
          document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show');
            menu.style.display = 'none';
          });
        }
      });

      // Close mobile menu on escape key
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          hideMobileMenu();
        }
      });
    });
  </script>
</body>
</html>

<?php
