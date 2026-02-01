<?php
// Include database configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connection.php';

try {
    // Check if the column already exists
    $checkQuery = "SELECT COUNT(*) as column_exists 
                   FROM information_schema.columns 
                   WHERE table_schema = 'attendance_system' 
                   AND table_name = 'lectures' 
                   AND column_name = 'code_generated_at'";
    
    $result = $conn->query($checkQuery);
    $columnExists = $result->fetch_assoc()['column_exists'] > 0;
    
    if (!$columnExists) {
        // Add the column if it doesn't exist
        $alterQuery = "ALTER TABLE lectures 
                      ADD COLUMN code_generated_at DATETIME DEFAULT NULL 
                      COMMENT 'Timestamp when the attendance code was generated'";
        
        if ($conn->query($alterQuery)) {
            echo "Successfully added 'code_generated_at' column to 'lectures' table.\n";
        } else {
            throw new Exception("Error adding column: " . $conn->error);
        }
    } else {
        echo "Column 'code_generated_at' already exists in 'lectures' table.\n";
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}

echo "Database update check completed.\n";
?>
