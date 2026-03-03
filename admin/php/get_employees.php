<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'u420482914_paluan_hrms');
define('DB_PASSWORD', 'Hrms_Paluan01');
define('DB_NAME', 'u420482914_hrms_paluan');

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$exact = isset($_GET['exact']) ? intval($_GET['exact']) : 0;

if (empty($search) || strlen($search) < 2) {
    echo json_encode(['success' => true, 'employees' => []]);
    exit();
}

$employees = [];

// Determine search pattern
if ($exact) {
    $searchPattern = $search; // Exact match
} else {
    $searchPattern = "%$search%"; // Like match
}

// Search in permanent table
try {
    if ($exact) {
        $sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department, 'Permanent' as type 
                FROM permanent 
                WHERE status = 'Active' 
                AND (employee_id = ? OR CONCAT(first_name, ' ', last_name) = ? OR office = ?)
                LIMIT 20";
    } else {
        $sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department, 'Permanent' as type 
                FROM permanent 
                WHERE status = 'Active' 
                AND (employee_id LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ? OR office LIKE ?)
                LIMIT 20";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$searchPattern, $searchPattern, $searchPattern]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $employees[] = $row;
    }
} catch (PDOException $e) {
    error_log("Error searching permanent: " . $e->getMessage());
}

// Search in job order table
try {
    if ($exact) {
        $sql = "SELECT employee_id, employee_name as full_name, office as department, 'Job Order' as type 
                FROM job_order 
                WHERE is_archived = 0 
                AND (employee_id = ? OR employee_name = ? OR office = ?)
                LIMIT 20";
    } else {
        $sql = "SELECT employee_id, employee_name as full_name, office as department, 'Job Order' as type 
                FROM job_order 
                WHERE is_archived = 0 
                AND (employee_id LIKE ? OR employee_name LIKE ? OR office LIKE ?)
                LIMIT 20";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$searchPattern, $searchPattern, $searchPattern]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $employees[] = $row;
    }
} catch (PDOException $e) {
    error_log("Error searching job order: " . $e->getMessage());
}

// Search in contractual table
try {
    if ($exact) {
        $sql = "SELECT employee_id, full_name, office as department, 'Contractual' as type 
                FROM contractofservice 
                WHERE status = 'active' 
                AND (employee_id = ? OR full_name = ? OR office = ?)
                LIMIT 20";
    } else {
        $sql = "SELECT employee_id, full_name, office as department, 'Contractual' as type 
                FROM contractofservice 
                WHERE status = 'active' 
                AND (employee_id LIKE ? OR full_name LIKE ? OR office LIKE ?)
                LIMIT 20";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$searchPattern, $searchPattern, $searchPattern]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $employees[] = $row;
    }
} catch (PDOException $e) {
    error_log("Error searching contractual: " . $e->getMessage());
}

// Remove duplicates by employee_id
$uniqueEmployees = [];
$seen = [];
foreach ($employees as $emp) {
    if (!in_array($emp['employee_id'], $seen)) {
        $seen[] = $emp['employee_id'];
        $uniqueEmployees[] = $emp;
    }
}

// Sort by name
usort($uniqueEmployees, function ($a, $b) {
    return strcmp($a['full_name'], $b['full_name']);
});

header('Content-Type: application/json');
echo json_encode(['success' => true, 'employees' => array_slice($uniqueEmployees, 0, 20)]);
