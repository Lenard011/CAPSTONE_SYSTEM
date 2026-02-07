<?php
session_start();
require_once '../conn.php';
require_once 'mailer.php';

$mailer = new Mailer();
$error = '';
$success = '';
$demo_reset_link = '';
$form_submitted = false; // Add this flag

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_submitted = true; // Set flag when form is submitted
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

        if ($admin) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token in database
            $stmt = $conn->prepare("UPDATE admins SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $stmt->bind_param("ssi", $token, $expires, $admin['id']);

            if ($stmt->execute()) {
                // Create reset link
                $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;

                // Send password reset email using Mailer class
                $mailResult = $mailer->sendPasswordReset($admin['email'], $admin['full_name'], $resetLink);

                if ($mailResult['success']) {
                    if (isset($mailResult['demo_mode']) && $mailResult['demo_mode']) {
                        // For development - show the link
                        $success = $mailResult['message'];
                        $demo_reset_link = $resetLink;
                        $_SESSION['demo_reset_link'] = $resetLink;
                    } else {
                        $success = "✅ Password reset instructions have been sent to your email address.";
                        // Store in session so it persists after page refresh
                        $_SESSION['reset_success'] = $success;
                        $_SESSION['reset_email'] = $email;
                    }
                } else {
                    $error = "Failed to send reset email. Please try again later or contact the system administrator.";
                    if (isset($mailResult['error'])) {
                        error_log("Password reset email error: " . $mailResult['error']);
                    }
                }
            } else {
                $error = "An error occurred while processing your request. Please try again.";
            }
            $stmt->close();
        } else {
            $error = "No active admin account found with that email address.";
        }
    }
}

