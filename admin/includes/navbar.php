<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light px-4 shadow-sm" style="background-color: white; border-bottom: 1px solid #dee2e6; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
    <div class="container-fluid py-2">
        <a class="navbar-brand" href="dashboard.php" style="color: #2e7d32;">
            <h5 style="margin: 0;">Dashboard</h5>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="ms-auto d-flex align-items-center">
                <div class="input-group me-3">
                    <span class="input-group-text" style="background-color: #f8f9fa; border: 1px solid #dee2e6;"><i class="fas fa-search text-dark" aria-hidden="true"></i></span>
                    <input type="text" class="form-control" placeholder="Type here..." style="background-color: #f8f9fa; border: 1px solid #dee2e6;">
                </div>
                <ul class="navbar-nav justify-content-end">
                    <?php if (isset($_SESSION['auth'])): ?>
                        <li class="nav-item dropdown d-flex align-items-center me-3">
                            <a href="javascript:;" class="nav-link dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="color: #2e7d32;">
                                <i class="fa fa-user me-1"></i>
                                <span class="d-sm-inline d-none">
                                    <?php echo htmlspecialchars($_SESSION['loggedInUser']['firstname'] ?? 'User'); ?>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end px-2 py-3" aria-labelledby="userDropdown">
                                <li>
                                    <a class="dropdown-item border-radius-md" href="profile.php">
                                        <div class="d-flex align-items-center">
                                            <i class="fa fa-user me-2 text-dark"></i>
                                            <span style="color: #2e7d32;">Profile</span>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item border-radius-md" href="logout.php">
                                        <div class="d-flex align-items-center">
                                            <i class="fa fa-sign-out-alt me-2 text-dark"></i>
                                            <span style="color: #2e7d32;">Logout</span>
                                        </div>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php" style="color: #2e7d32;">
                                <i class="fas fa-sign-in-alt me-1"></i> Sign In
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item d-flex align-items-center me-3">
                        <a href="javascript:;" class="nav-link" style="color: #2e7d32;">
                            <i class="fa fa-cog cursor-pointer"></i>
                        </a>
                    </li>
                    <li class="nav-item dropdown d-flex align-items-center">
                        <a href="javascript:;" class="nav-link dropdown-toggle" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="color: #2e7d32;">
                            <i class="fa fa-bell cursor-pointer"></i>
                            <span class="badge bg-danger rounded-pill ms-1" style="background-color: #dc3545;">3</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end px-2 py-3" aria-labelledby="notificationDropdown">
                            <li class="mb-2">
                                <a class="dropdown-item border-radius-md" href="javascript:;">
                                    <div class="d-flex align-items-center py-1">
                                        <div class="me-3">
                                            <i class="fa fa-envelope text-dark" aria-hidden="true"></i>
                                        </div>
                                        <div class="flex-column justify-content-center">
                                            <h6 class="text-sm font-weight-normal mb-1" style="color: #2e7d32;">
                                                <span class="font-weight-bold">New Document Request</span> from John Doe
                                            </h6>
                                            <p class="text-xs text-secondary mb-0">
                                                <i class="fa fa-clock me-1"></i>
                                                13 minutes ago
                                            </p>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li class="mb-2">
                                <a class="dropdown-item border-radius-md" href="javascript:;">
                                    <div class="d-flex align-items-center py-1">
                                        <div class="me-3">
                                            <i class="fa fa-file text-dark" aria-hidden="true"></i>
                                        </div>
                                        <div class="flex-column justify-content-center">
                                            <h6 class="text-sm font-weight-normal mb-1" style="color: #2e7d32;">
                                                <span class="font-weight-bold">Document Approved</span> for Jane Smith
                                            </h6>
                                            <p class="text-xs text-secondary mb-0">
                                                <i class="fa fa-clock me-1"></i>
                                                1 hour ago
                                            </p>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item border-radius-md" href="notifications.php">
                                    <div class="d-flex align-items-center py-1">
                                        <div class="me-3">
                                            <i class="fa fa-bell text-dark" aria-hidden="true"></i>
                                        </div>
                                        <div class="flex-column justify-content-center">
                                            <span style="color: #2e7d32;">View All Notifications</span>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<!-- End Navbar -->