<?php
require_once 'db_connect.php';
if (!isset($_SESSION['school_id'])) {
  header('Location: ../login.php');
  exit;
}

$schoolId = $_SESSION['school_id'];
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Get exam year ID
$stmt = $conn->prepare("SELECT id FROM exam_years WHERE exam_year = ?");
$stmt->bind_param("i", $year);
$stmt->execute();
$examYearResult = $stmt->get_result()->fetch_assoc();
$examYearId = $examYearResult ? $examYearResult['id'] : null;
$stmt->close();

// Get dashboard statistics
function getDashboardStats($conn, $schoolId, $examYearId, $year) {
    $stats = [];
    
    // Total Candidates
    if ($examYearId) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM candidates WHERE school_id = ? AND exam_year_id = ?");
        $stmt->bind_param("ii", $schoolId, $examYearId);
        $stmt->execute();
        $stats['total_candidates'] = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
    } else {
        $stats['total_candidates'] = 0;
    }
    
    // Results Processed
    if ($examYearId) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM candidate_results WHERE school_id = ? AND exam_year_id = ?");
        $stmt->bind_param("ii", $schoolId, $examYearId);
        $stmt->execute();
        $stats['results_processed'] = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
    } else {
        $stats['results_processed'] = 0;
    }
    
    // Pending Marks
    if ($examYearId) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM marks WHERE school_id = ? AND exam_year_id = ? AND mark IS NULL");
        $stmt->bind_param("ii", $schoolId, $examYearId);
        $stmt->execute();
        $stats['pending_marks'] = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
    } else {
        $stats['pending_marks'] = 0;
    }
    
    // Total Uploads
    $stmt = $conn->prepare("SELECT COUNT(*) FROM uploads WHERE school_id = ?");
    $stmt->bind_param("i", $schoolId);
    $stmt->execute();
    $stats['total_uploads'] = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    // Recent Resources
    $stmt = $conn->prepare("SELECT COUNT(*) FROM resources WHERE approved = 1 AND YEAR(created_at) = ? LIMIT 5");
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $stats['recent_resources'] = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    // Recent Announcements
    $stmt = $conn->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = 'announcement' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute();
    $stats['recent_announcements'] = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    return $stats;
}

