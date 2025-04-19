<script>
// Toggle sidebar
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('collapsed');
    document.querySelector('.main-content').classList.toggle('collapsed');
    document.querySelector('header').classList.toggle('collapsed');
}

// Toggle notification dropdown
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('show');
}

// Clear all notifications
function clearAllNotifications() {
    fetch('clear_notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            document.getElementById('notificationList').innerHTML = '<li>No new notifications.</li>';
            document.getElementById('notificationCount').textContent = '0';
        } else {
            alert('Failed to clear notifications: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while clearing notifications: ' + error.message);
    });
}

// Mark a single notification as read
function markNotificationAsRead(notificationId) {
    fetch('mark_notification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `notification_id=${notificationId}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Remove the "Mark as Read" button
            const notificationItem = document.querySelector(`li[data-notification-id="${notificationId}"]`);
            const markReadBtn = notificationItem.querySelector('.mark-read-btn');
            if (markReadBtn) {
                markReadBtn.remove();
            }
            // Update the notification count
            document.getElementById('notificationCount').textContent = data.unread_count;
        } else {
            alert('Failed to mark notification as read: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while marking the notification as read: ' + error.message);
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const notifyIcon = document.querySelector('.notify-icon');
    const notificationDropdown = document.getElementById('notificationDropdown');
    if (!notifyIcon.contains(event.target) && !notificationDropdown.contains(event.target)) {
        notificationDropdown.classList.remove('show');
    }
});
</script>

<!-- Load scripts for all users -->
<script src="../assets/js/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

</body>
</html>