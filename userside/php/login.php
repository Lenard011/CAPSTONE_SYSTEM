<?php
// ==============================================
// SIMPLE WORKING LOGIN SYSTEM WITH FORGOT PASSWORD
// USING EXISTING MAILER.PHP
// ==============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the correct cookie path for your setup
$cookiePath = '/CAPSTONE_SYSTEM/userside/php/';

// Configure session
session_name('HRMS_SESSION');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => $cookiePath,
    'domain' => '',
    'secure' => false, // false for localhost
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include mailer.php from admin side
require_once __DIR__ . '/../../admin/php/mailer.php';

// DEBUG: Show session info in HTML comment
echo "<!-- SESSION DEBUG: ID=" . session_id() . " Path=" . $cookiePath . " -->\n";

// Handle AJAX login request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    // Clear output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/json');

    $response = [
        'success' => false,
        'message' => '',
        'redirect' => null
    ];

    try {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            throw new Exception('Username and password are required');
        }

        // Database connection
        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $dbname = 'hrms_paluan';

        $conn = new mysqli($host, $user, $pass, $dbname);

        if ($conn->connect_error) {
            throw new Exception('Database connection failed');
        }

        $conn->set_charset("utf8mb4");

        // Query to find user
        $sql = "SELECT id, username, email, password_hash, first_name, last_name, 
                       full_name, role, access_level, employee_id, profile_image,
                       password_is_temporary
                FROM users 
                WHERE (username = ? OR email = ?) 
                AND account_status = 'ACTIVE'
                AND is_active = 1
                AND is_verified = 1
                AND status = 'approved'";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception('Database query error');
        }

        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            throw new Exception('Invalid username or email');
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('Invalid password');
        }

        // SUCCESS - Set session variables
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['access_level'] = (int) $user['access_level'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['profile_image'] = $user['profile_image'];
        $_SESSION['login_time'] = time();
        $_SESSION['created'] = time();
        $_SESSION['last_activity'] = time();

        // Check for temporary password
        if ($user['password_is_temporary'] == 1) {
            $_SESSION['must_change_password'] = true;
            $response['temp_password'] = true;
            $response['redirect'] = 'change_password.php';
            $response['message'] = 'Temporary password detected. Please create a new password.';
        } else {
            $response['redirect'] = 'homepage.php';
            $response['message'] = 'Login successful!';
        }

        $response['success'] = true;

        // Update last login
        $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        if ($updateStmt) {
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
        }

        $conn->close();

        // Force session write
        session_write_close();

        // Log success
        error_log("Login successful for user: " . $user['username']);

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Login error: " . $e->getMessage());
    }

    echo json_encode($response);
    exit();
}

// Handle Forgot Password AJAX Requests - USING EXISTING USERS TABLE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Clear output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json');

        // Database configuration
        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $dbname = 'hrms_paluan';

        $conn = new mysqli($host, $user, $pass, $dbname);

        if ($conn->connect_error) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit();
        }

        $conn->set_charset("utf8mb4");

        // Handle Send OTP
        if ($_POST['action'] === 'send_otp') {
            $email = trim($_POST['email']);

            // Validate email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Valid email is required']);
                exit();
            }

            // Check if email exists in users table
            $stmt = $conn->prepare("SELECT id, username, first_name, email FROM users WHERE email = ? AND is_active = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Email not found in our records']);
                exit();
            }

            // Generate OTP (6-digit code)
            $otp = sprintf("%06d", mt_rand(1, 999999));

            // Set expiration to 15 minutes from now
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Store OTP in users table using password_reset_token and password_reset_expires columns
            $updateStmt = $conn->prepare("
                UPDATE users 
                SET password_reset_token = ?, 
                    password_reset_expires = ? 
                WHERE id = ? AND email = ?
            ");
            $updateStmt->bind_param("ssis", $otp, $expires, $user['id'], $email);

            if (!$updateStmt->execute()) {
                error_log("Failed to store OTP: " . $updateStmt->error);
                echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
                $updateStmt->close();
                exit();
            }
            $updateStmt->close();

            // Initialize mailer and send OTP
            try {
                $mailer = new Mailer();

                // Get user's name
                $userName = $user['first_name'] ?: $user['username'];

                // Send OTP using mailer's sendResetOTP method
                $result = $mailer->sendResetOTP($email, $otp, $userName);

                if ($result['success']) {
                    if (isset($result['demo_mode']) && $result['demo_mode']) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'OTP sent (Development Mode)',
                            'debug_otp' => $otp,
                            'development_mode' => true,
                            'expires_at' => $expires
                        ]);
                    } else {
                        echo json_encode([
                            'success' => true,
                            'message' => 'OTP has been sent to your email address.'
                        ]);
                    }
                } else {
                    // Still return success since OTP is saved in database
                    echo json_encode([
                        'success' => true,
                        'message' => 'OTP generated. Please check your email.',
                        'debug_otp' => $otp // Remove in production
                    ]);
                }
            } catch (Exception $e) {
                error_log("Mailer error: " . $e->getMessage());
                // Still return success since OTP is in database
                echo json_encode([
                    'success' => true,
                    'message' => 'OTP generated. Please check your email.',
                    'debug_otp' => $otp // Remove in production
                ]);
            }
            exit();
        }

        // Handle Verify OTP - USING USERS TABLE
        if ($_POST['action'] === 'verify_otp') {
            $email = trim($_POST['email']);
            $otp = trim($_POST['otp']);

            if (empty($email) || empty($otp)) {
                echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
                exit();
            }

            // Debug: Log the current time and OTP being verified
            error_log("Verifying OTP - Email: $email, OTP: $otp, Current Time: " . date('Y-m-d H:i:s'));

            // Check OTP from users table
            $stmt = $conn->prepare("
                SELECT id, password_reset_token, password_reset_expires 
                FROM users 
                WHERE email = ? 
                AND password_reset_token IS NOT NULL 
                AND password_reset_expires IS NOT NULL
            ");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'No OTP found. Please request a new one.']);
                exit();
            }

            error_log("Stored OTP: {$user['password_reset_token']}, Expires: {$user['password_reset_expires']}");

            // Check if OTP matches
            if ($user['password_reset_token'] !== $otp) {
                echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please check and try again.']);
                exit();
            }

            // Check if OTP is expired
            $now = date('Y-m-d H:i:s');
            if ($user['password_reset_expires'] < $now) {
                echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
                exit();
            }

            // OTP is valid - clear the token (mark as used)
            $clearStmt = $conn->prepare("
                UPDATE users 
                SET password_reset_token = NULL, 
                    password_reset_expires = NULL 
                WHERE id = ?
            ");
            $clearStmt->bind_param("i", $user['id']);
            $clearStmt->execute();
            $clearStmt->close();

            // Store verification in session with timestamp
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_verified'] = true;
            $_SESSION['reset_time'] = time();
            $_SESSION['reset_expires'] = time() + 1800; // 30 minutes from now

            error_log("OTP verified successfully for email: $email");

            echo json_encode(['success' => true, 'message' => 'OTP verified successfully']);
            exit();
        }

        // Handle Reset Password - USING USERS TABLE
        if ($_POST['action'] === 'reset_password') {
            $email = trim($_POST['email']);
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            // Check session verification
            if (
                !isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true ||
                $_SESSION['reset_email'] !== $email
            ) {

                echo json_encode(['success' => false, 'message' => 'Please verify OTP first']);
                exit();
            }

            // Check session timeout (30 minutes)
            if (time() > $_SESSION['reset_expires']) {
                // Clear expired session
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_verified']);
                unset($_SESSION['reset_time']);
                unset($_SESSION['reset_expires']);

                echo json_encode(['success' => false, 'message' => 'Session expired. Please verify OTP again.']);
                exit();
            }

            // Validate passwords
            if (empty($newPassword) || empty($confirmPassword)) {
                echo json_encode(['success' => false, 'message' => 'All password fields are required']);
                exit();
            }

            if ($newPassword !== $confirmPassword) {
                echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
                exit();
            }

            if (strlen($newPassword) < 8) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
                exit();
            }

            // Check password strength
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $newPassword)) {
                echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number']);
                exit();
            }

            // Update password in users table
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                UPDATE users 
                SET password_hash = ?, 
                    password_is_temporary = 0,
                    last_password_change = NOW(),
                    login_attempts = 0,
                    last_login_attempt = NULL
                WHERE id = ? AND email = ?
            ");
            $stmt->bind_param("sis", $hashedPassword, $_SESSION['reset_user_id'], $email);

            if ($stmt->execute()) {
                // Clear reset session
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_verified']);
                unset($_SESSION['reset_time']);
                unset($_SESSION['reset_expires']);

                error_log("Password reset successful for email: $email");

                echo json_encode(['success' => true, 'message' => 'Password reset successful! You can now login with your new password.']);
            } else {
                error_log("Password reset failed for email: $email - " . $conn->error);
                echo json_encode(['success' => false, 'message' => 'Failed to reset password. Please try again.']);
            }
            $stmt->close();
            exit();
        }

        $conn->close();
    }
}

