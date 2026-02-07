<?php
session_start();
require_once '../conn.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$validToken = false;

// Validate token
if (!empty($token)) {
    $stmt = $conn->prepare("SELECT id, full_name, email, reset_token_expires FROM admins WHERE reset_token = ? AND is_active = 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();
    
    if ($admin && $admin['reset_token_expires'] && strtotime($admin['reset_token_expires']) > time()) {
        $validToken = true;
        $admin_id = $admin['id'];
    } else {
        $error = "Invalid or expired reset link. Please request a new password reset.";
    }
} else {
    $error = "Invalid reset link.";
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $stmt = $conn->prepare("UPDATE admins SET password = ?, reset_token = NULL, reset_token_expires = NULL, login_attempts = 0 WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $admin_id);
        
        if ($stmt->execute()) {
            $success = "Password has been reset successfully! You can now login with your new password.";
            $validToken = false; // Token is now invalid after use
            
            // Log this activity (optional - add to logs table if you have one)
            // $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
            // $log_stmt->bind_param("iss", $admin_id, 'Password Reset', $_SERVER['REMOTE_ADDR']);
            // $log_stmt->execute();
            // $log_stmt->close();
        } else {
            $error = "An error occurred while resetting your password. Please try again.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Municipality of Paluan HRMO</title>
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
            width: 70px;
            height: 70px;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, #1e3a8a, #1e293b);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.2);
            border: 2px solid #d4af37;
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
        
        .form-subtitle {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
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
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
            color: white;
            border-left: 4px solid #d4af37;
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
            border-radius: 10px;
            background: white;
            color: #1e293b;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
        }
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        
        .strength-bar {
            height: 5px;
            background: #e2e8f0;
            border-radius: 3px;
            margin-top: 0.25rem;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            background: #dc2626;
            transition: all 0.3s ease;
        }
        
        .strength-fill.good {
            background: #f59e0b;
        }
        
        .strength-fill.strong {
            background: #10b981;
        }
        
        .strength-text {
            color: #64748b;
        }
        
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #1e3a8a, #1e293b);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.3);
        }
        
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
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
            transition: all 0.2s ease;
        }
        
        .back-link:hover {
            color: #1e293b;
            text-decoration: underline;
        }
        
        .loader {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            flex-direction: column;
            border-radius: 20px;
            z-index: 10;
        }
        
        .loader.show {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f1f5f9;
            border-top: 4px solid #1e3a8a;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 640px) {
            .card-content {
                padding: 2rem;
            }
            
            .form-title {
                font-size: 1.6rem;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="reset-card">
        <div class="loader" id="loader">
            <div class="spinner"></div>
            <p style="margin-top: 1rem; color: #1e3a8a; font-weight: 500;">Resetting password...</p>
        </div>
        
        <div class="card-content">
            <!-- Header -->
            <div class="form-header">
                <div class="form-logo">
                    <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                         alt="Municipality of Paluan Logo">
                </div>
                <h2 class="form-title">Set New Password</h2>
                <?php if ($validToken): ?>
                    <p class="form-subtitle">
                        Please create a new strong password for your account.
                    </p>
                <?php endif; ?>
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
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($validToken): ?>
                <!-- Password Reset Form -->
                <form id="resetForm" method="POST" action="">
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
                        <div class="password-strength">
                            <div class="strength-text">Password strength: <span id="strengthText">Weak</span></div>
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
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
                        <div class="password-match" id="passwordMatch" style="margin-top: 0.5rem; font-size: 0.85rem; color: #64748b;"></div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>Password must be at least 8 characters with uppercase, lowercase, and numbers.</span>
                    </div>
                    
                    <button type="submit" class="submit-btn" id="submitBtn" disabled>
                        <div class="btn-content">
                            <i class="fas fa-key"></i>
                            <span>RESET PASSWORD</span>
                        </div>
                    </button>
                    
                    <a href="login.php" class="back-link">
                        <i class="fas fa-arrow-left"></i>
                        Back to Login
                    </a>
                </form>
            <?php elseif (empty($error) && empty($success)): ?>
                <!-- Invalid Token Message -->
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Invalid or expired reset link.</span>
                </div>
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Login
                </a>
            <?php elseif (!empty($success)): ?>
                <!-- Success Message with Login Link -->
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="login.php" class="submit-btn" style="text-decoration: none; display: inline-block;">
                        <div class="btn-content">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>LOGIN NOW</span>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetForm');
            const loader = document.getElementById('loader');
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            const passwordMatch = document.getElementById('passwordMatch');
            
            if (form) {
                // Password strength checker
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    
                    // Length check
                    if (password.length >= 8) strength++;
                    
                    // Contains numbers
                    if (/\d/.test(password)) strength++;
                    
                    // Contains lowercase
                    if (/[a-z]/.test(password)) strength++;
                    
                    // Contains uppercase
                    if (/[A-Z]/.test(password)) strength++;
                    
                    // Update strength indicator
                    let strengthPercent = (strength / 4) * 100;
                    let strengthClass = 'weak';
                    let text = 'Weak';
                    
                    if (strength >= 3) {
                        strengthClass = 'good';
                        text = 'Good';
                    }
                    if (strength === 4) {
                        strengthClass = 'strong';
                        text = 'Strong';
                    }
                    
                    strengthFill.style.width = strengthPercent + '%';
                    strengthFill.className = 'strength-fill ' + strengthClass;
                    strengthText.textContent = text;
                    
                    validateForm();
                });
                
                // Password confirmation check
                confirmInput.addEventListener('input', validateForm);
                passwordInput.addEventListener('input', validateForm);
                
                function validateForm() {
                    const password = passwordInput.value;
                    const confirmPassword = confirmInput.value;
                    let isValid = true;
                    
                    // Check password requirements
                    if (password.length < 8) {
                        isValid = false;
                        passwordMatch.textContent = 'Password must be at least 8 characters';
                        passwordMatch.style.color = '#dc2626';
                    } else if (!/\d/.test(password) || !/[a-z]/.test(password) || !/[A-Z]/.test(password)) {
                        isValid = false;
                        passwordMatch.textContent = 'Password needs uppercase, lowercase, and numbers';
                        passwordMatch.style.color = '#dc2626';
                    } else if (confirmPassword && password !== confirmPassword) {
                        isValid = false;
                        passwordMatch.textContent = 'Passwords do not match';
                        passwordMatch.style.color = '#dc2626';
                    } else if (confirmPassword && password === confirmPassword) {
                        passwordMatch.textContent = 'Passwords match';
                        passwordMatch.style.color = '#10b981';
                    } else {
                        passwordMatch.textContent = '';
                    }
                    
                    submitBtn.disabled = !isValid;
                    return isValid;
                }
                
                form.addEventListener('submit', function(e) {
                    if (!validateForm()) {
                        e.preventDefault();
                        return;
                    }
                    
                    // Show loader
                    loader.classList.add('show');
                });
            }
            
            // Auto-hide messages
            const messages = document.querySelectorAll('.alert');
            messages.forEach(message => {
                setTimeout(() => {
                    message.remove();
                }, 5000);
            });
        });
    </script>
