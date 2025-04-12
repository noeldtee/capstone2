<?php
$page_title = "Document Request Management";
require 'includes/header.php';

// Pagination settings
$requests_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $requests_per_page;

// Filter and search settings
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the WHERE clause
$where_clauses = ["r.status IN ('Pending', 'In Process', 'Ready to Pickup')", "r.archived = 0"];
$params = [];
$types = "";

// Search filter
if ($search) {
    $where_clauses[] = "(r.document_type LIKE ? OR CONCAT(u.firstname, ' ', u.lastname) LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
    $types .= "ss";
}

// Status filter
if ($status_filter !== 'all') {
    $where_clauses[] = "r.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Date filter
if ($date_filter) {
    $where_clauses[] = "DATE(r.requested_date) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total filtered requests
$count_query = "SELECT COUNT(*) as total FROM requests r JOIN users u ON r.user_id = u.id $where_sql";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_requests = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = $total_requests > 0 ? ceil($total_requests / $requests_per_page) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $requests_per_page;

// Fetch paginated requests
$query = "SELECT r.id, r.document_type, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                 r.price, r.status, r.requested_date, r.file_path, r.remarks 
          FROM requests r 
          JOIN users u ON r.user_id = u.id 
          $where_sql 
          ORDER BY r.requested_date DESC 
          LIMIT ? OFFSET ?";
$params[] = $requests_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$paginated_requests = [];
while ($row = $result->fetch_assoc()) {
    $paginated_requests[] = $row;
}
?>

<link rel="stylesheet" href="../assets/css/admin_dashboard.css">
<link rel="stylesheet" href="../assets/css/admin_request.css">
<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x" style="z-index: 1050; margin-top: 20px;" role="alert">
        <?php echo htmlspecialchars($_SESSION['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>

<main>
    <div class="page-header">
        <span>Document Request Management</span><br>
        <small>Manage and process student document requests efficiently.</small>
    </div>
    <div class="page-content">
        <!-- Filter Form -->
        <div class="mb-3">
            <form method="GET" action="request.php" class="row g-3" id="filterForm">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search requests..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Active Statuses</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="In Process" <?php echo $status_filter === 'In Process' ? 'selected' : ''; ?>>In Process</option>
                        <option value="Ready to Pickup" <?php echo $status_filter === 'Ready to Pickup' ? 'selected' : ''; ?>>Ready to Pickup</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="request.php" class="btn btn-secondary btn-sm">Clear</a>
                </div>
            </form>
        </div>

        <!-- Records Table -->
        <div class="records table-responsive">
            <div class="record-header">
                <div class="add">
                    <span>All Document Requests (<?php echo $total_requests; ?> found)</span>
                </div>
                <div class="bulk-actions">
                    <div class="dropdown">
                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="bulkActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            Bulk Actions
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="bulkActionsDropdown">
                            <li><a class="dropdown-item" href="#" onclick="bulkApprove(); return false;">Approve Pending</a></li>
                            <li><a class="dropdown-item" href="#" onclick="bulkMarkReady(); return false;">Mark In Process as Ready</a></li>
                            <li><a class="dropdown-item" href="#" onclick="bulkMarkCompleted(); return false;">Mark Ready as Completed</a></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="bulkReject(); return false;">Reject Pending</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <form id="bulk-form">
                <table class="table table-striped table-hover" width="100%">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" onclick="toggleSelectAll()"></th>
                            <th>ID</th>
                            <th>Document Type</th>
                            <th>Student Name</th>
                            <th>Price</th>
                            <th>Requested Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paginated_requests)): ?>
                            <tr><td colspan="8" class="text-center">No active requests found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($paginated_requests as $request): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_requests[]" value="<?php echo $request['id']; ?>" class="request-checkbox" data-status="<?php echo htmlspecialchars($request['status']); ?>">
                                    </td>
                                    <td><?php echo htmlspecialchars($request['id']); ?></td>
                                    <td><?php echo htmlspecialchars($request['document_type']); ?></td>
                                    <td><?php echo htmlspecialchars($request['student_name']); ?></td>
                                    <td>₱<?php echo number_format($request['price'], 2); ?></td>
                                    <td><?php echo date('F j, Y', strtotime($request['requested_date'])); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            switch (strtolower($request['status'])) {
                                                case 'pending': echo 'bg-warning'; break;
                                                case 'in process': echo 'bg-info'; break;
                                                case 'ready to pickup': echo 'bg-success'; break;
                                            }
                                        ?>">
                                            <?php echo htmlspecialchars($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm view-btn" data-bs-toggle="modal" data-bs-target="#viewModal" data-id="<?php echo $request['id']; ?>">View</button>
                                        <?php if ($request['status'] === 'Pending'): ?>
                                            <button type="button" class="btn btn-success btn-sm" onclick="approveRequest(<?php echo $request['id']; ?>)">Approve</button>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="<?php echo $request['id']; ?>">Reject</button>
                                        <?php elseif ($request['status'] === 'In Process'): ?>
                                            <button type="button" class="btn btn-success btn-sm" onclick="markReady(<?php echo $request['id']; ?>)">Mark Ready</button>
                                        <?php elseif ($request['status'] === 'Ready to Pickup'): ?>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="markCompleted(<?php echo $request['id']; ?>)">Mark Completed</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous">
                                <span aria-hidden="true">«</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next">
                                <span aria-hidden="true">»</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalLabel">Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>ID:</strong> <span id="modal-id"></span></p>
                <p><strong>Document Type:</strong> <span id="modal-document-type"></span></p>
                <p><strong>Student Name:</strong> <span id="modal-student-name"></span></p>
                <p><strong>Price:</strong> <span id="modal-price"></span></p>
                <p><strong>Requested Date:</strong> <span id="modal-requested-date"></span></p>
                <p><strong>Status:</strong> <span id="modal-status"></span></p>
                <p><strong>Reason for Request:</strong> <span id="modal-remarks"></span></p>
                <p><strong>Uploaded File:</strong> <span id="modal-file"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal (Individual) -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel">Reject Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rejectForm">
                    <input type="hidden" id="reject-request-id" name="request_id">
                    <div class="mb-3">
                        <label for="rejection-reason" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="rejection-reason" name="rejection_reason" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitRejection()">Reject</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Reject Modal -->
<div class="modal fade" id="bulkRejectModal" tabindex="-1" aria-labelledby="bulkRejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkRejectModalLabel">Bulk Reject Requests</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bulkRejectForm">
                    <input type="hidden" id="bulk-reject-ids" name="ids">
                    <div class="mb-3">
                        <label for="bulk-rejection-reason" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="bulk-rejection-reason" name="rejection_reason" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitBulkRejection()">Reject Selected</button>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle all checkboxes
function toggleSelectAll() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.request-checkbox');
    checkboxes.forEach(checkbox => checkbox.checked = selectAll.checked);
}

// Bulk approve
function bulkApprove() {
    const selected = Array.from(document.querySelectorAll('.request-checkbox:checked'))
        .filter(checkbox => checkbox.dataset.status === 'Pending')
        .map(checkbox => checkbox.value);
    if (selected.length === 0) {
        alert('Please select at least one Pending request to approve.');
        return;
    }
    if (confirm(`Approve ${selected.length} selected Pending request(s)?`)) {
        fetch('request_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=bulk_approve&ids=${selected.join(',')}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => alert('Failed to approve requests: ' + error.message));
    }
}

// Bulk mark ready
function bulkMarkReady() {
    const selected = Array.from(document.querySelectorAll('.request-checkbox:checked'))
        .filter(checkbox => checkbox.dataset.status === 'In Process')
        .map(checkbox => checkbox.value);
    if (selected.length === 0) {
        alert('Please select at least one In Process request to mark as ready.');
        return;
    }
    if (confirm(`Mark ${selected.length} selected In Process request(s) as Ready to Pickup?`)) {
        fetch('request_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=bulk_mark_ready&ids=${selected.join(',')}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => alert('Failed to mark requests as ready: ' + error.message));
    }
}

// Bulk mark completed
function bulkMarkCompleted() {
    const selected = Array.from(document.querySelectorAll('.request-checkbox:checked'))
        .filter(checkbox => checkbox.dataset.status === 'Ready to Pickup')
        .map(checkbox => checkbox.value);
    if (selected.length === 0) {
        alert('Please select at least one Ready to Pickup request to mark as completed.');
        return;
    }
    if (confirm(`Mark ${selected.length} selected Ready to Pickup request(s) as Completed?`)) {
        fetch('request_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=bulk_mark_completed&ids=${selected.join(',')}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => alert('Failed to mark requests as completed: ' + error.message));
    }
}

// Bulk reject
function bulkReject() {
    const selected = Array.from(document.querySelectorAll('.request-checkbox:checked'))
        .filter(checkbox => checkbox.dataset.status === 'Pending')
        .map(checkbox => checkbox.value);
    if (selected.length === 0) {
        alert('Please select at least one Pending request to reject.');
        return;
    }
    document.getElementById('bulk-reject-ids').value = selected.join(',');
    const modal = new bootstrap.Modal(document.getElementById('bulkRejectModal'));
    modal.show();
}

// Submit bulk rejection
function submitBulkRejection() {
    const ids = document.getElementById('bulk-reject-ids').value;
    const reason = document.getElementById('bulk-rejection-reason').value.trim();
    if (!reason) {
        alert('Please provide a reason for rejection.');
        return;
    }
    if (confirm(`Reject ${ids.split(',').length} selected request(s)?`)) {
        fetch('request_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=bulk_reject&ids=${ids}&rejection_reason=${encodeURIComponent(reason)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => alert('Failed to reject requests: ' + error.message));
    }
}

// Individual approve
function approveRequest(id) {
    if (confirm(`Approve request ID: ${id}?`)) {
        fetch('request_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=approve&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => alert('Failed to approve request: ' + error.message));
    }
}

// Individual mark ready
function markReady(id) {
    if (confirm(`Mark request ID: ${id} as Ready to Pickup?`)) {
        fetch('request_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=mark_ready&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => alert('Failed to mark request as ready: ' + error.message));
    }
}

// Individual mark completed
function markCompleted(id) {
    if (confirm(`Mark request ID: ${id} as Completed?`)) {
        fetch('request_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=mark_completed&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => alert('Failed to mark request as completed: ' + error.message));
    }
}

// Open reject modal
document.querySelectorAll('[data-bs-target="#rejectModal"]').forEach(button => {
    button.addEventListener('click', function () {
        document.getElementById('reject-request-id').value = this.dataset.id;
        document.getElementById('rejectModalLabel').textContent = `Reject Request ID: ${this.dataset.id}`;
    });
});

// Submit individual rejection
function submitRejection() {
    const id = document.getElementById('reject-request-id').value;
    const reason = document.getElementById('rejection-reason').value.trim();
    if (!reason) {
        alert('Please provide a reason for rejection.');
        return;
    }
    if (confirm(`Reject request ID: ${id}?`)) {
        fetch('request_management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=reject&id=${id}&rejection_reason=${encodeURIComponent(reason)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => alert('Failed to reject request: ' + error.message));
    }
}

// Populate view modal
document.querySelectorAll('.view-btn').forEach(button => {
    button.addEventListener('click', function () {
        const id = this.dataset.id;
        fetch(`request_management.php?action=get&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const request = data.data;
                    document.getElementById('modal-id').textContent = request.id;
                    document.getElementById('modal-document-type').textContent = request.document_type;
                    document.getElementById('modal-student-name').textContent = request.student_name;
                    document.getElementById('modal-price').textContent = '₱' + parseFloat(request.price).toFixed(2);
                    document.getElementById('modal-requested-date').textContent = new Date(request.requested_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                    document.getElementById('modal-status').textContent = request.status;
                    document.getElementById('modal-remarks').textContent = request.remarks || 'N/A';
                    const fileSpan = document.getElementById('modal-file');
                    if (request.file_path) {
                        fileSpan.innerHTML = `<a href="../${request.file_path}" target="_blank" download>Download File</a>`;
                    } else {
                        fileSpan.textContent = 'No file uploaded';
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => alert('Failed to load request details: ' + error.message));
    });
});
</script>

<?php require 'includes/footer.php'; ?>