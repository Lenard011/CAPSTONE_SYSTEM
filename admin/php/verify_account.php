<?php
// verify_account.php
session_start();

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "hrms_paluan";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include mailer.php
$mailerPath = __DIR__ . '/mailer.php';
if (file_exists($mailerPath)) {
    require_once $mailerPath;
} else {
    $mailerPath = '../mailer.php';
    if (file_exists($mailerPath)) {
        require_once $mailerPath;
    }
}

// Function to generate temporary password
function generateTemporaryPassword($length = 10) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    $password = '';
    
    // Ensure at least one of each required character type
    $password .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0, 25)];
    $password .= 'abcdefghijklmnopqrstuvwxyz'[random_int(0, 25)];
    $password .= '0123456789'[random_int(0, 9)];
    $password .= '!@#$%^&*'[random_int(0, 7)];
    
    // Fill the rest randomly
    for ($i = 4; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    // Shuffle the password
    return str_shuffle($password);
}

// Function to validate password
function validatePassword($password)
{
    $errors = [];
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if (!preg_match('/[A-Z]/', $password)) $errors[] = "Password must contain at least one uppercase letter";
    if (!preg_match('/[a-z]/', $password)) $errors[] = "Password must contain at least one lowercase letter";
    if (!preg_match('/[0-9]/', $password)) $errors[] = "Password must contain at least one number";
    if (!preg_match('/[\W_]/', $password)) $errors[] = "Password must contain at least one special character";
    return $errors;
}

// Initialize variables
$success_message = "";
$error_message = "";
$token_valid = false;
$user_info = [];

// Check token
if (isset($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);
    
    // FIXED: Using correct column name verification_expires instead of token_expires_at
    $sql = "SELECT id, full_name, email, employee_id, employment_type, 
                   verification_expires, is_verified, verification_token,
                   username, password_is_temporary
            FROM users WHERE verification_token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_info = $result->fetch_assoc();
        
        // Check if token has expired
        if ($user_info['verification_expires'] && strtotime($user_info['verification_expires']) < time()) {
            $token_valid = false;
            $error_message = "This verification link has expired. Please request a new invitation from your administrator.";
        }
        // Check if already verified
        elseif ($user_info['is_verified'] == 1) {
            $token_valid = false;
            $error_message = "This account has already been verified. Please login instead.";
        }
        // Check if username already set (means account already setup)
        elseif (!empty($user_info['username'])) {
            $token_valid = false;
            $error_message = "This account has already been setup. Please login instead.";
        }
        else {
            $token_valid = true;
        }
    } else {
        $error_message = "Invalid verification link. Please request a new invitation from your administrator.";
    }
    $stmt->close();
}

