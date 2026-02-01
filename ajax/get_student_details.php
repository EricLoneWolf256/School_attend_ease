<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if (empty($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Student ID is required']);
    exit();
}

$user_id = (int)$_GET['id'];

try {
    // Get student details with user information
    $query = "SELECT u.*, s.student_id, s.program, s.year_level, s.status 
              FROM users u 
              JOIN students s ON u.user_id = s.user_id 
              WHERE u.user_id = ? AND u.role = 'student'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Student not found');
    }
    
    $student = $result->fetch_assoc();
    
    // Remove sensitive data
    unset($student['password']);
    
    echo json_encode([
        'status' => 'success', 
        'data' => $student
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}
?>
