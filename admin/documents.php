<?php
$page_title = "Document Management";
require 'includes/header.php';

// Initialize variables to prevent undefined errors
$documents = [];
$total_pages = 1;
$total_documents = 0;

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;

// Fetch total number of documents using prepared statement
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM documents");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $total_documents = $result->fetch_assoc()['total'];
    $total_pages = $total_documents > 0 ? ceil($total_documents / $limit) : 1;
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $limit;
    $stmt->close();

    // Fetch documents with pagination using prepared statement
    $stmt = $conn->prepare("SELECT id, name, description, unit_price, form_needed, is_active, restrict_per_semester, requirements 
                            FROM documents 
                            ORDER BY name ASC 
                            LIMIT ? OFFSET ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['message'] = "Database error: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
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
        <span>Document Management</span><br>
        <small>View documents available for request.</small>
    </div>
    <div class="page-content">
        <!-- Records Table -->
        <div class="records table-responsive">
            <div class="record-header">
                <div class="add">
                    <span>All Documents (<?php echo $total_documents; ?> found)</span>
                    <?php if ($_SESSION['role'] === 'registrar' || $_SESSION['role'] === 'staff'): ?>
                        <button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#addModal">Add Document</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (isset($_SESSION['message']) && $_SESSION['message_type'] === 'danger'): ?>
                <p class="text-danger">Unable to display documents due to a database error.</p>
            <?php else: ?>
                <div>
                    <table class="table table-striped table-hover" width="100%">
                        <thead>
                            <tr>
                                <th>Document Name</th>
                                <th>Price</th>
                                <th>Attachment</th>
                                <th>Status</th>
                                <th>Semester Restriction</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents)): ?>
                                <tr><td colspan="6" class="text-center">No documents found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($doc['name']); ?></td>
                                        <td>₱<?php echo number_format((float)$doc['unit_price'], 2); ?></td>
                                        <td><?php echo $doc['form_needed'] == 1 ? 'Needed' : 'Not Needed'; ?></td>
                                        <td>
                                            <span class="badge <?php echo $doc['is_active'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $doc['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $doc['restrict_per_semester'] == 1 ? 'Restricted' : 'Not Restricted'; ?></td>
                                        <td>
                                            <?php if ($_SESSION['role'] === 'registrar' || $_SESSION['role'] === 'staff'): ?>
                                                <button type="button" class="btn btn-primary btn-sm edit-btn" data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?php echo $doc['id']; ?>">Edit</button>
                                            <?php endif; ?>
                                            <?php if ($_SESSION['role'] === 'registrar'): ?>
                                                <button type="button" class="btn btn-danger btn-sm delete-btn" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?php echo $doc['id']; ?>">Delete</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-3">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Add Document Modal (only for registrar and staff) -->
<?php if ($_SESSION['role'] === 'registrar' || $_SESSION['role'] === 'staff'): ?>
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">Add New Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addDocumentForm" method="POST" action="document_actions.php">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="addName" class="form-label">Document Name</label>
                        <input type="text" class="form-control" id="addName" name="name" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label for="addDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="addDescription" name="description" rows="3" maxlength="1000"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="addUnitPrice" class="form-label">Price (₱)</label>
                        <input type="number" class="form-control" id="addUnitPrice" name="unit_price" step="0.01" min="0" value="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label for="addForm" class="form-label">Attachment Required</label>
                        <select class="form-select" id="addForm" name="form_needed" required>
                            <option value="1">Needed</option>
                            <option value="0">Not Needed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addRequirements" class="form-label">Requirements (e.g., Completely Signed Clearance Form)</label>
                        <textarea class="form-control" id="addRequirements" name="requirements" rows="3" maxlength="1000" placeholder="Enter any specific requirements for this document..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="addStatus" class="form-label">Status</label>
                        <select class="form-select" id="addStatus" name="is_active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addRestrictPerSemester" class="form-label">Restrict to One Request Per Semester</label>
                        <select class="form-select" id="addRestrictPerSemester" name="restrict_per_semester" required>
                            <option value="1">Restricted</option>
                            <option value="0" selected>Not Restricted</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="addDocumentForm" class="btn btn-primary">Add Document</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Document Modal (only for registrar and staff) -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editDocumentForm" method="POST" action="document_actions.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="editId" name="id">
                    <div class="mb-3">
                        <label for="editName" class="form-label">Document Name</label>
                        <input type="text" class="form-control" id="editName" name="name" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3" maxlength="1000"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editUnitPrice" class="form-label">Price (₱)</label>
                        <input type="number" class="form-control" id="editUnitPrice" name="unit_price" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="editForm" class="form-label">Form Required</label>
                        <select class="form-select" id="editForm" name="form_needed" required>
                            <option value="1">Needed</option>
                            <option value="0">Not Needed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editRequirements" class="form-label">Requirements (e.g., Completely Signed Clearance Form)</label>
                        <textarea class="form-control" id="editRequirements" name="requirements" rows="3" maxlength="1000" placeholder="Enter any specific requirements for this document..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editStatus" class="form-label">Status</label>
                        <select class="form-select" id="editStatus" name="is_active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editRestrictPerSemester" class="form-label">Restrict to One Request Per Semester</label>
                        <select class="form-select" id="editRestrictPerSemester" name="restrict_per_semester" required>
                            <option value="1">Restricted</option>
                            <option value="0">Not Restricted</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="editDocumentForm" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<?php if ($_SESSION['role'] === 'registrar'): ?>
<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this document? This action cannot be undone.</p>
                <form id="deleteDocumentForm" method="POST" action="document_actions.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteId" name="id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                <button type="submit" form="deleteDocumentForm" class="btn btn-danger">Yes, Delete Now</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
// Reset modals on close to prevent stale data (only for registrar and staff)
<?php if ($_SESSION['role'] === 'registrar' || $_SESSION['role'] === 'staff'): ?>
document.getElementById('addModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('addDocumentForm').reset();
    document.getElementById('addUnitPrice').value = '0.00';
});

document.getElementById('editModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('editDocumentForm').reset();
});

// Populate Edit Modal
document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', function () {
        const id = this.dataset.id;
        fetch(`document_actions.php?action=get&id=${id}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success' && data.data) {
                    const doc = data.data;
                    document.getElementById('editId').value = doc.id;
                    document.getElementById('editName').value = doc.name || '';
                    document.getElementById('editDescription').value = doc.description || '';
                    document.getElementById('editUnitPrice').value = isNaN(parseFloat(doc.unit_price)) ? '0.00' : parseFloat(doc.unit_price).toFixed(2);
                    document.getElementById('editForm').value = doc.form_needed !== undefined ? doc.form_needed : '0';
                    document.getElementById('editRequirements').value = doc.requirements || '';
                    document.getElementById('editStatus').value = doc.is_active !== undefined ? doc.is_active : '1';
                    document.getElementById('editRestrictPerSemester').value = doc.restrict_per_semester !== undefined ? doc.restrict_per_semester : '0';
                } else {
                    console.error('Invalid response:', data);
                    alert('Error: ' + (data.message || 'Failed to fetch document data.'));
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Failed to load document data: ' + error.message);
            });
    });
});

<?php if ($_SESSION['role'] === 'registrar'): ?>
// Populate Delete Modal
document.querySelectorAll('.delete-btn').forEach(button => {
    button.addEventListener('click', function () {
        document.getElementById('deleteId').value = this.dataset.id;
    });
});
<?php endif; ?>
<?php endif; ?>
</script>

<?php require 'includes/footer.php'; ?>