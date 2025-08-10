<?php
// ===== SCHOOL/RESOURCES.PHP (ENHANCED) =====

require_once 'db_connect.php';

// Handle error messages
$error_messages = [
    'invalid_resource' => 'Invalid resource requested.',
    'resource_not_found' => 'Resource not found or not available.',
    'access_denied' => 'You do not have permission to access this resource.',
    'download_limit_exceeded' => 'You have reached the maximum download limit for this resource.',
    'file_not_found' => 'The resource file could not be found on the server.',
    'file_read_error' => 'There was an error reading the resource file.',
    'download_failed' => 'Download failed. Please try again later.'
];

$success_messages = [
    'payment_submitted' => 'Payment submitted successfully! Your payment is being verified.',
    'download_success' => 'Resource downloaded successfully!'
];

// Get current year from URL parameter or default to current year
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get filters
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$class_filter = isset($_GET['class']) ? $_GET['class'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get messages
$error = isset($_GET['error']) ? $_GET['error'] : '';
$success = isset($_GET['success']) ? $_GET['success'] : '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_conditions = ["r.approved = 1"]; // Only show approved resources
$params = [];
$param_types = "";

// Year filter
$where_conditions[] = "YEAR(r.created_at) = ?";
$params[] = $current_year;
$param_types .= "i";

// Type filter
if ($filter !== 'all') {
    $where_conditions[] = "r.type = ?";
    $params[] = $filter;
    $param_types .= "s";
}

// Class filter
if ($class_filter !== 'all') {
    $where_conditions[] = "r.class = ?";
    $params[] = $class_filter;
    $param_types .= "s";
}

// Category filter
if ($category_filter !== 'all') {
    $where_conditions[] = "r.category = ?";
    $params[] = $category_filter;
    $param_types .= "s";
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(r.title LIKE ? OR r.category LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

$where_clause = implode(" AND ", $where_conditions);

// Order by clause
$order_by = $sort === 'date_asc' ? "r.created_at ASC" : "r.created_at DESC";

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM resources r 
              LEFT JOIN system_users u ON r.uploader_id = u.id 
              WHERE $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_resources = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_resources / $per_page);

// Get resources
$sql = "SELECT r.*, u.username as uploader_name,
        CASE 
            WHEN r.type = 'free' THEN 1
            WHEN ura.id IS NOT NULL THEN 1 
            ELSE 0 
        END as has_access,
        pt.status as payment_status,
        ura.download_count,
        ura.max_downloads,
        COALESCE(dl.download_count, 0) as total_downloads
        FROM resources r 
        LEFT JOIN system_users u ON r.uploader_id = u.id
        LEFT JOIN user_resource_access ura ON r.id = ura.resource_id AND ura.user_id = ?
        LEFT JOIN payment_transactions pt ON r.id = pt.resource_id AND pt.user_id = ? AND pt.status IN ('verified', 'pending')
        LEFT JOIN (
            SELECT resource_id, COUNT(*) as download_count 
            FROM download_logs 
            WHERE action = 'download'
            GROUP BY resource_id
        ) dl ON r.id = dl.resource_id
        WHERE $where_clause 
        ORDER BY $order_by 
        LIMIT ? OFFSET ?";

// Add user_id params to the beginning
$all_params = [$_SESSION['user_id'], $_SESSION['user_id'], ...$params, $per_page, $offset];
$all_param_types = "ii" . $param_types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($all_param_types, ...$all_params);
$stmt->execute();
$resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN type = 'free' THEN 1 ELSE 0 END) as free_count,
    SUM(CASE WHEN type = 'premium' THEN 1 ELSE 0 END) as premium_count
    FROM resources r WHERE r.approved = 1 AND YEAR(r.created_at) = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $current_year);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$pageTitle = "Resources";
ob_start();
?>

