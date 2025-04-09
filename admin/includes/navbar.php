<header>
    <div class="header-content">
        <div class="toggle" onclick="toggleSidebar()">
            <span class="fa-solid fa-bars"></span>
        </div>
        <div class="header-right">
            <div class="notify-icon" onclick="toggleNotifications()">
                <span class="las la-bell"></span>
                <span class="notify" id="notificationCount">0</span>
                <!-- Notification Dropdown -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h4>Notifications</h4>
                        <button class="clear-all" onclick="clearAllNotifications()">Clear All</button>
                    </div>
                    <ul class="notification-list" id="notificationList">
                        <li style="padding: 0.75rem 1rem; color: #6c757d;">Loading notifications...</li>
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