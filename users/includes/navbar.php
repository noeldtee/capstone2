<?php
// Ensure variables are available (they should be set in user_data.php)
$user = $user ?? [];
$notification_count = $notification_count ?? 0;
$notifications = $notifications ?? [];
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
                                    <a href="<?= htmlspecialchars($notification['link']); ?>">
                                        <?= htmlspecialchars($notification['message']); ?>
                                        <small><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($notification['created_at']))); ?></small>
                                    </a>
                                    <?php if (!$notification['is_read']): ?>
                                        <button class="mark-read-btn" onclick="markNotificationAsRead(<?= htmlspecialchars($notification['id']); ?>)">
                                            Mark as Read
                                        </button>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No new notifications.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="user dropdown">
                <h3>Hello, <?= htmlspecialchars($user['firstname'] ?? 'User'); ?>!</h3>
                <a href="#" class="bg-img dropdown-toggle" style="background-image: url('<?= htmlspecialchars($profile_image); ?>'); background-size: cover; background-position: center;" data-bs-toggle="dropdown" aria-expanded="false"></a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>