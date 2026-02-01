<?php
require_once 'config.php';
require_once 'includes/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
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
            // Logout if role is invalid
            session_destroy();
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
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
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        .auth-box {
            background: #fff;
            border: 2px solid #990000;
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        .auth-box:hover {
            box-shadow: 0 6px 24px rgba(153, 0, 0, 0.15);
        }
        .auth-box h1 {
            color: #333;
            font-size: 1.75rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.75rem;
        }
        .auth-subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 1rem;
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
            border-color: #990000;
            box-shadow: 0 0 0 0.25rem rgba(153, 0, 0, 0.25);
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: #990000 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(153, 0, 0, 0.3);
        }
        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #666;
            font-size: 0.95rem;
        }
        .auth-footer a {
            color: #990000;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .auth-footer a:hover {
            color: #660000;
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
            <h1>Welcome to <?php echo SITE_NAME; ?></h1>
            <p class="auth-subtitle">Please sign in to continue</p>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger mb-4 p-3 rounded bg-red-50 text-red-700 border border-red-200">
                    <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success mb-4 p-3 rounded bg-green-50 text-green-700 border border-green-200">
                    <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <form action="auth.php" method="POST" class="auth-form">
                <input type="hidden" name="login" value="1">
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required 
                           class="form-control" placeholder="Enter your email">
                </div>
                
                <div class="form-group">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="password">Password</label>
                        <a href="forgot-password.php" class="text-decoration-none" style="font-size: 0.9rem; color: #666; transition: color 0.2s ease;">
                            Forgot password?
                        </a>
                    </div>
                    <input type="password" id="password" name="password" required 
                           class="form-control" placeholder="Enter your password">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign In
                    </button>
                </div>
            </form>
            
            <div class="auth-footer">
                Don't have an account? <a href="register.php" style="color: #990000; text-decoration: none; font-weight: 500; transition: color 0.2s ease;" onmouseover="this.style.color='#660000'" onmouseout="this.style.color='#990000'">Register as Student</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add active class to form inputs when focused
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('active');
            });
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('active');
                }
            });
            
            // Check if input has value on page load
            if (input.value) {
                input.parentElement.classList.add('active');
            }
        });
    </script>
</body>
</html>
