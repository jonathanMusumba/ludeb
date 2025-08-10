<?php
require_once '../db_connect.php';

// Ensure session is started and CSRF token is available
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Debug session
error_log("manage_schools.php: User ID: " . ($_SESSION['user_id'] ?? 'Not set') . ", Role: " . ($_SESSION['role'] ?? 'Not set'), 3, 'C:\xampp\htdocs\ludeb\debug.log');

// Restrict to System Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'System Admin') {
    error_log("Unauthorized access attempt, User ID: " . ($_SESSION['user_id'] ?? 'Not set'), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    header("Location: $root_url" . "login.php");
    exit;
}

// Use global CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

class SchoolManager {
    private $conn;
    private $user_id;
    private $csrf_token;
    
    public function __construct($connection, $user_id, $csrf_token) {
        $this->conn = $connection;
        $this->user_id = $user_id;
        $this->csrf_token = $csrf_token;
    }
    
    /**
     * Validate and sanitize pagination parameters
     */
    public function getPaginationParams() {
        $limit_options = [25, 50, 100, 200, 'all'];
        $limit = isset($_GET['limit']) && in_array($_GET['limit'], $limit_options) ? $_GET['limit'] : 50;
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $subcounty_filter = isset($_GET['subcounty']) && is_numeric($_GET['subcounty']) ? (int)$_GET['subcounty'] : 0;
        $type_filter = isset($_GET['type']) && is_numeric($_GET['type']) ? (int)$_GET['type'] : 0;
        $sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], ['center_no', 'school_name', 'subcounty', 'type', 'candidate_count', 'results_count', 'results_status']) ? $_GET['sort_by'] : 'center_no';
        $sort_order = isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['ASC', 'DESC']) ? $_GET['sort_order'] : 'ASC';
        
        return compact('limit_options', 'limit', 'page', 'search', 'subcounty_filter', 'type_filter', 'sort_by', 'sort_order');
    }
    
    /**
     * Build WHERE clause for filtering
     */
    private function buildWhereClause($search, $subcounty_filter, $type_filter, &$params, &$types) {
        $where_conditions = [];
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $where_conditions[] = "(s.school_name LIKE ? OR s.center_no LIKE ? OR sc.subcounty LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
            $types .= 'sss';
        }
        
        if ($subcounty_filter > 0) {
            $where_conditions[] = "s.subcounty_id = ?";
            $params[] = $subcounty_filter;
            $types .= 'i';
        }
        
        if ($type_filter > 0) {
            $where_conditions[] = "s.school_type_id = ?";
            $params[] = $type_filter;
            $types .= 'i';
        }
        
        return empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    /**
     * Get total count of schools with filters
     */
    private function getTotalSchools($search, $subcounty_filter, $type_filter) {
        $params = [];
        $types = '';
        $where_clause = $this->buildWhereClause($search, $subcounty_filter, $type_filter, $params, $types);
        
        $sql = "SELECT COUNT(*) as total FROM schools s
                JOIN subcounties sc ON s.subcounty_id = sc.id
                JOIN school_types st ON s.school_type_id = st.id
                {$where_clause}";
        
        if (empty($params)) {
            $stmt = $this->conn->query($sql);
            return $stmt->fetch_assoc()['total'];
        } else {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc()['total'];
        }
    }
    
    /**
     * Fetch schools with pagination and filters
     */
    public function getSchools($limit, $page, $search, $subcounty_filter, $type_filter, $sort_by, $sort_order) {
        $params = [];
        $types = '';
        $where_clause = $this->buildWhereClause($search, $subcounty_filter, $type_filter, $params, $types);
        
        $total_schools = $this->getTotalSchools($search, $subcounty_filter, $type_filter);
        
        // Calculate pagination
        if ($limit === 'all') {
            $limit_value = $total_schools;
            $total_pages = 1;
            $offset = 0;
        } else {
            $limit_value = (int)$limit;
            $total_pages = $total_schools > 0 ? ceil($total_schools / $limit_value) : 1;
            $page = min($page, $total_pages);
            $offset = ($page - 1) * $limit_value;
        }
        
        // Build order by clause
        $order_by = match($sort_by) {
            'center_no' => 's.center_no',
            'school_name' => 's.school_name',
            'subcounty' => 'sc.subcounty',
            'type' => 'st.type',
            'candidate_count' => 'candidate_count',
            'results_count' => 'results_count',
            'results_status' => 's.results_status',
            default => 's.center_no'
        };
        $order_by .= " {$sort_order}";
        
        // Base query
        $sql = "SELECT s.id, s.center_no, s.school_name, s.results_status, 
                       sc.subcounty, st.type, s.created_at,
                       (SELECT COUNT(*) FROM candidates c WHERE c.school_id = s.id) as candidate_count,
                       (SELECT COUNT(*) FROM results r JOIN candidates c ON r.candidate_id = c.id WHERE c.school_id = s.id) as results_count
                FROM schools s
                JOIN subcounties sc ON s.subcounty_id = sc.id
                JOIN school_types st ON s.school_type_id = st.id
                {$where_clause}
                ORDER BY {$order_by}";
        
        // Add pagination if not 'all'
        if ($limit !== 'all') {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit_value;
            $params[] = $offset;
            $types .= 'ii';
        }
        
        // Execute query
        if (empty($params)) {
            $stmt = $this->conn->query($sql);
            $schools = $stmt->fetch_all(MYSQLI_ASSOC);
        } else {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $schools = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        
        return [
            'schools' => $schools,
            'total_schools' => $total_schools,
            'total_pages' => $total_pages,
            'current_page' => $page
        ];
    }
    
    /**
     * Get subcounties for filter dropdown
     */
    public function getSubcounties() {
        $stmt = $this->conn->query("SELECT id, subcounty FROM subcounties ORDER BY subcounty");
        return $stmt->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get school types for filter dropdown
     */
    public function getSchoolTypes() {
        $stmt = $this->conn->query("SELECT id, type FROM school_types ORDER BY type");
        return $stmt->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Log user action
     */
    public function logAction($action, $details = null) {
        $stmt = $this->conn->prepare("CALL log_action(?, ?, ?)");
        $details = $details ?? 'System Admin accessed manage schools page';
        $stmt->bind_param("sis", $action, $this->user_id, $details);
        $stmt->execute();
    }
}

// Initialize manager
$user_id = $_SESSION['user_id'];
$school_manager = new SchoolManager($conn, $user_id, $csrf_token);

// Log dashboard access
$school_manager->logAction('Manage Schools Access');

// Get pagination and filter parameters
$params = $school_manager->getPaginationParams();
extract($params);

// Fetch data
$data = $school_manager->getSchools($limit, $page, $search, $subcounty_filter, $type_filter, $sort_by, $sort_order);
$schools = $data['schools'];
$total_schools = $data['total_schools'];
$total_pages = $data['total_pages'];
$current_page = $data['current_page'];

// Get filter options
$subcounties = $school_manager->getSubcounties();
$school_types = $school_manager->getSchoolTypes();

// Set page title
$page_title = "Manage Schools";

// Define content
ob_start();
?>
<div class="page-header">
    <h1 class="page-title">Manage Schools
        <small class="text-muted ms-2">(<?php echo number_format($total_schools); ?> total)</small>
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>Dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Manage Schools</li>
        </ol>
    </nav>
</div>

<!-- Quick Actions Bar -->
<div class="dashboard-card mb-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <a href="<?php echo $base_url; ?>schools/add_school.php" class="btn btn-enhanced">Add School</a>
                <button type="button" class="btn btn-enhanced" data-toggle="modal" data-target="#importModal" style="background: linear-gradient(135deg, #3b82f6, #6366f1);">
                    Import Schools
                </button>
                <a href="<?php echo $base_url; ?>schools/export_schools.php" class="btn btn-enhanced" style="background: linear-gradient(135deg, #10b981, #059669);">
                    Export
                </a>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary"><?php echo number_format(array_sum(array_column($schools, 'candidate_count'))); ?> Total Candidates</span>
                <span class="badge bg-success"><?php echo number_format(array_sum(array_column($schools, 'results_count'))); ?> Results Entered</span>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="dashboard-card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3" id="filterForm">
            <div class="col-md-4">
                <label for="search" class="form-label">Search Schools</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="School name, center number, or subcounty...">
                    <button type="button" class="btn btn-outline-secondary" id="clearSearch">Clear</button>
                </div>
            </div>
            <div class="col-md-2">
                <label for="subcounty" class="form-label">Subcounty</label>
                <select class="form-select" id="subcounty" name="subcounty">
                    <option value="0">All Subcounties</option>
                    <?php foreach ($subcounties as $subcounty): ?>
                        <option value="<?php echo $subcounty['id']; ?>" 
                                <?php echo $subcounty_filter == $subcounty['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subcounty['subcounty']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="type" class="form-label">School Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="0">All Types</option>
                    <?php foreach ($school_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>" 
                                <?php echo $type_filter == $type['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['type']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="limit" class="form-label">Show</label>
                <select class="form-select" id="limit" name="limit">
                    <?php foreach ($limit_options as $option): ?>
                        <option value="<?php echo $option; ?>" <?php echo $limit === $option ? 'selected' : ''; ?>>
                            <?php echo $option === 'all' ? 'All' : $option; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-enhanced">Filter</button>
                <a href="<?php echo $base_url; ?>schools/manage_schools.php" class="btn btn-outline-secondary" title="Clear Filters">Reset</a>
            </div>
            <input type="hidden" name="sort_by" id="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
            <input type="hidden" name="sort_order" id="sort_order" value="<?php echo htmlspecialchars($sort_order); ?>">
        </form>
    </div>
</div>

<!-- Main Content -->
<div class="dashboard-card">
    <div class="card-body">
        <!-- Alerts -->
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-enhanced alert-dismissible fade show">
                <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-enhanced alert-dismissible fade show">
                <?php echo htmlspecialchars($_GET['success']); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Schools Table -->
        <div class="table-responsive">
            <table class="table-enhanced" style="text-align: left;">
                <thead>
                    <tr>
                        <th class="sortable" data-column="center_no" data-order="<?php echo $sort_by === 'center_no' ? ($sort_order === 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>">Center No.</th>
                        <th class="sortable" data-column="school_name" data-order="<?php echo $sort_by === 'school_name' ? ($sort_order === 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>">School Name</th>
                        <th class="sortable" data-column="subcounty" data-order="<?php echo $sort_by === 'subcounty' ? ($sort_order === 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>">Subcounty</th>
                        <th class="sortable" data-column="type" data-order="<?php echo $sort_by === 'type' ? ($sort_order === 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>">Type</th>
                        <th class="sortable" data-column="candidate_count" data-order="<?php echo $sort_by === 'candidate_count' ? ($sort_order === 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>">Candidates</th>
                        <th class="sortable" data-column="results_count" data-order="<?php echo $sort_by === 'results_count' ? ($sort_order === 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>">Results</th>
                        <th class="sortable" data-column="results_status" data-order="<?php echo $sort_by === 'results_status' ? ($sort_order === 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schools)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <p class="text-muted mb-0">No schools found matching your criteria</p>
                                <?php if (!empty($search) || $subcounty_filter > 0 || $type_filter > 0): ?>
                                    <a href="<?php echo $base_url; ?>schools/manage_schools.php" class="btn btn-sm btn-outline-primary mt-2">Clear Filters</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($schools as $school): ?>
                            <tr>
                                <td><strong class="text-primary"><?php echo htmlspecialchars($school['center_no']); ?></strong></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($school['school_name']); ?></div>
                                    <?php if (isset($school['created_at'])): ?>
                                        <small class="text-muted">Added: <?php echo date('M j, Y', strtotime($school['created_at'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($school['subcounty']); ?></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($school['type']); ?></span></td>
                                <td><span class="badge bg-primary"><?php echo number_format($school['candidate_count']); ?></span></td>
                                <td><span class="badge bg-success"><?php echo number_format($school['results_count']); ?></span></td>
                                <td>
                                    <?php
                                    $status_class = match($school['results_status']) {
                                        'Complete' => 'bg-success',
                                        'Partial' => 'bg-warning text-dark',
                                        'Pending' => 'bg-secondary',
                                        default => 'bg-light text-dark'
                                    };
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($school['results_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="<?php echo $base_url; ?>schools/view_school.php?center_no=<?php echo urlencode($school['center_no']); ?>" 
                                           class="btn btn-enhanced btn-sm" 
                                           style="background: linear-gradient(135deg, #3b82f6, #6366f1);" 
                                           title="View Details">View</a>
                                        <a href="<?php echo $base_url; ?>schools/edit_school.php?center_no=<?php echo urlencode($school['center_no']); ?>" 
                                           class="btn btn-enhanced btn-sm" 
                                           title="Edit School">Edit</a>
                                        <button class="btn btn-enhanced btn-sm delete-btn" 
                                                data-center-no="<?php echo htmlspecialchars($school['center_no']); ?>" 
                                                data-school-name="<?php echo htmlspecialchars($school['school_name']); ?>"
                                                data-csrf="<?php echo htmlspecialchars($csrf_token); ?>" 
                                                style="background: linear-gradient(135deg, #ef4444, #dc2626);" 
                                                title="Delete School">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Enhanced Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" aria-label="Previous">
                            <span aria-hidden="true">« Previous</span>
                        </a>
                    </li>
                    
                    <?php
                    $range = 2;
                    $start = max(1, $current_page - $range);
                    $end = min($total_pages, $current_page + $range);
                    
                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    
                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" aria-label="Next">
                            <span aria-hidden="true">Next »</span>
                        </a>
                    </li>
                </ul>
                <div class="text-center mt-2">
                    <small class="text-muted">
                        Showing <?php echo number_format(($current_page - 1) * ($limit === 'all' ? $total_schools : (int)$limit) + 1); ?> 
                        to <?php echo number_format(min($current_page * ($limit === 'all' ? $total_schools : (int)$limit), $total_schools)); ?> 
                        of <?php echo number_format($total_schools); ?> schools
                    </small>
                </div>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?php echo $base_url; ?>schools/import_schools.php" method="post" enctype="multipart/form-data" id="importForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Schools</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="mb-3">
                        <label for="excel_file" class="form-label">Choose Excel File</label>
                        <input type="file" class="form-control" id="excel_file" name="excel_file" 
                               accept=".xlsx,.xls" required>
                        <div class="form-text">
                            Supported formats: .xlsx, .xls
                        </div>
                    </div>
                    <div class="alert alert-info alert-enhanced">
                        <strong>Import Tips:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Ensure your Excel file has the correct column headers</li>
                            <li>Duplicate center numbers will be skipped</li>
                            <li>Invalid data will be reported after import</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-enhanced" style="background: linear-gradient(135deg, #3b82f6, #6366f1);">
                        Import Schools
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.table-enhanced {
    width: 100%;
    border-collapse: collapse;
}
.table-enhanced th,
.table-enhanced td {
    padding: 12px;
    border: 1px solid #dee2e6;
    text-align: left;
}
.table-enhanced th.sortable {
    cursor: pointer;
    position: relative;
    padding-right: 24px;
}
.table-enhanced th.sortable:hover {
    background-color: #f8f9fa;
}
.table-enhanced th.sortable::after {
    content: '↕';
    position: absolute;
    right: 8px;
    opacity: 0.3;
}
.table-enhanced th.sortable.asc::after {
    content: '↑';
    opacity: 1;
}
.table-enhanced th.sortable.desc::after {
    content: '↓';
    opacity: 1;
}
</style>

<script>
jQuery.noConflict();
(function($) {
    $(document).ready(function() {
        console.log('Manage Schools page loaded with jQuery version:', $.fn.jquery);

        // Enhanced delete functionality
        $('.delete-btn').click(function() {
            const centerNo = $(this).data('center-no');
            const schoolName = $(this).data('school-name');
            const csrfToken = $(this).data('csrf');
            
            const confirmMessage = `Are you sure you want to delete "${schoolName}" (Center: ${centerNo})?\n\n` +
                                 `⚠️ This action will also remove:\n` +
                                 `• All associated candidates\n` +
                                 `• All marks and results\n` +
                                 `• This action cannot be undone!`;
            
            if (confirm(confirmMessage)) {
                const $button = $(this);
                $button.prop('disabled', true).text('Deleting...');
                
                $.ajax({
                    url: '<?php echo $base_url; ?>schools/delete_school.php',
                    type: 'POST',
                    data: { 
                        center_no: centerNo, 
                        csrf_token: csrfToken 
                    },
                    timeout: 30000,
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                window.showNotification(`School "${schoolName}" has been deleted successfully.`, 'success');
                                setTimeout(() => location.reload(), 2000);
                            } else {
                                window.showNotification(result.error || 'Failed to delete school.', 'error');
                                $button.prop('disabled', false).text('Delete');
                            }
                        } catch (e) {
                            console.error('JSON Parse Error:', e, response);
                            window.showNotification('Invalid response from server.', 'error');
                            $button.prop('disabled', false).text('Delete');
                            error_log('JSON Parse Error in delete school: ' + e.message, 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        window.showNotification(`Failed to delete school: ${error}`, 'error');
                        $button.prop('disabled', false).text('Delete');
                        error_log('AJAX Error in delete school: ' + status + ' - ' + error, 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
                    }
                });
            }
        });

        // Responsive search with debounce
        let searchTimeout;
        $('#search').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                $('#filterForm').submit();
            }, 300);
        });

        // Clear search button
        $('#clearSearch').click(function() {
            $('#search').val('');
            $('#filterForm').submit();
        });

        // Auto-submit form on filter changes
        $('#subcounty, #type, #limit').on('change', function() {
            $('#filterForm').submit();
        });

        // Column sorting
        $('.sortable').click(function() {
            const column = $(this).data('column');
            const order = $(this).data('order');
            
            $('#sort_by').val(column);
            $('#sort_order').val(order);
            $('#filterForm').submit();
        });

        // Add sort indicators
        $('.sortable').each(function() {
            if ($(this).data('column') === '<?php echo $sort_by; ?>') {
                $(this).addClass('<?php echo strtolower($sort_order); ?>');
            }
        });

        // Enhanced import form validation
        $('#importForm').on('submit', function(e) {
            const fileInput = $('#excel_file')[0];
            if (!fileInput.files.length) {
                e.preventDefault();
                window.showNotification('Please select a file to import.', 'error');
                return false;
            }
            
            const file = fileInput.files[0];
            const maxSize = 10 * 1024 * 1024; // 10MB
            
            if (file.size > maxSize) {
                e.preventDefault();
                window.showNotification('File size must be less than 10MB.', 'error');
                return false;
            }
            
            // Show loading state
            const $submitBtn = $(this).find('button[type="submit"]');
            $submitBtn.prop('disabled', true).text('Importing...');
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert.alert-dismissible').fadeOut('slow');
        }, 5000);

        // Keyboard shortcuts
        $(document).keydown(function(e) {
            // Ctrl/Cmd + F to focus search
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 70) {
                e.preventDefault();
                $('#search').focus();
            }
            // Escape to clear search
            if (e.keyCode === 27 && $('#search').is(':focus')) {
                $('#search').val('').trigger('change');
            }
        });
    });
})(jQuery);
</script>

<?php
$content = ob_get_clean();

// Include layout
require_once '../layout.php';
?>