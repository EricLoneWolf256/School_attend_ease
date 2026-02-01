-- Database update: Add is_late column to attendance table
-- Date: 2025-12-14
-- Description: Adds is_late column to track late arrivals in the attendance system

-- Add is_late column to attendance table
ALTER TABLE `attendance` ADD COLUMN `is_late` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Flag indicating if the student was late' AFTER `status`;

-- Add status column if it doesn't exist (for backward compatibility)
SET @dbname = DATABASE();
SET @tablename = "attendance";
SET @columnname = "status";
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE (table_schema = @dbname) 
        AND (table_name = @tablename) 
        AND (column_name = @columnname)
    ) = 0,
    "ALTER TABLE `attendance` ADD COLUMN `status` ENUM('present', 'absent', 'late') DEFAULT 'absent' AFTER `feedback`;",
    'SELECT 1;'
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add attendance_code column if it doesn't exist (for backward compatibility)
SET @columnname = "attendance_code";
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE (table_schema = @dbname) 
        AND (table_name = @tablename) 
        AND (column_name = @columnname)
    ) = 0,
    "ALTER TABLE `attendance` ADD COLUMN `attendance_code` VARCHAR(10) DEFAULT NULL AFTER `status`;",
    'SELECT 1;'
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
