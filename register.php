<?php
$page_title = "Register Form";
include('includes/header.php');

if (isset($_SESSION['auth'])) {
    redirect('dashboard.php', 'You are already logged in', 'info');
}
?>

<div class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <?php alertMessage(); ?>

                <div class="card shadow-sm p-4 mb-4" style="background-color: white; border-radius: 10px;">
                    <div class="card-header text-center" style="background-color: white;">
                        <img src="assets/images/logo.png" alt="Logo" width="80" height="76">
                        <h5 class="mt-3" style="color: #2e7d32;">BPC Document Request System</h5>
                    </div>
                    <div class="card-body">
                        <form action="/capstone-admin/code.php" method="POST" enctype="multipart/form-data">
                            <!-- Profile Upload -->
                            <div class="mb-3 text-center">
                                <label for="profile" class="form-label">Profile Picture</label>
                                <input type="file" name="profile" id="profile" class="form-control" accept="image/*" max="2000000" onchange="previewImage(event)">
                                <img id="profile-preview" src="#" alt="Preview" style="display: none; max-width: 200px; margin-top: 10px;">
                                <button type="button" id="remove-profile" class="btn btn-danger btn-sm mt-2" style="display: none;" onclick="removeImage()">Remove Image</button>
                                <small class="form-text text-muted">Leave blank to use default profile picture.</small>
                            </div>
                            <script>
                                const profileInput = document.getElementById('profile');
                                const profilePreview = document.getElementById('profile-preview');
                                const removeBtn = document.getElementById('remove-profile');

                                function previewImage(event) {
                                    if (event.target.files && event.target.files[0]) {
                                        profilePreview.src = URL.createObjectURL(event.target.files[0]);
                                        profilePreview.style.display = 'block';
                                        removeBtn.style.display = 'inline-block';
                                    }
                                }

                                function removeImage() {
                                    profileInput.value = '';
                                    profilePreview.style.display = 'none';
                                    profilePreview.src = '#';
                                    removeBtn.style.display = 'none';
                                }
                            </script>

                            <!-- First Name and Last Name -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6 form-floating">
                                    <input type="text" name="firstname" class="form-control" id="firstname" placeholder="First Name" required>
                                    <label for="firstname">First Name</label>
                                </div>
                                <div class="col-md-6 form-floating">
                                    <input type="text" name="lastname" class="form-control" id="lastname" placeholder="Last Name" required>
                                    <label for="lastname">Last Name</label>
                                </div>
                            </div>

                            <!-- Student ID and School Year -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6 form-floating">
                                    <input type="text" name="studentid" class="form-control" id="studentid" placeholder="Student ID" required>
                                    <label for="studentid">Student ID</label>
                                </div>
                                <div class="col-md-6 form-floating">
                                    <input type="text" name="year" class="form-control" id="year" placeholder="School Year (e.g., 2024-2025)" required pattern="\d{4}-\d{4}" title="Enter school year in the format YYYY-YYYY (e.g., 2024-2025)">
                                    <label for="year">School Year</label>
                                </div>
                            </div>

                            <!-- Course and Year Level -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6 form-floating">
                                    <input type="text" name="course" class="form-control" id="course" placeholder="Course" required>
                                    <label for="course">Course</label>
                                </div>
                                <div class="col-md-6 form-floating">
                                    <select name="year_level" class="form-select" id="year_level" required>
                                        <option value="" disabled selected>Select Year Level</option>
                                        <option value="1st Year">1st Year</option>
                                        <option value="2nd Year">2nd Year</option>
                                        <option value="3rd Year">3rd Year</option>
                                        <option value="4th Year">4th Year</option>
                                    </select>
                                    <label for="year_level">Year Level</label>
                                </div>
                            </div>

                            <!-- Section and Number -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6 form-floating">
                                    <input type="text" name="section" class="form-control" id="section" placeholder="Section" required>
                                    <label for="section">Section</label>
                                </div>
                                <div class="col-md-6 form-floating">
                                    <input type="text" name="number" class="form-control" id="number" placeholder="Phone Number" required pattern="\+63[0-9]{10}" title="Enter a Philippine number starting with +63 followed by 10 digits (e.g., +639123456789)">
                                    <label for="number">Phone Number</label>
                                </div>
                            </div>

                            <!-- Birthday and Gender -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6 form-floating">
                                    <input type="date" name="birthdate" class="form-control" id="birthday" required max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" min="<?php echo date('Y-m-d', strtotime('-60 years')); ?>">
                                    <label for="birthday">Birthday</label>
                                </div>
                                <div class="col-md-6 form-floating">
                                    <select name="gender" class="form-select" id="gender" required>
                                        <option value="" disabled selected>Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Prefer not to say">Prefer not to say</option>
                                    </select>
                                    <label for="gender">Gender</label>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="form-floating mb-3">
                                <input type="email" name="email" class="form-control" id="email" placeholder="Email Address" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                                <label for="email">Email Address</label>
                            </div>

                            <!-- Password and Confirm Password -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6 form-floating">
                                    <input type="password" name="password" class="form-control" id="password" placeholder="Password" required pattern="(?=.*[0-9])(?=.*[!@#$%^&*(),.?:{}|<>]).{8,}" title="Password must be at least 8 characters and include a number and a special character">
                                    <label for="password">Password</label>
                                </div>
                                <div class="col-md-6 form-floating">
                                    <input type="password" name="confirm_password" class="form-control" id="confirm_password" placeholder="Confirm Password" required>
                                    <label for="confirm_password">Confirm Password</label>
                                    <div id="password-error" class="text-danger mt-1" style="font-size: 0.875rem;"></div>
                                </div>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="terms" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" style="color: #2e7d32;" data-bs-toggle="modal" data-bs-target="#termsModal">terms and conditions</a>
                                </label>
                            </div>

                            <!-- Terms and Conditions Modal -->
                            <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <h6>1. Acceptance of Terms</h6>
                                            <p>By registering for the BPC Document Request System, you agree to comply with and be bound by the following terms and conditions. If you do not agree, please do not use this system.</p>

                                            <h6>2. User Responsibilities</h6>
                                            <p>You are responsible for providing accurate and complete information during registration. You must not use the system for any unlawful or unauthorized purpose.</p>

                                            <h6>3. Data Privacy</h6>
                                            <p>We collect and process your personal information in accordance with our Privacy Policy. By using this system, you consent to such processing.</p>

                                            <h6>4. Account Security</h6>
                                            <p>You are responsible for maintaining the confidentiality of your account password and for all activities that occur under your account.</p>

                                            <h6>5. Termination</h6>
                                            <p>We reserve the right to terminate or suspend your account at our discretion, without notice, for conduct that we believe violates these terms.</p>

                                            <h6>6. Changes to Terms</h6>
                                            <p>We may update these terms from time to time. Continued use of the system after such changes constitutes your acceptance of the new terms.</p>

                                            <p>For any questions, please contact us at <a href="mailto:support@bpc.edu">support@bpc.edu</a>.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Register Button -->
                            <div class="form-group">
                                <button type="submit" name="register_btn" class="btn w-100" style="background-color: #2e7d32; color: white; border: none;" id="registerBtn">
                                    Register Now <span class="spinner-border spinner-border-sm" role="status" style="display: none;"></span>
                                </button>
                            </div>
                            <!-- Sign In Link -->
                            <div class="text-center mt-3">
                                <span>Already have an account? </span>
                                <a href="index.php" class="text-decoration-none" style="color: #2e7d32;">Sign In</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const form = document.querySelector('form');
    const btn = document.getElementById('registerBtn');
    const passwordError = document.getElementById('password-error');

    // Function to validate passwords in real-time
    function validatePasswords() {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match.');
            passwordError.textContent = 'Passwords do not match.';
        } else {
            confirmPassword.setCustomValidity('');
            passwordError.textContent = '';
        }
    }

    // Validate on input change for both fields
    password.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);

    // Validate on form submission
    form.addEventListener('submit', function(event) {
        if (password.value !== confirmPassword.value) {
            event.preventDefault();
            confirmPassword.setCustomValidity('Passwords do not match.');
            passwordError.textContent = 'Passwords do not match.';
            confirmPassword.reportValidity();
        } else {
            confirmPassword.setCustomValidity('');
            passwordError.textContent = '';
            setTimeout(() => {
                btn.disabled = true;
                btn.querySelector('.spinner-border').style.display = 'inline-block';
            }, 0);
        }
    });
</script>

<?php include('includes/footer.php'); ?>