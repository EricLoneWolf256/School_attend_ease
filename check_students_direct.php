<?php
// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'attendance_system';

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if students table exists
$table_check = $conn->query("SHOW TABLES LIKE 'students'");
if ($table_check->num_rows === 0) {
    die("The 'students' table does not exist in the database.");
}

// Get count of students
$result = $conn->query("SELECT COUNT(*) as count FROM students");
$row = $result->fetch_assoc();
$student_count = $row['count'];

echo "<h2>Student Records Check</h2>";
echo "<p>Total students in database: " . $student_count . "</p>";

// If there are students, show them
if ($student_count > 0) {
    $students = $conn->query("SELECT * FROM students LIMIT 10");
    echo "<h3>First 10 Students:</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Student ID</th><th>User ID</th><th>Student Number</th><th>Program</th><th>Year Level</th><th>Status</th></tr>";
    
    while ($student = $students->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $student['student_id'] . "</td>";
        echo "<td>" . $student['user_id'] . "</td>";
        echo "<td>" . htmlspecialchars($student['student_number']) . "</td>";
        echo "<td>" . htmlspecialchars($student['program']) . "</td>";
        echo "<td>" . htmlspecialchars($student['year_level']) . "</td>";
        echo "<td>" . htmlspecialchars($student['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check if users table has student records
$users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$user_count = $users->fetch_assoc()['count'];
echo "<p>Total users with role 'student': " . $user_count . "</p>";

$conn->close();
?>
