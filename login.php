<?php
require_once 'config.php';
require_once 'includes/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare('SELECT user_id, email, password, role, first_name, last_name, profile_pic FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                
                // Set profile picture if it exists - store ONLY the filename
                if (!empty($user['profile_pic'])) {
                    $_SESSION['profile_pic'] = $user['profile_pic'];
                } else {
                    $_SESSION['profile_pic'] = ''; // Empty for default avatar
                }
                
                // Redirect based on role
                $redirect = ($user['role'] === 'admin') ? 'admin/dashboard.php' : 'index.php';
                redirect($redirect);
            } else {
                $error = 'Invalid email or password';
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
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/ghost/assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%) !important;
            font-family: 'Inter', -apple-system, sans-serif;
            color: #fff;
        }
        .login-container {
            max-width: 420px;
            width: 100%;
            padding: 20px;
            margin: auto;
        }
        .card {
            background: rgba(255, 255, 255, 0.05) !important;
            backdrop-filter: blur(15px) saturate(180%);
            -webkit-backdrop-filter: blur(15px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 20px !important;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }
        .card-header {
            background: rgba(153, 0, 0, 0.2) !important;
            color: var(--secondary-color) !important;
            text-align: center;
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
        .card-header h4 {
            font-weight: 800;
            margin-bottom: 0.5rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .card-header p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }
        .btn-primary {
            background-color: var(--primary-color) !important;
            border: none;
            padding: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(153, 0, 0, 0.3);
        }
        .btn-primary:hover {
            background-color: #b30000 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(153, 0, 0, 0.4);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
            padding: 0.75rem 1rem;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.1) !important;
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.25rem rgba(153, 0, 0, 0.25);
        }
        .forgot-password a, .text-center a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 600;
        }
        .forgot-password a:hover, .text-center a:hover {
            color: #fff;
            text-decoration: underline;
        }
        .input-group-text {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: var(--secondary-color);
            border-right: none;
        }
        .app-logo {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            filter: drop-shadow(0 0 10px rgba(153, 0, 0, 0.5));
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center">
            <i class="fas fa-calendar-check app-logo"></i>
        </div>
        <div class="card glass">
            <div class="card-header">
                <h4>ATTEND <span style="color: #fff;">EASE</span></h4>
                <p class="mb-0">Sign in to your account</p>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="forgot-password">
                        <a href="forgot-password.php">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Sign In</button>
                </form>
                
                <div class="text-center mt-3">
                    Don't have an account? <a href="register.php">Register here</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
