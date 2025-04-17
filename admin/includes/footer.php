<!-- JavaScript (place before </body> in footer.php or in header.php) -->
<script>
    // Fetch notifications from API
    // Fetch notifications from API
function fetchNotifications() {
    fetch('notifications.php?action=fetch')
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                renderNotifications(data.notifications);
            } else {
                console.error('Failed to fetch notifications:', data.message);
                renderNotifications([]);
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
            renderNotifications([]);
        });
}

// Render notifications and update count
function renderNotifications(notifications) {
    const notificationList = document.getElementById('notificationList');
    const notificationCount = document.getElementById('notificationCount');

    notificationList.innerHTML = '';
    notificationCount.textContent = notifications.length;

    if (notifications.length === 0) {
        notificationCount.style.display = 'none';
        notificationList.innerHTML = '<li style="padding: 0.75rem 1rem; color: #6c757d;">No new notifications</li>';
    } else {
        notificationCount.style.display = 'block';
        notifications.forEach(notification => {
            const li = document.createElement('li');
            li.innerHTML = `<a href="${notification.link}" onclick="markNotificationRead(${notification.id})">${notification.message} <small>(${notification.created_at})</small></a>`;
            notificationList.appendChild(li);
        });
    }
}

// Toggle notification dropdown
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('active');
    if (dropdown.classList.contains('active')) {
        fetchNotifications(); // Refresh when opened
    }
}

// Mark a notification as read
function markNotificationRead(id) {
    fetch('notifications.php?action=mark_read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                fetchNotifications(); // Refresh notifications
            } else {
                console.error('Failed to mark notification as read:', data.message);
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
}

// Clear all notifications
function clearAllNotifications() {
    fetch('notifications.php?action=clear_all', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                fetchNotifications(); // Refresh notifications
                document.getElementById('notificationDropdown').classList.remove('active');
            } else {
                console.error('Failed to clear notifications:', data.message);
            }
        })
        .catch(error => console.error('Error clearing notifications:', error));
}

// Initialize and poll notifications
document.addEventListener('DOMContentLoaded', () => {
    fetchNotifications();
    setInterval(fetchNotifications, 30000); // Poll every 30 seconds
});

// Close dropdown when clicking outside
document.addEventListener('click', (event) => {
    const notifyIcon = document.querySelector('.notify-icon');
    const dropdown = document.getElementById('notificationDropdown');
    if (!notifyIcon.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.remove('active');
    }
});

function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('collapsed');
    document.querySelector('.main-content').classList.toggle('collapsed');
    document.querySelector('header').classList.toggle('collapsed');
}
    // Handle logout confirmation
    document.getElementById('confirmLogout').addEventListener('click', function() {
        window.location.href = '../logout.php';
    });
</script>

<!-- Load scripts for all users -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="../assets/js/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

</body>

</html>