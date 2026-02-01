<?php
require_once 'config.php';
$db = getDBConnection();

$tables = ['student_courses', 'activity_log', 'courses', 'course_assignments', 'users', 'students'];

foreach ($tables as $table) {
    echo "Table: $table\n";
    try {
        $stmt = $db->query("DESCRIBE $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  {$row['Field']} - {$row['Type']}\n";
        }
    } catch (PDOException $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
?>
