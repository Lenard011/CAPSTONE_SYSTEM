<?php
// update_profile.php
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
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Get POST data - CHANGED: Now using mobile_number to match database column
$user_id = $_SESSION['user_id'];
$first_name = isset($_POST['first_name']) ? $conn->real_escape_string(trim($_POST['first_name'])) : '';
$last_name = isset($_POST['last_name']) ? $conn->real_escape_string(trim($_POST['last_name'])) : '';
$middle_name = isset($_POST['middle_name']) ? $conn->real_escape_string(trim($_POST['middle_name'])) : '';
$email = isset($_POST['email']) ? $conn->real_escape_string(trim($_POST['email'])) : '';
// IMPORTANT: Changed from phone_number to mobile_number
$mobile_number = isset($_POST['mobile_number']) ? $conn->real_escape_string(trim($_POST['mobile_number'])) : '';
$department = isset($_POST['department']) ? $conn->real_escape_string(trim($_POST['department'])) : '';
$position = isset($_POST['position']) ? $conn->real_escape_string(trim($_POST['position'])) : '';
$employment_type = isset($_POST['employment_type']) ? $conn->real_escape_string(trim($_POST['employment_type'])) : '';
$date_of_birth = isset($_POST['date_of_birth']) ? $conn->real_escape_string(trim($_POST['date_of_birth'])) : '';

// Debug logging (remove in production)
error_log("Profile update attempt for user_id: $user_id");
error_log("Mobile number received: " . ($mobile_number ?: 'empty'));

// Validate required fields
if (empty($first_name) || empty($last_name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Validate mobile number (optional, but good practice)
if (!empty($mobile_number) && !preg_match('/^[0-9\-\+\s\(\)]{7,15}$/', $mobile_number)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
    exit();
}

// Update query - Using mobile_number column
$sql = "UPDATE users SET 
        first_name = ?,
        last_name = ?,
        middle_name = ?,
        email = ?,
        mobile_number = ?,      -- This matches your database column
        department = ?,
        position = ?,
        employment_type = ?,
        date_of_birth = ?
        WHERE id = ?";

error_log("SQL Query: $sql");

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param("sssssssssi", 
    $first_name,
    $last_name,
    $middle_name,
    $email,
    $mobile_number,  // This should now match
    $department,
    $position,
    $employment_type,
    $date_of_birth,
    $user_id
);

if ($stmt->execute()) {
    $affected_rows = $stmt->affected_rows;
    error_log("Update executed. Affected rows: $affected_rows");
    
    // Update session variables regardless of affected rows
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    $_SESSION['email'] = $email;
    $_SESSION['department'] = $department;
    $_SESSION['position'] = $position;
    
    // Even if affected_rows is 0, the data was saved (it just matches existing data)
    echo json_encode([
        'success' => true, 
        'message' => 'Profile saved successfully',
        'updated_name' => $first_name . ' ' . $last_name,
        'affected_rows' => $affected_rows,
        'note' => $affected_rows === 0 ? 'Data was already up to date' : 'Changes applied'
    ]);
} else {
    error_log("Execute failed: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>