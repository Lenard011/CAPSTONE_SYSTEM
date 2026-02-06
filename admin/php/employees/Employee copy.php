<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'hrms_paluan';

// Initialize variables
$db_connected = false;
$employee_summary = [];
$error_message = '';

// Create database connection
try {
    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $db_connected = true;
    $conn->set_charset("utf8");

    // Check if action parameter is set for AJAX requests
    if (isset($_GET['action'])) {
        // Clear all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set JSON header
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        // Suppress any error output
        ini_set('display_errors', 0);

        try {
            handleAjaxRequest($conn);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Server error: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    // Fetch employee summary data for dashboard
    if ($db_connected) {
        $employee_summary = fetchEmployeeSummary($conn);
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $db_connected = false;

    // If this is an AJAX request, return JSON error
    if (isset($_GET['action'])) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $error_message]);
        exit;
    }
}

// Clear output buffer for HTML
ob_end_clean();

// Function to handle AJAX requests
function handleAjaxRequest($conn)
{
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'fetch_stats':
            echo fetchDashboardStats($conn);
            break;

        case 'fetch_details':
            echo fetchEmployeeDetails($conn);
            break;

        case 'fetch_summary':
            echo fetchOfficeSummary($conn);
            break;

        case 'search_employees':
            echo searchEmployees($conn);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
    }
}

// Helper function to find contractual table
function findContractualTable($conn)
{
    $possibleTables = ['contractofservice', 'contract_of_service', 'contractual', 'contractuals'];

    foreach ($possibleTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            return $table;
        }
    }

    return null;
}

// Function to fetch dashboard statistics
function fetchDashboardStats($conn)
{
    $stats = [
        'total_permanent' => 0,
        'total_contractual' => 0,
        'total_joborder' => 0,
        'total_employees' => 0
    ];

    // Count permanent employees
    $query = "SELECT COUNT(*) as count FROM permanent";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_permanent'] = (int) $row['count'];
    }

    // Try to find contractual table
    $contractualTable = findContractualTable($conn);

    if ($contractualTable) {
        $query = "SELECT COUNT(*) as count FROM `$contractualTable`";
        $result = $conn->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_contractual'] = (int) $row['count'];
        }
    }

    // Count job order employees
    $query = "SELECT COUNT(*) as count FROM job_order";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_joborder'] = (int) $row['count'];
    }

    // Calculate total
    $stats['total_employees'] = $stats['total_permanent'] + $stats['total_contractual'] + $stats['total_joborder'];

    return json_encode([
        'success' => true,
        'stats' => $stats,
        'debug' => ['contractual_table_found' => !empty($contractualTable)]
    ]);
}

// Function to fetch employee summary by office
function fetchEmployeeSummary($conn)
{
    $summary = [];

    // Fetch permanent employees by office
    $query = "SELECT office, COUNT(*) as count FROM permanent
              WHERE office IS NOT NULL AND TRIM(office) != ''
              GROUP BY office";
    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $office = trim($row['office']);
            if (!isset($summary[$office])) {
                $summary[$office] = [
                    'OFFICE' => $office,
                    'PERMANENT' => 0,
                    'CONTRACTUAL' => 0,
                    'JOB ORDER' => 0
                ];
            }
            $summary[$office]['PERMANENT'] = (int) $row['count'];
        }
    }

    // Find contractual table
    $contractualTable = findContractualTable($conn);

    if ($contractualTable) {
        $query = "SELECT office, COUNT(*) as count FROM `$contractualTable`
                  WHERE office IS NOT NULL AND TRIM(office) != ''
                  GROUP BY office";
        $result = $conn->query($query);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $office = trim($row['office']);
                if (!isset($summary[$office])) {
                    $summary[$office] = [
                        'OFFICE' => $office,
                        'PERMANENT' => 0,
                        'CONTRACTUAL' => 0,
                        'JOB ORDER' => 0
                    ];
                }
                $summary[$office]['CONTRACTUAL'] = (int) $row['count'];
            }
        }
    }

    // Fetch job order employees by office
    $query = "SELECT office, COUNT(*) as count FROM job_order
              WHERE office IS NOT NULL AND TRIM(office) != ''
              GROUP BY office";
    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $office = trim($row['office']);
            if (!isset($summary[$office])) {
                $summary[$office] = [
                    'OFFICE' => $office,
                    'PERMANENT' => 0,
                    'CONTRACTUAL' => 0,
                    'JOB ORDER' => 0
                ];
            }
            $summary[$office]['JOB ORDER'] = (int) $row['count'];
        }
    }

    // Sort by office name
    ksort($summary);

    return $summary;
}

// Function to fetch employee details by office and type
function fetchEmployeeDetails($conn)
{
    $office = $_GET['office'] ?? '';
    $table = $_GET['table'] ?? '';

    if (empty($office) || empty($table)) {
        return json_encode(['success' => false, 'error' => 'Missing parameters']);
    }

    // Handle contractual table
    if ($table === 'contractofservice') {
        $contractualTable = findContractualTable($conn);
        if (!$contractualTable) {
            return json_encode(['success' => false, 'error' => 'Contractual table not found']);
        }
        $table = $contractualTable;
    }

    // Prepare query based on table type
    $query = "";

    switch ($table) {
        case 'permanent':
            $query = "SELECT
                      CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name,
                      COALESCE(position, 'Not specified') as position,
                      COALESCE(office, 'Not specified') as office,
                      'PERMANENT' as status
                      FROM permanent
                      WHERE office = ?
                      ORDER BY last_name, first_name";
            break;

        case 'job_order':
            $query = "SELECT
                      CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name,
                      COALESCE(position, 'Not specified') as position,
                      COALESCE(office, 'Not specified') as office,
                      'JOB ORDER' as status
                      FROM job_order
                      WHERE office = ?
                      ORDER BY last_name, first_name";
            break;

        default:
            // Handle contractual tables
            $contractualTable = findContractualTable($conn);
            if ($contractualTable) {
                $query = "SELECT
                          CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name,
                          COALESCE(position, 'Not specified') as position,
                          COALESCE(office, 'Not specified') as office,
                          'CONTRACTUAL' as status
                          FROM `$contractualTable`
                          WHERE office = ?
                          ORDER BY last_name, first_name";
            } else {
                return json_encode(['success' => false, 'error' => 'Contractual table not found']);
            }
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return json_encode(['success' => false, 'error' => 'Query preparation failed: ' . $conn->error]);
    }

    $stmt->bind_param("s", $office);
    $stmt->execute();
    $result = $stmt->get_result();

    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = [
            'full_name' => $row['full_name'],
            'position' => $row['position'],
            'office' => $row['office'],
            'status' => $row['status']
        ];
    }

    $stmt->close();

    return json_encode([
        'success' => true,
        'employees' => $employees,
        'count' => count($employees),
        'status' => count($employees) > 0 ? $employees[0]['status'] : '',
        'table_used' => $table
    ]);
}

