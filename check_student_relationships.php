<?php
// Connect to database
require_once 'includes/db_connection.php';

// Check for orphaned records or incorrect relationships
$query = "
    -- Check for users who are students but have no corresponding entry in students table
    SELECT 'Orphaned Student Users' AS check_type, u.user_id, u.username, u.email, 'User exists but not in students table' AS issue
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    WHERE u.role = 'student' AND s.user_id IS NULL
    
    UNION ALL
    
    -- Check for students that reference non-existent users
    SELECT 'Invalid Student References' AS check_type, s.user_id, s.student_id, '' AS email, 'Student references non-existent user' AS issue
    FROM students s
    LEFT JOIN users u ON s.user_id = u.user_id
    WHERE u.user_id IS NULL
    
    UNION ALL
    
    -- Check for duplicate user_id in students table
    SELECT 'Duplicate Student Users' AS check_type, user_id, GROUP_CONCAT(student_id) AS student_ids, COUNT(*) AS count, 'Multiple student records for same user_id' AS issue
    FROM students
    GROUP BY user_id
    HAVING COUNT(*) > 1
";

$result = $conn->query($query);

if (!$result) {
    die("Error checking student relationships: " . $conn->error);
}

// Display results
echo "<h2>Student-User Relationship Check</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Check Type</th><th>User ID</th><th>Student ID/Username</th><th>Email</th><th>Issue</th></tr>";

$hasIssues = false;
while ($row = $result->fetch_assoc()) {
    $hasIssues = true;
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['check_type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['username'] ?? $row['student_ids'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($row['email'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($row['issue']) . "</td>";
    echo "</tr>";
}

echo "</table>";

if (!$hasIssues) {
    echo "<p>No issues found in student-user relationships.</p>";
}

// Show current student records
echo "<h2>Current Student Records</h2>";
$students = $conn->query("
    SELECT s.*, u.username, u.email, u.first_name, u.last_name 
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    ORDER BY s.student_id
");

if ($students->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>User ID</th><th>Student ID</th><th>Name</th><th>Email</th><th>Program</th><th>Year Level</th></tr>";
    
    while ($student = $students->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($student['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($student['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($student['email']) . "</td>";
        echo "<td>" . htmlspecialchars($student['program']) . "</td>";
        echo "<td>" . htmlspecialchars($student['year_level']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No student records found in the database.</p>";
}

$conn->close();
?>
