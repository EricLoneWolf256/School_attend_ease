<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once 'config.php';
require_once 'includes/session.php';
require_once 'includes/db_connection.php';

// Start session for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set admin session for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

// Test data
$testData = [
    'first_name' => 'Test',
    'last_name' => 'Lecturer',
    'email' => 'test.lecturer@example.com',
    'username' => 'testlector',
    'password' => 'Test@1234',
    'action' => 'add_lecturer'
];

// Simulate POST data
$_POST = $testData;

// Buffer the output
ob_start();

// Include the lecturer_actions.php file
require 'admin/includes/lecturer_actions.php';

// Get the output
$output = ob_get_clean();

// Output the results
echo "<h1>Test Lecturer Addition</h1>";

// Show the test data
echo "<h2>Test Data:</h2>";
echo "<pre>";
print_r($testData);
echo "</pre>";

// Show the output
echo "<h2>Output from lecturer_actions.php:</h2>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Check if the lecturer was added
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $testData['username']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $lecturer = $result->fetch_assoc();
    echo "<h2 style='color:green'>✓ Lecturer added successfully!</h2>";
    echo "<pre>";
    print_r($lecturer);
    echo "</pre>";
    
    // Clean up
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $lecturer['user_id']);
    $stmt->execute();
    echo "<p>Test lecturer has been removed from the database.</p>";
} else {
    echo "<h2 style='color:red'>❌ Lecturer was not added to the database</h2>";
    
    // Check for common issues
    echo "<h3>Checking for common issues:</h3>";
    
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows == 0) {
        echo "<p style='color:red'>❌ Users table does not exist!</p>";
    } else {
        echo "<p style='color:green'>✓ Users table exists</p>";
        
        // Check table structure
        $columns = [
            'user_id', 'username', 'password', 'email', 
            'first_name', 'last_name', 'role', 'created_at', 'updated_at'
        ];
        
        $result = $conn->query("DESCRIBE users");
        $tableColumns = [];
        while ($row = $result->fetch_assoc()) {
            $tableColumns[] = $row['Field'];
        }
        
        $missingColumns = array_diff($columns, $tableColumns);
        if (!empty($missingColumns)) {
            echo "<p style='color:red'>❌ Missing columns in users table: " . implode(", ", $missingColumns) . "</p>";
        } else {
            echo "<p style='color:green'>✓ All required columns exist in users table</p>";
        }
    }
    
    // Check for any MySQL errors
    if ($conn->error) {
        echo "<p style='color:red'>❌ MySQL Error: " . $conn->error . "</p>";
    }
}

// Close connection
$conn->close();
?>

<style>
    body { 
        font-family: Arial, sans-serif; 
        margin: 20px; 
        line-height: 1.6;
    }
    pre {
        background: #f5f5f5;
        padding: 15px;
        border-radius: 5px;
        border: 1px solid #ddd;
        overflow-x: auto;
    }
    h1 { color: #333; }
    h2 { color: #444; margin-top: 30px; }
    h3 { color: #555; margin-top: 20px; }
</style>
