<?php
// logout.php – log the user out by clearing session data
require_once 'config.php';

// Destroy all session variables
$_SESSION = [];
session_unset();
session_destroy();

// Redirect to the login page
header('Location: index.php');
exit();