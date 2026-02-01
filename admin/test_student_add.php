<?php
// Test script to check student addition

// Start output buffering
ob_start();

// Include required files
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/session.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simulate admin login for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

// Simulate a POST request to add a student
$_POST = [
    'action' => 'add_student',
    'student_id' => 'STU' . time(), // Unique ID for testing
    'first_name' => 'Test',
    'last_name' => 'Student',
    'email' => 'test.student' . time() . '@example.com',
    'program' => 'Computer Science',
    'year_level' => '1',
    'password' => 'Test@1234'
];

// Log the request
error_log("Test Student Add - Request: " . print_r($_POST, true));

// Include the actions file
ob_start();
include __DIR__ . '/includes/student_actions.php';
$output = ob_get_clean();

// Check for any output before JSON
if (!empty($output)) {
    echo "=== Output before JSON detected ===\n";
    echo $output;
    echo "\n=== End of output ===\n\n";
}

// Get the JSON response
$response = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "=== JSON Parse Error ===\n";
    echo "Error: " . json_last_error_msg() . "\n";
    echo "Raw response: " . htmlspecialchars($output) . "\n";
} else {
    echo "=== JSON Response ===\n";
    print_r($response);
}

// Check for any errors in the error log
echo "\n=== Error Log ===\n";
$error_log = @file_get_contents(__DIR__ . '/../../../php_errors.log');
if ($error_log === false) {
    echo "Could not read error log file.\n";
} else {
    echo substr($error_log, -2000); // Show last 2000 chars of error log
}

// Check if the student was added to the database
if (isset($response['status']) && $response['status'] === 'success') {
    echo "\n\n=== Database Check ===\n";
    $student_id = $_POST['student_id'];
    $result = $conn->query("SELECT * FROM users u 
                          JOIN students s ON u.user_id = s.user_id 
                          WHERE s.student_id = '$student_id'");
    
    if ($result->num_rows > 0) {
        echo "Student found in database:\n";
        print_r($result->fetch_assoc());
    } else {
        echo "Student not found in database.\n";
        
        // Check users table
        $result = $conn->query("SELECT * FROM users WHERE username = '$student_id'");
        echo "\nUsers table check:\n";
        if ($result->num_rows > 0) {
            echo "User found in users table but not in students table.\n";
            print_r($result->fetch_assoc());
        } else {
            echo "User not found in users table.\n";
        }
    }
}

$conn->close();
