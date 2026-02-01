<?php
require_once '../includes/db_connection.php';

$output = "";
$output .= "Describe students table:\n";
$result = $conn->query("DESC students");
if ($result) {
    while($row = $result->fetch_assoc()) {
        $output .= $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    $output .= "Query failed: " . $conn->error;
}

// Also check the last student added based on test script logic
$output .= "\nLast 5 students (sorted by student_id DESC):\n";
$result = $conn->query("SELECT * FROM students ORDER BY student_id DESC LIMIT 5");
if ($result) {
    while($row = $result->fetch_assoc()) {
        $output .= print_r($row, true) . "\n";
    }
} else {
    $output .= "Select failed: " . $conn->error . "\n";
}

file_put_contents('result.txt', $output);
echo "Done";
?>
