<?php
require $_SERVER['DOCUMENT_ROOT'] . '/capstone-admin/config/function.php';

// Restrict to authenticated admin, registrar, or cashier
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['registrar', 'staff', 'cashier'])) {
    redirect('../index.php', 'Please log in as an admin, registrar, or cashier to access this page.', 'warning');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php
        if (isset($page_title)) {
            echo htmlspecialchars($page_title);
        } else {
            echo "BPC Document Request System";
        }
        ?>
    </title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdn.lineicons.com/4.0/lineicons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
</head>

<body class="">

    <?php include('sidebar.php') ?>

    <div class="main-content">

        <?php include('navbar.php') ?>