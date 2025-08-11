<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['school_id'])) {
    header('Location: ../login.php');
    exit;
}

// Set page title
$pageTitle = 'Announcements';

// Get current exam year or selected year
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : null;
if (!$selectedYear) {
    $result = $conn->query("SELECT exam_year FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC LIMIT 1");
    $selectedYear = $result ? $result->fetch_row()[0] : date('Y');
}

// Get filters
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE clause for announcements
$where_conditions = ["al.action = 'announcement'", "YEAR(al.created_at) = ?"];
$params = [$selectedYear];
$param_types = "i";

// Category filter
if ($category_filter !== 'all') {
    $where_conditions[] = "JSON_EXTRACT(al.details, '$.category') = ?";
    $params[] = $category_filter;
    $param_types .= "s";
}

// Priority filter
if ($priority_filter !== 'all') {
    $where_conditions[] = "JSON_EXTRACT(al.details, '$.priority') = ?";
    $params[] = $priority_filter;
    $param_types .= "s";
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(al.description LIKE ? OR al.details LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM audit_logs al WHERE $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_announcements = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// Get announcements with pagination
$announcements_query = "SELECT 
                        al.id,
                        al.description as announcement_text,
                        al.created_at,
                        al.details,
                        s.school_name as source_school,
                        JSON_EXTRACT(al.details, '$.category') as category,
                        JSON_EXTRACT(al.details, '$.priority') as priority
                       FROM audit_logs al
                       LEFT JOIN schools s ON al.school_id = s.id
                       WHERE $where_clause
                       ORDER BY al.created_at DESC
                       LIMIT ? OFFSET ?";

$all_params = [...$params, $limit, $offset];
$all_param_types = $param_types . "ii";

$stmt = $conn->prepare($announcements_query);
$stmt->bind_param($all_param_types, ...$all_params);
$stmt->execute();
$result = $stmt->get_result();
$announcements = [];
while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}
$stmt->close();

// Calculate pagination
$total_pages = ceil($total_announcements / $limit);

// Get recent statistics for the stats section
$recent_stats = [];
$stats_query = "SELECT 
                COUNT(*) as total_announcements,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as this_week,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as this_month,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today
               FROM audit_logs 
               WHERE action = 'announcement' 
               AND YEAR(created_at) = ?";

$stmt = $conn->prepare($stats_query);
if ($stmt) {
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_stats = $result->fetch_assoc();
    $stmt->close();
}

// Get recent resources for quick access section
$recent_resources_query = "SELECT r.id, r.title, r.type, r.category, r.class, r.created_at,
                          COALESCE(dl.download_count, 0) as downloads
                          FROM resources r
                          LEFT JOIN (
                              SELECT resource_id, COUNT(*) as download_count 
                              FROM download_logs 
                              WHERE action = 'download'
                              GROUP BY resource_id
                          ) dl ON r.id = dl.resource_id
                          WHERE r.approved = 1 AND YEAR(r.created_at) = ?
                          ORDER BY r.created_at DESC
                          LIMIT 5";

$recent_resources_stmt = $conn->prepare($recent_resources_query);
$recent_resources_stmt->bind_param("i", $selectedYear);
$recent_resources_stmt->execute();
$recent_resources = $recent_resources_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_resources_stmt->close();

// Start output buffering for content
ob_start();
?>

<div class="flex flex-col lg:flex-row gap-6">
    <!-- Sidebar -->
    <aside class="lg:w-64 bg-white p-4 shadow rounded-lg h-fit">
        <!-- Search -->
        <div class="mb-6">
            <form method="GET" class="relative">
                <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                <input type="hidden" name="priority" value="<?php echo $priority_filter; ?>">
                <input type="text" name="search" placeholder="Search announcements..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       class="w-full p-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="submit" class="absolute right-3 top-3 text-gray-400 hover:text-blue-600">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <!-- Statistics -->
        <div class="mb-6 p-4 bg-blue-50 rounded-lg">
            <h4 class="font-semibold text-blue-800 mb-3">
                <i class="fas fa-chart-bar mr-2"></i>Statistics
            </h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span>Total:</span>
                    <span class="font-semibold"><?php echo $recent_stats['total_announcements'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-green-600">Today:</span>
                    <span class="font-semibold text-green-600"><?php echo $recent_stats['today'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-yellow-600">This Week:</span>
                    <span class="font-semibold text-yellow-600"><?php echo $recent_stats['this_week'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-purple-600">This Month:</span>
                    <span class="font-semibold text-purple-600"><?php echo $recent_stats['this_month'] ?? 0; ?></span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="space-y-4">
            <div>
                <h4 class="font-semibold mb-3">
                    <i class="fas fa-filter mr-2"></i>Filter by Category
                </h4>
                <ul class="space-y-1">
                    <?php 
                    $categories = [
                        'all' => 'All Categories', 
                        'system' => 'System Updates', 
                        'maintenance' => 'Maintenance', 
                        'policy' => 'Policy Changes',
                        'event' => 'Events',
                        'general' => 'General'
                    ];
                    foreach ($categories as $value => $label): 
                        $category_count_sql = "SELECT COUNT(*) as count FROM audit_logs WHERE action = 'announcement' AND YEAR(created_at) = ?" . ($value !== 'all' ? " AND JSON_EXTRACT(details, '$.category') = ?" : "");
                        $category_count_stmt = $conn->prepare($category_count_sql);
                        if ($value === 'all') {
                            $category_count_stmt->bind_param("i", $selectedYear);
                        } else {
                            $category_count_stmt->bind_param("is", $selectedYear, $value);
                        }
                        $category_count_stmt->execute();
                        $category_count = $category_count_stmt->get_result()->fetch_assoc()['count'];
                        $category_count_stmt->close();
                    ?>
                    <li>
                        <a href="?year=<?php echo $selectedYear; ?>&category=<?php echo $value; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>" 
                           class="block p-2 rounded hover:bg-blue-50 transition-colors <?php echo $category_filter === $value ? 'bg-blue-100 text-blue-600 font-medium' : 'text-gray-700'; ?>">
                            <?php echo $label; ?>
                            <span class="badge-circle"><?php echo $category_count; ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <h4 class="font-semibold mb-3">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Filter by Priority
                </h4>
                <ul class="space-y-1">
                    <?php 
                    $priorities = [
                        'all' => 'All Priorities', 
                        'high' => 'High Priority', 
                        'medium' => 'Medium Priority', 
                        'low' => 'Low Priority'
                    ];
                    foreach ($priorities as $value => $label): 
                        $priority_count_sql = "SELECT COUNT(*) as count FROM audit_logs WHERE action = 'announcement' AND YEAR(created_at) = ?" . ($value !== 'all' ? " AND JSON_EXTRACT(details, '$.priority') = ?" : "");
                        $priority_count_stmt = $conn->prepare($priority_count_sql);
                        if ($value === 'all') {
                            $priority_count_stmt->bind_param("i", $selectedYear);
                        } else {
                            $priority_count_stmt->bind_param("is", $selectedYear, $value);
                        }
                        $priority_count_stmt->execute();
                        $priority_count = $priority_count_stmt->get_result()->fetch_assoc()['count'];
                        $priority_count_stmt->close();
                    ?>
                    <li>
                        <a href="?year=<?php echo $selectedYear; ?>&priority=<?php echo $value; ?>&category=<?php echo $category_filter; ?>&search=<?php echo urlencode($search); ?>" 
                           class="block p-2 rounded hover:bg-blue-50 transition-colors <?php echo $priority_filter === $value ? 'bg-blue-100 text-blue-600 font-medium' : 'text-gray-700'; ?>">
                            <?php echo $label; ?>
                            <span class="badge-circle"><?php echo $priority_count; ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Quick Actions -->
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
        <div class="mt-6 pt-4 border-t">
            <button onclick="showCreateAnnouncementModal()" class="w-full btn bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center">
                <i class="fas fa-plus mr-2"></i>Create Announcement
            </button>
        </div>
        <?php endif; ?>
    </aside>

    <!-- Main Content -->
    <main class="flex-1">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-bullhorn text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-2xl font-bold text-gray-900"><?php echo $recent_stats['total_announcements'] ?? 0; ?></h4>
                        <p class="text-sm text-gray-600">Total Announcements</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-calendar-day text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-2xl font-bold text-gray-900"><?php echo $recent_stats['today'] ?? 0; ?></h4>
                        <p class="text-sm text-gray-600">Today</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-calendar-week text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-2xl font-bold text-gray-900"><?php echo $recent_stats['this_week'] ?? 0; ?></h4>
                        <p class="text-sm text-gray-600">This Week</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-calendar-alt text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-2xl font-bold text-gray-900"><?php echo $recent_stats['this_month'] ?? 0; ?></h4>
                        <p class="text-sm text-gray-600">This Month</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header Controls -->
        <div class="bg-white p-4 shadow rounded-lg mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold">
                        Announcements for <?php echo $selectedYear; ?>
                        <?php if (!empty($search)): ?>
                            <small class="text-gray-500">- Search: "<?php echo htmlspecialchars($search); ?>"</small>
                        <?php endif; ?>
                    </h2>
                    <p class="text-gray-600 text-sm">Showing <?php echo count($announcements); ?> of <?php echo $total_announcements; ?> announcements</p>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-2">
                    <button onclick="refreshAnnouncements()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded flex items-center justify-center transition-colors">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Refresh
                    </button>
                    <button onclick="markAllAsRead()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded flex items-center justify-center transition-colors">
                        <i class="fas fa-check-double mr-2"></i>
                        Mark All Read
                    </button>
                </div>
            </div>
        </div>

        <!-- Announcements List -->
        <div class="space-y-4 mb-8">
            <?php if (empty($announcements)): ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Announcements</h3>
                    <p class="text-gray-600">
                        <?php if (!empty($search)): ?>
                            No announcements match your search criteria. Try adjusting your filters.
                        <?php else: ?>
                            There are no announcements for <?php echo $selectedYear; ?> yet.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($search) || $category_filter !== 'all' || $priority_filter !== 'all'): ?>
                    <a href="?year=<?php echo $selectedYear; ?>" class="inline-block mt-4 text-blue-600 hover:text-blue-800">
                        <i class="fas fa-refresh mr-1"></i>Clear all filters
                    </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <?php 
                    $priority = json_decode($announcement['details'], true)['priority'] ?? 'medium';
                    $category = json_decode($announcement['details'], true)['category'] ?? 'general';
                    $priority_colors = [
                        'high' => 'border-l-red-500 bg-red-50',
                        'medium' => 'border-l-yellow-500 bg-yellow-50', 
                        'low' => 'border-l-green-500 bg-green-50'
                    ];
                    $priority_text_colors = [
                        'high' => 'text-red-600',
                        'medium' => 'text-yellow-600',
                        'low' => 'text-green-600'
                    ];
                    ?>
                    <div class="bg-white rounded-lg shadow hover:shadow-md transition-shadow border-l-4 <?php echo $priority_colors[$priority] ?? $priority_colors['medium']; ?>">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <div class="p-2 rounded-full bg-blue-100 text-blue-600 mr-3">
                                            <i class="fas fa-bullhorn text-sm"></i>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <h4 class="text-sm font-medium text-gray-900">
                                                    System Announcement
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ml-2 <?php echo $priority_text_colors[$priority] ?? $priority_text_colors['medium']; ?> bg-current bg-opacity-10">
                                                        <i class="fas fa-flag mr-1"></i><?php echo ucfirst($priority); ?>
                                                    </span>
                                                </div>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                    <i class="fas fa-tag mr-1"></i><?php echo ucfirst($category); ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500">
                                                <?php echo date('M d, Y \a\t H:i', strtotime($announcement['created_at'])); ?>
                                                <?php if ($announcement['source_school']): ?>
                                                    • from <?php echo htmlspecialchars($announcement['source_school']); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="ml-11">
                                        <p class="text-gray-800 mb-2">
                                            <?php echo htmlspecialchars($announcement['announcement_text']); ?>
                                        </p>
                                        <?php 
                                        $details = json_decode($announcement['details'], true);
                                        if ($details && isset($details['description'])): 
                                        ?>
                                            <div class="bg-gray-50 rounded p-3 mt-3">
                                                <p class="text-sm text-gray-700">
                                                    <?php echo nl2br(htmlspecialchars($details['description'])); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="ml-4 flex-shrink-0">
                                    <button onclick="toggleAnnouncementDetails(<?php echo $announcement['id']; ?>)" 
                                            class="text-gray-400 hover:text-gray-600 transition-colors">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Resources Section -->
        <?php if (!empty($recent_resources)): ?>
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-folder-open mr-2 text-blue-600"></i>Recent Resources
                    </h3>
                    <a href="resources.php?year=<?php echo $selectedYear; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                        View all <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <p class="text-sm text-gray-600 mt-1">Latest resources uploaded for <?php echo $selectedYear; ?></p>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <?php foreach ($recent_resources as $resource): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex items-center flex-1">
                            <div class="p-2 rounded bg-blue-100 text-blue-600 mr-3">
                                <i class="fas fa-file-alt text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-medium text-gray-900 truncate">
                                    <?php echo htmlspecialchars($resource['title']); ?>
                                </h4>
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                    <span><?php echo htmlspecialchars($resource['class']); ?></span>
                                    <span>•</span>
                                    <span><?php echo htmlspecialchars($resource['category']); ?></span>
                                    <span>•</span>
                                    <span class="<?php echo $resource['type'] === 'free' ? 'text-green-600' : 'text-orange-600'; ?>">
                                        <?php echo ucfirst($resource['type']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 ml-2">
                            <span class="text-xs text-gray-500">
                                <i class="fas fa-download mr-1"></i><?php echo $resource['downloads']; ?>
                            </span>
                            <a href="resources.php?year=<?php echo $selectedYear; ?>#resource-<?php echo $resource['id']; ?>" 
                               class="text-blue-600 hover:text-blue-800 transition-colors">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-8 flex justify-center">
            <nav class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                <a href="?year=<?php echo $selectedYear; ?>&page=<?php echo $page-1; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="px-3 py-2 text-sm border border-gray-300 rounded-l-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?year=<?php echo $selectedYear; ?>&page=<?php echo $i; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="px-3 py-2 text-sm border border-gray-300 hover:bg-gray-50 transition-colors <?php echo $i === $page ? 'bg-blue-600 text-white' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endforeach; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?year=<?php echo $selectedYear; ?>&page=<?php echo $page+1; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="px-3 py-2 text-sm border border-gray-300 rounded-r-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Create Announcement Modal (Admin Only) -->
<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
<div id="createAnnouncementModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-xl font-semibold">Create New Announcement</h3>
                    <button onclick="closeCreateAnnouncementModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form id="createAnnouncementForm" onsubmit="createAnnouncement(event)">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Announcement Title</label>
                            <input type="text" name="title" required 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter announcement title...">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                <select name="category" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select Category</option>
                                    <option value="system">System Updates</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="policy">Policy Changes</option>
                                    <option value="event">Events</option>
                                    <option value="general">General</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                                <select name="priority" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select Priority</option>
                                    <option value="high">High Priority</option>
                                    <option value="medium">Medium Priority</option>
                                    <option value="low">Low Priority</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Announcement Description</label>
                            <textarea name="description" required rows="4"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Enter detailed announcement description..."></textarea>
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <button type="button" onclick="closeCreateAnnouncementModal()" 
                                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-bullhorn mr-2"></i>Create Announcement
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Announcement Details Modal -->
<div id="announcementModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-xl font-semibold">Announcement Details</h3>
                    <button onclick="closeAnnouncementModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="announcementModalContent">
                    <!-- Content loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function refreshAnnouncements() {
    window.location.reload();
}

function markAllAsRead() {
    if (confirm('Mark all announcements as read?')) {
        fetch('api/mark_announcements_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'mark_all_read',
                year: <?php echo $selectedYear; ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to mark announcements as read: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while marking announcements as read.');
        });
    }
}

function toggleAnnouncementDetails(announcementId) {
    document.getElementById('announcementModal').classList.remove('hidden');
    document.getElementById('announcementModalContent').innerHTML = '<div class="text-center p-4"><i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i><p class="mt-2 text-gray-600">Loading...</p></div>';
    
    fetch(`api/announcement_details.php?id=${announcementId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const announcement = data.announcement;
                const details = announcement.details ? JSON.parse(announcement.details) : {};
                
                const priorityColors = {
                    'high': 'text-red-600 bg-red-100',
                    'medium': 'text-yellow-600 bg-yellow-100',
                    'low': 'text-green-600 bg-green-100'
                };
                
                const priorityColor = priorityColors[details.priority] || priorityColors['medium'];
                
                document.getElementById('announcementModalContent').innerHTML = `
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-semibold text-lg mb-2">${announcement.announcement_text}</h4>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm ${priorityColor}">
                                    <i class="fas fa-flag mr-1"></i>${details.priority ? details.priority.charAt(0).toUpperCase() + details.priority.slice(1) : 'Medium'} Priority
                                </span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-800">
                                    <i class="fas fa-tag mr-1"></i>${details.category ? details.category.charAt(0).toUpperCase() + details.category.slice(1) : 'General'}
                                </span>
                            </div>
                        </div>
                        
                        ${details.description ? `
                        <div>
                            <label class="font-medium text-gray-700 block mb-2">Description:</label>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-700 whitespace-pre-line">${details.description}</p>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <label class="font-medium text-gray-700">Created:</label>
                                <p class="text-gray-600">${new Date(announcement.created_at).toLocaleDateString()} at ${new Date(announcement.created_at).toLocaleTimeString()}</p>
                            </div>
                            <div>
                                <label class="font-medium text-gray-700">Source:</label>
                                <p class="text-gray-600">${announcement.source_school || 'System Administrator'}</p>
                            </div>
                        </div>
                        
                        <div class="pt-4 border-t">
                            <div class="flex justify-between items-center">
                                <button onclick="closeAnnouncementModal()" class="btn bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors">
                                    Close
                                </button>
                                <button onclick="markAnnouncementAsRead(${announcement.id})" class="btn bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-check mr-2"></i>Mark as Read
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('announcementModalContent').innerHTML = `
                    <div class="text-center p-4">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-600 mb-2"></i>
                        <p>Failed to load announcement details: ${data.error || 'Unknown error'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('announcementModalContent').innerHTML = `
                <div class="text-center p-4">
                    <i class="fas fa-exclamation-triangle text-2xl text-red-600 mb-2"></i>
                    <p>Error loading announcement details. Please try again.</p>
                </div>
            `;
        });
}

function closeAnnouncementModal() {
    document.getElementById('announcementModal').classList.add('hidden');
}

function markAnnouncementAsRead(announcementId) {
    fetch('api/mark_announcements_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'mark_single_read',
            announcement_id: announcementId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAnnouncementModal();
            // Optionally refresh the page or update the UI
        } else {
            alert('Failed to mark announcement as read: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while marking announcement as read.');
    });
}

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
function showCreateAnnouncementModal() {
    document.getElementById('createAnnouncementModal').classList.remove('hidden');
}

function closeCreateAnnouncementModal() {
    document.getElementById('createAnnouncementModal').classList.add('hidden');
    document.getElementById('createAnnouncementForm').reset();
}

function createAnnouncement(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = {
        title: formData.get('title'),
        category: formData.get('category'),
        priority: formData.get('priority'),
        description: formData.get('description')
    };
    
    fetch('api/create_announcement.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeCreateAnnouncementModal();
            location.reload();
        } else {
            alert('Failed to create announcement: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while creating the announcement.');
    });
}
<?php endif; ?>

// Modal event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Close modals on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAnnouncementModal();
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            closeCreateAnnouncementModal();
            <?php endif; ?>
        }
    });
    
    // Close modals on background click
    document.getElementById('announcementModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAnnouncementModal();
        }
    });
    
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
    document.getElementById('createAnnouncementModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCreateAnnouncementModal();
        }
    });
    <?php endif; ?>
});
</script>

