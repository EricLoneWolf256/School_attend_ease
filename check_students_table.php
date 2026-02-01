<?php
// Check students table structure
require_once 'includes/db_connection.php';

// Check if students table exists
$result = $conn->query("SHOW TABLES LIKE 'students'");
if ($result->num_rows === 0) {
    die("The 'students' table does not exist in the database.\n");
}

// Get table structure
$result = $conn->query("SHOW CREATE TABLE students");
if ($result === false) {
    die("Error getting table structure: " . $conn->error . "\n");
}

$row = $result->fetch_assoc();
echo "=== Students Table Structure ===\n";
echo $row['Create Table'] . "\n\n";

// Check for any existing student-user mappings
$result = $conn->query("
    SELECT s.user_id, u.username, s.student_id, u.first_name, u.last_name, u.email
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    ORDER BY s.user_id DESC
    LIMIT 10
");

echo "=== Recent Student-User Mappings ===\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "User ID: " . $row['user_id'] . " | ";
        echo "Student ID: " . $row['student_id'] . " | ";
        echo "Name: " . $row['first_name'] . " " . $row['last_name'] . " | ";
        echo "Email: " . $row['email'] . "\n";
    }
} else {
    echo "No student records found.\n";
}

// Check for any duplicate user_id in students table
$result = $conn->query("
    SELECT user_id, COUNT(*) as count
    FROM students
    GROUP BY user_id
    HAVING count > 1
");

echo "\n=== Duplicate User IDs in Students Table ===\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Duplicate user_id: " . $row['user_id'] . " (appears " . $row['count'] . " times)\n";
    }
} else {
    echo "No duplicate user_ids found in students table.\n";
}

$conn->close();
