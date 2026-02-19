<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database Configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hrms_paluan');

// Set header for JSON response
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch (PDOException $e) {
    error_log("Database connection error in get_employee_info.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get employee ID from POST request
$employee_id = isset($_POST['employee_id']) ? trim($_POST['employee_id']) : '';

// Validate input
if (empty($employee_id)) {
    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
    exit();
}

// Sanitize input
$employee_id = filter_var($employee_id, FILTER_SANITIZE_STRING);

// Log for debugging
error_log("Searching for employee ID: " . $employee_id);

// Function to search for employee in all tables
function findEmployee($pdo, $employee_id)
{
    $employee = null;

    // 1. Check permanent table first (using correct column names from your schema)
    try {
        $sql = "SELECT 
                    employee_id, 
                    CONCAT(first_name, ' ', last_name) as full_name, 
                    office as department,
                    'Permanent' as employee_type 
                FROM permanent 
                WHERE employee_id = ? AND status = 'Active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employee_id]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            error_log("Found in permanent table: " . print_r($row, true));
            return $row;
        }
    } catch (PDOException $e) {
        error_log("Error searching permanent table: " . $e->getMessage());
    }

    // 2. Check job_order table
    try {
        $sql = "SELECT 
                    employee_id, 
                    employee_name as full_name, 
                    office as department,
                    'Job Order' as employee_type 
                FROM job_order 
                WHERE employee_id = ? AND (is_archived = 0 OR is_archived IS NULL)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employee_id]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            error_log("Found in job_order table: " . print_r($row, true));
            return $row;
        }
    } catch (PDOException $e) {
        error_log("Error searching job_order table: " . $e->getMessage());
    }

    // 3. Check contractofservice table - FIXED: using office instead of office_assignment
    try {
        $sql = "SELECT 
                    employee_id, 
                    full_name, 
                    office as department,
                    'Contractual' as employee_type 
                FROM contractofservice 
                WHERE employee_id = ? AND status = 'active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employee_id]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            error_log("Found in contractofservice table: " . print_r($row, true));
            return $row;
        }
    } catch (PDOException $e) {
        error_log("Error searching contractofservice table: " . $e->getMessage());
    }

    return null;
}

// Search for the employee
$employee = findEmployee($pdo, $employee_id);

if ($employee) {
    // Success - return employee data
    echo json_encode([
        'success' => true,
        'employee' => [
            'employee_id' => $employee['employee_id'],
            'full_name' => $employee['full_name'],
            'department' => $employee['department'] ?? '',
            'employee_type' => $employee['employee_type'] ?? ''
        ]
    ]);
} else {
    // Not found
    error_log("Employee not found with ID: " . $employee_id);
    echo json_encode([
        'success' => false,
        'message' => 'Employee not found. Please check the Employee ID.'
    ]);
}

// Close connection
$pdo = null;
