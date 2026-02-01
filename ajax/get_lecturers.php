<?php
require_once '../config.php';

header('Content-Type: application/json');

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Check if user is logged in and has the right role (admin or lecturer)
if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'lecturer'])) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You must be an admin or lecturer to access this page.']);
    exit;
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if ($course_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid course ID']);
    exit;
}

try {
    $db = getDBConnection();
    
    // First, verify the course exists
    $stmt = $db->prepare("SELECT course_id FROM courses WHERE course_id = ?");
    $stmt->execute([$course_id]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Course not found']);
        exit;
    }
    
    // Get only lecturers assigned to the selected course
    $query = "SELECT u.user_id, u.first_name, u.last_name, u.email 
              FROM users u
              JOIN course_assignments ca ON u.user_id = ca.lecturer_id
              WHERE ca.course_id = ?
              ORDER BY u.last_name, u.first_name";
    
    error_log("Lecturer Query: " . $query . " (Course ID: " . $course_id . ")");
    $stmt = $db->prepare($query);
    $stmt->execute([$course_id]);
    $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log for debugging
    error_log("Fetched " . count($lecturers) . " users for course ID: " . $course_id);
    
    // Debug: Log the actual data being returned
    error_log("Lecturers data: " . print_r($lecturers, true));
    
    if (empty($lecturers)) {
        $response = [
            'status' => 'success',
            'data' => [],
            'message' => 'No lecturers found for this course.'
        ];
        error_log("No lecturers found for course ID: " . $course_id);
    } else {
        $response = [
            'status' => 'success',
            'data' => $lecturers,
            'message' => 'Lecturers retrieved successfully.'
        ];
        error_log("Successfully found " . count($lecturers) . " lecturers for course ID: " . $course_id);
    }
    
    // Set the content type and output the JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} catch (PDOException $e) {
    error_log("Error in get_lecturers.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
