<?php
ob_start();
require_once '../db_connect.php';

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Restrict to authorized users
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['System Admin', 'Examination Administrator'])) {
    error_log("Unauthorized access attempt, User ID: " . ($_SESSION['user_id'] ?? 'Not set'), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header("Location: $root_url" . "login.php");
    exit;
}

$page_title = 'Manage Announcements';

// Handle delete action
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $delete_id = (int)$_POST['delete_id'];
    $uploader_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = 'Announcement deleted successfully.';
        $message_type = 'success';
        $conn->query("CALL log_action('Announcement Deleted', $uploader_id, 'Deleted announcement ID: $delete_id')");
    } else {
        $message = 'Failed to delete announcement.';
        $message_type = 'error';
    }
    $stmt->close();
}

// Filter and pagination
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$category_filter = isset($_GET['category']) && in_array($_GET['category'], ['all', 'system', 'maintenance', 'policy', 'event', 'general']) ? $_GET['category'] : 'all';
$priority_filter = isset($_GET['priority']) && in_array($_GET['priority'], ['all', 'high', 'medium', 'low']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fetch announcements
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
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-bullhorn"></i> Manage Announcements</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Manage Announcements</li>
        </ol>
    </nav>
</div>

<?php if (!empty($message)): ?>
<div class="alert-enhanced alert-<?php echo $message_type === 'success' ? 'success' : 'warning'; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="dashboard-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="card-title"><i class="fas fa-list"></i> Announcements List</h5>
            <a href="create.php" class="btn-enhanced">
                <i class="fas fa-plus"></i> Create New Announcement
            </a>
        </div>

        <!-- Filters -->
        <div class="filter-container mb-4">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-control" id="year" name="year">
                        <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php echo $selectedYear == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-control" id="category" name="category">
                        <option value="all">All Categories</option>
                        <?php 
                        $categories = [
                            'system' => 'System Updates',
                            'maintenance' => 'Maintenance',
                            'policy' => 'Policy Changes',
                            'event' => 'Events',
                            'general' => 'General'
                        ];
                        foreach ($categories as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $category_filter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="priority" class="form-label">Priority</label>
                    <select class="form-control" id="priority" name="priority">
                        <option value="all">All Priorities</option>
                        <?php 
                        $priorities = [
                            'high' => 'High Priority',
                            'medium' => 'Medium Priority',
                            'low' => 'Low Priority'
                        ];
                        foreach ($priorities as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $priority_filter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search announcements...">
                </div>
                <div class="col-12 mt-3">
                    <button type="submit" class="btn-enhanced"><i class="fas fa-filter"></i> Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Announcements Table -->
        <?php if (empty($announcements)): ?>
        <div class="text-center py-8">
            <i class="fas fa-bullhorn text-4xl text-gray-300 mb-3"></i>
            <p class="text-gray-500">No announcements found for the selected filters.</p>
            <a href="?year=<?php echo $selectedYear; ?>&category=all&priority=all" class="text-blue-600 hover:text-blue-800 text-sm">
                Clear filters
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table-enhanced">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Content</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Uploader</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $announcement): ?>
                    <tr>
                        <td><?php echo $announcement['id']; ?></td>
                        <td><?php echo htmlspecialchars(substr($announcement['content'], 0, 50)) . (strlen($announcement['content']) > 50 ? '...' : ''); ?></td>
                        <td>
                            <span class="badge bg-blue-100 text-blue-800"><?php echo htmlspecialchars($announcement['category']); ?></span>
                        </td>
                        <td>
                            <span class="badge <?php echo $announcement['priority'] === 'high' ? 'bg-red-100 text-red-800' : ($announcement['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                <?php echo htmlspecialchars($announcement['priority']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($announcement['uploader_name'] ?? 'Unknown'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></td>
                        <td>
                            <a href="edit.php?id=<?php echo $announcement['id']; ?>" class="btn-enhanced btn-sm" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="delete_id" value="<?php echo $announcement['id']; ?>">
                                <button type="submit" class="btn-enhanced btn-sm btn-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-center align-items-center gap-2 mt-4">
            <?php if ($page > 1): ?>
            <a href="?year=<?php echo $selectedYear; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>" 
               class="btn-enhanced btn-sm">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?year=<?php echo $selectedYear; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
               class="btn btn-sm <?php echo $i === $page ? 'btn-enhanced' : 'btn-secondary'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?year=<?php echo $selectedYear; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>" 
               class="btn-enhanced btn-sm">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Clear filters
    const clearFiltersLink = document.querySelector('a[href*="category=all"]');
    if (clearFiltersLink) {
        clearFiltersLink.addEventListener('click', function() {
            console.log('Clear filters clicked');
        });
    }
});
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>