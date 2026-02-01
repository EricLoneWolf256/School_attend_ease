<?php
// Connect to database
require_once 'includes/db_connection.php';

echo "<h2>Current Student Records</h2>";

// Show all student records with user details
$query = "
    SELECT s.*, u.username, u.email, u.first_name, u.last_name, u.role, u.is_active
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    ORDER BY s.student_id
";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr>
            <th>User ID</th>
            <th>Student ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Program</th>
            <th>Year Level</th>
            <th>Status</th>
            <th>User Role</th>
            <th>Active</th>
          </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['program']) . "</td>";
        echo "<td>" . htmlspecialchars($row['year_level']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . ($row['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No student records found in the database.</p>";
}

// Show users with role 'student' that don't have corresponding student records
echo "<h2>Users with Role 'Student' but No Student Record</h2>";
$query = "
    SELECT u.user_id, u.username, u.email, u.first_name, u.last_name
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    WHERE u.role = 'student' AND s.user_id IS NULL
";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr>
            <th>User ID</th>
            <th>Username</th>
            <th>Name</th>
            <th>Email</th>
          </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No orphaned student users found.</p>";
}

$conn->close();
?>
