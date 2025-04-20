<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the user out
session_unset();     // Remove all session variables
session_destroy();   // Destroy the session

// Set a flash message
setFlashMessage("You have successfully logged out.", "success");

// Redirect to login page
redirect("login.php");
?> 