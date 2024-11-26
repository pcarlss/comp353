<?php
// Start the session
session_start();

// Destroy all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to the homepage or login page
header("Location: ../account_setup.php"); // Replace with your desired redirection page
exit();
?>
