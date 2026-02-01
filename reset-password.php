<?php
require_once 'config.php';
require_once 'includes/session.php';
require_once 'includes/db_connection.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';
$validToken = false;
$email = '';

// Check if token is provided and valid
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        $db = getDBConnection();
        $stmt = $db->prepare('SELECT * FROM password_resets WHERE token = ? AND created_at > NOW() AND used = 0');
        $stmt->execute([$token]);
        $resetRequest = $stmt->fetch();
        
        if ($resetRequest) {
            $validToken = true;
            $email = $resetRequest['email'];
        } else {
            $error = 'Invalid or expired reset link. Please request a new password reset.';
        }
    } catch (PDOException $e) {
        $error = 'An error occurred. Please try again.';
        // For debugging: $error = $e->getMessage();
    }
} else {
    $error = 'Invalid reset link. Please use the link sent to your email.';
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } else {
        try {
            $db = getDBConnection();
            
            // Start transaction
            $db->beginTransaction();
            
            // Update user's password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE users SET password = ? WHERE email = ?');
            $stmt->execute([$hashedPassword, $email]);
            
            // Mark token as used
            $stmt = $db->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
            $stmt->execute([$token]);
            
            // Commit transaction
            $db->commit();
            
            $success = 'Your password has been reset successfully. You can now <a href="index.php" class="text-primary">login</a> with your new password.';
            $validToken = false; // Prevent showing the form again
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'An error occurred. Please try again.';
            // For debugging: $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            background-color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .auth-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        .auth-box {
            background: #fff;
            border: 2px solid #dc3545;
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        .auth-box:hover {
            box-shadow: 0 6px 24px rgba(220, 53, 69, 0.15);
        }
        .auth-box h1 {
            color: #333;
            font-size: 1.75rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #444;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
            outline: none;
        }
        .btn-primary {
            background-color: #000000;
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 6px;
            font-weight: 500;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            font-size: 1rem;
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: #dc3545 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #666;
            font-size: 0.95rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        .auth-footer a {
            color: #dc3545;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .auth-footer a:hover {
            color: #a71d2a;
            text-decoration: underline;
        }
        .alert {
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .alert-danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }
        .alert-success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
        }
        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1>Reset Your Password</h1>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($validToken): ?>
                <p class="text-center text-muted mb-4">Enter your new password below.</p>
                
                <form method="POST" action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="Enter new password" required minlength="8"
                                   pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                   title="Must contain at least one number, one uppercase and lowercase letter, and at least 8 or more characters">
                        </div>
                        <div class="password-requirements">
                            Must be at least 8 characters and include uppercase, lowercase, and numbers
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   class="form-control" placeholder="Confirm new password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key mr-2"></i>
                        Reset Password
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                Remembered your password? <a href="index.php">Back to Login</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password match validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validatePassword() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        if (password && confirmPassword) {
            password.onchange = validatePassword;
            confirmPassword.onkeyup = validatePassword;
        }
    </script>
</body>
</html>
