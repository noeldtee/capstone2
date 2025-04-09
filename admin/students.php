<?php
$page_title = "Users Management";
include('includes/header.php');

// Pagination, search, filter, and sort settings remain unchanged
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'full_name';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_order']) : 'ASC';

$where_clauses = ["u.role IN ('student', 'alumni')"];
$params = [];
$param_types = '';

if ($search) {
    $where_clauses[] = "(CONCAT(u.firstname, ' ', u.lastname) LIKE ? OR u.studentid LIKE ? OR u.email LIKE ? OR s.section LIKE ? OR c.name LIKE ? OR sy.year LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssssss";
}

if ($role_filter && in_array($role_filter, ['student', 'alumni'])) {
    $where_clauses[] = "u.role = ?";
    $params[] = $role_filter;
    $param_types .= "s";
}

if ($status_filter !== '' && in_array($status_filter, ['0', '1'])) {
    $where_clauses[] = "u.is_ban = ?";
    $params[] = (int)$status_filter;
    $param_types .= "i";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch school years, courses, sections remain unchanged
$stmt = $conn->prepare("SELECT * FROM school_years");
$stmt->execute();
$result = $stmt->get_result();
$school_years = [];
while ($row = $result->fetch_assoc()) {
    $school_years[$row['id']] = $row;
}
$stmt->close();

$stmt = $conn->prepare("SELECT s.*, sy.year AS school_year, c.name AS course_name FROM sections s JOIN school_years sy ON s.school_year_id = sy.id JOIN courses c ON s.course_id = c.id");
$stmt->execute();
$result = $stmt->get_result();
$sections = [];
while ($row = $result->fetch_assoc()) {
    $sections[$row['id']] = $row;
}
$stmt->close();

// Fetch total number of users remains unchanged
$query = "SELECT COUNT(*) as total FROM users u LEFT JOIN sections s ON u.section_id = s.id LEFT JOIN courses c ON u.course_id = c.id LEFT JOIN school_years sy ON u.year_id = sy.id $where_sql";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_users = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = $total_users > 0 ? ceil($total_users / $limit) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// Fetch users query updated to include profile
$query = "SELECT u.*, CONCAT(u.firstname, ' ', u.lastname) AS full_name, s.section AS section_name, c.name AS course_name, sy.year AS school_year 
          FROM users u LEFT JOIN sections s ON u.section_id = s.id LEFT JOIN courses c ON u.course_id = c.id LEFT JOIN school_years sy ON u.year_id = sy.id 
          $where_sql ORDER BY $sort_by $sort_order LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

$year_levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
?>

<link rel="stylesheet" href="../assets/css/admin_dashboard.css">
<main>
    <div class="page-header">
        <span>Users Management</span><br>
        <small>Add, edit, or delete student and alumni records.</small>
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

        <!-- Filter Form remains unchanged -->
        <div class="mb-3">
            <form method="GET" action="students.php" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="alumni" <?php echo $role_filter === 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Active</option>
                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="students.php" class="btn btn-secondary btn-sm">Clear</a>
                </div>
            </form>
        </div>

        <!-- Records Table (removed phone number, added profile column) -->
        <div class="records table-responsive">
            <div class="record-header">
                <div class="add">
                    <span>All Users (<?php echo $total_users; ?> found)</span>
                    <button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#addModal">Add User</button>
                </div>
            </div>
            <div>
                <table class="table table-striped table-hover" width="100%">
                    <thead>
                        <tr>
                            <th>Profile</th>
                            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'full_name', 'sort_order' => $sort_by === 'full_name' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">Name <?php if ($sort_by === 'full_name') echo $sort_order === 'ASC' ? '↑' : '↓'; ?></a></th>
                            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'studentid', 'sort_order' => $sort_by === 'studentid' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">Student ID <?php if ($sort_by === 'studentid') echo $sort_order === 'ASC' ? '↑' : '↓'; ?></a></th>
                            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'email', 'sort_order' => $sort_by === 'email' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">Email <?php if ($sort_by === 'email') echo $sort_order === 'ASC' ? '↑' : '↓'; ?></a></th>
                            <th>Role</th>
                            <th>Course</th>
                            <th>Section</th>
                            <th>School Year</th>
                            <th>Year Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="11" class="text-center">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><img src="<?php echo htmlspecialchars($user['profile'] ?? '../assets/images/default_profile.png'); ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%;"></td>
                                    <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['studentid']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td><?php echo htmlspecialchars($user['course_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['section_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['school_year'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['year_level']); ?></td>
                                    <td><span class="badge <?php echo $user['is_ban'] == 0 ? 'bg-success' : 'bg-danger'; ?>"><?php echo $user['is_ban'] == 0 ? 'Active' : 'Inactive'; ?></span></td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm edit-user-btn" data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?php echo $user['id']; ?>">Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm delete-user-btn" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?php echo $user['id']; ?>">Delete</button>
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

<!-- Add User Modal (removed phone number, added hidden profile) -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm" method="POST" action="student_actions.php">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="profile" value="../assets/images/default_profile.png">
                    <div class="mb-3">
                        <label for="addStudentId" class="form-label">Student/Alumni ID</label>
                        <input type="text" class="form-control" id="addStudentId" name="studentid" required placeholder="e.g., STU001">
                    </div>
                    <div class="mb-3">
                        <label for="addFirstName" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="addFirstName" name="firstname" required>
                    </div>
                    <div class="mb-3">
                        <label for="addLastName" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="addLastName" name="lastname" required>
                    </div>
                    <div class="mb-3">
                        <label for="addEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="addEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="addPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="addPassword" name="password" value="@Student01" required>
                    </div>
                    <div class="mb-3">
                        <label for="addCourse" class="form-label">Course</label>
                        <select class="form-select" id="addCourse" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $id => $course): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addSection" class="form-label">Section</label>
                        <select class="form-select" id="addSection" name="section_id" required>
                            <option value="">Select Section</option>
                            <?php foreach ($sections as $id => $section): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($section['section'] . ' (' . $section['course_name'] . ', ' . $section['school_year'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addSchoolYear" class="form-label">School Year</label>
                        <select class="form-select" id="addSchoolYear" name="year_id" required>
                            <option value="">Select School Year</option>
                            <?php foreach ($school_years as $id => $sy): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($sy['year']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addYearLevel" class="form-label">Year Level</label>
                        <select class="form-select" id="addYearLevel" name="year_level" required>
                            <option value="">Select Year Level</option>
                            <?php foreach ($year_levels as $level): ?>
                                <option value="<?php echo $level; ?>"><?php echo $level; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addRole" class="form-label">Role</label>
                        <select class="form-select" id="addRole" name="role" required>
                            <option value="">Select Role</option>
                            <option value="student">Student</option>
                            <option value="alumni">Alumni</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addIsBan" class="form-label">Status</label>
                        <select class="form-select" id="addIsBan" name="is_ban" required>
                            <option value="0">Active</option>
                            <option value="1">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="addTerms" name="terms" value="1" checked required>
                        <label for="addTerms" class="form-check-label">Accept Terms of Service</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="addUserForm" class="btn btn-primary">Add User</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal (removed phone number, added hidden profile) -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" method="POST" action="student_actions.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="editId" name="id">
                    <input type="hidden" id="editProfile" name="profile">
                    <div class="mb-3">
                        <label for="editStudentId" class="form-label">Student/Alumni ID</label>
                        <input type="text" class="form-control" id="editStudentId" name="studentid" required>
                    </div>
                    <div class="mb-3">
                        <label for="editFirstName" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="editFirstName" name="firstname" required>
                    </div>
                    <div class="mb-3">
                        <label for="editLastName" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="editLastName" name="lastname" required>
                    </div>
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="editEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPassword" class="form-label">Password (Leave blank to keep unchanged)</label>
                        <input type="password" class="form-control" id="editPassword" name="password">
                    </div>
                    <div class="mb-3">
                        <label for="editCourse" class="form-label">Course</label>
                        <select class="form-select" id="editCourse" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $id => $course): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editSection" class="form-label">Section</label>
                        <select class="form-select" id="editSection" name="section_id" required>
                            <option value="">Select Section</option>
                            <?php foreach ($sections as $id => $section): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($section['section'] . ' (' . $section['course_name'] . ', ' . $section['school_year'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editSchoolYear" class="form-label">School Year</label>
                        <select class="form-select" id="editSchoolYear" name="year_id" required>
                            <option value="">Select School Year</option>
                            <?php foreach ($school_years as $id => $sy): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($sy['year']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editYearLevel" class="form-label">Year Level</label>
                        <select class="form-select" id="editYearLevel" name="year_level" required>
                            <option value="">Select Year Level</option>
                            <?php foreach ($year_levels as $level): ?>
                                <option value="<?php echo $level; ?>"><?php echo $level; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editRole" class="form-label">Role</label>
                        <select class="form-select" id="editRole" name="role" required>
                            <option value="">Select Role</option>
                            <option value="student">Student</option>
                            <option value="alumni">Alumni</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editIsBan" class="form-label">Status</label>
                        <select class="form-select" id="editIsBan" name="is_ban" required>
                            <option value="0">Active</option>
                            <option value="1">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="editTerms" name="terms" value="1">
                        <label for="editTerms" class="form-check-label">Accept Terms of Service</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="editUserForm" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal remains unchanged -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                <form id="deleteUserForm" method="POST" action="student_actions.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteId" name="id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                <button type="submit" form="deleteUserForm" class="btn btn-danger">Yes, Delete Now</button>
            </div>
        </div>
    </div>
</div>

<script>
// Populate Edit User Modal (removed phone number, added profile)
document.querySelectorAll('.edit-user-btn').forEach(button => {
    button.addEventListener('click', function () {
        const modal = document.getElementById('editModal');
        const modalBody = modal.querySelector('.modal-body');
        const form = document.getElementById('editUserForm');

        const spinner = document.createElement('div');
        spinner.className = 'text-center';
        spinner.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>';

        form.style.display = 'none';
        modalBody.appendChild(spinner);

        const id = this.dataset.id;
        fetch('student_actions.php?action=get&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.status === 200) {
                    const user = data.data;
                    document.getElementById('editId').value = user.id;
                    document.getElementById('editProfile').value = user.profile || '../assets/images/default_profile.png';
                    document.getElementById('editStudentId').value = user.studentid;
                    document.getElementById('editFirstName').value = user.firstname;
                    document.getElementById('editLastName').value = user.lastname;
                    document.getElementById('editEmail').value = user.email;
                    document.getElementById('editCourse').value = user.course_id || '';
                    document.getElementById('editSection').value = user.section_id || '';
                    document.getElementById('editSchoolYear').value = user.year_id || '';
                    document.getElementById('editYearLevel').value = user.year_level || '';
                    document.getElementById('editRole').value = user.role;
                    document.getElementById('editIsBan').value = user.is_ban;
                    document.getElementById('editTerms').checked = user.terms == 1;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching user data:', error);
                alert('Failed to load user data: ' + error.message);
            })
            .finally(() => {
                modalBody.removeChild(spinner);
                form.style.display = 'block';
            });
    });
});

// Populate Delete User Modal remains unchanged
document.querySelectorAll('.delete-user-btn').forEach(button => {
    button.addEventListener('click', function () {
        const id = this.dataset.id;
        document.getElementById('deleteId').value = id;
    });
});
</script>

<?php include('includes/footer.php'); ?>