<style>
/* Additional styles for announcements */
.announcement-item {
    transition: transform 0.2s ease;
}

.announcement-item:hover {
    transform: translateY(-1px);
}

.announcement-badge {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.btn {
    display: inline-flex;
    align-items: center;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.btn:hover {
    text-decoration: none;
    transform: translateY(-1px);
}

.badge-circle { 
    display: inline-flex; 
    justify-content: center; 
    align-items: center; 
    width: 20px; 
    height: 20px; 
    border-radius: 50%; 
    background-color: #b91d47; 
    color: #ffffff; 
    font-weight: bold; 
    font-size: 10px; 
    margin-left: 3px; 
}

/* Mobile responsive styles */
@media (max-width: 768px) {
    .grid-cols-4 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    
    .lg:flex-row {
        flex-direction: column;
    }
    
    .lg:w-64 {
        width: 100%;
    }
    
    .announcement-item .flex {
        flex-direction: column;
        gap: 1rem;
    }
    
    .announcement-item .ml-4 {
        margin-left: 0;
        margin-top: 1rem;
    }
    
    .grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .md:flex-row {
        flex-direction: column;
    }
    
    .flex-col.sm\:flex-row {
        flex-direction: row;
    }
}

@media (max-width: 480px) {
    .grid-cols-4,
    .md:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .px-6 {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .text-2xl {
        font-size: 1.5rem;
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.fa-spin {
    animation: spin 1s linear infinite;
}
</style>

<?php
$content = ob_get_clean();
include 'layout.php';
?>