<?php
require_once 'db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['school_id'])) {
  header('Location: ../login.php');
  exit();
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$pageTitle = 'Feedback & Support';
$schoolId = $_SESSION['school_id'];

// Handle feedback submission
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
  $feedback_text = trim($_POST['feedback_text']);
  $priority = in_array($_POST['priority'], ['High', 'Medium', 'Low']) ? $_POST['priority'] : 'Medium';
  $category = in_array($_POST['category'], ['Technical', 'Content', 'Feature Request', 'Bug Report', 'General']) ? $_POST['category'] : 'General';
  
  if (empty($feedback_text)) {
    $error = 'Feedback text is required.';
  } else {
    // Generate unique ticket number (e.g., FB-20250811-123456)
    $ticket_number = 'FB-' . date('Ymd') . '-' . rand(100000, 999999);
    
    $stmt = $conn->prepare("INSERT INTO feedbacks (school_id, ticket_number, feedback_text, priority, category, status, submitted_at) VALUES (?, ?, ?, ?, ?, 'open', NOW())");
    if ($stmt) {
      $stmt->bind_param("issss", $_SESSION['school_id'], $ticket_number, $feedback_text, $priority, $category);
      if ($stmt->execute()) {
        $success = 'Feedback submitted successfully! Your ticket number is: ' . htmlspecialchars($ticket_number) . '. We will respond within 24-48 hours.';
        $_POST = array();
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
$stmt = $conn->prepare("SELECT id, ticket_number, feedback_text, response_text, priority, category, status, submitted_at, responded_at 
                        FROM feedbacks 
                        WHERE school_id = ? AND YEAR(submitted_at) = ? 
                        ORDER BY submitted_at DESC");
if ($stmt) {
  $stmt->bind_param("ii", $_SESSION['school_id'], $year);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $feedbacks[] = $row;
  }
  $stmt->close();
} else {
  error_log("Feedback query failed: " . $conn->error);
  $error = 'Failed to fetch feedback history. Please try again later.';
}

// Debug: Log the number of feedbacks retrieved
error_log("Retrieved " . count($feedbacks) . " feedbacks for school_id: " . $_SESSION['school_id'] . ", year: " . $year);

// Get recent resources
function getRecentResources($conn, $year, $limit = 3) {
    $sql = "SELECT r.*, u.username as uploader_name,
            COALESCE(dl.download_count, 0) as download_count
            FROM resources r 
            LEFT JOIN system_users u ON r.uploader_id = u.id
            LEFT JOIN (
                SELECT resource_id, COUNT(*) as download_count 
                FROM download_logs 
                WHERE action = 'download'
                GROUP BY resource_id
            ) dl ON r.id = dl.resource_id
            WHERE r.approved = 1 AND YEAR(r.created_at) = ?
            ORDER BY r.created_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $year, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get recent announcements
function getRecentAnnouncements($conn, $year, $limit = 3) {
    $sql = "SELECT a.*, u.username as uploader_name
            FROM announcements a
            LEFT JOIN system_users u ON a.uploader_id = u.id
            WHERE YEAR(a.created_at) = ?
            ORDER BY a.created_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $year, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get feedback statistics
function getFeedbackStats($conn, $schoolId, $year) {
    $stats = [];
    
    // Total feedbacks this year
    $stmt = $conn->prepare("SELECT COUNT(*) FROM feedbacks WHERE school_id = ? AND YEAR(submitted_at) = ?");
    $stmt->bind_param("ii", $schoolId, $year);
    $stmt->execute();
    $stats['total'] = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    // Open feedbacks
    $stmt = $conn->prepare("SELECT COUNT(*) FROM feedbacks WHERE school_id = ? AND status = 'open' AND YEAR(submitted_at) = ?");
    $stmt->bind_param("ii", $schoolId, $year);
    $stmt->execute();
    $stats['open'] = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    // Closed feedbacks
    $stmt = $conn->prepare("SELECT COUNT(*) FROM feedbacks WHERE school_id = ? AND status = 'closed' AND YEAR(submitted_at) = ?");
    $stmt->bind_param("ii", $schoolId, $year);
    $stmt->execute();
    $stats['closed'] = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    // Responded feedbacks
    $stmt = $conn->prepare("SELECT COUNT(*) FROM feedbacks WHERE school_id = ? AND response_text IS NOT NULL AND status = 'closed' AND YEAR(submitted_at) = ?");
    $stmt->bind_param("ii", $schoolId, $year);
    $stmt->execute();
    $stats['responded'] = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    return $stats;
}

$recent_resources = getRecentResources($conn, $year);
$recent_announcements = getRecentAnnouncements($conn, $year);
$feedback_stats = getFeedbackStats($conn, $schoolId, $year);

ob_start();
?>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <!-- Left Column - Feedback Form and History (2/3 width) -->
  <div class="lg:col-span-2 space-y-6">
    
    <!-- Feedback Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <!-- Total Feedback -->
      <div class="bg-white p-4 rounded-lg shadow-md">
        <div class="flex items-center">
          <div class="p-2 rounded-full bg-blue-100 text-blue-600 mr-3">
            <i class="fas fa-comments text-lg"></i>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-700">Total Feedback</h3>
            <p class="text-xl font-bold text-blue-600"><?php echo number_format($feedback_stats['total']); ?></p>
          </div>
        </div>
      </div>

      <!-- Open Tickets -->
      <div class="bg-white p-4 rounded-lg shadow-md">
        <div class="flex items-center">
          <div class="p-2 rounded-full bg-orange-100 text-orange-600 mr-3">
            <i class="fas fa-clock text-lg"></i>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-700">Open Tickets</h3>
            <p class="text-xl font-bold text-orange-600"><?php echo number_format($feedback_stats['open']); ?></p>
          </div>
        </div>
      </div>

      <!-- Closed -->
      <div class="bg-white p-4 rounded-lg shadow-md">
        <div class="flex items-center">
          <div class="p-2 rounded-full bg-green-100 text-green-600 mr-3">
            <i class="fas fa-check-circle text-lg"></i>
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-700">Closed</h3>
            <p class="text-xl font-bold text-green-600"><?php echo number_format($feedback_stats['closed']); ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Submit Feedback Form -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-800">
          <i class="fas fa-paper-plane text-blue-600 mr-2"></i>Submit New Feedback
        </h2>
        <div class="text-sm text-gray-500">
          <i class="fas fa-info-circle mr-1"></i>We respond within 24-48 hours
        </div>
      </div>

      <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-4 rounded mb-4 border border-green-300">
          <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded mb-4 border border-red-300">
          <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <!-- Category Selection -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-tags mr-1"></i>Category
            </label>
            <select id="category" name="category" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
              <option value="">Select a category...</option>
              <option value="Technical" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Technical') ? 'selected' : ''; ?>>
                Technical Issue
              </option>
              <option value="Content" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Content') ? 'selected' : ''; ?>>
                Content Related
              </option>
              <option value="Feature Request" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Feature Request') ? 'selected' : ''; ?>>
                Feature Request
              </option>
              <option value="Bug Report" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Bug Report') ? 'selected' : ''; ?>>
                Bug Report
              </option>
              <option value="General" <?php echo (isset($_POST['category']) && $_POST['category'] === 'General') ? 'selected' : ''; ?>>
                General Inquiry
              </option>
            </select>
          </div>

          <div>
            <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-exclamation-triangle mr-1"></i>Priority
            </label>
            <select id="priority" name="priority" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="Low" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'Low') ? 'selected' : ''; ?>>
                Low Priority
              </option>
              <option value="Medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] === 'Medium') ? 'selected' : ''; ?>>
                Medium Priority
              </option>
              <option value="High" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'High') ? 'selected' : ''; ?>>
                High Priority
              </option>
            </select>
          </div>
        </div>

        <!-- Feedback Text -->
        <div>
          <label for="feedback_text" class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-comment-alt mr-1"></i>Your Feedback
          </label>
          <textarea 
            id="feedback_text" 
            name="feedback_text" 
            rows="6" 
            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
            placeholder="Please describe your feedback, issue, or suggestion in detail..."
            required><?php echo isset($_POST['feedback_text']) ? htmlspecialchars($_POST['feedback_text']) : ''; ?></textarea>
          <div class="text-sm text-gray-500 mt-2">
            <i class="fas fa-info-circle mr-1"></i>Please provide as much detail as possible to help us assist you better.
          </div>
        </div>

        <!-- Submit Button -->
        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
          <div class="text-sm text-gray-500">
            <i class="fas fa-shield-alt mr-1"></i>Your feedback is confidential and will only be seen by our support team.
          </div>
          <button 
            type="submit" 
            name="submit_feedback" 
            class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium flex items-center"
          >
            <i class="fas fa-paper-plane mr-2"></i>Submit Feedback
          </button>
        </div>
      </form>
    </div>

    <!-- Feedback History -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-800">
          <i class="fas fa-history text-gray-600 mr-2"></i>Your Feedback History
        </h2>
        <div class="text-sm text-gray-500">
          Showing feedback for <?php echo $year; ?>
        </div>
      </div>

      <?php if (empty($feedbacks)): ?>
        <div class="text-center py-12">
          <i class="fas fa-comment-slash text-4xl text-gray-300 mb-4"></i>
          <p class="text-gray-500 text-lg">No feedback submitted for <?php echo htmlspecialchars($year); ?></p>
          <p class="text-sm text-gray-400">Your feedback history will appear here once you submit your first feedback.</p>
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($feedbacks as $feedback): ?>
            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
              <div class="flex items-start justify-between mb-3">
                <div class="flex items-center space-x-3">
                  <div class="font-medium text-gray-800">
                    <i class="fas fa-ticket-alt text-blue-600 mr-1"></i>
                    <?php echo htmlspecialchars($feedback['ticket_number']); ?>
                  </div>
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs 
                    <?php echo $feedback['priority'] === 'High' ? 'bg-red-100 text-red-800' : 
                        ($feedback['priority'] === 'Medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                    <i class="fas fa-flag mr-1"></i><?php echo htmlspecialchars($feedback['priority']); ?>
                  </span>
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-800">
                    <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($feedback['category']); ?>
                  </span>
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs 
                    <?php echo $feedback['status'] === 'open' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'; ?>">
                    <i class="fas fa-circle mr-1 text-xs"></i><?php echo htmlspecialchars(ucfirst($feedback['status'])); ?>
                  </span>
                </div>
                <div class="text-sm text-gray-500">
                  <?php echo date('M d, Y g:i A', strtotime($feedback['submitted_at'])); ?>
                </div>
              </div>

              <!-- Feedback Content -->
              <div class="mb-3">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Your Feedback:</h4>
                <p class="text-gray-600 bg-gray-50 p-3 rounded border-l-4 border-blue-200">
                  <?php echo nl2br(htmlspecialchars($feedback['feedback_text'])); ?>
                </p>
              </div>

              <!-- Response -->
              <?php if ($feedback['response_text']): ?>
                <div class="border-t border-gray-200 pt-3">
                  <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-medium text-green-700">
                      <i class="fas fa-reply mr-1"></i>Support Team Response:
                    </h4>
                    <div class="text-sm text-gray-500">
                      <?php echo date('M d, Y g:i A', strtotime($feedback['responded_at'])); ?>
                    </div>
                  </div>
                  <p class="text-gray-600 bg-green-50 p-3 rounded border-l-4 border-green-200">
                    <?php echo nl2br(htmlspecialchars($feedback['response_text'])); ?>
                  </p>
                </div>
              <?php else: ?>
                <div class="border-t border-gray-200 pt-3">
                  <div class="text-center py-2">
                    <p class="text-gray-500 text-sm">
                      <i class="fas fa-clock mr-1"></i>Waiting for support team response...
                    </p>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Load More Button (if there are many feedbacks) -->
        <?php if (count($feedbacks) >= 10): ?>
          <div class="text-center mt-6">
            <button class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition-colors">
              <i class="fas fa-chevron-down mr-2"></i>Load More
            </button>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right Column - Announcements and Resources (1/3 width) -->
  <div class="space-y-6">
    <!-- Recent Announcements -->
    <div class="bg-white rounded-lg shadow-md">
      <div class="p-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-800 flex items-center">
            <i class="fas fa-bullhorn text-green-600 mr-2"></i>Recent Announcements
          </h3>
          <a href="./announcements.php?year=<?php echo $year; ?>" class="text-green-600 hover:text-green-800 text-sm font-medium">
            View All <i class="fas fa-arrow-right ml-1"></i>
          </a>
        </div>
      </div>
      <div class="p-4">
        <?php if (empty($recent_announcements)): ?>
          <div class="text-center py-6">
            <i class="fas fa-bullhorn text-2xl text-gray-300 mb-2"></i>
            <p class="text-gray-500 text-sm">No announcements for <?php echo $year; ?></p>
          </div>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($recent_announcements as $announcement): ?>
            <div class="border-l-4 border-green-500 pl-3 py-2 hover:bg-gray-50 transition-colors">
              <p class="font-medium text-gray-800 text-sm mb-1">
                <?php echo htmlspecialchars(substr($announcement['content'], 0, 60)) . (strlen($announcement['content']) > 60 ? '...' : ''); ?>
              </p>
              <div class="flex flex-wrap gap-2 mb-2">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                  <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars(ucfirst($announcement['category'])); ?>
                </span>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs 
                  <?php echo $announcement['priority'] === 'high' ? 'bg-red-100 text-red-800' : 
                      ($announcement['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                  <i class="fas fa-exclamation-circle mr-1"></i><?php echo htmlspecialchars(ucfirst($announcement['priority'])); ?>
                </span>
              </div>
              <div class="text-xs text-gray-500">
                <i class="fas fa-clock mr-1"></i><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          
          <div class="mt-4 text-center">
            <a href="./announcements.php?year=<?php echo $year; ?>" class="text-green-600 hover:text-green-800 text-sm font-medium">
              <i class="fas fa-bullhorn mr-1"></i>View All Announcements
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Resources -->
    <div class="bg-white rounded-lg shadow-md">
      <div class="p-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-800 flex items-center">
            <i class="fas fa-folder-open text-blue-600 mr-2"></i>Recent Resources
          </h3>
          <a href="./resources.php?year=<?php echo $year; ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
            View All <i class="fas fa-arrow-right ml-1"></i>
          </a>
        </div>
      </div>
      <div class="p-4">
        <?php if (empty($recent_resources)): ?>
          <div class="text-center py-6">
            <i class="fas fa-folder-open text-2xl text-gray-300 mb-2"></i>
            <p class="text-gray-500 text-sm">No resources for <?php echo $year; ?></p>
          </div>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($recent_resources as $resource): ?>
            <div class="border border-gray-200 rounded p-3 hover:shadow-sm transition-shadow">
              <h4 class="font-medium text-gray-800 text-sm mb-2">
                <?php echo htmlspecialchars(substr($resource['title'], 0, 50)) . (strlen($resource['title']) > 50 ? '...' : ''); ?>
              </h4>
              <div class="flex flex-wrap gap-2 mb-2">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                  <i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars($resource['class']); ?>
                </span>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-800">
                  <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($resource['category']); ?>
                </span>
              </div>
              <div class="text-xs text-gray-500">
                <span><i class="fas fa-download mr-1"></i><?php echo $resource['download_count']; ?> downloads</span>
                <span class="ml-3"><i class="fas fa-clock mr-1"></i><?php echo date('M d, Y', strtotime($resource['created_at'])); ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          
          <div class="mt-4 text-center">
            <a href="./resources.php?year=<?php echo $year; ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
              <i class="fas fa-folder-open mr-1"></i>View All Resources
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>