// Function to cleanup expired OTP tokens (call this periodically or on every request)
function cleanupExpiredOTPTokens($conn)
{
    $stmt = $conn->prepare("
        UPDATE users 
        SET password_reset_token = NULL, 
            password_reset_expires = NULL 
        WHERE password_reset_expires < NOW() 
        AND password_reset_expires IS NOT NULL
    ");
    $stmt->execute();
    $cleaned = $stmt->affected_rows;
    $stmt->close();

    if ($cleaned > 0) {
        error_log("Cleaned up $cleaned expired OTP tokens");
    }
    return $cleaned;
}
// Check if already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: homepage.php');
    exit();
}

// Handle session messages
$sessionExpired = isset($_GET['session']) && $_GET['session'] == 'expired';
$logoutSuccess = isset($_GET['logout']) && $_GET['logout'] == 'success';

// Check if user is already logged in - REDIRECT TO HOMEPAGE
if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && !empty($_SESSION['user_id'])) {
    // Check if this is a login POST request
    $isLoginRequest = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']);

    if (!$isLoginRequest) {
        // Update last activity
        $_SESSION['last_activity'] = time();

        // Check for forced password change
        if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] === true) {
            header('Location: change_password.php');
            exit();
        }

        // Clear output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Redirect to homepage
        header('Location: homepage.php');
        exit();
    }
}

