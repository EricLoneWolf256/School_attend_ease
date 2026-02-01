<?php
require_once 'config.php';
require_once 'includes/db_connection.php';

// Check if we can connect to the database
try {
    // Get the count of students
    $result = $conn->query("SELECT COUNT(*) as count FROM students");
    $row = $result->fetch_assoc();
    $studentCount = $row['count'];
    
    // Get the count of users with role 'student'
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $row = $result->fetch_assoc();
    $userStudentCount = $row['count'];
    
    echo "Total students in 'students' table: " . $studentCount . "\n";
    echo "Total users with role 'student': " . $userStudentCount . "\n";
    
    if ($studentCount > 0) {
        echo "\nSample of student records (first 5):\n";
        echo str_pad("ID", 8) . str_pad("Student Number", 20) . str_pad("Name", 30) . "Email\n";
        echo str_repeat("-", 80) . "\n";
        
        $result = $conn->query("SELECT s.student_id, s.student_number, u.first_name, u.last_name, u.email 
                               FROM students s 
                               JOIN users u ON s.user_id = u.user_id 
                               ORDER BY s.student_id DESC LIMIT 5");
        
        while ($row = $result->fetch_assoc()) {
            $name = $row['first_name'] . ' ' . $row['last_name'];
            echo str_pad($row['student_id'], 8) . 
                 str_pad($row['student_number'], 20) . 
                 str_pad(substr($name, 0, 28), 30) . 
                 $row['email'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Check if the database connection is properly configured in config.php\n";
    echo "DB_HOST: " . DB_HOST . "\n";
    echo "DB_NAME: " . DB_NAME . "\n";
}

$conn->close();
?>