// Function to fetch all employees in an office
function fetchOfficeSummary($conn)
{
    $office = $_GET['office'] ?? '';

    if (empty($office)) {
        return json_encode(['success' => false, 'error' => 'Missing office parameter']);
    }

    $all_employees = [];

    // Fetch permanent employees
    $query = "SELECT
              CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name,
              COALESCE(position, 'Not specified') as position,
              COALESCE(office, 'Not specified') as office,
              'PERMANENT' as status
              FROM permanent
              WHERE office = ?
              ORDER BY last_name, first_name";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $office);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $all_employees[] = $row;
    }
    $stmt->close();

    // Find and fetch contractual employees
    $contractualTable = findContractualTable($conn);
    if ($contractualTable) {
        $query = "SELECT
                  CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name,
                  COALESCE(position, 'Not specified') as position,
                  COALESCE(office, 'Not specified') as office,
                  'CONTRACTUAL' as status
                  FROM `$contractualTable`
                  WHERE office = ?
                  ORDER BY last_name, first_name";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $office);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $all_employees[] = $row;
        }
        $stmt->close();
    }

    // Fetch job order employees
    $query = "SELECT
              CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name,
              COALESCE(position, 'Not specified') as position,
              COALESCE(office, 'Not specified') as office,
              'JOB ORDER' as status
              FROM job_order
              WHERE office = ?
              ORDER BY last_name, first_name";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $office);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $all_employees[] = $row;
    }
    $stmt->close();

    // Sort by status, then name
    usort($all_employees, function ($a, $b) {
        $status_order = ['PERMANENT' => 1, 'CONTRACTUAL' => 2, 'JOB ORDER' => 3];
        $status_a = $status_order[$a['status']] ?? 99;
        $status_b = $status_order[$b['status']] ?? 99;

        if ($status_a !== $status_b) {
            return $status_a <=> $status_b;
        }
        return strcmp($a['full_name'] ?? '', $b['full_name'] ?? '');
    });

    return json_encode([
        'success' => true,
        'employees' => $all_employees,
        'count' => count($all_employees),
        'contractual_table_found' => !empty($contractualTable)
    ]);
}

