<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if (empty($query)) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $db = getDBConnection();
    $searchTerm = "%$query%";
    
    // Search in different tables based on user role
    $searchResults = [];
    
    // Search in users table (for admins)
    if ($_SESSION['role'] === 'admin') {
        $stmt = $db->prepare("
            SELECT 
                user_id as id,
                CONCAT(first_name, ' ', last_name) as title,
                email as description,
                CONCAT('/ghost/admin/student_details.php?id=', user_id) as url,
                'student' as type
            FROM users 
            WHERE (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)
            AND role = 'student'
            LIMIT 5
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $searchResults = array_merge($searchResults, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Search in courses table
    $stmt = $db->prepare("
        SELECT 
            course_id as id,
            course_name as title,
            course_code as description,
            CONCAT('/ghost/course_details.php?id=', course_id) as url,
            'course' as type
        FROM courses 
        WHERE (course_name LIKE ? OR course_code LIKE ? OR description LIKE ?)
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $searchResults = array_merge($searchResults, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // For lecturers, only show their assigned courses
    if ($_SESSION['role'] === 'lecturer') {
        $stmt = $db->prepare("
            SELECT 
                c.course_id as id,
                c.course_name as title,
                c.course_code as description,
                CONCAT('/ghost/lecturer/course_details.php?id=', c.course_id) as url,
                'course' as type
            FROM courses c
            JOIN course_assignments ca ON c.course_id = ca.course_id
            WHERE ca.lecturer_id = ? 
            AND (c.course_name LIKE ? OR c.course_code LIKE ?)
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id'], $searchTerm, $searchTerm]);
        $searchResults = array_merge($searchResults, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    echo json_encode(['results' => $searchResults]);
    
} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while searching']);
}
