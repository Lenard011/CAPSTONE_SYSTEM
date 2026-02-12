<?php
// upload_profile_picture.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set same session configuration
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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Database connection
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "hrms_paluan";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$upload_dir = 'uploads/profile_pictures/';

// Create upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle profile picture upload
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_image'];
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.']);
        exit();
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 2MB.']);
        exit();
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    // Delete old profile picture if exists
    $sql_select = "SELECT profile_image FROM users WHERE id = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("i", $user_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $old_image = $row['profile_image'];
        // Delete old file if it exists in uploads directory
        if ($old_image && file_exists($upload_dir . basename($old_image))) {
            unlink($upload_dir . basename($old_image));
        }
    }
    $stmt_select->close();
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Update database
        $sql_update = "UPDATE users SET profile_image = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $upload_path, $user_id);
        
        if ($stmt_update->execute()) {
            // Update session
            $_SESSION['profile_image'] = $upload_path;
            
            echo json_encode([
                'success' => true,
                'message' => 'Profile picture updated successfully',
                'image_url' => $upload_path . '?t=' . time() // Add timestamp to prevent caching
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed']);
        }
        $stmt_update->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'File upload failed']);
    }
    
} elseif (isset($_POST['remove_profile_image']) && $_POST['remove_profile_image'] == '1') {
    // Handle profile picture removal
    $sql_select = "SELECT profile_image FROM users WHERE id = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("i", $user_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $old_image = $row['profile_image'];
        // Delete file if it exists in uploads directory
        if ($old_image && file_exists($upload_dir . basename($old_image))) {
            unlink($upload_dir . basename($old_image));
        }
    }
    $stmt_select->close();
    
    // Update database to remove profile image
    $sql_update = "UPDATE users SET profile_image = NULL WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("i", $user_id);
    
    if ($stmt_update->execute()) {
        // Update session
        unset($_SESSION['profile_image']);
        
        // Generate avatar URL for default image
        $full_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
        $default_image = 'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=random&color=fff';
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile picture removed successfully',
            'image_url' => $default_image
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove profile picture']);
    }
    $stmt_update->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or invalid request']);
}

$conn->close();
?>