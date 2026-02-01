<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

echo "<h1>Database Table Fixes</h1>";
echo "<div style='font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto;'>";

try {
    // Get database connection
    $pdo = getDBConnection();
    
    // 1. Check and create user_courses table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `user_courses` (
        `user_course_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `course_id` INT NOT NULL,
        `role` ENUM('student', 'lecturer') NOT NULL,
        `enrollment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE,
        UNIQUE (`user_id`, `course_id`, `role`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $result = executeQuery($pdo, $sql);
    echo $result['success'] 
        ? "<p style='color:green'>✓ Created user_courses table</p>" 
        : "<p style='color:orange'>ℹ️ user_courses table already exists or error: " . $result['message'] . "</p>";
    
    // 2. Add missing columns to lectures table if they don't exist
    $columns = [
        'secret_code' => "VARCHAR(10) NULL",
        'code_expiry' => "DATETIME NULL",
        'is_active' => "BOOLEAN DEFAULT FALSE"
    ];
    
    foreach ($columns as $column => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM `lectures` LIKE '$column'");
        if ($check->rowCount() == 0) {
            $sql = "ALTER TABLE `lectures` ADD COLUMN `$column` $definition";
            $result = executeQuery($pdo, $sql);
            echo $result['success'] 
                ? "<p style='color:green'>✓ Added column '$column' to lectures table</p>" 
                : "<p style='color:red'>❌ Error adding column '$column': " . $result['message'] . "</p>";
        } else {
            echo "<p style='color:blue'>ℹ️ Column '$column' already exists in lectures table</p>";
        }
    }
    
    // 3. Check if student_courses table exists (for backward compatibility)
    $check = $pdo->query("SHOW TABLES LIKE 'student_courses'");
    if ($check->rowCount() > 0) {
        echo "<p style='color:blue'>ℹ️ Found student_courses table. Migrating data to user_courses...</p>";
        
        // Migrate data from student_courses to user_courses
        $migrate_sql = "
            INSERT IGNORE INTO user_courses (user_id, course_id, role, enrollment_date)
            SELECT student_id, course_id, 'student', NOW() 
            FROM student_courses
            WHERE (student_id, course_id) NOT IN (
                SELECT user_id, course_id FROM user_courses WHERE role = 'student'
            )";
            
        $result = executeQuery($pdo, $migrate_sql);
        if ($result['success']) {
            $migrated = $result['stmt']->rowCount();
            echo "<p style='color:green'>✓ Migrated $migrated records from student_courses to user_courses</p>";
        } else {
            echo "<p style='color:orange'>⚠️ Could not migrate data: " . $result['message'] . "</p>";
        }
    }
    
    // 4. Check if course_assignments table exists and has data
    $check = $pdo->query("SELECT COUNT(*) as count FROM course_assignments");
    $count = $check->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        echo "<p style='color:blue'>ℹ️ Found $count course assignments. Migrating to user_courses...</p>";
        
        // Migrate course_assignments to user_courses
        $migrate_sql = "
            INSERT IGNORE INTO user_courses (user_id, course_id, role, enrollment_date)
            SELECT lecturer_id, course_id, 'lecturer', assigned_at 
            FROM course_assignments
            WHERE (lecturer_id, course_id) NOT IN (
                SELECT user_id, course_id FROM user_courses WHERE role = 'lecturer'
            )";
            
        $result = executeQuery($pdo, $migrate_sql);
        if ($result['success']) {
            $migrated = $result['stmt']->rowCount();
            echo "<p style='color:green'>✓ Migrated $migrated lecturer assignments to user_courses</p>";
        } else {
            echo "<p style='color:orange'>⚠️ Could not migrate course assignments: " . $result['message'] . "</p>";
        }
    }
    
    echo "<h2>Database Fixes Completed</h2>";
    echo "<p><a href='lecturer/lectures.php' class='btn'>Return to Lectures</a></p>";
    
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
