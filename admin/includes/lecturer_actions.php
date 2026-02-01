<?php
// Start output buffering at the very beginning
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

// Disable error output to the browser
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../php_errors.log');
error_reporting(E_ALL);

// Function to log errors
function logError($message) {
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    error_log($message);
}

// Include required files
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/session.php';

// Function to send JSON response and exit
function sendJsonResponse($data) {
    // Clear any previous output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Set JSON header
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    // Output JSON and exit
    echo json_encode($data);
    exit();
}

// Ensure user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    sendJsonResponse(['status' => 'error', 'message' => 'Unauthorized access']);
}

// Get the action from POST data
$action = $_POST['action'] ?? '';

// Default response
$response = ['status' => 'error', 'message' => 'Invalid action'];

// Log the received POST data for debugging
error_log('POST data: ' . print_r($_POST, true));

// Make sure we have a valid action
if (empty($action)) {
    sendJsonResponse(['status' => 'error', 'message' => 'No action specified']);
}

try {
    // Handle different actions
    switch ($action) {
        case 'add_lecturer':
            $response = addLecturer($conn);
            break;
            
        case 'update_lecturer':
            $response = updateLecturer($conn);
            break;
            
        case 'delete_lecturer':
            $response = deleteLecturer($conn);
            break;
            
        case 'assign_course':
            $response = assignCourse($conn);
            break;
            
        case 'remove_course_assignment':
            $response = removeCourseAssignment($conn);
            break;
            
        default:
            $response = ['status' => 'error', 'message' => 'Invalid action'];
            break;
    }
} catch (Exception $e) {
    // Log the error
    error_log('Error in lecturer_actions.php: ' . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()];
}

// Clear any output that might have been generated
ob_clean();

// Send the JSON response
sendJsonResponse($response);

/**
 * Add a new lecturer
 */
function addLecturer($conn) {
    logError('Starting addLecturer function');
    logError('POST data: ' . print_r($_POST, true));
    
    $required = ['first_name', 'last_name', 'email', 'username', 'password'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $error = "Missing required field: $field";
            logError($error);
            return ['status' => 'error', 'message' => 'All fields are required'];
        }
    }
    
    error_log('All required fields are present');
    
    // Check if username or email already exists
    error_log('Checking if username or email already exists');
    try {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        if ($stmt === false) {
            error_log('Prepare failed: ' . $conn->error);
            throw new Exception('Database prepare failed');
        }
        
        $username = $_POST['username'];
        $email = $_POST['email'];
        
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result === false) {
            error_log('Query failed: ' . $stmt->error);
            throw new Exception('Database query failed');
        }
        
        if ($result->num_rows > 0) {
            error_log('Username or email already exists');
            return ['status' => 'error', 'message' => 'Username or email already exists'];
        }
    } catch (Exception $e) {
        error_log('Error checking existing user: ' . $e->getMessage());
        return ['status' => 'error', 'message' => 'Error checking existing user: ' . $e->getMessage()];
    }
    
    // Hash the password
    error_log('Hashing password');
    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Insert new lecturer
    error_log('Preparing to insert new lecturer');
    try {
        $query = "INSERT INTO users (username, password, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, 'lecturer')";
        logError("Preparing query: $query");
        
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            $error = 'Prepare failed: ' . $conn->error;
            logError($error);
            throw new Exception($error);
        }
        
        $username = $_POST['username'];
        $email = $_POST['email'];
        $firstName = $_POST['first_name'];
        $lastName = $_POST['last_name'];
        
        error_log("Binding params: $username, [hashed_password], $email, $firstName, $lastName");
        $stmt->bind_param("sssss", $username, $hashedPassword, $email, $firstName, $lastName);
        
        error_log('Executing query');
        $result = $stmt->execute();
        
        if ($result) {
            $newId = $conn->insert_id;
            error_log("Lecturer added successfully. New ID: $newId");
            return ['status' => 'success', 'message' => 'Lecturer added successfully'];
        } else {
            $error = $stmt->error ?: $conn->error;
            error_log('Failed to add lecturer: ' . $error);
            return ['status' => 'error', 'message' => 'Failed to add lecturer: ' . $error];
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log('Exception in addLecturer: ' . $error);
        return ['status' => 'error', 'message' => 'Error adding lecturer: ' . $error];
    }
}

