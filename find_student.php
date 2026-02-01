<?php
require_once 'includes/db_connection.php';

$student_number = '2024-Bs011-13499';

// Query to find the student
$query = "SELECT s.student_id, u.first_name, u.last_name, u.email, s.program, s.year_level, s.status 
          FROM students s 
          JOIN users u ON s.user_id = u.user_id 
          WHERE s.student_number = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
    echo "<h2>Student Found</h2>";
    echo "<pre>";
    print_r($student);
    echo "</pre>";
} else {
    echo "No student found with ID: " . htmlspecialchars($student_number);
}

// Close connection
$stmt->close();
$conn->close();
?>
