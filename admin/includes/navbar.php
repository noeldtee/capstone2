<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure database connection is available
if (!isset($conn)) {
    $config_path = $_SERVER['DOCUMENT_ROOT'] . '/capstone-admin/config/dbcon.php';
    if (!file_exists($config_path)) {
        die('Error: Database configuration file not found at ' . $config_path);
    }
    require $config_path;
}

// Fetch admin notifications
$admin_id = $_SESSION['user_id'] ?? null; // Assuming admin_id is stored in user_id
$notification_count = 0;
$notifications = [];

if ($admin_id) {
    $stmt = $conn->prepare("SELECT * FROM admin_notifications WHERE admin_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        die('Error: Failed to prepare statement for fetching notifications.');
    }
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $notification_count = count($notifications);
    $stmt->close();
} else {
    error_log('Admin ID not found in session. User may not be logged in.');
}
?>

<header>
    <div class="header-content">
        <div class="toggle" onclick="toggleSidebar()">
            <span class="fa-solid fa-bars"></span>
        </div>
        <div class="header-right">
            <div class="notify-icon" onclick="toggleNotifications()">
                <span class="las la-bell"></span>
                <span class="notify" id="notificationCount"><?= htmlspecialchars($notification_count); ?></span>
                <!-- Notification Dropdown -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h4>Notifications</h4>
                        <button class="clear-all" onclick="clearAllNotifications()">Clear All</button>
                    </div>
                    <ul class="notification-list" id="notificationList">
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <li data-notification-id="<?= htmlspecialchars($notification['id']); ?>">
                                    <div class="notification-content">
                                        <a href="<?= htmlspecialchars($notification['link']); ?>" onclick="markNotificationRead(<?= htmlspecialchars($notification['id']); ?>)">
                                            <?= htmlspecialchars($notification['message']); ?>
                                            <small><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($notification['created_at']))); ?></small>
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li style="padding: 0.75rem 1rem; color: #6c757d;">No new notifications</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="user">
                <h3>Hello! Admin</h3>
                <a href="" class="bg-img" style="background-image: url(../assets/images/default_profile.png);"></a>
            </div>
        </div>
    </div>
</header>