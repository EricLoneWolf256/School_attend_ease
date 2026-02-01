<?php
require_once 'includes/db_connection.php';

// Check if the student number exists
$student_number = '2024-Bs011-13487';

// Check in students table
$query = "SELECT * FROM students WHERE student_number = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "Student found in students table:<br>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "No student found with number: " . htmlspecialchars($student_number) . " in students table<br>";
}

// Also check in users table in case there's a mismatch
$query = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<br><br>User found in users table:<br>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "<br>No user found with username: " . htmlspecialchars($student_number) . " in users table";
}

// Check for similar student numbers
$like_number = '%' . $student_number . '%';
$query = "SELECT student_number FROM students WHERE student_number LIKE ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $like_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<br><br>Similar student numbers found:<br>";
    while ($row = $result->fetch_assoc()) {
        echo htmlspecialchars($row['student_number']) . "<br>";
    }
} else {
    echo "<br>No similar student numbers found";
}

$conn->close();
?>
