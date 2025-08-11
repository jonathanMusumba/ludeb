<?php
require_once 'db_connect.php';


// Ensure user is logged in
if (!isset($_SESSION['school_id'])) {
  header('Location: ../login.php');
  exit();
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$pageTitle = 'Feedback';

// Handle feedback submission
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
  $feedback_text = trim($_POST['feedback_text']);
  $priority = in_array($_POST['priority'], ['High', 'Medium', 'Low']) ? $_POST['priority'] : 'Medium';
  
  if (empty($feedback_text)) {
    $error = 'Feedback text is required.';
  } else {
    // Generate unique ticket number (e.g., FB-20250811-123456)
    $ticket_number = 'FB-' . date('Ymd') . '-' . rand(100000, 999999);
    
    $stmt = $conn->prepare("INSERT INTO feedbacks (school_id, ticket_number, feedback_text, priority, status, submitted_at) VALUES (?, ?, ?, ?, 'open', NOW())");
    if ($stmt) {
      $stmt->bind_param("isss", $_SESSION['school_id'], $ticket_number, $feedback_text, $priority);
      if ($stmt->execute()) {
        $success = 'Feedback submitted successfully with ticket number: ' . htmlspecialchars($ticket_number);
      } else {
        $error = 'Failed to submit feedback. Please try again.';
      }
      $stmt->close();
    } else {
      $error = 'Database error: ' . $conn->error;
    }
  }
}

// Fetch feedback records for the school
$feedbacks = [];
$stmt = $conn->prepare("SELECT ticket_number, feedback_text, response_text, priority, status, submitted_at, responded_at FROM feedbacks WHERE school_id = ? AND YEAR(submitted_at) = ? ORDER BY submitted_at DESC");
if ($stmt) {
  $stmt->bind_param("ii", $_SESSION['school_id'], $year);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $feedbacks[] = $row;
  }
  $stmt->close();
}

ob_start();
?>
<div class="bg-white p-6 rounded-lg shadow">
  <h2 class="text-xl font-semibold mb-4">Submit Feedback</h2>
  <?php if ($success): ?>
    <div class="bg-green-100 text-green-700 p-4 rounded mb-4"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="bg-red-100 text-red-700 p-4 rounded mb-4"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <form method="POST" class="space-y-4">
    <div>
      <label for="feedback_text" class="block text-sm font-medium text-gray-700">Feedback</label>
      <textarea id="feedback_text" name="feedback_text" rows="5" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
    </div>
    <div>
      <label for="priority" class="block text-sm font-medium text-gray-700">Priority</label>
      <select id="priority" name="priority" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="Low">Low</option>
        <option value="Medium" selected>Medium</option>
        <option value="High">High</option>
      </select>
    </div>
    <button type="submit" name="submit_feedback" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Submit Feedback</button>
  </form>
</div>

<div class="bg-white p-6 rounded-lg shadow mt-6">
  <h2 class="text-xl font-semibold mb-4">Your Feedback</h2>
  <?php if (empty($feedbacks)): ?>
    <p class="text-gray-500">No feedback submitted for <?php echo htmlspecialchars($year); ?>.</p>
  <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left text-gray-700">
        <thead class="bg-blue-600 text-white">
          <tr>
            <th class="p-3">Ticket Number</th>
            <th class="p-3">Feedback</th>
            <th class="p-3">Response</th>
            <th class="p-3">Priority</th>
            <th class="p-3">Status</th>
            <th class="p-3">Submitted At</th>
            <th class="p-3">Responded At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($feedbacks as $feedback): ?>
            <tr class="border-b hover:bg-gray-50">
              <td class="p-3"><?php echo htmlspecialchars($feedback['ticket_number']); ?></td>
              <td class="p-3"><?php echo htmlspecialchars($feedback['feedback_text']); ?></td>
              <td class="p-3"><?php echo $feedback['response_text'] ? htmlspecialchars($feedback['response_text']) : 'No response yet'; ?></td>
              <td class="p-3">
                <span class="inline-block px-2 py-1 rounded <?php echo $feedback['priority'] === 'High' ? 'bg-red-100 text-red-700' : ($feedback['priority'] === 'Medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'); ?>">
                  <?php echo htmlspecialchars($feedback['priority']); ?>
                </span>
              </td>
              <td class="p-3">
                <span class="inline-block px-2 py-1 rounded <?php echo $feedback['status'] === 'open' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'; ?>">
                  <?php echo htmlspecialchars($feedback['status']); ?>
                </span>
              </td>
              <td class="p-3"><?php echo htmlspecialchars($feedback['submitted_at']); ?></td>
              <td class="p-3"><?php echo $feedback['responded_at'] ? htmlspecialchars($feedback['responded_at']) : '-'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once 'layout.php';
?>