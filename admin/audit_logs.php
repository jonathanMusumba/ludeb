<?php
ob_start(); // Start output buffering for content
$page_title = 'Audit Logs'; // Set page title for layout.php

session_start();
require_once 'db_connect.php';

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    header("Location: ../../login.php");
    exit();
}

// Log page access
$user_id = $_SESSION['user_id'];
$conn->query("CALL log_action('Audit Logs Access', $user_id, 'System Admin accessed audit logs page')");

// Fetch board name and exam year (already handled in layout.php, but keep for consistency)
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');

$username = htmlspecialchars($_SESSION['username']);
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Handle filters and pagination
$limit = 10; // Logs per page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$action_filter = isset($_GET['action']) ? trim($_GET['action']) : '';
$user_filter = isset($_GET['user']) ? trim($_GET['user']) : '';

$where_clauses = [];
$params = [];
$types = '';

if ($action_filter) {
    $where_clauses[] = "al.action = ?";
    $params[] = $action_filter;
    $types .= 's';
}
if ($user_filter) {
    $where_clauses[] = "u.username = ?";
    $params[] = $user_filter;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Fetch audit logs
$query = "SELECT al.id, al.action, u.username, al.details, al.created_at 
          FROM audit_logs al
          JOIN system_users u ON al.user_id = u.id 
          $where_sql 
          ORDER BY al.created_at DESC 
          LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$types .= 'ii';
$params[] = $limit;
$params[] = $offset;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);

// Count total logs for pagination
$count_query = "SELECT COUNT(*) as total 
                FROM audit_logs al 
                JOIN system_users u ON al.user_id = u.id 
                $where_sql";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    array_pop($params); // Remove limit
    array_pop($params); // Remove offset
    if (!empty($params)) {
        $stmt->bind_param(substr($types, 0, -2), ...$params);
    }
}
$stmt->execute();
$result = $stmt->get_result();
$total_logs = $result->fetch_assoc()['total'];
$total_pages = ceil($total_logs / $limit);

// Fetch distinct actions and users for filter dropdowns
$actions = $conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetch_all(MYSQLI_ASSOC);
$users = $conn->query("SELECT DISTINCT u.username FROM audit_logs al JOIN system_users u ON al.user_id = u.id ORDER BY u.username")->fetch_all(MYSQLI_ASSOC);

// Capture the content for layout.php
ob_start();
?>

<div class="page-header">
    <h1 class="page-title">Audit Logs</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Audit Logs</li>
        </ol>
    </nav>
</div>

<div class="dashboard-card">
    <div class="card-body">
        <form method="get" class="filter-container mb-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label for="action">Filter by Action</label>
                    <select class="form-control" id="action" name="action">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?php echo htmlspecialchars($action['action']); ?>" <?php echo $action['action'] === $action_filter ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($action['action']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label for="user">Filter by User</label>
                    <select class="form-control" id="user" name="user">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['username']); ?>" <?php echo $user['username'] === $user_filter ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end mb-2">
                    <button type="submit" class="btn-enhanced btn-sm mr-2">Apply Filters</button>
                    <a href="audit_logs.php" class="btn-enhanced btn-sm">Clear Filters</a>
                </div>
            </div>
        </form>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>

        <table class="table-enhanced">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Action</th>
                    <th>User</th>
                    <th>Details</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5">No audit logs found</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['id']); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                            <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&action=<?php echo urlencode($action_filter); ?>&user=<?php echo urlencode($user_filter); ?>" aria-label="Previous">
                        <span aria-hidden="true">«</span>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&action=<?php echo urlencode($action_filter); ?>&user=<?php echo urlencode($user_filter); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&action=<?php echo urlencode($action_filter); ?>&user=<?php echo urlencode($user_filter); ?>" aria-label="Next">
                        <span aria-hidden="true">»</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<?php
$content = ob_get_clean(); // Capture the content
require_once 'layout.php'; // Include the layout
?>