<?php
require_once 'db_connect.php';
if (!isset($_SESSION['school_id'])) {
    header('Location: ../login.php');
    exit;
}

$schoolId = $_SESSION['school_id'];
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$category_filter = isset($_GET['category']) && in_array($_GET['category'], ['all', 'system', 'maintenance', 'policy', 'event', 'general']) ? $_GET['category'] : 'all';
$priority_filter = isset($_GET['priority']) && in_array($_GET['priority'], ['all', 'high', 'medium', 'low']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build the query for announcements
$sql = "SELECT a.*, u.username as uploader_name 
        FROM announcements a 
        LEFT JOIN system_users u ON a.uploader_id = u.id 
        WHERE YEAR(a.created_at) = ?";
$params = [$selectedYear];
$types = "i";

if ($category_filter !== 'all') {
    $sql .= " AND a.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}
if ($priority_filter !== 'all') {
    $sql .= " AND a.priority = ?";
    $params[] = $priority_filter;
    $types .= "s";
}
if (!empty($search)) {
    $sql .= " AND a.content LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

$sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total announcements for pagination
$count_sql = "SELECT COUNT(*) as total 
              FROM announcements 
              WHERE YEAR(created_at) = ?";
$count_params = [$selectedYear];
$count_types = "i";

if ($category_filter !== 'all') {
    $count_sql .= " AND category = ?";
    $count_params[] = $category_filter;
    $count_types .= "s";
}
if ($priority_filter !== 'all') {
    $count_sql .= " AND priority = ?";
    $count_params[] = $priority_filter;
    $count_types .= "s";
}
if (!empty($search)) {
    $count_sql .= " AND content LIKE ?";
    $count_params[] = "%$search%";
    $count_types .= "s";
}

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($count_types, ...$count_params);
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_items / $per_page);

// Get recent resources
$resources_sql = "SELECT r.*, u.username as uploader_name,
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
                 LIMIT 5";
$resources_stmt = $conn->prepare($resources_sql);
$resources_stmt->bind_param("i", $selectedYear);
$resources_stmt->execute();
$recent_resources = $resources_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$resources_stmt->close();

$pageTitle = 'Announcements';
ob_start();
?>

<div class="flex flex-col lg:flex-row gap-6">
    <!-- Sidebar -->
    <aside class="lg:w-1/4 bg-white rounded-lg shadow-md p-6">
        <!-- Search Form -->
        <div class="mb-6">
            <form action="" method="GET" class="flex gap-2">
                <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                <input type="hidden" name="priority" value="<?php echo $priority_filter; ?>">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search announcements..." 
                       class="flex-1 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <button type="submit" class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <!-- Filter by Category -->
        <div class="mb-6">
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
                    $category_count_sql = "SELECT COUNT(*) as count FROM announcements WHERE YEAR(created_at) = ?" . ($value !== 'all' ? " AND category = ?" : "");
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

        <!-- Filter by Priority -->
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
                    $priority_count_sql = "SELECT COUNT(*) as count FROM announcements WHERE YEAR(created_at) = ?" . ($value !== 'all' ? " AND priority = ?" : "");
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
    </aside>

    <!-- Main Content -->
    <div class="lg:w-3/4">
        <!-- Announcements List -->
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-bullhorn text-green-600 mr-3"></i>
                    Announcements for <?php echo $selectedYear; ?>
                </h2>
                <p class="text-gray-600 text-sm mt-1"><?php echo $total_items; ?> announcements found</p>
            </div>
            <div class="p-6">
                <?php if (empty($announcements)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-bullhorn text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">No announcements found for the selected filters.</p>
                        <a href="?year=<?php echo $selectedYear; ?>&category=all&priority=all" 
                           class="text-blue-600 hover:text-blue-800 text-sm">
                            Clear filters
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($announcements as $announcement): ?>
                        <div class="border-l-4 <?php echo $announcement['priority'] === 'high' ? 'border-red-500' : ($announcement['priority'] === 'medium' ? 'border-yellow-500' : 'border-green-500'); ?> pl-4 py-2">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-800 mb-1"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                    <div class="flex flex-wrap gap-2 mb-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                                            <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($announcement['category']); ?>
                                        </span>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs <?php echo $announcement['priority'] === 'high' ? 'bg-red-100 text-red-800' : ($announcement['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                            <i class="fas fa-exclamation-circle mr-1"></i><?php echo htmlspecialchars($announcement['priority']); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <span><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($announcement['uploader_name'] ?? 'Unknown'); ?></span>
                                        <span class="ml-4"><i class="fas fa-clock mr-1"></i><?php echo date('M d, Y g:i A', strtotime($announcement['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center items-center gap-2">
            <?php if ($page > 1): ?>
            <a href="?year=<?php echo $selectedYear; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>" 
               class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?year=<?php echo $selectedYear; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
               class="px-3 py-1 rounded <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?year=<?php echo $selectedYear; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>" 
               class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Recent Resources -->
        <div class="bg-white rounded-lg shadow-md mt-6">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-folder-open text-blue-600 mr-3"></i>Recent Resources
                </h2>
                <p class="text-gray-600 text-sm mt-1"><?php echo count($recent_resources); ?> resources available for <?php echo $selectedYear; ?></p>
            </div>
            <div class="p-6">
                <?php if (empty($recent_resources)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-folder-open text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">No resources available for <?php echo $selectedYear; ?></p>
                        <a href="./resources.php?year=<?php echo $selectedYear; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
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
                                    <a href="./resources.php?year=<?php echo $selectedYear; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="./resources.php?year=<?php echo $selectedYear; ?>" 
                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-folder-open mr-2"></i>Browse All Resources
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