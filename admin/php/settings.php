<?php
// Set session security headers BEFORE any output
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// Start session
session_start();

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Check if either admin or user is logged in
if (
    (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) ||
    (empty($_SESSION['admin_id']) && empty($_SESSION['user_id']))
) {
    header('Location: login.php');
    exit();
}

// Determine login type and set variables
if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    // ADMIN LOGIN
    $is_admin_login = true;
    $is_user_login = false;
    $user_id = $_SESSION['admin_id'];
    $user_name = $_SESSION['admin_name'] ?? 'Administrator';
    $user_email = $_SESSION['admin_email'] ?? 'admin@paluan.gov.ph';
    $is_admin = true; // All admins have full access
    $login_type = 'admin';

    // For admin login, we need to handle profile differently since admins table has different structure
    $admin_profile = [
        'full_name' => $user_name,
        'email' => $user_email,
        'position' => $_SESSION['admin_position'] ?? '',
        'department' => $_SESSION['admin_department'] ?? ''
    ];
} else {
    // USER LOGIN (employees/staff)
    $is_admin_login = false;
    $is_user_login = true;
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['user_name'] ?? 'User';
    $user_email = $_SESSION['user_email'] ?? 'user@paluan.gov.ph';
    $user_role = $_SESSION['user_role'] ?? 'EMPLOYEE';
    $is_admin = in_array($user_role, ['ADMIN', 'HR_MANAGER', 'HR_STAFF']);
    $login_type = 'user';
}

// Check if user has admin access
if (!$is_admin) {
    // Non-admin users can only access profile settings
    if (isset($_GET['tab']) && !in_array($_GET['tab'], ['profile', 'security'])) {
        header('Location: settings.php?tab=profile');
        exit();
    }
}

