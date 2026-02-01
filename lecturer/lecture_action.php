<?php
require_once '../config.php';
require_once '../includes/session.php';

// Check if user is logged in and is a lecturer
if (!isLoggedIn() || $_SESSION['role'] !== 'lecturer') {
    $_SESSION['error'] = 'Access denied. You must be a lecturer to access this page.';
    redirect('../index.php');
}

$db = getDBConnection();

// Debug: Check session data
error_log('Session data: ' . print_r($_SESSION, true));

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $_SESSION['error'] = 'User not properly authenticated. Please log in again.';
    redirect('../login.php');
}

$lecturer_id = (int)$_SESSION['user_id'];

// Verify the user exists in the database
try {
    $stmt = $db->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'lecturer'");
    $stmt->execute([$lecturer_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = 'Lecturer account not found. Please contact support.';
        error_log("Lecturer ID $lecturer_id not found in database");
        redirect('../index.php');
    }
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    $_SESSION['error'] = 'A database error occurred. Please try again.';
    redirect('lectures.php');
}
$action = $_GET['action'] ?? '';

// Process based on action
switch ($action) {
    case 'add':
        handleAddLecture($db, $lecturer_id);
        break;
    case 'edit':
        handleEditLecture($db, $lecturer_id);
        break;
    case 'generate_code':
        handleGenerateCode($db, $lecturer_id);
        break;
    case 'stop_attendance':
        handleStopAttendance($db, $lecturer_id);
        break;
    case 'duplicate':
        handleDuplicateLecture($db, $lecturer_id);
        break;
    default:
        $_SESSION['error'] = 'Invalid action specified.';
        redirect('lectures.php');
}

/**
 * Handle adding a new lecture
 */
function handleAddLecture($db, $lecturer_id) {
    // Verify CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        redirect('lectures.php');
    }
    
    // Get form data
    $course_id = (int)($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $scheduled_date = $_POST['scheduled_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    
    // Validate input
    $errors = [];
    
    // Check if course is assigned to lecturer
    if (!isCourseAssigned($db, $course_id, $lecturer_id)) {
        $errors[] = 'Invalid course selected.';
    }
    
    if (empty($title)) {
        $errors[] = 'Lecture title is required.';
    }
    
    if (empty($scheduled_date) || empty($start_time) || empty($end_time)) {
        $errors[] = 'Date and time are required.';
    } else {
        $start_datetime = new DateTime($scheduled_date . ' ' . $start_time);
        $end_datetime = new DateTime($scheduled_date . ' ' . $end_time);
        
        if ($end_datetime <= $start_datetime) {
            $errors[] = 'End time must be after start time.';
        }
        
        // Check for scheduling conflicts
        $conflict = checkSchedulingConflict($db, $lecturer_id, $start_datetime, $end_datetime, 0);
        if ($conflict) {
            $errors[] = 'You already have a lecture scheduled during this time.';
        }
    }
    
    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        $_SESSION['form_data'] = [
            'course_id' => $course_id,
            'title' => $title,
            'description' => $description,
            'scheduled_date' => $scheduled_date,
            'start_time' => $start_time,
            'end_time' => $end_time
        ];
        redirect('lectures.php?action=add');
    }
    
    // Insert new lecture
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare(
            "INSERT INTO lectures 
             (course_id, lecturer_id, title, description, scheduled_date, start_time, end_time, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        
        $stmt->execute([
            $course_id,
            $lecturer_id,  // This will be stored in the user_id column
            $title,
            $description,
            $scheduled_date,
            $start_time,
            $end_time
        ]);
        
        $lecture_id = $db->lastInsertId();
        
        // Log the action
        logAction($db, $lecturer_id, 'lecture', 'create', "Created lecture: $title (ID: $lecture_id)");
        
        $db->commit();
        
        $_SESSION['success'] = 'Lecture scheduled successfully!';
        redirect('lecture.php?id=' . $lecture_id);
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error scheduling lecture: ' . $e->getMessage();
        redirect('lectures.php');
    }
}

/**
 * Handle editing an existing lecture
 */
function handleEditLecture($db, $lecturer_id) {
    // Verify CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        redirect('lectures.php');
    }
    
    // Get lecture ID
    $lecture_id = (int)($_GET['id'] ?? 0);
    if ($lecture_id <= 0) {
        $_SESSION['error'] = 'Invalid lecture ID.';
        redirect('lectures.php');
    }
    
    // Verify lecture exists and belongs to lecturer
    $lecture = getLecture($db, $lecture_id, $lecturer_id);
    if (!$lecture) {
        $_SESSION['error'] = 'Lecture not found or access denied.';
        redirect('lectures.php');
    }
    
    // Get form data
    $course_id = (int)($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $scheduled_date = $_POST['scheduled_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    
    // Validate input
    $errors = [];
    
    // Check if course is assigned to lecturer
    if (!isCourseAssigned($db, $course_id, $lecturer_id)) {
        $errors[] = 'Invalid course selected.';
    }
    
    if (empty($title)) {
        $errors[] = 'Lecture title is required.';
    }
    
    if (empty($scheduled_date) || empty($start_time) || empty($end_time)) {
        $errors[] = 'Date and time are required.';
    } else {
        $start_datetime = new DateTime($scheduled_date . ' ' . $start_time);
        $end_datetime = new DateTime($scheduled_date . ' ' . $end_time);
        
        if ($end_datetime <= $start_datetime) {
            $errors[] = 'End time must be after start time.';
        }
        
        // Check for scheduling conflicts (excluding current lecture)
        $conflict = checkSchedulingConflict($db, $lecturer_id, $start_datetime, $end_datetime, $lecture_id);
        if ($conflict) {
            $errors[] = 'You already have another lecture scheduled during this time.';
        }
    }
    
    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        $_SESSION['form_data'] = [
            'course_id' => $course_id,
            'title' => $title,
            'description' => $description,
            'scheduled_date' => $scheduled_date,
            'start_time' => $start_time,
            'end_time' => $end_time
        ];
        redirect('lectures.php?action=edit&id=' . $lecture_id);
    }
    
    // Update lecture
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare(
            "UPDATE lectures 
             SET course_id = ?, title = ?, description = ?, 
                 scheduled_date = ?, start_time = ?, end_time = ?, 
                 updated_at = NOW() 
             WHERE lecture_id = ?"
        );
        
        $stmt->execute([
            $course_id,
            $title,
            $description,
            $scheduled_date,
            $start_time,
            $end_time,
            $lecture_id
        ]);
        
        // Log the action
        logAction($db, $lecturer_id, 'lecture', 'update', "Updated lecture: $title (ID: $lecture_id)");
        
        $db->commit();
        
        $_SESSION['success'] = 'Lecture updated successfully!';
        redirect('lecture.php?id=' . $lecture_id);
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error updating lecture: ' . $e->getMessage();
        redirect('lectures.php?action=edit&id=' . $lecture_id);
    }
}

/**
 * Handle generating an attendance code for a lecture
 */
function handleGenerateCode($db, $lecturer_id) {
    // Verify CSRF token if this is a POST request
    $token = $_POST['csrf_token'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCSRFToken($token)) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        redirect('lectures.php');
    }
    
    // Get lecture ID
    $lecture_id = (int)($_POST['lecture_id'] ?? ($_GET['id'] ?? 0));
    if ($lecture_id <= 0) {
        $_SESSION['error'] = 'Invalid lecture ID.';
        redirect('lectures.php');
    }
    
    // Verify lecture exists and belongs to lecturer
    $lecture = getLecture($db, $lecture_id, $lecturer_id);
    if (!$lecture) {
        $_SESSION['error'] = 'Lecture not found or access denied.';
        redirect('lectures.php');
    }
    
    // Check if lecture is in the past
    $now = new DateTime();
    $lecture_end = new DateTime($lecture['scheduled_date'] . ' ' . $lecture['end_time']);
    
    if ($now > $lecture_end) {
        $_SESSION['error'] = 'Cannot generate code for a past lecture.';
        redirect('lecture.php?id=' . $lecture_id);
    }
    
    // Generate a random 6-character code
    $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    
    try {
        $db->beginTransaction();
        
        // First, deactivate any other active codes for this lecturer
        $stmt = $db->prepare(
            "UPDATE lectures l 
             JOIN course_assignments ca ON l.course_id = ca.course_id
             SET l.is_active = 0, l.updated_at = NOW() 
             WHERE ca.lecturer_id = ? AND l.is_active = 1"
        );
        $stmt->execute([$lecturer_id]);
        
        // Then activate the current lecture
        $stmt = $db->prepare(
            "UPDATE lectures 
             SET is_active = 1, secret_code = ?, code_generated_at = NOW(), updated_at = NOW() 
             WHERE lecture_id = ?"
        );
        $stmt->execute([$code, $lecture_id]);
        
        // Log the action
        logAction($db, $lecturer_id, 'attendance', 'generate_code', 
                 "Generated attendance code for lecture ID: $lecture_id");
        
        $db->commit();
        
        $_SESSION['success'] = 'Attendance code generated successfully!';
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error generating attendance code: ' . $e->getMessage();
    }
    
    // Redirect back to the lecture page or to the dashboard
    $redirect = isset($_GET['from']) && $_GET['from'] === 'dashboard' ? 'dashboard.php' : 'lecture.php?id=' . $lecture_id;
    redirect($redirect);
}

