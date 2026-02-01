<?php
// Include database connection
require_once 'config.php';
require_once 'includes/db_connection.php';

$db = getDBConnection();

try {
    // Check if the column already exists
    $stmt = $db->query("SHOW COLUMNS FROM student_courses LIKE 'enrollment_date'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Add the enrollment_date column
        $sql = "ALTER TABLE student_courses ADD COLUMN enrollment_date DATETIME DEFAULT CURRENT_TIMESTAMP AFTER course_id";
        $db->exec($sql);
        echo "Successfully added 'enrollment_date' column to 'student_courses' table.\n";
    } else {
        echo "The 'enrollment_date' column already exists in the 'student_courses' table.\n";
    }
    
    // Show the table structure for verification
    echo "\nCurrent structure of student_courses table:\n";
    $result = $db->query("DESCRIBE student_courses");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}

echo "\nScript completed successfully.\n";
?>
