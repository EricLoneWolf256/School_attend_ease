<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'attendance_system');
define('DB_USER', 'root');  // Default XAMPP username
define('DB_PASS', '');      // Default XAMPP password

// Application settings
define('SITE_NAME', 'AttendEase');
define('SITE_URL', 'http://localhost/ghost');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection function
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Redirect function
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Check user role
function checkRole($allowed_roles) {
    if (!isLoggedIn() || !in_array($_SESSION['role'], (array)$allowed_roles)) {
        $_SESSION['error'] = 'Access denied. You do not have permission to access this page.';
        redirect('index.php');
    }
}

// CSRF token functions are now in includes/session.php

// Generate random string for secret code
function generateSecretCode($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Format date for display
function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

// Format time for display
function formatTime($time) {
    return date('h:i A', strtotime($time));
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>
