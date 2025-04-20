<div class="sidebar">
    <div class="side-content">
        <div class="profile">
            <!-- Logo -->
            <div class="profile-img bg-img" style="background-image: url(../assets/images/Logo.png);"></div>
            <h5>BPC Document Request System</h5>
        </div>
        <div class="side-menu">
            <ul class="container">
                <li>
                    <a href="dashboard.php" class="<?php echo (isset($page_title) && $page_title === 'Admin Dashboard') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-house"></i>
                        <small>Dashboard</small>
                    </a>
                </li>
                <li>
                    <a href="request.php" class="<?php echo (isset($page_title) && $page_title === 'Document Request Management') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-file-signature"></i>
                        <small>Request Management</small>
                    </a>
                </li>
                <li>
                    <a href="request_logs.php" class="<?php echo (isset($page_title) && $page_title === 'Request Logs') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-history"></i>
                        <small>Request Logs</small>
                    </a>
                </li>
                <li>
                    <a href="payment_logs.php" class="<?php echo (isset($page_title) && $page_title === 'Payment Logs') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-money-check-alt"></i>
                        <small>Payment Logs</small>
                    </a>
                </li>
                <!-- Settings with Bootstrap Dropdown -->
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle <?php echo (isset($page_title) && in_array($page_title, ['Document Management', 'Academic Management', 'Users Management'])) ? 'active' : ''; ?>" id="settingsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-cogs"></i>
                        <small>Management</small>
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
                        <li>
                            <a class="dropdown-item <?php echo (isset($page_title) && $page_title === 'Document Management') ? 'active' : ''; ?>" href="documents.php">
                                <i class="fa-solid fa-file-alt me-2"></i>
                                Document Management
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo (isset($page_title) && $page_title === 'Academic Management') ? 'active' : ''; ?>" href="sections.php">
                                <i class="fa-solid fa-school me-2"></i>
                                Academic Management
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo (isset($page_title) && $page_title === 'Users Management') ? 'active' : ''; ?>" href="students.php">
                                <i class="fa-solid fa-users me-2"></i>
                                Users Management
                            </a>
                        </li>
                    </ul>
                </li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle <?php echo (isset($page_title) && in_array($page_title, ['Action Logs', 'Settings - Terms and Conditions', 'Admin Users'])) ? 'active' : ''; ?>" id="settingsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-user-shield"></i>
                            <small>Admin<br>Management</small>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
                            <li>
                                <a class="dropdown-item <?php echo (isset($page_title) && $page_title === 'Action Logs') ? 'active' : ''; ?>" href="action_logs.php">
                                    <i class="fa-solid fa-history"></i>
                                    Action Logs
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?php echo (isset($page_title) && $page_title === 'Settings - Terms and Conditions') ? 'active' : ''; ?>" href="settings.php">
                                    <i class="fa-solid fa-file-contract"></i>
                                    Terms and Conditions
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?php echo (isset($page_title) && $page_title === 'Admin Users') ? 'active' : ''; ?>" href="admin.php">
                                    <i class="fa-solid fa-user-shield"></i>
                                    Admin Users
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
                <li class="logout">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <small>Logout</small>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Do you want to logout?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                <button type="button" class="btn btn-primary" id="confirmLogout">Yes</button>
            </div>
        </div>
    </div>
</div>