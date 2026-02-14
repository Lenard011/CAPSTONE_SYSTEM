<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database Configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hrms_paluan');

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$attendance_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($attendance_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid attendance ID']);
    exit();
}

try {
    $sql = "SELECT * FROM attendance WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$attendance_id]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Format times for display
        $row['am_time_in'] = !empty($row['am_time_in']) ? date('H:i', strtotime($row['am_time_in'])) : '';
        $row['am_time_out'] = !empty($row['am_time_out']) ? date('H:i', strtotime($row['am_time_out'])) : '';
        $row['pm_time_in'] = !empty($row['pm_time_in']) ? date('H:i', strtotime($row['pm_time_in'])) : '';
        $row['pm_time_out'] = !empty($row['pm_time_out']) ? date('H:i', strtotime($row['pm_time_out'])) : '';

        echo json_encode(['success' => true, 'record' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
    }
} catch (PDOException $e) {
    error_log("Get attendance record error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
