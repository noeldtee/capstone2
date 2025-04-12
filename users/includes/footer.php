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
        },
        body: JSON.stringify({ user_id: <?= $user_id ?? 'null'; ?> })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('notificationList').innerHTML = '<li>No new notifications.</li>';
            document.getElementById('notificationCount').textContent = '0';
        } else {
            alert('Failed to clear notifications.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while clearing notifications.');
    });
}

// Handle search form submission
function handleSearch(event) {
    event.preventDefault();
    const searchInput = document.getElementById('searchInput').value;
    console.log('Search query:', searchInput);
    // Implement search functionality here (e.g., redirect to a search results page)
}

// Clear search input and submit form
function clearSearchAndSubmit() {
    document.getElementById('searchInput').value = '';
    document.querySelector('.search-form').submit();
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