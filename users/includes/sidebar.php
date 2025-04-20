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
                    <a href="dashboard.php" class="<?php echo (isset($page_title) && $page_title === 'Student Dashboard') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-house"></i>
                        <small>Dashboard</small>
                    </a>
                </li>
                <li>
                    <a href="request_document.php" class="<?php echo (isset($page_title) && $page_title === 'Request Document') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-file-signature"></i>
                        <small>Request Document</small>
                    </a>
                </li>
                <li>
                    <a href="history.php" class="<?php echo (isset($page_title) && $page_title === 'Request History') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-history"></i>
                        <small>History</small>
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="<?php echo (isset($page_title) && $page_title === 'Settings') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-gear"></i>
                        <small>Settings</small>
                    </a>
                </li>
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

<script>
    // Handle logout confirmation
    document.getElementById('confirmLogout').addEventListener('click', function() {
        window.location.href = '../logout.php';
    });
</script>