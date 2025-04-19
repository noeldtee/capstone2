<?php
$page_title = "Settings - Terms and Conditions";
include('includes/header.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirect('dashboard.php', 'Access denied. You do not have permission to view this page.', 'danger');
    exit();
}

// Handle form submission to update Terms and Conditions
$terms = ''; // Initialize $terms
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
            // Re-fetch the updated content after a successful update
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

// Fetch the current Terms and Conditions if not already fetched (e.g., on initial page load or after a failed update)
if (empty($terms)) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'terms_and_conditions'");
    $stmt->execute();
    $result = $stmt->get_result();
    $terms = $result->num_rows > 0 ? $result->fetch_assoc()['setting_value'] : '';
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
        <span>Settings - Terms and Conditions</span><br>
        <small>Manage the Terms and Conditions displayed during user registration.</small>
    </div>
    <div class="page-content">
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
    </div>
</main>

<?php include('includes/footer.php'); ?>