<?php
// Test script to check lecturer addition

// Start output buffering
ob_start();

// Include required files
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/session.php';

// Simulate a POST request
$_POST = [
    'action' => 'add_lecturer',
    'first_name' => 'Test',
    'last_name' => 'Lecturer',
    'email' => 'test.lecturer@example.com',
    'username' => 'testlecturer',
    'password' => 'Test@1234'
];

// Include the actions file
ob_start();
include __DIR__ . '/includes/lecturer_actions.php';
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
    echo "Raw response: " . $output . "\n";
} else {
    echo "=== JSON Response ===\n";
    print_r($response);
}

// Check for any errors in the error log
echo "\n=== Error Log ===\n";
$error_log = file_get_contents(__DIR__ . '/../../../php_errors.log');
echo substr($error_log, -2000); // Show last 2000 chars of error log