// Check for remember me cookie (only if not already logged in)
if ((!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) && isset($_COOKIE['remember_me'])) {
    try {
        error_log("Checking remember me cookie...");
        $cookieValue = $_COOKIE['remember_me'];
        $parts = explode(':', $cookieValue);

        if (count($parts) === 2) {
            $selector = $parts[0];
            $token = $parts[1];

            // Database configuration
            $host = 'localhost';
            $user = 'root';
            $pass = '';
            $dbname = 'hrms_paluan';

            // Connect to database
            $conn = new mysqli($host, $user, $pass, $dbname);

            if (!$conn->connect_error) {
                $conn->set_charset("utf8mb4");

                // Check if remember_tokens table exists
                $checkTable = $conn->query("SHOW TABLES LIKE 'remember_tokens'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $stmt = $conn->prepare("
                        SELECT rt.user_id, rt.hashed_token, rt.expires, 
                               u.id, u.username, u.email, u.first_name, u.last_name, u.full_name,
                               u.role, u.access_level, u.employee_id, u.profile_image,
                               u.employment_type, u.department, u.position,
                               u.password_is_temporary, u.is_verified, u.is_active, u.status
                        FROM remember_tokens rt
                        JOIN users u ON rt.user_id = u.id
                        WHERE rt.selector = ? 
                        AND rt.expires > NOW()
                        AND u.account_status = 'ACTIVE'
                        AND u.is_active = 1
                        AND u.is_verified = 1
                        AND u.status = 'approved'
                    ");

                    if ($stmt) {
                        $stmt->bind_param("s", $selector);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $tokenData = $result->fetch_assoc();
                        $stmt->close();

                        if ($tokenData && password_verify($token, $tokenData['hashed_token'])) {
                            error_log("Remember me token valid for user: " . $tokenData['username']);

                            // Regenerate session ID for security
                            session_regenerate_id(true);

                            // IMPORTANT: Don't clear session, just set/update variables
                            $_SESSION['user_id'] = $tokenData['id'];
                            $_SESSION['username'] = $tokenData['username'];
                            $_SESSION['email'] = $tokenData['email'];
                            $_SESSION['first_name'] = $tokenData['first_name'];
                            $_SESSION['last_name'] = $tokenData['last_name'];
                            $_SESSION['full_name'] = $tokenData['full_name'];
                            $_SESSION['role'] = $tokenData['role'];
                            $_SESSION['access_level'] = $tokenData['access_level'];
                            $_SESSION['employee_id'] = $tokenData['employee_id'];
                            $_SESSION['profile_image'] = $tokenData['profile_image'];
                            $_SESSION['employment_type'] = $tokenData['employment_type'];
                            $_SESSION['department'] = $tokenData['department'];
                            $_SESSION['position'] = $tokenData['position'];
                            $_SESSION['last_activity'] = time();
                            $_SESSION['login_time'] = time();
                            $_SESSION['created'] = time();
                            $_SESSION['remember_login'] = true;

                            // Check for temporary password
                            if ($tokenData['password_is_temporary'] == 1) {
                                $_SESSION['must_change_password'] = true;
                                $_SESSION['temp_password_login'] = true;

                                // Clear output buffer
                                while (ob_get_level()) {
                                    ob_end_clean();
                                }

                                header('Location: change_password.php');
                                exit();
                            }

                            // Update last login in database
                            $updateStmt = $conn->prepare("
                                UPDATE users 
                                SET last_login = NOW(), 
                                    login_attempts = 0,
                                    last_login_attempt = NULL
                                WHERE id = ?
                            ");

                            if ($updateStmt) {
                                $updateStmt->bind_param("i", $tokenData['id']);
                                $updateStmt->execute();
                                $updateStmt->close();
                            }

                            // Force session write
                            session_write_close();

                            // Restart session immediately
                            session_start();

                            // Clear output buffer
                            while (ob_get_level()) {
                                ob_end_clean();
                            }

                            // Redirect to homepage
                            error_log("Remember me login successful, redirecting to homepage");
                            header('Location: homepage.php');
                            exit();
                        } else {
                            error_log("Invalid remember me token");
                            // Invalid token, clear the cookie
                            setcookie('remember_me', '', time() - 3600, '/', '', true, true);
                        }
                    }
                }
                $conn->close();
            }
        }
    } catch (Exception $e) {
        error_log("Remember me error: " . $e->getMessage());
    }
}

// Check if this is an AJAX login request
$isLoginRequest = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']);

if ($isLoginRequest) {
    // Clear ALL output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set JSON response headers
    header('Content-Type: application/json');

    // Initialize response
    $response = [
        'success' => false,
        'message' => '',
        'redirect' => null,
        'temp_password' => false,
        'debug' => [] // Added for debugging
    ];

    try {
        // Validate inputs
        if (empty($_POST['username']) || empty($_POST['password'])) {
            throw new Exception('Username and password are required');
        }

        $identifier = trim($_POST['username']);
        $password = $_POST['password'];
        $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === 'true';

        // Database configuration
        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $dbname = 'hrms_paluan';

        // Connect to database
        $conn = new mysqli($host, $user, $pass, $dbname);

        if ($conn->connect_error) {
            throw new Exception('Database connection failed.');
        }

        $conn->set_charset("utf8mb4");

        // Build SELECT query with all necessary fields
        $sql = "SELECT 
                    id, username, email, password_hash, first_name, last_name, full_name,
                    role, access_level, account_status, employee_id, profile_image,
                    password_is_temporary, must_change_password,
                    is_verified, is_active, status,
                    employment_type, department, position
                FROM users 
                WHERE (username = ? OR email = ?) 
                AND account_status = 'ACTIVE'
                AND is_active = 1";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception('Database query error: ' . $conn->error);
        }

        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            throw new Exception('Invalid username or email');
        }

        // Check if user is verified
        if ($user['is_verified'] != 1) {
            throw new Exception('Your account is not verified. Please check your email.');
        }

        // Check user status
        if ($user['status'] !== 'approved') {
            $statusMessage = match ($user['status']) {
                'pending' => 'Your account is pending approval.',
                'rejected' => 'Your account has been rejected.',
                'suspended' => 'Your account is suspended.',
                default => 'Your account is not approved.'
            };
            throw new Exception($statusMessage);
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            // Increment failed login attempts
            $updateStmt = $conn->prepare("
                UPDATE users 
                SET login_attempts = COALESCE(login_attempts, 0) + 1, 
                    last_login_attempt = NOW() 
                WHERE id = ?
            ");

            if ($updateStmt) {
                $updateStmt->bind_param("i", $user['id']);
                $updateStmt->execute();
                $updateStmt->close();
            }

            throw new Exception('Invalid password');
        }

        // Check for temporary password
        $isTemporaryPassword = $user['password_is_temporary'] == 1;

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set all session variables
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['access_level'] = (int) $user['access_level'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['profile_image'] = $user['profile_image'];
        $_SESSION['employment_type'] = $user['employment_type'];
        $_SESSION['department'] = $user['department'];
        $_SESSION['position'] = $user['position'];
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
        $_SESSION['created'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Add debug info
        $response['debug']['session_id'] = session_id();
        $response['debug']['user_id'] = $_SESSION['user_id'];
        $response['debug']['cookie_path'] = ini_get('session.cookie_path');

        // Handle temporary password
        if ($isTemporaryPassword) {
            $_SESSION['must_change_password'] = true;
            $response['temp_password'] = true;
            $response['redirect'] = 'change_password.php';
            $response['message'] = 'Temporary password detected. Please create a new password.';
        } else {
            $response['redirect'] = 'homepage.php';
            $response['message'] = 'Login successful! Redirecting...';
        }

        $response['success'] = true;

        // Update last login and reset attempts
        $updateStmt = $conn->prepare("
            UPDATE users 
            SET last_login = NOW(), 
                login_attempts = 0,
                last_login_attempt = NULL
            WHERE id = ?
        ");

        if ($updateStmt) {
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
        }

        // Remember me functionality
        if ($rememberMe) {
            $token = bin2hex(random_bytes(32));
            $selector = bin2hex(random_bytes(16));
            $expires = time() + (30 * 24 * 60 * 60); // 30 days

            $hashedToken = password_hash($token, PASSWORD_BCRYPT);

            $checkTable = $conn->query("SHOW TABLES LIKE 'remember_tokens'");
            if ($checkTable && $checkTable->num_rows > 0) {
                // Delete any existing tokens for this user
                $deleteStmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                if ($deleteStmt) {
                    $deleteStmt->bind_param("i", $user['id']);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                }

                // Insert new token
                $stmt = $conn->prepare("
                    INSERT INTO remember_tokens 
                    (user_id, selector, hashed_token, expires) 
                    VALUES (?, ?, ?, FROM_UNIXTIME(?))
                ");

                if ($stmt) {
                    $stmt->bind_param("issi", $user['id'], $selector, $hashedToken, $expires);
                    $stmt->execute();
                    $stmt->close();

                    $cookieValue = $selector . ':' . $token;
                    setcookie(
                        'remember_me',
                        $cookieValue,
                        $expires,
                        '/CAPSTONE_SYSTEM/userside/php/', // Same path as session
                        $_SERVER['HTTP_HOST'] ?? '',
                        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                        true
                    );
                }
            }
        }

        $conn->close();

        // Force session write
        session_write_close();

        // Don't restart session - let homepage.php start it fresh
        error_log("Login successful for user: " . $user['username']);
        error_log("Session ID after login: " . session_id());

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Login error: " . $e->getMessage());
    }

    echo json_encode($response);
    exit();
}

// Check for logout success
$logoutSuccess = isset($_GET['logout']) && $_GET['logout'] == 'success';

// Check for session expired
$sessionExpired = isset($_GET['session']) && $_GET['session'] == 'expired';

// Check if redirected
$redirected = isset($_GET['redirected']) && $_GET['redirected'] == 'true';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Management System - Municipality of Paluan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Modern Color Palette - Updated to match image */
        :root {
            --navy-blue: #0235a2ff;
            --button-blue: #2c6bc4;
            --button-hover: #1e4a8a;
            --primary-blue: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #60a5fa;
            --accent-teal: #0d9488;
            --accent-orange: #f97316;
            --accent-green: #10b981;
            --light-bg: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.98);
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-light: #64748b;
            --border-light: #e2e8f0;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --shadow-blue: 0 10px 25px -3px rgba(44, 107, 196, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 50%, #f8fafc 100%);
            min-height: 100vh;
            color: var(--text-primary);
            position: relative;
            overflow-x: hidden;
        }

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
            background: linear-gradient(135deg, rgba(44, 107, 196, 0.08) 0%, rgba(13, 148, 136, 0.04) 100%);
            filter: blur(60px);
        }

        .bg-shape:nth-child(1) {
            width: 600px;
            height: 600px;
            top: -200px;
            left: -200px;
            animation: float 25s infinite linear;
        }

        .bg-shape:nth-child(2) {
            width: 400px;
            height: 400px;
            bottom: -150px;
            right: -150px;
            animation: float 30s infinite linear reverse;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) rotate(0deg);
            }

            33% {
                transform: translate(40px, 60px) rotate(120deg);
            }

            66% {
                transform: translate(-30px, 100px) rotate(240deg);
            }

            100% {
                transform: translate(0, 0) rotate(360deg);
            }
        }

        .header {
            background: var(--navy-blue);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
            border-bottom: 4px solid rgba(255, 255, 255, 0.2);
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-light), #ffffff, var(--accent-teal), var(--primary-light));
            background-size: 400% 100%;
            animation: shimmer 3s infinite linear;
        }

        @keyframes shimmer {
            0% {
                background-position: -400% center;
            }

            100% {
                background-position: 400% center;
            }
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .logo-container:hover {
            transform: translateY(-2px);
        }

        .logo-img {
            height: 80px;
            width: auto;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .logo-img:hover {
            transform: scale(1.05);
        }

        .header-title {
            display: flex;
            flex-direction: column;
        }

        .header-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.25rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .header-title .municipality {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.95);
        }

        .header-title .republic {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .about-btn a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .about-btn a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .main-container {
            display: flex;
            min-height: calc(100vh - 120px);
            padding: 2rem;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 1000px;
            min-height: 550px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            box-shadow: var(--shadow-xl), var(--shadow-blue);
            border: 1px solid rgba(255, 255, 255, 0.4);
            display: flex;
            overflow: hidden;
            position: relative;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .login-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.2), 0 20px 40px -8px rgba(44, 107, 196, 0.3);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--button-blue), var(--accent-teal), var(--button-blue));
            background-size: 300% 100%;
            animation: shimmer 2s infinite linear;
        }

        .form-section {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .form-section::before {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 1px;
            height: 80%;
            background: linear-gradient(to bottom, transparent, var(--border-light), transparent);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .form-logo {
            width: 90px;
            height: 90px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--button-blue), var(--navy-blue));
            border-radius: 18px;
            padding: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(44, 107, 196, 0.25);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .form-logo:hover {
            transform: scale(1.08) rotate(3deg);
            box-shadow: 0 15px 35px rgba(44, 107, 196, 0.35);
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
            top: -100%;
            left: -100%;
            width: 300%;
            height: 300%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: rotate(45deg);
            transition: transform 0.8s ease;
        }

        .form-logo:hover::after {
            transform: rotate(45deg) translate(30%, 30%);
        }

        .form-title {
            font-size: 2.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--button-blue), var(--navy-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .form-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 1.75rem;
            position: relative;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 1.1rem 1.1rem 1.1rem 3.8rem;
            font-size: 1rem;
            border: 2px solid var(--border-light);
            border-radius: 14px;
            background: white;
            color: var(--text-primary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--button-blue);
            box-shadow: 0 0 0 4px rgba(44, 107, 196, 0.15);
            transform: translateY(-2px);
        }

        .form-input.error {
            border-color: var(--error);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 1.3rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1.3rem;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .form-input:focus+.input-icon {
            color: var(--button-blue);
            transform: translateY(-50%) scale(1.15);
        }

        .error-message-text {
            display: none;
            color: var(--error);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            margin-left: 0.5rem;
            animation: slideDown 0.3s ease;
        }

        .error-message-text.show {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .password-toggle {
            position: absolute;
            right: 1.3rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 1.2rem;
            transition: color 0.2s ease;
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--button-blue);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 0.95rem;
            transition: color 0.2s ease;
        }

        .remember-label:hover {
            color: var(--button-blue);
        }

        .remember-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-light);
            border-radius: 6px;
            background: white;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }

        .remember-checkbox.checked {
            background: var(--button-blue);
            border-color: var(--button-blue);
            animation: checkPop 0.3s ease;
        }

        @keyframes checkPop {
            0% {
                transform: scale(0.8);
            }

            70% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
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
            color: var(--button-blue);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
            padding-bottom: 3px;
        }

        .forgot-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--button-blue);
            transition: width 0.3s ease;
            border-radius: 1px;
        }

        .forgot-link:hover::after {
            width: 100%;
        }

        .submit-btn {
            width: 100%;
            padding: 1.1rem;
            background: var(--button-blue);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 1.15rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(44, 107, 196, 0.3);
        }

        .submit-btn:hover {
            background: var(--button-hover);
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(44, 107, 196, 0.4);
        }

        .submit-btn:active {
            transform: translateY(-1px);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
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
            width: 400px;
            height: 400px;
        }

        .btn-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .welcome-section {
            flex: 1;
            background: linear-gradient(135deg, rgba(44, 107, 196, 0.03) 0%, rgba(13, 148, 136, 0.03) 100%);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%232c6bc4' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .welcome-title {
            font-size: 2.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--button-blue), var(--navy-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
            letter-spacing: -0.5px;
        }

        .welcome-text {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .features-list {
            text-align: left;
            margin-bottom: 2rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
            transition: transform 0.3s ease;
        }

        .feature-item:hover {
            transform: translateX(5px);
            color: var(--button-blue);
        }

        .feature-icon {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, var(--button-blue), var(--navy-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            flex-shrink: 0;
        }

        .image-container {
            width: 100%;
            max-width: 320px;
            margin: 0 auto;
            position: relative;
        }

        .welcome-image {
            width: 100%;
            height: auto;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .welcome-image:hover {
            transform: scale(1.02) rotate(1deg);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.15);
        }

        .image-placeholder {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, var(--button-blue), var(--navy-blue));
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            box-shadow: 0 15px 35px rgba(44, 107, 196, 0.2);
        }

        .image-placeholder i {
            font-size: 64px;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .alert-message {
            display: none;
            padding: 1rem 1.5rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .alert-message.success {
            background: linear-gradient(135deg, var(--success), #34d399);
            color: white;
        }

        .alert-message.error {
            background: linear-gradient(135deg, var(--error), #f87171);
            color: white;
        }

        .alert-message.warning {
            background: linear-gradient(135deg, var(--warning), #fbbf24);
            color: white;
        }

        .alert-message.info {
            background: linear-gradient(135deg, var(--button-blue), var(--navy-blue));
            color: white;
        }

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
            border-radius: 28px;
            animation: fadeIn 0.3s ease;
        }

        .spinner {
            width: 70px;
            height: 70px;
            border: 4px solid #f1f5f9;
            border-top: 4px solid var(--button-blue);
            border-right: 4px solid var(--accent-teal);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loader-text {
            margin-top: 1.5rem;
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--button-blue);
            animation: pulse 2s infinite;
        }

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

        @media (max-width: 1024px) {
            .login-card {
                max-width: 900px;
            }
        }

        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
                max-width: 500px;
            }

            .form-section::before {
                display: none;
            }

            .form-section {
                border-bottom: 1px solid var(--border-light);
            }

            .welcome-section {
                padding: 2rem;
            }

            .header-container {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
                padding: 1rem 0;
            }

            .logo-container {
                margin: 0 auto;
            }

            .about-btn {
                margin-top: 1rem;
            }
        }

        @media (max-width: 480px) {
            .main-container {
                padding: 1rem;
            }

            .form-section,
            .welcome-section {
                padding: 2rem 1.5rem;
            }

            .form-title {
                font-size: 1.75rem;
            }

            .welcome-title {
                font-size: 1.75rem;
            }

            .form-input {
                padding: 1rem 1rem 1rem 3.5rem;
            }

            .form-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .header-container {
                padding: 0.75rem;
            }
        }

        .footer {
            text-align: center;
            padding: 1.5rem;
            color: var(--text-light);
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-8px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(8px);
            }
        }

        .hidden {
            display: none !important;
        }

        .visible {
            display: flex !important;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-container {
            width: 90%;
            max-width: 500px;
            background: var(--card-bg);
            border-radius: 28px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--button-blue), var(--navy-blue));
            color: white;
            padding: 1.5rem;
            position: relative;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            background: var(--light-bg);
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }

        .modal-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .modal-btn-primary {
            background: var(--button-blue);
            color: white;
            flex: 1;
        }

        .modal-btn-primary:hover {
            background: var(--button-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44, 107, 196, 0.2);
        }

        .modal-btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 1px solid var(--border-light);
        }

        .modal-btn-secondary:hover {
            background: var(--light-bg);
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 2rem;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--text-light);
            position: relative;
        }

        .step.active {
            color: var(--button-blue);
        }

        .step.completed {
            color: var(--success);
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 2px solid currentColor;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .step.active .step-number {
            background: var(--button-blue);
            border-color: var(--button-blue);
            color: white;
        }

        .step.completed .step-number {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }

        .step-text {
            font-size: 0.85rem;
        }

        .resend-link {
            color: var(--button-blue);
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .resend-link:hover {
            color: var(--button-hover);
            text-decoration: underline;
        }

        .resend-link.disabled {
            color: var(--text-light);
            cursor: not-allowed;
            pointer-events: none;
        }

        .timer-text {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }
    </style>
</head>

<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
    </div>

    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <div class="logo-container">
                <img class="logo-img"
                    src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                    alt="Municipality of Paluan Logo" />
                <div class="header-title">
                    <h1>PROVINCE OF OCCIDENTAL MINDORO</h1>
                    <h1 class="municipality">MUNICIPALITY OF PALUAN</h1>
                    <p class="republic">REPUBLIC OF THE PHILIPPINES</p>
                </div>
            </div>

            <div class="about-btn">
                <a href="#">
                    <i class="fas fa-info-circle"></i>
                    <span>About Us</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-container">
        <div class="login-card">
            <!-- Loader Overlay -->
            <div class="loader-overlay" id="loaderOverlay">
                <div class="spinner"></div>
                <div class="loader-text">Authenticating...</div>
            </div>

            <!-- Login Form Section -->
            <div class="form-section" id="loginFormSection">
                <!-- Alert Message Container -->
                <div class="alert-message" id="alertMessage">
                    <i class="fas" id="alertIcon"></i>
                    <span id="alertText"></span>
                </div>

                <!-- Show session expired message if applicable -->
                <?php if ($sessionExpired): ?>
                    <div class="alert-message info visible" id="sessionExpiredAlert">
                        <i class="fas fa-info-circle"></i>
                        <span>Your session has expired. Please login again.</span>
                    </div>
                <?php endif; ?>

                <!-- Show logout message if applicable -->
                <?php if (isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
                    <div class="alert-message success visible" id="logoutAlert">
                        <i class="fas fa-check-circle"></i>
                        <span>You have been successfully logged out.</span>
                    </div>
                <?php endif; ?>

                <!-- Form Header -->
                <div class="form-header">
                    <div class="form-logo">
                        <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                            alt="HR Management System" />
                    </div>
                    <h2 class="form-title">HR Management System</h2>
                    <p class="form-subtitle">Sign in to your account</p>
                </div>

                <!-- Login Form -->
                <form id="loginForm">
                    <!-- Username Input -->
                    <div class="form-group">
                        <div class="input-wrapper">
                            <input type="text" id="username" name="username" class="form-input"
                                placeholder="Enter your username or email" required autocomplete="username">
                            <i class="fas fa-user input-icon"></i>
                        </div>
                        <div class="error-message-text" id="usernameError"></div>
                    </div>

                    <!-- Password Input -->
                    <div class="form-group">
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" class="form-input"
                                placeholder="Enter your password" required autocomplete="current-password">
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" id="passwordToggle">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="error-message-text" id="passwordError"></div>
                    </div>

                    <!-- Form Options -->
                    <div class="form-options">
                        <label class="remember-label" id="rememberLabel">
                            <div class="remember-checkbox" id="rememberCheckbox"></div>
                            <span>Remember me</span>
                            <input type="hidden" id="rememberMeInput" name="remember_me" value="false">
                        </label>
                        <a href="#" class="forgot-link" id="forgotPasswordLink">Forgot password?</a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="submit-btn" id="submitBtn">
                        <div class="btn-content">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>SIGN IN TO PORTAL</span>
                        </div>
                    </button>

                    <!-- Back to User Selection -->
                    <a href="../../admin/php/homepage.php"
                        class="w-full block text-center px-4 py-3 bg-gradient-to-r from-gray-500 to-gray-600 text-white font-semibold rounded-lg hover:from-gray-600 hover:to-gray-700 transition-all duration-200 shadow-md hover:shadow-lg mt-4">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to User Selection
                    </a>
                </form>
            </div>

            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <h3 class="welcome-title">Welcome Back!</h3>
                    <p class="welcome-text">We're glad to see you again. Sign in to access your HR Account and manage
                        your information.</p>

                    <div class="features-list">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <span>Employee Management</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <span>Performance Analytics</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-file-contract"></i>
                            </div>
                            <span>Document Management</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <span>Leave Management</span>
                        </div>
                    </div>

                    <!-- Image Container -->
                    <div class="image-container">
                        <img id="welcomeImage" class="welcome-image"
                            src="https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80"
                            alt="HR Management Dashboard" onerror="handleImageError(this)">

                        <div id="imagePlaceholder" class="image-placeholder hidden">
                            <i class="fas fa-users"></i>
                            <span>HR Dashboard Preview</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Forgot Password Modal -->
    <div class="modal-overlay" id="forgotPasswordModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2 id="modalTitle">Reset Password</h2>
                <button class="modal-close" id="closeModalBtn">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <!-- Step Indicator -->
                <div class="step-indicator" id="stepIndicator">
                    <div class="step active" id="step1">
                        <div class="step-number">1</div>
                        <div class="step-text">Email</div>
                    </div>
                    <div class="step" id="step2">
                        <div class="step-number">2</div>
                        <div class="step-text">Verify OTP</div>
                    </div>
                    <div class="step" id="step3">
                        <div class="step-number">3</div>
                        <div class="step-text">New Password</div>
                    </div>
                </div>

                <!-- Step 1: Email Input -->
                <div id="step1Content">
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Enter your registered email
                            address</label>
                        <div class="input-wrapper">
                            <input type="email" id="resetEmail" class="form-input" placeholder="Enter your email"
                                required>
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                        <div class="error-message-text" id="emailError"></div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">
                        We'll send a One-Time Password (OTP) to your email for verification.
                    </p>
                </div>

                <!-- Step 2: OTP Verification -->
                <div id="step2Content" style="display: none;">
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Enter the 6-digit OTP sent to <span id="maskedEmail"></span>
                        </label>
                        <div class="input-wrapper">
                            <input type="text" id="otpInput" class="form-input" placeholder="Enter 6-digit OTP"
                                maxlength="6" pattern="[0-9]{6}" inputmode="numeric">
                            <i class="fas fa-key input-icon"></i>
                        </div>
                        <div class="error-message-text" id="otpError"></div>
                    </div>

                    <!-- Development Mode Notice (will be shown when in dev mode) -->
                    <div id="devModeNotice"
                        class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 hidden" role="alert">
                        <p class="font-bold">Development Mode</p>
                        <p>Check your email configuration or use the debug OTP below.</p>
                    </div>

                    <div class="flex items-center justify-between mt-2">
                        <span class="text-sm text-gray-500">
                            <i class="far fa-clock"></i>
                            <span id="timer">15:00</span>
                        </span>
                        <a href="#" class="resend-link" id="resendOtp">Resend OTP</a>
                    </div>
                </div>

                <!-- Step 3: New Password -->
                <div id="step3Content" style="display: none;">
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="newPassword" class="form-input" placeholder="Enter new password"
                                minlength="8">
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" id="newPasswordToggle">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="error-message-text" id="newPasswordError"></div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="confirmPassword" class="form-input"
                                placeholder="Confirm new password" minlength="8">
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" id="confirmPasswordToggle">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="error-message-text" id="confirmPasswordError"></div>
                    </div>

                    <div class="text-sm text-gray-500 mt-2">
                        <p>Password must contain:</p>
                        <ul class="list-disc pl-5 mt-1">
                            <li id="lengthCheck">At least 8 characters</li>
                            <li id="uppercaseCheck">At least 1 uppercase letter</li>
                            <li id="lowercaseCheck">At least 1 lowercase letter</li>
                            <li id="numberCheck">At least 1 number</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="modal-btn modal-btn-secondary" id="backBtn">Back</button>
                <button class="modal-btn modal-btn-primary" id="nextBtn">Next</button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2024 Municipality of Paluan HR Management System. All rights reserved.</p>
        <p style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-light);">
            Secure login | Privacy protected | Official Government System
        </p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // DOM Elements
            const loginForm = document.getElementById('loginForm');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('passwordToggle');
            const loaderOverlay = document.getElementById('loaderOverlay');
            const alertMessage = document.getElementById('alertMessage');
            const alertIcon = document.getElementById('alertIcon');
            const alertText = document.getElementById('alertText');
            const usernameError = document.getElementById('usernameError');
            const passwordError = document.getElementById('passwordError');
            const rememberCheckbox = document.getElementById('rememberCheckbox');
            const rememberLabel = document.getElementById('rememberLabel');
            const rememberMeInput = document.getElementById('rememberMeInput');
            const forgotPasswordLink = document.getElementById('forgotPasswordLink');
            const submitBtn = document.getElementById('submitBtn');
            const welcomeImage = document.getElementById('welcomeImage');
            const imagePlaceholder = document.getElementById('imagePlaceholder');

            // Modal Elements
            const modal = document.getElementById('forgotPasswordModal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const backBtn = document.getElementById('backBtn');
            const nextBtn = document.getElementById('nextBtn');
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const step3 = document.getElementById('step3');
            const step1Content = document.getElementById('step1Content');
            const step2Content = document.getElementById('step2Content');
            const step3Content = document.getElementById('step3Content');
            const modalTitle = document.getElementById('modalTitle');
            const resetEmail = document.getElementById('resetEmail');
            const maskedEmail = document.getElementById('maskedEmail');
            const otpInput = document.getElementById('otpInput');
            const resendOtp = document.getElementById('resendOtp');
            const timer = document.getElementById('timer');
            const emailError = document.getElementById('emailError');
            const otpError = document.getElementById('otpError');
            const newPassword = document.getElementById('newPassword');
            const confirmPassword = document.getElementById('confirmPassword');
            const newPasswordToggle = document.getElementById('newPasswordToggle');
            const confirmPasswordToggle = document.getElementById('confirmPasswordToggle');
            const newPasswordError = document.getElementById('newPasswordError');
            const confirmPasswordError = document.getElementById('confirmPasswordError');
            const devModeNotice = document.getElementById('devModeNotice');

            // Password requirement check elements
            const lengthCheck = document.getElementById('lengthCheck');
            const uppercaseCheck = document.getElementById('uppercaseCheck');
            const lowercaseCheck = document.getElementById('lowercaseCheck');
            const numberCheck = document.getElementById('numberCheck');

            // State variables
            let currentStep = 1;
            let otpTimer;
            let timeLeft = 900; // 15 minutes in seconds
            let verifiedEmail = '';
            let isResendDisabled = false;

            // Alternative images for fallback
            const alternativeImages = [
                'https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1565688534245-05d6b5be184a?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1573164713714-d95e436ab8d6?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1551434678-e076c223a692?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
            ];

            let currentImageIndex = 0;
            let isPasswordVisible = false;

            // Initialize the application
            function init() {
                loadSavedCredentials();
                setupEventListeners();
                preloadImages();

                // Auto-hide session expired and logout alerts after 5 seconds
                const sessionExpiredAlert = document.getElementById('sessionExpiredAlert');
                const logoutAlert = document.getElementById('logoutAlert');

                if (sessionExpiredAlert) {
                    setTimeout(() => {
                        sessionExpiredAlert.classList.remove('visible');
                    }, 5000);
                }

                if (logoutAlert) {
                    setTimeout(() => {
                        logoutAlert.classList.remove('visible');
                    }, 5000);
                }
            }

            // Load saved credentials from localStorage
            function loadSavedCredentials() {
                if (localStorage.getItem('rememberCredentials') === 'true') {
                    rememberCheckbox.classList.add('checked');
                    rememberMeInput.value = 'true';
                    const savedUsername = localStorage.getItem('lastUsername');
                    if (savedUsername) {
                        usernameInput.value = savedUsername;
                        // Focus on password field if username is remembered
                        setTimeout(() => {
                            passwordInput.focus();
                        }, 100);
                    }
                }
            }

            // Setup all event listeners
            function setupEventListeners() {
                // Password toggle
                passwordToggle.addEventListener('click', togglePasswordVisibility);

                // Remember me
                rememberLabel.addEventListener('click', toggleRememberMe);

                // Forgot password link
                forgotPasswordLink.addEventListener('click', handleForgotPassword);

                // Form submission
                loginForm.addEventListener('submit', handleFormSubmit);

                // Input validation on blur
                usernameInput.addEventListener('blur', validateUsername);
                passwordInput.addEventListener('blur', validatePassword);

                // Input validation on input
                usernameInput.addEventListener('input', clearError);
                passwordInput.addEventListener('input', clearError);

                // Modal close button
                closeModalBtn.addEventListener('click', closeModal);

                // Modal back button
                backBtn.addEventListener('click', handleBack);

                // Modal next button
                nextBtn.addEventListener('click', handleNext);

                // Password toggles in modal
                newPasswordToggle.addEventListener('click', () => toggleModalPassword(newPassword, newPasswordToggle));
                confirmPasswordToggle.addEventListener('click', () => toggleModalPassword(confirmPassword, confirmPasswordToggle));

                // Password validation
                newPassword.addEventListener('input', validateNewPassword);
                confirmPassword.addEventListener('input', validateConfirmPassword);

                // Resend OTP
                resendOtp.addEventListener('click', handleResendOtp);

                // OTP input validation
                otpInput.addEventListener('input', (e) => {
                    e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 6);
                });
            }

            // Preload alternative images
            function preloadImages() {
                alternativeImages.forEach(src => {
                    const img = new Image();
                    img.src = src;
                });
            }

            // Handle image error
            function handleImageError(img) {
                console.log('Image failed to load:', img.src);

                currentImageIndex = (currentImageIndex + 1) % alternativeImages.length;

                if (currentImageIndex === 0) {
                    // We've tried all images, show placeholder
                    img.classList.add('hidden');
                    imagePlaceholder.classList.remove('hidden');
                } else {
                    // Try next image
                    img.src = alternativeImages[currentImageIndex];
                }
            }

            // Toggle password visibility
            function togglePasswordVisibility() {
                isPasswordVisible = !isPasswordVisible;
                passwordInput.type = isPasswordVisible ? 'text' : 'password';
                passwordToggle.innerHTML = isPasswordVisible ?
                    '<i class="fas fa-eye-slash"></i>' :
                    '<i class="fas fa-eye"></i>';
            }

            // Toggle modal password visibility
            function toggleModalPassword(inputField, toggleButton) {
                const isVisible = inputField.type === 'text';
                inputField.type = isVisible ? 'password' : 'text';
                toggleButton.innerHTML = isVisible ?
                    '<i class="fas fa-eye"></i>' :
                    '<i class="fas fa-eye-slash"></i>';
            }

            // Toggle remember me
            function toggleRememberMe() {
                rememberCheckbox.classList.toggle('checked');

                if (rememberCheckbox.classList.contains('checked')) {
                    rememberMeInput.value = 'true';
                    localStorage.setItem('rememberCredentials', 'true');
                    // Save username when remember me is checked
                    localStorage.setItem('lastUsername', usernameInput.value.trim());
                } else {
                    rememberMeInput.value = 'false';
                    localStorage.removeItem('rememberCredentials');
                    localStorage.removeItem('lastUsername');
                }
            }

            // Handle forgot password
            function handleForgotPassword(e) {
                e.preventDefault();
                openModal();
            }

            // Validate username
            function validateUsername() {
                const username = usernameInput.value.trim();
                if (!username) {
                    showInputError(usernameInput, usernameError, 'Username/Email is required');
                    return false;
                }

                clearInputError(usernameInput, usernameError);
                return true;
            }

            // Validate password
            function validatePassword() {
                const password = passwordInput.value;
                if (!password) {
                    showInputError(passwordInput, passwordError, 'Password is required');
                    return false;
                }

                clearInputError(passwordInput, passwordError);
                return true;
            }

            // Show input error
            function showInputError(input, errorElement, message) {
                input.classList.add('error');
                errorElement.textContent = message;
                errorElement.classList.add('show');
            }

            // Clear input error
            function clearInputError(input, errorElement) {
                input.classList.remove('error');
                errorElement.classList.remove('show');
                errorElement.textContent = '';
            }

            // Clear all errors
            function clearError() {
                clearInputError(usernameInput, usernameError);
                clearInputError(passwordInput, passwordError);
                hideAlert();
            }

            // Show alert message
            function showAlert(message, type = 'error') {
                alertText.textContent = message;
                alertMessage.className = 'alert-message ' + type;

                // Set appropriate icon
                switch (type) {
                    case 'success':
                        alertIcon.className = 'fas fa-check-circle';
                        break;
                    case 'warning':
                        alertIcon.className = 'fas fa-exclamation-triangle';
                        break;
                    case 'info':
                        alertIcon.className = 'fas fa-info-circle';
                        break;
                    default: // error
                        alertIcon.className = 'fas fa-exclamation-circle';
                }

                alertMessage.classList.add('visible');

                // Auto-hide messages after appropriate time
                const hideTime = type === 'error' ? 5000 :
                    type === 'warning' ? 7000 :
                        type === 'info' ? 5000 : 3000;

                setTimeout(hideAlert, hideTime);
            }

            // Hide alert message
            function hideAlert() {
                alertMessage.classList.remove('visible');
            }

            // Create celebration particles
            function createCelebrationParticles() {
                const colors = ['#10b981', '#2c6bc4', '#0d9488', '#f97316'];

                for (let i = 0; i < 20; i++) {
                    setTimeout(() => {
                        createParticle(colors[Math.floor(Math.random() * colors.length)]);
                    }, i * 100);
                }
            }

            // Create a single particle
            function createParticle(color) {
                const particle = document.createElement('div');
                particle.style.position = 'absolute';
                particle.style.width = '8px';
                particle.style.height = '8px';
                particle.style.background = color;
                particle.style.borderRadius = '50%';
                particle.style.pointerEvents = 'none';
                particle.style.zIndex = '100';
                particle.style.top = '50%';
                particle.style.left = '50%';
                particle.style.boxShadow = `0 0 12px ${color}`;

                document.querySelector('.login-card').appendChild(particle);

                const angle = Math.random() * Math.PI * 2;
                const distance = 150 + Math.random() * 150;
                const size = 0.5 + Math.random() * 1.5;
                const duration = 1000 + Math.random() * 1000;

                particle.animate([{
                    transform: 'translate(-50%, -50%) scale(1)',
                    opacity: 1
                },
                {
                    transform: `translate(
                            ${Math.cos(angle) * distance}px, 
                            ${Math.sin(angle) * distance}px
                        ) scale(${size})`,
                    opacity: 0
                }
                ], {
                    duration: duration,
                    easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
                }).onfinish = () => particle.remove();
            }

            // Handle form submission
            async function handleFormSubmit(e) {
                e.preventDefault();

                // Get form values
                const username = usernameInput.value.trim();
                const password = passwordInput.value;
                const rememberMe = rememberMeInput.value === 'true';

                // Save username to localStorage if remember me is checked
                if (rememberMe) {
                    localStorage.setItem('lastUsername', username);
                }

                // Validate inputs
                if (!validateUsername() || !validatePassword()) {
                    return;
                }

                // Disable submit button and show loader
                submitBtn.disabled = true;
                loaderOverlay.classList.remove('hidden');

                try {
                    // Send login request
                    const formData = new FormData();
                    formData.append('username', username);
                    formData.append('password', password);
                    formData.append('remember_me', rememberMe);

                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();
                    console.log('Login response:', data);

                    if (data.success) {
                        if (data.temp_password) {
                            showAlert('Temporary password detected. Please create a new permanent password.', 'info');
                            setTimeout(() => {
                                window.location.href = data.redirect || 'change_password.php';
                            }, 1500);
                        } else {
                            showAlert(data.message || 'Login successful!', 'success');

                            // ALWAYS redirect to homepage.php after successful login
                            setTimeout(() => {
                                window.location.href = data.redirect || 'homepage.php';
                            }, 500); // Reduced delay for faster redirect
                        }
                    } else {
                        // Handle login error
                        showAlert(data.message || 'Login failed', 'error');

                        // Shake animation
                        loginForm.classList.add('shake');
                        setTimeout(() => {
                            loginForm.classList.remove('shake');
                        }, 500);

                        // Clear password and focus on it
                        passwordInput.value = '';
                        passwordInput.focus();

                        // Re-enable submit button
                        submitBtn.disabled = false;
                        loaderOverlay.classList.add('hidden');
                    }

                } catch (error) {
                    console.error('Error:', error);
                    showAlert('Network error: ' + error.message, 'error');
                    submitBtn.disabled = false;
                    loaderOverlay.classList.add('hidden');
                }
            }

            // Modal Functions
            function openModal() {
                modal.classList.add('active');
                resetModal();
            }

            function closeModal() {
                modal.classList.remove('active');
                resetModal();
                // Clear any timers
                if (otpTimer) {
                    clearInterval(otpTimer);
                }
            }

            function resetModal() {
                currentStep = 1;
                updateSteps();

                step1Content.style.display = 'block';
                step2Content.style.display = 'none';
                step3Content.style.display = 'none';

                resetEmail.value = '';
                otpInput.value = '';
                newPassword.value = '';
                confirmPassword.value = '';

                emailError.classList.remove('show');
                otpError.classList.remove('show');
                newPasswordError.classList.remove('show');
                confirmPasswordError.classList.remove('show');

                backBtn.style.display = 'none';
                nextBtn.textContent = 'Next';

                // Reset password checks
                resetPasswordChecks();

                // Hide dev mode notice
                devModeNotice.classList.add('hidden');
            }

            function resetPasswordChecks() {
                lengthCheck.style.color = '';
                uppercaseCheck.style.color = '';
                lowercaseCheck.style.color = '';
                numberCheck.style.color = '';
            }

            function updateSteps() {
                // Reset all steps
                step1.classList.remove('active', 'completed');
                step2.classList.remove('active', 'completed');
                step3.classList.remove('active', 'completed');

                // Set current step
                if (currentStep === 1) {
                    step1.classList.add('active');
                } else if (currentStep === 2) {
                    step1.classList.add('completed');
                    step2.classList.add('active');
                } else if (currentStep === 3) {
                    step1.classList.add('completed');
                    step2.classList.add('completed');
                    step3.classList.add('active');
                }
            }

            function handleBack() {
                if (currentStep === 2) {
                    currentStep = 1;
                    step1Content.style.display = 'block';
                    step2Content.style.display = 'none';
                    step3Content.style.display = 'none';
                    backBtn.style.display = 'none';
                    nextBtn.textContent = 'Next';
                    updateSteps();

                    // Clear OTP timer
                    if (otpTimer) {
                        clearInterval(otpTimer);
                    }
                } else if (currentStep === 3) {
                    currentStep = 2;
                    step2Content.style.display = 'block';
                    step3Content.style.display = 'none';
                    nextBtn.textContent = 'Verify';
                    updateSteps();
                }
            }

            async function handleNext() {
                if (currentStep === 1) {
                    // Validate email and send OTP
                    const email = resetEmail.value.trim();

                    if (!email || !isValidEmail(email)) {
                        emailError.textContent = 'Please enter a valid email address';
                        emailError.classList.add('show');
                        return;
                    }

                    emailError.classList.remove('show');

                    // Show loader
                    loaderOverlay.querySelector('.loader-text').textContent = 'Sending OTP...';
                    loaderOverlay.classList.remove('hidden');

                    try {
                        const formData = new FormData();
                        formData.append('action', 'send_otp');
                        formData.append('email', email);

                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            verifiedEmail = email;
                            maskedEmail.textContent = maskEmail(email);

                            // Move to step 2
                            currentStep = 2;
                            step1Content.style.display = 'none';
                            step2Content.style.display = 'block';
                            step3Content.style.display = 'none';
                            backBtn.style.display = 'block';
                            nextBtn.textContent = 'Verify';
                            updateSteps();

                            // Start OTP timer
                            startOtpTimer();

                            // Show development mode notice if applicable
                            if (data.development_mode) {
                                devModeNotice.classList.remove('hidden');
                                if (data.debug_otp) {
                                    console.log('🔐 Development OTP:', data.debug_otp);
                                    // Show OTP in alert for development
                                    showAlert(`Development OTP: ${data.debug_otp}`, 'info');
                                }
                            } else {
                                showAlert('OTP has been sent to your email', 'success');
                            }
                        } else {
                            showAlert(data.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showAlert('Failed to send OTP. Please try again.', 'error');
                    } finally {
                        loaderOverlay.classList.add('hidden');
                        loaderOverlay.querySelector('.loader-text').textContent = 'Authenticating...';
                    }

                } else if (currentStep === 2) {
                    // Verify OTP
                    const otp = otpInput.value.trim();

                    if (!otp || otp.length !== 6 || !/^\d+$/.test(otp)) {
                        otpError.textContent = 'Please enter a valid 6-digit OTP';
                        otpError.classList.add('show');
                        return;
                    }

                    otpError.classList.remove('show');

                    loaderOverlay.classList.remove('hidden');

                    try {
                        const formData = new FormData();
                        formData.append('action', 'verify_otp');
                        formData.append('email', verifiedEmail);
                        formData.append('otp', otp);

                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Move to step 3
                            currentStep = 3;
                            step2Content.style.display = 'none';
                            step3Content.style.display = 'block';
                            nextBtn.textContent = 'Reset Password';
                            updateSteps();

                            // Clear OTP timer
                            if (otpTimer) {
                                clearInterval(otpTimer);
                            }

                            showAlert('OTP verified successfully', 'success');
                        } else {
                            showAlert(data.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showAlert('Failed to verify OTP. Please try again.', 'error');
                    } finally {
                        loaderOverlay.classList.add('hidden');
                    }

                } else if (currentStep === 3) {
                    // Reset password
                    const password = newPassword.value;
                    const confirm = confirmPassword.value;

                    // Validate password
                    if (!validatePasswordStrength(password)) {
                        showAlert('Password does not meet requirements', 'error');
                        return;
                    }

                    if (password !== confirm) {
                        confirmPasswordError.textContent = 'Passwords do not match';
                        confirmPasswordError.classList.add('show');
                        return;
                    }

                    confirmPasswordError.classList.remove('show');

                    loaderOverlay.classList.remove('hidden');

                    try {
                        const formData = new FormData();
                        formData.append('action', 'reset_password');
                        formData.append('email', verifiedEmail);
                        formData.append('new_password', password);
                        formData.append('confirm_password', confirm);

                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            showAlert(data.message, 'success');

                            // Close modal after success
                            setTimeout(() => {
                                closeModal();
                            }, 2000);
                        } else {
                            showAlert(data.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showAlert('Failed to reset password. Please try again.', 'error');
                    } finally {
                        loaderOverlay.classList.add('hidden');
                    }
                }
            }

            function isValidEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }

            function maskEmail(email) {
                const [local, domain] = email.split('@');
                const maskedLocal = local.charAt(0) + '*'.repeat(local.length - 2) + local.charAt(local.length - 1);
                return maskedLocal + '@' + domain;
            }

            function startOtpTimer() {
                timeLeft = 900; // 15 minutes
                updateTimerDisplay();

                if (otpTimer) {
                    clearInterval(otpTimer);
                }

                otpTimer = setInterval(() => {
                    timeLeft--;
                    updateTimerDisplay();

                    if (timeLeft <= 0) {
                        clearInterval(otpTimer);
                        timer.textContent = '00:00';
                        isResendDisabled = true;
                        resendOtp.classList.add('disabled');
                    }
                }, 1000);

                isResendDisabled = false;
                resendOtp.classList.remove('disabled');
            }

            function updateTimerDisplay() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }

            async function handleResendOtp(e) {
                e.preventDefault();

                if (isResendDisabled) {
                    return;
                }

                loaderOverlay.classList.remove('hidden');
                loaderOverlay.querySelector('.loader-text').textContent = 'Resending OTP...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'send_otp');
                    formData.append('email', verifiedEmail);

                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Restart timer
                        startOtpTimer();

                        if (data.development_mode && data.debug_otp) {
                            console.log('🔐 New Development OTP:', data.debug_otp);
                            showAlert(`Development OTP: ${data.debug_otp}`, 'info');
                        } else {
                            showAlert('OTP resent successfully', 'success');
                        }
                    } else {
                        showAlert(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showAlert('Failed to resend OTP. Please try again.', 'error');
                } finally {
                    loaderOverlay.classList.add('hidden');
                    loaderOverlay.querySelector('.loader-text').textContent = 'Authenticating...';
                }
            }

            function validateNewPassword() {
                const password = newPassword.value;

                // Check requirements
                const hasLength = password.length >= 8;
                const hasUppercase = /[A-Z]/.test(password);
                const hasLowercase = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);

                // Update check indicators
                lengthCheck.style.color = hasLength ? '#10b981' : '#ef4444';
                uppercaseCheck.style.color = hasUppercase ? '#10b981' : '#ef4444';
                lowercaseCheck.style.color = hasLowercase ? '#10b981' : '#ef4444';
                numberCheck.style.color = hasNumber ? '#10b981' : '#ef4444';

                if (!hasLength || !hasUppercase || !hasLowercase || !hasNumber) {
                    newPasswordError.textContent = 'Password does not meet requirements';
                    newPasswordError.classList.add('show');
                    return false;
                }

                newPasswordError.classList.remove('show');
                return true;
            }

            function validateConfirmPassword() {
                const password = newPassword.value;
                const confirm = confirmPassword.value;

                if (password !== confirm) {
                    confirmPasswordError.textContent = 'Passwords do not match';
                    confirmPasswordError.classList.add('show');
                    return false;
                }

                confirmPasswordError.classList.remove('show');
                return true;
            }

            function validatePasswordStrength(password) {
                const hasLength = password.length >= 8;
                const hasUppercase = /[A-Z]/.test(password);
                const hasLowercase = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);

                return hasLength && hasUppercase && hasLowercase && hasNumber;
            }

            // Initialize the application
            init();

            // Make handleImageError available globally for onerror attribute
            window.handleImageError = function (img) {
                handleImageError(img);
            };
        });
    </script>
</body>

</html>