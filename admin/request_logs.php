<?php
$page_title = "Request Logs";
require 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['registrar', 'staff'])) {
    redirect('../index.php', 'Please log in as a registrar or staff to perform this action.', 'warning');
    exit();
}

// Pagination settings
$requests_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $requests_per_page;

// Filter and search settings
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the WHERE clause
$where_clauses = ["r.archived = 0"];
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

// Start date filter
if ($start_date) {
    $where_clauses[] = "DATE(r.requested_date) >= ?";
    $params[] = $start_date;
    $types .= "s";
}

// End date filter
if ($end_date) {
    $where_clauses[] = "DATE(r.requested_date) <= ?";
    $params[] = $end_date;
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
                 r.unit_price, r.status, r.requested_date, r.archived, r.file_path, r.remarks, r.rejection_reason 
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

// Fetch archived requests for the modal
$archived_query = "SELECT r.id, r.document_type, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                          r.unit_price, r.status, r.requested_date, r.file_path, r.remarks, r.rejection_reason 
                   FROM requests r 
                   JOIN users u ON r.user_id = u.id 
                   WHERE r.archived = 1 
                   ORDER BY r.requested_date DESC";
$stmt = $conn->prepare($archived_query);
$stmt->execute();
$archived_result = $stmt->get_result();
$archived_requests = [];
while ($row = $archived_result->fetch_assoc()) {
    $archived_requests[] = $row;
}
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
        <span>Request History</span><br>
        <small>View and manage the history of all document requests, including rejected ones.</small>
    </div>
    <div class="page-content">
        <!-- Filter Form -->
        <div class="mb-3">
            <form method="GET" action="request_logs.php" class="row g-3" id="filterForm">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search requests..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="In Process" <?php echo $status_filter === 'In Process' ? 'selected' : ''; ?>>In Process</option>
                        <option value="Ready to Pickup" <?php echo $status_filter === 'Ready to Pickup' ? 'selected' : ''; ?>>Ready to Pickup</option>
                        <option value="To Release" <?php echo $status_filter === 'To Release' ? 'selected' : ''; ?>>To Release</option>
                        <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="request_logs.php" class="btn btn-secondary btn-sm">Clear</a>
                    <button type="button" class="btn btn-primary btn-sm" style="margin-top: 3px;" data-bs-toggle="modal" data-bs-target="#archiveModal">View Archives</button>
                </div>
                <div class="col-12">
                    <small id="date-error" class="text-danger" style="display: none;"></small>
                </div>
            </form>
        </div>

        <!-- Records Table -->
        <div class="records table-responsive">
            <div class="record-header">
                <div class="add">
                    <span>All Requested Documents (<?php echo $total_requests; ?> found)</span>
                    <button type="button" class="btn btn-warning btn-sm float-end" onclick="bulkArchive()">Archive Selected</button>
                    <?php if ($_SESSION['role'] === 'registrar'): ?>
                        <button type="button" class="btn btn-danger btn-sm float-end" style="margin-right: 10px;" onclick="bulkDelete()">Delete Selected</button>
                    <?php endif; ?>
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
                            <tr>
                                <td colspan="8" class="text-center">No requests found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paginated_requests as $request): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_requests[]" value="<?php echo $request['id']; ?>" class="request-checkbox">
                                    </td>
                                    <td><?php echo htmlspecialchars($request['id']); ?></td>
                                    <td><?php echo htmlspecialchars($request['document_type']); ?></td>
                                    <td><?php echo htmlspecialchars($request['student_name']); ?></td>
                                    <td>₱<?php echo number_format($request['unit_price'], 2); ?></td>
                                    <td><?php echo date('F j, Y', strtotime($request['requested_date'])); ?></td>
                                    <td>
                                        <span class="badge <?php
                                                            switch (strtolower($request['status'])) {
                                                                case 'pending':
                                                                    echo 'bg-warning';
                                                                    break;
                                                                case 'in process':
                                                                    echo 'bg-info';
                                                                    break;
                                                                case 'ready to pickup':
                                                                    echo 'bg-success';
                                                                    break;
                                                                case 'to release':
                                                                    echo 'bg-success';
                                                                    break;
                                                                case 'completed':
                                                                    echo 'bg-primary';
                                                                    break;
                                                                case 'rejected':
                                                                    echo 'bg-danger';
                                                                    break;
                                                            }
                                                            ?>">
                                            <?php echo htmlspecialchars($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm view-btn" data-bs-toggle="modal" data-bs-target="#viewModal" data-id="<?php echo $request['id']; ?>">View</button>
                                        <button type="button" class="btn btn-warning btn-sm archive-btn" data-id="<?php echo $request['id']; ?>">Archive</button>
                                        <?php if ($_SESSION['role'] === 'registrar'): ?>
                                            <button type="button" class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $request['id']; ?>">Delete</button>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalLabel">Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Left Column: Student Info -->
                    <div class="col-md-6">
                        <h6>Student Information</h6>
                        <p><strong>Name:</strong> <span id="modal-student-name"></span></p>
                        <p><strong>Email:</strong> <span id="modal-email"></span></p>
                        <p><strong>Contact Number:</strong> <span id="modal-number"></span></p>
                        <p><strong>Course:</strong> <span id="modal-course"></span></p>
                        <p><strong>Section:</strong> <span id="modal-section"></span></p>
                        <p><strong>School Year:</strong> <span id="modal-school-year"></span></p>
                        <p><strong>Year Level:</strong> <span id="modal-year-level"></span></p>
                    </div>
                    <!-- Right Column: Request Info -->
                    <div class="col-md-6">
                        <h6>Request Information</h6>
                        <p><strong>ID:</strong> <span id="modal-id"></span></p>
                        <p><strong>Document Type:</strong> <span id="modal-document-type"></span></p>
                        <p><strong>Price:</strong> <span id="modal-price"></span></p>
                        <p><strong>Requested Date:</strong> <span id="modal-requested-date"></span></p>
                        <p><strong>Status:</strong> <span id="modal-status"></span></p>
                        <p><strong>Payment Status:</strong> <span id="modal-payment-status"></span></p>
                        <p><strong>Payment Method:</strong> <span id="modal-payment-method"></span></p>
                        <p><strong>Reason for Request:</strong> <span id="modal-remarks"></span></p>
                        <p><strong>Uploaded File:</strong> <span id="modal-file"></span></p>
                        <p><strong>Rejection Reason:</strong> <span id="modal-rejection-reason"></span></p>
                        <p><strong>QR Code:</strong></p>
                        <div id="modal-qr-code" style="display: none; text-align: center;">
                            <div id="qr-code-canvas"></div>
                            <button type="button" class="btn btn-primary btn-sm mt-2" onclick="downloadQRCode()">Download QR Code</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Archive Modal -->
<div class="modal fade" id="archiveModal" tabindex="-1" aria-labelledby="archiveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="archiveModalLabel">Archived Requests</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table">
                    <thead>
                        <tr>
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
                        <?php if (empty($archived_requests)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No archived requests found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($archived_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['id']); ?></td>
                                    <td><?php echo htmlspecialchars($request['document_type']); ?></td>
                                    <td><?php echo htmlspecialchars($request['student_name']); ?></td>
                                    <td>₱<?php echo number_format($request['unit_price'], 2); ?></td>
                                    <td><?php echo date('F j, Y', strtotime($request['requested_date'])); ?></td>
                                    <td>
                                        <span class="badge <?php
                                                            switch (strtolower($request['status'])) {
                                                                case 'pending':
                                                                    echo 'bg-warning';
                                                                    break;
                                                                case 'in process':
                                                                    echo 'bg-info';
                                                                    break;
                                                                case 'ready to pickup':
                                                                    echo 'bg-success';
                                                                    break;
                                                                case 'to release':
                                                                    echo 'bg-success';
                                                                    break;
                                                                case 'completed':
                                                                    echo 'bg-primary';
                                                                    break;
                                                                case 'rejected':
                                                                    echo 'bg-danger';
                                                                    break;
                                                            }
                                                            ?>">
                                            <?php echo htmlspecialchars($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm view-btn" data-bs-toggle="modal" data-bs-target="#viewModal" data-id="<?php echo $request['id']; ?>">View</button>
                                        <button type="button" class="btn btn-success btn-sm retrieve-btn" data-id="<?php echo $request['id']; ?>">Retrieve</button>
                                        <?php if ($_SESSION['role'] === 'registrar'): ?>
                                            <button type="button" class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $request['id']; ?>">Delete</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// Toggle all checkboxes
function toggleSelectAll() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.request-checkbox');
    checkboxes.forEach(checkbox => checkbox.checked = selectAll.checked);
}

// Function to show a processing message
function showProcessingMessage(message) {
    const processingDiv = document.createElement('div');
    processingDiv.id = 'processing-message';
    processingDiv.style.position = 'fixed';
    processingDiv.style.top = '50%';
    processingDiv.style.left = '50%';
    processingDiv.style.transform = 'translate(-50%, -50%)';
    processingDiv.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
    processingDiv.style.color = 'white';
    processingDiv.style.padding = '20px';
    processingDiv.style.borderRadius = '5px';
    processingDiv.style.zIndex = '1000';
    processingDiv.textContent = message;
    document.body.appendChild(processingDiv);
}

function hideProcessingMessage() {
    const processingDiv = document.getElementById('processing-message');
    if (processingDiv) processingDiv.remove();
}

// Bulk archive selected requests
function bulkArchive() {
    const selected = Array.from(document.querySelectorAll('.request-checkbox:checked'))
        .map(checkbox => checkbox.value);
    if (selected.length === 0) {
        alert('Please select at least one request to archive.');
        return;
    }
    if (confirm(`Archive ${selected.length} selected request(s)?`)) {
        showProcessingMessage('Archiving requests... Please wait.');
        fetch('request_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=bulk_archive&ids=${selected.join(',')}`
            })
            .then(response => response.json())
            .then(data => {
                hideProcessingMessage();
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                hideProcessingMessage();
                alert('Failed to archive requests: ' + error.message);
            });
    }
}

<?php if ($_SESSION['role'] === 'registrar'): ?>
    // Bulk delete selected requests
    function bulkDelete() {
        const selected = Array.from(document.querySelectorAll('.request-checkbox:checked'))
            .map(checkbox => checkbox.value);
        if (selected.length === 0) {
            alert('Please select at least one request to delete.');
            return;
        }
        if (confirm(`Delete ${selected.length} selected request(s)? This action cannot be undone.`)) {
            showProcessingMessage('Deleting requests... Please wait.');
            fetch('request_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=bulk_delete&ids=${selected.join(',')}`
                })
                .then(response => response.json())
                .then(data => {
                    hideProcessingMessage();
                    if (data.status === 'success') {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    hideProcessingMessage();
                    alert('Failed to delete requests: ' + error.message);
                });
        }
    }
<?php endif; ?>

// Individual archive function
document.querySelectorAll('.archive-btn').forEach(button => {
    button.addEventListener('click', function() {
        const requestId = this.dataset.id;
        if (confirm(`Archive request ID: ${requestId}?`)) {
            showProcessingMessage('Archiving request... Please wait.');
            fetch('request_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=archive&id=${requestId}`
                })
                .then(response => response.json())
                .then(data => {
                    hideProcessingMessage();
                    if (data.status === 'success') {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    hideProcessingMessage();
                    alert('Failed to archive request: ' + error.message);
                });
        }
    });
});

<?php if ($_SESSION['role'] === 'registrar'): ?>
    // Individual delete function
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const requestId = this.dataset.id;
            if (confirm(`Delete request ID: ${requestId}? This action cannot be undone.`)) {
                showProcessingMessage('Deleting request... Please wait.');
                fetch('request_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=delete&id=${requestId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideProcessingMessage();
                        if (data.status === 'success') {
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        hideProcessingMessage();
                        alert('Failed to delete request: ' + error.message);
                    });
            }
        });
    });
