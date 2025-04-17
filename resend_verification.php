<?php
$page_title = "Resend Verification Email";
include('includes/header.php');

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
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <a href="index.php" class="btn" style="background-color: #2e7d32; color: white; border: none;">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <h5 style="color: #2e7d32; margin: 0;">Resend Email Verification</h5>
                            <span></span> <!-- Spacer to balance the layout -->
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="resend_verification_code.php" method="POST">
                            <!-- CSRF Token -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            
                            <div class="form-floating mb-3">
                                <input type="email" name="email" class="form-control" id="email" placeholder="Enter your email address" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                                <label for="email">Email Address</label>
                                <div id="email-error" class="text-danger mt-1" style="font-size: 0.875rem;"></div>
                            </div>
                            <div class="form-group mb-3">
                                <button type="submit" name="resend_email_verify_btn" class="btn w-100" style="background-color: #2e7d32; color: white; border: none;">Submit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const emailInput = document.getElementById('email');
    const emailError = document.getElementById('email-error');
    const form = document.querySelector('form');

    // Function to validate email in real-time
    function validateEmail() {
        const emailPattern = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/;
        if (!emailPattern.test(emailInput.value)) {
            emailInput.setCustomValidity('Please enter a valid email address.');
            emailError.textContent = 'Please enter a valid email address.';
        } else {
            emailInput.setCustomValidity('');
            emailError.textContent = '';
        }
    }

    // Validate email on input change
    emailInput.addEventListener('input', validateEmail);

    // Validate on form submission
    form.addEventListener('submit', function(event) {
        validateEmail();
        if (!emailInput.checkValidity()) {
            event.preventDefault();
            emailInput.reportValidity();
        }
    });
</script>

<?php include('includes/footer.php'); ?>