<?php
session_start();
require 'config/function.php';

// Check if user is logged in and authorized
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['admin', 'registrar'])) {
    redirect('index.php', 'Please log in as an admin or registrar to access this page.', 'warning');
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$request = null;
$message = '';
$message_type = '';

// Validate the token and fetch request details
if ($token) {
    try {
        $stmt = $conn->prepare("SELECT r.id, r.document_type, r.status, u.firstname, u.lastname, u.number 
                                FROM requests r 
                                JOIN users u ON r.user_id = u.id 
                                WHERE r.pickup_token = ? AND r.status = 'Ready to Pickup'");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $request = $result->fetch_assoc();
        } else {
            $message = "Invalid or expired QR code. Please verify the request manually or contact the registrar.";
            $message_type = "danger";
        }
        $stmt->close();
    } catch (Exception $e) {
        $message = "Error verifying QR code: " . $e->getMessage();
        $message_type = "danger";
    }
} else {
    $message = "No token provided. Please scan a valid QR code.";
    $message_type = "warning";
}

// Handle marking as completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_completed' && isset($_POST['id'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid CSRF token. Please try again.";
        $message_type = "danger";
    } else {
        $id = (int)$_POST['id'];
        try {
            $stmt = $conn->prepare("UPDATE requests SET status = 'Completed', pickup_token = NULL, updated_at = NOW() WHERE id = ? AND status = 'Ready to Pickup'");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $qr_file = "Uploads/qrcodes/request_$id.png";
                if (file_exists($qr_file)) {
                    unlink($qr_file);
                }
                logAction($conn, 'Mark Completed via QR', "Request ID: $id", "Status changed to Completed via QR verification");
                $message = "Request ID $id marked as Completed.";
                $message_type = "success";
                $request = null; // Clear request to prevent re-display
            } else {
                $message = "Failed to mark request as completed or request is not Ready to Pickup.";
                $message_type = "danger";
            }
            $stmt->close();
        } catch (Exception $e) {
            $message = "Error marking request as completed: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify QR Code - BPC Registrar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .mark-completed-btn .spinner-border {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Verify QR Code</h2>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($request): ?>
            <div class="card">
                <div class="card-header">
                    Request Details
                </div>
                <div class="card-body">
                    <p><strong>Request ID:</strong> <?php echo htmlspecialchars($request['id']); ?></p>
                    <p><strong>Document Type:</strong> <?php echo htmlspecialchars($request['document_type']); ?></p>
                    <p><strong>Student Name:</strong> <?php echo htmlspecialchars($request['firstname'] . ' ' . $request['lastname']); ?></p>
                    <p><strong>Student Number:</strong> <?php echo htmlspecialchars($request['number'] ?: 'N/A'); ?></p>
                    <p><strong>Status:</strong> <span class="badge bg-success"><?php echo htmlspecialchars($request['status']); ?></span></p>
                    <form method="POST" action="verify_qr.php?token=<?php echo htmlspecialchars($token); ?>" id="markCompletedForm">
                        <input type="hidden" name="action" value="mark_completed">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($request['id']); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <button type="submit" class="btn btn-primary mark-completed-btn">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                            Mark as Completed
                        </button>
                        <a href="admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <p>Scan a QR code to verify a request, or return to the <a href="admin/request.php">requests page</a> for manual verification.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('markCompletedForm')?.addEventListener('submit', function (e) {
            const button = this.querySelector('.mark-completed-btn');
            const spinner = button.querySelector('.spinner-border');
            spinner.style.display = 'inline-block';
            button.disabled = true;
            button.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Completing...`;
        });
    </script>
</body>
</html>