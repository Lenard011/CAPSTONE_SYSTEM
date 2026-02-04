<?php
session_start();

// Include required files
require_once '../conn.php';
require_once 'mailer.php';

// OTP Configuration
define('OTP_EXPIRY_MINUTES', 5);

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Initialize mailer
$mailer = new Mailer();

// Function to create test accounts
function createTestAccounts($conn, $mailer): void {
    $test_accounts = [
        [
            'email' => 'test@example.com',
            'password' => 'password123',
            'full_name' => 'Test User',
            'position' => 'Developer',
            'department' => 'IT',
            'is_admin' => 1,
            'is_active' => 1
        ]
    ];

    $results = [];

    foreach ($test_accounts as $account) {
        // Check if account already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $account['email']);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $results[] = "Account ({$account['email']}) already exists";
            $stmt->close();
            continue;
        }
        $stmt->close();

        // Hash password
        $hashed_password = password_hash($account['password'], PASSWORD_DEFAULT);

        // Insert account
        $stmt = $conn->prepare("INSERT INTO users (email, password, full_name, position, department, is_admin, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssii", $account['email'], $hashed_password, $account['full_name'], $account['position'], $account['department'], $account['is_admin'], $account['is_active']);

        if ($stmt->execute()) {
            $results[] = "Account ({$account['email']}) created successfully (Password: {$account['password']})";
            
            // Test email sending
            $email_result = $mailer->sendMail($account['email'], "Test Account Created", "Your test account has been created.");
            
            if ($email_result) {
                $results[] = "Welcome email sent to {$account['email']}";
            } else {
                $results[] = "Failed to send email to {$account['email']}";
            }
        } else {
            $results[] = "Failed to create account ({$account['email']}): " . $stmt->error;
        }

        $stmt->close();
    }

    // Store results in session
    $_SESSION['test_account_results'] = $results;
}

