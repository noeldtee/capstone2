<?php
$page_title = "Reset Password";
include('includes/header.php');

redirectIfLoggedIn(); // Redirect if already logged in
// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    redirect('password-reset.php', 'Invalid reset link.', 'danger');
    exit();
}

$token = validate($_GET['token']);

// Validate token and fetch email
$stmt = $conn->prepare("SELECT email, reset_expires_at FROM users WHERE reset_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || mysqli_num_rows($result) == 0) {
    redirect('password-reset.php', 'Invalid reset link. Token not found.', 'danger');
    exit();
}

$row = $result->fetch_assoc();
$email = htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8');

// Check if token has expired
$expires_at = new DateTime($row['reset_expires_at']);
$now = new DateTime();
if ($now > $expires_at) {
    redirect('password-reset.php', 'Password reset link has expired. Please request a new link.', 'danger');
    exit();
}

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <?php alertMessage(); ?>

                <div class="card shadow-sm p-4" style="background-color: white; border-radius: 10px;">
                    <div class="card-header text-center" style="background-color: white;">
                        <h5 style="color: #2e7d32;">Set New Password</h5>
                    </div>
                    <div class="card-body">
                        <form action="password-reset-code.php" method="POST">
                            <!-- Hidden fields -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="password_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="email" value="<?php echo $email; ?>">

                            <div class="row g-3 mb-3">
                                <div class="col-md-6 form-floating">
                                    <input type="password" name="new_password" class="form-control" id="new_password" placeholder="New Password" required pattern="(?=.*[0-9])(?=.*[!@#$%^&*(),.?:{}|<>]).{8,}" title="Password must be at least 8 characters and include a number and a special character">
                                    <label for="new_password">New Password</label>
                                </div>
                                <div class="col-md-6 form-floating">
                                    <input type="password" name="confirm_password" class="form-control" id="confirm_password" placeholder="Confirm Password" required>
                                    <label for="confirm_password">Confirm Password</label>
                                    <div id="password-error" class="text-danger mt-1" style="font-size: 0.875rem;"></div>
                                </div>
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

<script>
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const passwordError = document.getElementById('password-error');
    const form = document.querySelector('form');

    // Function to validate passwords in real-time
    function validatePasswords() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match.');
            passwordError.textContent = 'Passwords do not match.';
        } else {
            confirmPassword.setCustomValidity('');
            passwordError.textContent = '';
        }
    }

    // Validate on input change
    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);

    // Validate on form submission
    form.addEventListener('submit', function(event) {
        validatePasswords();
        if (!form.checkValidity()) {
            event.preventDefault();
            confirmPassword.reportValidity();
        }
    });
</script>

<?php
// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>

<?php include('includes/footer.php'); ?>