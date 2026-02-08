<?php
require_once 'config.php';
require_once 'includes/db_connection.php';

// Database connection
global $conn;

echo "<h2>Admin Password Reset</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        echo "<p style='color: red;'>Please enter both passwords</p>";
    } elseif ($new_password !== $confirm_password) {
        echo "<p style='color: red;'>Passwords do not match</p>";
    } elseif (strlen($new_password) < 8) {
        echo "<p style='color: red;'>Password must be at least 8 characters</p>";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update admin password
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE role = 'admin'");
        $stmt->bind_param("s", $hashed_password);
        
        if ($stmt->execute()) {
            echo "<p style='color: green; font-weight: bold;'>✅ Admin password updated successfully!</p>";
            echo "<p>New password: <strong>" . htmlspecialchars($new_password) . "</strong></p>";
            echo "<p><a href='login.php'>Go to login page</a></p>";
        } else {
            echo "<p style='color: red;'>❌ Error updating password: " . $conn->error . "</p>";
        }
        
        $stmt->close();
        $conn->close();
        exit();
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Admin Password</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: 50px auto; padding: 20px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #990000; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #770000; }
        .form-group { margin: 20px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <form method="POST">
        <div class="form-group">
            <label for="new_password">New Password:</label>
            <input type="password" id="new_password" name="new_password" required minlength="8">
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
        </div>
        
        <button type="submit">Reset Admin Password</button>
    </form>
    
    <p style="margin-top: 30px; font-size: 12px; color: #666;">
        <strong>Security Notice:</strong> This script resets the admin account password. 
        Delete this file after use for security.
    </p>
</body>
</html>
