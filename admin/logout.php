<?php
session_start();

// remove all session variables
$_SESSION = [];

// destroy the session
session_destroy();

// redirect to login page
header("Location: admin_login.php");
exit();
?>