<?php
$page_title = "Cashier Dashboard";
include('../includes/header.php');

// Restrict to authenticated students/alumni
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['cashier'])) {
    redirect('../index.php', 'Please log in as a cashier to access this page.', 'warning');
    exit();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['loggedInUser']['firstname'] . ' ' . $_SESSION['loggedInUser']['lastname']); ?>!</h2>
            <p>This is your dashboard.</p>
            <a href="../logout.php" class="btn btn-danger">Logout</a> <!-- Root logout.php -->
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>