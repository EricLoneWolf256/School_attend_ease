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

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare('SELECT user_id, first_name, email FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate a unique token
                $token = bin2hex(random_bytes(50));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $stmt = $db->prepare('INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, ?)');
                $stmt->execute([$email, $token, $expires]);
                
                // Send email with reset link
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
                $subject = "Password Reset Request";
                $message = "
                    <h2>Password Reset Request</h2>
                    <p>Hello " . htmlspecialchars($user['first_name']) . ",</p>
                    <p>You requested to reset your password. Click the link below to set a new password:</p>
                    <p><a href='" . $resetLink . "' style='display: inline-block; padding: 10px 20px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                ";
                
                // Send email (you'll need to implement this function)
                if (sendEmail($email, $subject, $message)) {
                    $success = 'Password reset link has been sent to your email address.';
                } else {
                    $error = 'Failed to send reset email. Please try again.';
                }
            } else {
                // Don't reveal if the email exists or not
                $success = 'If your email exists in our system, you will receive a password reset link.';
            }
        } catch (PDOException $e) {
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
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
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
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1>Reset Your Password</h1>
            <p class="text-center text-muted mb-4">Enter your email address and we'll send you a link to reset your password.</p>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php else: ?>
                <form method="POST" action="forgot-password.php">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email address" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Send Reset Link
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                Remembered your password? <a href="index.php">Back to Login</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
