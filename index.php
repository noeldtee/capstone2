<?php

$page_title = "Login Form";
include('includes/header.php');

if (isset($_SESSION['auth'])) {
    redirect('dashboard.php', 'You are already logged in');
}
?>

<div class="d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow-sm p-4" style="width: 100%; max-width: 500px; background-color: white; border-radius: 10px;">
        <div class="text-center mb-4">
            <img src="assets/images/logo.png" alt="Logo" width="80" height="76">
            <h5 class="mt-3" style="color: #2e7d32;">BPC Document Request System</h5>
        </div>

        <?php alertMessage(); ?>

        <form action="logincode.php" method="POST">
            <div class="form-floating mb-3">
                <label for="">Email Address</label>
                <input type="text" name="email" class="form-control">
            </div>
            <div class="form-floating mb-3">
                <label for="">Password</label>
                <input type="text" name="password" class="form-control">
            </div>
            <div class="form-group mb-3">
                <button type="submit" name="loginBtn" class="btn btn-success w-100" style="background-color: #2e7d32; border: none;">Login</button>
            </div>
        </form>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="gridCheck" name="remember_me">
            <label class="form-check-label" for="gridCheck">Remember Me</label>
        </div>
        <div class="d-flex justify-content-between mb-3">
            <a href="resend_verification.php" class="text-decoration-none" style="color: #2e7d32;">Resend Verification Email</a>
            <a href="password-reset.php" class="text-decoration-none" style="color: #2e7d32;">Forgot your password?</a>
        </div>

        <hr>
        <div class="text-center mt-3">
            Don't have an account?
            <a href="register.php" class="text-decoration-none" style="color: #2e7d32;">Register Now</a>
        </div>

    </div>
</div>
</div>

<?php include('includes/footer.php'); ?>