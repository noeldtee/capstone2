<?php
// Include header
$page_title = "Request History";
include('includes/header.php');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fetch the user's document requests (active, i.e., not archived)
$user_id = $_SESSION['user_id'];
$data = ['documentRequests' => [], 'archivedRequests' => []];

// Handle archiving a request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_request_id'])) {
    $request_id = $_POST['archive_request_id'];
    $stmt = $conn->prepare("UPDATE requests SET archived = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $request_id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['alert'] = ['message' => 'Request archived successfully.', 'type' => 'success'];
    } else {
        $_SESSION['alert'] = ['message' => 'Failed to archive request. Please try again.', 'type' => 'danger'];
    }
    $stmt->close();
    // Removed header('Location: history.php'); to avoid redirect
}

// Handle retrieving a request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retrieve_request_id'])) {
    $request_id = $_POST['retrieve_request_id'];
    $stmt = $conn->prepare("UPDATE requests SET archived = 0 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $request_id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['alert'] = ['message' => 'Request retrieved successfully.', 'type' => 'success'];
    } else {
        $_SESSION['alert'] = ['message' => 'Failed to retrieve request. Please try again.', 'type' => 'danger'];
    }
    $stmt->close();
    // Removed header('Location: history.php'); to avoid redirect
}

// Fetch active requests (archived = 0)
$stmt = $conn->prepare("
    SELECT r.id, r.document_type, r.requested_date, r.status, r.payment_status,
           u.firstname, u.lastname, u.email, u.number,
           u.birthdate AS student_birthdate, u.studentid AS student_id, u.year_level, u.course_id, u.section_id, r.unit_price, r.remarks AS purpose
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.user_id = ? AND r.archived = 0
    ORDER BY r.requested_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data['documentRequests'] = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch archived requests (archived = 1)
$stmt = $conn->prepare("
    SELECT r.id, r.document_type, r.requested_date, r.status, r.payment_status,
           u.firstname, u.lastname, u.email, u.number,
           u.birthdate AS student_birthdate, u.studentid AS student_id, u.year_level, u.course_id, u.section_id, r.unit_price, r.remarks AS purpose
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.user_id = ? AND r.archived = 1
    ORDER BY r.requested_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data['archivedRequests'] = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<link rel="stylesheet" href="../assets/css/user_dashboard.css">
<link rel="stylesheet" href="../assets/css/history.css">
<main>
    <?php alertMessage(); ?>
    <div class="page-header">
        <h1>Your Request History</h1>
        <small>Check the history of your document request here.</small>
    </div>
    <div class="page-content">
        <div class="mb-3 text-end">
            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#viewArchivedModal">
                View Archived Requests
            </button>
        </div>
        <!-- Document Requests Table -->
        <div class="records table-responsive">
            <table width="100%">
                <thead>
                    <tr>
                        <th>Document Requested</th>
                        <th>Price</th>
                        <th>Request Date</th>
                        <th>Status</th>
                        <th>Payment Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data['documentRequests'])): ?>
                        <?php foreach ($data['documentRequests'] as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['document_type']); ?></td>
                                <td>₱<?= htmlspecialchars(number_format($request['unit_price'], 2)); ?></td>
                                <td><?= htmlspecialchars(date('Y-m-d', strtotime($request['requested_date']))); ?></td>
                                <td><?= htmlspecialchars(ucfirst($request['status'])); ?></td>
                                <td><?= htmlspecialchars(ucfirst($request['payment_status'] ?? 'N/A')); ?></td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm me-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#viewInfoModal"
                                        data-id="<?= htmlspecialchars($request['id']); ?>"
                                        data-requested_date="<?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($request['requested_date']))); ?>"
                                        data-firstname="<?= htmlspecialchars($request['firstname']); ?>"
                                        data-lastname="<?= htmlspecialchars($request['lastname']); ?>"
                                        data-email="<?= htmlspecialchars($request['email']); ?>"
                                        data-number="<?= htmlspecialchars($request['number']); ?>"
                                        data-student_birthdate="<?= htmlspecialchars($request['student_birthdate'] ?? 'N/A'); ?>"
                                        data-student_id="<?= htmlspecialchars($request['student_id'] ?? 'N/A'); ?>"
                                        data-year_level="<?= htmlspecialchars($request['year_level'] ?? 'N/A'); ?>"
                                        data-course_id="<?= htmlspecialchars($request['course_id'] ?? 'N/A'); ?>"
                                        data-section_id="<?= htmlspecialchars($request['section_id'] ?? 'N/A'); ?>"
                                        data-document_type="<?= htmlspecialchars($request['document_type']); ?>"
                                        data-status="<?= htmlspecialchars($request['status']); ?>"
                                        data-unit_price="<?= htmlspecialchars(number_format($request['unit_price'], 2)); ?>"
                                        data-payment_status="<?= htmlspecialchars($request['payment_status'] ?? 'N/A'); ?>"
                                        data-purpose="<?= htmlspecialchars($request['purpose']); ?>">
                                        View Information
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="archive_request_id" value="<?= htmlspecialchars($request['id']); ?>">
                                        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Are you sure you want to archive this request?')">Archive</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No active document requests found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- View Information Modal -->