/**
 * Handle stopping attendance collection for a lecture
 */
function handleStopAttendance($db, $lecturer_id) {
    // Verify CSRF token if this is a POST request
    $token = $_POST['csrf_token'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCSRFToken($token)) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        redirect('lectures.php');
    }
    
    // Get lecture ID
    $lecture_id = (int)($_POST['lecture_id'] ?? ($_GET['id'] ?? 0));
    if ($lecture_id <= 0) {
        $_SESSION['error'] = 'Invalid lecture ID.';
        redirect('lectures.php');
    }
    
    // Verify lecture exists and belongs to lecturer
    $lecture = getLecture($db, $lecture_id, $lecturer_id);
    if (!$lecture) {
        $_SESSION['error'] = 'Lecture not found or access denied.';
        redirect('lectures.php');
    }
    
    try {
        $db->beginTransaction();
        
        // Deactivate the lecture
        $stmt = $db->prepare(
            "UPDATE lectures 
             SET is_active = 0, secret_code = NULL, code_generated_at = NULL, updated_at = NOW() 
             WHERE lecture_id = ?"
        );
        $stmt->execute([$lecture_id]);
        
        // Log the action
        logAction($db, $lecturer_id, 'attendance', 'stop', 
                 "Stopped attendance collection for lecture ID: $lecture_id");
        
        $db->commit();
        
        $_SESSION['success'] = 'Attendance collection stopped successfully.';
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error stopping attendance: ' . $e->getMessage();
    }
    
    // Redirect back to the lecture page or to the dashboard
    $redirect = isset($_GET['from']) && $_GET['from'] === 'dashboard' ? 'dashboard.php' : 'lecture.php?id=' . $lecture_id;
    redirect($redirect);
}