// Logout functionality
if (isset($_GET['logout'])) {
    // Log the logout
    require_once '../conn.php';
    $conn = new mysqli("localhost", "root", "", "hrms_paluan");
    if (!$conn->connect_error) {
        // Log audit trail
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $action_type = 'logout';
        $description = 'User logged out';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt->bind_param("issss", $user_id, $action_type, $description, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }

    // Clear all session variables
    $_SESSION = array();

    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();

    // Clear remember me cookie if exists
    if (isset($_COOKIE['remember_user'])) {
        setcookie('remember_user', '', time() - 3600, "/", "", true, true);
    }

    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "hrms_paluan"; // Changed from hrmo_paluan to hrms_paluan

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include mailer.php for email functionality
$mailerPath = __DIR__ . '/mailer.php';
if (file_exists($mailerPath)) {
    require_once $mailerPath;
} else {
    // Try alternative path
    $mailerPath = '../mailer.php';
    if (file_exists($mailerPath)) {
        require_once $mailerPath;
    }
}

// Function to log audit trail
function logAuditTrail($conn, $user_id, $action_type, $description): void
{
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $action_type, $description, $ip_address, $user_agent);

    $stmt->execute();
    $stmt->close();
}

// Initialize messages
$success_message = "";
$error_message = "";

// Function to validate password
function validatePassword($password)
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    if (!preg_match('/[\W_]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }

    return $errors;
}

// Function to create employee record based on employment type
function createEmployeeRecord($conn, $user_id, $full_name, $employee_id, $employment_type, $department, $position)
{
    $name_parts = explode(' ', trim($full_name), 2);
    $first_name = $name_parts[0];
    $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

    switch ($employment_type) {
        case 'permanent':
            $table = 'permanent_employees';
            break;
        case 'job_order':
            $table = 'job_order_employees';
            break;
        case 'contract_of_service':
            $table = 'contract_of_service_employees';
            break;
        default:
            $table = 'employees'; // fallback table
    }

    // Check if table exists
    $check_table = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check_table->num_rows > 0) {
        // Create basic employee record
        $sql = "INSERT INTO $table (
                user_id,
                employee_id,
                first_name,
                last_name,
                full_name,
                department,
                position,
                employment_type,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param(
                "isssssss",
                $user_id,
                $employee_id,
                $first_name,
                $last_name,
                $full_name,
                $department,
                $position,
                $employment_type
            );
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Handle Send Verification Invitation
if (isset($_POST['send_verification_invitation']) && $is_admin) {
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

        // Generate temporary password (8 characters for user-friendly)
        $temp_password = generateTemporaryPassword(8);

        // Set expiration for temp password (24 hours)
        $temp_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Hash the temporary password
        $hashed_temp_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // Update user with temporary password and expiry
        $sql = "UPDATE users SET 
                password_hash = ?, 
                temporary_password_expiry = ?,
                password_is_temporary = 1,
                must_change_password = 1,
                is_active = 1,
                is_verified = 1,
                last_verification_sent = NOW(), 
                updated_at = NOW() 
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $hashed_temp_password, $temp_expiry, $invite_user_id);

        if ($stmt->execute()) {
            $success_message = "Verification invitation sent successfully to " . htmlspecialchars($user['full_name']) . "!";
            logAuditTrail($conn, $user_id, 'invite', "Sent verification invitation to: " . $user['full_name']);

            // Generate login URL
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
            $login_url = $base_url . dirname($_SERVER['PHP_SELF']) . "/login.php";

            // Clean up the URL (remove double slashes)
            $login_url = preg_replace('/([^:])(\/{2,})/', '$1/', $login_url);

            // Send verification email using your mailer function
            $email_sent = sendVerificationInvitationEmail($conn, $user['email'], $user['full_name'], $temp_password, $login_url);

            if ($email_sent) {
                $success_message .= " Email with temporary credentials has been sent.";
            } else {
                $success_message .= " <strong>BUT email failed to send.</strong> Temporary password: <code>$temp_password</code>";
            }

            $stmt->close();
        } else {
            $error_message = "Error sending invitation: " . $conn->error;
        }
    } else {
        $error_message = "User not found!";
    }
}

// Handle Send Verification Invitation
if (isset($_POST['send_verification_invitation']) && $is_admin) {
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

        // Generate temporary password (8 characters for user-friendly)
        $temp_password = generateTemporaryPassword(8);

        // Set expiration for temp password (24 hours)
        $temp_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Hash the temporary password
        $hashed_temp_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // Update user with temporary password and expiry
        $sql = "UPDATE users SET 
                password_hash = ?, 
                temporary_password_expiry = ?,
                password_is_temporary = 1,
                must_change_password = 1,
                is_active = 1,
                is_verified = 1,
                account_status = 'ACTIVE',  
                status = 'approved',        
                last_verification_sent = NOW(), 
                updated_at = NOW() 
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $hashed_temp_password, $temp_expiry, $invite_user_id);

        if ($stmt->execute()) {
            // Generate login URL
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
            $login_url = $base_url . dirname($_SERVER['PHP_SELF']) . "/login.php";

            // Clean up the URL (remove double slashes)
            $login_url = preg_replace('/([^:])(\/{2,})/', '$1/', $login_url);

            // Use the Mailer class from mailer.php
            if (class_exists('Mailer')) {
                try {
                    $mailer = new Mailer();

                    // Create email subject and body
                    $subject = "Your HRMS Account is Ready - Login Credentials";

                    $body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <title>HRMS Account Credentials</title>
                        <style>
                            body { 
                                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                                line-height: 1.6; 
                                color: #333; 
                                background-color: #f8fafc;
                                margin: 0;
                                padding: 20px;
                            }
                            .container { 
                                max-width: 650px; 
                                margin: 0 auto; 
                                background: white;
                                border-radius: 12px;
                                overflow: hidden;
                                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                            }
                            .header { 
                                background: linear-gradient(135deg, #0235a2 0%, #1e3a8a 100%); 
                                color: white; 
                                padding: 35px 30px;
                                text-align: center; 
                                border-bottom: 5px solid #2c6bc4;
                            }
                            .header h1 {
                                margin: 0;
                                font-size: 28px;
                                font-weight: 700;
                            }
                            .header .subtitle {
                                font-size: 16px;
                                opacity: 0.9;
                                margin-top: 10px;
                            }
                            .content { 
                                padding: 40px 35px; 
                            }
                            .credentials-box { 
                                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); 
                                border: 2px solid #2c6bc4; 
                                padding: 30px; 
                                margin: 25px 0; 
                                border-radius: 12px;
                                border-left: 5px solid #2c6bc4;
                            }
                            .password-display {
                                background: #1e293b;
                                color: #ffffff;
                                padding: 15px;
                                border-radius: 8px;
                                font-family: 'Courier New', monospace;
                                font-size: 20px;
                                letter-spacing: 2px;
                                text-align: center;
                                margin: 15px 0;
                                border: 2px solid #2c6bc4;
                                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                            }
                            .login-button {
                                display: inline-block;
                                background: linear-gradient(135deg, #2c6bc4 0%, #1e4a8a 100%); 
                                color: white; 
                                padding: 18px 40px;
                                text-decoration: none; 
                                border-radius: 10px; 
                                font-weight: 700;
                                font-size: 18px;
                                margin: 25px 0;
                            }
                            .warning-box { 
                                background: linear-gradient(135deg, #fef3c7 0%, #fef9c3 100%); 
                                border-left: 5px solid #f59e0b; 
                                padding: 22px; 
                                margin: 25px 0; 
                                border-radius: 8px;
                            }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>HR Management System Account</h1>
                                <div class='subtitle'>Municipality of Paluan, Occidental Mindoro</div>
                            </div>
                            
                            <div class='content'>
                                <h2>Hello " . htmlspecialchars($user['full_name']) . ",</h2>
                                
                                <p>Welcome to the HR Management System of Paluan! Your employee account has been successfully created and is ready for use.</p>
                                
                                <div class='warning-box'>
                                    <h4><i class='fas fa-shield-alt'></i> Important Security Notice</h4>
                                    <p>You have been provided with temporary credentials. For security reasons, you <strong>must</strong> change your password immediately after your first login.</p>
                                </div>
                                
                                <div class='credentials-box'>
                                    <h3><i class='fas fa-key'></i> Your Login Credentials</h3>
                                    
                                    <p><strong>Login URL:</strong> " . htmlspecialchars($login_url) . "</p>
                                    <p><strong>Email/Username:</strong> " . htmlspecialchars($user['email']) . "</p>
                                    <p><strong>Temporary Password:</strong></p>
                                    <div class='password-display'>" . htmlspecialchars($temp_password) . "</div>
                                    <p><em>Expires in 24 hours</em></p>
                                </div>
                                
                                <div style='text-align: center;'>
                                    <a href='" . htmlspecialchars($login_url) . "' class='login-button'>
                                        <i class='fas fa-sign-in-alt'></i> Go to Login Page
                                    </a>
                                </div>
                                
                                <p><strong>Need Help?</strong> Contact HR Department: hrmo@paluan.gov.ph</p>
                                
                                <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #64748b; font-size: 14px;'>
                                    <p>Best regards,<br>
                                    <strong>Human Resource Management Office</strong><br>
                                    Municipality of Paluan, Occidental Mindoro</p>
                                </div>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";

                    // Send email using Mailer class
                    $email_result = $mailer->sendGeneralEmail(
                        $user['email'],
                        $user['full_name'],
                        $subject,
                        $body
                    );

                    if ($email_result['success'] && $email_result['email_sent']) {
                        $success_message = "Verification invitation sent successfully to " . htmlspecialchars($user['full_name']) . "! Email has been delivered.";
                        logAuditTrail($conn, $user_id, 'invite', "Sent verification invitation via email to: " . $user['full_name']);
                    } else {
                        // Display credentials on screen if email failed
                        $credentials_display = "
                        <div class='credentials-display' style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>
                            <h4>Credentials for " . htmlspecialchars($user['full_name']) . " (Email failed to send):</h4>
                            <p><strong>Email/Username:</strong> " . htmlspecialchars($user['email']) . "</p>
                            <p><strong>Temporary Password:</strong> <code style='background: #333; color: #fff; padding: 5px 10px; border-radius: 4px;'>$temp_password</code></p>
                            <p><strong>Login URL:</strong> $login_url</p>
                            <p><em>Please provide these credentials to the user manually.</em></p>
                        </div>";

                        $success_message = "Verification invitation prepared successfully!<br>" . $credentials_display;
                        logAuditTrail($conn, $user_id, 'invite', "Created credentials for: " . $user['full_name'] . " (email failed)");
                    }

                } catch (Exception $e) {
                    // Log error and show credentials
                    error_log("Mailer error: " . $e->getMessage());

                    $credentials_display = "
                    <div class='credentials-display' style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>
                        <h4>Credentials for " . htmlspecialchars($user['full_name']) . " (Mailer error):</h4>
                        <p><strong>Email/Username:</strong> " . htmlspecialchars($user['email']) . "</p>
                        <p><strong>Temporary Password:</strong> <code style='background: #333; color: #fff; padding: 5px 10px; border-radius: 4px;'>$temp_password</code></p>
                        <p><strong>Login URL:</strong> $login_url</p>
                        <p><em>Error: " . htmlspecialchars($e->getMessage()) . "</em></p>
                        <p><em>Please provide these credentials to the user manually.</em></p>
                    </div>";

                    $success_message = "Verification invitation prepared successfully!<br>" . $credentials_display;
                    logAuditTrail($conn, $user_id, 'invite', "Created credentials for: " . $user['full_name'] . " (mailer error)");
                }
            } else {
                // Fallback to original function if Mailer class not found
                $email_sent = sendVerificationInvitationEmail($conn, $user['email'], $user['full_name'], $temp_password, $login_url);

                if ($email_sent) {
                    $success_message = "Verification invitation sent successfully to " . htmlspecialchars($user['full_name']) . "!";
                    logAuditTrail($conn, $user_id, 'invite', "Sent verification invitation to: " . $user['full_name']);
                } else {
                    $credentials_display = "
                    <div class='credentials-display' style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>
                        <h4>Credentials for " . htmlspecialchars($user['full_name']) . ":</h4>
                        <p><strong>Email/Username:</strong> " . htmlspecialchars($user['email']) . "</p>
                        <p><strong>Temporary Password:</strong> <code style='background: #333; color: #fff; padding: 5px 10px; border-radius: 4px;'>$temp_password</code></p>
                        <p><strong>Login URL:</strong> $login_url</p>
                        <p><em>Please provide these credentials to the user manually.</em></p>
                    </div>";

                    $success_message = "Verification invitation prepared successfully!<br>" . $credentials_display;
                    logAuditTrail($conn, $user_id, 'invite', "Created credentials for: " . $user['full_name'] . " (fallback method)");
                }
            }

            $stmt->close();
        } else {
            $error_message = "Error sending invitation: " . $conn->error;
        }
    } else {
        $error_message = "User not found!";
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. System Settings
    if (isset($_POST['update_system_settings']) && $is_admin) {
        $system_name = $conn->real_escape_string($_POST['system_name']);
        $timezone = $conn->real_escape_string($_POST['timezone']);
        $date_format = $conn->real_escape_string($_POST['date_format']);
        $time_format = $conn->real_escape_string($_POST['time_format']);
        $pagination_limit = (int) $_POST['pagination_limit'];
        $session_timeout = (int) $_POST['session_timeout'];
        $enable_registration = isset($_POST['enable_registration']) ? 1 : 0;
        $enable_remember_me = isset($_POST['enable_remember_me']) ? 1 : 0;
        $enable_debug = isset($_POST['enable_debug']) ? 1 : 0;

        $sql = "UPDATE system_settings SET 
                system_name = '$system_name',
                timezone = '$timezone',
                date_format = '$date_format',
                time_format = '$time_format',
                pagination_limit = $pagination_limit,
                session_timeout = $session_timeout,
                enable_registration = $enable_registration,
                enable_remember_me = $enable_remember_me,
                enable_debug = $enable_debug,
                updated_at = NOW()
                WHERE id = 1";

        if ($conn->query($sql) === TRUE) {
            $success_message = "System settings updated successfully!";
            logAuditTrail($conn, $user_id, 'update', 'Updated system settings');
        } else {
            $error_message = "Error updating system settings: " . $conn->error;
        }
    }

    // 2. Email Settings
    if (isset($_POST['update_email_settings']) && $is_admin) {
        $smtp_host = $conn->real_escape_string($_POST['smtp_host']);
        $smtp_port = (int) $_POST['smtp_port'];
        $smtp_username = $conn->real_escape_string($_POST['smtp_username']);
        $smtp_password = $conn->real_escape_string($_POST['smtp_password']);
        $smtp_encryption = $conn->real_escape_string($_POST['smtp_encryption']);
        $from_email = $conn->real_escape_string($_POST['from_email']);
        $from_name = $conn->real_escape_string($_POST['from_name']);
        $enable_email_notifications = isset($_POST['enable_email_notifications']) ? 1 : 0;

        $sql = "UPDATE email_settings SET 
                smtp_host = '$smtp_host',
                smtp_port = $smtp_port,
                smtp_username = '$smtp_username',
                smtp_password = '$smtp_password',
                smtp_encryption = '$smtp_encryption',
                from_email = '$from_email',
                from_name = '$from_name',
                enable_email_notifications = $enable_email_notifications,
                updated_at = NOW()
                WHERE id = 1";

        if ($conn->query($sql) === TRUE) {
            $success_message = "Email settings updated successfully!";
            logAuditTrail($conn, $user_id, 'update', 'Updated email settings');
        } else {
            $error_message = "Error updating email settings: " . $conn->error;
        }
    }

    // 3. User Profile
    if (isset($_POST['update_profile'])) {
        $first_name = $conn->real_escape_string($_POST['first_name']);
        $middle_name = $conn->real_escape_string($_POST['middle_name'] ?? '');
        $last_name = $conn->real_escape_string($_POST['last_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone'] ?? '');
        $department = $conn->real_escape_string($_POST['department'] ?? '');
        $position = $conn->real_escape_string($_POST['position'] ?? '');

        // Create full name
        $full_name = trim("$first_name " . ($middle_name ? "$middle_name " : "") . $last_name);

        // Check if email already exists (excluding current user)
        $check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Email already exists! Please use a different email.";
            $check_stmt->close();
        } else {
            $check_stmt->close();

            $sql = "UPDATE users SET 
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                full_name = ?,
                email = ?,
                phone = ?,
                department = ?,
                position = ?,
                updated_at = NOW()
                WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssssssi",
                $first_name,
                $middle_name,
                $last_name,
                $full_name,
                $email,
                $phone,
                $department,
                $position,
                $user_id
            );

            if ($stmt->execute()) {
                // Update session variables
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_email'] = $email;
                $success_message = "Profile updated successfully!";
                logAuditTrail($conn, $user_id, 'update', 'Updated user profile');
            } else {
                $error_message = "Error updating profile: " . $conn->error;
            }
            $stmt->close();
        }
    }

    // 4. Password Change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate passwords
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required!";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match!";
        } elseif (strlen($new_password) < 8) {
            $error_message = "Password must be at least 8 characters long!";
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $error_message = "Password must contain at least one uppercase letter!";
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $error_message = "Password must contain at least one lowercase letter!";
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $error_message = "Password must contain at least one number!";
        } elseif (!preg_match('/[\W_]/', $new_password)) {
            $error_message = "Password must contain at least one special character!";
        } else {
            // Get current password hash
            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (password_verify($current_password, $row['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    $sql = "UPDATE users SET 
                            password = ?,
                            password_changed_at = NOW(),
                            updated_at = NOW()
                            WHERE id = ?";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $hashed_password, $user_id);

                    if ($stmt->execute()) {
                        $success_message = "Password changed successfully!";
                        logAuditTrail($conn, $user_id, 'update', 'Changed password');

                        // Send email notification
                        if (function_exists('sendEmailNotification')) {
                            $email_subject = "Password Changed Successfully";
                            $email_message = "Hello $user_name,<br><br>Your password has been successfully changed.<br><br>If you did not make this change, please contact the system administrator immediately.<br><br>Best regards,<br>HRMS Team";
                            sendEmailNotification($conn, $user_email, $email_subject, $email_message);
                        }
                    } else {
                        $error_message = "Error changing password: " . $conn->error;
                    }
                } else {
                    $error_message = "Current password is incorrect!";
                }
            } else {
                $error_message = "User not found!";
            }
            $stmt->close();
        }
    }

    // 5. Backup Settings
    if (isset($_POST['create_backup']) && $is_admin) {
        $backup_type = $conn->real_escape_string($_POST['backup_type']);
        $backup_notes = $conn->real_escape_string($_POST['backup_notes'] ?? '');

        // Generate backup file name
        $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '_' . $backup_type . '.sql';
        $backup_path = '../backups/' . $backup_file;

        // Create backups directory if it doesn't exist
        if (!is_dir('../backups')) {
            mkdir('../backups', 0755, true);
        }

        // Perform database backup
        $command = "mysqldump --user={$username} --password={$password} --host={$host} {$database} > {$backup_path}";
        exec($command, $output, $return_var);

        if ($return_var === 0) {
            $file_size = filesize($backup_path);

            $sql = "INSERT INTO backups (backup_type, backup_notes, file_path, file_size, created_by, status) 
                    VALUES (?, ?, ?, ?, ?, 'completed')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $backup_type, $backup_notes, $backup_path, $file_size, $user_id);

            if ($stmt->execute()) {
                $success_message = "Backup created successfully! File: " . $backup_file;
                logAuditTrail($conn, $user_id, 'create', 'Created backup: ' . $backup_file);
            } else {
                $error_message = "Error saving backup record: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Error creating backup file!";
        }
    }

    // 6. User Management - Add new user with employment types
    if (isset($_POST['add_user']) && $is_admin) {
        $new_full_name = $conn->real_escape_string($_POST['new_full_name']);
        $new_email = $conn->real_escape_string($_POST['new_email']);
        $new_role = $conn->real_escape_string($_POST['new_role']);
        $employment_type = $conn->real_escape_string($_POST['employment_type'] ?? 'permanent');
        $employee_id = $conn->real_escape_string($_POST['employee_id'] ?? '');
        $department = $conn->real_escape_string($_POST['department'] ?? '');
        $position = $conn->real_escape_string($_POST['position'] ?? '');

        // Generate temporary password
        $temp_password = generateTemporaryPassword(8);
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // Set expiration for temp password (24 hours)
        $temp_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Parse full name for first and last name
        $name_parts = explode(' ', trim($new_full_name), 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

        // Generate username from email (before @ symbol)
        $username_parts = explode('@', $new_email);
        $username = strtolower($username_parts[0]);

        // Make username unique
        $original_username = $username;
        $counter = 1;

        while (true) {
            $checkUserStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $checkUserStmt->bind_param("s", $username);
            $checkUserStmt->execute();
            $checkUserResult = $checkUserStmt->get_result();
            $checkUserStmt->close();

            if ($checkUserResult->num_rows === 0) {
                break;
            }

            $username = $original_username . $counter;
            $counter++;
        }

        // Generate employee ID if not provided
        if (empty($employee_id)) {
            $prefix = match ($employment_type) {
                'permanent' => 'PERM',
                'job_order' => 'JO',
                'contract_of_service' => 'COS',
                default => 'EMP'
            };
            $employee_id = $prefix . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        // Check if employee ID already exists
        $checkEmpStmt = $conn->prepare("SELECT id FROM users WHERE employee_id = ?");
        $checkEmpStmt->bind_param("s", $employee_id);
        $checkEmpStmt->execute();
        $checkEmpResult = $checkEmpStmt->get_result();

        if ($checkEmpResult->num_rows > 0) {
            $error_message = "Employee ID already exists! Please use a different ID.";
            $checkEmpStmt->close();
        } else {
            $checkEmpStmt->close();

            // Check if email already exists
            $checkEmailStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $checkEmailStmt->bind_param("s", $new_email);
            $checkEmailStmt->execute();
            $checkEmailResult = $checkEmailStmt->get_result();

            if ($checkEmailResult->num_rows > 0) {
                $error_message = "User with this email already exists!";
                $checkEmailStmt->close();
            } else {
                $checkEmailStmt->close();

                // Set access level based on role
                $access_level = 'restricted';
                if ($new_role === 'admin') {
                    $access_level = 'full';
                } elseif ($new_role === 'manager') {
                    $access_level = 'elevated';
                }

                // Prepare SQL statement for users table
                $sql = "INSERT INTO users (
                username, 
                email, 
                password_hash, 
                first_name, 
                last_name, 
                full_name,
                role, 
                access_level, 
                account_status, 
                employee_id,
                employment_type,
                department,
                position,
                is_active,
                is_verified,
                password_is_temporary,
                temporary_password_expiry,
                must_change_password,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $account_status = 'active';
                    $is_active = 1;
                    $is_verified = 1;
                    $password_is_temporary = 1;
                    $must_change_password = 1;

                    $stmt->bind_param(
                        "sssssssssssssiiiss",
                        $username,
                        $new_email,
                        $hashed_password,
                        $first_name,
                        $last_name,
                        $new_full_name,
                        $new_role,
                        $access_level,
                        $account_status,
                        $employee_id,
                        $employment_type,
                        $department,
                        $position,
                        $is_active,
                        $is_verified,
                        $password_is_temporary,
                        $temp_expiry,
                        $must_change_password
                    );

                    if ($stmt->execute()) {
                        $new_user_id = $stmt->insert_id;
                        $success_message = "User added successfully!<br>";
                        $success_message .= "• Username: $username<br>";
                        $success_message .= "• Employee ID: $employee_id<br>";
                        $success_message .= "• Employment Type: " . ucfirst(str_replace('_', ' ', $employment_type)) . "<br>";
                        $success_message .= "• Temporary password: $temp_password<br>";

                        logAuditTrail($conn, $user_id, 'create', "Added new user: $new_full_name ($employment_type)");

                        // Create employee record in appropriate table based on employment type
                        createEmployeeRecord($conn, $new_user_id, $new_full_name, $employee_id, $employment_type, $department, $position);

                        // Generate login URL
                        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                        $login_url = dirname($_SERVER['PHP_SELF']) . "/login.php";
                        $login_url = $base_url . $login_url;
                        $login_url = preg_replace('/([^:])(\/{2,})/', '$1/', $login_url);

                        // Send welcome email using your mailer function
                        $email_sent = sendVerificationInvitationEmail($conn, $new_email, $new_full_name, $temp_password, $login_url);

                        if ($email_sent) {
                            $success_message .= "• Email with credentials has been sent to the user.";
                        } else {
                            $success_message .= "• <strong>Email failed to send.</strong> Please provide the temporary password manually.";
                        }
                    } else {
                        $error_message = "Error adding user: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Database error: " . $conn->error;
                }
            }
        }
    }

    // 7. Department Management
    if (isset($_POST['add_department']) && $is_admin) {
        $dept_name = $conn->real_escape_string($_POST['dept_name']);
        $dept_code = $conn->real_escape_string($_POST['dept_code']);
        $dept_head = $conn->real_escape_string($_POST['dept_head'] ?? '');

        // Check if department code already exists
        $check_sql = "SELECT id FROM departments WHERE dept_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $dept_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Department with this code already exists!";
        } else {
            $sql = "INSERT INTO departments (dept_name, dept_code, dept_head) 
                    VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $dept_name, $dept_code, $dept_head);

            if ($stmt->execute()) {
                $success_message = "Department added successfully!";
                logAuditTrail($conn, $user_id, 'create', 'Added department: ' . $dept_name);
            } else {
                $error_message = "Error adding department: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }

    // 8. Update Department
    if (isset($_POST['update_department']) && $is_admin) {
        $dept_id = (int) $_POST['dept_id'];
        $dept_name = $conn->real_escape_string($_POST['dept_name']);
        $dept_code = $conn->real_escape_string($_POST['dept_code']);
        $dept_head = $conn->real_escape_string($_POST['dept_head'] ?? '');

        $sql = "UPDATE departments SET dept_name = ?, dept_code = ?, dept_head = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $dept_name, $dept_code, $dept_head, $dept_id);

        if ($stmt->execute()) {
            $success_message = "Department updated successfully!";
            logAuditTrail($conn, $user_id, 'update', 'Updated department: ' . $dept_name);
        } else {
            $error_message = "Error updating department: " . $conn->error;
        }
        $stmt->close();
    }

    // 9. Delete Department
    if (isset($_POST['delete_department']) && $is_admin) {
        $dept_id = (int) $_POST['dept_id'];

        // Get department name for audit log
        $sql = "SELECT dept_name FROM departments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $dept_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $dept = $result->fetch_assoc();
        $stmt->close();

        $sql = "DELETE FROM departments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $dept_id);

        if ($stmt->execute()) {
            $success_message = "Department deleted successfully!";
            logAuditTrail($conn, $user_id, 'delete', 'Deleted department: ' . $dept['dept_name']);
        } else {
            $error_message = "Error deleting department: " . $conn->error;
        }
        $stmt->close();
    }

    // 10. Update User
    if (isset($_POST['update_user']) && $is_admin) {
        $update_user_id = (int) $_POST['user_id'];
        $update_first_name = $conn->real_escape_string($_POST['first_name']);
        $update_middle_name = $conn->real_escape_string($_POST['middle_name'] ?? '');
        $update_last_name = $conn->real_escape_string($_POST['last_name']);
        $update_email = $conn->real_escape_string($_POST['email']);
        $update_role = $conn->real_escape_string($_POST['role']);
        $update_is_active = isset($_POST['is_active']) ? 1 : 0;

        // Create full name
        $update_full_name = trim("$update_first_name " .
            ($update_middle_name ? "$update_middle_name " : "") .
            $update_last_name);

        // Check if email already exists (excluding current user)
        $check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $update_email, $update_user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Email already exists! Please use a different email.";
            $check_stmt->close();
        } else {
            $check_stmt->close();

            // First, let's check what columns exist in the users table
            $check_columns = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
            $has_role_column = $check_columns->num_rows > 0;

            $check_columns2 = $conn->query("SHOW COLUMNS FROM users LIKE 'account_status'");
            $has_account_status_column = $check_columns2->num_rows > 0;

            // Build SQL query based on what columns exist
            if ($has_role_column && $has_account_status_column) {
                // Both columns exist
                $sql = "UPDATE users SET 
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    full_name = ?,
                    email = ?,
                    role = ?,
                    account_status = ?,
                    is_active = ?,
                    updated_at = NOW() 
                    WHERE id = ?";

                $account_status = $update_is_active ? 'active' : 'inactive';
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "sssssssii",
                    $update_first_name,
                    $update_middle_name,
                    $update_last_name,
                    $update_full_name,
                    $update_email,
                    $update_role,
                    $account_status,
                    $update_is_active,
                    $update_user_id
                );

            } elseif ($has_role_column) {
                // Only role column exists
                $sql = "UPDATE users SET 
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    full_name = ?,
                    email = ?,
                    role = ?,
                    is_active = ?,
                    updated_at = NOW() 
                    WHERE id = ?";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "sssssiii",
                    $update_first_name,
                    $update_middle_name,
                    $update_last_name,
                    $update_full_name,
                    $update_email,
                    $update_role,
                    $update_is_active,
                    $update_user_id
                );

            } elseif ($has_account_status_column) {
                // Only account_status column exists - use it for role
                $sql = "UPDATE users SET 
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    full_name = ?,
                    email = ?,
                    account_status = ?,
                    is_active = ?,
                    updated_at = NOW() 
                    WHERE id = ?";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "ssssssii",
                    $update_first_name,
                    $update_middle_name,
                    $update_last_name,
                    $update_full_name,
                    $update_email,
                    $update_role,
                    $update_is_active,
                    $update_user_id
                );

            } else {
                // Neither column exists - try to update anyway without role
                $sql = "UPDATE users SET 
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    full_name = ?,
                    email = ?,
                    is_active = ?,
                    updated_at = NOW() 
                    WHERE id = ?";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "sssssii",
                    $update_first_name,
                    $update_middle_name,
                    $update_last_name,
                    $update_full_name,
                    $update_email,
                    $update_is_active,
                    $update_user_id
                );
            }

            if ($stmt->execute()) {
                $success_message = "User updated successfully!";
                logAuditTrail($conn, $user_id, 'update', 'Updated user: ' . $update_email . ' (Role: ' . $update_role . ')');
            } else {
                $error_message = "Error updating user: " . $conn->error;
            }
            $stmt->close();
        }
    }

    // 11. Delete User
    if (isset($_POST['delete_user']) && $is_admin) {
        $delete_user_id = (int) $_POST['user_id'];

        // Get user email for audit log
        $sql = "SELECT email FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $delete_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Don't allow deleting own account
        if ($delete_user_id == $user_id) {
            $error_message = "You cannot delete your own account!";
        } else {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $delete_user_id);

            if ($stmt->execute()) {
                $success_message = "User deleted successfully!";
                logAuditTrail($conn, $user_id, 'delete', 'Deleted user: ' . $user['email']);
            } else {
                $error_message = "Error deleting user: " . $conn->error;
            }
            $stmt->close();
        }
    }

    // 12. Reset User Password
    if (isset($_POST['reset_password']) && $is_admin) {
        $reset_user_id = (int) $_POST['user_id'];

        // Get user info for email
        $sql = "SELECT email, full_name FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $reset_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        $new_password = 'Password123!';
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $sql = "UPDATE users SET password_hash = ?, password_changed_at = NULL, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $reset_user_id);

        if ($stmt->execute()) {
            $success_message = "Password reset successfully! New password: Password123!";
            logAuditTrail($conn, $user_id, 'update', 'Reset password for user: ' . $user['email']);

            // Send password reset email
            if (function_exists('sendEmailNotification')) {
                $email_subject = "Password Reset - HRMS Paluan";
                $email_message = "Hello " . $user['full_name'] . ",<br><br>Your password has been reset by administrator.<br><br>Your new password is: <strong>$new_password</strong><br><br>Please change your password after login.<br><br>Best regards,<br>HRMS Team";
                sendEmailNotification($conn, $user['email'], $email_subject, $email_message);
            }
        } else {
            $error_message = "Error resetting password: " . $conn->error;
        }
        $stmt->close();
    }

    // 13. Reset User Password with options
    if (isset($_POST['reset_user_password']) && $is_admin) {
        $reset_user_id = (int) $_POST['user_id'];
        $reset_option = $_POST['reset_password_option'] ?? 'default';
        $custom_reset_password = $_POST['custom_reset_password'] ?? '';
        $generated_reset_password = $_POST['generated_reset_password'] ?? '';

        // Get user info for email
        $sql = "SELECT email, full_name FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $reset_user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $stmt->close();

            // Determine password
            if ($reset_option === 'custom' && !empty($custom_reset_password)) {
                $new_password = $custom_reset_password;
            } elseif ($reset_option === 'generate' && !empty($generated_reset_password)) {
                $new_password = $generated_reset_password;
            } else {
                $new_password = 'Password123!'; // Default password
            }

            // Validate password
            $password_errors = validatePassword($new_password);
            if (empty($password_errors)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $sql = "UPDATE users SET password_hash = ?, password_changed_at = NULL, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $hashed_password, $reset_user_id);

                if ($stmt->execute()) {
                    $success_message = "Password reset successfully!";
                    if ($reset_option === 'default') {
                        $success_message .= "<br>New password: $new_password";
                    }

                    logAuditTrail($conn, $user_id, 'update', 'Reset password for user: ' . $user['email']);

                    // Send password reset email
                    if (function_exists('sendEmailNotification')) {
                        $email_subject = "Password Reset - HRMS Paluan";
                        $email_message = "Hello " . $user['full_name'] . ",<br><br>
                    Your password has been reset by administrator.<br><br>";

                        if ($reset_option === 'custom') {
                            $email_message .= "A new password has been set for your account.<br>";
                        } elseif ($reset_option === 'generate') {
                            $email_message .= "Your new password is: <strong>$new_password</strong><br>";
                        } else {
                            $email_message .= "Your new password is: <strong>$new_password</strong><br>";
                        }

                        $email_message .= "<br>Please change your password after login.<br><br>
                    Best regards,<br>HRMS Team";

                        sendEmailNotification($conn, $user['email'], $email_subject, $email_message);
                    }
                } else {
                    $error_message = "Error resetting password: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message = "Password error: " . implode(', ', $password_errors);
            }
        } else {
            $error_message = "User not found!";
        }
    }
}

