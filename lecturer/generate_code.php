<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/attendance_functions.php';

if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'lecturer'])) {
    $_SESSION['error'] = 'Access denied. You must be a lecturer to access this page.';
    header('Location: ../index.php');
    exit();
}

$lecture_id = isset($_GET['lecture_id']) ? (int)$_GET['lecture_id'] : 0;
$lecturer_id = $_SESSION['user_id'];

if ($lecture_id <= 0) {
    $_SESSION['error'] = 'Invalid lecture ID.';
    header('Location: lectures.php');
    exit();
}

// Generate the code (15 minutes expiry by default)
$result = generateAttendanceCode(getDBConnection(), $lecture_id, $lecturer_id, 15);

if ($result['status'] === 'success') {
    $_SESSION['success'] = "Attendance code generated: <strong>{$result['code']}</strong> (expires at " . 
                          date('g:i A', strtotime($result['expires_at'])) . ")";
} else {
    $_SESSION['error'] = $result['message'];
}

header('Location: lectures.php');
exit();
