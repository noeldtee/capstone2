<?php
$page_title = "Student Dashboard";
include('includes/header.php');

// Ensure the user is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    redirect('index.php', 'Please log in as a student to access the dashboard.', 'danger');
    exit;
}

// Fetch student details
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch document request statistics
$stmt = $conn->prepare("SELECT COUNT(*) as totalRequested FROM requests WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$data['totalRequested'] = $stmt->get_result()->fetch_assoc()['totalRequested'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as totalPending FROM requests WHERE user_id = ? AND status = 'Pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$data['totalPending'] = $stmt->get_result()->fetch_assoc()['totalPending'];
$stmt->close();

// Fetch recent document requests with joined data
$stmt = $conn->prepare("
    SELECT r.*, c.name as course_name, s.section as section_name, sy.year as school_year
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN courses c ON u.course_id = c.id
    LEFT JOIN sections s ON u.section_id = s.id
    LEFT JOIN school_years sy ON u.year_id = sy.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$data['recentRequests'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<link rel="stylesheet" href="../assets/css/user_dashboard.css">
<main>
    <?php alertMessage(); ?>
    <div class="page-header">
        <h1>Dashboard</h1>
        <small>Welcome back, <?= htmlspecialchars($user['firstname']); ?>! Here's an overview of your activity.</small>
    </div>
    <div class="page-content">
        <!-- Analytics Cards -->
        <div class="analytics">
            <div class="card">
                <div class="card-head">
                    <h2><?= $data['totalRequested'] ?? 0; ?></h2>
                    <i class="fa-solid fa-file-alt"></i>
                </div>
                <div class="card-progress">
                    <small>Total Documents Requested</small>
                    <h6>This is the total number of documents you've requested so far.</h6>
                </div>
            </div>
            <div class="card">
                <div class="card-head">
                    <h2><?= $data['totalPending'] ?? 0; ?></h2>
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div class="card-progress">
                    <small>Total Documents Pending</small>
                    <h6>Documents that are still being processed or reviewed.</h6>
                </div>
            </div>
            <div class="card2">
                <small>Click Below to Get Started on Your Document Request!</small>
                <div class="card-head2">
                    <a href="request_document.php">Request a Document</a>
                </div>
            </div>
        </div>

        <!-- Records Table -->
        <div class="records table-responsive">
            <div class="record-header">
                <div class="add">
                    <span>Most Recent Requested</span>
                </div>
            </div>
            <div>
                <table width="100%">
                    <thead>
                        <tr>
                            <th>Document Requested</th>
                            <th>Price</th>
                            <th>Requested Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data['recentRequests'])): ?>
                            <?php foreach ($data['recentRequests'] as $request): ?>
                                <tr>
                                    <td><?= htmlspecialchars($request['document_type']); ?></td>
                                    <td>₱<?= htmlspecialchars($request['price']); ?></td>
                                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($request['requested_date']))); ?></td>
                                    <td>
                                        <span class="badge bg-<?= $request['status'] === 'Pending' ? 'warning' : ($request['status'] === 'Approved' ? 'success' : 'danger'); ?>">
                                            <?= htmlspecialchars(ucfirst($request['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-primary btn-sm view-details-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#requestDetailsModal"
                                            data-id="<?= htmlspecialchars($request['id']); ?>"
                                            data-requested_at="<?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($request['requested_date']))); ?>"
                                            data-firstname="<?= htmlspecialchars($user['firstname'] ?? ''); ?>"
                                            data-lastname="<?= htmlspecialchars($user['lastname'] ?? ''); ?>"
                                            data-email="<?= htmlspecialchars($user['email'] ?? ''); ?>"
                                            data-number="<?= htmlspecialchars($user['number'] ?? ''); ?>"
                                            data-birthdate="<?= htmlspecialchars($user['birthdate'] ?? ''); ?>"
                                            data-student_id="<?= htmlspecialchars($user['studentid'] ?? ''); ?>"
                                            data-year_level="<?= htmlspecialchars($user['year_level'] ?? ''); ?>"
                                            data-course="<?= htmlspecialchars($request['course_name'] ?? 'N/A'); ?>"
                                            data-section="<?= htmlspecialchars($request['section_name'] ?? 'N/A'); ?>"
                                            data-school_year="<?= htmlspecialchars($request['school_year'] ?? 'N/A'); ?>"
                                            data-document_type="<?= htmlspecialchars($request['document_type'] ?? ''); ?>"
                                            data-status="<?= htmlspecialchars($request['status'] ?? ''); ?>"
                                            data-price="<?= htmlspecialchars($request['price'] ?? ''); ?>"
                                            data-payment_status="N/A"
                                            data-remarks="<?= htmlspecialchars($request['remarks'] ?? ''); ?>"
                                            data-pickup_date="<?= htmlspecialchars($request['updated_at'] ? date('Y-m-d H:i:s', strtotime($request['updated_at'])) : 'N/A'); ?>">
                                            View Information
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">No recent requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Information Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestDetailsModalLabel">Document Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input name="firstname" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input name="lastname" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input name="email" type="email" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact Number</label>
                        <input name="number" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Student ID</label>
                        <input name="student_id" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Year Level</label>
                        <input name="year_level" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Course</label>
                        <input name="course" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Section</label>
                        <input name="section" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">School Year</label>
                        <input name="school_year" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Requested At</label>
                        <input name="requested_at" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Birthdate</label>
                        <input name="birthdate" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Document</label>
                        <input name="document_type" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <input name="status" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Price</label>
                        <input name="price" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Payment Status</label>
                        <input name="payment_status" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pickup Date</label>
                        <input name="pickup_date" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="col-md">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Populate modal with data when the "View Information" button is clicked
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('requestDetailsModal');
    if (!modal) {
        console.error('Modal element with ID "requestDetailsModal" not found.');
        return;
    }

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; // Button that triggered the modal
        if (!button) {
            console.error('Button that triggered the modal not found.');
            return;
        }

        const modalBody = modal.querySelector('.modal-body');
        if (!modalBody) {
            console.error('Modal body not found.');
            return;
        }

        // Populate the modal fields with data from the button's data attributes
        modalBody.querySelector('input[name="firstname"]').value = button.getAttribute('data-firstname') || 'N/A';
        modalBody.querySelector('input[name="lastname"]').value = button.getAttribute('data-lastname') || 'N/A';
        modalBody.querySelector('input[name="email"]').value = button.getAttribute('data-email') || 'N/A';
        modalBody.querySelector('input[name="number"]').value = button.getAttribute('data-number') || 'N/A';
        modalBody.querySelector('input[name="student_id"]').value = button.getAttribute('data-student_id') || 'N/A';
        modalBody.querySelector('input[name="year_level"]').value = button.getAttribute('data-year_level') || 'N/A';
        modalBody.querySelector('input[name="course"]').value = button.getAttribute('data-course') || 'N/A';
        modalBody.querySelector('input[name="section"]').value = button.getAttribute('data-section') || 'N/A';
        modalBody.querySelector('input[name="school_year"]').value = button.getAttribute('data-school_year') || 'N/A';
        modalBody.querySelector('input[name="requested_at"]').value = button.getAttribute('data-requested_at') || 'N/A';
        modalBody.querySelector('input[name="birthdate"]').value = button.getAttribute('data-birthdate') || 'N/A';
        modalBody.querySelector('input[name="document_type"]').value = button.getAttribute('data-document_type') || 'N/A';
        modalBody.querySelector('input[name="status"]').value = button.getAttribute('data-status') || 'N/A';
        modalBody.querySelector('input[name="price"]').value = button.getAttribute('data-price') || 'N/A';
        modalBody.querySelector('input[name="payment_status"]').value = button.getAttribute('data-payment_status') || 'N/A';
        modalBody.querySelector('input[name="pickup_date"]').value = button.getAttribute('data-pickup_date') || 'N/A';
        modalBody.querySelector('textarea[name="remarks"]').value = button.getAttribute('data-remarks') || 'N/A';
    });
});
</script>

<?php include('includes/footer.php'); ?>