<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'attendance_system';

// Connect to MySQL
$conn = new mysqli($db_host, $db_user, $db_pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "Database checked/created successfully<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($db_name);

// SQL statements to create tables if they don't exist
$tables = [];

// Users table
$tables[] = "CREATE TABLE IF NOT EXISTS `users` (
    `user_id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `password` varchar(255) NOT NULL,
    `email` varchar(100) NOT NULL,
    `first_name` varchar(50) NOT NULL,
    `last_name` varchar(50) NOT NULL,
    `role` enum('admin','lecturer','student') NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Courses table
$tables[] = "CREATE TABLE IF NOT EXISTS `courses` (
    `course_id` int(11) NOT NULL AUTO_INCREMENT,
    `course_code` varchar(20) NOT NULL,
    `course_name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`course_id`),
    UNIQUE KEY `course_code` (`course_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Course assignments
$tables[] = "CREATE TABLE IF NOT EXISTS `course_assignments` (
    `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
    `lecturer_id` int(11) NOT NULL,
    `course_id` int(11) NOT NULL,
    `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`assignment_id`),
    UNIQUE KEY `unique_assignment` (`lecturer_id`,`course_id`),
    KEY `course_id` (`course_id`),
    CONSTRAINT `course_assignments_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `course_assignments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Lectures/Schedules
$tables[] = "CREATE TABLE IF NOT EXISTS `lectures` (
    `lecture_id` int(11) NOT NULL AUTO_INCREMENT,
    `course_id` int(11) NOT NULL,
    `lecturer_id` int(11) NOT NULL,
    `title` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `scheduled_date` date NOT NULL,
    `start_time` time NOT NULL,
    `end_time` time NOT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 0,
    `secret_code` varchar(6) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`lecture_id`),
    KEY `course_id` (`course_id`),
    KEY `lecturer_id` (`lecturer_id`),
    CONSTRAINT `lectures_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
    CONSTRAINT `lectures_ibfk_2` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Students table
$tables[] = "CREATE TABLE IF NOT EXISTS `students` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Student courses
$tables[] = "CREATE TABLE IF NOT EXISTS `student_courses` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Attendance records
$tables[] = "CREATE TABLE IF NOT EXISTS `attendance_records` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Execute table creation
foreach ($tables as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table created successfully: " . explode('`', $sql)[1] . "<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
}

// Create indexes
$indexes = [
    "CREATE INDEX IF NOT EXISTS `idx_student_number` ON `students` (`student_number`)",
    "CREATE INDEX IF NOT EXISTS `idx_student_program` ON `students` (`program`)",
    "CREATE INDEX IF NOT EXISTS `idx_student_status` ON `students` (`status`)",
    "CREATE INDEX IF NOT EXISTS `idx_enrollment_student` ON `student_courses` (`student_id`)",
    "CREATE INDEX IF NOT EXISTS `idx_enrollment_course` ON `student_courses` (`course_id`)"
];

foreach ($indexes as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Index created successfully<br>";
    } else {
        echo "Error creating index: " . $conn->error . "<br>";
    }
}

// Create trigger for new student users
try {
    $conn->query("DROP TRIGGER IF EXISTS `after_user_insert`");
    $trigger_sql = "
    CREATE TRIGGER `after_user_insert`
    AFTER INSERT ON `users`
    FOR EACH ROW
    BEGIN
        IF NEW.role = 'student' THEN
            INSERT INTO students (user_id, student_number, program, year_level)
            VALUES (NEW.user_id, NEW.username, 'Undeclared', 1);
        END IF;
    END;";
    
    if ($conn->multi_query($trigger_sql)) {
        echo "Trigger created successfully<br>";
    } else {
        echo "Error creating trigger: " . $conn->error . "<br>";
    }
} catch (Exception $e) {
    echo "Error with trigger: " . $e->getMessage() . "<br>";
}

// Create admin user if not exists
$admin_username = 'admin';
$admin_email = 'admin@example.com';
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);

$check_admin = $conn->query("SELECT user_id FROM users WHERE username = '$admin_username' OR email = '$admin_email'");

if ($check_admin->num_rows == 0) {
    $sql = "INSERT INTO users (username, password, email, first_name, last_name, role) 
            VALUES ('$admin_username', '$admin_password', '$admin_email', 'System', 'Admin', 'admin')";
    
    if ($conn->query($sql) === TRUE) {
        echo "Admin user created successfully<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
        echo "<strong>Please change the default password after logging in!</strong><br>";
    } else {
        echo "Error creating admin user: " . $conn->error . "<br>";
    }
} else {
    echo "Admin user already exists<br>";
}

// Create view for attendance summary
$view_sql = "
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
GROUP BY c.course_id, u.user_id;";

if ($conn->multi_query($view_sql)) {
    echo "View created successfully<br>";
} else {
    echo "Error creating view: " . $conn->error . "<br>";
}

echo "<h3>Database setup completed!</h3>";
$conn->close();
?>

<h4>Next Steps:</h4>
<ol>
    <li>Go to <a href='admin/'>Admin Panel</a></li>
    <li>Login with the admin credentials shown above</li>
    <li>Change the default admin password immediately</li>
    <li>Start adding courses, students, and managing attendance</li>
</ol>

<p>After completing the setup, please delete or secure this file for security reasons.</p>