// Check for success message from session
if (isset($_SESSION['reset_success'])) {
    $success = $_SESSION['reset_success'];
    $form_submitted = true;
    unset($_SESSION['reset_success']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Municipality of Paluan HRMO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

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

        .forgot-card {
            width: 100%;
            max-width: 550px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .forgot-card::before {
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
            0% {
                background-position: -200% center;
            }

            100% {
                background-position: 200% center;
            }
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

        .form-subtitle {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
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
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .demo-link-box {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 2px solid #0ea5e9;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(14, 165, 233, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0);
            }
        }

        .demo-link-box h4 {
            color: #1e3a8a;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .demo-link {
            word-break: break-all;
            background: white;
            padding: 12px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.9rem;
            border: 1px solid #cbd5e1;
            margin: 10px 0;
            display: block;
            color: #1e293b;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .demo-link:hover {
            background: #f1f5f9;
            border-color: #3b82f6;
        }

        .demo-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.85rem;
            color: #92400e;
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

        .form-input.error {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
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

        .form-input:focus+.input-icon {
            color: #1e3a8a;
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
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
            font-family: 'Inter', sans-serif;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
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
            transform: translateY(-1px);
        }

        .loader-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            border-radius: 20px;
            z-index: 10;
        }

        .loader-overlay.show {
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .loader-text {
            margin-top: 1rem;
            font-size: 1rem;
            font-weight: 600;
            color: #1e3a8a;
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

            .demo-link {
                font-size: 0.8rem;
                padding: 10px;
            }
        }
    </style>

</head>

<body>
    <div class="forgot-card">
        <!-- Loader Overlay -->
        <div class="loader-overlay" id="loaderOverlay">
            <div class="spinner"></div>
            <div class="loader-text">Sending reset link...</div>
        </div>

        <div class="card-content">
            <!-- Header -->
            <div class="form-header">
                <div class="form-logo">
                    <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                        alt="Municipality of Paluan Logo">
                </div>
                <h2 class="form-title">Reset Password</h2>
                <p class="form-subtitle">
                    Enter your email address and we'll send you instructions to reset your password.
                </p>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (!empty($success) && !isset($mailResult['demo_mode'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Demo Mode Info Message -->
            <?php if (!empty($success) && isset($mailResult['demo_mode']) && $mailResult['demo_mode']): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>Development Mode: Email not configured. Here's your reset link:</span>
                </div>

                <div class="demo-link-box">
                    <h4><i class="fas fa-link"></i> Password Reset Link</h4>
                    <a href="<?php echo htmlspecialchars($demo_reset_link); ?>" class="demo-link" target="_blank">
                        <?php echo htmlspecialchars($demo_reset_link); ?>
                    </a>
                    <div class="demo-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Note:</strong> Running in development mode. To enable real email sending, configure your
                        Gmail App Password in mailer.php
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form (only show if form hasn't been successfully submitted) -->
            <?php if (!$form_submitted || (isset($mailResult['demo_mode']) && $mailResult['demo_mode'])): ?>
                <form id="forgotForm" method="POST" action="">
                    <div class="form-group">
                        <div class="input-wrapper">
                            <input type="email" id="email" name="email" class="form-input"
                                placeholder="Enter your email address" required autocomplete="email"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <div class="btn-content">
                            <i class="fas fa-paper-plane"></i>
                            <span>SEND RESET LINK</span>
                        </div>
                    </button>
                </form>
            <?php endif; ?>

            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Login
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('forgotForm');
            const loader = document.getElementById('loaderOverlay');
            const emailInput = document.getElementById('email');

            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault(); // Prevent default form submission

                    const email = emailInput.value.trim();

                    // Basic email validation
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                    if (!email) {
                        showError('Please enter your email address.');
                        emailInput.classList.add('error');
                        emailInput.focus();
                        return false;
                    }

                    if (!emailRegex.test(email)) {
                        showError('Please enter a valid email address.');
                        emailInput.classList.add('error');
                        emailInput.focus();
                        return false;
                    }

                    // Show loader
                    if (loader) {
                        loader.classList.add('show');
                    }

                    // Disable submit button
                    const submitBtn = form.querySelector('.submit-btn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<div class="btn-content"><i class="fas fa-spinner fa-spin"></i><span>SENDING...</span></div>';
                    }

                    // Create FormData
                    const formData = new FormData(form);

                    // Send AJAX request
                    fetch('', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(response => response.text())
                        .then(html => {
                            // Create a temporary div to parse the response
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = html;

                            // Find success/error messages in response
                            const successMsg = tempDiv.querySelector('.alert-success');
                            const errorMsg = tempDiv.querySelector('.alert-error');
                            const infoMsg = tempDiv.querySelector('.alert-info');
                            const demoBox = tempDiv.querySelector('.demo-link-box');

                            // Hide loader
                            if (loader) {
                                loader.classList.remove('show');
                            }

                            // Reset submit button
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = '<div class="btn-content"><i class="fas fa-paper-plane"></i><span>SEND RESET LINK</span></div>';
                            }

                            // Remove existing messages
                            document.querySelectorAll('.alert, .demo-link-box').forEach(el => el.remove());

                            // Add new messages
                            const cardContent = document.querySelector('.card-content');
                            const formHeader = document.querySelector('.form-header');

                            if (errorMsg) {
                                formHeader.parentNode.insertBefore(errorMsg, formHeader.nextSibling);
                                emailInput.classList.add('error');
                            }

                            if (successMsg) {
                                formHeader.parentNode.insertBefore(successMsg, formHeader.nextSibling);

                                // Hide form on success (unless demo mode)
                                if (!demoBox) {
                                    form.style.display = 'none';
                                }
                            }

                            if (infoMsg) {
                                formHeader.parentNode.insertBefore(infoMsg, formHeader.nextSibling);
                            }

                            if (demoBox) {
                                formHeader.parentNode.insertBefore(demoBox, form.nextSibling);
                            }

                            // Scroll to top
                            window.scrollTo({ top: 0, behavior: 'smooth' });

                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showError('An error occurred. Please try again.');

                            // Hide loader
                            if (loader) {
                                loader.classList.remove('show');
                            }

                            // Reset submit button
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = '<div class="btn-content"><i class="fas fa-paper-plane"></i><span>SEND RESET LINK</span></div>';
                            }
                        });

                    return false;
                });

                // Remove error class when typing
                if (emailInput) {
                    emailInput.addEventListener('input', function () {
                        this.classList.remove('error');
                        hideAlert();
                    });
                }
            }

            function showError(message) {
                // Remove existing error
                const existingError = document.querySelector('.alert-error');
                if (existingError) {
                    existingError.remove();
                }

                // Create new error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-error';
                errorDiv.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <span>${message}</span>
            `;

                // Insert after header
                const header = document.querySelector('.form-header');
                if (header && header.parentNode) {
                    header.parentNode.insertBefore(errorDiv, header.nextSibling);

                    // Scroll to error
                    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }

            function hideAlert() {
                const alerts = document.querySelectorAll('.alert-error');
                alerts.forEach(alert => alert.remove());
            }

            // Auto-focus email input
            if (emailInput) {
                emailInput.focus();
            }
        });
    </script>
</body>

</html>