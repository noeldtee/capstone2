<?php
session_start();
require '../config/function.php';

logoutSession();
redirect('../index.php', 'You have been logged out.');
?>