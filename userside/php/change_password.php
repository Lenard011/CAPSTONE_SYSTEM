<?php
// change_password.php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user needs to change password (temporary password login)
if (!isset($_SESSION['must_change_password']) || !$_SESSION['must_change_password']) {
    header('Location: dashboard.php'); // Redirect to dashboard if not required
    exit();
}

$error_message = '';
$success_message = '';

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'All fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'New passwords do not match';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Password must be at least 8 characters long';
    } else {
        // Connect to database
        $conn = new mysqli('localhost', 'root', '', 'hrms_paluan');
        
        if ($conn->connect_error) {
            $error_message = 'Database connection failed';
        } else {
            // Get current user info
            $stmt = $conn->prepare("SELECT password_hash, password_is_temporary FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if ($user) {
                // Verify current password
                if (password_verify($current_password, $user['password_hash'])) {
                    // Check if this is a temporary password
                    if ($user['password_is_temporary'] == 1) {
                        // Validate new password strength
                        if (!preg_match('/[A-Z]/', $new_password)) {
                            $error_message = 'Password must contain at least one uppercase letter';
                        } elseif (!preg_match('/[a-z]/', $new_password)) {
                            $error_message = 'Password must contain at least one lowercase letter';
                        } elseif (!preg_match('/[0-9]/', $new_password)) {
                            $error_message = 'Password must contain at least one number';
                        } elseif (!preg_match('/[\W_]/', $new_password)) {
                            $error_message = 'Password must contain at least one special character';
                        } else {
                            // Hash new password
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            
                            // Update password and clear temporary flags
                            $updateStmt = $conn->prepare("
                                UPDATE users 
                                SET password_hash = ?, 
                                    password_is_temporary = 0,
                                    must_change_password = 0,
                                    temporary_password_expiry = NULL,
                                    last_password_change = NOW()
                                WHERE id = ?
                            ");
                            
                            if ($updateStmt) {
                                $updateStmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
                                
                                if ($updateStmt->execute()) {
                                    // Clear temporary password flags from session
                                    unset($_SESSION['must_change_password']);
                                    unset($_SESSION['temp_password_login']);
                                    
                                    $success_message = 'Password changed successfully! Redirecting to dashboard...';
                                    
                                    // Redirect after 3 seconds
                                    header('refresh:3;url=dashboard.php');
                                } else {
                                    $error_message = 'Error updating password: ' . $conn->error;
                                }
                                $updateStmt->close();
                            } else {
                                $error_message = 'Database error: ' . $conn->error;
                            }
                        }
                    } else {
                        $error_message = 'This is not a temporary password. Please use the regular password change feature.';
                    }
                } else {
                    $error_message = 'Current password is incorrect';
                }
            } else {
                $error_message = 'User not found';
            }
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - HRMS Paluan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-blue: #2c6bc4;
            --primary-dark: #1e4a8a;
            --error: #ef4444;
            --success: #10b981;
            --light-bg: #f8fafc;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 500px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            border-radius: 16px;
            padding: 15px;
            box-shadow: 0 10px 25px rgba(44, 107, 196, 0.3);
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }
        
        h1 {
            color: var(--primary-dark);
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #64748b;
            font-size: 16px;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--error);
        }
        
        .alert i {
            font-size: 20px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        input {
            width: 100%;
            padding: 14px 16px 14px 45px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(44, 107, 196, 0.1);
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 18px;
        }
        
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            font-size: 18px;
        }
        
        .requirements {
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
            color: #4b5563;
        }
        
        .requirements h4 {
            margin-bottom: 10px;
            color: var(--primary-dark);
        }
        
        .requirements ul {
            padding-left: 20px;
        }
        
        .requirements li {
            margin-bottom: 5px;
        }
        
        .requirements li.met {
            color: var(--success);
        }
        
        .requirements li.met::before {
            content: '✓ ';
        }
        
        .requirements li.unmet {
            color: #6b7280;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(44, 107, 196, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        @media (max-width: 480px) {
            .card {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo">
            </div>
            <h1>Create New Password</h1>
            <p class="subtitle">Your temporary password requires a change. Please create a permanent password.</p>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <form id="passwordForm" method="POST">
                <div class="form-group">
                    <label for="current_password">Current Temporary Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-key input-icon"></i>
                        <input type="password" id="current_password" name="current_password" required>
                        <button type="button" class="toggle-password" data-target="current_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="new_password" name="new_password" required>
                        <button type="button" class="toggle-password" data-target="new_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="toggle-password" data-target="confirm_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="requirements">
                    <h4>Password Requirements:</h4>
                    <ul>
                        <li id="req-length" class="unmet">At least 8 characters</li>
                        <li id="req-uppercase" class="unmet">At least one uppercase letter</li>
                        <li id="req-lowercase" class="unmet">At least one lowercase letter</li>
                        <li id="req-number" class="unmet">At least one number</li>
                        <li id="req-special" class="unmet">At least one special character</li>
                        <li id="req-match" class="unmet">Passwords must match</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn" id="submitBtn">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle functionality
            document.querySelectorAll('.toggle-password').forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.className = 'fas fa-eye-slash';
                    } else {
                        input.type = 'password';
                        icon.className = 'fas fa-eye';
                    }
                });
            });
            
            // Password validation
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            
            function validatePassword() {
                const password = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                // Check length
                const hasLength = password.length >= 8;
                document.getElementById('req-length').className = hasLength ? 'met' : 'unmet';
                
                // Check uppercase
                const hasUppercase = /[A-Z]/.test(password);
                document.getElementById('req-uppercase').className = hasUppercase ? 'met' : 'unmet';
                
                // Check lowercase
                const hasLowercase = /[a-z]/.test(password);
                document.getElementById('req-lowercase').className = hasLowercase ? 'met' : 'unmet';
                
                // Check number
                const hasNumber = /[0-9]/.test(password);
                document.getElementById('req-number').className = hasNumber ? 'met' : 'unmet';
                
                // Check special character
                const hasSpecial = /[\W_]/.test(password);
                document.getElementById('req-special').className = hasSpecial ? 'met' : 'unmet';
                
                // Check match
                const passwordsMatch = password === confirmPassword && password !== '';
                document.getElementById('req-match').className = passwordsMatch ? 'met' : 'unmet';
                
                // Enable/disable submit button
                const isValid = hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial && passwordsMatch;
                submitBtn.disabled = !isValid;
                
                return isValid;
            }
            
            // Add event listeners for real-time validation
            newPasswordInput.addEventListener('input', validatePassword);
            confirmPasswordInput.addEventListener('input', validatePassword);
            
            // Initial validation
            validatePassword();
            
            // Form submission
            document.getElementById('passwordForm').addEventListener('submit', function(e) {
                if (!validatePassword()) {
                    e.preventDefault();
                    alert('Please fix all password requirements before submitting.');
                }
            });
        });
    </script>
</body>
</html>