-- Database updates for attendance system
-- Date: 2025-12-13

-- Add attendance code columns to lectures table
ALTER TABLE lectures 
ADD COLUMN IF NOT EXISTS attendance_code VARCHAR(10) NULL,
ADD COLUMN IF NOT EXISTS code_generated_at DATETIME NULL,
ADD COLUMN IF NOT EXISTS code_expires_at DATETIME NULL;

-- Add status column to attendance table
ALTER TABLE attendance 
ADD COLUMN IF NOT EXISTS status ENUM('present', 'absent', 'late') DEFAULT 'absent';

-- Create activity_log table if it doesn't exist
CREATE TABLE IF NOT EXISTS activity_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    entity_type ENUM('user', 'lecture', 'course', 'attendance') NOT NULL,
    entity_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for better performance
CREATE INDEX IF NOT EXISTS idx_activity_log_entity ON activity_log(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_activity_log_user ON activity_log(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_log_created ON activity_log(created_at);

-- Add is_late column to attendance table for better querying
ALTER TABLE attendance 
ADD COLUMN IF NOT EXISTS is_late TINYINT(1) DEFAULT 0;

-- Create uploads directory if it doesn't exist
-- Note: This is a comment - the directory should be created manually
-- Create directory: C:\wamp64\www\ghost\uploads
-- Set permissions to 755 (rwxr-xr-x)