<?php endif; ?>

// Retrieve (unarchive) a request
document.querySelectorAll('.retrieve-btn').forEach(button => {
    button.addEventListener('click', function() {
        const requestId = this.dataset.id;
        if (confirm(`Retrieve request ID: ${requestId} from archive?`)) {
            showProcessingMessage('Retrieving request... Please wait.');
            fetch('request_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=retrieve&id=${requestId}`
                })
                .then(response => response.json())
                .then(data => {
                    hideProcessingMessage();
                    if (data.status === 'success') {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    hideProcessingMessage();
                    alert('Failed to retrieve request: ' + error.message);
                });
        }
    });
});

// Populate view modal
document.querySelectorAll('.view-btn').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.dataset.id;
        fetch(`request_actions.php?action=get&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const request = data.data;
                    // Student Information
                    document.getElementById('modal-student-name').textContent = request.student_name || 'N/A';
                    document.getElementById('modal-email').textContent = request.email || 'N/A';
                    document.getElementById('modal-number').textContent = request.number || 'N/A';
                    document.getElementById('modal-course').textContent = request.course_name || 'N/A';
                    document.getElementById('modal-section').textContent = request.section_name || 'N/A';
                    document.getElementById('modal-school-year').textContent = request.school_year || 'N/A';
                    document.getElementById('modal-year-level').textContent = request.year_level || 'N/A';
                    // Request Information
                    document.getElementById('modal-id').textContent = request.id;
                    document.getElementById('modal-document-type').textContent = request.document_type || 'N/A';
                    document.getElementById('modal-price').textContent = '₱' + parseFloat(request.unit_price).toFixed(2);
                    document.getElementById('modal-requested-date').textContent = new Date(request.requested_date).toLocaleDateString('en-US', {
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric'
                    });
                    document.getElementById('modal-status').textContent = request.status || 'N/A';
                    document.getElementById('modal-payment-status').textContent = request.payment_status ? ucwords(request.payment_status.replace('_', ' ')) : 'N/A';
                    document.getElementById('modal-payment-method').textContent = request.payment_method ? ucwords(request.payment_method.replace('_', ' ')) : 'N/A';
                    document.getElementById('modal-remarks').textContent = request.remarks || 'N/A';
                    const fileSpan = document.getElementById('modal-file');
                    if (request.file_path) {
                        fileSpan.innerHTML = `<a href="../${request.file_path}" target="_blank" download>Download File</a>`;
                    } else {
                        fileSpan.textContent = 'No file uploaded';
                    }
                    document.getElementById('modal-rejection-reason').textContent = request.rejection_reason || 'N/A';
                    // QR Code
                    const qrCodeDiv = document.getElementById('modal-qr-code');
                    const qrCodeCanvas = document.getElementById('qr-code-canvas');
                    qrCodeCanvas.innerHTML = ''; // Clear previous QR code
                    if (request.status === 'Ready to Pickup') {
                        qrCodeDiv.style.display = 'block';
                        new QRCode(qrCodeCanvas, {
                            text: `request_id:${request.id}`,
                            width: 150,
                            height: 150
                        });
                    } else {
                        qrCodeDiv.style.display = 'none';
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => alert('Failed to load request details: ' + error.message));
    });
});

// Utility function to capitalize words
function ucwords(str) {
    return str.toLowerCase().replace(/(^([a-zA-Z\p{M}]))|([ -][a-zA-Z\p{M}])/g, function(s) {
        return s.toUpperCase();
    });
}

// Date validation
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const dateError = document.getElementById('date-error');
    const filterForm = document.getElementById('filterForm');
    const today = new Date().toISOString().split('T')[0]; // e.g., '2025-04-27'

    // Set max attribute to today
    startDateInput.max = today;
    endDateInput.max = today;

    // Validate dates on form submission
    filterForm.addEventListener('submit', function(event) {
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;

        if (startDate && endDate && startDate > endDate) {
            event.preventDefault();
            dateError.textContent = 'End date cannot be before start date.';
            dateError.style.display = 'block';
        } else {
            dateError.style.display = 'none';
        }
    });

    // Clear error on input change
    startDateInput.addEventListener('change', function() {
        dateError.style.display = 'none';
    });
    endDateInput.addEventListener('change', function() {
        dateError.style.display = 'none';
    });
});

// Download QR Code
function downloadQRCode() {
    const qrCanvas = document.querySelector('#qr-code-canvas canvas');
    if (qrCanvas) {
        const link = document.createElement('a');
        link.href = qrCanvas.toDataURL('image/png');
        link.download = `request_qr_${document.getElementById('modal-id').textContent}.png`;
        link.click();
    }
}
</script>

<?php require 'includes/footer.php'; ?>