<!-- Alert Messages -->
<?php if (!empty($error) && isset($error_messages[$error])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo $error_messages[$error]; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($success) && isset($success_messages[$success])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $success_messages[$success]; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="flex flex-col lg:flex-row gap-6">
    <!-- Sidebar -->
    <aside class="lg:w-64 bg-white p-4 shadow rounded-lg h-fit">
        <!-- Year Selector -->
        <div class="mb-6">
            <form method="GET" class="relative">
                <label class="form-label text-sm font-medium text-gray-700 mb-2 block">
                    <i class="fas fa-calendar mr-2"></i>Select Year
                </label>
                <select name="year" onchange="this.form.submit()" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <?php
                    $current_db_year = date('Y');
                    for ($y = $current_db_year; $y >= $current_db_year - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $current_year == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>

        <!-- Search -->
        <div class="mb-6">
            <form method="GET" class="relative">
                <input type="hidden" name="year" value="<?php echo $current_year; ?>">
                <input type="text" name="search" placeholder="Search resources..." 
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
                    <span>Total Resources:</span>
                    <span class="font-semibold"><?php echo $stats['total']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-green-600">Free:</span>
                    <span class="font-semibold text-green-600"><?php echo $stats['free_count']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-orange-600">Premium:</span>
                    <span class="font-semibold text-orange-600"><?php echo $stats['premium_count']; ?></span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="space-y-4">
            <div>
                <h4 class="font-semibold mb-3">
                    <i class="fas fa-filter mr-2"></i>Filter by Class
                </h4>
                <ul class="space-y-1">
                    <?php 
                    $classes = ['all' => 'All Classes', 'Baby' => 'Baby', 'Middle' => 'Middle', 'Top' => 'Top', 
                               'P1' => 'Primary 1', 'P2' => 'Primary 2', 'P3' => 'Primary 3', 'P4' => 'Primary 4', 
                               'P5' => 'Primary 5', 'P6' => 'Primary 6', 'P7' => 'Primary 7'];
                    foreach ($classes as $value => $label): 
                    ?>
                    <li>
                        <a href="?year=<?php echo $current_year; ?>&class=<?php echo $value; ?>&filter=<?php echo $filter; ?>&category=<?php echo $category_filter; ?>&search=<?php echo urlencode($search); ?>" 
                           class="block p-2 rounded hover:bg-blue-50 transition-colors <?php echo $class_filter === $value ? 'bg-blue-100 text-blue-600 font-medium' : 'text-gray-700'; ?>">
                            <?php echo $label; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <h4 class="font-semibold mb-3">
                    <i class="fas fa-tags mr-2"></i>Filter by Category
                </h4>
                <ul class="space-y-1">
                    <?php 
                    $categories = ['all' => 'All Categories', 'Notes' => 'Study Notes', 'Exam' => 'Exam Papers', 'Other' => 'Other Materials'];
                    foreach ($categories as $value => $label): 
                    ?>
                    <li>
                        <a href="?year=<?php echo $current_year; ?>&category=<?php echo $value; ?>&filter=<?php echo $filter; ?>&class=<?php echo $class_filter; ?>&search=<?php echo urlencode($search); ?>" 
                           class="block p-2 rounded hover:bg-blue-50 transition-colors <?php echo $category_filter === $value ? 'bg-blue-100 text-blue-600 font-medium' : 'text-gray-700'; ?>">
                            <?php echo $label; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <h4 class="font-semibold mb-3">
                    <i class="fas fa-dollar-sign mr-2"></i>Filter by Type
                </h4>
                <ul class="space-y-1">
                    <?php 
                    $types = ['all' => 'All Types', 'free' => 'Free Resources', 'premium' => 'Premium Resources'];
                    foreach ($types as $value => $label): 
                    ?>
                    <li>
                        <a href="?year=<?php echo $current_year; ?>&filter=<?php echo $value; ?>&class=<?php echo $class_filter; ?>&category=<?php echo $category_filter; ?>&search=<?php echo urlencode($search); ?>" 
                           class="block p-2 rounded hover:bg-blue-50 transition-colors <?php echo $filter === $value ? 'bg-blue-100 text-blue-600 font-medium' : 'text-gray-700'; ?>">
                            <?php echo $label; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Quick Actions for Schools -->
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'School'): ?>
        <div class="mt-6 pt-4 border-t">
            <a href="../admin/resources/create.php" class="w-full btn bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center">
                <i class="fas fa-plus mr-2"></i>Upload Resource
            </a>
            <p class="text-xs text-gray-500 mt-2 text-center">School uploads require approval</p>
        </div>
        <?php endif; ?>
    </aside>

    <!-- Main Content -->
    <main class="flex-1">
        <!-- Header Controls -->
        <div class="bg-white p-4 shadow rounded-lg mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold">
                        Resources for <?php echo $current_year; ?>
                        <?php if (!empty($search)): ?>
                            <small class="text-gray-500">- Search: "<?php echo htmlspecialchars($search); ?>"</small>
                        <?php endif; ?>
                    </h2>
                    <p class="text-gray-600 text-sm">Showing <?php echo count($resources); ?> of <?php echo $total_resources; ?> resources</p>
                </div>
                
                <div class="flex gap-2">
                    <form method="GET" class="flex gap-2">
                        <input type="hidden" name="year" value="<?php echo $current_year; ?>">
                        <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                        <input type="hidden" name="class" value="<?php echo $class_filter; ?>">
                        <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                        <input type="hidden" name="search" value="<?php echo $search; ?>">
                        
                        <select name="sort" onchange="this.form.submit()" class="p-2 border border-gray-300 rounded text-sm">
                            <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <!-- Resources List -->
        <?php if (empty($resources)): ?>
        <div class="bg-white p-8 shadow rounded-lg text-center">
            <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-600 mb-2">No Resources Found</h3>
            <p class="text-gray-500">
                <?php if (!empty($search)): ?>
                    No resources match your search criteria. Try adjusting your filters or search terms.
                <?php else: ?>
                    No resources are available for the selected filters.
                <?php endif; ?>
            </p>
            <?php if (!empty($search) || $filter !== 'all' || $class_filter !== 'all' || $category_filter !== 'all'): ?>
            <a href="?year=<?php echo $current_year; ?>" class="inline-block mt-4 text-blue-600 hover:text-blue-800">
                <i class="fas fa-refresh mr-1"></i>Clear all filters
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($resources as $resource): ?>
            <div class="bg-white p-6 shadow rounded-lg hover:shadow-md transition-shadow resource-item">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <!-- Resource Header -->
                        <div class="flex items-center gap-3 mb-3">
                            <i class="fas fa-file-alt text-2xl text-blue-600"></i>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-1">
                                    <?php echo htmlspecialchars($resource['title']); ?>
                                </h3>
                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                    <span><i class="fas fa-calendar mr-1"></i><?php echo date('M d, Y', strtotime($resource['created_at'])); ?></span>
                                    <span><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($resource['uploader_name']); ?></span>
                                    <span><i class="fas fa-download mr-1"></i><?php echo $resource['total_downloads']; ?> downloads</span>
                                </div>
                            </div>
                        </div>

                        <!-- Resource Meta -->
                        <div class="flex flex-wrap gap-2 mb-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars($resource['class']); ?>
                            </span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($resource['category']); ?>
                            </span>
                            <?php if ($resource['type'] === 'free'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <i class="fas fa-gift mr-1"></i>Free
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                <i class="fas fa-crown mr-1"></i>Premium - UGX <?php echo number_format($resource['amount'], 0); ?>
                            </span>
                            <?php if ($resource['has_access'] && $resource['max_downloads'] > 0): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                <i class="fas fa-download mr-1"></i><?php echo $resource['download_count']; ?>/<?php echo $resource['max_downloads']; ?> downloads used
                            </span>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col gap-2 ml-4">
                        <?php if ($resource['type'] === 'free' || $resource['has_access']): ?>
                            <?php if ($resource['type'] === 'premium' && $resource['max_downloads'] > 0 && $resource['download_count'] >= $resource['max_downloads']): ?>
                                <span class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-red-100 text-red-800">
                                    <i class="fas fa-ban mr-2"></i>Download Limit Reached
                                </span>
                            <?php else: ?>
                                <a href="download.php?resource_id=<?php echo $resource['id']; ?>" 
                                   onclick="trackDownload(<?php echo $resource['id']; ?>, '<?php echo addslashes($resource['title']); ?>')"
                                   class="btn bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                                    <i class="fas fa-download mr-2"></i>Download
                                </a>
                            <?php endif; ?>
                        <?php elseif ($resource['type'] === 'premium'): ?>
                            <?php if ($resource['payment_status'] === 'pending'): ?>
                                <span class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-yellow-100 text-yellow-800">
                                    <i class="fas fa-clock mr-2"></i>Payment Pending
                                </span>
                            <?php else: ?>
                                <a href="../admin/resources/payment.php?resource_id=<?php echo $resource['id']; ?>" 
                                   class="btn bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition-colors flex items-center">
                                    <i class="fas fa-credit-card mr-2"></i>Pay & Download
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <button onclick="showResourceDetails(<?php echo $resource['id']; ?>)" 
                                class="btn bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>Details
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-8 flex justify-center">
            <nav class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                <a href="?year=<?php echo $current_year; ?>&page=<?php echo $page-1; ?>&sort=<?php echo $sort; ?>&filter=<?php echo $filter; ?>&class=<?php echo $class_filter; ?>&category=<?php echo $category_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="px-3 py-2 text-sm border border-gray-300 rounded-l-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?year=<?php echo $current_year; ?>&page=<?php echo $i; ?>&sort=<?php echo $sort; ?>&filter=<?php echo $filter; ?>&class=<?php echo $class_filter; ?>&category=<?php echo $category_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="px-3 py-2 text-sm border border-gray-300 hover:bg-gray-50 transition-colors <?php echo $i === $page ? 'bg-blue-600 text-white' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?year=<?php echo $current_year; ?>&page=<?php echo $page+1; ?>&sort=<?php echo $sort; ?>&filter=<?php echo $filter; ?>&class=<?php echo $class_filter; ?>&category=<?php echo $category_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="px-3 py-2 text-sm border border-gray-300 rounded-r-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<!-- Resource Details Modal -->
<div id="resourceModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-xl font-semibold">Resource Details</h3>
                    <button onclick="closeResourceModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="resourceModalContent">
                    <!-- Content loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showResourceDetails(resourceId) {
    document.getElementById('resourceModal').classList.remove('hidden');
    document.getElementById('resourceModalContent').innerHTML = '<div class="text-center p-4"><i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i><p class="mt-2 text-gray-600">Loading...</p></div>';
    
    // Fetch resource details via AJAX
    fetch(`api/resource_details.php?id=${resourceId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const resource = data.resource;
                const hasAccess = data.has_access;
                const paymentStatus = data.payment_status;
                
                let actionButton = '';
                let accessInfo = '';
                
                if (resource.type === 'free' || hasAccess) {
                    if (resource.type === 'premium' && resource.max_downloads > 0 && resource.download_count >= resource.max_downloads) {
                        actionButton = `<span class="inline-flex items-center px-6 py-2 rounded-lg text-sm font-medium bg-red-100 text-red-800">
                            <i class="fas fa-ban mr-2"></i>Download Limit Reached
                        </span>`;
                    } else {
                        actionButton = `<a href="download.php?resource_id=${resource.id}" onclick="trackDownload(${resource.id}, '${resource.title.replace(/'/g, "\\'")}')" class="btn bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Download Now
                        </a>`;
                    }
                } else if (resource.type === 'premium') {
                    if (paymentStatus === 'pending') {
                        actionButton = `<span class="inline-flex items-center px-6 py-2 rounded-lg text-sm font-medium bg-yellow-100 text-yellow-800">
                            <i class="fas fa-clock mr-2"></i>Payment Verification Pending
                        </span>`;
                    } else {
                        actionButton = `<a href="payment.php?resource_id=${resource.id}" class="btn bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 transition-colors">
                            <i class="fas fa-credit-card mr-2"></i>Pay UGX ${parseInt(resource.amount).toLocaleString()} & Download
                        </a>`;
                    }
                }
                
                if (resource.type === 'premium' && hasAccess && resource.max_downloads > 0) {
                    accessInfo = `
                        <div class="bg-purple-50 p-3 rounded-lg mb-4">
                            <h6 class="font-medium text-purple-800 mb-1">Download Usage</h6>
                            <div class="flex items-center justify-between text-sm">
                                <span>Downloads used: ${resource.download_count}/${resource.max_downloads}</span>
                                <div class="w-32 bg-purple-200 rounded-full h-2">
                                    <div class="bg-purple-600 h-2 rounded-full" style="width: ${(resource.download_count / resource.max_downloads) * 100}%"></div>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                document.getElementById('resourceModalContent').innerHTML = `
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-semibold text-lg mb-2">${resource.title}</h4>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                                    <i class="fas fa-graduation-cap mr-1"></i>${resource.class}
                                </span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-800">
                                    <i class="fas fa-tag mr-1"></i>${resource.category}
                                </span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm ${resource.type === 'free' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'}">
                                    <i class="fas fa-${resource.type === 'free' ? 'gift' : 'crown'} mr-1"></i>${resource.type === 'free' ? 'Free' : 'Premium'}
                                </span>
                            </div>
                        </div>
                        
                        ${accessInfo}
                        
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <label class="font-medium text-gray-700">Uploaded by:</label>
                                <p class="text-gray-600">${resource.uploader_name}</p>
                            </div>
                            <div>
                                <label class="font-medium text-gray-700">Upload Date:</label>
                                <p class="text-gray-600">${new Date(resource.created_at).toLocaleDateString()}</p>
                            </div>
                            <div>
                                <label class="font-medium text-gray-700">File Size:</label>
                                <p class="text-gray-600">${resource.file_size || 'Unknown'}</p>
                            </div>
                            <div>
                                <label class="font-medium text-gray-700">Total Downloads:</label>
                                <p class="text-gray-600">${resource.download_count || 0}</p>
                            </div>
                        </div>
                        
                        <div class="pt-4 border-t">
                            <div class="flex justify-between items-center">
                                <button onclick="closeResourceModal()" class="btn bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors">
                                    Close
                                </button>
                                ${actionButton}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('resourceModalContent').innerHTML = `
                    <div class="text-center p-4">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-600 mb-2"></i>
                        <p>Failed to load resource details: ${data.error || 'Unknown error'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('resourceModalContent').innerHTML = `
                <div class="text-center p-4">
                    <i class="fas fa-exclamation-triangle text-2xl text-red-600 mb-2"></i>
                    <p>Error loading resource details. Please try again.</p>
                </div>
            `;
        });
}

function closeResourceModal() {
    document.getElementById('resourceModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('resourceModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeResourceModal();
    }
});

