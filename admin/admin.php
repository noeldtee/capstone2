<?php
$page_title = "Admin Management";
include('includes/header.php');

// Pagination, search, filter, and sort settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'full_name';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_order']) : 'ASC';

$where_clauses = ["role IN ('admin', 'registrar', 'cashier')"];
$params = [];
$param_types = '';

if ($search) {
    $where_clauses[] = "(CONCAT(firstname, ' ', lastname) LIKE ? OR email LIKE ? OR number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= "sss";
}

if ($role_filter && in_array($role_filter, ['admin', 'registrar', 'cashier'])) {
    $where_clauses[] = "role = ?";
    $params[] = $role_filter;
    $param_types .= "s";
}

if ($status_filter !== '' && in_array($status_filter, ['0', '1'])) {
    $where_clauses[] = "is_ban = ?";
    $params[] = (int)$status_filter;
    $param_types .= "i";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch total number of admins
$query = "SELECT COUNT(*) as total FROM users $where_sql";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_admins = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = $total_admins > 0 ? ceil($total_admins / $limit) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// Fetch admins
$query = "SELECT *, CONCAT(firstname, ' ', lastname) AS full_name FROM users $where_sql ORDER BY $sort_by $sort_order LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$admins = [];
while ($row = $result->fetch_assoc()) {
    $admins[] = $row;
}
$stmt->close();
?>

<link rel="stylesheet" href="../assets/css/admin_dashboard.css">
<main>
    <div class="page-header">
        <span>Admin Management</span><br>
        <small>Add, edit, or delete administrative staff accounts.</small>
    </div>
    <div class="page-content">
        <!-- Display Messages -->
        <?php
        // Convert $_SESSION['error'] to $_SESSION['message'] for consistency
        if (isset($_SESSION['error'])) {
            $_SESSION['message'] = $_SESSION['error'];
            $_SESSION['message_type'] = 'danger';
            unset($_SESSION['error']);
        }
        ?>
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_SESSION['message_type']); ?> alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x" style="z-index: 1050; margin-top: 20px;" role="alert">
                <?php echo htmlspecialchars($_SESSION['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="mb-3">
            <form method="GET" action="admin.php" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search admins..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="registrar" <?php echo $role_filter === 'registrar' ? 'selected' : ''; ?>>Registrar</option>
                        <option value="cashier" <?php echo $role_filter === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Active</option>
                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Banned</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="admin.php" class="btn btn-secondary btn-sm">Clear</a>
                </div>
            </form>
        </div>

        <!-- Records Table -->
        <div class="records table-responsive">
            <div class="record-header">
                <div class="add">
                    <span>All Admins (<?php echo $total_admins; ?> found)</span>
                    <button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#addModal">Add Admin</button>
                </div>
            </div>
            <div>
                <table class="table table-striped table-hover" width="100%">
                    <thead>
                        <tr>
                            <th>Profile</th>
                            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'full_name', 'sort_order' => $sort_by === 'full_name' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">Name <?php if ($sort_by === 'full_name') echo $sort_order === 'ASC' ? '↑' : '↓'; ?></a></th>
                            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'email', 'sort_order' => $sort_by === 'email' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">Email <?php if ($sort_by === 'email') echo $sort_order === 'ASC' ? '↑' : '↓'; ?></a></th>
                            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'number', 'sort_order' => $sort_by === 'number' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">Phone Number <?php if ($sort_by === 'number') echo $sort_order === 'ASC' ? '↑' : '↓'; ?></a></th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($admins)): ?>
                            <tr><td colspan="7" class="text-center">No admins found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><img src="<?php echo htmlspecialchars($admin['profile'] ?? '../assets/images/default_profile.png'); ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;"></td>
                                    <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['number']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($admin['role'])); ?></td>
                                    <td><span class="badge <?php echo $admin['is_ban'] == 0 ? 'bg-success' : 'bg-danger'; ?>"><?php echo $admin['is_ban'] == 0 ? 'Active' : 'Banned'; ?></span></td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm edit-btn" data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?php echo $admin['id']; ?>">Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm delete-btn" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?php echo $admin['id']; ?>">Delete</button>
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
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a></li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Add Admin Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">Add New Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addAdminForm" method="POST" action="admin_actions.php">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="profile" value="../assets/images/default_profile.png">
                    <div class="mb-3">
                        <label for="addFirstname" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="addFirstname" name="firstname" required>
                    </div>
                    <div class="mb-3">
                        <label for="addLastname" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="addLastname" name="lastname" required>
                    </div>
                    <div class="mb-3">
                        <label for="addEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="addEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="addNumber" class="form-label">Phone Number (must start with 09)</label>
                        <input type="text" class="form-control" id="addNumber" name="number" required pattern="^09[0-9]{9}$" placeholder="e.g., 09123456789" title="Phone number must start with 09 followed by 9 digits">
                    </div>
                    <div class="mb-3">
                        <label for="addPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="addPassword" name="password" value="@Admin01" required>
                    </div>
                    <div class="mb-3">
                        <label for="addRole" class="form-label">Role</label>
                        <select class="form-select" id="addRole" name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="registrar">Registrar</option>
                            <option value="cashier">Cashier</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addIsBan" class="form-label">Status</label>
                        <select class="form-select" id="addIsBan" name="is_ban" required>
                            <option value="0">Active</option>
                            <option value="1">Banned</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="addAdminForm" class="btn btn-primary">Add Admin</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editAdminForm" method="POST" action="admin_actions.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="editId" name="id">
                    <input type="hidden" id="editProfile" name="profile">
                    <div class="mb-3">
                        <label for="editFirstname" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="editFirstname" name="firstname" required>
                    </div>
                    <div class="mb-3">
                        <label for="editLastname" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="editLastname" name="lastname" required>
                    </div>
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="editEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="editNumber" class="form-label">Phone Number (must start with 09)</label>
                        <input type="text" class="form-control" id="editNumber" name="number" required pattern="^09[0-9]{9}$" placeholder="e.g., 09123456789" title="Phone number must start with 09 followed by 9 digits">
                    </div>
                    <div class="mb-3">
                        <label for="editPassword" class="form-label">Password (Leave blank to keep unchanged)</label>
                        <input type="password" class="form-control" id="editPassword" name="password">
                    </div>
                    <div class="mb-3">
                        <label for="editRole" class="form-label">Role</label>
                        <select class="form-select" id="editRole" name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="registrar">Registrar</option>
                            <option value="cashier">Cashier</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editIsBan" class="form-label">Status</label>
                        <select class="form-select" id="editIsBan" name="is_ban" required>
                            <option value="0">Active</option>
                            <option value="1">Banned</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="editAdminForm" class="btn btn-primary">Save Changes</button>
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
                <p>Are you sure you want to delete this admin? This action cannot be undone.</p>
                <form id="deleteAdminForm" method="POST" action="admin_actions.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteId" name="id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                <button type="submit" form="deleteAdminForm" class="btn btn-danger">Yes, Delete Now</button>
            </div>
        </div>
    </div>
</div>

<script>
// Populate Edit Modal
document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', function () {
        const modal = document.getElementById('editModal');
        const modalBody = modal.querySelector('.modal-body');
        const form = document.getElementById('editAdminForm');

        const spinner = document.createElement('div');
        spinner.className = 'text-center';
        spinner.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>';

        form.style.display = 'none';
        modalBody.appendChild(spinner);

        const id = this.dataset.id;
        fetch('admin_actions.php?action=get&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.status === 200) {
                    const admin = data.data;
                    document.getElementById('editId').value = admin.id;
                    document.getElementById('editProfile').value = admin.profile || '../assets/images/default_profile.png';
                    document.getElementById('editFirstname').value = admin.firstname;
                    document.getElementById('editLastname').value = admin.lastname;
                    document.getElementById('editEmail').value = admin.email;
                    document.getElementById('editNumber').value = admin.number;
                    document.getElementById('editRole').value = admin.role;
                    document.getElementById('editIsBan').value = admin.is_ban;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching admin data:', error);
                alert('Failed to load admin data: ' + error.message);
            })
            .finally(() => {
                modalBody.removeChild(spinner);
                form.style.display = 'block';
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