<?php
require_once 'db_connect.php';


// Ensure user is logged in
if (!isset($_SESSION['school_id'])) {
  header('Location: ../login.php');
  exit();
}

$pageTitle = 'Help';

ob_start();
?>
<div class="bg-white p-6 rounded-lg shadow">
  <h2 class="text-xl font-semibold mb-4">LUDEB Portal Help</h2>
  <p class="text-gray-700 mb-4">Welcome to the LUDEB Portal Help page. Here you can find guidance on navigating and using the portal effectively.</p>
  
  <h3 class="text-lg font-semibold mt-6 mb-2">Getting Started</h3>
  <ul class="list-disc pl-6 text-gray-700">
    <li><strong>Dashboard</strong>: Access the main dashboard at <a href="./index.php" class="text-blue-600 hover:bg-gray-100 rounded px-1">Home</a> to view summaries and updates.</li>
    <li><strong>Resources</strong>: Browse educational materials under <a href="./resources.php?year=<?php echo isset($_GET['year']) ? (int)$_GET['year'] : date('Y'); ?>" class="text-blue-600 hover:bg-gray-100 rounded px-1">Resources</a>.</li>
    <li><strong>Announcements</strong>: Stay updated with important notices at <a href="./announcements.php?year=<?php echo isset($_GET['year']) ? (int)$_GET['year'] : date('Y'); ?>" class="text-blue-600 hover:bg-gray-100 rounded px-1">Announcements</a>.</li>
    <li><strong>Uploads</strong>: Manage your uploaded files at <a href="./Uploads.php?year=<?php echo isset($_GET['year']) ? (int)$_GET['year'] : date('Y'); ?>" class="text-blue-600 hover:bg-gray-100 rounded px-1">Uploads</a>.</li>
    <li><strong>Feedback</strong>: Submit feedback or view responses at <a href="./feedback.php" class="text-blue-600 hover:bg-gray-100 rounded px-1">Send Feedback</a>.</li>
  </ul>
  
  <h3 class="text-lg font-semibold mt-6 mb-2">Submitting Feedback</h3>
  <p class="text-gray-700 mb-4">To submit feedback:
    <ol class="list-decimal pl-6">
      <li>Navigate to the <a href="./feedback.php" class="text-blue-600 hover:bg-gray-100 rounded px-1">Send Feedback</a> page.</li>
      <li>Enter your feedback message and select a priority (High, Medium, Low).</li>
      <li>Submit the form to receive a unique ticket number.</li>
      <li>Check the feedback table for responses from the LUDEB team.</li>
    </ol>
  </p>
  
  <h3 class="text-lg font-semibold mt-6 mb-2">Troubleshooting</h3>
  <p class="text-gray-700 mb-4">If you encounter issues:
    <ul class="list-disc pl-6">
      <li>Ensure you are logged in with valid credentials.</li>
      <li>Clear your browser cache if pages do not load correctly.</li>
      <li>Contact support via the <a href="./feedback.php" class="text-blue-600 hover:bg-gray-100 rounded px-1">Send Feedback</a> page for assistance.</li>
    </ul>
  </p>
  
  <h3 class="text-lg font-semibold mt-6 mb-2">Contact Us</h3>
  <p class="text-gray-700">For further assistance, submit a feedback ticket or reach out to iLabs Technologies Uganda via the contact details in the portal footer.</p>
</div>

<?php
$content = ob_get_clean();
require_once 'layout.php';
?>