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

// Get employee ID from POST request
$employee_id = isset($_POST['employee_id']) ? trim($_POST['employee_id']) : '';

if (empty($employee_id)) {
    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
    exit();
}

// Function to search for employee in all tables (fixed to work with your existing function)
function getEmployeeById($pdo, $employee_id)
{
    // Search in permanent table
    $permanent_sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department FROM permanent WHERE employee_id = ? AND status = 'Active'";
    $stmt = $pdo->prepare($permanent_sql);
    $stmt->execute([$employee_id]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'employee_id' => $row['employee_id'],
            'full_name' => $row['full_name'],
            'department' => $row['department'],
            'type' => 'Permanent'
        ];
    }

    // Search in job order table
    $joborder_sql = "SELECT employee_id, employee_name as full_name, office as department FROM job_order WHERE employee_id = ? AND is_archived = 0";
    $stmt = $pdo->prepare($joborder_sql);
    $stmt->execute([$employee_id]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'employee_id' => $row['employee_id'],
            'full_name' => $row['full_name'],
            'department' => $row['department'],
            'type' => 'Job Order'
        ];
    }

    // Search in contractual table
    $contractual_sql = "SELECT employee_id, full_name, office_assignment as department FROM contractofservice WHERE employee_id = ? AND status = 'active'";
    $stmt = $pdo->prepare($contractual_sql);
    $stmt->execute([$employee_id]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'employee_id' => $row['employee_id'],
            'full_name' => $row['full_name'],
            'department' => $row['department'],
            'type' => 'Contractual'
        ];
    }

    return null;
}

// Search for the employee
$employee = getEmployeeById($pdo, $employee_id);

if ($employee) {
    echo json_encode([
        'success' => true,
        'employee' => [
            'employee_id' => $employee['employee_id'],
            'full_name' => $employee['full_name'],
            'department' => $employee['department']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Employee not found. Please check the Employee ID.'
    ]);
}

$pdo = null;