/**
 * Handle duplicating a lecture
 */
function handleDuplicateLecture($db, $lecturer_id) {
    // Verify CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        redirect('lectures.php');
    }
    
    // Get lecture ID
    $lecture_id = (int)($_GET['id'] ?? 0);
    if ($lecture_id <= 0) {
        $_SESSION['error'] = 'Invalid lecture ID.';
        redirect('lectures.php');
    }
    
    // Verify lecture exists and belongs to lecturer
    $lecture = getLecture($db, $lecture_id, $lecturer_id);
    if (!$lecture) {
        $_SESSION['error'] = 'Lecture not found or access denied.';
        redirect('lectures.php');
    }
    
    // Set the new date (default to next week)
    $new_date = new DateTime();
    $new_date->modify('+1 week');
    $new_date_str = $new_date->format('Y-m-d');
    
    try {
        $db->beginTransaction();
        
        // Insert the new lecture
        $stmt = $db->prepare(
            "INSERT INTO lectures 
             (course_id, title, description, scheduled_date, start_time, end_time, created_at) 
             SELECT course_id, CONCAT(title, ' (Copy)'), description, ?, start_time, end_time, NOW() 
             FROM lectures 
             WHERE lecture_id = ?"
        );
        
        $stmt->execute([$new_date_str, $lecture_id]);
        $new_lecture_id = $db->lastInsertId();
        
        // Log the action
        logAction($db, $lecturer_id, 'lecture', 'duplicate', 
                 "Duplicated lecture from ID: $lecture_id to ID: $new_lecture_id");
        
        $db->commit();
        
        $_SESSION['success'] = 'Lecture duplicated successfully!';
        redirect('lecture.php?id=' . $new_lecture_id);
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error duplicating lecture: ' . $e->getMessage();
        redirect('lecture.php?id=' . $lecture_id);
    }
}

