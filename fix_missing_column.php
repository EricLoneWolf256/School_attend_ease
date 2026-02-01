<?php
// Database configuration
$db_host = 'localhost';
$db_name = 'attendance_system';
$db_user = 'root';
$db_pass = '';

try {
    // Create connection
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    
    if($check->rowCount() == 0) {
        // Column doesn't exist, so add it
        $sql = "ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE";
        $conn->exec($sql);
        echo "Successfully added is_active column to users table\n";
    } else {
        echo "is_active column already exists in users table\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
