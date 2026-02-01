<?php
/**
 * Send an email using PHP's mail function
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message HTML message content
 * @return bool True if the email was sent successfully, false otherwise
 */
function sendEmail($to, $subject, $message) {
    $siteName = SITE_NAME;
    $siteEmail = 'noreply@' . $_SERVER['HTTP_HOST'];
    
    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $siteName <$siteEmail>" . "\r\n";
    $headers .= "Reply-To: $siteEmail" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Wrap message in a nice HTML template
    $htmlMessage = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
        <title>$subject</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #dc3545; padding: 20px; text-align: center; color: white; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .footer { margin-top: 20px; padding: 10px; text-align: center; font-size: 12px; color: #777; }
            .button { 
                display: inline-block; 
                padding: 10px 20px; 
                background-color: #000000; 
                color: white !important; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 15px 0;
            }
            .button:hover { 
                background-color: #dc3545 !important;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>$siteName</h2>
            </div>
            <div class='content'>
                $message
            </div>
            <div class='footer'>
                <p>This is an automated message from $siteName. Please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " $siteName. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send the email
    return mail($to, $subject, $htmlMessage, $headers);
}

/**
 * Send a password reset email
 * 
 * @param string $email User's email address
 * @param string $token Password reset token
 * @param string $firstName User's first name
 * @return bool True if the email was sent successfully, false otherwise
 */
function sendPasswordResetEmail($email, $token, $firstName) {
    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
    $subject = "Password Reset Request";
    
    $message = "
        <h3>Hello " . htmlspecialchars($firstName) . ",</h3>
        <p>We received a request to reset your password. Click the button below to set a new password:</p>
        <p style='text-align: center;'><a href='$resetLink' class='button'>Reset Password</a></p>
        <p>Or copy and paste this link into your browser:</p>
        <p><a href='$resetLink' style='word-break: break-all;'>$resetLink</a></p>
        <p>This link will expire in 1 hour.</p>
        <p>If you didn't request this, please ignore this email and your password will remain unchanged.</p>
        <p>Thanks,<br>$siteName Team</p>
    ";
    
    return sendEmail($email, $subject, $message);
}
?>
