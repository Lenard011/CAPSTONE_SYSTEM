<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_GET['id'])) {
    die(json_encode(['error' => 'Department ID required']));
}

$dept_id = (int)$_GET['id'];

// Get department data
$sql = "SELECT id, dept_name, dept_code, dept_head FROM departments WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $department = $result->fetch_assoc();
    echo json_encode($department);
} else {
    echo json_encode(['error' => 'Department not found']);
}

$stmt->close();
$conn->close();
?>