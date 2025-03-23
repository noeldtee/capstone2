<?php

require '../config/function.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        Dashboard
    </title>

    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.lineicons.com/4.0/lineicons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">

</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="side-content">
            <div class="profile">
                <!-- Logo -->
                <div class="profile-img bg-img" style="background-image: url(../assets/images/logo.png);"></div>
                <h5>BPC Document Request System</h>

            </div>
            <div class="side-menu">
                <ul class="container">
                    <li>
                        <a href="dashboard" class="active">
                            <span class="las la-home"></span>
                            <small>Dashboard</small>
                        </a>
                    </li>
                    <li>
                        <a href="request">
                            <span class="las la-file-alt"></span>
                            <small>Request a Document</small>
                        </a>
                    </li>
                    <li>
                        <a href="track">
                            <span class="las la-search"></span>
                            <small>Track Your Request</small>
                        </a>
                    </li>
                    <li>
                        <a href="history">
                            <span class="las la-history"></span>
                            <small>Request History</small>
                        </a>
                    </li>
                    <li>
                        <a href="setting">
                            <span class="las la-cog"></span>
                            <small>Settings</small>
                        </a>
                    </li>
                    <li class="logout">
                        <a href="../logout.php">
                            <span class="las la-sign-out-alt"></span>
                            <small>Logout</small>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <div class="header-content">
                <label for="menu-toggle" class="toggle">
                    <span class="las la-bars"></span>
                </label>
                <div class="header-menu">
                    <form class="d-flex position-relative" role="search" method="GET" action="" id="searchForm">
                        <div class="position-relative">
                            <input
                                class="form-control me-2" style="width: 30rem;"
                                type="search"
                                placeholder="Search"
                                aria-label="Search"
                                name="search"
                                value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                                id="searchInput">
                            <!-- Clear Button -->
                            <button
                                type="button"
                                class="btn position-absolute top-50 translate-middle-y end-0"
                                aria-label="Clear search"
                                onclick="clearSearchAndSubmit()"
                                style="background: none; border: none; font-size: 1.5rem; color: #6c757d; cursor: pointer; margin-right: .6rem; margin-top: 2px;">
                                &times;
                            </button>
                        </div>
                        <button class="btn btn-outline-dark ms-2" style="margin-right: 2rem;" type="submit">Search</button>
                    </form>
                    <div class="notify-icon">
                        <span class="las la-bell"></span>
                        <span class="notify" id="notificationCount">3</span>
                    </div>
                    <div class="user">
                        <h3>Hello User</h3>
                        <a href="" class="bg-img"></a>
                    </div>
                </div>
            </div>
        </header>
        <main>
            <div class="page-header">
                <h1>Dashboard</h1>
                <small>Welcome back! Here's an overview of your activity.</small>
            </div>
            <div class="page-content">
                <!-- Analytics Cards -->
                <div class="analytics">
                    <div class="card">
                        <div class="card-head">
                            <h2>1</h2>
                            <span class="las la-user-friends"></span>
                        </div>
                        <div class="card-progress">
                            <small>Total Documents Requested</small>
                            <h6>This is the total number of documents you've requested so far.</h6>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-head">
                            <h2>3</h2>
                            <span class="las la-hourglass-half"></span>
                        </div>
                        <div class="card-progress">
                            <small>Total Documents Pending</small>
                            <h6>Documents that are still being processed or reviewed.</h6>
                        </div>
                    </div>
                    <div class="card2">
                        <small>Click Below to Get Started on Your Document Request!</small>
                        <div class="card-head2">
                            <a href="">Request a Document</a>
                        </div>
                    </div>
                </div>
                <div class="records table-responsive">
                    <div class="record-header">
                        <div class="add">
                            <span>Recent Activity</span>
                        </div>
                    </div>
                    <div>
                        <table width="100%">
                            <thead>
                                <tr>
                                    <th>Document Requested</th>
                                    <th>Price</th>
                                    <th>Requested Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                        <tr>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td>
                                            </td>
                                        </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php include('../includes/footer.php'); ?>