/**
 * Check if a course is assigned to a lecturer
 */
function isCourseAssigned($db, $course_id, $lecturer_id) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) as count 
         FROM course_assignments 
         WHERE course_id = ? AND lecturer_id = ?"
    );
    $stmt->execute([$course_id, $lecturer_id]);
    $result = $stmt->fetch();
    return $result && $result['count'] > 0;
}

/**
 * Check for scheduling conflicts
 */
function checkSchedulingConflict($db, $lecturer_id, $start_datetime, $end_datetime, $exclude_lecture_id = 0) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) as count 
         FROM lectures l
         JOIN course_assignments ca ON l.course_id = ca.course_id
         WHERE ca.lecturer_id = ?
         AND l.lecture_id != ?
         AND l.scheduled_date = ?
         AND (
             (l.start_time <= ? AND l.end_time > ?) OR  -- New lecture starts during existing
             (l.start_time < ? AND l.end_time >= ?) OR  -- New lecture ends during existing
             (l.start_time >= ? AND l.end_time <= ?)    -- New lecture is within existing
         )"
    );
    
    $start_time = $start_datetime->format('H:i:s');
    $end_time = $end_datetime->format('H:i:s');
    $date = $start_datetime->format('Y-m-d');
    
    $stmt->execute([
        $lecturer_id,
        $exclude_lecture_id,
        $date,
        $start_time, $start_time,
        $end_time, $end_time,
        $start_time, $end_time
    ]);
    
    $result = $stmt->fetch();
    return $result && $result['count'] > 0;
}

/**
 * Get lecture details if it belongs to the lecturer
 */
function getLecture($db, $lecture_id, $lecturer_id) {
    $stmt = $db->prepare(
        "SELECT l.* 
         FROM lectures l
         JOIN course_assignments ca ON l.course_id = ca.course_id
         WHERE l.lecture_id = ? AND ca.lecturer_id = ?"
    );
    $stmt->execute([$lecture_id, $lecturer_id]);
    return $stmt->fetch();
}

/**
 * Log an action to the activity log
 */
function logAction($db, $user_id, $entity_type, $action, $details) {
    try {
        $stmt = $db->prepare(
            "INSERT INTO activity_log 
             (user_id, entity_type, action, details, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->execute([
            $user_id,
            $entity_type,
            $action,
            $details,
            $ip_address,
            $user_agent
        ]);
        
        return true;
    } catch (PDOException $e) {
        // Log to error log if database logging fails
        error_log('Failed to log action: ' . $e->getMessage());
        return false;
    }
}

?>
