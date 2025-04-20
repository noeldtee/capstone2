<?php
$page_title = "Payment Logs";
require 'includes/header.php';

// Pagination settings
$payments_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $payments_per_page;

// Filter and search settings
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the WHERE clause
$where_clauses = ["p.archived = 0"];
$params = [];
$types = "";

// Search filter
if ($search) {
    $where_clauses[] = "(p.description LIKE ? OR p.payment_method LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
    $types .= "ss";
}

// Status filter
if ($status_filter !== 'all') {
    $where_clauses[] = "p.payment_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Start date filter
if ($start_date) {
    $where_clauses[] = "DATE(p.payment_date) >= ?";
    $params[] = $start_date;
    $types .= "s";
}

// End date filter
if ($end_date) {
    $where_clauses[] = "DATE(p.payment_date) <= ?";
    $params[] = $end_date;
    $types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total filtered payments
$count_query = "SELECT COUNT(*) as total FROM payments p $where_sql";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_payments = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = $total_payments > 0 ? ceil($total_payments / $payments_per_page) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $payments_per_page;

// Fetch paginated payments
$query = "SELECT p.id, p.request_id, p.payment_method, p.amount, p.payment_status, p.description, 
                 p.payment_date, p.archived 
          FROM payments p 
          $where_sql 
          ORDER BY p.payment_date DESC 
          LIMIT ? OFFSET ?";
$params[] = $payments_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$paginated_payments = [];
while ($row = $result->fetch_assoc()) {
    $paginated_payments[] = $row;
}

// Fetch archived payments for the modal
$archived_query = "SELECT p.id, p.request_id, p.payment_method, p.amount, p.payment_status, 
                          p.description, p.payment_date 
                   FROM payments p 
                   WHERE p.archived = 1 
                   ORDER BY p.payment_date DESC";
$stmt = $conn->prepare($archived_query);
$stmt->execute();
$archived_result = $stmt->get_result();
$archived_payments = [];
while ($row = $archived_result->fetch_assoc()) {
    $archived_payments[] = $row;
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
        <span>Payment History</span><br>
        <small>View and manage the history of all payment transactions.</small>
    </div>
    <div class="page-content">
        <!-- Filter Form -->
        <div class="mb-3">
            <form method="GET" action="payment_logs.php" class="row g-3" id="filterForm">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search payments..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="PENDING" <?php echo $status_filter === 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                        <option value="PAID" <?php echo $status_filter === 'PAID' ? 'selected' : ''; ?>>Paid</option>
                        <option value="FAILED" <?php echo $status_filter === 'FAILED' ? 'selected' : ''; ?>>Failed</option>
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
                    <a href="payment_logs.php" class="btn btn-secondary btn-sm">Clear</a>
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
                    <span>All Payment Transactions (<?php echo $total_payments; ?> found)</span>
                    <button type="button" class="btn btn-warning btn-sm float-end" onclick="bulkArchive()">Archive Selected</button>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <button type="button" class="btn btn-danger btn-sm float-end me-2" onclick="bulkDelete()">Delete Selected</button>
                    <?php endif; ?>
                </div>
            </div>
            <form id="bulk-form">
                <table class="table table-striped table-hover" width="100%">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" onclick="toggleSelectAll()"></th>
                            <th>Payment Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Description</th>
                            <th>Date Paid</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paginated_payments)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No payments found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paginated_payments as $payment): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_payments[]" value="<?php echo $payment['id']; ?>" class="payment-checkbox">
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php
                                                            switch (strtolower($payment['payment_status'])) {
                                                                case 'pending':
                                                                    echo 'bg-warning';
                                                                    break;
                                                                case 'paid':
                                                                    echo 'bg-success';
                                                                    break;
                                                                case 'failed':
                                                                    echo 'bg-danger';
                                                                    break;
                                                            }
                                                            ?>">
                                            <?php echo htmlspecialchars($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['description']); ?></td>
                                    <td><?php echo date('F j, Y, h:i A', strtotime($payment['payment_date'])); ?></td>
                                    <td>
                                        <a href="#" class="view-btn" data-bs-toggle="modal" data-bs-target="#viewModal" data-id="<?php echo $payment['id']; ?>">View details</a>
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
                <h5 class="modal-title" id="viewModalLabel">Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Left Column: Payment Info -->
                    <div class="col-md-6">
                        <h6>Payment Information</h6>
                        <p><strong>Payment ID:</strong> <span id="modal-payment-id"></span></p>
                        <p><strong>Request ID:</strong> <span id="modal-request-id"></span></p>
                        <p><strong>Payment Method:</strong> <span id="modal-payment-method"></span></p>
                        <p><strong>Amount:</strong> <span id="modal-amount"></span></p>
                        <p><strong>Status:</strong> <span id="modal-payment-status"></span></p>
                        <p><strong>Description:</strong> <span id="modal-description"></span></p>
                        <p><strong>Date Paid:</strong> <span id="modal-payment-date"></span></p>
                    </div>
                    <!-- Right Column: Associated Request Info -->
                    <div class="col-md-6">
                        <h6>Associated Request Information</h6>
                        <p><strong>Student Name:</strong> <span id="modal-student-name"></span></p>
                        <p><strong>Email:</strong> <span id="modal-email"></span></p>
                        <p><strong>Contact Number:</strong> <span id="modal-number"></span></p>
                        <p><strong>Document Type:</strong> <span id="modal-document-type"></span></p>
                        <p><strong>Request Status:</strong> <span id="modal-request-status"></span></p>
                        <p><strong>Requested Date:</strong> <span id="modal-requested-date"></span></p>
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
                <h5 class="modal-title" id="archiveModalLabel">Archived Payments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Payment Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Description</th>
                            <th>Date Paid</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($archived_payments)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No archived payments found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($archived_payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php
                                                            switch (strtolower($payment['payment_status'])) {
                                                                case 'pending':
                                                                    echo 'bg-warning';
                                                                    break;
                                                                case 'paid':
                                                                    echo 'bg-success';
                                                                    break;
                                                                case 'failed':
                                                                    echo 'bg-danger';
                                                                    break;
                                                            }
                                                            ?>">
                                            <?php echo htmlspecialchars($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['description']); ?></td>
                                    <td><?php echo date('F j, Y, h:i A', strtotime($payment['payment_date'])); ?></td>
                                    <td>
                                        <a href="#" class="view-btn" data-bs-toggle="modal" data-bs-target="#viewModal" data-id="<?php echo $payment['id']; ?>">View details</a>
                                        <button type="button" class="btn btn-success btn-sm retrieve-btn" data-id="<?php echo $payment['id']; ?>">Retrieve</button>
                                        <?php if ($_SESSION['role'] === 'admin'): ?>
                                            <button type="button" class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $payment['id']; ?>">Delete</button>
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

<script>
    // Toggle all checkboxes
    function toggleSelectAll() {
        const selectAll = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.payment-checkbox');
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

    // Bulk archive selected payments
    function bulkArchive() {
        const selected = Array.from(document.querySelectorAll('.payment-checkbox:checked'))
            .map(checkbox => checkbox.value);
        if (selected.length === 0) {
            alert('Please select at least one payment to archive.');
            return;
        }
        if (confirm(`Archive ${selected.length} selected payment(s)?`)) {
            showProcessingMessage('Archiving payments... Please wait.');
            fetch('payment_actions.php', {
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
                    alert('Failed to archive payments: ' + error.message);
                });
        }
    }

    <?php if ($_SESSION['role'] === 'admin'): ?>
        // Bulk delete selected payments
        function bulkDelete() {
            const selected = Array.from(document.querySelectorAll('.payment-checkbox:checked'))
                .map(checkbox => checkbox.value);
            if (selected.length === 0) {
                alert('Please select at least one payment to delete.');
                return;
            }
            if (confirm(`Delete ${selected.length} selected payment(s)? This action cannot be undone.`)) {
                showProcessingMessage('Deleting payments... Please wait.');
                fetch('payment_actions.php', {
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
                        alert('Failed to delete payments: ' + error.message);
                    });
            }
        }
    <?php endif; ?>

    // Individual archive function
    document.querySelectorAll('.archive-btn').forEach(button => {
        button.addEventListener('click', function() {
            const paymentId = this.dataset.id;
            if (confirm(`Archive payment ID: ${paymentId}?`)) {
                showProcessingMessage('Archiving payment... Please wait.');
                fetch('payment_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=archive&id=${paymentId}`
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
                        alert('Failed to archive payment: ' + error.message);
                    });
            }
        });
    });

    <?php if ($_SESSION['role'] === 'admin'): ?>
        // Individual delete function
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const paymentId = this.dataset.id;
                if (confirm(`Delete payment ID: ${paymentId}? This action cannot be undone.`)) {
                    showProcessingMessage('Deleting payment... Please wait.');
                    fetch('payment_actions.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `action=delete&id=${paymentId}`
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
                            alert('Failed to delete payment: ' + error.message);
                        });
                }
            });
        });
    <?php endif; ?>

    // Retrieve (unarchive) a payment
    document.querySelectorAll('.retrieve-btn').forEach(button => {
        button.addEventListener('click', function() {
            const paymentId = this.dataset.id;
            if (confirm(`Retrieve payment ID: ${paymentId} from archive?`)) {
                showProcessingMessage('Retrieving payment... Please wait.');
                fetch('payment_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=retrieve&id=${paymentId}`
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
                        alert('Failed to retrieve payment: ' + error.message);
                    });
            }
        });
    });

    // Populate view modal
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            fetch(`payment_actions.php?action=get&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const payment = data.payment;
                        const request = data.request;
                        // Payment Information
                        document.getElementById('modal-payment-id').textContent = payment.id;
                        document.getElementById('modal-request-id').textContent = payment.request_id;
                        document.getElementById('modal-payment-method').textContent = payment.payment_method;
                        document.getElementById('modal-amount').textContent = '₱' + parseFloat(payment.amount).toFixed(2);
                        document.getElementById('modal-payment-status').textContent = payment.payment_status;
                        document.getElementById('modal-description').textContent = payment.description;
                        document.getElementById('modal-payment-date').textContent = new Date(payment.payment_date).toLocaleString('en-US', {
                            month: 'long',
                            day: 'numeric',
                            year: 'numeric',
                            hour: 'numeric',
                            minute: 'numeric',
                            hour12: true
                        });
                        // Associated Request Information
                        document.getElementById('modal-student-name').textContent = request.student_name || 'N/A';
                        document.getElementById('modal-email').textContent = request.email || 'N/A';
                        document.getElementById('modal-number').textContent = request.number || 'N/A';
                        document.getElementById('modal-document-type').textContent = request.document_type || 'N/A';
                        document.getElementById('modal-request-status').textContent = request.status || 'N/A';
                        document.getElementById('modal-requested-date').textContent = request.requested_date ? new Date(request.requested_date).toLocaleDateString('en-US', {
                            month: 'long',
                            day: 'numeric',
                            year: 'numeric'
                        }) : 'N/A';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => alert('Failed to load payment details: ' + error.message));
        });
    });

    // Date validation
    document.addEventListener('DOMContentLoaded', function() {
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const dateError = document.getElementById('date-error');
        const filterForm = document.getElementById('filterForm');
        const today = new Date().toISOString().split('T')[0]; // e.g., '2025-04-19'

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
</script>

<?php require 'includes/footer.php'; ?>