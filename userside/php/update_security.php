<?php
// update_security.php - UPDATED with better debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
$cookiePath = '/CAPSTONE_SYSTEM/userside/php/';
session_name('HRMS_SESSION');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => $cookiePath,
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "hrms_paluan";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Debug: Log session and POST data
error_log("=== update_security.php DEBUG ===");
error_log("Session ID: " . session_id());
error_log("User ID in session: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("POST data: " . print_r($_POST, true));

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Not authenticated',
        'debug' => [
            'session_id' => session_id(),
            'session_data' => $_SESSION
        ]
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    error_log("Password change attempt for user_id: $user_id");
    error_log("Current password length: " . strlen($current_password));
    error_log("New password length: " . strlen($new_password));
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
        exit();
    }
    
    // Get user's current password_hash
    $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    error_log("User password_hash from DB: " . $user['password_hash']);
    error_log("Hash length: " . strlen($user['password_hash']));
    
    // Verify current password
    if (!password_verify($current_password, $user['password_hash'])) {
        error_log("Current password verification FAILED");
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }
    
    error_log("Current password verified successfully");
    
    // Hash new password
    $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
    error_log("New password hash: " . $hashed_new_password);
    
    // Update password_hash
    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ?, last_password_change = NOW() WHERE id = ?");
    if (!$update_stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare update failed: ' . $conn->error]);
        exit();
    }
    
    $update_stmt->bind_param("si", $hashed_new_password, $user_id);
    
    if ($update_stmt->execute()) {
        $affected_rows = $update_stmt->affected_rows;
        error_log("UPDATE successful. Affected rows: $affected_rows");
        
        // Verify update
        $verify_stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $verify_stmt->bind_param("i", $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $updated_user = $verify_result->fetch_assoc();
        
        if (password_verify($new_password, $updated_user['password_hash'])) {
            error_log("Password update VERIFIED");
            echo json_encode([
                'success' => true, 
                'message' => 'Password updated successfully!',
                'debug' => [
                    'affected_rows' => $affected_rows,
                    'verification' => 'passed'
                ]
            ]);
        } else {
            error_log("Password update verification FAILED");
            echo json_encode(['success' => false, 'message' => 'Password update failed verification']);
        }
    } else {
        error_log("UPDATE failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>