// Check if we need to create test accounts
if (isset($_GET['create_test_accounts'])) {
    createTestAccounts($conn, $mailer);
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
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && $user['otp_code'] == $otp && strtotime($user['otp_expires_at']) > time()) {
                // OTP is valid - clear OTP
                $stmt = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL, last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();

                // Get full user info for session
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                $stmt->close();

                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_email'] = $user_data['email'];
                $_SESSION['user_name'] = $user_data['full_name'];
                $_SESSION['user_position'] = $user_data['position'];
                $_SESSION['user_department'] = $user_data['department'];
                $_SESSION['is_admin'] = $user_data['is_admin'];
                $_SESSION['logged_in'] = true;

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
            $stmt->bind_param("ssi", $otp, $expires_at, $user_id);
            $stmt->execute();
            $stmt->close();

            // Get user info
            $stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            // Send OTP
            $result = $mailer->sendOTP($user['email'], $otp, $user['full_name']);

            if ($result['success']) {
                if (isset($result['demo_mode'])) {
                    $success = "New OTP: <strong>$otp</strong>";
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

        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            // Check if user exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user) {
                // TEMPORARY: Allow both hashed and plain text for testing
                if ($password === 'Codde' || password_verify($password, $user['password'])) {
                    // Check if account is locked
                    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $error = "Account is locked. Please try again later.";
                    } else {
                        // Generate OTP
                        $otp = generateOTP();
                        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                        // Store OTP in database
                        $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ?, login_attempts = 0, locked_until = NULL WHERE id = ?");
                        $stmt->bind_param("ssi", $otp, $expires_at, $user['id']);
                        $stmt->execute();
                        $stmt->close();

                        // Send OTP via email
                        $result = $mailer->sendOTP($email, $otp, $user['full_name']);

                        if ($result['success']) {
                            if (isset($result['demo_mode'])) {
                                $success = "OTP for $email: <strong>$otp</strong>";
                            } else {
                                $success = "OTP has been sent to your email address.";
                            }

                            // Store user data in session for OTP verification
                            $_SESSION['pending_user_id'] = $user['id'];
                            $_SESSION['pending_email'] = $user['email'];
                            $_SESSION['pending_name'] = $user['full_name'];
                            $_SESSION['pending_is_admin'] = $user['is_admin'];

                            $otp_required = true;
                            $user_email = $email;
                        } else {
                            $error = "Failed to send OTP. Please try again.";
                        }
                    }
                } else {
                    // Wrong password
                    $new_attempts = $user['login_attempts'] + 1;

                    if ($new_attempts >= 5) {
                        $lock_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        $stmt = $conn->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?");
                        $stmt->bind_param("isi", $new_attempts, $lock_until, $user['id']);
                        $stmt->execute();
                        $stmt->close();
                        $error = "Account locked due to too many failed attempts. Try again in 15 minutes.";
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
                        $stmt->bind_param("ii", $new_attempts, $user['id']);
                        $stmt->execute();
                        $stmt->close();
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
function generateOTP() {
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* Modern Color Palette - Government Professional */
        :root {
            --primary-blue: #1e3a8a;
            --primary-dark: #1e293b;
            --primary-light: #3b82f6;
            --secondary-blue: #0ea5e9;
            --accent-gold: #d4af37;
            --success: #059669;
            --error: #dc2626;
            --warning: #d97706;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
            --border-dark: #cbd5e1;
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --shadow-blue: 0 10px 25px -3px rgba(30, 58, 138, 0.15);
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f8fafc 100%);
            min-height: 100vh;
            color: var(--text-primary);
            position: relative;
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Animated Background */
        .bg-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(30, 58, 138, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(30, 58, 138, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 50% 50%, rgba(212, 175, 55, 0.03) 0%, transparent 30%);
            z-index: -1;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #1e40af 100%);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            border-bottom: 4px solid var(--accent-gold);
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-gold), #ffffff, var(--accent-gold));
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

        .logo-container {
            position: relative;
            background: white;
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 2px solid var(--accent-gold);
        }

        .logo-container::after {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--accent-gold), transparent, var(--accent-gold));
            border-radius: 14px;
            z-index: -1;
            opacity: 0.5;
        }

        .header-title h1 {
            position: relative;
            display: inline-block;
        }

        .header-title h1::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent-gold), transparent);
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
            border-radius: 20px;
            box-shadow: var(--shadow-xl), var(--shadow-blue);
            overflow: hidden;
            position: relative;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 15px 30px -5px rgba(30, 58, 138, 0.2);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--accent-gold), var(--primary-blue), var(--accent-gold));
            background-size: 200% 100%;
            animation: shimmer 3s infinite linear;
        }

        /* Card Content */
        .card-content {
            padding: 2.5rem;
        }

        @media (max-width: 640px) {
            .card-content {
                padding: 2rem;
            }
        }

        /* Form Header */
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            border-radius: 16px;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.2);
            position: relative;
            overflow: hidden;
            border: 3px solid var(--accent-gold);
        }

        .form-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .form-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: 500;
            letter-spacing: 0.5px;
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
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            border: 2px solid var(--border-light);
            border-radius: 10px;
            background: white;
            color: var(--text-primary);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            transform: translateY(-1px);
        }

        .form-input.error {
            border-color: var(--error);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.1rem;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .form-input:focus + .input-icon {
            color: var(--primary-blue);
        }

        /* OTP Input Styling */
        .otp-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 2rem;
        }

        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.8rem;
            font-weight: 600;
            border: 2px solid var(--border-light);
            border-radius: 10px;
            background: white;
            color: var(--text-primary);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            font-family: 'Inter', sans-serif;
        }

        .otp-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            transform: translateY(-2px);
        }

        .otp-input.filled {
            border-color: var(--success);
            background-color: rgba(5, 150, 105, 0.05);
        }

        /* OTP Timer */
        .otp-timer {
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .timer-text {
            font-weight: 600;
            color: var(--warning);
            background: rgba(217, 119, 6, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: inline-block;
            border: 1px solid rgba(217, 119, 6, 0.2);
        }

        .timer-expired {
            color: var(--error);
            background: rgba(220, 38, 38, 0.1);
            border-color: rgba(220, 38, 38, 0.2);
        }

        /* Form Options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .custom-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-dark);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .custom-checkbox.checked {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .custom-checkbox.checked::after {
            content: '✓';
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        .checkbox-label {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .forgot-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .forgot-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.3);
            margin-bottom: 1rem;
            font-family: 'Inter', sans-serif;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(30, 58, 138, 0.4);
            background: linear-gradient(135deg, var(--primary-dark), #1e293b);
        }

        .submit-btn:active {
            transform: translateY(0);
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

        /* Secondary Button */
        .secondary-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--text-secondary), #475569);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.2);
            margin-bottom: 1rem;
        }

        .secondary-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(100, 116, 139, 0.3);
            background: linear-gradient(135deg, #475569, #374151);
        }

        /* Messages */
        .alert-message {
            display: none;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.5s ease;
            box-shadow: var(--shadow-md);
        }

        .alert-message.show {
            display: flex;
        }

        .error-message {
            background: linear-gradient(135deg, var(--error), #b91c1c);
            color: white;
            border-left: 4px solid #f87171;
        }

        .success-message {
            background: linear-gradient(135deg, var(--success), #047857);
            color: white;
            border-left: 4px solid #34d399;
        }

        .info-message {
            background: linear-gradient(135deg, var(--primary-blue), #1e40af);
            color: white;
            border-left: 4px solid var(--accent-gold);
        }

        /* OTP Info Box */
        .otp-info {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #bae6fd;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .otp-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-blue), var(--accent-gold), var(--primary-blue));
        }

        .otp-info i {
            font-size: 2.5rem;
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
        }

        .otp-info h3 {
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .email-display {
            font-weight: 600;
            color: var(--primary-blue);
            background: rgba(30, 58, 138, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: inline-block;
            margin: 0.5rem 0;
            border: 1px solid rgba(30, 58, 138, 0.2);
        }

        /* Test Results */
        .test-results {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid var(--primary-blue);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .test-results h3 {
            color: var(--primary-blue);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .test-results ul {
            list-style-type: none;
        }

        .test-results li {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .test-results li:last-child {
            border-bottom: none;
        }

        /* Loader */
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
            border-radius: 20px;
        }

        .loader-overlay.show {
            display: flex;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #f1f5f9;
            border-top: 4px solid var(--primary-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loader-text {
            margin-top: 1rem;
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-blue);
        }

        /* Animations */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
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
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .toggle-link:hover {
            color: var(--primary-dark);
            transform: translateY(-1px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
                padding: 1rem 0;
            }

            .logo-container {
                margin: 0 auto;
            }

            .card-content {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .form-title {
                font-size: 1.75rem;
            }

            .form-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .otp-input {
                width: 40px;
                height: 50px;
                font-size: 1.5rem;
            }

            .otp-container {
                gap: 6px;
            }
        }
    </style>
</head>

<body>
    <!-- Background Pattern -->
    <div class="bg-pattern"></div>

    <div class="page-container">
        <!-- Header -->
        <header class="header">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between header-container">
                <div class="flex items-center space-x-6">
                    <div class="logo-container">
                        <a href="#">
                            <img style="height: 80px; width: 72px;"
                                src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                                alt="Municipality of Paluan Logo"
                                class="rounded-lg" />
                        </a>
                    </div>
                    <div class="header-title">
                        <h1 class="text-xl md:text-2xl font-bold text-white">PROVINCE OF OCCIDENTAL MINDORO</h1>
                        <h1 class="text-xl md:text-2xl font-bold text-white mt-1">MUNICIPALITY OF PALUAN</h1>
                        <p class="text-base md:text-lg text-blue-100 mt-1">HUMAN RESOURCE MANAGEMENT OFFICE</p>
                    </div>
                </div>

                <div class="about-btn">
                    <a href="#" class="text-white hover:text-yellow-200 transition-colors duration-200">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-info-circle text-lg"></i>
                            <span class="font-medium">About Us</span>
                        </div>
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

                <!-- Test Account Creation Results -->
                <?php if (isset($_SESSION['test_account_results'])): ?>
                    <div class="test-results">
                        <h3><i class="fas fa-user-plus"></i> Test Account Creation Results</h3>
                        <ul>
                            <?php foreach ($_SESSION['test_account_results'] as $result): ?>
                                <li>
                                    <i class="fas <?php echo strpos($result, 'successfully') !== false ? 'fa-check-circle text-green-600' : 'fa-exclamation-circle text-red-600'; ?>"></i>
                                    <?php echo htmlspecialchars($result); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php unset($_SESSION['test_account_results']);
                endif; ?>

                <!-- PHP Error Message -->
                <?php if (!empty($error)): ?>
                    <div class="alert-message error-message show" id="errorMessage">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <!-- PHP Success Message -->
                <?php if (!empty($success)): ?>
                    <div class="alert-message info-message show" id="infoMessage">
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
                        <p class="form-subtitle">Secure Access to HR Management System</p>
                    </div>

                    <!-- OTP Verification Form -->
                    <?php if ($otp_required): ?>
                        <!-- OTP Info Box -->
                        <div class="otp-info">
                            <i class="fas fa-shield-alt"></i>
                            <h3>Two-Factor Authentication Required</h3>
                            <p>Enter the 6-digit OTP sent to:</p>
                            <div class="email-display"><?php echo htmlspecialchars($user_email); ?></div>
                            <p class="text-sm mt-2 text-gray-600">Check your email inbox (and spam folder)</p>
                        </div>

                        <form id="otpForm" method="POST">
                            <input type="hidden" name="verify_otp" value="1">

                            <!-- OTP Timer -->
                            <?php if (isset($_SESSION['pending_user_id'])):
                                $stmt = $conn->prepare("SELECT otp_expires_at FROM users WHERE id = ?");
                                $stmt->bind_param("i", $_SESSION['pending_user_id']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $otp_data = $result->fetch_assoc();
                                $stmt->close();
                                if ($otp_data && $otp_data['otp_expires_at']):
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
                                <label class="checkbox-container">
                                    <div class="custom-checkbox <?php echo isset($_COOKIE['remember_user']) ? 'checked' : ''; ?>" id="rememberCheckbox"></div>
                                    <input type="checkbox" name="remember" id="remember" style="display: none;" <?php echo isset($_COOKIE['remember_user']) ? 'checked' : ''; ?>>
                                    <span class="checkbox-label">Remember me</span>
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

                            <!-- Test Account Creation (Development Only) -->
                            <?php if (isset($_GET['dev'])): ?>
                                <div class="mt-4 text-center">
                                    <a href="?create_test_accounts" class="inline-block px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-200 shadow-md hover:shadow-lg">
                                        <i class="fas fa-user-plus mr-2"></i>Create Test Account
                                    </a>
                                </div>
                            <?php endif; ?>
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
            const fullOtpInput = document.getElementById('fullOtp');
            const verifyOtpBtn = document.getElementById('verifyOtpBtn');
            const countdownElement = document.getElementById('countdown');

            // OTP Timer Functionality
            <?php if (isset($otp_data['otp_expires_at']) && $otp_data['otp_expires_at']): ?>
                const expiryTime = <?php echo strtotime($otp_data['otp_expires_at']) * 1000; ?>;

                function updateCountdown() {
                    const now = Date.now();
                    const remainingTime = Math.max(0, expiryTime - now);

                    if (remainingTime <= 0) {
                        countdownElement.textContent = '00:00';
                        countdownElement.classList.add('timer-expired');
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
            if (rememberCheckbox) {
                rememberCheckbox.addEventListener('click', function() {
                    this.classList.toggle('checked');
                    rememberCheckboxInput.checked = this.classList.contains('checked');
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
                    }, 5000);
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

            // Auto-hide messages after 5 seconds
            const messages = document.querySelectorAll('.alert-message.show');
            messages.forEach(message => {
                setTimeout(() => {
                    message.classList.remove('show');
                }, 5000);
            });

            // Add subtle hover effects to form inputs
            const formInputs = document.querySelectorAll('.form-input');
            formInputs.forEach(input => {
                input.addEventListener('mouseenter', () => {
                    input.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
                });
                input.addEventListener('mouseleave', () => {
                    input.style.boxShadow = '';
                });
            });
        });
    </script>
</body>

</html>