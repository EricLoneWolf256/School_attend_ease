-- Add faculty column to users table
ALTER TABLE users 
ADD COLUMN faculty VARCHAR(100) NULL AFTER role;

-- Update existing users to have a default faculty
UPDATE users SET faculty = 'Science' WHERE faculty IS NULL;