// Close modal with escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeResourceModal();
    }
});

// Track download clicks
function trackDownload(resourceId, resourceTitle) {
    // Log download attempt
    fetch('api/track_download.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            resource_id: resourceId,
            action: 'download_click'
        })
    }).catch(error => console.log('Download tracking failed:', error));
}

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert && !alert.classList.contains('show')) return;
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) closeBtn.click();
        }, 5000);
    });
});
</script>

<style>
.resource-item {
    transition: all 0.2s ease;
}

.resource-item:hover {
    transform: translateY(-1px);
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

.alert {
    border-left: 4px solid;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.alert-danger {
    background-color: #fef2f2;
    border-left-color: #ef4444;
    color: #b91c1c;
}

.alert-success {
    background-color: #f0fdf4;
    border-left-color: #22c55e;
    color: #166534;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.2em;
    cursor: pointer;
    padding: 0;
    margin-left: auto;
    opacity: 0.5;
}

.btn-close:hover {
    opacity: 1;
}

@media (max-width: 768px) {
    .flex {
        flex-direction: column;
    }
    
    .resource-item .flex {
        flex-direction: column;
        gap: 1rem;
    }
    
    .resource-item .ml-4 {
        margin-left: 0;
        margin-top: 1rem;
    }
    
    .resource-item .flex-col {
        flex-direction: row;
        flex-wrap: wrap;
    }
}

/* Loading animation */
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