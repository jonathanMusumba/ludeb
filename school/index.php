<?php
require_once 'db_connect.php';
if (!isset($_SESSION['school_id'])) {
  header('Location: ../login.php');
  exit;
}

$schoolId = $_SESSION['school_id'];
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$stmt = $conn->prepare("SELECT id FROM exam_years WHERE exam_year = ?");
$stmt->bind_param("i", $year);
$stmt->execute();
$examYearId = $stmt->get_result()->fetch_assoc()['id'];

ob_start();
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
  <div class="bg-white p-4 rounded-lg shadow">
    <h3 class="text-xl font-semibold">Total Candidates</h3>
    <p class="text-2xl"><?php
      $stmt = $conn->prepare("SELECT COUNT(*) FROM candidates WHERE school_id = ? AND exam_year_id = ?");
      $stmt->bind_param("ii", $schoolId, $examYearId);
      $stmt->execute();
      $result = $stmt->get_result();
      echo $result->fetch_row()[0];
    ?></p>
  </div>
  <div class="bg-white p-4 rounded-lg shadow">
    <h3 class="text-xl font-semibold">Results Processed</h3>
    <p class="text-2xl"><?php
      $stmt = $conn->prepare("SELECT COUNT(*) FROM candidate_results WHERE school_id = ? AND exam_year_id = ?");
      $stmt->bind_param("ii", $schoolId, $examYearId);
      $stmt->execute();
      $result = $stmt->get_result();
      echo $result->fetch_row()[0];
    ?></p>
  </div>
  <div class="bg-white p-4 rounded-lg shadow">
    <h3 class="text-xl font-semibold">Pending Marks</h3>
    <p class="text-2xl"><?php
      $stmt = $conn->prepare("SELECT COUNT(*) FROM marks WHERE school_id = ? AND exam_year_id = ? AND mark IS NULL");
      $stmt->bind_param("ii", $schoolId, $examYearId);
      $stmt->execute();
      $result = $stmt->get_result();
      echo $result->fetch_row()[0];
    ?></p>
  </div>
  <div class="bg-white p-4 rounded-lg shadow">
    <h3 class="text-xl font-semibold">Uploads</h3>
    <p class="text-2xl"><?php
      $stmt = $conn->prepare("SELECT COUNT(*) FROM uploads WHERE school_id = ?");
      $stmt->bind_param("i", $schoolId);
      $stmt->execute();
      $result = $stmt->get_result();
      echo $result->fetch_row()[0];
    ?></p>
  </div>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Dashboard';
include 'layout.php';
?>