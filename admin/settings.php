<?php
$page_title = "Settings";
include('includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirect('dashboard.php', 'Access denied. You do not have permission to view this page.', 'danger');
    exit();
}

// Handle form submission to update Terms and Conditions
$terms = '';
if (isset($_POST['update_terms'])) {
    $terms_content = validate($_POST['terms_content']);
    if (empty($terms_content)) {
        $_SESSION['message'] = 'Terms and Conditions content cannot be empty.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('terms_and_conditions', ?) 
                                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param("ss", $terms_content, $terms_content);
        if ($stmt->execute()) {
            logAction($conn, 'Terms Updated', 'Terms and Conditions', 'Updated Terms and Conditions content');
            $_SESSION['message'] = 'Terms and Conditions updated successfully.';
            $_SESSION['message_type'] = 'success';
            $stmt->close();
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'terms_and_conditions'");
            $stmt->execute();
            $result = $stmt->get_result();
            $terms = $result->num_rows > 0 ? $result->fetch_assoc()['setting_value'] : '';
            $stmt->close();
        } else {
            $_SESSION['message'] = 'Failed to update Terms and Conditions: ' . $stmt->error;
            $_SESSION['message_type'] = 'danger';
            $stmt->close();
        }
    }
}

// Handle form submission to update Current Semester
$current_semester = '';
if (isset($_POST['update_semester'])) {
    $semester = validate($_POST['current_semester']);
    if (empty($semester)) {
        $_SESSION['message'] = 'Semester cannot be empty.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('current_semester', ?) 
                                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param("ss", $semester, $semester);
        if ($stmt->execute()) {
            logAction($conn, 'Semester Updated', 'Current Semester', 'Updated semester to: ' . $semester);
            $_SESSION['message'] = 'Current semester updated successfully.';
            $_SESSION['message_type'] = 'success';
            $current_semester = $semester;
            $stmt->close();
        } else {
            $_SESSION['message'] = 'Failed to update semester: ' . $stmt->error;
            $_SESSION['message_type'] = 'danger';
            $stmt->close();
        }
    }
}

// Fetch the current Terms and Conditions if not already fetched
if (empty($terms)) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'terms_and_conditions'");
    $stmt->execute();
    $result = $stmt->get_result();
    $terms = $result->num_rows > 0 ? $result->fetch_assoc()['setting_value'] : '';
    $stmt->close();
}

// Fetch the current semester if not already fetched
if (empty($current_semester)) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'current_semester'");
    $stmt->execute();
    $result = $stmt->get_result();
    $current_semester = $result->num_rows > 0 ? $result->fetch_assoc()['setting_value'] : '';
    $stmt->close();
}

$conn->close();
?>

<link rel="stylesheet" href="../assets/css/admin_dashboard.css">
<!-- Include TinyMCE with your API key -->
<script src="https://cdn.tiny.cloud/1/qzr200l7x453ec7brf75twjz4akcmp5ymtrhmyyeuq556anc/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: '#terms_content',
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
        toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
        height: 400,
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
    });
</script>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x" style="z-index: 1050; margin-top: 20px;" role="alert">
        <?php echo htmlspecialchars($_SESSION['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>

<main>
    <div class="page-header">
        <span>Settings</span><br>
        <small>Manage system settings.</small>
    </div>
    <div class="page-content">
        <!-- Terms and Conditions Section -->
        <div class="records table-responsive">
            <div class="record-header">
                <div class="add">
                    <span>Update Terms and Conditions</span>
                </div>
            </div>
            <div>
                <form action="settings.php" method="POST">
                    <div class="mb-3">
                        <label for="terms_content" class="form-label">Terms and Conditions Content</label>
                        <textarea name="terms_content" id="terms_content" class="form-control"><?php echo htmlspecialchars($terms); ?></textarea>
                    </div>
                    <button type="submit" name="update_terms" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Current Semester Section -->
        <div class="records table-responsive mt-4">
            <div class="record-header">
                <div class="add">
                    <span>Update Current Semester</span>
                </div>
            </div>
            <div>
                <form action="settings.php" method="POST">
                    <div class="mb-3">
                        <label for="current_semester" class="form-label">Current Semester</label>
                        <input type="text" class="form-control" id="current_semester" name="current_semester" value="<?php echo htmlspecialchars($current_semester); ?>" placeholder="e.g., 1st Semester 2024-2025" required>
                        <small class="form-text text-muted">Enter the current semester (e.g., "1st Semester 2024-2025"). Changing this will allow students to request restricted documents again.</small>
                    </div>
                    <button type="submit" name="update_semester" class="btn btn-primary">Update Semester</button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include('includes/footer.php'); ?>