</body>
</html><?php
session_start();
require_once '../conn.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$validToken = false;

// Validate token
if (!empty($token)) {
    $stmt = $conn->prepare("SELECT id, full_name, email, reset_token_expires FROM admins WHERE reset_token = ? AND is_active = 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();
    
    if ($admin && $admin['reset_token_expires'] && strtotime($admin['reset_token_expires']) > time()) {
        $validToken = true;
        $admin_id = $admin['id'];
    } else {
        $error = "Invalid or expired reset link. Please request a new password reset.";
    }
} else {
    $error = "Invalid reset link.";
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $stmt = $conn->prepare("UPDATE admins SET password = ?, reset_token = NULL, reset_token_expires = NULL, login_attempts = 0 WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $admin_id);
        
        if ($stmt->execute()) {
            $success = "Password has been reset successfully! You can now login with your new password.";
            $validToken = false; // Token is now invalid after use
            
            // Log this activity (optional - add to logs table if you have one)
            // $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
            // $log_stmt->bind_param("iss", $admin_id, 'Password Reset', $_SERVER['REMOTE_ADDR']);
            // $log_stmt->execute();
            // $log_stmt->close();
        } else {
            $error = "An error occurred while resetting your password. Please try again.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Municipality of Paluan HRMO</title>
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
            width: 70px;
            height: 70px;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, #1e3a8a, #1e293b);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.2);
            border: 2px solid #d4af37;
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
        
        .form-subtitle {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
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
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
            color: white;
            border-left: 4px solid #d4af37;
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
            border-radius: 10px;
            background: white;
            color: #1e293b;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
        }
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        
        .strength-bar {
            height: 5px;
            background: #e2e8f0;
            border-radius: 3px;
            margin-top: 0.25rem;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            background: #dc2626;
            transition: all 0.3s ease;
        }
        
        .strength-fill.good {
            background: #f59e0b;
        }
        
        .strength-fill.strong {
            background: #10b981;
        }
        
        .strength-text {
            color: #64748b;
        }
        
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #1e3a8a, #1e293b);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.3);
        }
        
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
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
            transition: all 0.2s ease;
        }
        
        .back-link:hover {
            color: #1e293b;
            text-decoration: underline;
        }
        
        .loader {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            flex-direction: column;
            border-radius: 20px;
            z-index: 10;
        }
        
        .loader.show {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f1f5f9;
            border-top: 4px solid #1e3a8a;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 640px) {
            .card-content {
                padding: 2rem;
            }
            
            .form-title {
                font-size: 1.6rem;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="reset-card">
        <div class="loader" id="loader">
            <div class="spinner"></div>
            <p style="margin-top: 1rem; color: #1e3a8a; font-weight: 500;">Resetting password...</p>
        </div>
        
        <div class="card-content">
            <!-- Header -->
            <div class="form-header">
                <div class="form-logo">
                    <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                         alt="Municipality of Paluan Logo">
                </div>
                <h2 class="form-title">Set New Password</h2>
                <?php if ($validToken): ?>
                    <p class="form-subtitle">
                        Please create a new strong password for your account.
                    </p>
                <?php endif; ?>
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
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($validToken): ?>
                <!-- Password Reset Form -->
                <form id="resetForm" method="POST" action="">
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
                        <div class="password-strength">
                            <div class="strength-text">Password strength: <span id="strengthText">Weak</span></div>
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
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
                        <div class="password-match" id="passwordMatch" style="margin-top: 0.5rem; font-size: 0.85rem; color: #64748b;"></div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>Password must be at least 8 characters with uppercase, lowercase, and numbers.</span>
                    </div>
                    
                    <button type="submit" class="submit-btn" id="submitBtn" disabled>
                        <div class="btn-content">
                            <i class="fas fa-key"></i>
                            <span>RESET PASSWORD</span>
                        </div>
                    </button>
                    
                    <a href="login.php" class="back-link">
                        <i class="fas fa-arrow-left"></i>
                        Back to Login
                    </a>
                </form>
            <?php elseif (empty($error) && empty($success)): ?>
                <!-- Invalid Token Message -->
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Invalid or expired reset link.</span>
                </div>
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Login
                </a>
            <?php elseif (!empty($success)): ?>
                <!-- Success Message with Login Link -->
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="login.php" class="submit-btn" style="text-decoration: none; display: inline-block;">
                        <div class="btn-content">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>LOGIN NOW</span>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetForm');
            const loader = document.getElementById('loader');
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            const passwordMatch = document.getElementById('passwordMatch');
            
            if (form) {
                // Password strength checker
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    
                    // Length check
                    if (password.length >= 8) strength++;
                    
                    // Contains numbers
                    if (/\d/.test(password)) strength++;
                    
                    // Contains lowercase
                    if (/[a-z]/.test(password)) strength++;
                    
                    // Contains uppercase
                    if (/[A-Z]/.test(password)) strength++;
                    
                    // Update strength indicator
                    let strengthPercent = (strength / 4) * 100;
                    let strengthClass = 'weak';
                    let text = 'Weak';
                    
                    if (strength >= 3) {
                        strengthClass = 'good';
                        text = 'Good';
                    }
                    if (strength === 4) {
                        strengthClass = 'strong';
                        text = 'Strong';
                    }
                    
                    strengthFill.style.width = strengthPercent + '%';
                    strengthFill.className = 'strength-fill ' + strengthClass;
                    strengthText.textContent = text;
                    
                    validateForm();
                });
                
                // Password confirmation check
                confirmInput.addEventListener('input', validateForm);
                passwordInput.addEventListener('input', validateForm);
                
                function validateForm() {
                    const password = passwordInput.value;
                    const confirmPassword = confirmInput.value;
                    let isValid = true;
                    
                    // Check password requirements
                    if (password.length < 8) {
                        isValid = false;
                        passwordMatch.textContent = 'Password must be at least 8 characters';
                        passwordMatch.style.color = '#dc2626';
                    } else if (!/\d/.test(password) || !/[a-z]/.test(password) || !/[A-Z]/.test(password)) {
                        isValid = false;
                        passwordMatch.textContent = 'Password needs uppercase, lowercase, and numbers';
                        passwordMatch.style.color = '#dc2626';
                    } else if (confirmPassword && password !== confirmPassword) {
                        isValid = false;
                        passwordMatch.textContent = 'Passwords do not match';
                        passwordMatch.style.color = '#dc2626';
                    } else if (confirmPassword && password === confirmPassword) {
                        passwordMatch.textContent = 'Passwords match';
                        passwordMatch.style.color = '#10b981';
                    } else {
                        passwordMatch.textContent = '';
                    }
                    
                    submitBtn.disabled = !isValid;
                    return isValid;
                }
                
                form.addEventListener('submit', function(e) {
                    if (!validateForm()) {
                        e.preventDefault();
                        return;
                    }
                    
                    // Show loader
                    loader.classList.add('show');
                });
            }
            
            // Auto-hide messages
            const messages = document.querySelectorAll('.alert');
            messages.forEach(message => {
                setTimeout(() => {
                    message.remove();
                }, 5000);
            });
        });
    </script>
</body>
</html>