<?php
// get_user.php

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "hrms_paluan";

$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed']));
}

// Get user ID from request
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id > 0) {
    $sql = "SELECT id, first_name, middle_name, last_name, email, role, is_active, employment_type 
            FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Combine first, middle, last name into full_name for compatibility
        $user['full_name'] = trim($user['first_name'] . ' ' . 
                                  ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . 
                                  $user['last_name']);
        
        echo json_encode($user);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid user ID']);
}

$conn->close();
?>