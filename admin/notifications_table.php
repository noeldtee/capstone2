<?php
$page_title = "Notifications";
include '../includes/header.php';
include 'navbar.php';

$stmt = $conn->prepare("SELECT n.id, n.message, n.link, n.is_read, n.created_at FROM notifications n ORDER BY n.created_at DESC");
$stmt->execute();
$notifications = $stmt->get_result();
?>

<div class="main-content">
    <div class="container">
        <h2>Notifications</h2>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Message</th>
                    <th>Link</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($notif = $notifications->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($notif['message']); ?></td>
                        <td><a href="<?php echo $notif['link']; ?>">View</a></td>
                        <td><?php echo $notif['is_read'] ? 'Read' : 'Unread'; ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>