// Fetch current settings from database
$system_settings = [
    'system_name' => 'HRMS Paluan',
    'timezone' => 'Asia/Manila',
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i:s',
    'pagination_limit' => 25,
    'session_timeout' => 30,
    'enable_registration' => 1,
    'enable_remember_me' => 1,
    'enable_debug' => 0
];

$email_settings = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',
    'from_email' => 'hrmo@paluan.gov.ph',
    'from_name' => 'HRMO Paluan',
    'enable_email_notifications' => 1
];

// Change the user profile array to use separate name fields:
$user_profile = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'email' => $user_email,
    'phone' => '',
    'department' => '',
    'position' => ''
];

// Fetch existing data
$sql = "SELECT * FROM system_settings LIMIT 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $system_settings = array_merge($system_settings, $result->fetch_assoc());
}

$sql = "SELECT * FROM email_settings LIMIT 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $email_settings = array_merge($email_settings, $result->fetch_assoc());
}

// Get user profile from database
$sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    $user_profile = array_merge($user_profile, $user_data);
}
$stmt->close();

// Get audit logs (limited to 100 for performance)
$audit_logs = [];
$sql = "SELECT al.*, 
        CONCAT(
            COALESCE(u.first_name, ''), 
            ' ', 
            COALESCE(u.middle_name, ''), 
            ' ', 
            COALESCE(u.last_name, '')
        ) as full_name
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT 100";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $audit_logs[] = $row;
    }
}

