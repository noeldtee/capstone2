<?php
$page_title = "Academic Management";
include('includes/header.php');

// Restrict to authenticated admins (registrar)
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['admin'])) {
    redirect('../index.php', 'Please log in as an admin to access this page.', 'warning');
    exit();
}

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$school_year_filter = isset($_GET['school_year']) ? trim($_GET['school_year']) : '';
$course_filter = isset($_GET['course']) ? trim($_GET['course']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build the WHERE clause for filtering sections
$where_clauses = [];
$params = [];
$param_types = '';

if ($school_year_filter) {
    $where_clauses[] = "s.school_year_id = ?";
    $params[] = (int)$school_year_filter;
    $param_types .= "i";
}

if ($course_filter) {
    $where_clauses[] = "s.course_id = ?";
    $params[] = (int)$course_filter;
    $param_types .= "i";
}

if ($status_filter !== '' && in_array($status_filter, ['0', '1'])) {
    $where_clauses[] = "s.is_active = ?";
    $params[] = (int)$status_filter;
    $param_types .= "i";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch school years
$query = "SELECT * FROM school_years";
$result = mysqli_query($conn, $query);
$school_years = [];
while ($row = mysqli_fetch_assoc($result)) {
    $school_years[$row['id']] = $row;
}

// Fetch courses (without department)
$query = "SELECT * FROM courses";
$result = mysqli_query($conn, $query);
$courses = [];
while ($row = mysqli_fetch_assoc($result)) {
    $courses[$row['id']] = $row;
}

// Year levels (static, as defined in the database)
$year_levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

// Fetch sections with joined school years and courses
$query = "SELECT s.*, sy.year AS school_year, c.name AS course_name 
          FROM sections s 
          JOIN school_years sy ON s.school_year_id = sy.id 
          JOIN courses c ON s.course_id = c.id 
          $where_sql";
$stmt = $conn->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = "Failed to prepare query for sections: " . $conn->error;
    $sections = [];
} else {
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
    $stmt->close();
}
?>

<link rel="stylesheet" href="../assets/css/admin_dashboard.css">
<main>
    <div class="page-header">
        <span>Academic Management</span><br>
        <small>Manage sections, courses, and school years for student organization.</small>
    </div>
    <div class="page-content">
        <!-- Display Success/Error Messages -->
        <?php alertMessage(); ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Management Options (Courses and School Years only) -->
        <div class="mb-3">
            <button type="button" class="btn btn-info btn-sm me-2" data-bs-toggle="modal" data-bs-target="#viewCoursesModal">View Courses</button>
            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewSchoolYearsModal">View School Years</button>
        </div>

        <!-- Filter Form (Search + Filters) -->
        <div class="mb-3">
            <form method="GET" action="sections.php" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search sections..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select name="school_year" class="form-select form-select-sm">
                        <option value="">All School Years</option>
                        <?php foreach ($school_years as $id => $sy): ?>
                            <option value="<?php echo $id; ?>" <?php echo $school_year_filter == $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sy['year']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="course" class="form-select form-select-sm">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $id => $course): ?>
                            <option value="<?php echo $id; ?>" <?php echo $course_filter == $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="sections.php" class="btn btn-secondary btn-sm">Clear</a>
                </div>
            </form>
        </div>

        <!-- Sections Table -->
        <div class="records table-responsive">
            <div class="record-header">
                <div class="add">
                    <span>All Sections (<?php echo count($sections); ?> found)</span>
                </div>
                <div class="action">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSectionModal">Add Section</button>
                </div>
            </div>
            <div>
                <table width="100%">
                    <thead>
                        <tr>
                            <th>School Year</th>
                            <th>Course</th>
                            <th>Year Level</th>
                            <th>Section</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections as $section): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($section['school_year']); ?></td>
                                <td><?php echo htmlspecialchars($section['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($section['year_level']); ?></td>
                                <td><?php echo htmlspecialchars($section['section']); ?></td>
                                <td>
                                    <span class="badge <?php echo $section['is_active'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $section['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm edit-section-btn" data-bs-toggle="modal" data-bs-target="#editSectionModal" data-id="<?php echo $section['id']; ?>">Edit</button>
                                    <button type="button" class="btn btn-danger btn-sm delete-section-btn" data-bs-toggle="modal" data-bs-target="#deleteSectionModal" data-id="<?php echo $section['id']; ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sections)): ?>
                            <tr><td colspan="6" class="text-center">No sections found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- View Courses Modal -->
<div class="modal fade" id="viewCoursesModal" tabindex="-1" aria-labelledby="viewCoursesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewCoursesModalLabel">Courses</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">Add Course</button>
                </div>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Course Name</th>
                            <th>Course Code</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['name']); ?></td>
                                <td><?php echo htmlspecialchars($course['code']); ?></td>
                                <td>
                                    <span class="badge <?php echo $course['is_active'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $course['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm edit-course-btn" data-bs-toggle="modal" data-bs-target="#editCourseModal" data-id="<?php echo $course['id']; ?>">Edit</button>
                                    <button type="button" class="btn btn-danger btn-sm delete-course-btn" data-bs-toggle="modal" data-bs-target="#deleteCourseModal" data-id="<?php echo $course['id']; ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($courses)): ?>
                            <tr><td colspan="4" class="text-center">No courses found.</td></tr>
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

<!-- View School Years Modal -->
<div class="modal fade" id="viewSchoolYearsModal" tabindex="-1" aria-labelledby="viewSchoolYearsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewSchoolYearsModalLabel">School Years</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSchoolYearModal">Add School Year</button>
                </div>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>School Year</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($school_years as $school_year): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($school_year['year']); ?></td>
                                <td>
                                    <span class="badge <?php echo $school_year['is_active'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $school_year['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm edit-school-year-btn" data-bs-toggle="modal" data-bs-target="#editSchoolYearModal" data-id="<?php echo $school_year['id']; ?>">Edit</button>
                                    <button type="button" class="btn btn-danger btn-sm delete-school-year-btn" data-bs-toggle="modal" data-bs-target="#deleteSchoolYearModal" data-id="<?php echo $school_year['id']; ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($school_years)): ?>
                            <tr><td colspan="3" class="text-center">No school years found.</td></tr>
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

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1" aria-labelledby="addSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSectionModalLabel">Add New Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addSectionForm" method="POST" action="section_actions.php">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="addSectionSchoolYear" class="form-label">School Year</label>
                        <select class="form-select" id="addSectionSchoolYear" name="school_year_id" required>
                            <option value="">Select School Year</option>
                            <?php foreach ($school_years as $id => $sy): ?>
                                <option value="<?php echo $id; ?>"><?php echo $sy['year']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addSectionCourse" class="form-label">Course</label>
                        <select class="form-select" id="addSectionCourse" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $id => $course): ?>
                                <option value="<?php echo $id; ?>"><?php echo $course['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addSectionYearLevel" class="form-label">Year Level</label>
                        <select class="form-select" id="addSectionYearLevel" name="year_level" required>
                            <option value="">Select Year Level</option>
                            <?php foreach ($year_levels as $level): ?>
                                <option value="<?php echo $level; ?>"><?php echo $level; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addSectionSection" class="form-label">Section</label>
                        <input type="text" class="form-control" id="addSectionSection" name="section" required placeholder="e.g., Section A">
                    </div>
                    <div class="mb-3">
                        <label for="addSectionStatus" class="form-label">Status</label>
                        <select class="form-select" id="addSectionStatus" name="is_active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="addSectionForm" class="btn btn-primary">Add Section</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSectionModalLabel">Edit Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editSectionForm" method="POST" action="section_actions.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="editSectionId" name="id">
                    <div class="mb-3">
                        <label for="editSectionSchoolYear" class="form-label">School Year</label>
                        <select class="form-select" id="editSectionSchoolYear" name="school_year_id" required>
                            <option value="">Select School Year</option>
                            <?php foreach ($school_years as $id => $sy): ?>
                                <option value="<?php echo $id; ?>"><?php echo $sy['year']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editSectionCourse" class="form-label">Course</label>
                        <select class="form-select" id="editSectionCourse" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $id => $course): ?>
                                <option value="<?php echo $id; ?>"><?php echo $course['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editSectionYearLevel" class="form-label">Year Level</label>
                        <select class="form-select" id="editSectionYearLevel" name="year_level" required>
                            <option value="">Select Year Level</option>
                            <?php foreach ($year_levels as $level): ?>
                                <option value="<?php echo $level; ?>"><?php echo $level; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editSectionSection" class="form-label">Section</label>
                        <input type="text" class="form-control" id="editSectionSection" name="section" required>
                    </div>
                    <div class="mb-3">
                        <label for="editSectionStatus" class="form-label">Status</label>
                        <select class="form-select" id="editSectionStatus" name="is_active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="editSectionForm" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Section Modal -->
<div class="modal fade" id="deleteSectionModal" tabindex="-1" aria-labelledby="deleteSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteSectionModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this section? This action cannot be undone.</p>
                <form id="deleteSectionForm" method="POST" action="section_actions.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteSectionId" name="id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                <button type="submit" form="deleteSectionForm" class="btn btn-danger">Yes, Delete Now</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCourseModalLabel">Add New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addCourseForm" method="POST" action="course_actions.php">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="addCourseName" class="form-label">Course Name</label>
                        <input type="text" class="form-control" id="addCourseName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="addCourseCode" class="form-label">Course Code</label>
                        <input type="text" class="form-control" id="addCourseCode" name="code" required>
                    </div>
                    <div class="mb-3">
                        <label for="addCourseDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="addCourseDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="addCourseStatus" class="form-label">Status</label>
                        <select class="form-select" id="addCourseStatus" name="is_active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="addCourseForm" class="btn btn-primary">Add Course</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1" aria-labelledby="editCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCourseModalLabel">Edit Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editCourseForm" method="POST" action="course_actions.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="editCourseId" name="id">
                    <div class="mb-3">
                        <label for="editCourseName" class="form-label">Course Name</label>
                        <input type="text" class="form-control" id="editCourseName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editCourseCode" class="form-label">Course Code</label>
                        <input type="text" class="form-control" id="editCourseCode" name="code" required>
                    </div>
                    <div class="mb-3">
                        <label for="editCourseDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editCourseDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editCourseStatus" class="form-label">Status</label>
                        <select class="form-select" id="editCourseStatus" name="is_active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="editCourseForm" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Course Modal -->
<div class="modal fade" id="deleteCourseModal" tabindex="-1" aria-labelledby="deleteCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCourseModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this course? This action cannot be undone.</p>
                <form id="deleteCourseForm" method="POST" action="course_actions.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteCourseId" name="id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                <button type="submit" form="deleteCourseForm" class="btn btn-danger">Yes, Delete Now</button>
            </div>
        </div>
    </div>
</div>

<!-- Add School Year Modal -->
<div class="modal fade" id="addSchoolYearModal" tabindex="-1" aria-labelledby="addSchoolYearModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSchoolYearModalLabel">Add New School Year</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addSchoolYearForm" method="POST" action="school_year_actions.php">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="addSchoolYearYear" class="form-label">School Year</label>
                        <input type="text" class="form-control" id="addSchoolYearYear" name="year" required placeholder="e.g., 2024-2025">
                    </div>
                    <div class="mb-3">
                        <label for="addSchoolYearStatus" class="form-label">Status</label>
                        <select class="form-select" id="addSchoolYearStatus" name="is_active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="addSchoolYearForm" class="btn btn-primary">Add School Year</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit School Year Modal -->
<div class="modal fade" id="editSchoolYearModal" tabindex="-1" aria-labelledby="editSchoolYearModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSchoolYearModalLabel">Edit School Year</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editSchoolYearForm" method="POST" action="school_year_actions.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="editSchoolYearId" name="id">
                    <div class="mb-3">
                        <label for="editSchoolYearYear" class="form-label">School Year</label>
                        <input type="text" class="form-control" id="editSchoolYearYear" name="year" required>
                    </div>
                    <div class="mb-3">
                        <label for="editSchoolYearStatus" class="form-label">Status</label>
                        <select class="form-select" id="editSchoolYearStatus" name="is_active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="editSchoolYearForm" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete School Year Modal -->
<div class="modal fade" id="deleteSchoolYearModal" tabindex="-1" aria-labelledby="deleteSchoolYearModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteSchoolYearModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this school year? This action cannot be undone.</p>
                <form id="deleteSchoolYearForm" method="POST" action="school_year_actions.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteSchoolYearId" name="id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                <button type="submit" form="deleteSchoolYearForm" class="btn btn-danger">Yes, Delete Now</button>
            </div>
        </div>
    </div>
</div>

<script>
// Search functionality for sections table (client-side search)
document.querySelector('input[name="search"]').addEventListener('input', function () {
    const search = this.value.toLowerCase();
    document.querySelectorAll('.records tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Populate Edit Section Modal
document.querySelectorAll('.edit-section-btn').forEach(button => {
    button.addEventListener('click', function () {
        const id = this.dataset.id;
        fetch('section_actions.php?action=get&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.status === 200) {
                    const section = data.data;
                    document.getElementById('editSectionId').value = section.id;
                    document.getElementById('editSectionSchoolYear').value = section.school_year_id;
                    document.getElementById('editSectionCourse').value = section.course_id;
                    document.getElementById('editSectionYearLevel').value = section.year_level;
                    document.getElementById('editSectionSection').value = section.section;
                    document.getElementById('editSectionStatus').value = section.is_active;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching section data:', error);
                alert('Failed to load section data.');
            });
    });
});

// Populate Delete Section Modal
document.querySelectorAll('.delete-section-btn').forEach(button => {
    button.addEventListener('click', function () {
        document.getElementById('deleteSectionId').value = this.dataset.id;
    });
});

// Populate Edit Course Modal
document.querySelectorAll('.edit-course-btn').forEach(button => {
    button.addEventListener('click', function () {
        const id = this.dataset.id;
        fetch('course_actions.php?action=get&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.status === 200) {
                    const course = data.data;
                    document.getElementById('editCourseId').value = course.id;
                    document.getElementById('editCourseName').value = course.name;
                    document.getElementById('editCourseCode').value = course.code;
                    document.getElementById('editCourseDescription').value = course.description || '';
                    document.getElementById('editCourseStatus').value = course.is_active;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching course data:', error);
                alert('Failed to load course data.');
            });
    });
});

// Populate Delete Course Modal
document.querySelectorAll('.delete-course-btn').forEach(button => {
    button.addEventListener('click', function () {
        document.getElementById('deleteCourseId').value = this.dataset.id;
    });
});

// Populate Edit School Year Modal
document.querySelectorAll('.edit-school-year-btn').forEach(button => {
    button.addEventListener('click', function () {
        const id = this.dataset.id;
        fetch('school_year_actions.php?action=get&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.status === 200) {
                    const schoolYear = data.data;
                    document.getElementById('editSchoolYearId').value = schoolYear.id;
                    document.getElementById('editSchoolYearYear').value = schoolYear.year;
                    document.getElementById('editSchoolYearStatus').value = schoolYear.is_active;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching school year data:', error);
                alert('Failed to load school year data.');
            });
    });
});

// Populate Delete School Year Modal
document.querySelectorAll('.delete-school-year-btn').forEach(button => {
    button.addEventListener('click', function () {
        document.getElementById('deleteSchoolYearId').value = this.dataset.id;
    });
});
</script>

<?php include('includes/footer.php'); ?>