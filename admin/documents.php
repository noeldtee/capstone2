<?php
$page_title = "Document Management";
include('includes/header.php');

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch total number of documents
$query = "SELECT COUNT(*) as total FROM documents";
$result = mysqli_query($conn, $query);
$total_documents = mysqli_fetch_assoc($result)['total'];
$total_pages = $total_documents > 0 ? ceil($total_documents / $limit) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// Fetch documents with pagination
$query = "SELECT * FROM documents LIMIT $offset, $limit";
$result = mysqli_query($conn, $query);

if (!$result) {
    $_SESSION['error'] = "Failed to fetch documents: " . mysqli_error($conn);
    header("Location: error.php");
    exit();
}

$documents = [];
while ($row = mysqli_fetch_assoc($result)) {
    $documents[] = $row;
}
?>

<link rel="stylesheet" href="../assets/css/admin_dashboard.css">
<main>
    <div class="page-header">
        <span>Document Management</span><br>
        <small>Create, edit, or delete documents available for request.</small>
    </div>
    <div class="page-content">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Records Table -->
        <div class="records table-responsive">
            <div class="record-header">
                <div class="add">
                    <span>All Documents (<?php echo $total_documents; ?> found)</span>
                    <button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#addModal">Add Document</button>
                </div>
            </div>
            <div>
                <table class="table table-striped table-hover" width="100%">
                    <thead>
                        <tr>
                            <th>Document Name</th>
                            <th>Price</th>
                            <th>Form</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                            <tr><td colspan="5" class="text-center">No documents found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doc['name']); ?></td>
                                    <td>₱<?php echo number_format($doc['price'], 2); ?></td>
                                    <td><?php echo $doc['form_needed'] == 1 ? 'Needed' : 'Not Needed'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $doc['is_active'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $doc['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm edit-btn" data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?php echo $doc['id']; ?>">Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm delete-btn" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?php echo $doc['id']; ?>">Delete</button>
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
        </div>
    </div>
</main>

<!-- Add Document Modal -->
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
                        <input type="text" class="form-control" id="addName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="addDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="addDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="addPrice" class="form-label">Price (₱)</label>
                        <input type="number" class="form-control" id="addPrice" name="price" step="0.01" value="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label for="addForm" class="form-label">Form Required</label>
                        <select class="form-select" id="addForm" name="form_needed" required>
                            <option value="1">Needed</option>
                            <option value="0">Not Needed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addStatus" class="form-label">Status</label>
                        <select class="form-select" id="addStatus" name="is_active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
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

<!-- Edit Document Modal -->
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
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editPrice" class="form-label">Price (₱)</label>
                        <input type="number" class="form-control" id="editPrice" name="price" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="editForm" class="form-label">Form Required</label>
                        <select class="form-select" id="editForm" name="form_needed" required>
                            <option value="1">Needed</option>
                            <option value="0">Not Needed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editStatus" class="form-label">Status</label>
                        <select class="form-select" id="editStatus" name="is_active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
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

<script>
    // Populate Edit Modal
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            const id = this.dataset.id;
            fetch('document_actions.php?action=get&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 200) {
                        const doc = data.data;
                        document.getElementById('editId').value = doc.id;
                        document.getElementById('editName').value = doc.name;
                        document.getElementById('editDescription').value = doc.description || '';
                        document.getElementById('editPrice').value = parseFloat(doc.price);
                        document.getElementById('editForm').value = doc.form_needed;
                        document.getElementById('editStatus').value = doc.is_active;
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching document data:', error);
                    alert('Failed to load document data.');
                });
        });
    });

    // Populate Delete Modal
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function () {
            document.getElementById('deleteId').value = this.dataset.id;
        });
    });
</script>

<?php include('includes/footer.php'); ?>