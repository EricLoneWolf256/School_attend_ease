<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

// Ensure user is logged in and is an admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

echo "<h1>Test Reports Page</h1>";
echo "<p>If you can see this, the reports page is working!</p>";

// Include the footer
include '../includes/footer.php';
?>
