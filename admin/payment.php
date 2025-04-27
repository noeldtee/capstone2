<?php
$page_title = "Payment Management";
require 'includes/header.php';

// Check if user is logged in as cashier or registrar
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['registrar', 'cashier'])) {
    redirect('../index.php', 'Please log in as a registrar or cashier to access this page.', 'warning');
    exit();
}

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filter and search settings
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$payment_status_filter = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : 'all';
$payment_method_filter = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : 'all';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the WHERE clause
$where_clauses = ["r.status IN ('Pending', 'In Process', 'Ready to Pickup', 'To Release')", "r.archived = 0"];
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

// Payment status filter
if ($payment_status_filter !== 'all') {
    $where_clauses[] = "r.payment_status = ?";
    $params[] = $payment_status_filter;
    $types .= "s";
}

// Payment method filter
if ($payment_method_filter !== 'all') {
    $where_clauses[] = "r.payment_method = ?";
    $params[] = $payment_method_filter;
    $types .= "s";
}

// Date filter
if ($date_filter) {
    $where_clauses[] = "DATE(r.requested_date) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total filtered payments
$count_query = "SELECT COUNT(*) as total FROM requests r JOIN users u ON r.user_id = u.id $where_sql";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $records_per_page;

// Fetch paginated payments
$query = "SELECT r.id, r.document_type, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                 r.unit_price, r.status, r.payment_status, r.payment_method, r.requested_date 
          FROM requests r 
          JOIN users u ON r.user_id = u.id 
          $where_sql 
          ORDER BY r.requested_date DESC 
          LIMIT ? OFFSET ?";
$params[] = $records_per_page;
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
?>

<link rel="stylesheet" href="../assets/css/admin_dashboard.css">
<link rel="stylesheet" href="../assets/css/admin_payment.css">
<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x" style="z-index: 1050; margin-top: 20px;" role="alert">
        <?php echo htmlspecialchars($_SESSION['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>

<main>
    <div class="page-header">
        <span>Payment Management</span><br>
        <small>Manage and process student payment requests efficiently.</small>
    </div>
    <div class="page-content">
        <!-- Filter Form -->
        <div class="mb-3">
            <form method="GET" action="payment.php" class="row g-3" id="filterForm">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search payments..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Active Statuses</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="In Process" <?php echo $status_filter === 'In Process' ? 'selected' : ''; ?>>In Process</option>
                        <option value="Ready to Pickup" <?php echo $status_filter === 'Ready to Pickup' ? 'selected' : ''; ?>>Ready to Pickup</option>
                        <option value="To Release" <?php echo $status_filter === 'To Release' ? 'selected' : ''; ?>>To Release</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="payment_status" class="form-select form-select-sm">
                        <option value="all" <?php echo $payment_status_filter === 'all' ? 'selected' : ''; ?>>All Payment Statuses</option>
                        <option value="paid" <?php echo $payment_status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Awaiting Payment" <?php echo $payment_status_filter === 'Awaiting Payment' ? 'selected' : ''; ?>>Awaiting Payment</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="payment_method" class="form-select form-select-sm">
                        <option value="all" <?php echo $payment_method_filter === 'all' ? 'selected' : ''; ?>>All Payment Methods</option>
                        <option value="cash" <?php echo $payment_method_filter === 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="online" <?php echo $payment_method_filter === 'online' ? 'selected' : ''; ?>>Online</option>
                        <option value="N/A" <?php echo $payment_method_filter === 'N/A' ? 'selected' : ''; ?>>N/A</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="payment.php" class="btn btn-secondary btn-sm">Clear</a>
                </div>
            </form>
        </div>

        <!-- Records Table -->
        <div class="records table-responsive">
            <div class="record-header">
                <div class="add">
                    <span>All Payment Requests (<?php echo $total_records; ?> found)</span>
                </div>
            </div>
            <table class="table table-striped table-hover" width="100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Document Type</th>
                        <th>Student Name</th>
                        <th>Price</th>
                        <th>Payment Status</th>
                        <th>Payment Method</th>
                        <th>Requested Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paginated_payments)): ?>
                        <tr><td colspan="9" class="text-center">No payment requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($paginated_payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['id']); ?></td>
                                <td><?php echo htmlspecialchars($payment['document_type']); ?></td>
                                <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                                <td>₱<?php echo number_format($payment['unit_price'], 2); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        switch (strtolower($payment['payment_status'])) {
                                            case 'paid': echo 'bg-success'; break;
                                            case 'awaiting payment': echo 'bg-info'; break;
                                            case 'pending': echo 'bg-warning'; break;
                                        }
                                    ?>">
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $payment['payment_status']))); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(ucwords($payment['payment_method'])); ?></td>
                                <td><?php echo date('F j, Y', strtotime($payment['requested_date'])); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        switch (strtolower($payment['status'])) {
                                            case 'pending': echo 'bg-warning'; break;
                                            case 'in process': echo 'bg-info'; break;
                                            case 'ready to pickup': echo 'bg-success'; break;
                                            case 'to release': echo 'bg-primary'; break;
                                        }
                                    ?>">
                                        <?php echo htmlspecialchars($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm view-btn" data-bs-toggle="modal" data-bs-target="#viewModal" data-id="<?php echo $payment['id']; ?>">View</button>
                                    <?php if ($payment['payment_status'] === 'Awaiting Payment' && $payment['payment_method'] === 'cash'): ?>
                                        <button type="button" class="btn btn-success btn-sm mark-paid-btn" onclick="markPaid(<?php echo $payment['id']; ?>, this)">
                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                                            Mark Paid
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

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
                    <!-- Left Column: Student Info -->
                    <div class="col-md-6">
                        <h6>Student Information</h6>
                        <p><strong>Name:</strong> <span id="modal-student-name"></span></p>
                        <p><strong>Email:</strong> <span id="modal-email"></span></p>
                        <p><strong>Contact Number:</strong> <span id="modal-number"></span></p>
                    </div>
                    <!-- Right Column: Request Info -->
                    <div class="col-md-6">
                        <h6>Request Information</h6>
                        <p><strong>ID:</strong> <span id="modal-id"></span></p>
                        <p><strong>Document Type:</strong> <span id="modal-document-type"></span></p>
                        <p><strong>Price:</strong> <span id="modal-price"></span></p>
                        <p><strong>Payment Status:</strong> <span id="modal-payment-status"></span></p>
                        <p><strong>Payment Method:</strong> <span id="modal-payment-method"></span></p>
                        <p><strong>Requested Date:</strong> <span id="modal-requested-date"></span></p>
                        <p><strong>Status:</strong> <span id="modal-status"></span></p>
                    </div>
                </div>
                <!-- Payment Details -->
                <div class="mt-3">
                    <h6>Payment Details</h6>
                    <p><strong>Payment ID:</strong> <span id="modal-payment-id"></span></p>
                    <p><strong>Amount:</strong> <span id="modal-payment-amount"></span></p>
                    <p><strong>Payment Date:</strong> <span id="modal-payment-date"></span></p>
                    <p><strong>Description:</strong> <span id="modal-payment-description"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show spinner on a button
function showSpinner(button, loadingText) {
    const spinner = button.querySelector('.spinner-border');
    spinner.style.display = 'inline-block';
    button.disabled = true;
    button.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${loadingText}`;
}

// Hide spinner on a button
function hideSpinner(button, originalText) {
    const spinner = button.querySelector('.spinner-border');
    spinner.style.display = 'none';
    button.disabled = false;
    button.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span> ${originalText}`;
}

// Mark paid
function markPaid(id, button) {
    if (confirm(`Mark payment for request ID: ${id} as paid?`)) {
        showSpinner(button, 'Marking...');
        fetch('payment_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=mark_paid&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => alert('Failed to mark payment as paid: ' + error.message))
        .finally(() => hideSpinner(button, 'Mark Paid'));
    }
}

// Populate view modal
document.querySelectorAll('.view-btn').forEach(button => {
    button.addEventListener('click', function () {
        const id = this.dataset.id;
        fetch(`payment_actions.php?action=get&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const payment = data.data;
                    // Student Information
                    document.getElementById('modal-student-name').textContent = payment.student_name || 'N/A';
                    document.getElementById('modal-email').textContent = payment.email || 'N/A';
                    document.getElementById('modal-number').textContent = payment.number || 'N/A';
                    // Request Information
                    document.getElementById('modal-id').textContent = payment.id;
                    document.getElementById('modal-document-type').textContent = payment.document_type || 'N/A';
                    document.getElementById('modal-price').textContent = '₱' + parseFloat(payment.unit_price || 0).toFixed(2);
                    document.getElementById('modal-payment-status').textContent = payment.payment_status ? ucwords(payment.payment_status.replace('_', ' ')) : 'N/A';
                    document.getElementById('modal-payment-method').textContent = payment.payment_method ? ucwords(payment.payment_method) : 'N/A';
                    document.getElementById('modal-requested-date').textContent = payment.requested_date ? new Date(payment.requested_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A';
                    document.getElementById('modal-status').textContent = payment.status || 'N/A';
                    // Payment Details
                    document.getElementById('modal-payment-id').textContent = payment.payment_id || 'N/A';
                    document.getElementById('modal-payment-amount').textContent = payment.payment_amount ? '₱' + parseFloat(payment.payment_amount).toFixed(2) : 'N/A';
                    document.getElementById('modal-payment-date').textContent = payment.payment_date ? new Date(payment.payment_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A';
                    document.getElementById('modal-payment-description').textContent = payment.payment_description || 'N/A';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load payment details.');
            });
    });
});

function ucwords(str) {
    return str.toLowerCase().replace(/(^|\s)\w/g, letter => letter.toUpperCase());
}
</script>

<?php require 'includes/footer.php'; ?>
<?php
// Close database connection
$conn->close();
?>