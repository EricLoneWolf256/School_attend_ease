<?php
// Start output buffering
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

// Disable error output to the browser
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../php_errors.log');
error_reporting(E_ALL);

// Include required files
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/session.php';

// Database connection is already established in db_connection.php
// Using the global $conn variable
global $conn;

// Verify database connection
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    error_log("Database connection error: " . ($conn->connect_error ?? 'No connection'));
    sendJsonResponse(['status' => 'error', 'message' => 'Database connection failed. Please try again later.']);
}

// Function to log errors
function logError($message) {
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    error_log($message);
}

// Function to send JSON response and exit
function sendJsonResponse($data) {
    global $conn;
    
    // Close database connection if it exists
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    
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
    sendJsonResponse(['status' => 'error', 'message' => 'Unauthorized access']);
}

// Get the action from POST data
$action = $_POST['action'] ?? '';

// Default response
$response = ['status' => 'error', 'message' => 'Invalid action'];

$response = ['status' => 'error', 'message' => 'Invalid action'];

// Function to check if a student number already exists (case-insensitive and trimmed)
if (!function_exists('studentNumberExists')) {
    function studentNumberExists($conn, $student_number) {
        $normalized = strtoupper(trim($student_number));
        $stmt = $conn->prepare("SELECT student_id, student_number FROM students WHERE UPPER(TRIM(student_number)) = ?");
        $stmt->bind_param("s", $normalized);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            error_log("Found existing student number: " . $row['student_number'] . " (input: $student_number)");
            return $row; // Return the existing record
        }
        return false;
    }
}

// Move generateUniqueStudentId function after the database connection
if (!function_exists('generateUniqueStudentId')) {
    function generateUniqueStudentId($conn, $base_id) {
        $base_id = strtoupper(trim($base_id));
        $counter = 1;
        $new_id = $base_id;
        
        // Keep trying until we find an available ID
        while (true) {
            if (!studentNumberExists($conn, $new_id)) {
                return $new_id; // Found an available ID
            }
            
            // If ID exists, try appending a letter (A, B, C, etc.)
            $suffix = chr(64 + $counter); // A, B, C, ...
            $new_id = $base_id . $suffix;
            $counter++;
            
            // Safety check to prevent infinite loops
            if ($counter > 26) {
                // If we've tried all letters, try appending numbers
                $new_id = $base_id . '-' . $counter;
            }
            
            if ($counter > 100) {
                // Absolute fallback
                return $base_id . '-' . uniqid();
            }
        }
    }
}

// Make sure we have a valid action
if (empty($action)) {
    sendJsonResponse(['status' => 'error', 'message' => 'No action specified']);
    exit;
}

// Verify database connection is available
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    error_log("Database connection lost before action processing");
    sendJsonResponse(['status' => 'error', 'message' => 'Database connection lost. Please refresh and try again.']);
    exit;
}

try {
    // Handle the request based on action
    switch ($action) {
        case 'add_student':
            try {
                $response = addStudent($conn);
            } catch (Exception $e) {
                error_log("Error in addStudent: " . $e->getMessage());
                $response = ['status' => 'error', 'message' => 'Failed to add student: ' . $e->getMessage()];
            }
            break;
        case 'update_student':
            $response = updateStudent($conn);
            break;
        case 'delete_student':
            $response = deleteStudent($conn);
            break;
        case 'update_student_status':
            $response = updateStudentStatus($conn);
            break;
        case 'import_students':
            $response = importStudents($conn);
            break;
        case 'get_students':
            $response = getStudentsForDataTable($conn);
            break;
        default:
            $response = ['status' => 'error', 'message' => 'Invalid action'];
            break;
    }
} catch (Exception $e) {
    $errorMessage = 'Error in ' . $action . ': ' . $e->getMessage();
    error_log($errorMessage);
    $response = ['status' => 'error', 'message' => $errorMessage];
}

// Send the JSON response
sendJsonResponse($response);

/**
 * Add a new student
 */
