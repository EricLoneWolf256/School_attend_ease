<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'config.php';
require_once 'includes/db_connection.php';

echo "<h1>Database Check</h1>";

// Check connection
if ($conn->connect_error) {
    die("<p style='color:red'>Connection failed: " . $conn->connect_error . "</p>");
}
echo "<p style='color:green'>✓ Connected to database successfully</p>";

// Check if users table exists
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows == 0) {
    die("<p style='color:red'>❌ Users table does not exist</p>");
}
echo "<p style='color:green'>✓ Users table exists</p>";

// Show users table structure
$result = $conn->query("DESCRIBE users");
if ($result) {
    echo "<h3>Users Table Structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . (is_null($row['Default']) ? 'NULL' : htmlspecialchars($row['Default'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>❌ Could not describe users table: " . $conn->error . "</p>";
}

// Test adding a lecturer
if (isset($_POST['test_add_lecturer'])) {
    echo "<h3>Test Adding Lecturer:</h3>";
    
    // Test data
    $testData = [
        'first_name' => 'Test',
        'last_name' => 'Lecturer',
        'email' => 'test.lecturer@example.com',
        'username' => 'testlecturer',
        'password' => 'Test@1234',
        'action' => 'add_lecturer'
    ];
    
    // Simulate the lecturer_actions.php
    $_POST = $testData;
    
    // Include the lecturer_actions.php file
    ob_start();
    require 'admin/includes/lecturer_actions.php';
    $output = ob_get_clean();
    
    echo "<p>Raw output from lecturer_actions.php:</p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Check if the lecturer was added
    $result = $conn->query("SELECT * FROM users WHERE username = 'testlecturer'");
    if ($result->num_rows > 0) {
        echo "<p style='color:green'>✓ Test lecturer was added successfully</p>";
        // Clean up
        $conn->query("DELETE FROM users WHERE username = 'testlecturer'");
    } else {
        echo "<p style='color:red'>❌ Test lecturer was not added</p>";
    }
}

// Show test form
echo "<h3>Run Test:</h3>";
echo "<form method='post'>";
echo "<input type='submit' name='test_add_lecturer' value='Test Adding Lecturer'>";
echo "</form>";

// Close connection
$conn->close();
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
    table { border-collapse: collapse; margin: 10px 0; }
    th { background: #f0f0f0; }
    td, th { padding: 8px; text-align: left; border: 1px solid #ddd; }
</style>