<div class="modal fade" id="viewInfoModal" tabindex="-1" aria-labelledby="viewInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewInfoModalLabel">Request Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input name="firstname" value="" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input name="lastname" value="" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input name="email" value="" type="email" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact Number</label>
                        <input name="number" value="" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Student ID</label>
                        <input name="student_id" value="" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Year Level</label>
                        <input name="year_level" value="" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Course</label>
                        <input name="course_id" value="" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Section</label>
                        <input name="section_id" value="" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Requested At</label>
                        <input name="requested_date" value="" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Birthdate</label>
                        <input name="student_birthdate" value="" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Document</label>
                        <input name="document_type" value="" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <input name="status" value="" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Price</label>
                        <input name="unit_price" value="" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Status</label>
                        <input name="payment_status" value="" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Purpose</label>
                        <textarea name="purpose" class="form-control" rows="3" readonly></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View Archived Requests Modal -->
<div class="modal fade" id="viewArchivedModal" tabindex="-1" aria-labelledby="viewArchivedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewArchivedModalLabel">Archived Requests</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover" width="100%">
                        <thead>
                            <tr>
                                <th>Document Requested</th>
                                <th>Price</th>
                                <th>Request Date</th>
                                <th>Status</th>
                                <th>Payment Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data['archivedRequests'])): ?>
                                <?php foreach ($data['archivedRequests'] as $request): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($request['document_type']); ?></td>
                                        <td>₱<?= htmlspecialchars(number_format($request['unit_price'], 2)); ?></td>
                                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($request['requested_date']))); ?></td>
                                        <td><?= htmlspecialchars(ucfirst($request['status'])); ?></td>
                                        <td><?= htmlspecialchars(ucfirst($request['payment_status'] ?? 'N/A')); ?></td>
                                        <td>
                                            <button
                                                type="button"
                                                class="btn btn-primary btn-sm me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewInfoModal"
                                                data-id="<?= htmlspecialchars($request['id']); ?>"
                                                data-requested_date="<?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($request['requested_date']))); ?>"
                                                data-firstname="<?= htmlspecialchars($request['firstname']); ?>"
                                                data-lastname="<?= htmlspecialchars($request['lastname']); ?>"
                                                data-email="<?= htmlspecialchars($request['email']); ?>"
                                                data-number="<?= htmlspecialchars($request['number']); ?>"
                                                data-student_birthdate="<?= htmlspecialchars($request['student_birthdate'] ?? 'N/A'); ?>"
                                                data-student_id="<?= htmlspecialchars($request['student_id'] ?? 'N/A'); ?>"
                                                data-year_level="<?= htmlspecialchars($request['year_level'] ?? 'N/A'); ?>"
                                                data-course_id="<?= htmlspecialchars($request['course_id'] ?? 'N/A'); ?>"
                                                data-section_id="<?= htmlspecialchars($request['section_id'] ?? 'N/A'); ?>"
                                                data-document_type="<?= htmlspecialchars($request['document_type']); ?>"
                                                data-status="<?= htmlspecialchars($request['status']); ?>"
                                                data-unit_price="<?= htmlspecialchars(number_format($request['unit_price'], 2)); ?>"
                                                data-payment_status="<?= htmlspecialchars($request['payment_status'] ?? 'N/A'); ?>"
                                                data-purpose="<?= htmlspecialchars($request['purpose']); ?>">
                                                View Information
                                            </button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="retrieve_request_id" value="<?= htmlspecialchars($request['id']); ?>">
                                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to retrieve this request?')">Retrieve</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No archived requests found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    const viewInfoModal = document.getElementById('viewInfoModal');
    viewInfoModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget; // Button that triggered the modal
        const fields = [
            "firstname", "lastname", "email", "number",
            "student_id", "year_level", "course_id", "section_id", "requested_date",
            "student_birthdate", "document_type", "status", "unit_price",
            "payment_status"
        ];

        // Populate input fields
        fields.forEach(field => {
            const input = viewInfoModal.querySelector(`input[name="${field}"]`);
            if (input) {
                input.value = button.getAttribute(`data-${field}`) || "N/A";
            }
        });

        // Populate textarea for purpose
        const purposeTextarea = viewInfoModal.querySelector('textarea[name="purpose"]');
        if (purposeTextarea) {
            purposeTextarea.value = button.getAttribute('data-purpose') || "N/A";
        }
    });
</script>

<?php include('includes/footer.php'); ?>