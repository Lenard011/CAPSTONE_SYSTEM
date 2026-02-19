<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database configuration
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
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

// Get parameters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$department = isset($_GET['department']) ? $_GET['department'] : '';
$employee_ids = isset($_GET['employee_ids']) ? $_GET['employee_ids'] : '';

if (empty($employee_ids)) {
    echo json_encode(['has_records' => false, 'message' => 'No employees selected for export.']);
    exit();
}

// Parse employee IDs
$employee_id_array = array_map('trim', explode(',', $employee_ids));
$employee_id_array = array_filter($employee_id_array);

if (empty($employee_id_array)) {
    echo json_encode(['has_records' => false, 'message' => 'No valid employees selected for export.']);
    exit();
}

// Handle large number of employees - split into chunks if needed
$chunk_size = 100; // Process 100 employees at a time
$record_count = 0;
$employees_with_records = [];

foreach (array_chunk($employee_id_array, $chunk_size) as $chunk) {
    $placeholders = implode(',', array_fill(0, count($chunk), '?'));

    // Build query for this chunk
    $sql = "SELECT COUNT(*) as chunk_count, GROUP_CONCAT(DISTINCT employee_id) as emp_ids FROM attendance WHERE employee_id IN ($placeholders)";
    $params = $chunk;

    if (!empty($from_date)) {
        $sql .= " AND date >= ?";
        $params[] = $from_date;
    }

    if (!empty($to_date)) {
        $sql .= " AND date <= ?";
        $params[] = $to_date;
    }

    if (!empty($department)) {
        $sql .= " AND department = ?";
        $params[] = $department;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $record_count += $result['chunk_count'];

        if (!empty($result['emp_ids'])) {
            $ids = explode(',', $result['emp_ids']);
            foreach ($ids as $id) {
                $employees_with_records[$id] = true;
            }
        }
    } catch (PDOException $e) {
        error_log("Check export records error: " . $e->getMessage());
        echo json_encode(['has_records' => false, 'message' => 'Database error occurred']);
        exit();
    }
}

if ($record_count > 0) {
    echo json_encode([
        'has_records' => true,
        'record_count' => $record_count,
        'employee_count' => count($employees_with_records)
    ]);
} else {
    $message = "None of the selected employees have attendance records";
    if (!empty($from_date) && !empty($to_date)) {
        $message .= " for the period " . date('M d, Y', strtotime($from_date)) . " to " . date('M d, Y', strtotime($to_date));
    }
    $message .= ".";

    echo json_encode([
        'has_records' => false,
        'message' => $message,
        'record_count' => 0
    ]);
}

$pdo = null;
