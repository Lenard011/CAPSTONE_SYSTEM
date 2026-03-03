<?php
// Set session security headers BEFORE any output
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Check if user is logged in - MATCHING DASHBOARD.PHP
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Set user variables from session - MATCHING DASHBOARD.PHP
$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['user_email'] ?? 'user@paluan.gov.ph';
$user_role = $_SESSION['user_role'] ?? 'user';
$is_admin = in_array($user_role, ['admin', 'manager']); // Admin or manager have elevated access

// Check if user has admin access
if (!$is_admin) {
    // Non-admin users can only access profile settings
    if (isset($_GET['tab']) && !in_array($_GET['tab'], ['profile', 'security'])) {
        header('Location: settings.php?tab=profile');
        exit();
    }
}

// Logout functionality - MATCHING DASHBOARD.PHP
if (isset($_GET['logout'])) {
    // Database connection for logging
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

    header('Location: login.php');
    exit();
}

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

// Include mailer.php for email functionality
$mailerPath = __DIR__ . '/mailer.php';
if (file_exists($mailerPath)) {
    require_once $mailerPath;
} else {
    $mailerPath = '../mailer.php';
    if (file_exists($mailerPath)) {
        require_once $mailerPath;
    }
}

// Initialize messages
$success_message = "";
$error_message = "";

// Handle Profile Update
if (isset($_POST['update_profile'])) {
    $first_name = trim($conn->real_escape_string($_POST['first_name']));
    $middle_name = trim($conn->real_escape_string($_POST['middle_name'] ?? ''));
    $last_name = trim($conn->real_escape_string($_POST['last_name']));
    $email = trim($conn->real_escape_string($_POST['email']));
    $mobile_number = trim($conn->real_escape_string($_POST['mobile_number'] ?? ''));

    // Validation
    $errors = [];

    if (empty($first_name)) {
        $errors[] = "First name is required";
    }

    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Validate mobile number if provided (Philippine format)
    if (!empty($mobile_number) && !preg_match('/^(09|\+639)\d{9}$/', preg_replace('/\s+/', '', $mobile_number))) {
        $errors[] = "Please enter a valid Philippine mobile number (e.g., 09123456789 or +639123456789)";
    }

    // Check if email already exists (excluding current user)
    if (!empty($email)) {
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $email, $user_id);
        $check_email->execute();
        $email_result = $check_email->get_result();

        if ($email_result->num_rows > 0) {
            $errors[] = "Email address is already in use by another account";
        }
        $check_email->close();
    }

    if (empty($errors)) {
        // Create full name
        $full_name = trim($first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name);

        // Update users table
        $sql = "UPDATE users SET 
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                full_name = ?,
                email = ?,
                mobile_number = ?,
                updated_at = NOW()
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssi",
            $first_name,
            $middle_name,
            $last_name,
            $full_name,
            $email,
            $mobile_number,
            $user_id
        );

        if ($stmt->execute()) {
            // Update session variables
            $_SESSION['user_name'] = $full_name;
            $_SESSION['user_email'] = $email;

            // Log the action
            logAuditTrail($conn, $user_id, 'update', 'Updated profile information');

            $_SESSION['profile_success'] = "Profile updated successfully!";

            // Refresh user data
            $refresh_sql = "SELECT * FROM users WHERE id = ?";
            $refresh_stmt = $conn->prepare($refresh_sql);
            $refresh_stmt->bind_param("i", $user_id);
            $refresh_stmt->execute();
            $refresh_result = $refresh_stmt->get_result();
            if ($refresh_result->num_rows > 0) {
                $user_profile = $refresh_result->fetch_assoc();
            }
            $refresh_stmt->close();
        } else {
            $_SESSION['profile_error'] = "Error updating profile: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['profile_error'] = implode("<br>", $errors);
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=profile");
    exit();
}

// Handle Profile Picture Upload
if (isset($_POST['update_profile_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        $file_name = $_FILES['profile_picture']['name'];
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_type = $_FILES['profile_picture']['type'];

        // Get file extension
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['profile_error'] = "Only JPG, PNG, and GIF files are allowed";
        }
        // Validate file size
        elseif ($file_size > $max_size) {
            $_SESSION['profile_error'] = "File size must be less than 2MB";
        } else {
            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Get current user data to check for existing profile image
            $current_sql = "SELECT profile_image FROM users WHERE id = ?";
            $current_stmt = $conn->prepare($current_sql);
            $current_stmt->bind_param("i", $user_id);
            $current_stmt->execute();
            $current_result = $current_stmt->get_result();
            $current_data = $current_result->fetch_assoc();
            $current_stmt->close();

            // Generate unique filename
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;

            // Delete old profile picture if exists
            if (!empty($current_data['profile_image'])) {
                $old_file = $upload_dir . $current_data['profile_image'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }

            // Upload new file
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Update database with new profile picture filename
                $update_sql = "UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $new_filename, $user_id);

                if ($update_stmt->execute()) {
                    $_SESSION['profile_success'] = "Profile picture updated successfully!";
                    logAuditTrail($conn, $user_id, 'update', 'Updated profile picture');
                } else {
                    $_SESSION['profile_error'] = "Database error: " . $conn->error;
                }
                $update_stmt->close();
            } else {
                $_SESSION['profile_error'] = "Failed to upload file";
            }
        }
    } else {
        $_SESSION['profile_error'] = "Please select a file to upload";
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=profile");
    exit();
}

// Create upload directory if it doesn't exist
$upload_dir = '../uploads/profile_pictures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
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

// Function to generate temporary password
function generateTemporaryPassword($length = 8)
{
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $symbols = '!@#$%^&*';

    $password = '';
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $symbols[random_int(0, strlen($symbols) - 1)];

    $allChars = $lowercase . $uppercase . $numbers . $symbols;
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }

    return str_shuffle($password);
}

