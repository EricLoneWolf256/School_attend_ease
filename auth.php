<?php
require_once 'config.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables - standardized format
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            
            // Set profile picture - store ONLY the filename
            $_SESSION['profile_pic'] = $user['profile_pic'] ?? '';
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    redirect('admin/dashboard.php');
                    break;
                case 'lecturer':
                    redirect('lecturer/dashboard.php');
                    break;
                case 'student':
                    redirect('student/dashboard.php');
                    break;
                default:
                    redirect('index.php');
            }
        } else {
            $_SESSION['error'] = 'Invalid email or password';
            redirect('index.php');
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        redirect('index.php');
    }
}

// Handle student registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $email = sanitize($_POST['email']);
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $faculty = sanitize($_POST['faculty']);
    
    // Basic validation
    if (empty($username) || empty($password) || empty($email) || empty($firstName) || empty($lastName) || empty($faculty)) {
        $_SESSION['error'] = 'All fields are required';
        redirect('register.php');
    }
    
    if (strlen($password) < 8) {
        $_SESSION['error'] = 'Password must be at least 8 characters long';
        redirect('register.php');
    }
    
    try {
        $db = getDBConnection();
        
        // Check if username or email already exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = 'Username or email already exists';
            redirect('register.php');
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert new student
        $stmt = $db->prepare("INSERT INTO users (username, password, email, first_name, last_name, faculty, role) VALUES (?, ?, ?, ?, ?, ?, 'student')");
        $stmt->execute([$username, $hashedPassword, $email, $firstName, $lastName, $faculty]);
        
        $_SESSION['success'] = 'Registration successful! Please login.';
        redirect('index.php');
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Registration failed: ' . $e->getMessage();
        redirect('register.php');
    }
}

// If no valid action, redirect to home
redirect('index.php');
?>
