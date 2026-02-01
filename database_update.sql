-- Add attendance_records table if it doesn't exist
CREATE TABLE IF NOT EXISTS `attendance_records` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `lecture_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'absent',
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `marked_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `unique_attendance` (`lecture_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `marked_by` (`marked_by`),
  CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`lecture_id`) REFERENCES `lectures` (`lecture_id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_records_ibfk_3` FOREIGN KEY (`marked_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add students table if it doesn't exist
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `student_number` varchar(20) NOT NULL,
  `program` varchar(100) NOT NULL DEFAULT 'Undeclared',
  `year_level` int(11) NOT NULL DEFAULT 1,
  `status` enum('active','inactive','suspended','graduated') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `student_number` (`student_number`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `student_status` (`status`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add student_courses table for course enrollment
CREATE TABLE IF NOT EXISTS `student_courses` (
  `enrollment_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrollment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('enrolled','completed','dropped') NOT NULL DEFAULT 'enrolled',
  `grade` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`enrollment_id`),
  UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `student_courses_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `student_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_student_number` ON `students` (`student_number`);
CREATE INDEX IF NOT EXISTS `idx_student_program` ON `students` (`program`);
CREATE INDEX IF NOT EXISTS `idx_student_status` ON `students` (`status`);
CREATE INDEX IF NOT EXISTS `idx_enrollment_student` ON `student_courses` (`student_id`);
CREATE INDEX IF NOT EXISTS `idx_enrollment_course` ON `student_courses` (`course_id`);

-- Create trigger for new student users
DELIMITER //
CREATE TRIGGER IF NOT EXISTS `after_user_insert`
AFTER INSERT ON `users`
FOR EACH ROW
BEGIN
    IF NEW.role = 'student' THEN
        INSERT INTO students (user_id, student_number, program, year_level)
        VALUES (NEW.user_id, NEW.username, 'Undeclared', 1);
    END IF;
END;//
DELIMITER ;

-- Add any missing columns to existing tables
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `first_name` varchar(50) NOT NULL AFTER `email`,
ADD COLUMN IF NOT EXISTS `last_name` varchar(50) NOT NULL AFTER `first_name`,
ADD COLUMN IF NOT EXISTS `role` enum('admin','lecturer','student') NOT NULL AFTER `password`,
ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp() AFTER `role`,
ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`;

-- Update existing admin user if needed
UPDATE `users` SET 
  `first_name` = 'System',
  `last_name` = 'Admin',
  `role` = 'admin'
WHERE `username` = 'admin' AND `role` = '';

-- Add any other missing columns to other tables as needed
ALTER TABLE `lectures` 
ADD COLUMN IF NOT EXISTS `is_active` tinyint(1) NOT NULL DEFAULT 0 AFTER `end_time`,
ADD COLUMN IF NOT EXISTS `secret_code` varchar(6) DEFAULT NULL AFTER `is_active`,
ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp() AFTER `secret_code`,
ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`;

-- Add any other necessary database updates here

-- Create a view for attendance summary reports
CREATE OR REPLACE VIEW `vw_attendance_summary` AS
SELECT 
    c.course_id,
    c.course_code,
    c.course_name,
    u.user_id as lecturer_id,
    CONCAT(u.first_name, ' ', u.last_name) as lecturer_name,
    COUNT(DISTINCT l.lecture_id) as total_lectures,
    COUNT(DISTINCT sc.user_id) as enrolled_students,
    IFNULL(SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END), 0) as present_count,
    IFNULL(SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END), 0) as absent_count,
    IFNULL(SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END), 0) as late_count,
    COUNT(ar.record_id) as total_records
FROM courses c
LEFT JOIN course_assignments ca ON c.course_id = ca.course_id
LEFT JOIN users u ON ca.lecturer_id = u.user_id
LEFT JOIN lectures l ON c.course_id = l.course_id
LEFT JOIN student_courses sc ON c.course_id = sc.course_id
LEFT JOIN attendance_records ar ON l.lecture_id = ar.lecture_id
GROUP BY c.course_id, u.user_id;
