<?php
require_once 'config.php';
require_once 'attendance.php';
require_once 'employees.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Get request method
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

// Authentication (basic token)
$headers = apache_request_headers();
$auth_token = $headers['Authorization'] ?? '';

// Validate token (simplified)
function validateToken($token) {
    $valid_tokens = ['HRMO_PALUAN_TOKEN_2024', 'ZKBIOTIME_API_KEY'];
    return in_array($token, $valid_tokens);
}

if (!validateToken($auth_token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Handle API endpoints
switch($endpoint) {
    case 'attendance':
        handleAttendanceAPI($method);
        break;
    case 'employees':
        handleEmployeesAPI($method);
        break;
    case 'sync':
        handleSyncAPI($method);
        break;
    case 'reports':
        handleReportsAPI($method);
        break;
    default:
        echo json_encode(['message' => 'HRMO Paluan API v1.0', 'database' => 'hrmo_paluan']);
}

function handleAttendanceAPI($method) {
    $attendance = new AttendanceManager();
    
    switch($method) {
        case 'GET':
            $date_from = $_GET['from'] ?? date('Y-m-d');
            $date_to = $_GET['to'] ?? date('Y-m-d');
            $employee_id = $_GET['employee_id'] ?? null;
            
            $records = $attendance->getAttendanceRecords([
                'date_from' => $date_from,
                'date_to' => $date_to,
                'employee_id' => $employee_id,
                'limit' => 1000
            ]);
            
            echo json_encode([
                'success' => true,
                'count' => count($records),
                'data' => $records
            ]);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!empty($data['user_id']) && !empty($data['check_time'])) {
                $result = $attendance->addAttendanceRecord($data);
                
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Attendance recorded' : 'Failed to record'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields'
                ]);
            }
            break;
    }
}

function handleEmployeesAPI($method) {
    $employee = new EmployeeManager();
    
    switch($method) {
        case 'GET':
            $filters = [
                'status' => $_GET['status'] ?? 'Active',
                'department_id' => $_GET['department_id'] ?? null,
                'search' => $_GET['search'] ?? null
            ];
            
            $employees = $employee->getEmployees($filters);
            
            echo json_encode([
                'success' => true,
                'count' => count($employees),
                'data' => $employees
            ]);
            break;
    }
}

function handleSyncAPI($method) {
    if ($method == 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $attendance = new AttendanceManager();
        
        if (!empty($data['device_sn'])) {
            $date = $data['date'] ?? date('Y-m-d');
            $result = $attendance->syncFromZKBioTime($data['device_sn'], $date);
            
            echo json_encode($result);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Device SN required'
            ]);
        }
    }
}
?>