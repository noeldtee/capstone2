<?php
require 'config/function.php'; // Include function.php for logoutSession() and redirect()

// Call the logout function
logoutSession();

// Redirect to the login page (index.php) after logout
redirect('index.php', 'You have been logged out successfully.', 'success');