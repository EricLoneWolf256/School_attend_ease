<?php
require_once 'config.php';

try {
    // Create connection
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL to create activity_log table
    $sql = "CREATE TABLE IF NOT EXISTS `activity_log` (
        `log_id` INT PRIMARY KEY AUTO_INCREMENT,
        `user_id` INT,
        `entity_type` ENUM('lecture', 'attendance', 'course', 'user') NOT NULL,
        `entity_id` INT NOT NULL,
        `action` VARCHAR(50) NOT NULL,
        `details` TEXT,
        `ip_address` VARCHAR(45),
        `user_agent` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Execute SQL
    $db->exec($sql);
    echo "Table 'activity_log' created successfully\n";
    
    // Add some initial data
    $db->exec("INSERT INTO `activity_log` (user_id, entity_type, entity_id, action, details) VALUES 
        (1, 'system', 1, 'system_start', 'System initialized and activity log table created')
    ");
    echo "Initial log entry added\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
