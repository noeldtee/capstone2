<?php
$page_title = "Reset Password";
include('includes/header.php');

if (!isset($_GET['token']) || empty($_GET['token'])) {
    redirect('password-reset.php', 'Invalid reset link.', 'danger');
    exit();
}
?>

<div class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <?php alertMessage(); ?>

                <div class="card shadow-sm p-4" style="background-color: white; border-radius: 10px;">
                    <div class="card-header text-center" style="background-color: white;">
                        <h5 style="color: #2e7d32;">Reset Password</h5>
                    </div>
                    <div class="card-body">
                        <form action="password-reset-code.php" method="POST">
                            <input type="hidden" name="password_token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                            <?php
                            // Get email from URL or database if available (optional, for security)
                            $email = isset($_GET['email']) && !empty($_GET['email']) ? htmlspecialchars($_GET['email']) : '';
                            if (empty($email)) {
                                // Optionally fetch email from database using the token (for security)
                                $stmt = $conn->prepare("SELECT email FROM users WHERE reset_token = ? LIMIT 1");
                                $stmt->bind_param("s", $_GET['token']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($result && mysqli_num_rows($result) == 1) {
                                    $row = $result->fetch_assoc();
                                    $email = htmlspecialchars($row['email']);
                                } else {
                                    redirect('password-reset.php', 'Invalid reset link. Email not found.', 'danger');
                                    exit();
                                }
                            }
                            ?>
                            <input type="hidden" name="email" value="<?php echo $email; ?>">
                            
                            <div class="form-floating mb-3">
                                <input type="password" name="new_password" class="form-control" id="new_password" placeholder="New Password" required>
                                <label for="new_password">New Password</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="password" name="confirm_password" class="form-control" id="confirm_password" placeholder="Confirm Password" required>
                                <label for="confirm_password">Confirm Password</label>
                            </div>
                            <div class="form-group mb-3">
                                <button type="submit" name="password_update" class="btn w-100" style="background-color: #2e7d32; color: white; border: none;">Update Password</button>
                            </div>
                            <!-- Back Button -->
                            <div class="text-center mt-3">
                                <a href="password-reset.php" class="btn" style="background-color: #2e7d32; color: white; border: none;">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>

<?php
// Close database connection if opened
if (isset($conn)) {
    $conn->close();
}
?>