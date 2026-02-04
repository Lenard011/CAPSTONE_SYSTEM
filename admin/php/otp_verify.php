<?php
session_start();

// Include required files
require_once '../conn.php'; // Your existing connection file
require_once 'mailer.php';

// OTP Configuration
define('OTP_EXPIRY_MINUTES', 5);

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Variables
$error = '';
$success = '';
$otp_required = false;
$user_email = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['verify_otp'])) {
        // Handle OTP verification
        $otp = $_POST['otp'] ?? '';
        $user_id = $_SESSION['pending_user_id'] ?? 0;

        if ($user_id && $otp) {
            // Verify OTP
            $stmt = $conn->prepare("SELECT otp_code, otp_expires_at FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['otp_code'] == $otp && strtotime($user['otp_expires_at']) > time()) {
                // OTP is valid
                // Clear OTP
                $stmt = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL, last_login = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);

                // Set session
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_email'] = $_SESSION['pending_email'];
                $_SESSION['user_name'] = $_SESSION['pending_name'];
                $_SESSION['is_admin'] = $_SESSION['pending_is_admin'];

                // Clear pending data
                unset($_SESSION['pending_user_id']);
                unset($_SESSION['pending_email']);
                unset($_SESSION['pending_name']);
                unset($_SESSION['pending_is_admin']);

                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            } else {
                $error = "Invalid or expired OTP. Please try again.";
                $otp_required = true;
                $user_email = $_SESSION['pending_email'] ?? '';
            }
        }
    } elseif (isset($_POST['resend_otp'])) {
        // Handle OTP resend
        $user_id = $_SESSION['pending_user_id'] ?? 0;

        if ($user_id) {
            // Generate new OTP
            $otp = generateOTP();
            $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            // Store OTP
            $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
            $stmt->execute([$otp, $expires_at, $user_id]);

            // Get user info for email
            $stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Send OTP
            $result = $mailer->sendOTP($user['email'], $otp, $user['full_name']);

            if ($result['success']) {
                if (isset($result['demo_otp'])) {
                    $success = "New OTP: <strong>$otp</strong> (Demo - in real app, this would be emailed)";
                } else {
                    $success = "New OTP has been sent to your email.";
                }
            } else {
                $error = "Failed to send OTP. Please try again.";
            }

            $otp_required = true;
            $user_email = $user['email'] ?? '';
        }
    } else {
        // Handle initial login
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;

        // Validate inputs
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            // Check if user exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Check if account is locked
                    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $error = "Account is locked. Please try again later.";
                    } else {
                        // Generate OTP
                        $otp = generateOTP();
                        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                        // Store OTP in database
                        $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
                        $stmt->execute([$otp, $expires_at, $user['id']]);

                        // Send OTP via email
                        $result = $mailer->sendOTP($email, $otp, $user['full_name']);

                        if ($result['success']) {
                            if (isset($result['demo_otp'])) {
                                $success = "OTP sent to $email. OTP: <strong>$otp</strong> (Demo - in real app, this would be emailed)";
                            } else {
                                $success = "OTP has been sent to your email address.";
                            }

                            // Store user data in session for OTP verification
                            $_SESSION['pending_user_id'] = $user['id'];
                            $_SESSION['pending_email'] = $user['email'];
                            $_SESSION['pending_name'] = $user['full_name'];
                            $_SESSION['pending_is_admin'] = $user['is_admin'];

                            // Reset login attempts
                            $stmt = $conn->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?");
                            $stmt->execute([$user['id']]);

                            $otp_required = true;
                            $user_email = $email;
                        } else {
                            $error = "Failed to send OTP. Please try again.";
                        }
                    }
                } else {
                    // Wrong password - increment login attempts
                    $new_attempts = $user['login_attempts'] + 1;

                    if ($new_attempts >= 5) {
                        // Lock account for 15 minutes
                        $lock_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        $stmt = $conn->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?");
                        $stmt->execute([$new_attempts, $lock_until, $user['id']]);
                        $error = "Account locked due to too many failed attempts. Try again in 15 minutes.";
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
                        $stmt->execute([$new_attempts, $user['id']]);
                        $error = "Invalid password. Attempt " . $new_attempts . " of 5.";
                    }
                }
            } else {
                $error = "No account found with that email.";
            }
        }
    }
}

