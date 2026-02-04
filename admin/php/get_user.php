<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_GET['id'])) {
    die(json_encode(['error' => 'User ID required']));
}

$user_id = (int)$_GET['id'];

// Get user data
$sql = "SELECT id, full_name, email, role, is_active FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo json_encode($user);
} else {
    echo json_encode(['error' => 'User not found']);
}

$stmt->close();
$conn->close();
?>