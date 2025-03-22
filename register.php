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
                        <form action="code.php" method="POST" enctype="multipart/form-data">
                            <!-- Profile Upload -->
                            <div class="mb-3 text-center">
                                <label for="profile" class="form-label">Profile Picture</label>
                                <input type="file" name="profile" id="profile" class="form-control" accept="image/*" max="2000000">
                            </div>

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
                                    <input type="text" name="year" class="form-control" id="year" placeholder="School Year" required>
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
                                    <input type="text" name="number" class="form-control" id="number" placeholder="Phone Number" required pattern="[0-9]{10,11}" title="Enter a 10-11 digit phone number">
                                    <label for="number">Phone Number</label>
                                </div>
                            </div>

                            <!-- Birthday and Gender -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6 form-floating">
                                    <input type="date" name="birthdate" class="form-control" id="birthday" required max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" min="<?php echo date('Y-m-d', strtotime('-100 years')); ?>">
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
                                    <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                                    <label for="password">Password</label>
                                </div>
                                <div class="col-md-6 form-floating">
                                    <input type="password" name="confirm_password" class="form-control" id="confirm_password" placeholder="Confirm Password" required>
                                    <label for="confirm_password">Confirm Password</label>
                                </div>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="terms" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="terms.php" style="color: #2e7d32;" target="_blank">terms and conditions</a>
                                </label>
                            </div>

                            <!-- Register Button -->
                            <div class="form-group">
                                <button type="submit" name="register_btn" class="btn w-100" style="background-color: #2e7d32; color: white; border: none;">Register Now</button>
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

<?php include('includes/footer.php'); ?>