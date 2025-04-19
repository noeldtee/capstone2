<?php
$page_title = "Admin Dashboard";
include('includes/header.php');

// Fetch analytics data
// Total Students (users with role 'student' or 'alumni')
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role IN ('student', 'alumni')");
$stmt->execute();
$total_students = $stmt->get_result()->fetch_assoc()['total'];

// Total Requests
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM requests");
$stmt->execute();
$total_requests = $stmt->get_result()->fetch_assoc()['total'];

// Total Pending
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM requests WHERE status = 'Pending'");
$stmt->execute();
$total_pending = $stmt->get_result()->fetch_assoc()['total'];

// Total In Process
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM requests WHERE status = 'In Process'");
$stmt->execute();
$total_in_process = $stmt->get_result()->fetch_assoc()['total'];

// Total Ready to Pickup
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM requests WHERE status = 'Ready to Pickup'");
$stmt->execute();
$total_ready_to_pickup = $stmt->get_result()->fetch_assoc()['total'];

// Fetch the 10 most recent requests (most recent first)
$stmt = $conn->prepare("SELECT r.id, r.document_type, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                               r.unit_price, r.status, r.requested_date 
                        FROM requests r 
                        JOIN users u ON r.user_id = u.id 
                        ORDER BY r.requested_date DESC 
                        LIMIT 10");
$stmt->execute();
$result = $stmt->get_result();
$recent_requests = [];
while ($row = $result->fetch_assoc()) {
    $recent_requests[] = $row;
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
        <span>Dashboard</span><br>
        <small>Welcome back! Here's an overview of today's system activity.</small>
    </div>
    <div class="page-content">
        <!-- Analytics Cards -->
        <div class="analytics">
            <div class="card">
                <div class="card-head">
                    <h2><?php echo $total_students; ?></h2>
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="card-progress">
                    <small>Total Students</small>
                </div>
            </div>
            <div class="card">
                <div class="card-head">
                    <h2><?php echo $total_requests; ?></h2>
                    <i class="fa-solid fa-file-alt"></i>
                </div>
                <div class="card-progress">
                    <small>Total Requests</small>
                </div>
            </div>
            <div class="card">
                <div class="card-head">
                    <h2><?php echo $total_pending; ?></h2>
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div class="card-progress">
                    <small>Total Pending</small>
                </div>
            </div>
            <div class="card">
                <div class="card-head">
                    <h2><?php echo $total_in_process; ?></h2>
                    <i class="fa-solid fa-spinner"></i>
                </div>
                <div class="card-progress">
                    <small>Total In Process</small>
                </div>
            </div>
            <div class="card">
                <div class="card-head">
                    <h2><?php echo $total_ready_to_pickup; ?></h2>
                    <i class="fa-solid fa-check-circle"></i>
                </div>
                <div class="card-progress">
                    <small>Ready to Pickup</small>
                </div>
            </div>
        </div>
        <!-- Records Table -->
        <div class="records table-responsive">
            <div class="record-header">
                <div class="add">
                    <span>Most Recent Requests (Last 10)</span>
                </div>
            </div>
            <div>
                <table width="100%">
                    <thead>
                        <tr>
                            <th>Document Requested</th>
                            <th>Student Name</th>
                            <th>Price</th>
                            <th>Requested Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_requests)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No recent requests found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['document_type']); ?></td>
                                    <td><?php echo htmlspecialchars($request['student_name']); ?></td>
                                    <td>₱<?php echo number_format($request['unit_price'], 2); ?></td>
                                    <td><?php echo date('F j, Y', strtotime($request['requested_date'])); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            switch (strtolower($request['status'])) {
                                                case 'pending': echo 'bg-warning'; break;
                                                case 'in process': echo 'bg-info'; break;
                                                case 'ready to pickup': echo 'bg-success'; break;
                                                case 'completed': echo 'bg-primary'; break;
                                                case 'rejected': echo 'bg-danger'; break;
                                            }
                                        ?>">
                                            <?php echo htmlspecialchars($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-primary btn-sm view-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#viewModal"
                                            data-id="<?php echo $request['id']; ?>">
                                            View Information
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Modal for Viewing Request Details -->
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

<script>
// Populate the view modal with data
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
                    document.getElementById('modal-price').textContent = '₱' + parseFloat(request.unit_price).toFixed(2);
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
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load request details.');
            });
    });
});
</script>

<?php include('includes/footer.php'); ?>