// Get recent resources
function getRecentResources($conn, $year, $limit = 5) {
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
function getRecentAnnouncements($conn, $limit = 5) {
    $sql = "SELECT * FROM audit_logs 
            WHERE action = 'announcement' 
            ORDER BY created_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get recent results summary
function getRecentResults($conn, $schoolId, $examYearId, $limit = 5) {
    if (!$examYearId) return [];
    
    // Fixed: Use correct column names from the candidates table
    $sql = "SELECT cr.*, c.candidate_name, c.index_number
            FROM candidate_results cr
            JOIN candidates c ON cr.candidate_id = c.id
            WHERE cr.school_id = ? AND cr.exam_year_id = ?
            ORDER BY cr.created_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("iii", $schoolId, $examYearId, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$stats = getDashboardStats($conn, $schoolId, $examYearId, $year);
$recent_resources = getRecentResources($conn, $year);
$recent_announcements = getRecentAnnouncements($conn);
$recent_results = getRecentResults($conn, $schoolId, $examYearId);

$pageTitle = 'Dashboard';
ob_start();
?>

<!-- Dashboard Overview -->
<div class="mb-8">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Candidates Card -->
        <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Total Candidates</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo number_format($stats['total_candidates']); ?></p>
                    <p class="text-sm text-gray-500">for <?php echo $year; ?></p>
                </div>
            </div>
        </div>

        <!-- Results Processed Card -->
        <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                    <i class="fas fa-chart-line text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Results Processed</h3>
                    <p class="text-3xl font-bold text-green-600"><?php echo number_format($stats['results_processed']); ?></p>
                    <p class="text-sm text-gray-500">verified results</p>
                </div>
            </div>
        </div>

        <!-- Pending Marks Card -->
        <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-orange-100 text-orange-600 mr-4">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Pending Marks</h3>
                    <p class="text-3xl font-bold text-orange-600"><?php echo number_format($stats['pending_marks']); ?></p>
                    <p class="text-sm text-gray-500">awaiting input</p>
                </div>
            </div>
        </div>

        <!-- Total Uploads Card -->
        <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                    <i class="fas fa-cloud-upload-alt text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Total Uploads</h3>
                    <p class="text-3xl font-bold text-purple-600"><?php echo number_format($stats['total_uploads']); ?></p>
                    <p class="text-sm text-gray-500">your uploads</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Three Column Layout -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Resources Section -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-folder-open text-blue-600 mr-3"></i>Recent Resources
                </h2>
                <a href="./resources.php?year=<?php echo $year; ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <p class="text-gray-600 text-sm mt-1"><?php echo $stats['recent_resources']; ?> resources available for <?php echo $year; ?></p>
        </div>
        <div class="p-6">
            <?php if (empty($recent_resources)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-folder-open text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No resources available for <?php echo $year; ?></p>
                    <a href="./resources.php?year=<?php echo $year; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                        Browse all resources
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recent_resources as $resource): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-800 mb-2"><?php echo htmlspecialchars($resource['title']); ?></h4>
                                <div class="flex flex-wrap gap-2 mb-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                                        <i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars($resource['class']); ?>
                                    </span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-800">
                                        <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($resource['category']); ?>
                                    </span>
                                    <?php if ($resource['type'] === 'free'): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
                                        <i class="fas fa-gift mr-1"></i>Free
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-orange-100 text-orange-800">
                                        <i class="fas fa-crown mr-1"></i>Premium
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <span><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($resource['uploader_name']); ?></span>
                                    <span class="ml-4"><i class="fas fa-download mr-1"></i><?php echo $resource['download_count']; ?> downloads</span>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 ml-4">
                                <a href="./resources.php?year=<?php echo $year; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="./resources.php?year=<?php echo $year; ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-folder-open mr-2"></i>Browse All Resources
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Announcements Section -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-bullhorn text-green-600 mr-3"></i>Recent Announcements
                </h2>
                <a href="./announcements.php?year=<?php echo $year; ?>" class="text-green-600 hover:text-green-800 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <p class="text-gray-600 text-sm mt-1"><?php echo $stats['recent_announcements']; ?> announcements this month</p>
        </div>
        <div class="p-6">
            <?php if (empty($recent_announcements)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-bullhorn text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No recent announcements</p>
                    <a href="./announcements.php?year=<?php echo $year; ?>" class="text-green-600 hover:text-green-800 text-sm">
                        View all announcements
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach (array_slice($recent_announcements, 0, 5) as $announcement): ?>
                    <div class="border-l-4 border-green-500 pl-4 py-2">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <p class="font-medium text-gray-800 mb-1">New Announcement</p>
                                <p class="text-sm text-gray-600 mb-2">
                                    Action: <?php echo htmlspecialchars($announcement['action']); ?>
                                </p>
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-clock mr-1"></i>
                                    <?php echo date('M d, Y g:i A', strtotime($announcement['created_at'])); ?>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
                                <i class="fas fa-bell mr-1"></i>New
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="./announcements.php?year=<?php echo $year; ?>" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-bullhorn mr-2"></i>View All Announcements
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Results Section -->
<div class="bg-white rounded-lg shadow-md">
    <div class="p-6 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                <i class="fas fa-chart-bar text-indigo-600 mr-3"></i>Recent Results
            </h2>
            <a href="./results.php?year=<?php echo $year; ?>" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        <p class="text-gray-600 text-sm mt-1"><?php echo $stats['results_processed']; ?> results processed for <?php echo $year; ?></p>
    </div>
    <div class="p-6">
        <?php if (empty($recent_results)): ?>
            <div class="text-center py-8">
                <i class="fas fa-chart-bar text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">No results processed yet</p>
                <?php if ($stats['pending_marks'] > 0): ?>
                    <p class="text-orange-600 text-sm mt-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <?php echo $stats['pending_marks']; ?> marks pending input
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($recent_results as $result): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-800 mb-1">
                                <?php echo htmlspecialchars($result['candidate_name']); ?>
                            </h4>
                            <p class="text-sm text-gray-600 mb-2">
                                Index No: <?php echo htmlspecialchars($result['index_number']); ?>
                            </p>
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo date('M d, Y', strtotime($result['created_at'])); ?>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-indigo-100 text-indigo-800">
                            <i class="fas fa-check-circle mr-1"></i>Processed
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-4 text-center">
                <a href="./results.php?year=<?php echo $year; ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i>View All Results
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions Section -->
<div class="bg-white rounded-lg shadow-md mb-8">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-800 flex items-center">
            <i class="fas fa-bolt text-yellow-500 mr-3"></i>Quick Actions
        </h2>
        <p class="text-gray-600 text-sm mt-1">Frequently used features</p>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Upload Resource -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'School'): ?>
            <a href="../admin/resources/create.php" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors group">
                <div class="p-2 bg-blue-600 rounded-lg text-white mr-3 group-hover:bg-blue-700 transition-colors">
                    <i class="fas fa-upload"></i>
                </div>
                <div>
                    <h4 class="font-medium text-gray-800">Upload Resource</h4>
                    <p class="text-sm text-gray-600">Share learning materials</p>
                </div>
            </a>
            <?php endif; ?>

            <!-- Browse Resources -->
            <a href="./resources.php?year=<?php echo $year; ?>" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors group">
                <div class="p-2 bg-green-600 rounded-lg text-white mr-3 group-hover:bg-green-700 transition-colors">
                    <i class="fas fa-search"></i>
                </div>
                <div>
                    <h4 class="font-medium text-gray-800">Browse Resources</h4>
                    <p class="text-sm text-gray-600">Find study materials</p>
                </div>
            </a>

            <!-- View Results -->
            <a href="./results.php?year=<?php echo $year; ?>" class="flex items-center p-4 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors group">
                <div class="p-2 bg-indigo-600 rounded-lg text-white mr-3 group-hover:bg-indigo-700 transition-colors">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h4 class="font-medium text-gray-800">View Results</h4>
                    <p class="text-sm text-gray-600">Check exam results</p>
                </div>
            </a>

            <!-- Send Feedback -->
            <a href="./feedback.php" class="flex items-center p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors group">
                <div class="p-2 bg-orange-600 rounded-lg text-white mr-3 group-hover:bg-orange-700 transition-colors">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <div>
                    <h4 class="font-medium text-gray-800">Send Feedback</h4>
                    <p class="text-sm text-gray-600">Share your thoughts</p>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Activity Timeline -->
<!-- Activity Timeline - CORRECTED VERSION -->
<div class="bg-white rounded-lg shadow-md">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-800 flex items-center">
            <i class="fas fa-history text-gray-600 mr-3"></i>Recent Activity
        </h2>
        <p class="text-gray-600 text-sm mt-1">Latest updates and activities</p>
    </div>
    <div class="p-6">
        <div class="space-y-4">
            <!-- Combine recent activities from resources, announcements, and results -->
            <?php 
            $activities = [];
            
            // Add recent resources to activities
            foreach ($recent_resources as $resource) {
                $activities[] = [
                    'type' => 'resource',
                    'icon' => 'fa-file-alt',
                    'color' => 'blue',
                    'title' => 'New Resource: ' . $resource['title'],
                    'description' => $resource['category'] . ' for ' . $resource['class'],
                    'time' => $resource['created_at'],
                    'link' => "./resources.php?year=$year"
                ];
            }
            
            // Add recent announcements to activities
            foreach (array_slice($recent_announcements, 0, 3) as $announcement) {
                $activities[] = [
                    'type' => 'announcement',
                    'icon' => 'fa-bullhorn',
                    'color' => 'green',
                    'title' => 'New Announcement',
                    'description' => 'Action: ' . $announcement['action'],
                    'time' => $announcement['created_at'],
                    'link' => "./announcements.php?year=$year"
                ];
            }
            
            // Add recent results to activities - FIXED: Use correct field names
            foreach (array_slice($recent_results, 0, 3) as $result) {
                $activities[] = [
                    'type' => 'result',
                    'icon' => 'fa-chart-line',
                    'color' => 'indigo',
                    'title' => 'Result Processed: ' . $result['candidate_name'],
                    'description' => 'Index No: ' . $result['index_number'], // Fixed: changed from index_no to index_number
                    'time' => $result['created_at'],
                    'link' => "./results.php?year=$year"
                ];
            }
            
            // Sort activities by time (most recent first)
            usort($activities, function($a, $b) {
                return strtotime($b['time']) - strtotime($a['time']);
            });
            
            // Display activities (limit to 8)
            $activities = array_slice($activities, 0, 8);
            ?>
            
            <?php if (empty($activities)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-history text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No recent activity</p>
                </div>
            <?php else: ?>
                <?php $activity_count = 0; ?>
                <?php foreach ($activities as $activity): ?>
                    <?php $activity_count++; ?>
                    <div class="flex items-start activity-item">
                        <div class="p-2 bg-<?php echo $activity['color']; ?>-100 rounded-lg text-<?php echo $activity['color']; ?>-600 mr-4 mt-1">
                            <i class="fas <?php echo $activity['icon']; ?>"></i>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($activity['title']); ?></h4>
                                <span class="text-xs text-gray-500">
                                    <?php echo date('M d, g:i A', strtotime($activity['time'])); ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($activity['description']); ?></p>
                            <a href="<?php echo $activity['link']; ?>" class="text-<?php echo $activity['color']; ?>-600 hover:text-<?php echo $activity['color']; ?>-800 text-sm">
                                View details <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                    <?php if ($activity_count < count($activities)): ?>
                    <hr class="border-gray-200">
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Performance Insights (if results are available) -->
<?php if ($stats['results_processed'] > 0): ?>
<div class="mt-8 bg-white rounded-lg shadow-md">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-800 flex items-center">
            <i class="fas fa-analytics text-purple-600 mr-3"></i>Performance Insights
        </h2>
        <p class="text-gray-600 text-sm mt-1">Key metrics for <?php echo $year; ?></p>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Completion Rate -->
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                    <i class="fas fa-check-circle text-2xl text-green-600"></i>
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">Completion Rate</h4>
                <p class="text-2xl font-bold text-green-600">
                    <?php 
                    $completion_rate = $stats['total_candidates'] > 0 ? 
                        round(($stats['results_processed'] / $stats['total_candidates']) * 100, 1) : 0;
                    echo $completion_rate;
                    ?>%
                </p>
                <p class="text-sm text-gray-500">of candidates</p>
            </div>

            <!-- Pending Tasks -->
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-orange-100 rounded-full mb-4">
                    <i class="fas fa-tasks text-2xl text-orange-600"></i>
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">Pending Tasks</h4>
                <p class="text-2xl font-bold text-orange-600"><?php echo number_format($stats['pending_marks']); ?></p>
                <p class="text-sm text-gray-500">marks to input</p>
            </div>

            <!-- Resource Utilization -->
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-purple-100 rounded-full mb-4">
                    <i class="fas fa-folder-open text-2xl text-purple-600"></i>
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">Resources Shared</h4>
                <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['total_uploads']); ?></p>
                <p class="text-sm text-gray-500">total uploads</p>
            </div>
        </div>
        
        <!-- Progress Bars -->
        <div class="mt-6 space-y-4">
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Results Processing Progress</span>
                    <span class="text-sm text-gray-500"><?php echo $stats['results_processed']; ?>/<?php echo $stats['total_candidates']; ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-600 h-2 rounded-full transition-all duration-300" style="width: <?php echo $completion_rate; ?>%"></div>
                </div>
            </div>
            
            <?php if ($stats['pending_marks'] > 0): ?>
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Marks Input Progress</span>
                    <span class="text-sm text-gray-500"><?php echo $stats['pending_marks']; ?> remaining</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <?php 
                    $total_marks = $stats['results_processed'] + $stats['pending_marks'];
                    $marks_progress = $total_marks > 0 ? (($stats['results_processed'] / $total_marks) * 100) : 0;
                    ?>
                    <div class="bg-orange-600 h-2 rounded-full transition-all duration-300" style="width: <?php echo $marks_progress; ?>%"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- System Status -->
<div class="bg-white rounded-lg shadow-md">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-800 flex items-center">
            <i class="fas fa-server text-gray-600 mr-3"></i>System Status
        </h2>
        <p class="text-gray-600 text-sm mt-1">Current system information</p>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Database Status -->
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-green-100 rounded-full mb-3">
                    <i class="fas fa-database text-green-600"></i>
                </div>
                <h4 class="font-medium text-gray-800">Database</h4>
                <p class="text-sm text-green-600">
                    <i class="fas fa-check-circle mr-1"></i>Connected
                </p>
            </div>

            <!-- Current Year -->
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-100 rounded-full mb-3">
                    <i class="fas fa-calendar text-blue-600"></i>
                </div>
                <h4 class="font-medium text-gray-800">Current Year</h4>
                <p class="text-sm text-blue-600"><?php echo $year; ?></p>
            </div>

            <!-- Your Role -->
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-purple-100 rounded-full mb-3">
                    <i class="fas fa-user-tag text-purple-600"></i>
                </div>
                <h4 class="font-medium text-gray-800">Your Role</h4>
                <p class="text-sm text-purple-600"><?php echo htmlspecialchars($_SESSION['role'] ?? 'User'); ?></p>
            </div>

            <!-- Last Login -->
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-gray-100 rounded-full mb-3">
                    <i class="fas fa-clock text-gray-600"></i>
                </div>
                <h4 class="font-medium text-gray-800">Session</h4>
                <p class="text-sm text-gray-600">Active</p>
            </div>
        </div>
    </div>
</div>

<!-- Help & Support Section -->
<div class="mt-8 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6">
    <div class="text-center">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Need Help?</h3>
        <p class="text-gray-600 mb-4">Get support or learn how to use the LUDEB Portal effectively</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="./feedback.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-life-ring mr-2"></i>Get Support
            </a>
            <a href="./help.php" class="inline-flex items-center px-4 py-2 bg-white text-blue-600 border border-blue-600 rounded-lg hover:bg-blue-50 transition-colors">
                <i class="fas fa-book mr-2"></i>User Guide
            </a>
        </div>
    </div>
</div>

<script>
// Auto-refresh dashboard data every 5 minutes
setInterval(function() {
    // You can implement auto-refresh for specific sections here
    // For now, we'll just log that refresh would happen
    console.log('Dashboard refresh check...');
}, 300000); // 5 minutes

// Add smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Add loading states for quick action buttons
document.querySelectorAll('.group').forEach(button => {
    button.addEventListener('click', function() {
        const icon = this.querySelector('i');
        if (icon) {
            icon.classList.add('fa-spin');
            setTimeout(() => {
                icon.classList.remove('fa-spin');
            }, 1000);
        }
    });
});

// Animate numbers on page load
document.addEventListener('DOMContentLoaded', function() {
    const numberElements = document.querySelectorAll('.text-3xl.font-bold');
    
    numberElements.forEach(element => {
        const finalNumber = parseInt(element.textContent.replace(/,/g, ''));
        if (!isNaN(finalNumber)) {
            let currentNumber = 0;
            const increment = Math.ceil(finalNumber / 50); // Animate over ~50 steps
            const timer = setInterval(() => {
                currentNumber += increment;
                if (currentNumber >= finalNumber) {
                    element.textContent = finalNumber.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = currentNumber.toLocaleString();
                }
            }, 20); // Update every 20ms for smooth animation
        }
    });
});

// Add hover effects for activity items
document.querySelectorAll('.space-y-4 > div').forEach(item => {
    item.addEventListener('mouseenter', function() {
        this.style.transform = 'translateX(4px)';
        this.style.transition = 'transform 0.2s ease';
    });
    
    item.addEventListener('mouseleave', function() {
        this.style.transform = 'translateX(0)';
    });
});

// Show welcome message for new users (you can customize this logic)
<?php if (isset($_SESSION['first_login']) && $_SESSION['first_login']): ?>
setTimeout(function() {
    // You can add a welcome modal or notification here
    console.log('Welcome to LUDEB Portal!');
}, 1000);
<?php unset($_SESSION['first_login']); ?>
<?php endif; ?>
</script>

<style>
/* Additional dashboard-specific styles */
.dashboard-card {
    transition: all 0.3s ease;
}

.dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.activity-item {
    transition: all 0.2s ease;
}

.activity-item:hover {
    background-color: #f9fafb;
    padding-left: 1.5rem;
}

.quick-action-card {
    transition: all 0.2s ease;
}

.quick-action-card:hover {
    transform: scale(1.02);
}

.progress-bar {
    transition: width 0.5s ease-in-out;
}

/* Mobile responsive enhancements */
@media (max-width: 640px) {
    .grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .p-6 {
        padding: 1rem;
    }
    
    .text-3xl {
        font-size: 1.5rem;
    }
    
    .text-xl {
        font-size: 1.125rem;
    }
}

@media (max-width: 480px) {
    .grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
}

/* Loading animation */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.loading {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Custom scrollbar for activity section */
.space-y-4 {
    max-height: 400px;
    overflow-y: auto;
}

.space-y-4::-webkit-scrollbar {
    width: 6px;
}

.space-y-4::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.space-y-4::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.space-y-4::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}
</style>

<?php
$content = ob_get_clean();
include 'layout.php';
?>