// Handle Send Verification Invitation (from admin side)
if (isset($_POST['send_verification_invitation']) && isset($_SESSION['admin_id'])) {
    $invite_user_id = (int) $_POST['invite_user_id'];

    // Get user info
    $sql = "SELECT email, full_name, employee_id, employment_type FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $invite_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmt->close();

        // Generate unique verification token
        $verification_token = bin2hex(random_bytes(32));
        $token_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Generate temporary password
        $temp_password = generateTemporaryPassword();

        // Hash the temporary password
        $temp_password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

        // Update user with verification token and temporary password
        // FIXED: Using correct column name verification_expires
        $sql = "UPDATE users SET 
                verification_token = ?, 
                verification_expires = ?,
                password_hash = ?,
                password_is_temporary = 1,
                temporary_password_expiry = ?,
                must_change_password = 1,
                last_verification_sent = NOW(), 
                updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $temp_password_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $stmt->bind_param("ssssi", $verification_token, $token_expires_at, $temp_password_hash, $temp_password_expiry, $invite_user_id);

        if ($stmt->execute()) {
            $success_message = "Verification invitation sent successfully to " . htmlspecialchars($user['full_name']) . "!";
            
            // Create verification link
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
            $verification_link = $base_url . dirname($_SERVER['PHP_SELF']) . "/verify_account.php?token=" . $verification_token;
            
            // Clean up the URL
            $verification_link = preg_replace('/([^:])(\/{2,})/', '$1/', $verification_link);
            
            // Send verification email with temporary credentials
            $email_subject = "Verify Your HRMS Account - Municipality of Paluan";
            $email_message = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
                    .header { background: linear-gradient(135deg, #0235a2 0%, #1e3a8a 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f8fafc; padding: 30px; border: 1px solid #e5e7eb; }
                    .credentials-box { background: white; border: 2px solid #2c6bc4; padding: 20px; margin: 20px 0; border-radius: 8px; }
                    .login-button { background: #2c6bc4; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; }
                    .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px; }
                    .important-note { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 15px 0; }
                    .password-display { font-size: 18px; font-weight: bold; background: #f3f4f6; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>HRMS Account Verification</h1>
                    <p>Municipality of Paluan, Occidental Mindoro</p>
                </div>
                
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($user['full_name']) . ",</h2>
                    
                    <p>Welcome to the HR Management System of Paluan! Your account has been created and is ready for verification.</p>
                    
                    <div class='important-note'>
                        <strong>Important Security Notice:</strong> You have been provided with temporary credentials. You must change your password on first login.
                    </div>
                    
                    <div class='credentials-box'>
                        <h3>Your Temporary Login Credentials:</h3>
                        <p><strong>Login URL:</strong> <a href='" . htmlspecialchars($verification_link) . "'>Click here to login and setup your account</a></p>
                        <p><strong>Or go to:</strong> " . htmlspecialchars($verification_link) . "</p>
                        <p><strong>Username/Email:</strong> " . htmlspecialchars($user['email']) . "</p>
                        <p><strong>Temporary Password:</strong></p>
                        <div class='password-display'>" . htmlspecialchars($temp_password) . "</div>
                        
                        <p><strong>Account Details:</strong></p>
                        <ul>
                            <li>Employee ID: " . htmlspecialchars($user['employee_id'] ?? 'Not assigned') . "</li>
                            <li>Employment Type: " . htmlspecialchars(ucfirst(str_replace('_', ' ', $user['employment_type'] ?? 'Not specified'))) . "</li>
                        </ul>
                    </div>
                    
                    <p><strong>Steps to Complete Account Setup:</strong></p>
                    <ol>
                        <li>Click the login link above or copy the URL to your browser</li>
                        <li>Login with your email and the temporary password shown above</li>
                        <li>You will be prompted to create your permanent password</li>
                        <li>Choose a username for your account</li>
                        <li>Complete your profile information</li>
                    </ol>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . htmlspecialchars($verification_link) . "' class='login-button'>Setup Your Account Now</a>
                    </div>
                    
                    <p><strong>Important Notes:</strong></p>
                    <ul>
                        <li>This temporary password expires in 24 hours</li>
                        <li>You must change your password on first login</li>
                        <li>Do not share your login credentials with anyone</li>
                        <li>For security, choose a strong, unique password</li>
                        <li>This verification link is valid for 24 hours</li>
                    </ul>
                    
                    <p>If you didn't request this account or have any questions, please contact your HR administrator immediately.</p>
                </div>
                
                <div class='footer'>
                    <p>Best regards,<br>
                    <strong>HRMS Team</strong><br>
                    Municipality of Paluan, Occidental Mindoro</p>
                    <p><small>This is an automated message. Please do not reply to this email.</small></p>
                </div>
            </body>
            </html>
            ";
            
            // Send email using PHP's mail function
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: HRMS Paluan <hrmo@paluan.gov.ph>" . "\r\n";
            $headers .= "Reply-To: hrmo@paluan.gov.ph" . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            if (mail($user['email'], $email_subject, $email_message, $headers)) {
                $success_message .= " Temporary password has been sent to their email.";
            } else {
                $success_message .= " (Note: Email sending failed. Temporary password: " . $temp_password . ")";
            }
            
        } else {
            $error_message = "Error sending invitation: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error_message = "User not found!";
    }
}

// Handle user account verification (from user side after clicking email link)
if (isset($_POST['verify_user_account'])) {
    $verification_token = $conn->real_escape_string($_POST['verification_token']);
    $verify_username = trim($conn->real_escape_string($_POST['verify_username']));
    $verify_password = $_POST['verify_password'];
    $confirm_verify_password = $_POST['confirm_verify_password'];
    
    // Validation
    if (empty($verification_token)) {
        $error_message = "Invalid verification token!";
    } elseif (empty($verify_username)) {
        $error_message = "Username is required!";
    } elseif (empty($verify_password)) {
        $error_message = "Password is required!";
    } elseif ($verify_password !== $confirm_verify_password) {
        $error_message = "Passwords do not match!";
    } else {
        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $verify_username)) {
            $error_message = "Username can only contain letters, numbers, and underscores!";
        } else {
            // Validate password strength
            $password_errors = validatePassword($verify_password);
            if (!empty($password_errors)) {
                $error_message = "Password error: " . implode(', ', $password_errors);
            } else {
                // Check if username already exists
                $check_sql = "SELECT id FROM users WHERE username = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $verify_username);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error_message = "Username already exists! Please choose a different username.";
                } else {
                    // Get user by verification token
                    $sql = "SELECT id, email, full_name, password_is_temporary FROM users WHERE verification_token = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $verification_token);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $user = $result->fetch_assoc();
                        $stmt->close();
                        
                        // Hash password
                        $hashed_password = password_hash($verify_password, PASSWORD_DEFAULT);
                        
                        // Update user with username, password, and clear token
                        // FIXED: Using correct column name verification_expires
                        $sql = "UPDATE users SET username = ?, password_hash = ?, 
                                is_active = 1, is_verified = 1, verification_token = NULL, 
                                verification_expires = NULL, password_is_temporary = 0,
                                temporary_password_expiry = NULL, must_change_password = 0,
                                verified_at = NOW(), updated_at = NOW() WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssi", $verify_username, $hashed_password, $user['id']);
                        
                        if ($stmt->execute()) {
                            $success_message = "Account verified successfully! You can now login.";
                            
                            // Send confirmation email
                            $email_subject = "Account Verification Complete - HRMS Paluan";
                            $email_message = "
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <style>
                                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                    .header { background: linear-gradient(135deg, #0235a2 0%, #1e3a8a 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                                    .content { background: #f8fafc; padding: 30px; border: 1px solid #e5e7eb; }
                                    .success-box { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 15px 0; }
                                    .login-button { background: #2c6bc4; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block; }
                                </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <div class='header'>
                                        <h1>Account Verification Complete</h1>
                                    </div>
                                    
                                    <div class='content'>
                                        <h2>Hello " . htmlspecialchars($user['full_name']) . ",</h2>
                                        
                                        <div class='success-box'>
                                            <strong>Success!</strong> Your HRMS account has been successfully verified.
                                        </div>
                                        
                                        <p><strong>Your Account Details:</strong></p>
                                        <ul>
                                            <li><strong>Username:</strong> " . htmlspecialchars($verify_username) . "</li>
                                            <li><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</li>
                                            <li><strong>Status:</strong> Active and Verified</li>
                                        </ul>
                                        
                                        <p>You can now login to the HRMS system using your username and password.</p>
                                        
                                        <div style='text-align: center; margin: 30px 0;'>
                                            <a href='http://" . $_SERVER['HTTP_HOST'] . "/CAPSTONE_SYSTEM/admin/php/login.php' class='login-button'>Go to Login Page</a>
                                        </div>
                                        
                                        <p><strong>Security Reminder:</strong></p>
                                        <ul>
                                            <li>Keep your password secure and don't share it with anyone</li>
                                            <li>Log out after each session, especially on shared computers</li>
                                            <li>Contact HR if you suspect any unauthorized access</li>
                                        </ul>
                                    </div>
                                    
                                    <div style='margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px; text-align: center;'>
                                        <p>Best regards,<br>
                                        <strong>HRMS Team</strong><br>
                                        Municipality of Paluan, Occidental Mindoro</p>
                                    </div>
                                </div>
                            </body>
                            </html>
                            ";
                            
                            // Send confirmation email
                            $headers = "MIME-Version: 1.0" . "\r\n";
                            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                            $headers .= "From: HRMS Paluan <hrmo@paluan.gov.ph>" . "\r\n";
                            
                            mail($user['email'], $email_subject, $email_message, $headers);
                            
                            // Redirect to login page after 3 seconds
                            header("refresh:3;url=login.php?verified=1");
                            echo '<div style="text-align: center; padding: 20px;">Redirecting to login page in 3 seconds...</div>';
                            exit();
                        } else {
                            $error_message = "Error verifying account: " . $conn->error;
                        }
                        $stmt->close();
                    } else {
                        $error_message = "Invalid verification token!";
                    }
                }
                $check_stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account - HRMS Paluan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --secondary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            width: 100%;
        }

        .verification-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            padding: 2rem;
            color: white;
            text-align: center;
        }

        .card-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .card-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .card-body {
            padding: 2rem;
        }

        .user-info {
            background: var(--light);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-200);
        }

        .user-info h3 {
            color: var(--primary-dark);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.8rem;
            color: var(--gray-600);
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--dark);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-label span {
            color: var(--danger);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .input-group {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
            width: 100%;
            justify-content: center;
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .alert i {
            font-size: 1.25rem;
        }

        .form-text {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .password-toggle {
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            padding: 0.5rem;
        }

        .expired-message {
            text-align: center;
            padding: 3rem 2rem;
        }

        .expired-message i {
            font-size: 3rem;
            color: var(--danger);
            margin-bottom: 1rem;
        }

        .expired-message h2 {
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .expired-message p {
            color: var(--gray-600);
            margin-bottom: 1.5rem;
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 1.5rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="verification-card">
            <?php if ($token_valid): ?>
                <div class="card-header">
                    <h1><i class="fas fa-user-check"></i> Complete Your Account</h1>
                    <p>Set up your username and password to access the HRMS system</p>
                </div>
                
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="user-info">
                        <h3><i class="fas fa-user"></i> Account Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Full Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_info['full_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_info['email']); ?></span>
                            </div>
                            <?php if (!empty($user_info['employee_id'])): ?>
                            <div class="info-item">
                                <span class="info-label">Employee ID</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_info['employee_id']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($user_info['employment_type'])): 
                                $emp_type_display = ucfirst(str_replace('_', ' ', $user_info['employment_type']));
                            ?>
                            <div class="info-item">
                                <span class="info-label">Employment Type</span>
                                <span class="info-value"><?php echo htmlspecialchars($emp_type_display); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <form method="POST" id="verifyForm">
                        <input type="hidden" name="verification_token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                        
                        <div class="form-group">
                            <label class="form-label" for="verify_username">Choose Username <span>*</span></label>
                            <input type="text" class="form-control" id="verify_username" name="verify_username" 
                                   required placeholder="Enter your username" pattern="[a-zA-Z0-9_]+">
                            <div class="form-text">Username can only contain letters, numbers, and underscores</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="verify_password">Create Password <span>*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="verify_password" name="verify_password" 
                                       required minlength="8" placeholder="Enter your password">
                                <button type="button" class="password-toggle" onclick="togglePassword('verify_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Password must be at least 8 characters with uppercase, lowercase, number, and special character</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="confirm_verify_password">Confirm Password <span>*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_verify_password" name="confirm_verify_password" 
                                       required placeholder="Confirm your password">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_verify_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="verify_user_account" class="btn btn-primary">
                                <i class="fas fa-check-circle"></i>
                                Complete Account Setup
                            </button>
                        </div>
                        
                        <div class="form-text" style="text-align: center; margin-top: 1rem;">
                            <p>By completing this setup, you agree to the system's terms of use.</p>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="card-header">
                    <h1><i class="fas fa-exclamation-triangle"></i> Account Verification</h1>
                </div>
                
                <div class="card-body">
                    <div class="expired-message">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <i class="fas fa-exclamation-circle"></i>
                        <h2>Verification Link <?php echo isset($_GET['token']) ? 'Invalid or Expired' : 'Required'; ?></h2>
                        <p>
                            <?php 
                            if (isset($_GET['token'])) {
                                echo "This verification link is invalid, has expired, or the account has already been verified.";
                            } else {
                                echo "A verification token is required to access this page.";
                            }
                            ?>
                        </p>
                        <a href="login.php" class="btn btn-secondary">
                            <i class="fas fa-sign-in-alt"></i>
                            Go to Login
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, button) {
            const field = document.getElementById(fieldId);
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.getElementById('verifyForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('verify_password').value;
            const confirmPassword = document.getElementById('confirm_verify_password').value;
            const username = document.getElementById('verify_username').value;
            
            // Check if passwords match
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            // Check password strength
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumbers = /\d/.test(password);
            const hasSpecialChar = /[\W_]/.test(password);
            
            if (!hasUpperCase || !hasLowerCase || !hasNumbers || !hasSpecialChar) {
                e.preventDefault();
                alert('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character!');
                return false;
            }
            
            // Check username format
            const usernameRegex = /^[a-zA-Z0-9_]+$/;
            if (!usernameRegex.test(username)) {
                e.preventDefault();
                alert('Username can only contain letters, numbers, and underscores!');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>