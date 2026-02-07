<?php
session_start();
error_log("=== RESET PASSWORD OTP PAGE LOADED ===");
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));
error_log("POST data: " . print_r($_POST, true));
error_log("GET data: " . print_r($_GET, true));

require_once '../conn.php';
require_once 'mailer.php';

$mailer = new Mailer();
$error = '';
$success = '';
$otp_required = false;
$reset_email = '';
$reset_step = 1; // 1 = request, 2 = verify OTP, 3 = set new password

// Check URL parameter for step
if (isset($_GET['step'])) {
    $step = (int)$_GET['step'];
    if ($step >= 1 && $step <= 3) {
        $reset_step = $step;
        if ($step == 2) {
            $otp_required = true;
            $reset_email = $_SESSION['reset_email'] ?? '';
        }
        error_log("Setting step from URL: $reset_step");
    }
}

// Check if we're in step 2 (OTP verification)
if (isset($_SESSION['reset_admin_id']) && isset($_SESSION['reset_step']) && $_SESSION['reset_step'] == 2) {
    $reset_step = 2;
    $otp_required = true;
    $reset_email = $_SESSION['reset_email'] ?? '';
    error_log("Setting step 2 from session");
}

// Check if we're in step 3 (set new password)
if (isset($_SESSION['reset_admin_id']) && isset($_SESSION['reset_step']) && $_SESSION['reset_step'] == 3) {
    $reset_step = 3;
    $reset_email = $_SESSION['reset_email'] ?? '';
    error_log("Setting step 3 from session");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("Form submitted with action: " . print_r($_POST, true));
    
    if (isset($_POST['request_reset'])) {
        // Step 1: Request password reset
        $email = $_POST['email'] ?? '';
        
        if (empty($email)) {
            $error = "Please enter your email address.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if admin exists and is active
            $stmt = $conn->prepare("SELECT id, full_name, email FROM admins WHERE email = ? AND is_active = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
            $stmt->close();
            
            error_log("Admin lookup result: " . print_r($admin, true));
            
            if ($admin) {
                // Generate OTP for reset (6 digits)
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                error_log("Generated OTP: $otp, Expires: $expires_at");
                
                // Store OTP in database
                $stmt = $conn->prepare("UPDATE admins SET reset_otp = ?, reset_otp_expires = ? WHERE id = ?");
                $stmt->bind_param("ssi", $otp, $expires_at, $admin['id']);
                
                if ($stmt->execute()) {
                    error_log("OTP stored in database for admin ID: " . $admin['id']);
                    
                    // Send OTP via email
                    $mailResult = $mailer->sendResetOTP($admin['email'], $otp, $admin['full_name']);
                    
                    error_log("Mail result: " . print_r($mailResult, true));
                    
                    if ($mailResult['success']) {
                        // Store in session for OTP verification
                        $_SESSION['reset_admin_id'] = $admin['id'];
                        $_SESSION['reset_email'] = $admin['email'];
                        $_SESSION['reset_name'] = $admin['full_name'];
                        $_SESSION['reset_step'] = 2; // Move to OTP verification step
                        
                        error_log("Session set for reset: " . print_r($_SESSION, true));
                        
                        if (isset($mailResult['demo_mode']) && $mailResult['demo_mode']) {
                            $success = "Reset OTP for " . $admin['email'] . ": <strong>$otp</strong><br><br>Enter this OTP in the next step.";
                            $otp_required = true;
                            $reset_step = 2;
                            $reset_email = $admin['email'];
                        } else {
                            $success = "OTP has been sent to your email address.";
                            // Redirect to OTP verification page
                            header('Location: reset-password-otp.php?step=2');
                            exit();
                        }
                    } else {
                        $error = "Failed to send OTP. Please try again later. " . ($mailResult['error'] ?? '');
                    }
                } else {
                    $error = "Database error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "No active admin account found with that email address.";
            }
        }
        
    } elseif (isset($_POST['verify_reset_otp'])) {
        // Step 2: Verify OTP
        $otp = $_POST['otp'] ?? '';
        $admin_id = $_SESSION['reset_admin_id'] ?? 0;
        
        error_log("Verifying OTP: $otp for admin ID: $admin_id");
        
        if ($admin_id && $otp) {
            // Verify OTP from database
            $stmt = $conn->prepare("SELECT reset_otp, reset_otp_expires FROM admins WHERE id = ?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
            $stmt->close();
            
            error_log("Database OTP check: " . print_r($admin, true));
            
            if ($admin && $admin['reset_otp'] == $otp) {
                if (strtotime($admin['reset_otp_expires']) > time()) {
                    // OTP is valid - move to password reset step
                    $_SESSION['reset_step'] = 3;
                    $_SESSION['reset_verified'] = true;
                    
                    // Clear OTP from database
                    $stmt = $conn->prepare("UPDATE admins SET reset_otp = NULL, reset_otp_expires = NULL WHERE id = ?");
                    $stmt->bind_param("i", $admin_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    error_log("OTP verified successfully, moving to step 3");
                    
                    // Redirect to password reset page
                    header('Location: reset-password-otp.php?step=3');
                    exit();
                } else {
                    $error = "OTP has expired. Please request a new one.";
                    $otp_required = true;
                    $reset_step = 2;
                    $reset_email = $_SESSION['reset_email'] ?? '';
                }
            } else {
                $error = "Invalid OTP. Please try again.";
                $otp_required = true;
                $reset_step = 2;
                $reset_email = $_SESSION['reset_email'] ?? '';
            }
        } else {
            $error = "Invalid request. Please start over.";
            $reset_step = 1;
        }
        
    } elseif (isset($_POST['resend_reset_otp'])) {
        // Resend OTP
        $admin_id = $_SESSION['reset_admin_id'] ?? 0;
        
        error_log("Resending OTP for admin ID: $admin_id");
        
        if ($admin_id) {
            // Generate new OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store OTP in database
            $stmt = $conn->prepare("UPDATE admins SET reset_otp = ?, reset_otp_expires = ? WHERE id = ?");
            $stmt->bind_param("ssi", $otp, $expires_at, $admin_id);
            $stmt->execute();
            $stmt->close();
            
            // Get admin info
            $stmt = $conn->prepare("SELECT email, full_name FROM admins WHERE id = ?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
            $stmt->close();
            
            // Send OTP
            $mailResult = $mailer->sendResetOTP($admin['email'], $otp, $admin['full_name']);
            
            if ($mailResult['success']) {
                if (isset($mailResult['demo_mode']) && $mailResult['demo_mode']) {
                    $success = "New OTP: <strong>$otp</strong>";
                } else {
                    $success = "New OTP has been sent to your email.";
                }
            } else {
                $error = "Failed to send OTP. Please try again.";
            }
            
            $otp_required = true;
            $reset_step = 2;
            $reset_email = $admin['email'] ?? '';
        }
        
    } elseif (isset($_POST['set_new_password'])) {
        // Step 3: Set new password
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $admin_id = $_SESSION['reset_admin_id'] ?? 0;
        
        error_log("Setting new password for admin ID: $admin_id");
        
        if (!$admin_id || !isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
            $error = "Session expired. Please start the reset process again.";
            $reset_step = 1;
            session_destroy();
            session_start(); // Restart session
        } elseif (empty($password) || empty($confirm_password)) {
            $error = "Please fill in all fields.";
            $reset_step = 3;
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
            $reset_step = 3;
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
            $reset_step = 3;
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
            $reset_step = 3;
        } else {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update password and clear reset data
            $stmt = $conn->prepare("UPDATE admins SET password = ?, reset_otp = NULL, reset_otp_expires = NULL, reset_token = NULL, reset_token_expires = NULL, login_attempts = 0, locked_until = NULL WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $admin_id);
            
            if ($stmt->execute()) {
                error_log("Password updated successfully for admin ID: $admin_id");
                
                // Clear session
                session_destroy();
                
                // Start new session for success message
                session_start();
                $_SESSION['reset_success'] = "Password has been reset successfully! You can now login with your new password.";
                
                // Redirect to login page
                header('Location: login.php?reset=success');
                exit();
            } else {
                $error = "Database error: " . $stmt->error;
                $reset_step = 3;
            }
            $stmt->close();
        }
    }
}

error_log("Final state - Step: $reset_step, Email: $reset_email, Error: $error, Success: $success");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Municipality of Paluan HRMO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f8fafc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-card {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .reset-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #d4af37, #1e3a8a, #d4af37);
            background-size: 200% 100%;
            animation: shimmer 3s infinite linear;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }
        
        .card-content {
            padding: 2.5rem;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, #1e3a8a, #1e293b);
            border-radius: 15px;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.2);
            border: 3px solid #d4af37;
        }
        
        .form-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }
        
        .form-title {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e3a8a, #1e293b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .step.active .step-circle {
            background: #1e3a8a;
            color: white;
            box-shadow: 0 4px 10px rgba(30, 58, 138, 0.3);
        }
        
        .step.completed .step-circle {
            background: #10b981;
            color: white;
        }
        
        .step-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .step.active .step-label {
            color: #1e3a8a;
            font-weight: 600;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            border-left: 4px solid #f87171;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border-left: 4px solid #34d399;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            border-left: 4px solid #60a5fa;
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: white;
            color: #1e293b;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            font-family: 'Inter', sans-serif;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            transform: translateY(-1px);
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus + .input-icon {
            color: #1e3a8a;
        }
        
        /* OTP Input Styling */
        .otp-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 1.5rem;
        }
        
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.8rem;
            font-weight: bold;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: white;
            color: #1e293b;
            transition: all 0.3s ease;
        }
        
        .otp-input:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }
        
        .otp-info {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .otp-info i {
            font-size: 2rem;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
        }
        
        .email-display {
            font-weight: 600;
            color: #1e3a8a;
            background: rgba(30, 58, 138, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: inline-block;
            margin: 0.5rem 0;
        }
        
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #1e3a8a, #1e293b);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.3);
        }
        
        .secondary-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #475569, #374151);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .secondary-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(100, 116, 139, 0.3);
        }
        
        .back-link {
            display: block;
            text-align: center;
            color: #1e3a8a;
            text-decoration: none;
            font-weight: 500;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .password-requirements {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #92400e;
        }
        
        .password-requirements ul {
            margin: 0.5rem 0 0 1.5rem;
        }
        
        @media (max-width: 640px) {
            .card-content {
                padding: 2rem;
            }
            
            .form-title {
                font-size: 1.6rem;
            }
            
            .form-logo {
                width: 70px;
                height: 70px;
            }
            
            .otp-input {
                width: 40px;
                height: 50px;
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="card-content">
            <!-- Header -->
            <div class="form-header">
                <div class="form-logo">
                    <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                         alt="Municipality of Paluan Logo">
                </div>
                <h2 class="form-title">Reset Password</h2>
                <p style="color: #64748b; font-size: 0.9rem;">OTP Verification System</p>
            </div>
            
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo $reset_step >= 1 ? 'active' : ''; ?>">
                    <div class="step-circle">1</div>
                    <div class="step-label">Request</div>
                </div>
                <div class="step <?php echo $reset_step >= 2 ? 'active' : ''; ?>">
                    <div class="step-circle">2</div>
                    <div class="step-label">Verify OTP</div>
                </div>
                <div class="step <?php echo $reset_step >= 3 ? 'active' : ''; ?>">
                    <div class="step-circle">3</div>
                    <div class="step-label">New Password</div>
                </div>
            </div>
            
            <!-- Error Message -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Success Message -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Step 1: Request Reset -->
            <?php if ($reset_step == 1): ?>
                <form method="POST" action="">
                    <input type="hidden" name="request_reset" value="1">
                    
                    <div class="form-group">
                        <div class="input-wrapper">
                            <input type="email"
                                   id="email"
                                   name="email"
                                   class="form-input"
                                   placeholder="Enter your email address"
                                   required
                                   autocomplete="email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> SEND OTP
                    </button>
                    
                    <a href="login.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </form>
            
            <!-- Step 2: Verify OTP -->
            <?php elseif ($reset_step == 2): ?>
                <div class="otp-info">
                    <i class="fas fa-shield-alt"></i>
                    <h3>OTP Verification</h3>
                    <p>Enter the 6-digit OTP sent to:</p>
                    <div class="email-display"><?php echo htmlspecialchars($reset_email); ?></div>
                    <p class="text-sm">Check your email inbox (and spam folder)</p>
                    <?php if (isset($_SESSION['reset_otp'])): ?>
                        <p class="text-sm" style="color: #dc2626; font-weight: bold;">
                            Demo OTP: <?php echo $_SESSION['reset_otp']; ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="verify_reset_otp" value="1">
                    
                    <div class="otp-container">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <input type="text"
                                   name="otp[]"
                                   class="otp-input"
                                   maxlength="1"
                                   data-index="<?php echo $i; ?>"
                                   oninput="moveToNext(this, <?php echo $i; ?>)"
                                   onkeydown="handleOTPKeyDown(event, <?php echo $i; ?>)"
                                   autocomplete="off">
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="otp" id="fullOtp">
                    
                    <button type="submit" class="submit-btn" id="verifyOtpBtn">
                        <i class="fas fa-check-circle"></i> VERIFY OTP
                    </button>
                    
                    <button type="submit" name="resend_reset_otp" value="1" class="secondary-btn">
                        <i class="fas fa-redo"></i> RESEND OTP
                    </button>
                    
                    <a href="reset-password-otp.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Use Different Email
                    </a>
                </form>
            
            <!-- Step 3: Set New Password -->
            <?php elseif ($reset_step == 3): ?>
                <form method="POST" action="">
                    <input type="hidden" name="set_new_password" value="1">
                    
                    <div class="password-requirements">
                        <strong>Password Requirements:</strong>
                        <ul>
                            <li>At least 8 characters long</li>
                            <li>Contains uppercase and lowercase letters</li>
                            <li>Contains at least one number</li>
                        </ul>
                    </div>
                    
                    <div class="form-group">
                        <div class="input-wrapper">
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-input"
                                   placeholder="Enter new password"
                                   required
                                   minlength="8"
                                   autocomplete="new-password">
                            <i class="fas fa-lock input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="input-wrapper">
                            <input type="password"
                                   id="confirm_password"
                                   name="confirm_password"
                                   class="form-input"
                                   placeholder="Confirm new password"
                                   required
                                   minlength="8"
                                   autocomplete="new-password">
                            <i class="fas fa-lock input-icon"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-key"></i> RESET PASSWORD
                    </button>
                    
                    <a href="login.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // OTP Input Handling
            const otpInputs = document.querySelectorAll('.otp-input');
            const fullOtpInput = document.getElementById('fullOtp');
            const verifyOtpBtn = document.getElementById('verifyOtpBtn');
            
            if (otpInputs.length > 0) {
                // Focus first OTP input
                otpInputs[0].focus();
                
                function moveToNext(input, index) {
                    if (input.value.length === 1) {
                        if (index < 6) {
                            const nextInput = document.querySelector(`.otp-input[data-index="${index + 1}"]`);
                            if (nextInput) nextInput.focus();
                        }
                    }
                    updateFullOTP();
                }
                
                function handleOTPKeyDown(event, index) {
                    if (event.key === 'Backspace' && !event.target.value && index > 1) {
                        event.preventDefault();
                        const prevInput = document.querySelector(`.otp-input[data-index="${index - 1}"]`);
                        if (prevInput) {
                            prevInput.value = '';
                            prevInput.focus();
                            updateFullOTP();
                        }
                    } else if (event.key === 'ArrowLeft' && index > 1) {
                        event.preventDefault();
                        const prevInput = document.querySelector(`.otp-input[data-index="${index - 1}"]`);
                        if (prevInput) prevInput.focus();
                    } else if (event.key === 'ArrowRight' && index < 6) {
                        event.preventDefault();
                        const nextInput = document.querySelector(`.otp-input[data-index="${index + 1}"]`);
                        if (nextInput) nextInput.focus();
                    }
                }
                
                function updateFullOTP() {
                    let fullOTP = '';
                    otpInputs.forEach(input => {
                        fullOTP += input.value;
                    });
                    if (fullOtpInput) {
                        fullOtpInput.value = fullOTP;
                    }
                    
                    // Enable/disable verify button based on OTP length
                    if (verifyOtpBtn) {
                        verifyOtpBtn.disabled = fullOTP.length !== 6;
                    }
                }
                
                // Add event listeners
                otpInputs.forEach(input => {
                    input.addEventListener('input', () => updateFullOTP());
                });
                
                updateFullOTP();
            }
            
            // Password confirmation validation
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            
            if (passwordInput && confirmInput) {
                function validatePasswords() {
                    if (passwordInput.value && confirmInput.value) {
                        if (passwordInput.value !== confirmInput.value) {
                            confirmInput.style.borderColor = '#dc2626';
                            confirmInput.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.1)';
                        } else {
                            confirmInput.style.borderColor = '#10b981';
                            confirmInput.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
                        }
                    }
                }
                
                passwordInput.addEventListener('input', validatePasswords);
                confirmInput.addEventListener('input', validatePasswords);
            }
            
            // Auto-hide messages after 5 seconds
            const messages = document.querySelectorAll('.alert');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.display = 'none';
                }, 5000);
            });
        });
    </script>
</body>
</html>