<?php
$page_title = "Register Form";
include('includes/header.php');

redirectIfLoggedIn(); // Redirect if already logged in

// Fetch initial school years
$query = "SELECT * FROM school_years WHERE is_active = 1 ORDER BY year DESC";
$result = mysqli_query($conn, $query);
$school_years = [];
while ($row = mysqli_fetch_assoc($result)) {
    $school_years[] = $row;
}
?>

<div class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6" style="width: 700px;">
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

                            <!-- Student ID -->
                            <div class="form-floating mb-3">
                                <input type="text" name="studentid" class="form-control" id="studentid" placeholder="Student ID" required>
                                <label for="studentid">Student ID</label>
                            </div>

                            <!-- School Year -->
                            <div class="form-floating mb-3">
                                <select name="year_id" class="form-select" id="school_year" required>
                                    <option value="" selected disabled>Select School Year</option>
                                    <?php foreach ($school_years as $sy): ?>
                                        <option value="<?php echo $sy['id']; ?>"><?php echo htmlspecialchars($sy['year']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="school_year">School Year</label>
                            </div>

                            <!-- Course -->
                            <div class="form-floating mb-3" id="course-container" style="display: none;">
                                <select name="course_id" class="form-select" id="course" required disabled>
                                    <option value="" selected disabled>Select Course</option>
                                </select>
                                <label for="course">Course</label>
                            </div>

                            <!-- Year Level -->
                            <div class="form-floating mb-3" id="year-level-container" style="display: none;">
                                <select name="year_level" class="form-select" id="year_level" required disabled>
                                    <option value="" selected disabled>Select Year Level</option>
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year">3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                </select>
                                <label for="year_level">Year Level</label>
                            </div>

                            <!-- Section -->
                            <div class="form-floating mb-3" id="section-container" style="display: none;">
                                <select name="section_id" class="form-select" id="section" required disabled>
                                    <option value="" selected disabled>Select Section</option>
                                </select>
                                <label for="section">Section</label>
                            </div>

                            <!-- Number -->
                            <div class="form-floating mb-3">
                                <input type="text" name="number" class="form-control" id="number" placeholder="e.g., +639123456789 or 09123456789" required pattern="(\+63[0-9]{10}|09[0-9]{9})" title="Enter a Philippine number starting with +63 followed by 10 digits (e.g., +639123456789) or starting with 09 followed by 9 digits (e.g., 09123456789)">
                                <label for="number">Phone Number</label>
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
                                            <!-- ... (rest of modal content unchanged) ... -->
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
    // Profile picture preview (unchanged)
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

    // Dynamic field handling
    const schoolYearSelect = document.getElementById('school_year');
    const courseSelect = document.getElementById('course');
    const yearLevelSelect = document.getElementById('year_level');
    const sectionSelect = document.getElementById('section');
    const courseContainer = document.getElementById('course-container');
    const yearLevelContainer = document.getElementById('year-level-container');
    const sectionContainer = document.getElementById('section-container');

    // Reset subsequent fields when a prior field changes
    function resetFields(from) {
        if (from <= 1) {
            courseSelect.innerHTML = '<option value="" selected disabled>Select Course</option>';
            courseContainer.style.display = 'none';
            courseSelect.disabled = true;
        }
        if (from <= 2) {
            yearLevelSelect.value = '';
            yearLevelContainer.style.display = 'none';
            yearLevelSelect.disabled = true;
        }
        if (from <= 3) {
            sectionSelect.innerHTML = '<option value="" selected disabled>Select Section</option>';
            sectionContainer.style.display = 'none';
            sectionSelect.disabled = true;
        }
    }

    // School Year change - Just enables courses
    schoolYearSelect.addEventListener('change', function() {
        resetFields(1);
        if (this.value) {
            fetchCourses(); // Fetch all active courses, no school year filter
            courseContainer.style.display = 'block';
            courseSelect.disabled = false;
        }
    });

    // Course change - Enables year level
    courseSelect.addEventListener('change', function() {
        resetFields(2);
        if (this.value) {
            yearLevelContainer.style.display = 'block';
            yearLevelSelect.disabled = false;
        }
    });

    // Year Level change - Fetches sections based on course
    yearLevelSelect.addEventListener('change', function() {
        resetFields(3);
        if (this.value) {
            fetchSections(schoolYearSelect.value, courseSelect.value, this.value);
            sectionContainer.style.display = 'block';
            sectionSelect.disabled = false;
        }
    });

    // Fetch all active courses (no school year dependency)
    function fetchCourses() {
        fetch('/capstone-admin/fetch_options.php?action=courses')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok: ' + response.statusText);
                return response.json();
            })
            .then(data => {
                console.log('Courses data:', data); // Debug: Log the response
                courseSelect.innerHTML = '<option value="" selected disabled>Select Course</option>';
                if (data.status === 'success') {
                    if (data.courses && data.courses.length > 0) {
                        data.courses.forEach(course => {
                            const option = document.createElement('option');
                            option.value = course.id;
                            option.textContent = course.name;
                            courseSelect.appendChild(option);
                        });
                    } else {
                        courseSelect.innerHTML += '<option value="" disabled>No courses available</option>';
                    }
                } else {
                    courseSelect.innerHTML += '<option value="" disabled>Error: ' + (data.message || 'Unknown error') + '</option>';
                }
            })
            .catch(error => {
                console.error('Error fetching courses:', error);
                courseSelect.innerHTML = '<option value="" selected disabled>Error loading courses</option>';
            });
    }

    // Fetch sections based on school year, course, and year level
    function fetchSections(schoolYearId, courseId, yearLevel) {
        fetch(`/capstone-admin/fetch_options.php?action=sections&school_year_id=${schoolYearId}&course_id=${courseId}&year_level=${encodeURIComponent(yearLevel)}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok: ' + response.statusText);
                return response.json();
            })
            .then(data => {
                console.log('Sections data:', data); // Debug: Log the response
                sectionSelect.innerHTML = '<option value="" selected disabled>Select Section</option>';
                if (data.status === 'success') {
                    if (data.sections && data.sections.length > 0) {
                        data.sections.forEach(section => {
                            const option = document.createElement('option');
                            option.value = section.id;
                            option.textContent = section.section;
                            sectionSelect.appendChild(option);
                        });
                    } else {
                        sectionSelect.innerHTML += '<option value="" disabled>No sections available</option>';
                    }
                } else {
                    sectionSelect.innerHTML += '<option value="" disabled>Error: ' + (data.message || 'Unknown error') + '</option>';
                }
            })
            .catch(error => {
                console.error('Error fetching sections:', error);
                sectionSelect.innerHTML = '<option value="" selected disabled>Error loading sections</option>';
            });
    }

    // Password validation (unchanged)
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const form = document.querySelector('form');
    const btn = document.getElementById('registerBtn');
    const passwordError = document.getElementById('password-error');

    function validatePasswords() {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match.');
            passwordError.textContent = 'Passwords do not match.';
        } else {
            confirmPassword.setCustomValidity('');
            passwordError.textContent = '';
        }
    }

    password.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);

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