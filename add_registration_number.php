<?php
// Include database configuration
require_once 'config.php';

// Function to execute SQL queries
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return [
            'success' => true,
            'message' => 'Query executed successfully',
            'stmt' => $stmt
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

echo "<h1>Database Update: Add Registration Number</h1>";
echo "<div style='font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto;'>";

try {
    // Get database connection
    $pdo = getDBConnection();
    
    // 1. Add registration_number column to users table if it doesn't exist
    $sql = "SHOW COLUMNS FROM `users` LIKE 'registration_number'";
    $result = $pdo->query($sql);
    
    if ($result->rowCount() == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE `users` 
                ADD COLUMN `registration_number` VARCHAR(20) NULL COMMENT 'Student registration number' AFTER `last_name`,
                ADD INDEX `idx_registration_number` (`registration_number`)";
        
        $result = executeQuery($pdo, $sql);
        
        if ($result['success']) {
            echo "<p style='color:green'>✓ Added registration_number column to users table</p>";
            
            // Generate registration numbers for existing students if needed
            // Format: YYYY-XXXXX (e.g., 2023-00001)
            $sql = "UPDATE users 
                   SET registration_number = CONCAT(YEAR(created_at), '-', LPAD(user_id, 5, '0'))
                   WHERE role = 'student' AND (registration_number IS NULL OR registration_number = '')";
            
            $result = executeQuery($pdo, $sql);
            
            if ($result['success']) {
                $count = $result['stmt']->rowCount();
                echo "<p style='color:green'>✓ Generated registration numbers for $count students</p>";
            } else {
                echo "<p style='color:orange'>⚠️ Could not generate registration numbers: " . $result['message'] . "</p>";
            }
        } else {
            echo "<p style='color:red'>❌ Error adding registration_number column: " . $result['message'] . "</p>";
        }
    } else {
        echo "<p style='color:blue'>ℹ️ registration_number column already exists in users table</p>";
    }
    
    // 2. Update the attendance query to handle cases where registration_number might be null
    $file_path = dirname(__FILE__) . '/lecturer/attendance.php';
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        $new_content = str_replace(
            's.registration_number,',
            'IFNULL(s.registration_number, CONCAT("STU-", s.user_id)) as registration_number,',
            $content
        );
        
        if (file_put_contents($file_path, $new_content)) {
            echo "<p style='color:green'>✓ Updated attendance.php to handle null registration numbers</p>";
        } else {
            echo "<p style='color:orange'>⚠️ Could not update attendance.php. Please check file permissions.</p>";
        }
    }
    
    echo "<h2>Database Update Completed</h2>";
    echo "<p><a href='lecturer/attendance.php' class='btn'>Go to Attendance</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Database Error: " . $e->getMessage() . "</p>";
}

// Add some basic styling
echo "
<style>
    body { 
        font-family: Arial, sans-serif; 
        margin: 20px; 
        line-height: 1.6;
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    h1 { 
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }
    h2 {
        color: #34495e;
        margin-top: 30px;
    }
    .btn {
        display: inline-block;
        background: #3498db;
        color: white;
        padding: 10px 15px;
        text-decoration: none;
        border-radius: 4px;
        margin-top: 20px;
    }
    .btn:hover {
        background: #2980b9;
    }
</style>";

echo "</div>"; // Close container
?>