// Handle Send Verification Invitation
if (isset($_POST['send_verification_invitation']) && $is_admin) {
    $invite_user_id = (int) $_POST['invite_user_id'];

    // Get user info
    $sql = "SELECT email, full_name, employee_id, employee_type FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $invite_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmt->close();

        $temp_password = generateTemporaryPassword(8);
        $temp_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
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
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
            $login_url = $base_url . dirname($_SERVER['PHP_SELF']) . "/login.php";
            $login_url = preg_replace('/([^:])(\/{2,})/', '$1/', $login_url);

            if (class_exists('Mailer')) {
                try {
                    $mailer = new Mailer();
                    $subject = "Your HRMS Account is Ready - Login Credentials";

                    $body = "
                    <html>
                    <body>
                        <h2>Hello " . htmlspecialchars($user['full_name']) . ",</h2>
                        <p>Your HRMS account has been created. Here are your login credentials:</p>
                        <p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>
                        <p><strong>Temporary Password:</strong> <code>" . htmlspecialchars($temp_password) . "</code></p>
                        <p><strong>Login URL:</strong> <a href='" . htmlspecialchars($login_url) . "'>" . htmlspecialchars($login_url) . "</a></p>
                        <p>Please change your password after first login.</p>
                    </body>
                    </html>
                    ";

                    $email_result = $mailer->sendGeneralEmail(
                        $user['email'],
                        $user['full_name'],
                        $subject,
                        $body
                    );

                    if ($email_result['success'] && $email_result['email_sent']) {
                        $success_message = "Verification invitation sent successfully to " . htmlspecialchars($user['full_name']) . "!";
                    } else {
                        $success_message = "User updated but email failed to send. Password: <code>$temp_password</code>";
                    }
                    logAuditTrail($conn, $user_id, 'invite', "Sent verification invitation to: " . $user['full_name']);
                } catch (Exception $e) {
                    $success_message = "User updated but mailer error. Password: <code>$temp_password</code>";
                    logAuditTrail($conn, $user_id, 'invite', "Created credentials for: " . $user['full_name'] . " (mailer error)");
                }
            } else {
                $success_message = "User updated. Password: <code>$temp_password</code>";
                logAuditTrail($conn, $user_id, 'invite', "Created credentials for: " . $user['full_name']);
            }

            $stmt->close();
        } else {
            $error_message = "Error sending invitation: " . $conn->error;
        }
    } else {
        $error_message = "User not found!";
    }
}

