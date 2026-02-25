<?php
// get_user_complete.php
session_start();

// Check authentication
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "hrms_paluan";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get user ID from request
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit();
}

// Get base user data with correct table names
$sql = "SELECT u.* 
        FROM users u 
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    // Get employment type from users table
    $employment_type = $user_data['employment_type'] ?? 'permanent';
    
    // Now get data from the specific employee table based on employment type
    switch ($employment_type) {
        case 'permanent':
            $emp_sql = "SELECT * FROM permanent WHERE user_id = ?";
            $emp_stmt = $conn->prepare($emp_sql);
            $emp_stmt->bind_param("i", $user_id);
            $emp_stmt->execute();
            $emp_result = $emp_stmt->get_result();
            if ($emp_result->num_rows > 0) {
                $emp_data = $emp_result->fetch_assoc();
                $user_data = array_merge($user_data, $emp_data);
            }
            $emp_stmt->close();
            break;
            
        case 'job_order':
            $emp_sql = "SELECT * FROM job_order WHERE user_id = ?";
            $emp_stmt = $conn->prepare($emp_sql);
            $emp_stmt->bind_param("i", $user_id);
            $emp_stmt->execute();
            $emp_result = $emp_stmt->get_result();
            if ($emp_result->num_rows > 0) {
                $emp_data = $emp_result->fetch_assoc();
                $user_data = array_merge($user_data, $emp_data);
            }
            $emp_stmt->close();
            break;
            
        case 'contract_of_service':
            $emp_sql = "SELECT * FROM contractofservice WHERE user_id = ?";
            $emp_stmt = $conn->prepare($emp_sql);
            $emp_stmt->bind_param("i", $user_id);
            $emp_stmt->execute();
            $emp_result = $emp_stmt->get_result();
            if ($emp_result->num_rows > 0) {
                $emp_data = $emp_result->fetch_assoc();
                $user_data = array_merge($user_data, $emp_data);
            }
            $emp_stmt->close();
            break;
    }
    
    // Clean up the data - remove nulls and set defaults
    foreach ($user_data as $key => $value) {
        if ($value === null) {
            $user_data[$key] = '';
        }
    }
    
    // Make sure we have the employment type set
    $user_data['employment_type'] = $employment_type;
    
    echo json_encode(['success' => true, 'data' => $user_data]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
}

$conn->close();
?>