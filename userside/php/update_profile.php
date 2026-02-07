<?php
// update_profile.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Database connection
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "your_database_name";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$first_name = $conn->real_escape_string($_POST['first_name'] ?? '');
$last_name = $conn->real_escape_string($_POST['last_name'] ?? '');
$middle_name = $conn->real_escape_string($_POST['middle_name'] ?? '');
$email = $conn->real_escape_string($_POST['email'] ?? '');
$phone_number = $conn->real_escape_string($_POST['phone_number'] ?? '');
$department = $conn->real_escape_string($_POST['department'] ?? '');
$position = $conn->real_escape_string($_POST['position'] ?? '');
$date_of_birth = $conn->real_escape_string($_POST['date_of_birth'] ?? '');
$bio = $conn->real_escape_string($_POST['bio'] ?? '');

// Update query
$sql = "UPDATE users SET 
        first_name = ?,
        last_name = ?,
        middle_name = ?,
        email = ?,
        phone_number = ?,
        department = ?,
        position = ?,
        date_of_birth = ?,
        bio = ?,
        updated_at = NOW()
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssssi", 
    $first_name, $last_name, $middle_name, $email, 
    $phone_number, $department, $position, $date_of_birth, 
    $bio, $user_id);

if ($stmt->execute()) {
    // Update session variables
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    $_SESSION['email'] = $email;
    $_SESSION['department'] = $department;
    $_SESSION['position'] = $position;
    
    // Generate full name for response
    $full_name = trim($first_name . ' ' . 
                     (!empty($middle_name) ? substr($middle_name, 0, 1) . '.' : '') . ' ' . 
                     $last_name);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Profile updated successfully',
        'updated_name' => $full_name
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
}

$stmt->close();
$conn->close();
?>