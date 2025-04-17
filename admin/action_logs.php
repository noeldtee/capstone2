<?php

$page_title = "Action Logs";
require 'includes/header.php';

// Handle bulk delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    global $conn;
    if (!empty($_POST['log_ids'])) {
        $log_ids = array_map('intval', $_POST['log_ids']); // Sanitize IDs
        $placeholders = implode(',', array_fill(0, count($log_ids), '?'));
        $query = "DELETE FROM action_logs WHERE id IN ($placeholders)";
        
        // Prepare the statement
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            $_SESSION['message'] = "Failed to prepare delete statement: " . $conn->error;
            $_SESSION['message_type'] = "danger";
        } else {
            // Bind parameters
            $stmt->bind_param(str_repeat('i', count($log_ids)), ...$log_ids);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Selected action logs deleted successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Failed to delete action logs: " . $stmt->error;
                $_SESSION['message_type'] = "danger";
            }
            $stmt->close();
        }
    } else {
        $_SESSION['message'] = "No logs selected for deletion.";
        $_SESSION['message_type'] = "warning";
    }
    // No redirect; continue rendering the page
}

// Pagination, search, filter, and sort settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$action_type_filter = isset($_GET['action_type']) ? trim($_GET['action_type']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Sorting parameters
$sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'created_at';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_order']) : 'DESC';

// Build the WHERE clause for filtering
$where_clauses = [];
$params = [];
$param_types = '';

if ($search) {
    $where_clauses[] = "(al.action_type LIKE ? OR al.target LIKE ? OR al.details LIKE ? OR CONCAT(u.firstname, ' ', u.lastname) LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssss";
}

if ($action_type_filter) {
    $where_clauses[] = "al.action_type = ?";
    $params[] = $action_type_filter;
    $param_types .= "s";
}

if ($date_from) {
    $where_clauses[] = "al.created_at >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if ($date_to) {
    $where_clauses[] = "al.created_at <= ?";
    $params[] = $date_to . " 23:59:59";
    $param_types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch total number of logs
$query = "SELECT COUNT(*) as total FROM action_logs al JOIN users u ON al.performed_by = u.id $where_sql";
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Failed to prepare count query: " . $conn->error);
}
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_logs = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = $total_logs > 0 ? ceil($total_logs / $limit) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// Fetch action logs with admin names
$query = "SELECT al.id, al.action_type, al.performed_by, al.target, al.details, al.created_at, 
                 CONCAT(u.firstname, ' ', u.lastname) AS admin_name 
          FROM action_logs al 
          JOIN users u ON al.performed_by = u.id 
          $where_sql 
          ORDER BY al.$sort_by $sort_order 
          LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Failed to prepare fetch query: " . $conn->error);
}
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$action_logs = [];
while ($row = $result->fetch_assoc()) {
    $action_logs[] = $row;
}
$stmt->close();

// Fetch distinct action types for the filter dropdown
$action_types = [];
$stmt = $conn->prepare("SELECT DISTINCT action_type FROM action_logs ORDER BY action_type");
if ($stmt === false) {
    die("Failed to prepare action types query: " . $conn->error);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $action_types[] = $row['action_type'];
}
$stmt->close();
?>

<link rel="stylesheet" href="../assets/css/admin_dashboard.css">
<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x" style="z-index: 1050; margin-top: 20px;" role="alert">
        <?php echo htmlspecialchars($_SESSION['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>
<main>
    <div class="page-header">
        <span>Action Logs</span><br>
        <small>Track all admin activities and changes in the system.</small>
    </div>
    <div class="page-content">
        <!-- Filter Form -->
        <div class="mb-3">
            <form method="GET" action="action_logs.php" class="row g-3" id="filterForm">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search actions..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="action_type" class="form-select form-select-sm">
                        <option value="">All Action Types</option>
                        <?php foreach ($action_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $action_type_filter === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="action_logs.php" class="btn btn-secondary btn-sm">Clear</a>
                    <button type="submit" form="bulkActionForm" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete the selected logs?');">Delete Selected</button>
                </div>
            </form>
        </div>

        <!-- Records Table with Bulk Action Form -->
        <form id="bulkActionForm" method="POST" action="action_logs.php">
            <input type="hidden" name="action" value="bulk_delete">
            <div class="records table-responsive">
                <div class="record-header.remaining">
                    <div class="add">
                        <span>All Actions (<?php echo $total_logs; ?> found)</span>
                    </div>
                </div>
                <div>
                    <table class="table table-striped table-hover" width="100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'action_type', 'sort_order' => $sort_by === 'action_type' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">Action Type <?php if ($sort_by === 'action_type') echo $sort_order === 'ASC' ? '↑' : '↓'; ?></a></th>
                                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'admin_name', 'sort_order' => $sort_by === 'admin_name' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">Performed By <?php if ($sort_by === 'admin_name') echo $sort_order === 'ASC' ? '↑' : '↓'; ?></a></th>
                                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'target', 'sort_order' => $sort_by === 'target' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">Target <?php if ($sort_by === 'target') echo $sort_order === 'ASC' ? '↑' : '↓'; ?></a></th>
                                <th>Details</th>
                                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'created_at', 'sort_order' => $sort_by === 'created_at' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">Timestamp <?php if ($sort_by === 'created_at') echo $sort_order === 'ASC' ? '↑' : '↓'; ?></a></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($action_logs)): ?>
                                <tr><td colspan="6" class="text-center">No actions found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($action_logs as $log): ?>
                                    <tr>
                                        <td><input type="checkbox" name="log_ids[]" value="<?php echo $log['id']; ?>" class="logCheckbox"></td>
                                        <td><?php echo htmlspecialchars($log['action_type']); ?></td>
                                        <td><?php echo htmlspecialchars($log['admin_name']); ?></td>
                                        <td><?php echo htmlspecialchars($log['target']); ?></td>
                                        <td><?php echo htmlspecialchars($log['details'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('F j, Y, g:i A', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-3">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a></li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a></li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </form>
    </div>
</main>

<script>
    // Select All functionality
    document.getElementById('selectAll').addEventListener('change', function () {
        const checkboxes = document.querySelectorAll('.logCheckbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Ensure at least one checkbox is selected before submitting
    document.getElementById('bulkActionForm').addEventListener('submit', function (e) {
        const checked = document.querySelectorAll('.logCheckbox:checked').length;
        if (checked === 0) {
            e.preventDefault();
            alert('Please select at least one log to delete.');
        }
    });
</script>

<?php require 'includes/footer.php'; ?>