// Function to search employees across all tables
function searchEmployees($conn)
{
    $query = $_GET['query'] ?? '';

    if (empty($query) || strlen($query) < 2) {
        return json_encode(['success' => false, 'error' => 'Query too short']);
    }

    $search_term = "%" . $query . "%";
    $results = [];

    // Search in permanent employees
    $sql = "SELECT
            CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name,
            COALESCE(position, 'Not specified') as position,
            COALESCE(office, 'Not specified') as office,
            'PERMANENT' as status
            FROM permanent
            WHERE (CONCAT(first_name, ' ', last_name) LIKE ?
                   OR position LIKE ?
                   OR office LIKE ?)
            ORDER BY last_name, first_name";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();

    // Search in contractual employees
    $contractualTable = findContractualTable($conn);
    if ($contractualTable) {
        $sql = "SELECT
                CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name,
                COALESCE(position, 'Not specified') as position,
                COALESCE(office, 'Not specified') as office,
                'CONTRACTUAL' as status
                FROM `$contractualTable`
                WHERE (CONCAT(first_name, ' ', last_name) LIKE ?
                       OR position LIKE ?
                       OR office LIKE ?)
                ORDER BY last_name, first_name";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $search_term, $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    }

    // Search in job order
    $sql = "SELECT
            CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name,
            COALESCE(position, 'Not specified') as position,
            COALESCE(office, 'Not specified') as office,
            'JOB ORDER' as status
            FROM job_order
            WHERE (CONCAT(first_name, ' ', last_name) LIKE ?
                   OR position LIKE ?
                   OR office LIKE ?)
            ORDER BY last_name, first_name";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();

    return json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results),
        'contractual_table_used' => $contractualTable
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Employee Management Dashboard | HRMS</title>
    <!-- Flowbite CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.2.1/dist/flowbite.min.css" rel="stylesheet" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/output.css">
    <link rel="stylesheet" href="../css/dasboard.css">

    <style>
        :root {
            --primary: #1e40af;
            --primary-dark: #1e3a8a;
            --primary-light: #3b82f6;
            --secondary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: var(--dark);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Navbar Styling */
        .navbar {
            background: var(--gradient-primary);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 1.5rem;
            max-width: 100%;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }


        .mobile-toggle {
            display: none;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
        }

        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }


        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .datetime-container {
            display: flex;
            gap: 0.5rem;
        }

        .datetime-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 0.4rem 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .datetime-box:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .datetime-icon {
            color: white;
            font-size: 0.9rem;
        }

        .datetime-text {
            display: flex;
            flex-direction: column;
        }

        .datetime-label {
            font-size: 0.65rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .datetime-value {
            font-size: 0.8rem;
            font-weight: 700;
            color: white;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 90px;
            right: 10px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-width: 95%;
        }

        .toast {
            background: white;
            border-radius: 10px;
            padding: 0.9rem 1rem;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            min-width: 280px;
            max-width: 400px;
            animation: slideInRight 0.3s ease;
            transform: translateX(100%);
            opacity: 0;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast.success {
            border-left-color: #10b981;
        }

        .toast.error {
            border-left-color: #ef4444;
        }

        .toast-icon {
            font-size: 1.1rem;
        }

        .toast.success .toast-icon {
            color: #10b981;
        }

        .toast.error .toast-icon {
            color: #ef4444;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 0.2rem;
            font-size: 0.9rem;
        }

        .toast-message {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .toast-close {
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0.2rem;
        }

        /* Sidebar */
        .sidebar-container {
            position: fixed;
            left: 0;
            top: 70px;
            width: 260px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            z-index: 999;
            transition: transform 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .sidebar {
            height: 100%;
            padding: 1.5rem 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-content {
            flex: 1;
            padding: 0 1rem;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 1.25rem;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 12px;
            margin-bottom: 0.25rem;
            position: relative;
            overflow: hidden;
        }

        .sidebar-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: white;
            transform: scaleY(0);
            transition: transform 0.3s ease;
            border-radius: 0 4px 4px 0;
        }

        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.12);
            color: white;
            transform: translateX(4px);
        }

        .sidebar-item:hover::before {
            transform: scaleY(1);
        }

        .sidebar-item.active {
            background: rgba(255, 255, 255, 0.18);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .sidebar-item.active::before {
            transform: scaleY(1);
        }

        .sidebar-item i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .sidebar-item span {
            font-size: 0.95rem;
            font-weight: 600;
            flex: 1;
        }

        .dropdown-menu {
            display: none;
            padding-left: 1rem;
            margin-left: 2.5rem;
            border-left: 2px solid rgba(255, 255, 255, 0.1);
            animation: fadeIn 0.3s ease;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-menu .dropdown-item {
            padding: 0.7rem 1rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            border-bottom: none;
        }

        .dropdown-menu .dropdown-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .chevron {
            transition: transform 0.3s ease;
        }

        .chevron.rotate {
            transform: rotate(180deg);
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.75rem;
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 998;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Main Content Styles */
        .container {
            max-width: 100%;
            margin: 0 auto;
            width: 100%;
            margin-top: 10px;
        }

        /* Breadcrumb */
        .breadcrumb {
            background: #f8fafc;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }

        .breadcrumb ol {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .breadcrumb li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb a:hover {
            color: #1e40af;
            text-decoration: underline;
        }

        .breadcrumb .separator {
            color: #9ca3af;
        }

        /* DASHBOARD STYLES */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-light), var(--primary));
        }

        .stat-card.permanent::before {
            background: linear-gradient(90deg, #3b82f6, #1e40af);
        }

        .stat-card.contractual::before {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        .stat-card.joborder::before {
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }

        .stat-card.total::before {
            background: linear-gradient(90deg, #10b981, #059669);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        .stat-card.permanent .stat-icon {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            color: #1e40af;
        }

        .stat-card.contractual .stat-icon {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            color: #92400e;
        }

        .stat-card.joborder .stat-icon {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            color: #991b1b;
        }

        .stat-card.total .stat-icon {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            color: #059669;
        }

        .stat-title {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1f2937;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        /* Search Bar */
        .search-container {
            background: white;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        .search-wrapper {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-input {
            flex: 1;
            position: relative;
        }

        .search-input input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .search-input input:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-input i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .search-button {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .search-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        /* Offices Grid */
        .offices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .office-card {
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .office-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .office-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1.25rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .office-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .office-count {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 500;
        }

        .office-body {
            padding: 1.25rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            text-align: center;
            padding: 0.9rem 0.5rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-item.permanent {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #dbeafe;
        }

        .stat-item.contractual {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 1px solid #fef3c7;
        }

        .stat-item.joborder {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 1px solid #fee2e2;
        }

        .stat-count {
            font-size: 1.4rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-item.permanent .stat-count {
            color: #1e40af;
        }

        .stat-item.contractual .stat-count {
            color: #92400e;
        }

        .stat-item.joborder .stat-count {
            color: #991b1b;
        }

        .stat-label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-item.permanent .stat-label {
            color: #3b82f6;
        }

        .stat-item.contractual .stat-label {
            color: #f59e0b;
        }

        .stat-item.joborder .stat-label {
            color: #ef4444;
        }

        .office-actions {
            display: flex;
            gap: 0.75rem;
        }

        .action-button {
            flex: 1;
            padding: 0.75rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .view-all-btn {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
        }

        .view-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        /* Search Results Modal */
        .search-results-modal {
            position: fixed;
            top: 70px;
            left: 260px;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            z-index: 999;
            padding: 1.5rem;
            overflow-y: auto;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }

        .search-results-modal.active {
            transform: translateX(0);
        }

        .search-results-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .search-results-header h3 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1f2937;
        }

        .close-search-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6b7280;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .close-search-btn:hover {
            background: #f3f4f6;
            color: #1f2937;
        }

        .search-results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        .result-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .result-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .result-name {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .result-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .result-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #6b7280;
        }

        .result-detail i {
            width: 16px;
            color: #9ca3af;
        }

        .result-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .result-status.permanent {
            background: #eff6ff;
            color: #1e40af;
        }

        .result-status.contractual {
            background: #fffbeb;
            color: #92400e;
        }

        .result-status.joborder {
            background: #fef2f2;
            color: #991b1b;
        }

        /* Database Connection Error */
        .db-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            color: #991b1b;
        }

        .db-error h3 {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .db-error p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }

        .empty-state h3 {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #4b5563;
        }

        .empty-state p {
            max-width: 400px;
            margin: 0 auto;
            font-size: 0.9rem;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1100;
            padding: 1rem;
        }

        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            border-radius: 18px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            padding: 1.25rem 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .close-button {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
        }

        .close-button:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-subheader {
            padding: 1rem 1.5rem;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-subheader p {
            color: #4b5563;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .modal-body {
            padding: 1.25rem;
            overflow-y: auto;
            max-height: 50vh;
        }

        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: #6b7280;
        }

        .spinner {
            width: 2.5rem;
            height: 2.5rem;
            border: 3px solid #e5e7eb;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .employee-table {
            width: 100%;
            border-collapse: collapse;
        }

        .employee-table th {
            padding: 0.75rem 0.5rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e5e7eb;
        }

        .employee-table td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid #e5e7eb;
            color: #4b5563;
            word-break: break-word;
        }

        .employee-table tr:last-child td {
            border-bottom: none;
        }

        .employee-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.9rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
            white-space: nowrap;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .sidebar-container {
                transform: translateX(-100%);
            }

            .sidebar-container.active {
                transform: translateX(0);
            }


            .mobile-toggle {
                display: flex;
            }

            .search-results-modal {
                left: 0;
            }

            .offices-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }


        @media (max-width: 768px) {

            .datetime-container {
                display: none;
            }

            .brand-text {
                display: none;
            }


            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .offices-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .search-wrapper {
                flex-direction: column;
            }

            .search-button {
                width: 100%;
                justify-content: center;
            }

            .search-results-modal {
                padding: 1rem;
            }

            .search-results-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                gap: 0.5rem;
            }

            .office-actions {
                flex-direction: column;
            }

            .action-button {
                width: 100%;
            }
        }

        @media (max-width: 480px) {

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.6rem;
            }

            .office-card {
                margin-bottom: 0.75rem;
            }

            .modal-content {
                border-radius: 12px;
                margin: 0;
            }

            .modal-header,
            .modal-subheader,
            .modal-body {
                padding: 1rem;
            }

            .modal-header h3 {
                font-size: 1rem;
            }

            .search-container {
                padding: 1rem;
            }

            .search-input input {
                padding: 0.75rem 1rem 0.75rem 2.5rem;
                font-size: 0.9rem;
            }

            .office-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }

            .office-count {
                font-size: 0.8rem;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        /* Utility Classes */
        .hidden {
            display: none !important;
        }

        .flex {
            display: flex;
        }

        .items-center {
            align-items: center;
        }

        .justify-center {
            justify-content: center;
        }

        .text-center {
            text-align: center;
        }

        /* Status badge colors */
        .bg-indigo-100 {
            background-color: #e0e7ff;
        }

        .text-indigo-800 {
            color: #3730a3;
        }

        .bg-amber-100 {
            background-color: #fef3c7;
        }

        .text-amber-800 {
            color: #92400e;
        }

        .bg-red-100 {
            background-color: #fee2e2;
        }

        .text-red-800 {
            color: #991b1b;
        }
    </style>

    <style>
        /* Your existing CSS styles remain the same */
        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --secondary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            --gradient-secondary: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            --gradient-danger: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --shadow-blue: 0 10px 25px -3px rgba(30, 64, 175, 0.2);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            color: var(--dark);
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f8fafc 100%);
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            background: var(--gradient-primary);
            box-shadow: var(--shadow-md);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .navbar-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 1rem;
            max-width: 100%;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            transition: transform 0.3s ease;
        }

        .mobile-toggle:hover {
            transform: scale(1.1);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .datetime-container {
            display: flex;
            gap: 1rem;
        }

        .datetime-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .datetime-box:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .datetime-icon {
            color: white;
            font-size: 1rem;
        }

        .datetime-text {
            display: flex;
            flex-direction: column;
        }

        .datetime-label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .datetime-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
        }

        /* Sidebar */
        .sidebar-container {
            position: fixed;
            left: 0;
            top: 70px;
            width: 260px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            z-index: 999;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .sidebar {
            height: 100%;
            padding: 1.5rem 0;
        }

        .sidebar-content {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding: 0 1rem;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 1.25rem;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 12px;
            margin-bottom: 0.25rem;
            position: relative;
            overflow: hidden;
        }

        .sidebar-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: white;
            transform: scaleY(0);
            transition: transform 0.3s ease;
            border-radius: 0 4px 4px 0;
        }

        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.12);
            color: white;
            transform: translateX(4px);
        }

        .sidebar-item:hover::before {
            transform: scaleY(1);
        }

        .sidebar-item.active {
            background: rgba(255, 255, 255, 0.18);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .sidebar-item.active::before {
            transform: scaleY(1);
        }

        .sidebar-item i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .sidebar-item span {
            font-size: 0.95rem;
            font-weight: 600;
            flex: 1;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Modal Styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1100;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
        }

        .modal-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1101;
            display: none;
            padding: 1rem;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            width: 100%;
        }

        @media (min-width: 768px) {
            .modal-content {
                max-width: 800px;
            }
        }

        @media (min-width: 1024px) {
            .modal-content {
                max-width: 1000px;
            }
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .action-btn i {
            font-size: 14px;
        }

        .action-btn.view {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .action-btn.view:hover {
            background-color: #2563eb;
            border-color: #2563eb;
        }

        .action-btn.edit {
            background-color: #10b981;
            color: white;
            border-color: #10b981;
        }

        .action-btn.edit:hover {
            background-color: #059669;
            border-color: #059669;
        }

        .action-btn.inactive {
            background-color: #f59e0b;
            color: white;
            border-color: #f59e0b;
        }

        .action-btn.inactive:hover {
            background-color: #d97706;
            border-color: #d97706;
        }

        .action-btn.active {
            background-color: #10b981;
            color: white;
            border-color: #10b981;
        }

        .action-btn.active:hover {
            background-color: #059669;
            border-color: #059669;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background-color: #fef3c7;
            color: #92400e;
        }

        /* Form Steps */
        .form-step,
        .edit-form-step {
            display: none;
        }

        .form-step.active,
        .edit-form-step.active {
            display: block;
        }

        /* Error Messages */
        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Input Error State */
        .input-error {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .sidebar-container {
                transform: translateX(-100%);
            }

            .sidebar-container.active {
                transform: translateX(0);
            }

            .mobile-toggle {
                display: flex;
            }

            .navbar-right .datetime-container {
                display: none;
            }

            .brand-text {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .datetime-container {
                display: none;
            }

            .main-content {
                padding: 1rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }

            .action-btn {
                width: 28px;
                height: 28px;
            }

            .modal-content {
                margin: 0.5rem;
                max-height: 85vh;
            }
        }

        @media (max-width: 640px) {
            .navbar-container {
                padding: 0 0.75rem;
            }

            .main-content {
                padding: 0.75rem;
            }

            .modal-content {
                margin: 0.25rem;
                max-height: 80vh;
            }
        }

        /* Overlay for mobile menu */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
            backdrop-filter: blur(4px);
        }

        .overlay.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        /* Pagination Styles */
        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            background-color: white;
            color: #374151;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 0.375rem;
        }

        .pagination-btn:hover:not(:disabled) {
            background-color: #f3f4f6;
            border-color: #9ca3af;
        }

        .pagination-btn.active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background-color: #3b82f6;
        }

        input:checked+.toggle-slider:before {
            transform: translateX(30px);
        }

        /* Dropdown Menu in Sidebar */
    .sidebar-dropdown-menu {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease;
      margin-left: 2.5rem;
    }

    .sidebar-dropdown-menu.open {
      max-height: 500px;
    }

    .sidebar-dropdown-item {
      display: flex;
      align-items: center;
      padding: 0.5rem 1rem;
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      border-radius: 8px;
      margin-bottom: 0.25rem;
      transition: all 0.3s ease;
      font-size: 0.85rem;
    }

    .sidebar-dropdown-item:hover {
      background: rgba(255, 255, 255, 0.1);
      color: white;
      transform: translateX(5px);
    }

    .sidebar-dropdown-item i {
      font-size: 0.75rem;
      margin-right: 0.5rem;
    }
    </style>
</head>

<body>
    <!-- Navigation Header -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Left Section -->
            <div class="navbar-left">
                <!-- Mobile Menu Toggle -->
                <button class="mobile-toggle" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Logo and Brand -->
                <a href="dashboard.php" class="navbar-brand">
                    <img class="brand-logo"
                        src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                        alt="Logo" />
                    <div class="brand-text">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Paluan Occidental Mindoro</span>
                    </div>
                </a>
            </div>

            <!-- Right Section -->
            <div class="navbar-right">
                <!-- Date & Time -->
                <div class="datetime-container">
                    <div class="datetime-box">
                        <i class="datetime-icon fas fa-calendar-alt"></i>
                        <div class="datetime-text">
                            <span class="datetime-label">Date</span>
                            <span class="datetime-value" id="current-date">Loading...</span>
                        </div>
                    </div>

                    <div class="datetime-box">
                        <i class="datetime-icon fas fa-clock"></i>
                        <div class="datetime-text">
                            <span class="datetime-label">Time</span>
                            <span class="datetime-value" id="current-time">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar -->
    <div class="sidebar-container" id="sidebar-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <!-- Dashboard -->
                <a href="../dashboard.php" class="sidebar-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard Analytics</span>
                </a>

                <!-- Employees -->
                <a href="Employee.php" class="sidebar-item active">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>

                <!-- Attendance -->
                <a href="../attendance.php" class="sidebar-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>

                <!-- Payroll -->
                <a href="#" class="sidebar-item" id="payroll-toggle">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                    <i class="fas fa-chevron-down chevron ml-auto"></i>
                </a>
                <div class="sidebar-dropdown-menu" id="payroll-dropdown">
                    <a href="../Payrollmanagement/contractualpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Contractual
                    </a>
                    <a href="../Payrollmanagement/joboerderpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Job Order
                    </a>
                    <a href="../Payrollmanagement/permanentpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Permanent
                    </a>
                </div>

                <!-- Reports -->
                <a href="../paysliphistory.php" class="sidebar-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>

                <!-- Settings -->
                <a href="../settings.php" class="sidebar-item">
                    <i class="fas fa-sliders-h"></i>
                    <span>Settings</span>
                </a>

                <!-- Logout -->
                <a href="?logout=true" class="sidebar-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="text-center text-white/60 text-sm">
                    <p>HRMS v2.0</p>
                    <p class="text-xs mt-1">© 2024 Paluan LGU</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="bg-white rounded-xl shadow-lg p-4 md:p-6">
            <!-- Breadcrumb Navigation -->
            <nav class="flex mb-4 overflow-x-auto">
                <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse whitespace-nowrap">
                    <li class="inline-flex items-center">
                        <a href="Employee.php"
                            class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-600">
                            <i class="fas fa-home mr-2"></i>All Employee
                        </a>
                    </li>
                    <li>
                        <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                        <a href="permanent.php" class="ms-1 text-sm font-medium  md:ms-2">Permanent</a>
                    </li>
                    <li>
                        <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                        <a href="contractofservice.php"
                            class="ms-1 text-sm font-medium hover:text-blue-600 md:ms-2">Contractual</a>
                    </li>
                    <li>
                        <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                        <a href="Job_order.php"
                            class="ms-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ms-2">Job Order</a>
                    </li>
                </ol>
            </nav>
            <div class="container">
                <!-- Database Connection Error (if any) -->
                <?php if (!$db_connected): ?>
                    <div class="db-error">
                        <h3><i class="fas fa-database"></i> Database Connection Error</h3>
                        <p>Unable to connect to the database. Please check:</p>
                        <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                            <li>1. Database server is running</li>
                            <li>2. Database credentials are correct</li>
                            <li>3. Database '<?php echo $database; ?>' exists</li>
                        </ul>
                        <p style="margin-top: 1rem;"><strong>Error:</strong>
                            <?php echo htmlspecialchars($error_message); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Employee Management Dashboard</h1>
                    <p class="text-gray-600">Manage and view employee data across all departments and employment
                        types
                    </p>
                </div>

                <!-- Statistics Cards -->
                <div class="dashboard-grid" id="stats-cards">
                    <div class="stat-card permanent">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Permanent Employees</div>
                                <div class="stat-value" id="permanent-count">0</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card contractual">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Contractual (COS)</div>
                                <div class="stat-value" id="contractual-count">0</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-file-contract"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card joborder">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Job Order (JO)</div>
                                <div class="stat-value" id="joborder-count">0</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-briefcase"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card total">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Total Employees</div>
                                <div class="stat-value" id="total-count">0</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Debug Info (only show if no data) -->
                <?php if ($db_connected && empty($employee_summary)): ?>
                    <div class="db-error">
                        <h3><i class="fas fa-exclamation-triangle"></i> Debug Information</h3>
                        <p>No employee data found. Please check:</p>
                        <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                            <li>1. Check if tables exist in database: permanent, contractofservice, job_order</li>
                            <li>2. Check if tables have 'office' column</li>
                            <li>3. Check if tables have data with non-empty office values</li>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Search Bar -->
                <div class="search-container">
                    <div class="search-wrapper">
                        <div class="search-input">
                            <i class="fas fa-search"></i>
                            <input type="text" id="search-input"
                                placeholder="Search employees by name, position, or department...">
                        </div>
                        <button class="search-button" id="search-button">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </div>
                </div>

                <!-- Offices Grid -->
                <div class="offices-grid" id="offices-grid">
                    <?php if (!$db_connected): ?>
                        <div class="col-span-full">
                            <div class="empty-state">
                                <i class="fas fa-database"></i>
                                <h3>Database Connection Failed</h3>
                                <p>Unable to connect to database. Please check your database configuration.</p>
                            </div>
                        </div>
                    <?php elseif (empty($employee_summary)): ?>
                        <div class="col-span-full">
                            <div class="empty-state">
                                <i class="fas fa-users-slash"></i>
                                <h3>No Employee Data Found</h3>
                                <p>No employee data found across all offices.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($employee_summary as $office_data):
                            $total_office = ($office_data['PERMANENT'] ?? 0) + ($office_data['CONTRACTUAL'] ?? 0) + ($office_data['JOB ORDER'] ?? 0);
                        ?>
                            <div class="office-card">
                                <div class="office-header">
                                    <div class="office-title">
                                        <?= htmlspecialchars($office_data['OFFICE']) ?>
                                        <span class="office-count"><?= $total_office ?> employees</span>
                                    </div>
                                </div>

                                <div class="office-body">
                                    <div class="stats-grid">
                                        <div class="stat-item permanent"
                                            onclick="viewEmployeeDetails('<?= htmlspecialchars($office_data['OFFICE']) ?>', 'PERMANENT', 'permanent')"
                                            style="cursor: pointer;">
                                            <div class="stat-count"><?= $office_data['PERMANENT'] ?? 0 ?></div>
                                            <div class="stat-label">Permanent</div>
                                        </div>

                                        <div class="stat-item contractual"
                                            onclick="viewEmployeeDetails('<?= htmlspecialchars($office_data['OFFICE']) ?>', 'CONTRACTUAL', 'contractofservice')"
                                            style="cursor: pointer;">
                                            <div class="stat-count"><?= $office_data['CONTRACTUAL'] ?? 0 ?></div>
                                            <div class="stat-label">Contractual</div>
                                        </div>

                                        <div class="stat-item joborder"
                                            onclick="viewEmployeeDetails('<?= htmlspecialchars($office_data['OFFICE']) ?>', 'JOB ORDER', 'job_order')"
                                            style="cursor: pointer;">
                                            <div class="stat-count"><?= $office_data['JOB ORDER'] ?? 0 ?></div>
                                            <div class="stat-label">Job Order</div>
                                        </div>
                                    </div>

                                    <div class="office-actions">
                                        <button class="action-button view-all-btn"
                                            onclick="viewAllEmployees('<?= htmlspecialchars($office_data['OFFICE']) ?>')">
                                            <i class="fas fa-eye mr-2"></i>
                                            View All Employees
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Search Results Modal -->
    <div class="search-results-modal" id="search-results-modal">
        <div class="search-results-header">
            <h3>Search Results</h3>
            <button class="close-search-btn" id="close-search-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="search-results-grid" id="search-results">
            <!-- Search results will be inserted here -->
        </div>
    </div>

    <!-- Employee Modal -->
    <div class="modal-overlay" id="employeeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Employee Details</h3>
                <button class="close-button" id="closeModalBtn">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-subheader">
                <p>Office: <span id="modalOfficeName" class="text-blue-600 font-bold"></span> | Status: <span
                        id="modalStatusType" class="text-blue-600 font-bold"></span></p>
            </div>

            <div class="modal-body">
                <!-- Loading State -->
                <div id="modalLoading" class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading employee list...</p>
                </div>

                <!-- Error State -->
                <div id="modalError" class="hidden">
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">Error Loading Data</h3>
                        <p id="errorMessage" class="text-gray-500">Failed to load employee data. Please try again.</p>
                        <button onclick="retryLoad()"
                            class="mt-4 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">
                            <i class="fas fa-redo mr-2"></i>Retry
                        </button>
                    </div>
                </div>

                <!-- No Data State -->
                <div id="modalNoData" class="hidden">
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">No Employees Found</h3>
                        <p class="text-gray-500">No employee records found for this category.</p>
                    </div>
                </div>

                <!-- Employee List -->
                <div id="employeeListContainer" class="hidden">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Position & Status</th>
                            </tr>
                        </thead>
                        <tbody id="employeeList">
                            <!-- Employee rows will be inserted here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Store last request details for retry
        let lastRequest = {
            type: '',
            office: '',
            table: ''
        };

        document.addEventListener('DOMContentLoaded', function() {
            // ===============================================
            // TOAST NOTIFICATION FUNCTIONS
            // ===============================================
            function showToast(type, title, message) {
                const container = document.getElementById('toast-container');
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.innerHTML = `
                    <div class="toast-icon">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                    </div>
                    <div class="toast-content">
                        <div class="toast-title">${title}</div>
                        <div class="toast-message">${message}</div>
                    </div>
                    <button class="toast-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                `;

                container.appendChild(toast);

                // Trigger animation
                setTimeout(() => toast.classList.add('show'), 10);

                // Auto remove after 5 seconds
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            }

            // ===============================================
            // LOAD DASHBOARD STATISTICS
            // ===============================================
            function loadDashboardStats() {
                fetch('?action=fetch_stats')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            const stats = data.stats;
                            document.getElementById('permanent-count').textContent = stats.total_permanent;
                            document.getElementById('contractual-count').textContent = stats.total_contractual;
                            document.getElementById('joborder-count').textContent = stats.total_joborder;
                            document.getElementById('total-count').textContent = stats.total_employees;
                        } else {
                            showToast('error', 'Error', 'Failed to load statistics');
                        }
                    })
                    .catch(error => {
                        showToast('error', 'Error', 'Failed to load statistics: ' + error.message);
                    });
            }

            // Load stats on page load
            loadDashboardStats();

            // ===============================================
            // NAVBAR DATE & TIME FUNCTIONALITY
            // ===============================================
            function updateDateTime() {
                const now = new Date();

                // Format date: Weekday, Month Day, Year
                const optionsDate = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                };
                const dateString = now.toLocaleDateString('en-US', optionsDate);

                // Format time: HH:MM:SS AM/PM
                let hours = now.getHours();
                let minutes = now.getMinutes();
                let seconds = now.getSeconds();
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12;
                minutes = minutes < 10 ? '0' + minutes : minutes;
                seconds = seconds < 10 ? '0' + seconds : seconds;

                const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;

                // Update the DOM
                document.getElementById('current-date').textContent = dateString;
                document.getElementById('current-time').textContent = timeString;
            }

            // Initial call
            updateDateTime();

            // Update every second
            setInterval(updateDateTime, 1000);

            // Sidebar functionality
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarContainer = document.getElementById('sidebar-container');
            const overlay = document.getElementById('overlay');

            if (sidebarToggle && sidebarContainer && overlay) {
                sidebarToggle.addEventListener('click', function() {
                    sidebarContainer.classList.toggle('active');
                    overlay.classList.toggle('active');
                });

                overlay.addEventListener('click', function() {
                    sidebarContainer.classList.remove('active');
                    overlay.classList.remove('active');
                });
            }

            // Payroll dropdown functionality
            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollDropdown = document.getElementById('payroll-dropdown');

            if (payrollToggle && payrollDropdown) {
                payrollToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Toggle the 'open' class
                    payrollDropdown.classList.toggle('open');

                    // Toggle chevron rotation
                    const chevron = this.querySelector('.chevron');
                    if (chevron) {
                        chevron.classList.toggle('rotated');
                    }
                });
            }

            // ===============================================
            // EMPLOYEE MODAL FUNCTIONALITY
            // ===============================================
            const modal = document.getElementById('employeeModal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const modalOfficeName = document.getElementById('modalOfficeName');
            const modalStatusType = document.getElementById('modalStatusType');
            const employeeList = document.getElementById('employeeList');
            const modalLoading = document.getElementById('modalLoading');
            const modalError = document.getElementById('modalError');
            const modalNoData = document.getElementById('modalNoData');
            const employeeListContainer = document.getElementById('employeeListContainer');
            const errorMessage = document.getElementById('errorMessage');

            // Global functions for employee modal
            window.viewEmployeeDetails = function(office, status, table) {
                // Update modal headers
                modalOfficeName.textContent = office;
                modalStatusType.textContent = status;
                document.getElementById('modalTitle').textContent = 'Employee Details';

                // Store for retry
                lastRequest = {
                    type: 'details',
                    office,
                    table
                };

                // Reset and open modal
                resetModalState();
                openEmployeeModal();

                // Fetch data
                const endpoint = `?action=fetch_details&office=${encodeURIComponent(office)}&table=${encodeURIComponent(table)}`;

                fetch(endpoint)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        modalLoading.classList.add('hidden');

                        if (data.success && data.employees && data.employees.length > 0) {
                            // Display employee data
                            data.employees.forEach(employee => {
                                const row = document.createElement('tr');
                                const statusClass = getStatusClass(employee.status);
                                row.innerHTML = `
                                    <td class="employee-name">${escapeHtml(employee.full_name || 'N/A')}</td>
                                    <td>
                                        ${escapeHtml(employee.position || 'N/A')}
                                        <span class="status-badge ${statusClass}">
                                            ${escapeHtml(employee.status)}
                                        </span>
                                    </td>
                                `;
                                employeeList.appendChild(row);
                            });
                            employeeListContainer.classList.remove('hidden');
                        } else {
                            modalNoData.classList.remove('hidden');
                            if (data.error) {
                                errorMessage.textContent = data.error;
                            }
                        }
                    })
                    .catch(error => {
                        modalLoading.classList.add('hidden');
                        modalError.classList.remove('hidden');
                        errorMessage.textContent = `Failed to load data: ${error.message}`;
                        console.error('Error fetching employee details:', error);
                    });
            };

            window.viewAllEmployees = function(office) {
                // Update modal headers
                modalOfficeName.textContent = office;
                modalStatusType.textContent = 'ALL EMPLOYEES';
                document.getElementById('modalTitle').textContent = 'Office Employee Summary';

                // Store for retry
                lastRequest = {
                    type: 'summary',
                    office
                };

                // Reset and open modal
                resetModalState();
                openEmployeeModal();

                // Fetch data
                const endpoint = `?action=fetch_summary&office=${encodeURIComponent(office)}`;

                fetch(endpoint)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        modalLoading.classList.add('hidden');

                        if (data.success && data.employees && data.employees.length > 0) {
                            // Display all employees with their status
                            data.employees.forEach(employee => {
                                const row = document.createElement('tr');
                                const statusClass = getStatusClass(employee.status);
                                row.innerHTML = `
                                    <td class="employee-name">${escapeHtml(employee.full_name || 'N/A')}</td>
                                    <td>
                                        ${escapeHtml(employee.position || 'N/A')}
                                        <span class="status-badge ${statusClass}">
                                            ${escapeHtml(employee.status)}
                                        </span>
                                    </td>
                                `;
                                employeeList.appendChild(row);
                            });
                            employeeListContainer.classList.remove('hidden');
                        } else {
                            modalNoData.classList.remove('hidden');
                        }
                    })
                    .catch(error => {
                        modalLoading.classList.add('hidden');
                        modalError.classList.remove('hidden');
                        errorMessage.textContent = `Failed to load summary data: ${error.message}`;
                        console.error('Error fetching office summary:', error);
                    });
            };

            // Retry function
            window.retryLoad = function() {
                if (lastRequest.type === 'details') {
                    viewEmployeeDetails(lastRequest.office, modalStatusType.textContent, lastRequest.table);
                } else if (lastRequest.type === 'summary') {
                    viewAllEmployees(lastRequest.office);
                }
            };

            function openEmployeeModal() {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                document.addEventListener('keydown', handleEscapeKey);
            }

            function closeEmployeeModal() {
                modal.classList.remove('active');
                document.body.style.overflow = '';
                document.removeEventListener('keydown', handleEscapeKey);
            }

            function handleEscapeKey(event) {
                if (event.key === 'Escape') {
                    closeEmployeeModal();
                }
            }

            function resetModalState() {
                employeeList.innerHTML = '';
                modalLoading.classList.remove('hidden');
                modalError.classList.add('hidden');
                modalNoData.classList.add('hidden');
                employeeListContainer.classList.add('hidden');
            }

            function getStatusClass(status) {
                switch (status) {
                    case 'PERMANENT':
                        return 'bg-indigo-100 text-indigo-800';
                    case 'CONTRACTUAL':
                        return 'bg-amber-100 text-amber-800';
                    case 'JOB ORDER':
                        return 'bg-red-100 text-red-800';
                    default:
                        return 'bg-gray-100 text-gray-800';
                }
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Modal Event Listeners
            closeModalBtn.addEventListener('click', closeEmployeeModal);
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeEmployeeModal();
                }
            });

            // ===============================================
            // SEARCH FUNCTIONALITY
            // ===============================================
            const searchInput = document.getElementById('search-input');
            const searchButton = document.getElementById('search-button');
            const searchResultsModal = document.getElementById('search-results-modal');
            const closeSearchBtn = document.getElementById('close-search-btn');
            const searchResults = document.getElementById('search-results');

            function performSearch() {
                const query = searchInput.value.trim();

                if (query.length < 2) {
                    showToast('warning', 'Search Error', 'Please enter at least 2 characters to search');
                    return;
                }

                // Show loading in search results
                searchResults.innerHTML = `
                    <div class="col-span-full">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Searching employees...</p>
                        </div>
                    </div>
                `;

                // Open search results modal
                searchResultsModal.classList.add('active');
                document.body.style.overflow = 'hidden';

                // Perform search
                fetch(`?action=search_employees&query=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        searchResults.innerHTML = '';

                        if (data.success && data.results && data.results.length > 0) {
                            data.results.forEach(employee => {
                                const resultCard = document.createElement('div');
                                resultCard.className = 'result-card';
                                const statusClass = employee.status.toLowerCase().replace(' ', '');
                                resultCard.innerHTML = `
                                    <div class="result-name">${escapeHtml(employee.full_name || 'N/A')}</div>
                                    <div class="result-details">
                                        <div class="result-detail">
                                            <i class="fas fa-briefcase"></i>
                                            <span>${escapeHtml(employee.position || 'Not specified')}</span>
                                        </div>
                                        <div class="result-detail">
                                            <i class="fas fa-building"></i>
                                            <span>${escapeHtml(employee.office || 'Not specified')}</span>
                                        </div>
                                    </div>
                                    <span class="result-status ${statusClass}">
                                        ${escapeHtml(employee.status)}
                                    </span>
                                `;
                                searchResults.appendChild(resultCard);
                            });
                        } else {
                            searchResults.innerHTML = `
                                <div class="col-span-full">
                                    <div class="empty-state">
                                        <i class="fas fa-search"></i>
                                        <h3>No Results Found</h3>
                                        <p>No employees found matching "${escapeHtml(query)}"</p>
                                    </div>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        searchResults.innerHTML = `
                            <div class="col-span-full">
                                <div class="empty-state">
                                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                                    <h3>Search Error</h3>
                                    <p>Failed to perform search. Please try again.</p>
                                </div>
                            </div>
                        `;
                        console.error('Search error:', error);
                    });
            }

            // Event listeners for search
            searchButton.addEventListener('click', performSearch);

            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });

            closeSearchBtn.addEventListener('click', function() {
                searchResultsModal.classList.remove('active');
                document.body.style.overflow = '';
            });

            searchResultsModal.addEventListener('click', function(e) {
                if (e.target === searchResultsModal) {
                    searchResultsModal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // ===============================================
            // UTILITY FUNCTIONS
            // ===============================================
            // Handle window resize
            window.addEventListener('resize', function() {
                // Close sidebar if open when resizing to desktop
                if (window.innerWidth >= 768 && sidebarContainer.classList.contains('active')) {
                    sidebarContainer.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }

                // Close modals on very small screens if needed
                if (window.innerWidth < 480) {
                    if (modal.classList.contains('active')) {
                        closeEmployeeModal();
                    }
                    if (searchResultsModal.classList.contains('active')) {
                        searchResultsModal.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                }
            });

            // Auto-refresh stats every 5 minutes
            setInterval(loadDashboardStats, 5 * 60 * 1000);
        });
    </script>
</body>

</html>