function addStudent($conn) {
    // Required fields
    $required = ['student_id', 'first_name', 'last_name', 'email', 'program', 'year_level'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            return ['status' => 'error', 'message' => 'All fields are required'];
        }
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Enhanced logging
        error_log("=== ATTEMPTING TO ADD STUDENT ===");
        error_log("Student ID In: " . $_POST['student_id']);
        error_log("Name In: " . $_POST['first_name'] . ' ' . $_POST['last_name']);
        
        $student_id = trim(strtoupper($_POST['student_id']));
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $program = trim($_POST['program']);
        $year_level = trim($_POST['year_level']);
        
        error_log("Normalized ID: $student_id");
        
        // Enhanced check for existing student with more detailed logging
        error_log("Checking for existing student with number: '$student_id'");
        
        // Check if student number already exists (case-insensitive and trimmed)
        $existingStudent = studentNumberExists($conn, $student_id);
        
        if ($existingStudent) {
            error_log("Found existing student with number: '{$existingStudent['student_number']}'. Original input: '$student_id'");
            
            // Check for hidden characters in student numbers
            $originalHex = '';
            $dbHex = '';
            for ($i = 0; $i < strlen($student_id); $i++) {
                $originalHex .= sprintf("%02X ", ord($student_id[$i]));
            }
            for ($i = 0; $i < strlen($existingStudent['student_number']); $i++) {
                $dbHex .= sprintf("%02X ", ord($existingStudent['student_number'][$i]));
            }
            error_log("Input hex: $originalHex");
            error_log("Database hex: $dbHex");
            
            // Get the full student details for update
            $stmt = $conn->prepare("SELECT s.*, u.user_id, u.first_name, u.last_name, u.email 
                                  FROM students s 
                                  JOIN users u ON s.user_id = u.user_id 
                                  WHERE s.student_id = ?");
            $stmt->bind_param("i", $existingStudent['student_id']);
            $stmt->execute();
            $studentDetails = $stmt->get_result()->fetch_assoc();
            
            if (!$studentDetails) {
                throw new Exception("Error retrieving student details for update");
            }
            
            // Update existing user information
            $updateUser = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?");
            $updateUser->bind_param("sssi", $first_name, $last_name, $email, $studentDetails['user_id']);
            
            if (!$updateUser->execute()) {
                throw new Exception("Failed to update user information: " . $conn->error);
            }
            
            // Update student information
            $updateStudent = $conn->prepare("UPDATE students SET program = ?, year_level = ?, status = 'active' WHERE student_id = ?");
            $updateStudent->bind_param("ssi", $program, $year_level, $existingStudent['student_id']);
            
            if (!$updateStudent->execute()) {
                throw new Exception("Failed to update student record: " . $conn->error);
            }
            
            $conn->commit();
            return ['status' => 'success', 'message' => 'Student information updated successfully', 'action' => 'updated'];
        }
        
        // Check if user exists with same email or username (but different from the student we're processing)
        $checkUser = $conn->prepare("SELECT user_id FROM users WHERE (LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)) AND user_id NOT IN (SELECT user_id FROM students WHERE UPPER(TRIM(student_number)) = UPPER(TRIM(?)))");
        $checkUser->bind_param("sss", $email, $student_id, $student_id);
        $checkUser->execute();
        $existingUser = $checkUser->get_result()->fetch_assoc();
        
        if ($existingUser) {
            // Update existing user
            $updateUser = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = 'student' WHERE user_id = ?");
            $updateUser->bind_param("sssi", $first_name, $last_name, $email, $existingUser['user_id']);
            
            if (!$updateUser->execute()) {
                if ($conn->errno == 1062) { // Duplicate entry error
                    throw new Exception("A user with this email or username already exists");
                }
                throw new Exception("Failed to update user information: " . $conn->error);
            }
            
            $user_id = $existingUser['user_id'];
        } else {
            // Create new user
            $password = $student_id; // Using student ID as default password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $insertUser = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, 'student')");
            $insertUser->bind_param("sssss", $student_id, $hashedPassword, $email, $first_name, $last_name);
            
            if (!$insertUser->execute()) {
                if ($conn->errno == 1062) { // Duplicate entry error
                    throw new Exception("A user with this email or username already exists");
                }
                throw new Exception("Failed to create user: " . $conn->error);
            }
            
            $user_id = $conn->insert_id;
        }
        
        // Final check right before insertion to prevent race conditions (using normalized ID)
        $finalCheck = $conn->prepare("SELECT student_id, student_number FROM students WHERE UPPER(TRIM(student_number)) = ?");
        $finalCheck->bind_param("s", $normalizedStudentId);
        $finalCheck->execute();
        $existing = $finalCheck->get_result()->fetch_assoc();
        
        if ($existing) {
            $conn->rollback();
            error_log("Final check found existing student with number: '{$existing['student_number']}'");
            
            // Get the existing student's details for a more helpful message
            $existingDetails = $conn->query("
                SELECT u.first_name, u.last_name, u.email, s.program, s.year_level 
                FROM students s 
                JOIN users u ON s.user_id = u.user_id 
                WHERE UPPER(TRIM(s.student_number)) = '".$conn->real_escape_string($normalizedStudentId)."'"
            )->fetch_assoc();
            
            $message = "A student with this ID already exists in the system. ";
            if ($existingDetails) {
                $message .= "Existing student: {$existingDetails['first_name']} {$existingDetails['last_name']} ";
                $message .= "({$existing['student_number']}) - {$existingDetails['program']} Year {$existingDetails['year_level']}";
            }
            
            return [
                'status' => 'error', 
                'message' => $message,
                'existing_number' => $existing['student_number'],
                'existing_details' => $existingDetails
            ];
        }
        
        // Final race condition check using normalized student ID
        $finalCheck = $conn->prepare("SELECT s.student_id, s.user_id, s.student_number, u.first_name, u.last_name, u.email, s.program, s.year_level 
                                    FROM students s 
                                    JOIN users u ON s.user_id = u.user_id 
                                    WHERE UPPER(TRIM(s.student_number)) = ?");
        $finalCheck->bind_param("s", $normalizedStudentId);
        $finalCheck->execute();
        $existingStudent = $finalCheck->get_result()->fetch_assoc();
        
        if ($existingStudent) {
            $conn->rollback();
            error_log("Final race condition check found existing student with number: '{$existingStudent['student_number']}'");
            
            $message = "A student with this ID already exists in the system. ";
            $message .= "Existing student: {$existingStudent['first_name']} {$existingStudent['last_name']} ";
            $message .= "({$existingStudent['student_number']}) - {$existingStudent['program']} Year {$existingStudent['year_level']}";
            
            return [
                'status' => 'error', 
                'message' => $message,
                'existing_number' => $existingStudent['student_number'],
                'existing_details' => [
                    'first_name' => $existingStudent['first_name'],
                    'last_name' => $existingStudent['last_name'],
                    'email' => $existingStudent['email'],
                    'program' => $existingStudent['program'],
                    'year_level' => $existingStudent['year_level']
                ]
            ];
        } else {
            // No student exists, proceed with insertion
            $insertStudent = $conn->prepare("INSERT INTO students (user_id, student_number, program, year_level, status) VALUES (?, ?, ?, ?, 'active')");
            if ($insertStudent === false) {
                throw new Exception("Failed to prepare student insert: " . $conn->error);
            }
            
            $insertStudent->bind_param("isss", $user_id, $student_id, $program, $year_level);
            
            if (!$insertStudent->execute()) {
                $error = $conn->error;
                error_log("SQL Error in insertStudent: " . $error);
                
                // If it's a duplicate entry, try to handle it gracefully
                if (strpos($error, 'Duplicate entry') !== false || $conn->errno == 1062) {
                    // Try to get the existing student record
                    $existing = $conn->query("SELECT * FROM students WHERE student_number = '" . $conn->real_escape_string($student_id) . "'");
                    if ($existing && $existing->num_rows > 0) {
                        $existingData = $existing->fetch_assoc();
                        
                        // Update the existing record
                        $updateUser = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?");
                        $updateUser->bind_param("sssi", $first_name, $last_name, $email, $existingData['user_id']);
                        
                        if (!$updateUser->execute()) {
                            throw new Exception("Failed to update user information: " . $conn->error);
                        }
                        
                        $updateStudent = $conn->prepare("UPDATE students SET program = ?, year_level = ?, status = 'active' WHERE student_id = ?");
                        $updateStudent->bind_param("ssi", $program, $year_level, $existingData['student_id']);
                        
                        if (!$updateStudent->execute()) {
                            throw new Exception("Failed to update student record: " . $conn->error);
                        }
                        
                        $conn->commit();
                        return ['status' => 'success', 'message' => 'Student information updated successfully', 'action' => 'updated'];
                    }
                }
                throw new Exception("Failed to create student record: " . $error);
            }
        }
        
        $conn->commit();
        
        return [
            'status' => 'success', 
            'message' => 'Student added successfully',
            'student' => [
                'user_id' => $user_id,
                'student_number' => $student_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'program' => $program,
                'year_level' => $year_level,
                'status' => 'active'
            ]
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in addStudent: " . $e->getMessage());
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Update student details
 */
function updateStudent($conn) {
    if (empty($_POST['user_id'])) {
        return ['status' => 'error', 'message' => 'Invalid student ID'];
    }
    
    $user_id = (int)$_POST['user_id'];
    
    // Required fields
    $required = ['student_id', 'first_name', 'last_name', 'email', 'program', 'year_level', 'status'];
    foreach ($required as $field) {
        if (!isset($_POST[$field])) {
            return ['status' => 'error', 'message' => 'All fields are required'];
        }
    }
    
    // Validate email
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'error', 'message' => 'Please enter a valid email address'];
    }
    
    // Check if email or student ID is already taken by another user
    $stmt = $conn->prepare("SELECT user_id FROM users 
                           WHERE (email = ? OR username = ?) AND user_id != ?");
    $email = $_POST['email'];
    $username = $_POST['student_id'];
    $stmt->bind_param("ssi", $email, $username, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['status' => 'error', 'message' => 'Student ID or email already exists for another student'];
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update users table
        $query = "UPDATE users SET 
                 username = ?, 
                 email = ?, 
                 first_name = ?, 
                 last_name = ?";
        
        $params = [
            $username,
            $email,
            $_POST['first_name'],
            $_POST['last_name']
        ];
        
        // Add password to query if provided
        if (!empty($_POST['password'])) {
            $query .= ", password = ?";
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $params[] = $hashedPassword;
        }
        
        $query .= " WHERE user_id = ?";
        $params[] = $user_id;
        
        $stmt = $conn->prepare($query);
        $types = str_repeat('s', count($params) - 1) . 'i';
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update user: " . $stmt->error);
        }
        
        // Update students table
        $stmt = $conn->prepare("UPDATE students 
                               SET student_id = ?, program = ?, year_level = ?, status = ? 
                               WHERE user_id = ?");
        $stmt->bind_param("ssssi", 
            $_POST['student_id'],
            $_POST['program'],
            $_POST['year_level'],
            $_POST['status'],
            $user_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update student: " . $stmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        return ['status' => 'success', 'message' => 'Student updated successfully'];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Update student status
 */
function updateStudentStatus($conn) {
    if (empty($_POST['user_id']) || !isset($_POST['status'])) {
        return ['status' => 'error', 'message' => 'Invalid request'];
    }
    
    $user_id = (int)$_POST['user_id'];
    $status = $_POST['status'];
    
    // Validate status
    $valid_statuses = ['active', 'inactive', 'suspended', 'graduated'];
    if (!in_array($status, $valid_statuses)) {
        return ['status' => 'error', 'message' => 'Invalid status'];
    }
    
    try {
        $stmt = $conn->prepare("UPDATE students SET status = ? WHERE user_id = ?");
        $stmt->bind_param("si", $status, $user_id);
        
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'Student status updated successfully'];
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Failed to update student status: ' . $e->getMessage()];
    }
}

/**
 * Delete a student
 */
function deleteStudent($conn) {
    if (empty($_POST['user_id'])) {
        return ['status' => 'error', 'message' => 'Invalid student ID'];
    }
    
    $user_id = (int)$_POST['user_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete from students table first (foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM students WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete student: " . $stmt->error);
        }
        
        // Delete from users table
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'");
        $stmt->bind_param("i", $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete user: " . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Student not found or already deleted");
        }
        
        // Commit transaction
        $conn->commit();
        
        return ['status' => 'success', 'message' => 'Student deleted successfully'];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Import students from CSV
 */
function importStudents($conn) {
    // Check if file was uploaded
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        return ['status' => 'error', 'message' => 'Please upload a valid CSV file'];
    }
    
    // Check file type
    $file_type = $_FILES['csv_file']['type'];
    if (!in_array($file_type, ['text/csv', 'application/vnd.ms-excel', 'text/plain'])) {
        return ['status' => 'error', 'message' => 'Only CSV files are allowed'];
    }
    
    // Open the uploaded file
    $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if ($file === false) {
        return ['status' => 'error', 'message' => 'Failed to open the uploaded file'];
    }
    
    // Read header row
    $header = fgetcsv($file);
    if ($header === false) {
        return ['status' => 'error', 'message' => 'Empty or invalid CSV file'];
    }
    
    // Map header columns to field names
    $header_map = [
        'student_id' => ['student_id', 'student id', 'id', 'student_no', 'student_no'],
        'first_name' => ['first_name', 'first name', 'firstname', 'fname'],
        'last_name' => ['last_name', 'last name', 'lastname', 'lname'],
        'email' => ['email', 'email address', 'e-mail'],
        'program' => ['program', 'course', 'programme'],
        'year_level' => ['year_level', 'year', 'level', 'year level']
    ];
    
    $field_indices = [];
    
    // Find the index of each required field
    foreach ($header_map as $field => $possible_names) {
        $found = false;
        foreach ($possible_names as $name) {
            $index = array_search(strtolower($name), array_map('strtolower', $header));
            if ($index !== false) {
                $field_indices[$field] = $index;
                $found = true;
                break;
            }
        }
        
        if (!$found && $field !== 'year_level') { // year_level is optional
            return ['status' => 'error', 'message' => "Required column not found: " . $possible_names[0]];
        }
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $row_num = 1; // Start from 1 to account for header row
        
        // Prepare statements for better performance
        $check_user = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
        $insert_user = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) 
                                     VALUES (?, ?, ?, ?, ?, 'student')");
        $insert_student = $conn->prepare("INSERT INTO students (user_id, student_id, program, year_level, status) 
                                        VALUES (?, ?, ?, ?, 'active')");
        
        // Process each row
        while (($row = fgetcsv($file)) !== false) {
            $row_num++;
            
            // Skip empty rows
            if (count(array_filter($row)) === 0) {
                continue;
            }
            
            // Get field values
            $student = [];
            foreach ($field_indices as $field => $index) {
                if (isset($row[$index])) {
                    $student[$field] = trim($row[$index]);
                } else {
                    $student[$field] = '';
                }
            }
            
            // Skip if required fields are empty
            if (empty($student['student_id']) || empty($student['first_name']) || 
                empty($student['last_name']) || empty($student['email'])) {
                $skipped++;
                $errors[] = "Row $row_num: Missing required fields";
                continue;
            }
            
            // Validate email
            if (!filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                $errors[] = "Row $row_num: Invalid email address: " . $student['email'];
                continue;
            }
            
            // Set default values for optional fields
            if (empty($student['program'])) {
                $student['program'] = 'Undeclared';
            }
            
            if (empty($student['year_level']) || !is_numeric($student['year_level'])) {
                $student['year_level'] = 1;
            } else {
                $student['year_level'] = (int)$student['year_level'];
            }
            
            // Check if student already exists
            $check_user->bind_param("ss", $student['email'], $student['student_id']);
            $check_user->execute();
            $result = $check_user->get_result();
            
            if ($result->num_rows > 0) {
                $skipped++;
                $errors[] = "Row $row_num: Student ID or email already exists: " . $student['student_id'];
                continue;
            }
            
            // Generate password (use student ID as default)
            $password = $student['student_id'];
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into users table
            $insert_user->bind_param(
                "sssss",
                $student['student_id'],
                $hashedPassword,
                $student['email'],
                $student['first_name'],
                $student['last_name']
            );
            
            if (!$insert_user->execute()) {
                throw new Exception("Failed to insert user: " . $insert_user->error);
            }
            
            $user_id = $conn->insert_id;
            
            // Insert into students table
            $insert_student->bind_param(
                "issi",
                $user_id,
                $student['student_id'],
                $student['program'],
                $student['year_level']
            );
            
            if (!$insert_student->execute()) {
                throw new Exception("Failed to insert student: " . $insert_student->error);
            }
            
            $imported++;
            
            // TODO: Send welcome email if requested
            if (isset($_POST['send_email']) && $_POST['send_email'] === 'on') {
                // Implement email sending logic here
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $message = "Successfully imported $imported students";
        if ($skipped > 0) {
            $message .= ", skipped $skipped rows with errors";
            if (!empty($errors)) {
                $message .= ". First few errors: " . implode("; ", array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $message .= "...";
                }
            }
        }
        
        return [
            'status' => 'success', 
            'message' => $message,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['status' => 'error', 'message' => 'Import failed: ' . $e->getMessage()];
    } finally {
        fclose($file);
    }
}
