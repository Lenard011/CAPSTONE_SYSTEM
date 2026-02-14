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

$employees = [];

// =================================================================================
// --- Get ALL employees from all tables with attendance records ---
// =================================================================================

try {
    // 1. Get distinct employees who have attendance records
    $attendance_sql = "SELECT DISTINCT employee_id, employee_name, department 
                       FROM attendance 
                       ORDER BY employee_name ASC";
    $attendance_stmt = $pdo->query($attendance_sql);
    $attendance_employees = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

    $employee_map = [];

    foreach ($attendance_employees as $emp) {
        $employee_map[$emp['employee_id']] = [
            'employee_id' => $emp['employee_id'],
            'full_name' => $emp['employee_name'],
            'department' => $emp['department'],
            'type' => 'Unknown',
            'has_attendance' => true
        ];
    }

    // 2. Get permanent employees to add type info
    $permanent_sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department 
                      FROM permanent WHERE status = 'Active'";
    $permanent_stmt = $pdo->query($permanent_sql);
    while ($row = $permanent_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($employee_map[$row['employee_id']])) {
            $employee_map[$row['employee_id']]['type'] = 'Permanent';
            $employee_map[$row['employee_id']]['full_name'] = $row['full_name'];
            $employee_map[$row['employee_id']]['department'] = $row['department'];
        }
    }

    // 3. Get job order employees
    $joborder_sql = "SELECT employee_id, employee_name as full_name, office as department 
                     FROM job_order WHERE is_archived = 0";
    $joborder_stmt = $pdo->query($joborder_sql);
    while ($row = $joborder_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($employee_map[$row['employee_id']])) {
            $employee_map[$row['employee_id']]['type'] = 'Job Order';
            $employee_map[$row['employee_id']]['full_name'] = $row['full_name'];
            $employee_map[$row['employee_id']]['department'] = $row['department'];
        }
    }

    // 4. Get contractual employees
    $contractual_sql = "SELECT employee_id, full_name, office as department 
                        FROM contractofservice WHERE status = 'active'";
    $contractual_stmt = $pdo->query($contractual_sql);
    while ($row = $contractual_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($employee_map[$row['employee_id']])) {
            $employee_map[$row['employee_id']]['type'] = 'Contractual';
            $employee_map[$row['employee_id']]['full_name'] = $row['full_name'];
            $employee_map[$row['employee_id']]['department'] = $row['department'];
        }
    }

    // 5. Also include employees from employee tables even if no attendance yet? 
    // Uncomment if you want to include all employees:
    /*
    $all_employees = [];
    
    // Add all permanents
    $permanent_sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department, 'Permanent' as type 
                      FROM permanent WHERE status = 'Active";
    $stmt = $pdo->query($permanent_sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $all_employees[$row['employee_id']] = $row;
    }
    
    // Add all job orders
    $joborder_sql = "SELECT employee_id, employee_name as full_name, office as department, 'Job Order' as type 
                      FROM job_order WHERE is_archived = 0";
    $stmt = $pdo->query($joborder_sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $all_employees[$row['employee_id']] = $row;
    }
    
    // Add all contractuals
    $contractual_sql = "SELECT employee_id, full_name, office as department, 'Contractual' as type 
                        FROM contractofservice WHERE status = 'active'";
    $stmt = $pdo->query($contractual_sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $all_employees[$row['employee_id']] = $row;
    }
    
    // Merge with existing employee_map
    foreach ($all_employees as $id => $emp) {
        if (!isset($employee_map[$id])) {
            $employee_map[$id] = [
                'employee_id' => $emp['employee_id'],
                'full_name' => $emp['full_name'],
                'department' => $emp['department'],
                'type' => $emp['type'],
                'has_attendance' => false
            ];
        }
    }
    */

    // Convert map to array
    $employees = array_values($employee_map);

    // Sort by name
    usort($employees, function ($a, $b) {
        return strcmp($a['full_name'], $b['full_name']);
    });

    echo json_encode([
        'success' => true,
        'employees' => $employees,
        'count' => count($employees)
    ]);
} catch (PDOException $e) {
    error_log("Get employees for export error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$pdo = null;