// Function to generate OTP
function generateOTP()
{
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Municipality of Paluan HRMO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* Modern Color Palette - Updated to match image */
        :root {
            --primary-blue: #1e40af;
            --primary-dark: #1e3a8a;
            --primary-light: #3b82f6;
            --accent-blue: #60a5fa;
            --navy-blue: #1e3a8a;
            --royal-blue: #2563eb;
            --light-bg: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-light: #64748b;
            --border-light: #e2e8f0;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --shadow-blue: 0 10px 25px -3px rgba(30, 64, 175, 0.2);
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f8fafc 100%);
            min-height: 100vh;
            color: var(--text-primary);
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-shape {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(30, 64, 175, 0.08) 0%, rgba(37, 99, 235, 0.04) 100%);
            filter: blur(40px);
        }

        .bg-shape:nth-child(1) {
            width: 500px;
            height: 500px;
            top: -200px;
            left: -200px;
            animation: float 20s infinite linear;
        }

        .bg-shape:nth-child(2) {
            width: 400px;
            height: 400px;
            bottom: -150px;
            right: -150px;
            animation: float 25s infinite linear reverse;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) rotate(0deg);
            }

            33% {
                transform: translate(30px, 50px) rotate(120deg);
            }

            66% {
                transform: translate(-20px, 80px) rotate(240deg);
            }

            100% {
                transform: translate(0, 0) rotate(360deg);
            }
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--navy-blue) 0%, #1e40af 100%);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #ffffff, #3b82f6);
            background-size: 200% 100%;
            animation: shimmer 3s infinite linear;
        }

        @keyframes shimmer {
            0% {
                background-position: -200% center;
            }

            100% {
                background-position: 200% center;
            }
        }

        .logo-wrapper {
            position: relative;
            transition: all 0.3s ease;
        }

        .logo-wrapper:hover {
            transform: translateY(-2px);
        }

        .logo-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 110%;
            height: 110%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.2) 0%, transparent 70%);
            border-radius: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .logo-wrapper:hover .logo-glow {
            opacity: 1;
        }

        .header-title {
            position: relative;
        }

        .header-title h1 {
            background: linear-gradient(135deg, #ffffff 0%, rgba(255, 255, 255, 0.9) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Main Content */
        .main-content {
            min-height: calc(100vh - 140px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        /* Login Card */
        .login-card {
            width: 100%;
            max-width: 480px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: var(--shadow-xl), var(--shadow-blue);
            border: 1px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
            position: relative;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .login-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 15px 30px -5px rgba(30, 64, 175, 0.25);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--royal-blue), #3b82f6, var(--royal-blue));
            background-size: 200% 100%;
            animation: shimmer 3s infinite linear;
        }

        /* Card Content */
        .card-content {
            padding: 3rem;
        }

        @media (max-width: 640px) {
            .card-content {
                padding: 2rem;
            }
        }

        /* Form Header */
        .form-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .form-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--royal-blue), var(--primary-blue));
            border-radius: 16px;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.2);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .form-logo:hover {
            transform: scale(1.05) rotate(5deg);
            box-shadow: 0 12px 25px rgba(30, 64, 175, 0.3);
        }

        .form-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .form-logo::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            transition: transform 0.5s ease;
        }

        .form-logo:hover::after {
            transform: rotate(45deg) translate(50%, 50%);
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--royal-blue), var(--primary-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .form-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: 500;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3.5rem;
            font-size: 1rem;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            background: white;
            color: var(--text-primary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--royal-blue);
            box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.1);
            transform: translateY(-1px);
        }

        .form-input.error {
            border-color: var(--error);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1.2rem;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .form-input:focus+.input-icon {
            color: var(--royal-blue);
            transform: translateY(-50%) scale(1.1);
        }

        /* OTP Input Styling - Matching Design */
        .otp-container {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 2rem;
        }

        .otp-input {
            width: 55px;
            height: 65px;
            text-align: center;
            font-size: 1.8rem;
            font-weight: bold;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            background: white;
            color: var(--text-primary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
        }

        .otp-input:focus {
            outline: none;
            border-color: var(--royal-blue);
            box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.1);
            transform: translateY(-2px);
        }

        .otp-input.filled {
            border-color: var(--success);
            background-color: rgba(16, 185, 129, 0.05);
        }

        /* OTP Timer */
        .otp-timer {
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .timer-text {
            font-weight: 500;
            color: var(--warning);
            background: rgba(245, 158, 11, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: inline-block;
        }

        .timer-expired {
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
        }

        /* Form Options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 0.95rem;
            transition: color 0.2s ease;
        }

        .remember-label:hover {
            color: var(--royal-blue);
        }

        .remember-checkbox {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border-light);
            border-radius: 4px;
            background: white;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }

        .remember-checkbox.checked {
            background: var(--royal-blue);
            border-color: var(--royal-blue);
        }

        .remember-checkbox.checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        .forgot-link {
            color: var(--royal-blue);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
            padding-bottom: 2px;
        }

        .forgot-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 1px;
            background: var(--royal-blue);
            transition: width 0.3s ease;
        }

        .forgot-link:hover::after {
            width: 100%;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--royal-blue), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.3);
            margin-bottom: 1rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(30, 64, 175, 0.4);
            background: linear-gradient(135deg, var(--primary-dark), #1e3a8a);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .submit-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        /* Secondary Button for OTP Resend */
        .secondary-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--text-light), var(--text-secondary));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.2);
        }

        .secondary-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(100, 116, 139, 0.3);
            background: linear-gradient(135deg, var(--text-secondary), #475569);
        }

        /* Error Message */
        .error-message {
            display: none;
            background: linear-gradient(135deg, var(--error), #f87171);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }

        .error-message.show {
            display: flex;
        }

        /* Success Message */
        .success-message {
            display: none;
            background: linear-gradient(135deg, var(--success), #34d399);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .success-message.show {
            display: flex;
        }

        /* Info Message */
        .info-message {
            display: none;
            background: linear-gradient(135deg, var(--royal-blue), var(--primary-blue));
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.3);
        }

        .info-message.show {
            display: flex;
        }

        /* OTP Info Box */
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
            color: var(--royal-blue);
            margin-bottom: 0.5rem;
        }

        .otp-info h3 {
            color: var(--navy-blue);
            margin-bottom: 0.5rem;
        }

        .otp-info p {
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .email-display {
            font-weight: 600;
            color: var(--royal-blue);
            background: rgba(30, 64, 175, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            display: inline-block;
            margin: 0.25rem 0;
        }

        /* Loader Overlay */
        .loader-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            z-index: 10;
            border-radius: 24px;
            animation: fadeIn 0.3s ease;
        }

        .loader-overlay.show {
            display: flex;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #f1f5f9;
            border-top: 4px solid var(--royal-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loader-text {
            margin-top: 1.5rem;
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--royal-blue);
            animation: pulse 2s infinite;
        }

        /* Animations */
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Form Toggle */
        .form-toggle {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-light);
        }

        .toggle-link {
            color: var(--royal-blue);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .toggle-link:hover {
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
                padding: 1rem 0;
            }

            .logo-wrapper {
                margin: 0 auto;
            }
        }

        @media (max-width: 480px) {
            .card-content {
                padding: 1.5rem;
            }

            .form-title {
                font-size: 1.75rem;
            }

            .form-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .otp-input {
                width: 45px;
                height: 55px;
                font-size: 1.5rem;
            }

            .otp-container {
                gap: 8px;
            }
        }
    </style>
</head>

<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
    </div>

    <div class="page-container">
        <!-- Header -->
        <header class="header">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between header-container">
                <div class="flex items-center space-x-4">
                    <div class="logo-wrapper">
                        <div class="logo-glow"></div>
                        <a href="#">
                            <img style="height: 80px; width: 72px;"
                                src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                                alt="Municipality of Paluan Logo"
                                class="rounded-lg" />
                        </a>
                    </div>
                    <div class="header-title">
                        <h1 class="text-xl md:text-2xl font-bold">PROVINCE OF OCCIDENTAL MINDORO</h1>
                        <h1 class="text-xl md:text-2xl font-bold text-white">MUNICIPALITY OF PALUAN</h1>
                        <p class="text-base md:text-lg text-blue-100">REPUBLIC OF THE PHILIPPINES</p>
                    </div>
                </div>

                <div class="hidden md:flex about-btn">
                    <a href="#" class="flex items-center space-x-2">
                        <i class="fas fa-info-circle"></i>
                        <span>About Us</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <div class="login-card">
                <!-- Loader Overlay -->
                <div class="loader-overlay" id="loaderOverlay">
                    <div class="spinner"></div>
                    <div class="loader-text">Authenticating...</div>
                </div>

                <!-- PHP Error Message -->
                <?php if (!empty($error)): ?>
                    <div class="error-message show" id="errorMessage">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <!-- PHP Success Message -->
                <?php if (!empty($success)): ?>
                    <div class="info-message show" id="infoMessage">
                        <i class="fas fa-info-circle"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>

                <div class="card-content">
                    <!-- Form Header -->
                    <div class="form-header">
                        <div class="form-logo">
                            <img style="height: 80px; width: 72px;"
                                src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                                alt="Admin Portal" />
                        </div>
                        <h2 class="form-title"><?php echo $otp_required ? 'OTP Verification' : 'Admin Portal'; ?></h2>
                        <p class="form-subtitle">Human Resource Management Office</p>
                    </div>

                    <!-- OTP Verification Form -->
                    <?php if ($otp_required): ?>
                        <!-- OTP Info Box -->
                        <div class="otp-info">
                            <i class="fas fa-shield-alt"></i>
                            <h3>Two-Factor Authentication</h3>
                            <p>Enter the 6-digit OTP sent to:</p>
                            <div class="email-display"><?php echo htmlspecialchars($user_email); ?></div>
                            <p class="text-sm mt-2">Check your email inbox (and spam folder)</p>
                        </div>

                        <form id="otpForm" method="POST">
                            <input type="hidden" name="verify_otp" value="1">

                            <!-- OTP Timer -->
                            <?php if (isset($_SESSION['pending_user_id'])):
                                $stmt = $conn->prepare("SELECT otp_expires_at FROM users WHERE id = ?");
                                $stmt->execute([$_SESSION['pending_user_id']]);
                                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($result && $result['otp_expires_at']):
                            ?>
                                    <div class="otp-timer">
                                        <span class="timer-text" id="timerText">OTP expires in: <span id="countdown">05:00</span></span>
                                    </div>
                            <?php endif;
                            endif; ?>

                            <!-- OTP Input -->
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

                            <!-- Verify OTP Button -->
                            <button type="submit" class="submit-btn" id="verifyOtpBtn">
                                <div class="btn-content">
                                    <i class="fas fa-check-circle"></i>
                                    <span>VERIFY OTP</span>
                                </div>
                            </button>

                            <!-- Resend OTP Button -->
                            <button type="submit" name="resend_otp" value="1" class="secondary-btn">
                                <div class="btn-content">
                                    <i class="fas fa-redo"></i>
                                    <span>RESEND OTP</span>
                                </div>
                            </button>

                            <!-- Back to Login -->
                            <div class="form-toggle">
                                <a href="?" class="toggle-link">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back to Login</span>
                                </a>
                            </div>
                        </form>

                        <!-- Login Form (Hidden when OTP is required) -->
                    <?php else: ?>
                        <form id="loginForm" method="POST">
                            <!-- Email Input -->
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <input type="email"
                                        id="email"
                                        name="email"
                                        class="form-input"
                                        placeholder="Enter your email address"
                                        required
                                        autocomplete="email"
                                        value="<?php echo isset($_COOKIE['remember_user']) ? htmlspecialchars($_COOKIE['remember_user']) : ''; ?>">
                                    <i class="fas fa-envelope input-icon"></i>
                                </div>
                            </div>

                            <!-- Password Input -->
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <input type="password"
                                        id="password"
                                        name="password"
                                        class="form-input"
                                        placeholder="Enter your password"
                                        required
                                        autocomplete="current-password">
                                    <i class="fas fa-lock input-icon"></i>
                                </div>
                            </div>

                            <!-- Form Options -->
                            <div class="form-options">
                                <label class="remember-label" id="rememberLabel">
                                    <div class="remember-checkbox <?php echo isset($_COOKIE['remember_user']) ? 'checked' : ''; ?>" id="rememberCheckbox"></div>
                                    <span>Remember me</span>
                                    <input type="checkbox" name="remember" id="remember" style="display: none;" <?php echo isset($_COOKIE['remember_user']) ? 'checked' : ''; ?>>
                                </label>
                                <a href="#" class="forgot-link">Forgot password?</a>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="submit-btn">
                                <div class="btn-content">
                                    <i class="fas fa-sign-in-alt"></i>
                                    <span>SIGN IN TO PORTAL</span>
                                </div>
                            </button>

                            <!-- Demo Credentials -->
                            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                                <p class="text-sm text-blue-800 font-semibold mb-2 flex items-center">
                                    <i class="fas fa-info-circle mr-2"></i> Demo Credentials:
                                </p>
                                <p class="text-sm text-blue-700 mb-1"><strong>Email:</strong> punzalanmarkjhon8@gmail.com</p>
                                <p class="text-sm text-blue-700"><strong>Password:</strong> Codde</p>
                                <p class="text-xs text-blue-600 mt-2">
                                    <i class="fas fa-shield-alt mr-1"></i> OTP will be displayed on screen in demo mode
                                </p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const loginForm = document.getElementById('loginForm');
            const otpForm = document.getElementById('otpForm');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const loaderOverlay = document.getElementById('loaderOverlay');
            const rememberCheckbox = document.getElementById('rememberCheckbox');
            const rememberCheckboxInput = document.getElementById('remember');
            const rememberLabel = document.getElementById('rememberLabel');
            const fullOtpInput = document.getElementById('fullOtp');
            const verifyOtpBtn = document.getElementById('verifyOtpBtn');
            const timerText = document.getElementById('timerText');
            const countdownElement = document.getElementById('countdown');

            // OTP Timer Functionality
            <?php if (isset($result['otp_expires_at']) && $result['otp_expires_at']): ?>
                const expiryTime = <?php echo strtotime($result['otp_expires_at']) * 1000; ?>;

                function updateCountdown() {
                    const now = Date.now();
                    const remainingTime = Math.max(0, expiryTime - now);

                    if (remainingTime <= 0) {
                        countdownElement.textContent = '00:00';
                        countdownElement.classList.add('timer-expired');
                        if (timerText) {
                            timerText.innerHTML = 'OTP has expired. Please request a new one.';
                        }
                        if (verifyOtpBtn) {
                            verifyOtpBtn.disabled = true;
                        }
                        return;
                    }

                    const minutes = Math.floor(remainingTime / (1000 * 60));
                    const seconds = Math.floor((remainingTime % (1000 * 60)) / 1000);

                    countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                    // Update every second
                    setTimeout(updateCountdown, 1000);
                }

                // Start countdown
                if (countdownElement) {
                    updateCountdown();
                }
            <?php endif; ?>

            // OTP Input Handling
            const otpInputs = document.querySelectorAll('.otp-input');

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
                    if (input.value) {
                        input.classList.add('filled');
                    } else {
                        input.classList.remove('filled');
                    }
                });
                if (fullOtpInput) {
                    fullOtpInput.value = fullOTP;
                }

                // Enable/disable verify button based on OTP length
                if (verifyOtpBtn) {
                    verifyOtpBtn.disabled = fullOTP.length !== 6;
                }
            }

            // Initialize OTP inputs
            if (otpInputs.length > 0) {
                otpInputs[0].focus();
                otpInputs.forEach(input => {
                    input.addEventListener('input', () => updateFullOTP());
                });
                updateFullOTP();
            }

            // Remember me functionality
            if (rememberLabel) {
                rememberLabel.addEventListener('click', function() {
                    rememberCheckbox.classList.toggle('checked');
                    rememberCheckboxInput.checked = rememberCheckbox.classList.contains('checked');
                });
            }

            // Input validation
            function validateEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }

            // Show error state
            function showError(input, message = '') {
                input.classList.add('error');
                const errorMessage = document.getElementById('errorMessage');
                if (message && errorMessage) {
                    errorMessage.querySelector('span').textContent = message;
                    errorMessage.classList.add('show');
                    setTimeout(() => {
                        errorMessage.classList.remove('show');
                    }, 3000);
                }
            }

            // Hide error state
            function hideError(input) {
                input.classList.remove('error');
            }

            // Form submission handler for login form
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    // Get form values
                    const email = emailInput.value.trim();
                    const password = passwordInput.value;

                    // Validation
                    let isValid = true;

                    // Validate email
                    if (!email) {
                        showError(emailInput, 'Email is required');
                        isValid = false;
                    } else if (!validateEmail(email)) {
                        showError(emailInput, 'Please enter a valid email address');
                        isValid = false;
                    } else {
                        hideError(emailInput);
                    }

                    // Validate password
                    if (!password) {
                        showError(passwordInput, 'Password is required');
                        isValid = false;
                    } else if (password.length < 4) {
                        showError(passwordInput, 'Password must be at least 4 characters');
                        isValid = false;
                    } else {
                        hideError(passwordInput);
                    }

                    if (!isValid) {
                        e.preventDefault();
                        return;
                    }

                    // Show loader
                    loaderOverlay.classList.add('show');
                    loginForm.style.pointerEvents = 'none';
                });
            }

            // Form submission handler for OTP form
            if (otpForm) {
                otpForm.addEventListener('submit', function(e) {
                    const fullOTP = fullOtpInput ? fullOtpInput.value : '';

                    if (fullOTP.length !== 6) {
                        e.preventDefault();
                        showError(otpInputs[0], 'Please enter the complete 6-digit OTP');
                        return;
                    }

                    // Show loader
                    loaderOverlay.classList.add('show');
                    otpForm.style.pointerEvents = 'none';
                });
            }

            // Auto-hide success messages after 5 seconds
            const successMessages = document.querySelectorAll('.info-message.show, .success-message.show');
            successMessages.forEach(message => {
                setTimeout(() => {
                    message.classList.remove('show');
                }, 5000);
            });
        });
    </script>
</body>

</html>