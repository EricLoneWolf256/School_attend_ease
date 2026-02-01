<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "START_DEBUG\n";

$db_path = '../includes/db_connection.php';
if (!file_exists($db_path)) {
    die("DB Connection file not found at $db_path");
}
require_once $db_path;

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Users Table Check:\n";
$users = $conn->query("SELECT user_id, role, email FROM users");
if (!$users) {
    echo "Query Error: " . $conn->error . "\n";
} else {
    echo "Found " . $users->num_rows . " users.\n";
    while ($row = $users->fetch_assoc()) {
        echo "ID: " . $row['user_id'] . " | Role: " . $row['role'] . " | Email: " . $row['email'] . "\n";
    }
}

echo "\nStudents Table Check:\n";
$students = $conn->query("SELECT * FROM students");
if (!$students) {
    echo "Query Error: " . $conn->error . "\n";
} else {
    echo "Found " . $students->num_rows . " students.\n";
    while ($row = $students->fetch_assoc()) {
        $sid = isset($row['student_id']) ? $row['student_id'] : 'MISSING_KEY_student_id';
        $uid = isset($row['user_id']) ? $row['user_id'] : 'MISSING_KEY_user_id';
        $sno = isset($row['student_number']) ? $row['student_number'] : 'MISSING_KEY_student_number';
        echo "PK: $sid | FK(user_id): $uid | StudentNo: $sno\n";
    }
}

echo "END_DEBUG";
?>