// Get backup history
$backup_history = [];
$sql = "SELECT b.*, 
        CONCAT(
            COALESCE(u.first_name, ''), 
            ' ', 
            COALESCE(u.middle_name, ''), 
            ' ', 
            COALESCE(u.last_name, '')
        ) as full_name
        FROM backups b 
        LEFT JOIN users u ON b.created_by = u.id 
        ORDER BY b.created_at DESC 
        LIMIT 20";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $backup_history[] = $row;
    }
}

// Get departments for department management
$departments = [];
$sql = "SELECT * FROM departments ORDER BY dept_name ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}

// Get users for user management with correct columns
$users = [];
$sql = "SELECT id, username, email, full_name, employee_id, 
               employment_type, employee_type,
               role, access_level, is_active, is_verified, 
               last_verification_sent, verified_at, created_at 
        FROM users 
        ORDER BY full_name ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get current tab from URL or default to system
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : ($is_admin ? 'system' : 'profile');

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - HRMS Paluan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --secondary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--light);
            color: var(--dark);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            background: var(--gradient-primary);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 1.5rem;
            max-width: 100%;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            transition: transform 0.3s ease;
        }

        .mobile-toggle:hover {
            transform: scale(1.1);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .brand-logo {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .datetime-container {
            display: flex;
            gap: 1rem;
        }

        .datetime-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .datetime-box:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .datetime-icon {
            color: white;
            font-size: 1rem;
        }

        .datetime-text {
            display: flex;
            flex-direction: column;
        }

        .datetime-label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .datetime-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
        }

        /* User Menu */
        .user-menu {
            position: relative;
        }

        .user-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 0.3rem 0.6rem 0.3rem 0.3rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-name {
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 25px -3px rgba(30, 64, 175, 0.2);
            min-width: 220px;
            display: none;
            z-index: 1001;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            animation: slideDown 0.3s ease;
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

        .user-dropdown.active {
            display: block;
        }

        .dropdown-header {
            padding: 1.25rem 1.5rem;
            background: var(--gradient-primary);
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.25rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--gray-100);
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background: var(--gray-50);
            color: var(--primary);
            padding-left: 1.5rem;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            color: var(--primary-light);
            font-size: 1.1rem;
        }

        /* Sidebar */
        .sidebar-container {
            position: fixed;
            left: 0;
            top: 70px;
            width: 260px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            z-index: 999;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .sidebar {
            height: 100%;
            padding: 1.5rem 0;
        }

        .sidebar-content {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding: 0 1rem;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 1.25rem;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 12px;
            margin-bottom: 0.25rem;
            position: relative;
            overflow: hidden;
        }

        .sidebar-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: white;
            transform: scaleY(0);
            transition: transform 0.3s ease;
            border-radius: 0 4px 4px 0;
        }

        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.12);
            color: white;
            transform: translateX(4px);
        }

        .sidebar-item:hover::before {
            transform: scaleY(1);
        }

        .sidebar-item.active {
            background: rgba(255, 255, 255, 0.18);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .sidebar-item.active::before {
            transform: scaleY(1);
        }

        .sidebar-item i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .sidebar-item span {
            font-size: 0.95rem;
            font-weight: 600;
            flex: 1;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: white;
        }

        /* Dropdown Menu in Sidebar */
        .dropdown-menu {
            display: none;
            padding-left: 1rem;
            margin-left: 2.5rem;
            border-left: 2px solid rgba(255, 255, 255, 0.1);
            animation: fadeIn 0.3s ease;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-menu .dropdown-item {
            padding: 0.7rem 1rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            border-bottom: none;
        }

        .dropdown-menu .dropdown-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .chevron {
            transition: transform 0.3s ease;
        }

        .chevron.rotate {
            transform: rotate(180deg);
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 2rem;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .sidebar-container.active+.main-content {
                margin-left: 260px;
            }
        }

        /* Settings Container */
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Settings Header */
        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .settings-header h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .settings-header h1 i {
            color: var(--primary);
        }

        .settings-actions {
            display: flex;
            gap: 1rem;
        }

        /* Settings Tabs */
        .settings-tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid var(--gray-200);
            margin-bottom: 2rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .settings-tab {
            padding: 0.75rem 1.5rem;
            border: none;
            background: none;
            color: var(--gray-600);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .settings-tab:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        .settings-tab.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(30, 64, 175, 0.2);
        }

        .settings-tab i {
            font-size: 1rem;
        }

        /* Tab Content */
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Settings Card */
        .settings-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1.25rem 1.5rem;
            color: white;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
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

        .form-control:disabled {
            background: var(--gray-100);
            cursor: not-allowed;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        .form-text {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-check-input {
            width: 1.125em;
            height: 1.125em;
            border: 1px solid var(--gray-300);
            border-radius: 0.25rem;
            cursor: pointer;
        }

        .form-check-label {
            font-weight: 500;
            color: var(--gray-700);
            cursor: pointer;
        }

        /* Button Styles */
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
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            color: white;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }

        /* Message Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideInDown 0.3s ease;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .alert i {
            font-size: 1.25rem;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .table th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
        }

        .table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-600);
        }

        .table tr:hover {
            background: var(--gray-50);
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1100;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--gray-500);
            cursor: pointer;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        /* System Info Cards */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--gray-200);
        }

        .info-card h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-500);
            margin-bottom: 0.5rem;
        }

        .info-card p {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }

        /* Overlay for mobile */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
            backdrop-filter: blur(4px);
        }

        .overlay.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }

            .datetime-container {
                display: none;
            }

            .sidebar-container {
                transform: translateX(-100%);
            }

            .sidebar-container.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .settings-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .settings-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .settings-tab {
                font-size: 0.875rem;
                padding: 0.5rem 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .table {
                font-size: 0.875rem;
            }
        }

        @media (max-width: 480px) {
            .navbar-container {
                padding: 0 1rem;
            }

            .brand-title {
                font-size: 0.9rem;
            }

            .card-body {
                padding: 1rem;
            }

            .modal-content {
                margin: 0;
                border-radius: 12px;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation Header -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Left Section -->
            <div class="navbar-left">
                <!-- Mobile Menu Toggle -->
                <button class="mobile-toggle" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Logo and Brand -->
                <a href="dashboard.php" class="navbar-brand">
                    <img class="brand-logo"
                        src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                        alt="Logo" />
                    <div class="brand-text">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Paluan Occidental Mindoro</span>
                    </div>
                </a>
            </div>

            <!-- Right Section -->
            <div class="navbar-right">
                <!-- Date & Time -->
                <div class="datetime-container">
                    <div class="datetime-box">
                        <i class="datetime-icon fas fa-calendar-alt"></i>
                        <div class="datetime-text">
                            <span class="datetime-label">Date</span>
                            <span class="datetime-value" id="current-date">Loading...</span>
                        </div>
                    </div>

                    <div class="datetime-box">
                        <i class="datetime-icon fas fa-clock"></i>
                        <div class="datetime-text">
                            <span class="datetime-label">Time</span>
                            <span class="datetime-value" id="current-time">Loading...</span>
                        </div>
                    </div>
                </div>


            </div>
        </div>
    </nav>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar -->
    <div class="sidebar-container" id="sidebar-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <!-- Dashboard -->
                <a href="dashboard.php" class="sidebar-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard Analytics</span>
                </a>

                <!-- Employees -->
                <a href="./employees/Employee.php" class="sidebar-item">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>

                <!-- Attendance -->
                <a href="attendance.php" class="sidebar-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>

                <!-- Payroll -->
                <a href="#" class="sidebar-item" id="payroll-toggle">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                    <i class="fas fa-chevron-down chevron ml-auto"></i>
                </a>
                <div class="dropdown-menu" id="payroll-dropdown">
                    <a href="Payrollmanagement/contractualpayrolltable1.php" class="dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Contractual
                    </a>
                    <a href="Payrollmanagement/joboerderpayrolltable1.php" class="dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Job Order
                    </a>
                    <a href="Payrollmanagement/permanentpayrolltable1.php" class="dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Permanent
                    </a>
                </div>



                <!-- Reports -->
                <a href="paysliphistory.php" class="sidebar-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>

                <!-- Settings -->
                <a href="settings.php" class="sidebar-item active">
                    <i class="fas fa-sliders-h"></i>
                    <span>Settings</span>
                </a>

                <!-- Logout -->
                <a href="?logout=true" class="sidebar-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="text-center text-white/60 text-sm">
                    <p>HRMS v2.0</p>
                    <p class="text-xs mt-1">© 2024 Paluan LGU</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="settings-container">
            <!-- Settings Header -->
            <div class="settings-header">
                <h1>
                    <i class="fas fa-sliders-h"></i>
                    System Settings
                </h1>
                <div class="settings-actions">
                    <button class="btn btn-secondary" onclick="exportSettings()">
                        <i class="fas fa-download"></i>
                        Export Settings
                    </button>
                    <?php if ($is_admin): ?>
                        <button class="btn btn-danger" onclick="openResetModal()">
                            <i class="fas fa-trash"></i>
                            Reset Settings
                        </button>
                        <!-- Change this button to open the invitations modal
                        <button class="btn btn-success" onclick="openVerifyUserModal()">
                            <i class="fas fa-paper-plane"></i>
                            Send Invitations
                        </button> -->
                    <?php endif; ?>
                </div>
            </div>

            <!-- Success/Error Messages -->
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

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <?php if ($is_admin): ?>
                    <button class="settings-tab <?php echo $current_tab == 'system' ? 'active' : ''; ?>" data-tab="system">
                        <i class="fas fa-cog"></i>
                        System Settings
                    </button>
                    <button class="settings-tab <?php echo $current_tab == 'users' ? 'active' : ''; ?>" data-tab="users">
                        <i class="fas fa-users"></i>
                        User Management
                    </button>
                <?php endif; ?>
                <button class="settings-tab <?php echo $current_tab == 'profile' ? 'active' : ''; ?>"
                    data-tab="profile">
                    <i class="fas fa-user"></i>
                    Profile
                </button>
                <button class="settings-tab <?php echo $current_tab == 'security' ? 'active' : ''; ?>"
                    data-tab="security">
                    <i class="fas fa-shield-alt"></i>
                    Security
                </button>
                <?php if ($is_admin): ?>
                    <button class="settings-tab <?php echo $current_tab == 'backup' ? 'active' : ''; ?>" data-tab="backup">
                        <i class="fas fa-database"></i>
                        Backup & Restore
                    </button>
                    <button class="settings-tab <?php echo $current_tab == 'logs' ? 'active' : ''; ?>" data-tab="logs">
                        <i class="fas fa-history"></i>
                        Audit Logs
                    </button>
                    <button class="settings-tab <?php echo $current_tab == 'system-info' ? 'active' : ''; ?>"
                        data-tab="system-info">
                        <i class="fas fa-info-circle"></i>
                        System Info
                    </button>
                <?php endif; ?>
            </div>

            <!-- Tab Contents -->
            <?php if ($is_admin): ?>
                <!-- System Settings Tab -->
                <div id="system" class="tab-content <?php echo $current_tab == 'system' ? 'active' : ''; ?>">
                    <div class="settings-card">
                        <div class="card-header">
                            <h2><i class="fas fa-cog"></i> General System Settings</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="system_name">System Name <span>*</span></label>
                                        <input type="text" class="form-control" id="system_name" name="system_name"
                                            value="<?php echo htmlspecialchars($system_settings['system_name']); ?>"
                                            required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="timezone">Timezone <span>*</span></label>
                                        <select class="form-control form-select" id="timezone" name="timezone" required>
                                            <option value="Asia/Manila" <?php echo $system_settings['timezone'] == 'Asia/Manila' ? 'selected' : ''; ?>>
                                                Asia/Manila (GMT+8)</option>
                                            <option value="UTC" <?php echo $system_settings['timezone'] == 'UTC' ? 'selected' : ''; ?>>UTC (GMT+0)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="date_format">Date Format <span>*</span></label>
                                        <select class="form-control form-select" id="date_format" name="date_format"
                                            required>
                                            <option value="Y-m-d" <?php echo $system_settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (2024-01-15)</option>
                                            <option value="d/m/Y" <?php echo $system_settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (15/01/2024)</option>
                                            <option value="m/d/Y" <?php echo $system_settings['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (01/15/2024)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="time_format">Time Format <span>*</span></label>
                                        <select class="form-control form-select" id="time_format" name="time_format"
                                            required>
                                            <option value="H:i:s" <?php echo $system_settings['time_format'] == 'H:i:s' ? 'selected' : ''; ?>>24-hour (14:30:00)</option>
                                            <option value="h:i:s A" <?php echo $system_settings['time_format'] == 'h:i:s A' ? 'selected' : ''; ?>>12-hour (02:30:00 PM)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="pagination_limit">Items Per Page
                                            <span>*</span></label>
                                        <select class="form-control form-select" id="pagination_limit"
                                            name="pagination_limit" required>
                                            <option value="10" <?php echo $system_settings['pagination_limit'] == 10 ? 'selected' : ''; ?>>10 items</option>
                                            <option value="25" <?php echo $system_settings['pagination_limit'] == 25 ? 'selected' : ''; ?>>25 items</option>
                                            <option value="50" <?php echo $system_settings['pagination_limit'] == 50 ? 'selected' : ''; ?>>50 items</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="session_timeout">Session Timeout (minutes)
                                            <span>*</span></label>
                                        <select class="form-control form-select" id="session_timeout" name="session_timeout"
                                            required>
                                            <option value="15" <?php echo $system_settings['session_timeout'] == 15 ? 'selected' : ''; ?>>15 minutes</option>
                                            <option value="30" <?php echo $system_settings['session_timeout'] == 30 ? 'selected' : ''; ?>>30 minutes</option>
                                            <option value="60" <?php echo $system_settings['session_timeout'] == 60 ? 'selected' : ''; ?>>1 hour</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="enable_registration"
                                                name="enable_registration" value="1" <?php echo $system_settings['enable_registration'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_registration">
                                                Enable User Registration
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="enable_remember_me"
                                                name="enable_remember_me" value="1" <?php echo $system_settings['enable_remember_me'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_remember_me">
                                                Enable Remember Me
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <button type="submit" name="update_system_settings" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                        Save System Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- User Management Tab -->
                <div id="users" class="tab-content <?php echo $current_tab == 'users' ? 'active' : ''; ?>">
                    <div class="settings-card">
                        <div class="card-header"
                            style="display: flex; justify-content: space-between; align-items: center;">
                            <h2><i class="fas fa-users"></i> User Management</h2>
                            <!-- Search Bar integrated in header -->
                            <div class="search-wrapper" style="width: 300px;">
                                <div class="input-group input-group-sm">
                                    <input type="text" id="userSearch" class="form-control border-left-0"
                                        placeholder="Search users..." style="border-left: none; padding-left: 20;">
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="table" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Employee ID</th>
                                            <th>Email</th>
                                            <th>Username</th>
                                            <th>Verification Status</th>
                                            <th>Employment Type</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Update the table body -->
                                        <?php foreach ($users as $user): ?>
                                            <?php
                                            // Get employment type from database - FIX THIS
                                            // You have both employment_type (line 36) and employee_type (line 21) columns
                                            // Use the correct one based on your data structure
                                            $emp_type = $user['employment_type'] ?? $user['employee_type'] ?? 'Not set';
                                            $emp_type_display = ucfirst(str_replace('_', ' ', $emp_type));

                                            // Determine badge class based on employment type
                                            $emp_badge = 'badge-info';
                                            if ($emp_type == 'permanent')
                                                $emp_badge = 'badge-success';
                                            elseif ($emp_type == 'job_order')
                                                $emp_badge = 'badge-warning';
                                            elseif ($emp_type == 'contract_of_service')
                                                $emp_badge = 'badge-primary';

                                            // Check invitation status
                                            $has_username = !empty($user['username']);
                                            $is_verified = $user['is_verified'] ?? 0;
                                            $invitation_sent = !empty($user['last_verification_sent']);
                                            $invitation_accepted = !empty($user['verified_at']);

                                            if ($has_username && $is_verified) {
                                                $verification_status = 'Verified';
                                                $verification_badge = 'badge-success';
                                            } elseif ($invitation_accepted) {
                                                $verification_status = 'Accepted';
                                                $verification_badge = 'badge-info';
                                            } elseif ($invitation_sent) {
                                                $verification_status = 'Invited';
                                                $verification_badge = 'badge-warning';
                                            } else {
                                                $verification_status = 'Pending';
                                                $verification_badge = 'badge-secondary';
                                            }
                                            ?>
                                            <tr class="user-row" data-search="<?php
                                            echo htmlspecialchars(strtolower(
                                                $user['full_name'] . ' ' .
                                                ($user['employee_id'] ?? '') . ' ' .
                                                $user['email'] . ' ' .
                                                ($user['username'] ?? '') . ' ' .
                                                $verification_status . ' ' .
                                                $emp_type_display . ' ' .
                                                $user['role'] . ' ' .
                                                ($user['is_active'] ? 'Active' : 'Inactive')
                                            ));
                                            ?>">
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['employee_id'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <?php if ($has_username): ?>
                                                        <?php echo htmlspecialchars($user['username']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $verification_badge; ?>">
                                                        <?php echo $verification_status; ?>
                                                        <?php if ($invitation_sent && !$invitation_accepted): ?>
                                                            <br><small>Sent:
                                                                <?php echo date('M d', strtotime($user['last_verification_sent'])); ?></small>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $emp_badge; ?>">
                                                        <?php echo $emp_type_display; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge <?php echo $user['role'] == 'admin' ? 'badge-danger' : ($user['role'] == 'manager' ? 'badge-warning' : 'badge-info'); ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons" style="display: flex; gap: 0.25rem;">
                                                        <button class="btn btn-secondary btn-sm"
                                                            onclick="editUser(<?php echo $user['id']; ?>, <?php echo htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8'); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($has_username): ?>
                                                            <button class="btn btn-warning btn-sm"
                                                                onclick="resetUserPassword(<?php echo $user['id']; ?>)">
                                                                <i class="fas fa-key"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if (!$has_username || !$is_verified): ?>
                                                            <!-- Direct form submission method (most reliable) -->
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="invite_user_id"
                                                                    value="<?php echo $user['id']; ?>">
                                                                <input type="hidden" name="send_verification_invitation" value="1">
                                                                <button type="submit" class="btn btn-success btn-sm"
                                                                    onclick="return confirm('Send verification invitation to <?php echo addslashes($user['full_name']); ?>?')">
                                                                    <i class="fas fa-paper-plane"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($user['id'] != $user_id): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="user_id"
                                                                    value="<?php echo $user['id']; ?>">
                                                                <input type="hidden" name="delete_user" value="1">
                                                                <button type="submit" class="btn btn-danger btn-sm"
                                                                    onclick="return confirm('Are you sure you want to delete <?php echo addslashes($user['full_name']); ?>? This action cannot be undone.')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <!-- Profile Tab -->
            <div id="profile" class="tab-content <?php echo $current_tab == 'profile' ? 'active' : ''; ?>">
                <div class="settings-card">
                    <div class="card-header">
                        <h2><i class="fas fa-user"></i> User Profile</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="first_name">First Name <span>*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name"
                                        value="<?php echo htmlspecialchars($user_profile['first_name'] ?? ''); ?>"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="middle_name">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name"
                                        value="<?php echo htmlspecialchars($user_profile['middle_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="last_name">Last Name <span>*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name"
                                        value="<?php echo htmlspecialchars($user_profile['last_name'] ?? ''); ?>"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="email">Email Address <span>*</span></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo htmlspecialchars($user_profile['email']); ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="phone">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                        value="<?php echo htmlspecialchars($user_profile['phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="department">Department</label>
                                    <input type="text" class="form-control" id="department" name="department"
                                        value="<?php echo htmlspecialchars($user_profile['department'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="position">Position</label>
                                <input type="text" class="form-control" id="position" name="position"
                                    value="<?php echo htmlspecialchars($user_profile['position'] ?? ''); ?>">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="phone">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                        value="<?php echo htmlspecialchars($user_profile['phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="department">Department</label>
                                    <input type="text" class="form-control" id="department" name="department"
                                        value="<?php echo htmlspecialchars($user_profile['department'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="position">Position</label>
                                <input type="text" class="form-control" id="position" name="position"
                                    value="<?php echo htmlspecialchars($user_profile['position'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Security Tab -->
            <div id="security" class="tab-content <?php echo $current_tab == 'security' ? 'active' : ''; ?>">
                <div class="settings-card">
                    <div class="card-header">
                        <h2><i class="fas fa-shield-alt"></i> Security Settings</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="passwordForm">
                            <div class="form-group">
                                <label class="form-label" for="current_password">Current Password <span>*</span></label>
                                <input type="password" class="form-control" id="current_password"
                                    name="current_password" required>
                                <div class="form-text">Enter your current password to verify your identity.</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="new_password">New Password <span>*</span></label>
                                <input type="password" class="form-control" id="new_password" name="new_password"
                                    minlength="8" required>
                                <div class="form-text">Password must be at least 8 characters with uppercase, lowercase,
                                    number, and special character.</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="confirm_password">Confirm New Password
                                    <span>*</span></label>
                                <input type="password" class="form-control" id="confirm_password"
                                    name="confirm_password" required>
                                <div class="form-text">Re-enter your new password for confirmation.</div>
                            </div>

                            <div class="form-group">
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key"></i>
                                    Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($is_admin): ?>
                <!-- Backup & Restore Tab -->
                <div id="backup" class="tab-content <?php echo $current_tab == 'backup' ? 'active' : ''; ?>">
                    <div class="settings-card">
                        <div class="card-header">
                            <h2><i class="fas fa-database"></i> Backup & Restore</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Create New Backup</label>
                                    <form method="POST">
                                        <select class="form-control form-select" name="backup_type" required
                                            style="margin-bottom: 1rem;">
                                            <option value="full">Full Backup (Database + Files)</option>
                                            <option value="database">Database Only</option>
                                            <option value="files">Files Only</option>
                                        </select>
                                        <input type="text" class="form-control" name="backup_notes"
                                            placeholder="Backup notes (optional)" style="margin-bottom: 1rem;">
                                        <button type="submit" name="create_backup" class="btn btn-primary">
                                            <i class="fas fa-plus"></i>
                                            Create Backup
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <hr style="margin: 2rem 0; border-color: var(--gray-200);">

                            <h3 style="margin-bottom: 1rem; color: var(--dark);">Backup History</h3>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Created By</th>
                                            <th>File Size</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backup_history as $backup): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d H:i', strtotime($backup['created_at'])); ?></td>
                                                <td>
                                                    <span
                                                        class="badge 
                                                    <?php echo $backup['backup_type'] == 'full' ? 'badge-success' : ($backup['backup_type'] == 'database' ? 'badge-info' : 'badge-warning'); ?>">
                                                        <?php echo ucfirst($backup['backup_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($backup['full_name'] ?? 'System'); ?></td>
                                                <td>
                                                    <?php if ($backup['file_size']): ?>
                                                        <?php echo formatFileSize($backup['file_size']); ?>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-success">Completed</span>
                                                </td>
                                                <td>
                                                    <?php if ($backup['file_path'] && file_exists($backup['file_path'])): ?>
                                                        <button class="btn btn-secondary btn-sm"
                                                            onclick="downloadBackup('<?php echo basename($backup['file_path']); ?>')">
                                                            <i class="fas fa-download"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Audit Logs Tab -->
                <div id="logs" class="tab-content <?php echo $current_tab == 'logs' ? 'active' : ''; ?>">
                    <div class="settings-card">
                        <div class="card-header">
                            <h2><i class="fas fa-history"></i> Audit Logs</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Description</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($audit_logs as $log): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></td>
                                                <td>
                                                    <span class="badge 
                                                    <?php
                                                    $badge_class = 'badge-info';
                                                    if ($log['action_type'] == 'login')
                                                        $badge_class = 'badge-success';
                                                    elseif ($log['action_type'] == 'logout')
                                                        $badge_class = 'badge-warning';
                                                    elseif ($log['action_type'] == 'delete')
                                                        $badge_class = 'badge-danger';
                                                    elseif ($log['action_type'] == 'create')
                                                        $badge_class = 'badge-success';
                                                    elseif ($log['action_type'] == 'update')
                                                        $badge_class = 'badge-primary';
                                                    echo $badge_class;
                                                    ?>">
                                                        <?php echo ucfirst($log['action_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Info Tab -->
                <div id="system-info" class="tab-content <?php echo $current_tab == 'system-info' ? 'active' : ''; ?>">
                    <div class="settings-card">
                        <div class="card-header">
                            <h2><i class="fas fa-info-circle"></i> System Information</h2>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-card">
                                    <h3>System Version</h3>
                                    <p>HRMS v2.0.1</p>
                                </div>
                                <div class="info-card">
                                    <h3>PHP Version</h3>
                                    <p><?php echo phpversion(); ?></p>
                                </div>
                                <div class="info-card">
                                    <h3>Total Users</h3>
                                    <p><?php echo count($users); ?></p>
                                </div>
                                <div class="info-card">
                                    <h3>Total Departments</h3>
                                    <p><?php echo count($departments); ?></p>
                                </div>
                            </div>

                            <h3 style="margin-bottom: 1rem; color: var(--dark);">Server Information</h3>
                            <div class="table-container">
                                <table class="table">
                                    <tbody>
                                        <tr>
                                            <td><strong>Server Software</strong></td>
                                            <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>PHP Memory Limit</strong></td>
                                            <td><?php echo ini_get('memory_limit'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Max Upload Size</strong></td>
                                            <td><?php echo ini_get('upload_max_filesize'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add User Modal -->
    <div class="modal" id="addUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                <button class="modal-close" onclick="closeAddUserModal()">&times;</button>
            </div>
            <form method="POST" id="addUserForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Full Name <span>*</span></label>
                        <input type="text" class="form-control" name="new_full_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address <span>*</span></label>
                        <input type="email" class="form-control" name="new_email" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Employee ID</label>
                        <input type="text" class="form-control" name="employee_id"
                            placeholder="Auto-generated if empty">
                    </div>

                    <!-- Password Section -->
                    <div class="form-group">
                        <label class="form-label">Password Option <span>*</span></label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="password_option" id="passwordDefault"
                                value="default" checked onclick="togglePasswordFields()">
                            <label class="form-check-label" for="passwordDefault">
                                Use Default Password (Password123!)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="password_option" id="passwordCustom"
                                value="custom" onclick="togglePasswordFields()">
                            <label class="form-check-label" for="passwordCustom">
                                Set Custom Password
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="password_option" id="passwordGenerate"
                                value="generate" onclick="togglePasswordFields()">
                            <label class="form-check-label" for="passwordGenerate">
                                Generate Random Password
                            </label>
                        </div>

                        <!-- Custom Password Field -->
                        <div id="customPasswordField" style="display: none; margin-top: 10px;">
                            <label class="form-label">Custom Password</label>
                            <input type="password" class="form-control" name="custom_password" id="customPassword"
                                placeholder="Enter custom password" oninput="checkPasswordStrength(this.value)">
                            <div class="form-text">
                                <small>Password must contain: 8+ characters, uppercase, lowercase, number, special
                                    character</small>
                            </div>
                            <div id="passwordStrength" style="margin-top: 5px;"></div>
                            <button type="button" class="btn btn-sm btn-secondary mt-2"
                                onclick="togglePasswordVisibility('customPassword')">
                                <i class="fas fa-eye"></i> Show Password
                            </button>
                        </div>

                        <!-- Generated Password Display -->
                        <!-- Generated Password Display -->
                        <div id="generatedPasswordField" style="display: none; margin-top: 10px;">
                            <label class="form-label">Generated Password</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="generatedPassword" readonly>
                                <button type="button" class="btn btn-secondary" onclick="generatePassword()">
                                    <i class="fas fa-redo"></i> Regenerate
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="copyGeneratedPassword()">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                            <!-- CHANGE THIS LINE: Use generated_password instead of generated_password -->
                            <input type="hidden" name="generated_password" id="generatedPasswordHidden">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Employment Type <span>*</span></label>
                            <select class="form-control form-select" name="employment_type" required>
                                <option value="permanent">Permanent</option>
                                <option value="job_order">Job Order</option>
                                <option value="contract_of_service">Contract of Service</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role <span>*</span></label>
                            <select class="form-control form-select" name="new_role" required>
                                <option value="admin">Administrator</option>
                                <option value="manager">Manager</option>
                                <option value="user">Regular User</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Position</label>
                            <input type="text" class="form-control" name="position">
                        </div>
                    </div>
                    <div class="form-text">
                        <strong>Note:</strong> User will receive login credentials via email.<br>
                        <strong>Username:</strong> Will be auto-generated from email (part before @).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Verify User Modal -->
    <div class="modal" id="verifyUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-check"></i> Verify User Account</h3>
                <button class="modal-close" onclick="closeVerifyUserModal()">&times;</button>
            </div>
            <form method="POST" id="verifyUserForm">
                <div class="modal-body">
                    <p>Setting up login credentials for: <strong id="verifyUserName"></strong></p>
                    <input type="hidden" name="verify_user_id" id="verifyUserId">

                    <div class="form-group">
                        <label class="form-label">Username <span>*</span></label>
                        <input type="text" class="form-control" name="verify_username" id="verifyUsername" required
                            placeholder="Enter username (usually email prefix)">
                        <div class="form-text">Username will be used for login</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password <span>*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="verify_password" id="verifyPassword"
                                required minlength="8" placeholder="Enter password">
                            <button type="button" class="btn btn-secondary"
                                onclick="togglePasswordVisibility('verifyPassword')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Password must be at least 8 characters with uppercase, lowercase, number,
                            and special character</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password <span>*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_verify_password"
                                id="confirmVerifyPassword" required placeholder="Confirm password">
                            <button type="button" class="btn btn-secondary"
                                onclick="togglePasswordVisibility('confirmVerifyPassword')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="send_credentials_email"
                            id="sendCredentialsEmail" checked>
                        <label class="form-check-label" for="sendCredentialsEmail">
                            Send login credentials via email
                        </label>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        This will enable the user to login to the system with the provided credentials.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeVerifyUserModal()">Cancel</button>
                    <button type="submit" name="verify_user" class="btn btn-success">Verify User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="editUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit User</h3>
                <button class="modal-close" onclick="closeEditUserModal()">&times;</button>
            </div>
            <form method="POST" id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" id="editUserFirstName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control" name="middle_name" id="editUserMiddleName">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" id="editUserLastName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" id="editUserEmail" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select class="form-control form-select" name="role" id="editUserRole" required>
                            <option value="admin">Administrator</option>
                            <option value="manager">Manager</option>
                            <option value="user">Regular User</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="editUserActive"
                                value="1">
                            <label class="form-check-label" for="editUserActive">Active User</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                    <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div class="modal" id="editDepartmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Department</h3>
                <button class="modal-close" onclick="closeEditDepartmentModal()">&times;</button>
            </div>
            <form method="POST" id="editDepartmentForm">
                <div class="modal-body">
                    <input type="hidden" name="dept_id" id="editDeptId">
                    <div class="form-group">
                        <label class="form-label">Department Name</label>
                        <input type="text" class="form-control" name="dept_name" id="editDeptName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department Code</label>
                        <input type="text" class="form-control" name="dept_code" id="editDeptCode" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department Head</label>
                        <input type="text" class="form-control" name="dept_head" id="editDeptHead">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditDepartmentModal()">Cancel</button>
                    <button type="submit" name="update_department" class="btn btn-primary">Update Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Settings Modal -->
    <div class="modal" id="resetModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Reset Settings</h3>
                <button class="modal-close" onclick="closeResetModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reset all settings to their default values? This action cannot be undone.
                </p>
                <div class="form-group">
                    <label class="form-label">Please type "RESET" to confirm:</label>
                    <input type="text" class="form-control" id="resetConfirm" placeholder="Type RESET here">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeResetModal()">Cancel</button>
                <button class="btn btn-danger" onclick="confirmReset()">Reset Settings</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Are you sure you want to delete this item?</p>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <input type="hidden" name="dept_id" id="deleteDeptId">
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn btn-danger" onclick="submitDelete()">Delete</button>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal" id="resetPasswordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Reset User Password</h3>
                <button class="modal-close" onclick="closeResetPasswordModal()">&times;</button>
            </div>
            <form method="POST" id="resetPasswordForm">
                <div class="modal-body">
                    <p id="resetPasswordMessage"></p>
                    <input type="hidden" name="user_id" id="resetPasswordUserId">

                    <div class="form-group">
                        <label class="form-label">Password Option <span>*</span></label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="reset_password_option" id="resetDefault"
                                value="default" checked onclick="toggleResetPasswordFields()">
                            <label class="form-check-label" for="resetDefault">
                                Use Default Password (Password123!)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="reset_password_option" id="resetCustom"
                                value="custom" onclick="toggleResetPasswordFields()">
                            <label class="form-check-label" for="resetCustom">
                                Set Custom Password
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="reset_password_option" id="resetGenerate"
                                value="generate" onclick="toggleResetPasswordFields()">
                            <label class="form-check-label" for="resetGenerate">
                                Generate Random Password
                            </label>
                        </div>

                        <!-- Custom Reset Password Field -->
                        <div id="customResetPasswordField" style="display: none; margin-top: 10px;">
                            <label class="form-label">Custom Password</label>
                            <input type="password" class="form-control" name="custom_reset_password"
                                id="customResetPassword" placeholder="Enter custom password">
                            <div class="form-text">
                                <small>Password must contain: 8+ characters, uppercase, lowercase, number, special
                                    character</small>
                            </div>
                        </div>

                        <!-- Generated Reset Password Display -->
                        <div id="generatedResetPasswordField" style="display: none; margin-top: 10px;">
                            <label class="form-label">Generated Password</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="generatedResetPassword" readonly>
                                <button type="button" class="btn btn-secondary" onclick="generateResetPassword()">
                                    <i class="fas fa-redo"></i> Regenerate
                                </button>
                            </div>
                            <input type="hidden" name="generated_reset_password" id="generatedResetPasswordHidden">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeResetPasswordModal()">Cancel</button>
                    <button type="submit" name="reset_user_password" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('userSearch');
            const userRows = document.querySelectorAll('.user-row');

            // Debounce function to prevent too many searches
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            // Search function
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                let hasVisibleRows = false;

                if (searchTerm === '') {
                    // Show all rows
                    userRows.forEach(row => {
                        row.style.display = '';
                        hasVisibleRows = true;
                    });
                } else {
                    // Filter rows
                    userRows.forEach(row => {
                        const searchData = row.getAttribute('data-search');
                        const isVisible = searchData.includes(searchTerm);

                        if (isVisible) {
                            row.style.display = '';
                            hasVisibleRows = true;

                            // Optional: Highlight search term in row
                            if (searchTerm.length > 2) {
                                highlightSearchTerm(row, searchTerm);
                            }
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    // Remove highlights from hidden rows
                    if (searchTerm.length > 2) {
                        userRows.forEach(row => {
                            if (row.style.display === 'none') {
                                removeHighlights(row);
                            }
                        });
                    }
                }

                // Add subtle animation to rows that appear
                document.querySelectorAll('.user-row[style=""]').forEach((row, index) => {
                    row.style.animationDelay = `${index * 0.05}s`;
                    row.classList.add('fade-in');
                });
            }

            // Optional: Highlight search term (for better UX)
            function highlightSearchTerm(row, term) {
                removeHighlights(row);

                const cells = row.querySelectorAll('td');
                cells.forEach(cell => {
                    const html = cell.innerHTML;
                    const regex = new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                    const newHtml = html.replace(regex, '<mark class="search-highlight">$1</mark>');
                    cell.innerHTML = newHtml;
                });
            }

            // Remove highlights
            function removeHighlights(row) {
                const cells = row.querySelectorAll('td');
                cells.forEach(cell => {
                    const html = cell.innerHTML;
                    cell.innerHTML = html.replace(/<mark class="search-highlight">|<\/mark>/g, '');
                });
            }

            // Add CSS for fade-in animation
            const style = document.createElement('style');
            style.textContent = `
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0.5; transform: translateY(2px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .search-highlight {
            background-color: #fff3cd;
            padding: 0 2px;
            border-radius: 2px;
            font-weight: bold;
        }
        
        .user-row {
            transition: all 0.3s ease;
        }
    `;
            document.head.appendChild(style);

            // Add event listener with debounce
            searchInput.addEventListener('input', debounce(performSearch, 200));

            // Add clear button on focus
            searchInput.addEventListener('focus', function () {
                if (this.value) {
                    if (!this.parentNode.querySelector('.search-clear')) {
                        const clearBtn = document.createElement('span');
                        clearBtn.className = 'search-clear';
                        clearBtn.innerHTML = '&times;';
                        clearBtn.style.cssText = `
                    position: absolute;
                    right: 10px;
                    top: 50%;
                    transform: translateY(-50%);
                    cursor: pointer;
                    color: #999;
                    font-size: 18px;
                    z-index: 10;
                `;
                        clearBtn.onclick = function (e) {
                            e.stopPropagation();
                            searchInput.value = '';
                            performSearch();
                            searchInput.focus();
                        };
                        this.parentNode.style.position = 'relative';
                        this.parentNode.appendChild(clearBtn);
                    }
                }
            });

            // Remove clear button when input loses focus if empty
            searchInput.addEventListener('blur', function () {
                const clearBtn = this.parentNode.querySelector('.search-clear');
                if (clearBtn && !this.value) {
                    clearBtn.remove();
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function (e) {
                // Focus search with Ctrl+F or /
                if ((e.ctrlKey && e.key === 'f') || (e.key === '/' && !e.target.matches('input, textarea'))) {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }

                // Clear search with Esc
                if (e.key === 'Escape' && document.activeElement === searchInput && searchInput.value) {
                    searchInput.value = '';
                    performSearch();
                }
            });

            // Add placeholder text that shows search tips
            searchInput.setAttribute('title', 'Press / to focus, Esc to clear');
        });
    </script>
    <script>
        // Verify user function
        function verifyUser(userId, userName) {
            document.getElementById('verifyUserId').value = userId;
            document.getElementById('verifyUserName').textContent = userName;

            // Clear previous values
            document.getElementById('verifyUsername').value = '';
            document.getElementById('verifyPassword').value = '';
            document.getElementById('confirmVerifyPassword').value = '';

            // Show modal
            document.getElementById('verifyUserModal').classList.add('active');
        }

        function closeVerifyUserModal() {
            document.getElementById('verifyUserModal').classList.remove('active');
        }

        function togglePasswordVisibility(fieldId, button) {
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

        function validateVerifyForm() {
            const password = document.getElementById('verifyPassword').value;
            const confirmPassword = document.getElementById('confirmVerifyPassword').value;
            const username = document.getElementById('verifyUsername').value;

            // Check if passwords match
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }

            // Check password strength
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumbers = /\d/.test(password);
            const hasSpecialChar = /[\W_]/.test(password);

            if (!hasUpperCase || !hasLowerCase || !hasNumbers || !hasSpecialChar) {
                alert('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character!');
                return false;
            }

            // Check username format
            const usernameRegex = /^[a-zA-Z0-9_]+$/;
            if (!usernameRegex.test(username)) {
                alert('Username can only contain letters, numbers, and underscores!');
                return false;
            }

            return true;
        }

        function openVerifyUserModal() {
            // Show modal
            document.getElementById('verifyUserModal').classList.add('active');
        }

        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            if (field.type === 'password') {
                field.type = 'text';
            } else {
                field.type = 'password';
            }
        }
        // Send verification invitation
        function sendVerificationInvitation(userId, userName) {
            if (confirm('Send verification invitation to ' + userName + '?\n\nThey will receive an email to set up their account.')) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.style.display = 'none';

                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'invite_user_id';
                userIdInput.value = userId;

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'send_verification_invitation';
                actionInput.value = '1';

                form.appendChild(userIdInput);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Update the openVerifyUserModal function to show pending invitations
        function openVerifyUserModal() {
            // Create a modal showing users pending verification
            const modalHtml = `
    <div class="modal active" id="invitationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-paper-plane"></i> Send Verification Invitations</h3>
                <button class="modal-close" onclick="closeInvitationModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Click the send button next to each user to send them a verification invitation email.</p>
                <div class="table-container" style="max-height: 400px; overflow-y: auto; margin-top: 1rem;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="invitationList">
                            <!-- Users will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeInvitationModal()">Close</button>
            </div>
        </div>
    </div>
    `;

            // Add modal to document
            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalHtml;
            document.body.appendChild(modalContainer);

            // Load users pending verification
            loadPendingUsers();
        }

        function closeInvitationModal() {
            const modal = document.getElementById('invitationModal');
            if (modal) {
                modal.remove();
            }
        }

        function loadPendingUsers() {
            // This would typically fetch from an API, but for simplicity we'll use existing data
            const tbody = document.getElementById('invitationList');
            if (!tbody) return;

            // Get all users from the table (simplified approach)
            // In a real implementation, you would fetch this via AJAX
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem;">Use the send button in the user table above</td></tr>';
        }

        // Function to generate temporary password
        function generateTemporaryPassword($length = 8) {
            // Define character sets
            $lowercase = 'abcdefghijklmnopqrstuvwxyz';
            $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $numbers = '0123456789';
            $symbols = '!@#$%^&*';

            // Ensure at least one character from each set
            $password = '';
            $password.= $lowercase[random_int(0, strlen($lowercase) - 1)];
            $password.= $uppercase[random_int(0, strlen($uppercase) - 1)];
            $password.= $numbers[random_int(0, strlen($numbers) - 1)];
            $password.= $symbols[random_int(0, strlen($symbols) - 1)];

            // Fill remaining characters randomly
            $allChars = $lowercase.$uppercase.$numbers.$symbols;
            for ($i = 4; $i < $length; $i++) {
                $password.= $allChars[random_int(0, strlen($allChars) - 1)];
            }

            // Shuffle the password
            return str_shuffle($password);
        }
    </script>
    <script>
        // Update the resetUserPassword function
        function resetUserPassword(userId, userName) {
            document.getElementById('resetPasswordMessage').innerHTML =
                `Reset password for user: <strong>${userName}</strong>`;
            document.getElementById('resetPasswordUserId').value = userId;

            // Reset form fields
            document.getElementById('resetDefault').checked = true;
            toggleResetPasswordFields();

            document.getElementById('resetPasswordModal').classList.add('active');
        }

        function toggleResetPasswordFields() {
            const defaultOption = document.getElementById('resetDefault').checked;
            const customOption = document.getElementById('resetCustom').checked;
            const generateOption = document.getElementById('resetGenerate').checked;

            document.getElementById('customResetPasswordField').style.display = customOption ? 'block' : 'none';
            document.getElementById('generatedResetPasswordField').style.display = generateOption ? 'block' : 'none';

            if (generateOption) {
                generateResetPassword();
            }
        }

        // Generate random password
        function generatePassword() {
            const length = 12;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
            let password = "";

            // Ensure at least one of each required character type
            password += "ABCDEFGHIJKLMNOPQRSTUVWXYZ".charAt(Math.floor(Math.random() * 26));
            password += "abcdefghijklmnopqrstuvwxyz".charAt(Math.floor(Math.random() * 26));
            password += "0123456789".charAt(Math.floor(Math.random() * 10));
            password += "!@#$%^&*()_+".charAt(Math.floor(Math.random() * 12));

            // Fill the rest randomly
            for (let i = 4; i < length; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }

            // Shuffle the password
            password = password.split('').sort(() => Math.random() - 0.5).join('');

            document.getElementById('generatedPassword').value = password;
            document.getElementById('generatedPasswordHidden').value = password;
        }

        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.remove('active');
        }

        function submitResetPassword() {
            document.getElementById('resetPasswordForm').submit();
        }
    </script>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.addEventListener('click', function () {
                const tabId = this.getAttribute('data-tab');

                // Update URL without page reload
                const url = new URL(window.location);
                url.searchParams.set('tab', tabId);
                window.history.pushState({}, '', url);

                // Remove active class from all tabs
                document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));

                // Add active class to clicked tab
                this.classList.add('active');

                // Show corresponding content
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Mobile sidebar toggle
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarContainer = document.getElementById('sidebar-container');
        const overlay = document.getElementById('overlay');
        const mainContent = document.querySelector('.main-content');

        function toggleSidebar() {
            const isActive = sidebarContainer.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = isActive ? 'hidden' : '';

            // Adjust main content margin on mobile
            if (window.innerWidth <= 768) {
                if (isActive) {
                    mainContent.style.marginLeft = '260px';
                } else {
                    mainContent.style.marginLeft = '0';
                }
            }
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }

        if (overlay) {
            overlay.addEventListener('click', toggleSidebar);
        }

        // User dropdown functionality
        const userMenuButton = document.getElementById('user-menu-button');
        const userDropdown = document.getElementById('user-dropdown');

        if (userMenuButton && userDropdown) {
            userMenuButton.addEventListener('click', function (e) {
                e.stopPropagation();
                userDropdown.classList.toggle('active');
            });

            document.addEventListener('click', function () {
                userDropdown.classList.remove('active');
            });

            userDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        // Payroll dropdown in sidebar
        const payrollToggle = document.getElementById('payroll-toggle');
        const payrollDropdown = document.getElementById('payroll-dropdown');

        if (payrollToggle) {
            payrollToggle.addEventListener('click', function (e) {
                e.preventDefault();
                payrollDropdown.classList.toggle('show');
                this.querySelector('.chevron').classList.toggle('rotate');
            });
        }

        // Update date and time
        function updateDateTime() {
            const now = new Date();
            const dateOptions = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };

            const dateElement = document.getElementById('current-date');
            const timeElement = document.getElementById('current-time');

            if (dateElement) {
                dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
            }
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
            }
        }

        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Modal functions
        function openAddUserModal() {
            document.getElementById('addUserModal').classList.add('active');
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').classList.remove('active');
        }

        function editUser(userId, userData) {
            // Fill the form with existing user data
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUserFirstName').value = userData.first_name || '';
            document.getElementById('editUserMiddleName').value = userData.middle_name || '';
            document.getElementById('editUserLastName').value = userData.last_name || '';
            document.getElementById('editUserEmail').value = userData.email || '';
            document.getElementById('editUserRole').value = userData.role || 'user';
            document.getElementById('editUserActive').checked = userData.is_active == 1;

            // Add employment type if it exists
            if (userData.employment_type) {
                // Check if employment type field exists, if not create it
                let empTypeField = document.getElementById('editUserEmploymentType');
                if (!empTypeField) {
                    const roleField = document.getElementById('editUserRole').parentElement;
                    const empTypeHtml = `
                <div class="form-group">
                    <label class="form-label">Employment Type</label>
                    <select class="form-control form-select" name="employment_type" id="editUserEmploymentType">
                        <option value="permanent">Permanent</option>
                        <option value="job_order">Job Order</option>
                        <option value="contract_of_service">Contract of Service</option>
                    </select>
                </div>
            `;
                    roleField.insertAdjacentHTML('afterend', empTypeHtml);
                }
                document.getElementById('editUserEmploymentType').value = userData.employment_type;
            }

            document.getElementById('editUserModal').classList.add('active');
        }

        function closeEditUserModal() {
            document.getElementById('editUserModal').classList.remove('active');
        }

        function editDepartment(deptId) {
            fetch('get_department.php?id=' + deptId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('editDeptId').value = data.id;
                    document.getElementById('editDeptName').value = data.dept_name;
                    document.getElementById('editDeptCode').value = data.dept_code;
                    document.getElementById('editDeptHead').value = data.dept_head || '';
                    document.getElementById('editDepartmentModal').classList.add('active');
                })
                .catch(error => {
                    alert('Error loading department data');
                });
        }

        function closeEditDepartmentModal() {
            document.getElementById('editDepartmentModal').classList.remove('active');
        }

        function openResetModal() {
            document.getElementById('resetModal').classList.add('active');
        }

        function closeResetModal() {
            document.getElementById('resetModal').classList.remove('active');
        }

        function openDeleteModal(type, id, name) {
            if (type === 'user') {
                document.getElementById('deleteMessage').textContent = `Are you sure you want to delete user "${name}"? This action cannot be undone.`;
                document.getElementById('deleteUserId').value = id;
                document.getElementById('deleteDeptId').value = '';
                document.getElementById('deleteForm').action = '';
                document.getElementById('deleteForm').method = 'POST';
                document.getElementById('deleteForm').innerHTML = `
                    <input type="hidden" name="user_id" value="${id}">
                    <input type="hidden" name="delete_user" value="1">
                `;
            } else if (type === 'department') {
                document.getElementById('deleteMessage').textContent = `Are you sure you want to delete department "${name}"? This action cannot be undone.`;
                document.getElementById('deleteUserId').value = '';
                document.getElementById('deleteDeptId').value = id;
                document.getElementById('deleteForm').action = '';
                document.getElementById('deleteForm').method = 'POST';
                document.getElementById('deleteForm').innerHTML = `
                    <input type="hidden" name="dept_id" value="${id}">
                    <input type="hidden" name="delete_department" value="1">
                `;
            }
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        function submitDelete() {
            document.getElementById('deleteForm').submit();
        }

        function openResetPasswordModal(userId, userName) {
            document.getElementById('resetPasswordMessage').textContent = `Are you sure you want to reset password for user "${userName}"? The new password will be "Password123!".`;
            document.getElementById('resetPasswordUserId').value = userId;
            document.getElementById('resetPasswordModal').classList.add('active');
        }

        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.remove('active');
        }

        function submitResetPassword() {
            document.getElementById('resetPasswordForm').innerHTML = `
                <input type="hidden" name="user_id" value="${document.getElementById('resetPasswordUserId').value}">
                <input type="hidden" name="reset_password" value="1">
            `;
            document.getElementById('resetPasswordForm').submit();
        }

        function deleteUser(userId, username) {
            if (confirm('Are you sure you want to delete user: ' + username + '?')) {
                // Send request with username parameter
                fetch('/api/delete-user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: userId,
                        username: username // Add username here
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message);
                        }
                    });
            }
        }

        function resetUserPassword(userId) {
            const userName = prompt('Enter the user\'s name to confirm password reset:');
            if (userName) {
                openResetPasswordModal(userId, userName);
            }
        }

        // Department management functions
        function deleteDepartment(deptId) {
            const deptName = prompt('Enter the department name to confirm deletion:');
            if (deptName) {
                openDeleteModal('department', deptId, deptName);
            }
        }

        // Backup functions
        function downloadBackup(filename) {
            window.location.href = 'download_backup.php?file=' + encodeURIComponent(filename);
        }

        // Export settings
        function exportSettings() {
            const data = {
                system_settings: <?php echo json_encode($system_settings); ?>,
                email_settings: <?php echo json_encode($email_settings); ?>,
                user_profile: <?php echo json_encode($user_profile); ?>,
                exported_at: new Date().toISOString(),
                exported_by: "<?php echo $user_name; ?>"
            };

            const blob = new Blob([JSON.stringify(data, null, 2)], {
                type: 'application/json'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'hrms-settings-' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        function confirmReset() {
            const confirmText = document.getElementById('resetConfirm').value;
            if (confirmText === 'RESET') {
                // Submit reset form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.innerHTML = '<input type="hidden" name="reset_settings" value="1">';
                document.body.appendChild(form);
                form.submit();
            } else {
                alert('Please type "RESET" exactly as shown to confirm.');
            }
        }

        // Auto-hide success messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.classList.contains('alert-success')) {
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 300);
                    }
                });
            }, 5000);

            // Password validation
            const passwordForm = document.getElementById('passwordForm');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function (e) {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;

                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('New passwords do not match!');
                        return;
                    }

                    // Password strength validation
                    const hasUpperCase = /[A-Z]/.test(newPassword);
                    const hasLowerCase = /[a-z]/.test(newPassword);
                    const hasNumbers = /\d/.test(newPassword);
                    const hasSpecialChar = /[\W_]/.test(newPassword);

                    if (!hasUpperCase || !hasLowerCase || !hasNumbers || !hasSpecialChar) {
                        e.preventDefault();
                        alert('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character!');
                        return;
                    }
                });
            }
        });

        // Handle window resize
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768 && sidebarContainer.classList.contains('active')) {
                sidebarContainer.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
                mainContent.style.marginLeft = '260px';
            }
        });

        // Handle browser back/forward for tabs
        window.addEventListener('popstate', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || '<?php echo $is_admin ? "system" : "profile"; ?>';

            // Update active tab
            document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));

            const activeTab = document.querySelector(`.settings-tab[data-tab="${tab}"]`);
            const activeContent = document.getElementById(tab);

            if (activeTab) activeTab.classList.add('active');
            if (activeContent) activeContent.classList.add('active');
        });
    </script>
    <script>
        // Password field toggling
        function togglePasswordFields() {
            const defaultOption = document.getElementById('passwordDefault').checked;
            const customOption = document.getElementById('passwordCustom').checked;
            const generateOption = document.getElementById('passwordGenerate').checked;

            document.getElementById('customPasswordField').style.display = customOption ? 'block' : 'none';
            document.getElementById('generatedPasswordField').style.display = generateOption ? 'block' : 'none';

            if (generateOption) {
                generatePassword();
            }
        }

        // Generate random password
        function generatePassword() {
            const length = 12;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
            let password = "";

            // Ensure at least one of each required character type
            password += "ABCDEFGHIJKLMNOPQRSTUVWXYZ".charAt(Math.floor(Math.random() * 26));
            password += "abcdefghijklmnopqrstuvwxyz".charAt(Math.floor(Math.random() * 26));
            password += "0123456789".charAt(Math.floor(Math.random() * 10));
            password += "!@#$%^&*()_+".charAt(Math.floor(Math.random() * 12));

            // Fill the rest randomly
            for (let i = 4; i < length; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }

            // Shuffle the password
            password = password.split('').sort(() => Math.random() - 0.5).join('');

            document.getElementById('generatedPassword').value = password;
            document.getElementById('generatedPasswordHidden').value = password;
        }

        // Copy generated password
        function copyGeneratedPassword() {
            const passwordField = document.getElementById('generatedPassword');
            passwordField.select();
            document.execCommand('copy');
            alert('Password copied to clipboard!');
        }

        // Check password strength
        function checkPasswordStrength(password) {
            const strengthDiv = document.getElementById('passwordStrength');
            let strength = 0;
            let messages = [];

            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[\W_]/.test(password)) strength++;

            if (strength === 5) {
                strengthDiv.innerHTML = '<span style="color: green;"><i class="fas fa-check-circle"></i> Strong password</span>';
            } else if (strength >= 3) {
                strengthDiv.innerHTML = '<span style="color: orange;"><i class="fas fa-exclamation-circle"></i> Medium password</span>';
            } else {
                strengthDiv.innerHTML = '<span style="color: red;"><i class="fas fa-times-circle"></i> Weak password</span>';
            }
        }

        // Toggle password visibility
        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const button = event.target.closest('button');

            if (field.type === 'password') {
                field.type = 'text';
                button.innerHTML = '<i class="fas fa-eye-slash"></i> Hide Password';
            } else {
                field.type = 'password';
                button.innerHTML = '<i class="fas fa-eye"></i> Show Password';
            }
        }

        // Initialize on modal open
        document.addEventListener('DOMContentLoaded', function () {
            const addUserModal = document.getElementById('addUserModal');
            if (addUserModal) {
                addUserModal.addEventListener('show.bs.modal', function () {
                    togglePasswordFields();
                });
            }

            // Initialize form validation
            const addUserForm = document.getElementById('addUserForm');
            if (addUserForm) {
                addUserForm.addEventListener('submit', function (e) {
                    const passwordOption = document.querySelector('input[name="password_option"]:checked').value;

                    if (passwordOption === 'custom') {
                        const customPassword = document.getElementById('customPassword').value;
                        const passwordErrors = validatePassword(customPassword);

                        if (passwordErrors.length > 0) {
                            e.preventDefault();
                            alert('Password errors:\n' + passwordErrors.join('\n'));
                            return false;
                        }
                    }

                    return true;
                });
            }
        });

        // Client-side password validation
        function validatePassword(password) {
            const errors = [];

            if (password.length < 8) {
                errors.push("Password must be at least 8 characters");
            }
            if (!/[A-Z]/.test(password)) {
                errors.push("Password must contain at least one uppercase letter");
            }
            if (!/[a-z]/.test(password)) {
                errors.push("Password must contain at least one lowercase letter");
            }
            if (!/[0-9]/.test(password)) {
                errors.push("Password must contain at least one number");
            }
            if (!/[\W_]/.test(password)) {
                errors.push("Password must contain at least one special character");
            }

            return errors;
        }
    </script>
</body>

</html>

<?php
// Helper functions
function formatFileSize($bytes)
{
    if ($bytes == 0)
        return "0 Bytes";
    $k = 1024;
    $sizes = ["Bytes", "KB", "MB", "GB", "TB"];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . " " . $sizes[$i];
}