<?php
// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';

// Create connection without selecting a database
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Setting up Attendance System Database</h2>";

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "<p>✅ Database 'attendance_system' created successfully or already exists.</p>";
} else {
    echo "<p>❌ Error creating database: " . $conn->error . "</p>";
    exit();
}

// Select the database
$conn->select_db('attendance_system');

// SQL to create users table
$sql = "CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `role` ENUM('admin', 'lecturer', 'student') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `last_login` DATETIME DEFAULT NULL,
    `profile_picture` VARCHAR(255) DEFAULT NULL,
    `reset_token` VARCHAR(100) DEFAULT NULL,
    `reset_expires` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "<p>✅ Table 'users' created successfully or already exists.</p>";
} else {
    echo "<p>❌ Error creating users table: " . $conn->error . "</p>";
}

// SQL to create students table
$sql = "CREATE TABLE IF NOT EXISTS `students` (
    `student_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `student_number` VARCHAR(50) NOT NULL UNIQUE,
    `program` VARCHAR(100) NOT NULL,
    `year_level` VARCHAR(20) NOT NULL,
    `admission_date` DATE DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'graduated', 'suspended') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "<p>✅ Table 'students' created successfully or already exists.</p>";
} else {
    echo "<p>❌ Error creating students table: " . $conn->error . "</p>";
}

// Create admin user if not exists
$admin_username = 'admin';
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$admin_email = 'admin@example.com';

$check_admin = $conn->query("SELECT user_id FROM users WHERE username = '$admin_username' OR email = '$admin_email'");

if ($check_admin->num_rows == 0) {
    $sql = "INSERT INTO users (username, password, email, first_name, last_name, role) 
            VALUES ('$admin_username', '$admin_password', '$admin_email', 'System', 'Administrator', 'admin')";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>✅ Admin user created successfully.</p>";
        echo "<p>Username: admin</p>";
        echo "<p>Password: admin123</p>";
        echo "<p style='color: red; font-weight: bold;'>IMPORTANT: Please change the default password after logging in!</p>";
    } else {
        echo "<p>❌ Error creating admin user: " . $conn->error . "</p>";
    }
} else {
    echo "<p>ℹ️ Admin user already exists.</p>";
}

echo "<h3>Setup Complete!</h3>";
echo "<p>You can now <a href='login.php'>login to the system</a>.</p>";

$conn->close();
?>