/**
 * Update lecturer details
 */
function updateLecturer($conn) {
    if (empty($_POST['lecturer_id'])) {
        return ['status' => 'error', 'message' => 'Invalid lecturer ID'];
    }
    
    $lecturerId = (int)$_POST['lecturer_id'];
    
    // Check if username or email already exists for other users
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
    $stmt->bind_param("ssi", $_POST['username'], $_POST['email'], $lecturerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['status' => 'error', 'message' => 'Username or email already exists for another user'];
    }
    
    // Update lecturer details
    $query = "UPDATE users SET 
              first_name = ?, 
              last_name = ?, 
              email = ?, 
              username = ?";
    
    $params = [
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['email'],
        $_POST['username']
    ];
    
    // Update password if provided
    if (!empty($_POST['password'])) {
        $query .= ", password = ?";
        $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }
    
    $query .= " WHERE user_id = ? AND role = 'lecturer'";
    $params[] = $lecturerId;
    
    $stmt = $conn->prepare($query);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        return ['status' => 'success', 'message' => 'Lecturer updated successfully'];
    } else {
        return ['status' => 'error', 'message' => 'Failed to update lecturer: ' . $conn->error];
    }
}

/**
 * Delete a lecturer
 */
function deleteLecturer($conn) {
    if (empty($_POST['lecturer_id'])) {
        return ['status' => 'error', 'message' => 'Invalid lecturer ID'];
    }
    
    $lecturerId = (int)$_POST['lecturer_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Remove course assignments
        $stmt = $conn->prepare("DELETE FROM course_assignments WHERE lecturer_id = ?");
        $stmt->bind_param("i", $lecturerId);
        $stmt->execute();
        
        // Delete lecturer
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'lecturer'");
        $stmt->bind_param("i", $lecturerId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            return ['status' => 'success', 'message' => 'Lecturer deleted successfully'];
        } else {
            throw new Exception('Lecturer not found or already deleted');
        }
    } catch (Exception $e) {
        $conn->rollback();
        return ['status' => 'error', 'message' => 'Failed to delete lecturer: ' . $e->getMessage()];
    }
}

/**
 * Assign a course to a lecturer
 */
function assignCourse($conn) {
    if (empty($_POST['lecturer_id']) || empty($_POST['course_id'])) {
        return ['status' => 'error', 'message' => 'Lecturer ID and Course ID are required'];
    }
    
    $lecturerId = (int)$_POST['lecturer_id'];
    $courseId = (int)$_POST['course_id'];
    
    try {
        // First, check if the course is already assigned to this lecturer
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM course_assignments WHERE lecturer_id = ? AND course_id = ?");
        $checkStmt->bind_param("ii", $lecturerId, $courseId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            return ['status' => 'error', 'message' => 'This course is already assigned to the lecturer'];
        }
        
        // If not already assigned, proceed with the assignment
        $stmt = $conn->prepare("INSERT INTO course_assignments (lecturer_id, course_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $lecturerId, $courseId);
        
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'Course assigned successfully'];
        } else {
            // This should not happen since we already checked for duplicates, but just in case
            if ($conn->errno == 1062) {
                return ['status' => 'error', 'message' => 'This course is already assigned to the lecturer'];
            }
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        error_log('Error in assignCourse: ' . $e->getMessage());
        return ['status' => 'error', 'message' => 'Failed to assign course. Please try again.'];
    }
}

/**
 * Remove a course assignment from a lecturer
 */
function removeCourseAssignment($conn) {
    if (empty($_POST['assignment_id'])) {
        return ['status' => 'error', 'message' => 'Assignment ID is required'];
    }
    
    $assignmentId = (int)$_POST['assignment_id'];
    
    $stmt = $conn->prepare("DELETE FROM course_assignments WHERE assignment_id = ?");
    $stmt->bind_param("i", $assignmentId);
    
    if ($stmt->execute()) {
        return ['status' => 'success', 'message' => 'Course assignment removed successfully'];
    } else {
        return ['status' => 'error', 'message' => 'Failed to remove course assignment: ' . $conn->error];
    }
}
