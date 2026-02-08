<?php
require_once 'config.php';
require_once 'includes/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #990000;
            --secondary-color: #FFD700;
            --accent-color: #000000;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%) !important;
            font-family: 'Inter', -apple-system, sans-serif;
            margin: 0;
            padding: 20px;
            color: #fff;
        }
        .auth-container {
            max-width: 550px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        .auth-box {
            background: rgba(255, 255, 255, 0.05) !important;
            backdrop-filter: blur(15px) saturate(180%);
            -webkit-backdrop-filter: blur(15px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 20px !important;
            padding: 3rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
        }
        .auth-box h1 {
            color: var(--secondary-color);
            font-size: 2rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 2rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: #fff !important;
            padding: 0.8rem 1rem;
            border-radius: 10px;
            font-family: 'Inter', -apple-system, sans-serif !important;
            font-size: 0.95rem !important;
        }
        .form-select {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: #fff !important;
            padding: 0.8rem 1rem;
            border-radius: 10px;
            font-family: 'Inter', -apple-system, sans-serif !important;
            font-size: 0.95rem !important;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='rgba(255,255,255,0.7)' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.7rem center !important;
            background-size: 1.2em !important;
            padding-right: 2.5rem !important;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6) !important;
        }
        .form-select option {
            background: #1a1a1a !important;
            color: #fff !important;
            padding: 10px;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15) !important;
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.25rem rgba(153, 0, 0, 0.25) !important;
            outline: none;
            color: #fff !important;
        }
        .btn-primary {
            background-color: var(--primary-color) !important;
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 10px;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 1.5rem;
            box-shadow: 0 4px 15px rgba(153, 0, 0, 0.3);
        }
        .btn-primary:hover {
            background-color: #b30000 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(153, 0, 0, 0.4);
        }
        .auth-footer {
            text-align: center;
            margin-top: 2rem;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.95rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .auth-footer a {
            color: var(--secondary-color);
            font-weight: 600;
            text-decoration: none;
        }
        .auth-footer a:hover {
            color: #fff;
            text-decoration: underline;
        }
        .password-requirements {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 0.5rem;
        }
        .app-logo {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            filter: drop-shadow(0 0 10px rgba(153, 0, 0, 0.5));
            text-align: center;
            display: block;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <i class="fas fa-calendar-check app-logo"></i>
        <div class="auth-box glass">
            <h1>ATTEND <span style="color: #fff;">EASE</span></h1>
            <p class="text-center text-white-50 mb-4">Create your student account</p>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <form action="auth.php" method="POST" class="auth-form">
                <input type="hidden" name="register" value="1">
                
                <div class="form-group">
                    <label for="username">Student ID</label>
                    <input type="text" id="username" name="username" required 
                           class="form-control" placeholder="Enter your student ID">
                </div>
                
                <div class="form-group">
                    <label for="faculty">Faculty</label>
                    <select id="faculty" name="faculty" class="form-select" required>
                        <option value="" selected disabled>Select your faculty</option>
                        <option value="Agriculture">Faculty of Agriculture</option>
                        <option value="Science">Faculty of Science</option>
                        <option value="Education">Faculty of Education</option>
                        <option value="Law">Faculty of Law</option>
                        <option value="Business_Administration">Faculty of Business Administration</option>
                        <option value="Journalism_and_Communication">Faculty of Journalism and Communication</option>
                        <option value="Health_Science">Faculty of Health Science</option>
                        <option value="Fashion_and_Design">Faculty of Fashion and Design</option>
                        <option value="Built_Environment">Faculty of Built Environment</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="study_program">Study Program</label>
                    <input type="text" id="study_program" name="study_program" required 
                           class="form-control" placeholder="Enter your study program (e.g., Computer Science)">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required 
                           class="form-control" placeholder="Enter password" 
                           pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                           title="Must contain at least one number, one uppercase and lowercase letter, and at least 8 or more characters">
                    <div class="password-requirements">
                        Must be at least 8 characters and include uppercase, lowercase, and numbers
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required 
                           class="form-control" placeholder="Enter email">
                </div>
                
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required 
                           class="form-control" placeholder="Enter first name">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required 
                           class="form-control" placeholder="Enter last name">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus mr-2"></i>
                    Create Account
                </button>
            </form>
            
            <div class="auth-footer">
                Already have an account? <a href="index.php">Login here</a>
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

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const passwordRequirements = document.querySelector('.password-requirements');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const hasMinLength = password.length >= 8;
                const hasNumber = /\d/.test(password);
                const hasUpper = /[A-Z]/.test(password);
                const hasLower = /[a-z]/.test(password);
                
                if (hasMinLength && hasNumber && hasUpper && hasLower) {
                    passwordRequirements.style.color = '#15803d'; // Green for valid
                } else {
                    passwordRequirements.style.color = '#666';
                }
            });
        }
    </script>
</body>
</html>
