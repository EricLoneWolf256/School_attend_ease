<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

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

try {
    $db = getDBConnection();
    
    // Verify the lecture belongs to the lecturer
    $stmt = $db->prepare(
        "UPDATE lectures l
         JOIN course_assignments ca ON l.course_id = ca.course_id
         SET l.secret_code = NULL,
             l.code_expiry = NULL,
             l.is_active = 0,
             l.updated_at = NOW()
         WHERE l.lecture_id = ? AND ca.lecturer_id = ?"
    );
    $stmt->execute([$lecture_id, $lecturer_id]);

    if ($stmt->rowCount() > 0) {
        logAction($db, $lecturer_id, 'attendance', 'stop', "Stopped attendance for lecture ID: $lecture_id");
        $_SESSION['success'] = 'Attendance tracking has been stopped for this lecture.';
    } else {
        $_SESSION['error'] = 'Lecture not found or you do not have permission to modify it.';
    }
} catch (PDOException $e) {
    error_log("Error stopping attendance: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while stopping attendance.';
}

header('Location: lectures.php');
exit();