// Handle Password Change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required!";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match!";
    } else {
        $password_errors = validatePassword($new_password);
        if (!empty($password_errors)) {
            $error_message = implode("<br>", $password_errors);
        } else {
            // Get current password hash
            $sql = "SELECT password_hash FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (password_verify($current_password, $row['password_hash'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    $sql = "UPDATE users SET 
                            password_hash = ?,
                            last_password_change = NOW(),
                            updated_at = NOW()
                            WHERE id = ?";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $hashed_password, $user_id);

                    if ($stmt->execute()) {
                        $success_message = "Password changed successfully!";
                        logAuditTrail($conn, $user_id, 'update', 'Changed password');
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
}

// Handle Add User
if (isset($_POST['add_user']) && $is_admin) {
    $new_full_name = $conn->real_escape_string($_POST['new_full_name']);
    $new_email = $conn->real_escape_string($_POST['new_email']);
    $new_role = $conn->real_escape_string($_POST['new_role']);
    $employee_type = $conn->real_escape_string($_POST['employee_type'] ?? 'permanent');
    $employee_id = $conn->real_escape_string($_POST['employee_id'] ?? '');
    $department = $conn->real_escape_string($_POST['department'] ?? '');
    $position = $conn->real_escape_string($_POST['position'] ?? '');

    // Generate temporary password
    $temp_password = generateTemporaryPassword(8);
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    $temp_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Parse full name
    $name_parts = explode(' ', trim($new_full_name), 2);
    $first_name = $name_parts[0];
    $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

    // Generate username from email
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
        $prefix = match ($employee_type) {
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

            $access_level = match ($new_role) {
                'admin' => 'full',
                'manager' => 'elevated',
                default => 'restricted'
            };

            // Insert into users table
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
                employee_type,
                department,
                position,
                is_active,
                is_verified,
                password_is_temporary,
                temporary_password_expiry,
                must_change_password,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?, ?, ?, 1, 1, 1, ?, 1, 'approved', NOW())";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param(
                    "sssssssssssssss",
                    $username,
                    $new_email,
                    $hashed_password,
                    $first_name,
                    $last_name,
                    $new_full_name,
                    $new_role,
                    $access_level,
                    $employee_id,
                    $employee_type,
                    $department,
                    $position,
                    $temp_expiry
                );

                if ($stmt->execute()) {
                    $new_user_id = $stmt->insert_id;
                    $success_message = "User added successfully!<br>";
                    $success_message .= "• Username: $username<br>";
                    $success_message .= "• Employee ID: $employee_id<br>";
                    $success_message .= "• Employee Type: " . ucfirst(str_replace('_', ' ', $employee_type)) . "<br>";
                    $success_message .= "• Temporary password: $temp_password<br>";

                    logAuditTrail($conn, $user_id, 'create', "Added new user: $new_full_name ($employee_type)");

                    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                    $login_url = $base_url . dirname($_SERVER['PHP_SELF']) . "/login.php";
                    $login_url = preg_replace('/([^:])(\/{2,})/', '$1/', $login_url);
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

// Handle Update User
if (isset($_POST['update_user']) && $is_admin) {
    $update_user_id = (int) $_POST['user_id'];
    $update_role = $conn->real_escape_string($_POST['role']);
    $update_is_active = isset($_POST['is_active']) ? 1 : 0;
    $update_access_level = $conn->real_escape_string($_POST['access_level'] ?? 'restricted');

    $sql = "UPDATE users SET 
            role = ?,
            access_level = ?,
            is_active = ?,
            updated_at = NOW() 
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $update_role, $update_access_level, $update_is_active, $update_user_id);

    if ($stmt->execute()) {
        $success_message = "User updated successfully!";
        logAuditTrail($conn, $user_id, 'update', 'Updated user ID: ' . $update_user_id);
    } else {
        $error_message = "Error updating user: " . $conn->error;
    }
    $stmt->close();
}

// Handle Reset User Password
if (isset($_POST['reset_user_password']) && $is_admin) {
    $reset_user_id = (int) $_POST['user_id'];
    $reset_option = $_POST['reset_password_option'] ?? 'default';
    $custom_reset_password = $_POST['custom_reset_password'] ?? '';
    $generated_reset_password = $_POST['generated_reset_password'] ?? '';

    $sql = "SELECT email, full_name FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $reset_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($reset_option === 'custom' && !empty($custom_reset_password)) {
            $new_password = $custom_reset_password;
        } elseif ($reset_option === 'generate' && !empty($generated_reset_password)) {
            $new_password = $generated_reset_password;
        } else {
            $new_password = 'Password123!';
        }

        $password_errors = validatePassword($new_password);
        if (empty($password_errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $sql = "UPDATE users SET password_hash = ?, last_password_change = NULL, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $hashed_password, $reset_user_id);

            if ($stmt->execute()) {
                $success_message = "Password reset successfully!";
                if ($reset_option !== 'custom') {
                    $success_message .= "<br>New password: $new_password";
                }
                logAuditTrail($conn, $user_id, 'update', 'Reset password for user: ' . $user['email']);
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

// Fetch current user profile
$user_profile = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'email' => $user_email,
    'mobile_number' => '',
    'username' => '',
    'role' => $user_role,
    'created_at' => '',
    'profile_image' => ''
];

$sql = "SELECT id, username, email, first_name, middle_name, last_name, 
               mobile_number, profile_image, role, created_at 
        FROM users 
        WHERE id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    $user_profile = array_merge($user_profile, $user_data);
}
$stmt->close();

// Get audit logs
$audit_logs = [];
if ($is_admin) {
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
}

// Get users for user management
$users = [];
if ($is_admin) {
    $sql = "SELECT id, username, email, full_name, employee_id, 
                   employee_type,
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
}

// Get current tab
$default_tab = $is_admin ? 'users' : 'profile';
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;

$valid_tabs = ['profile', 'security'];
if ($is_admin) {
    $valid_tabs = array_merge($valid_tabs, ['users', 'logs']);
}

if (!in_array($current_tab, $valid_tabs)) {
    $current_tab = $default_tab;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - HRMS Paluan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Add Flowbite CSS for consistency with dashboard -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.1.1/flowbite.min.css" rel="stylesheet">
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
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-card: 0 8px 30px rgba(0, 0, 0, 0.08);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f8fafc 100%);
            min-height: 100vh;
            color: var(--dark);
        }

        /* Navigation - Matching dashboard.php */
        .navbar {
            background: var(--gradient-primary);
            box-shadow: var(--shadow-md);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;
            backdrop-filter: blur(10px);
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
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            color: white;
        }

        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        .mobile-toggle i {
            font-size: 1.25rem;
            color: white;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
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
            border-radius: var(--radius-md);
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
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

        /* Sidebar - Matching dashboard.php */
        .sidebar-container {
            position: fixed;
            left: 0;
            top: 70px;
            width: 260px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            z-index: 999;
            transition: transform 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .sidebar {
            height: 100%;
            padding: 1.5rem 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-content {
            flex: 1;
            padding: 0 1rem;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 1.25rem;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: var(--transition);
            border-radius: var(--radius-md);
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

        .sidebar-dropdown-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            margin-left: 2.5rem;
        }

        .sidebar-dropdown-menu.open {
            max-height: 500px;
        }

        .sidebar-dropdown-item {
            display: flex;
            align-items: center;
            padding: 0.7rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .sidebar-dropdown-item:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-dropdown-item i {
            font-size: 0.7rem;
            margin-right: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            transition: color 0.3s ease;
        }

        .sidebar-dropdown-item:hover i {
            color: white;
        }

        .chevron {
            transition: transform 0.3s ease;
        }

        .chevron.rotated {
            transform: rotate(180deg);
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: white;
        }

        .sidebar-item.logout {
            color: #fecaca;
            margin-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
        }

        .sidebar-item.logout:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #fecaca;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 2rem;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.3s ease;
            background: linear-gradient(135deg, #f8fafc 0%, #f0f9ff 50%, #e0f2fe 100%);
        }

        /* Overlay for mobile */
        .overlay {
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 998;
            display: none;
        }

        .overlay.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
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
            font-size: clamp(1.5rem, 3vw, 2rem);
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

        /* Settings Card */
        .settings-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: var(--gradient-primary);
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
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: var(--transition);
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
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
            transform: translateY(-2px);
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
            border-radius: var(--radius-md);
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
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger);
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius-lg);
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

        .badge-primary {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-secondary {
            background: #f3f4f6;
            color: #4b5563;
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
            border-radius: var(--radius-2xl);
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
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--danger);
            transform: rotate(90deg);
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

        /* Profile Picture Styles */
        .profile-picture-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-picture-container {
            position: relative;
            display: inline-block;
        }

        #profileImage {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            box-shadow: var(--shadow-md);
        }

        .camera-button {
            position: absolute;
            bottom: 10px;
            right: 10px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: white;
            border: 2px solid white;
            transition: var(--transition);
        }

        .camera-button:hover {
            transform: scale(1.1);
            background: var(--primary-dark);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h3 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-bottom: 2px solid var(--gray-200);
            padding-bottom: 0.5rem;
        }

        .text-danger {
            color: var(--danger);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1.5rem;
            }

            .sidebar-container {
                transform: translateX(-100%);
            }

            .sidebar-container.active {
                transform: translateX(0);
            }

            .mobile-toggle {
                display: flex;
            }

            .navbar-right .datetime-container {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .main-content {
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

            .modal-content {
                margin: 1rem;
            }
        }

        @media (max-width: 480px) {
            .navbar-container {
                padding: 0 1rem;
            }

            .brand-title {
                font-size: 0.9rem;
            }

            .brand-subtitle {
                display: none;
            }

            .card-body {
                padding: 1rem;
            }

            .modal-content {
                margin: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation Header - Matching dashboard.php -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Left Section -->
            <div class="navbar-left">
                <!-- Mobile Menu Toggle -->
                <button class="mobile-toggle" id="sidebar-toggle" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Logo and Brand -->
                <a href="dashboard.php" class="navbar-brand">
                    <img class="brand-logo"
                        src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                        alt="Paluan LGU Logo">
                    <div class="brand-text">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Municipality of Paluan</span>
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
                            <span class="datetime-value" id="current-date"><?php echo date('F j, Y'); ?></span>
                        </div>
                    </div>

                    <div class="datetime-box">
                        <i class="datetime-icon fas fa-clock"></i>
                        <div class="datetime-text">
                            <span class="datetime-label">Time</span>
                            <span class="datetime-value" id="current-time"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar - Matching dashboard.php -->
    <div class="sidebar-container" id="sidebar-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <!-- Dashboard -->
                <a href="dashboard.php" class="sidebar-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
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

                <!-- Payroll Dropdown -->
                <a href="#" class="sidebar-item" id="payroll-toggle">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                    <i class="fas fa-chevron-down chevron ml-auto"></i>
                </a>
                <div class="sidebar-dropdown-menu" id="payroll-dropdown">
                    <a href="Payrollmanagement/contractualpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Contractual
                    </a>
                    <a href="Payrollmanagement/joborderpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Job Order
                    </a>
                    <a href="Payrollmanagement/permanentpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Permanent
                    </a>
                </div>

                <!-- Settings -->
                <a href="settings.php" class="sidebar-item active">
                    <i class="fas fa-sliders-h"></i>
                    <span>Settings</span>
                </a>

                <!-- Logout -->
                <a href="?logout=true" class="sidebar-item logout" id="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>

            <div class="sidebar-footer">
                <p>HRMS v4.1</p>
                <p style="font-size: 0.7rem;">© <?php echo date('Y'); ?> Paluan LGU</p>
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
                    <?php if ($is_admin): ?>
                        <button class="btn btn-success" onclick="openAddUserModal()">
                            <i class="fas fa-user-plus"></i>
                            Add User
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="settings-tab <?php echo $current_tab == 'profile' ? 'active' : ''; ?>" data-tab="profile">
                    <i class="fas fa-user"></i>
                    Profile
                </button>
                <button class="settings-tab <?php echo $current_tab == 'security' ? 'active' : ''; ?>" data-tab="security">
                    <i class="fas fa-shield-alt"></i>
                    Security
                </button>
                <?php if ($is_admin): ?>
                    <button class="settings-tab <?php echo $current_tab == 'users' ? 'active' : ''; ?>" data-tab="users">
                        <i class="fas fa-users"></i>
                        User Management
                    </button>
                    <button class="settings-tab <?php echo $current_tab == 'logs' ? 'active' : ''; ?>" data-tab="logs">
                        <i class="fas fa-history"></i>
                        Audit Logs
                    </button>
                <?php endif; ?>
            </div>

            <!-- Profile Tab -->
            <div id="profile" class="tab-content <?php echo $current_tab == 'profile' ? 'active' : ''; ?>">
                <div class="settings-card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-cog"></i> User Profile</h2>
                    </div>
                    <div class="card-body">
                        <!-- Profile Picture Section -->
                        <div class="profile-picture-section">
                            <div class="profile-picture-container">
                                <?php
                                $profile_pic = !empty($user_profile['profile_image'])
                                    ? '../uploads/profile_pictures/' . htmlspecialchars($user_profile['profile_image'])
                                    : 'https://ui-avatars.com/api/?name=' . urlencode($user_profile['first_name'] ?? 'User') . '+' . urlencode($user_profile['last_name'] ?? '') . '&size=150&background=1e40af&color=fff&bold=true';
                                ?>
                                <img src="<?php echo $profile_pic; ?>" alt="Profile Picture" id="profileImage">
                                <button type="button" class="btn btn-primary btn-sm camera-button" onclick="openProfilePictureModal()">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            <div style="margin-top: 0.5rem;">
                                <small class="text-muted">Click camera icon to update profile picture</small>
                            </div>
                        </div>

                        <!-- Profile Information Form -->
                        <form method="POST" action="" id="profileForm">
                            <input type="hidden" name="update_profile" value="1">

                            <div class="form-section">
                                <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="first_name">First Name <span>*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name"
                                            value="<?php echo htmlspecialchars($user_profile['first_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="middle_name">Middle Name</label>
                                        <input type="text" class="form-control" id="middle_name" name="middle_name"
                                            value="<?php echo htmlspecialchars($user_profile['middle_name'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="last_name">Last Name <span>*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name"
                                            value="<?php echo htmlspecialchars($user_profile['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3><i class="fas fa-address-card"></i> Contact Information</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="email">Email Address <span>*</span></label>
                                        <input type="email" class="form-control" id="email" name="email"
                                            value="<?php echo htmlspecialchars($user_profile['email']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="mobile_number">Mobile Number</label>
                                        <input type="tel" class="form-control" id="mobile_number" name="mobile_number"
                                            value="<?php echo htmlspecialchars($user_profile['mobile_number'] ?? ''); ?>"
                                            placeholder="09XXXXXXXXX">
                                    </div>
                                </div>
                            </div>

                            <div class="form-section" style="background-color: var(--gray-50); padding: 1rem; border-radius: var(--radius-md);">
                                <h3><i class="fas fa-info-circle"></i> Account Information</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control"
                                            value="<?php echo htmlspecialchars($user_profile['username'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Role</label>
                                        <input type="text" class="form-control"
                                            value="<?php echo ucfirst($user_profile['role'] ?? 'User'); ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Member Since</label>
                                        <input type="text" class="form-control"
                                            value="<?php echo isset($user_profile['created_at']) ? date('F d, Y', strtotime($user_profile['created_at'])) : 'N/A'; ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="display: flex; gap: 1rem; margin-top: 2rem;">
                                <button type="submit" class="btn btn-primary" id="saveProfileBtn">
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
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="new_password">New Password <span>*</span></label>
                                <input type="password" class="form-control" id="new_password" name="new_password"
                                    minlength="8" required>
                                <div class="form-text">Password must be at least 8 characters with uppercase, lowercase,
                                    number, and special character.</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="confirm_password">Confirm New Password <span>*</span></label>
                                <input type="password" class="form-control" id="confirm_password"
                                    name="confirm_password" required>
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
                <!-- User Management Tab -->
                <div id="users" class="tab-content <?php echo $current_tab == 'users' ? 'active' : ''; ?>">
                    <div class="settings-card">
                        <div class="card-header">
                            <h2><i class="fas fa-users"></i> User Management</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Employee ID</th>
                                            <th>Email</th>
                                            <th>Username</th>
                                            <th>Employee Type</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <?php
                                            $emp_type = $user['employee_type'] ?? 'Not set';
                                            $emp_type_display = ucfirst(str_replace('_', ' ', $emp_type));

                                            $emp_badge = 'badge-info';
                                            if ($emp_type == 'permanent') $emp_badge = 'badge-success';
                                            elseif ($emp_type == 'job_order') $emp_badge = 'badge-warning';
                                            elseif ($emp_type == 'contract_of_service') $emp_badge = 'badge-primary';
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['employee_id'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $emp_badge; ?>">
                                                        <?php echo $emp_type_display; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $user['role'] == 'admin' ? 'badge-danger' : ($user['role'] == 'manager' ? 'badge-warning' : 'badge-info'); ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                                        <button class="btn btn-secondary btn-sm" onclick="editUser(<?php echo $user['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if (!$user['is_verified']): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="invite_user_id" value="<?php echo $user['id']; ?>">
                                                                <input type="hidden" name="send_verification_invitation" value="1">
                                                                <button type="submit" class="btn btn-success btn-sm" title="Send verification">
                                                                    <i class="fas fa-paper-plane"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <button class="btn btn-warning btn-sm" onclick="resetUserPassword(<?php echo $user['id']; ?>, '<?php echo addslashes($user['full_name']); ?>')">
                                                            <i class="fas fa-key"></i>
                                                        </button>
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
                                                        if ($log['action_type'] == 'login') $badge_class = 'badge-success';
                                                        elseif ($log['action_type'] == 'logout') $badge_class = 'badge-warning';
                                                        elseif ($log['action_type'] == 'delete') $badge_class = 'badge-danger';
                                                        elseif ($log['action_type'] == 'create') $badge_class = 'badge-success';
                                                        elseif ($log['action_type'] == 'update') $badge_class = 'badge-primary';
                                                        echo $badge_class;
                                                        ?>">
                                                        <?php echo ucfirst($log['action_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                                                <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Profile Picture Upload Modal -->
    <div class="modal" id="profilePictureModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-camera"></i> Update Profile Picture</h3>
                <button class="modal-close" onclick="closeProfilePictureModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="profilePictureForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Upload New Picture</label>
                        <input type="file" class="form-control" name="profile_picture" id="profilePictureInput"
                            accept="image/jpeg,image/png,image/gif" required>
                        <small class="form-text">Max size: 2MB. Allowed: JPG, PNG, GIF</small>
                    </div>
                    <div id="imagePreview" style="text-align: center; margin-top: 1rem; display: none;">
                        <img src="" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: var(--radius-md);">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeProfilePictureModal()">Cancel</button>
                    <button type="submit" name="update_profile_picture" class="btn btn-primary">Upload Picture</button>
                </div>
            </form>
        </div>
    </div>

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
                        <input type="text" class="form-control" name="employee_id" placeholder="Auto-generated if empty">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Employee Type <span>*</span></label>
                            <select class="form-control form-select" name="employee_type" required>
                                <option value="permanent">Permanent</option>
                                <option value="job_order">Job Order</option>
                                <option value="contract_of_service">Contract of Service</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role <span>*</span></label>
                            <select class="form-control form-select" name="new_role" required>
                                <option value="user">Regular User</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Administrator</option>
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
                        <strong>Note:</strong> Temporary password will be auto-generated.<br>
                        <strong>Username:</strong> Auto-generated from email.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
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
                        <label class="form-label">Role <span>*</span></label>
                        <select class="form-control form-select" name="role" id="editUserRole" required>
                            <option value="user">Regular User</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Access Level</label>
                        <select class="form-control form-select" name="access_level" id="editUserAccessLevel">
                            <option value="full">Full Access</option>
                            <option value="elevated">Elevated Access</option>
                            <option value="restricted">Restricted Access</option>
                        </select>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="editUserActive" value="1">
                        <label class="form-check-label" for="editUserActive">Active User</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                    <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                </div>
            </form>
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
                        <label class="form-label">Password Option</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="reset_password_option" id="resetDefault" value="default" checked onclick="toggleResetPasswordFields()">
                            <label class="form-check-label" for="resetDefault">Use Default Password (Password123!)</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="reset_password_option" id="resetCustom" value="custom" onclick="toggleResetPasswordFields()">
                            <label class="form-check-label" for="resetCustom">Set Custom Password</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="reset_password_option" id="resetGenerate" value="generate" onclick="toggleResetPasswordFields()">
                            <label class="form-check-label" for="resetGenerate">Generate Random Password</label>
                        </div>

                        <div id="customResetPasswordField" style="display: none; margin-top: 10px;">
                            <label class="form-label">Custom Password</label>
                            <input type="password" class="form-control" name="custom_reset_password" id="customResetPassword">
                        </div>

                        <div id="generatedResetPasswordField" style="display: none; margin-top: 10px;">
                            <label class="form-label">Generated Password</label>
                            <div class="input-group" style="display: flex; gap: 0.5rem;">
                                <input type="text" class="form-control" id="generatedResetPassword" readonly>
                                <button type="button" class="btn btn-secondary" onclick="generateResetPassword()">
                                    <i class="fas fa-redo"></i>
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

    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scrollTop" aria-label="Scroll to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ===============================================
            // DATE & TIME UPDATE
            // ===============================================
            function updateDateTime() {
                const now = new Date();
                const timeElement = document.getElementById('current-time');

                if (timeElement) {
                    timeElement.textContent = now.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true
                    });
                }
            }

            updateDateTime();
            setInterval(updateDateTime, 1000);

            // ===============================================
            // SIDEBAR TOGGLE - Matching dashboard.php
            // ===============================================
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarContainer = document.getElementById('sidebar-container');
            const overlay = document.getElementById('overlay');
            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollDropdown = document.getElementById('payroll-dropdown');

            // Ensure payroll dropdown is closed by default
            if (payrollToggle && payrollDropdown) {
                payrollDropdown.classList.remove('open');
                const chevron = payrollToggle.querySelector('.chevron');
                if (chevron) {
                    chevron.classList.remove('rotated');
                }

                payrollToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    payrollDropdown.classList.toggle('open');
                    if (chevron) {
                        chevron.classList.toggle('rotated');
                    }
                });
            }

            // Toggle sidebar
            if (sidebarToggle && sidebarContainer && overlay) {
                sidebarToggle.addEventListener('click', function() {
                    sidebarContainer.classList.toggle('active');
                    overlay.classList.toggle('active');
                    document.body.style.overflow = sidebarContainer.classList.contains('active') ? 'hidden' : '';
                });

                overlay.addEventListener('click', function() {
                    sidebarContainer.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }

            // Close sidebar on window resize if open
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024 && sidebarContainer.classList.contains('active')) {
                    sidebarContainer.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // ===============================================
            // LOGOUT CONFIRMATION - Matching dashboard.php
            // ===============================================
            const logoutBtn = document.getElementById('logout-btn');

            if (logoutBtn) {
                logoutBtn.addEventListener('click', function(e) {
                    e.preventDefault();

                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You will be logged out of the system",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#1e40af',
                        cancelButtonColor: '#ef4444',
                        confirmButtonText: 'Yes, logout',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '?logout=true';
                        }
                    });
                });
            }

            // ===============================================
            // SCROLL TO TOP - Matching dashboard.php
            // ===============================================
            const scrollTopBtn = document.getElementById('scrollTop');

            if (scrollTopBtn) {
                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > 300) {
                        scrollTopBtn.classList.add('show');
                    } else {
                        scrollTopBtn.classList.remove('show');
                    }
                });

                scrollTopBtn.addEventListener('click', function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }

            // ===============================================
            // TAB SWITCHING
            // ===============================================
            const urlParams = new URLSearchParams(window.location.search);
            const currentTab = urlParams.get('tab') || '<?php echo $default_tab; ?>';
            activateTab(currentTab);

            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.getAttribute('data-tab');

                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.pushState({}, '', url);

                    activateTab(tabId);
                });
            });

            window.addEventListener('popstate', function() {
                const urlParams = new URLSearchParams(window.location.search);
                const tab = urlParams.get('tab') || '<?php echo $default_tab; ?>';
                activateTab(tab);
            });

            function activateTab(tabId) {
                document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));

                const activeTab = document.querySelector(`.settings-tab[data-tab="${tabId}"]`);
                if (activeTab) activeTab.classList.add('active');

                const activeContent = document.getElementById(tabId);
                if (activeContent) activeContent.classList.add('active');
            }

            // ===============================================
            // PROFILE PICTURE MODAL
            // ===============================================
            window.openProfilePictureModal = function() {
                document.getElementById('profilePictureModal').classList.add('active');
            };

            window.closeProfilePictureModal = function() {
                document.getElementById('profilePictureModal').classList.remove('active');
                document.getElementById('profilePictureInput').value = '';
                document.getElementById('imagePreview').style.display = 'none';
            };

            // Image preview
            document.getElementById('profilePictureInput')?.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.size > 2 * 1024 * 1024) {
                        Swal.fire({
                            icon: 'error',
                            title: 'File Too Large',
                            text: 'Maximum file size is 2MB'
                        });
                        this.value = '';
                        return;
                    }

                    const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!validTypes.includes(file.type)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid File Type',
                            text: 'Please upload a valid image file (JPG, PNG, GIF)'
                        });
                        this.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.getElementById('imagePreview');
                        preview.querySelector('img').src = e.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Close modal when clicking outside
            window.onclick = function(event) {
                const modal = document.getElementById('profilePictureModal');
                if (event.target == modal) {
                    closeProfilePictureModal();
                }
            };

            // ===============================================
            // ADD USER MODAL
            // ===============================================
            window.openAddUserModal = function() {
                document.getElementById('addUserModal').classList.add('active');
            };

            window.closeAddUserModal = function() {
                document.getElementById('addUserModal').classList.remove('active');
            };

            // ===============================================
            // EDIT USER MODAL
            // ===============================================
            window.editUser = function(userId) {
                // For simplicity, we'll just set the user ID and show the modal
                // In production, you'd fetch user data via AJAX
                document.getElementById('editUserId').value = userId;
                document.getElementById('editUserModal').classList.add('active');
            };

            window.closeEditUserModal = function() {
                document.getElementById('editUserModal').classList.remove('active');
            };

            // ===============================================
            // RESET PASSWORD MODAL
            // ===============================================
            window.resetUserPassword = function(userId, userName) {
                document.getElementById('resetPasswordMessage').innerHTML = `Reset password for user: <strong>${userName}</strong>`;
                document.getElementById('resetPasswordUserId').value = userId;
                document.getElementById('resetDefault').checked = true;
                toggleResetPasswordFields();
                document.getElementById('resetPasswordModal').classList.add('active');
            };

            window.closeResetPasswordModal = function() {
                document.getElementById('resetPasswordModal').classList.remove('active');
            };

            window.toggleResetPasswordFields = function() {
                const defaultOption = document.getElementById('resetDefault').checked;
                const customOption = document.getElementById('resetCustom').checked;
                const generateOption = document.getElementById('resetGenerate').checked;

                document.getElementById('customResetPasswordField').style.display = customOption ? 'block' : 'none';
                document.getElementById('generatedResetPasswordField').style.display = generateOption ? 'block' : 'none';

                if (generateOption) {
                    generateResetPassword();
                }
            };

            window.generateResetPassword = function() {
                const length = 12;
                const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
                let password = "";

                password += "ABCDEFGHIJKLMNOPQRSTUVWXYZ".charAt(Math.floor(Math.random() * 26));
                password += "abcdefghijklmnopqrstuvwxyz".charAt(Math.floor(Math.random() * 26));
                password += "0123456789".charAt(Math.floor(Math.random() * 10));
                password += "!@#$%^&*()_+".charAt(Math.floor(Math.random() * 12));

                for (let i = 4; i < length; i++) {
                    password += charset.charAt(Math.floor(Math.random() * charset.length));
                }

                password = password.split('').sort(() => Math.random() - 0.5).join('');

                document.getElementById('generatedResetPassword').value = password;
                document.getElementById('generatedResetPasswordHidden').value = password;
            };

            // ===============================================
            // PASSWORD FORM VALIDATION
            // ===============================================
            document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Password Mismatch',
                        text: 'New passwords do not match!'
                    });
                    return;
                }

                const hasUpperCase = /[A-Z]/.test(newPassword);
                const hasLowerCase = /[a-z]/.test(newPassword);
                const hasNumbers = /\d/.test(newPassword);
                const hasSpecialChar = /[\W_]/.test(newPassword);

                if (!hasUpperCase || !hasLowerCase || !hasNumbers || !hasSpecialChar) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Weak Password',
                        text: 'Password must contain uppercase, lowercase, number, and special character!'
                    });
                }
            });

            // ===============================================
            // AUTO-HIDE MESSAGES
            // ===============================================
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(alert => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);

            // ===============================================
            // ESCAPE KEY HANDLER
            // ===============================================
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (document.getElementById('profilePictureModal').classList.contains('active')) {
                        closeProfilePictureModal();
                    }
                    if (document.getElementById('addUserModal').classList.contains('active')) {
                        closeAddUserModal();
                    }
                    if (document.getElementById('editUserModal').classList.contains('active')) {
                        closeEditUserModal();
                    }
                    if (document.getElementById('resetPasswordModal').classList.contains('active')) {
                        closeResetPasswordModal();
                    }
                    if (sidebarContainer.classList.contains('active')) {
                        sidebarContainer.classList.remove('active');
                        overlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                }
            });

            // ===============================================
            // KEYBOARD SHORTCUTS
            // ===============================================
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'd') {
                    e.preventDefault();
                    window.location.href = 'dashboard.php';
                }
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    window.location.href = 'settings.php';
                }
                if (e.ctrlKey && e.key === 'e') {
                    e.preventDefault();
                    window.location.href = './employees/Employee.php';
                }
                if (e.ctrlKey && e.key === 'a') {
                    e.preventDefault();
                    window.location.href = 'attendance.php';
                }
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    window.location.href = 'Payrollmanagement/permanentpayrolltable1.php';
                }
            });
        });
    </script>

    <style>
        /* Scroll to Top Button */
        .scroll-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.25rem;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            z-index: 998;
        }

        .scroll-top:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: var(--shadow-xl);
        }

        .scroll-top.show {
            display: flex;
        }

        @media (max-width: 768px) {
            .scroll-top {
                bottom: 1rem;
                right: 1rem;
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }

        /* ML Auto class */
        .ml-auto {
            margin-left: auto;
        }
    </style>
</body>

</html>
<?php $conn->close(); ?>