<?php
require_once 'includes/db_connection.php';
require_once 'includes/session.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Set admin role for testing (remove in production)
$_SESSION['role'] = 'admin';

// Test student data
$test_student = [
    'action' => 'add_student',
    'student_id' => '2024-Bs011-13499',
    'first_name' => 'Test',
    'last_name' => 'Student',
    'email' => 'test' . time() . '@example.com',
    'program' => 'Computer Science',
    'year_level' => '1',
    'password' => 'test123'
];

// Include the student actions file
require_once 'admin/includes/student_actions.php';

// Override the $_POST with our test data
$_POST = $test_student;

// Function to display the result
function displayResult($result) {
    echo "<h2>Test Result</h2>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if ($result['status'] === 'success') {
        echo "<div style='color: green; font-weight: bold;'>Test successful! Student added/updated.</div>";
    } else {
        echo "<div style='color: red; font-weight: bold;'>Error: " . htmlspecialchars($result['message']) . "</div>";
    }
    
    // Show the students table
    echo "<h2>Current Students</h2>";
    $query = "SELECT u.user_id, u.first_name, u.last_name, s.student_number, s.program, s.year_level, s.status 
              FROM users u 
              JOIN students s ON u.user_id = s.user_id 
              WHERE u.role = 'student' AND 
                   (s.student_number LIKE '%2024-Bs011-13499%' OR s.program LIKE '%2024-Bs011-13499%')
              ORDER BY s.student_number";
    $result = mysqli_query($GLOBALS['conn'], $query);
    
    if (mysqli_num_rows($result) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Student ID</th><th>Name</th><th>Program</th><th>Year</th><th>Status</th></tr>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['student_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['program']) . "</td>";
            echo "<td>" . htmlspecialchars($row['year_level']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No matching students found.";
    }
}

// Run the test
try {
    // Call the addStudent function directly
    $result = addStudent($conn);
    displayResult($result);
} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold;'>Exception: " . htmlspecialchars($e->getMessage()) . "</div>";
    
    // Show the last error if any
    if (isset($conn)) {
        echo "<div>MySQL Error: " . htmlspecialchars($conn->error) . "</div>";
    }
}

// Show the last few error log entries
echo "<h2>Recent Error Log</h2>";
$log_file = __DIR__ . '/php_errors.log';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $log_entries = array_filter(explode("\n", $log_content));
    $recent_entries = array_slice($log_entries, -20); // Show last 20 entries
    echo "<pre>" . htmlspecialchars(implode("\n", $recent_entries)) . "</pre>";
} else {
    echo "Error log file not found.";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    table { border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
</style>
