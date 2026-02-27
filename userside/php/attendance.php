<?php
// attendance.php - Employee Attendance History View
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set EXACTLY the same session configuration as login.php
$cookiePath = '/CAPSTONE_SYSTEM/userside/php/';

session_name('HRMS_SESSION');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => $cookiePath,
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// SIMPLE SESSION CHECK
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php?error=session_missing&redirected=true');
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// User is logged in - get variables
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$email = $_SESSION['email'] ?? '';
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$full_name = $_SESSION['full_name'] ?? ($first_name . ' ' . $last_name);
$role = $_SESSION['role'] ?? 'employee';
$access_level = $_SESSION['access_level'] ?? 1;
$employee_id = $_SESSION['employee_id'] ?? '';
$profile_image = $_SESSION['profile_image'] ?? '';

// Database connection
require_once '../../admin/conn.php';

// If employee_id is not set in session, try to fetch it from database
if (empty($employee_id)) {
    $emp_query = "SELECT employee_id, first_name, last_name, full_name, department, position, employment_type 
                  FROM users WHERE id = ?";
    $emp_stmt = $conn->prepare($emp_query);

    if ($emp_stmt) {
        $emp_stmt->bind_param("i", $user_id);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->get_result();

        if ($emp_row = $emp_result->fetch_assoc()) {
            $employee_id = $emp_row['employee_id'];
            $first_name = $emp_row['first_name'] ?? $first_name;
            $last_name = $emp_row['last_name'] ?? $last_name;
            $full_name = $emp_row['full_name'] ?? ($first_name . ' ' . $last_name);
            $department = $emp_row['department'] ?? 'N/A';
            $position = $emp_row['position'] ?? 'N/A';
            $employment_type = $emp_row['employment_type'] ?? 'Regular';

            $_SESSION['employee_id'] = $employee_id;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['department'] = $department;
            $_SESSION['position'] = $position;
            $_SESSION['employment_type'] = $employment_type;
        }
        $emp_stmt->close();
    }
}

// Set employee details array (matching admin format)
$employee_details = [
    'full_name' => $full_name,
    'employee_id' => $employee_id,
    'department' => $department ?? 'N/A',
    'position' => $position ?? 'N/A',
    'type' => $employment_type ?? 'Regular'
];

// Initialize variables
$view_attendance_records = [];
$attendance_total_records = 0;
$attendance_all_records = 0;
$attendance_total_pages = 0;
$attendance_current_page = 1;

// Only proceed with attendance queries if we have an employee_id
if (!empty($employee_id)) {
    // Pagination settings
    $records_per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $offset = ($page - 1) * $records_per_page;

    $attendance_current_page = $page;

    // Filter parameters
    $month_filter = isset($_GET['month']) ? $_GET['month'] : '';
    $year_filter = isset($_GET['year']) ? $_GET['year'] : '';

    // Build the query conditions
    $conditions = ["employee_id = ?"];
    $params = [$employee_id];
    $types = "s";

    // Apply month filter
    if (!empty($month_filter)) {
        $conditions[] = "MONTH(date) = ?";
        $params[] = $month_filter;
        $types .= "i";
    }

    // Apply year filter
    if (!empty($year_filter)) {
        $conditions[] = "YEAR(date) = ?";
        $params[] = $year_filter;
        $types .= "i";
    }

    $where_clause = implode(" AND ", $conditions);

    // Get total records count with filters
    $count_query = "SELECT COUNT(*) as total FROM attendance WHERE $where_clause";
    $count_stmt = $conn->prepare($count_query);

    if ($count_stmt) {
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        if ($count_row = $count_result->fetch_assoc()) {
            $attendance_total_records = $count_row['total'];
        }
        $count_stmt->close();
    }

    // Get total records without filters
    $total_all_query = "SELECT COUNT(*) as total FROM attendance WHERE employee_id = ?";
    $total_all_stmt = $conn->prepare($total_all_query);

    if ($total_all_stmt) {
        $total_all_stmt->bind_param("s", $employee_id);
        $total_all_stmt->execute();
        $total_all_result = $total_all_stmt->get_result();
        if ($total_all_row = $total_all_result->fetch_assoc()) {
            $attendance_all_records = $total_all_row['total'];
        }
        $total_all_stmt->close();
    }

    // Calculate total pages
    $attendance_total_pages = $attendance_total_records > 0 ? ceil($attendance_total_records / $records_per_page) : 0;

    // Get attendance records with pagination
    if ($attendance_total_records > 0) {
        $query = "SELECT * FROM attendance 
                  WHERE $where_clause 
                  ORDER BY date DESC 
                  LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($query);

        if ($stmt) {
            $params[] = $records_per_page;
            $params[] = $offset;
            $types .= "ii";
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $view_attendance_records = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

// Months array for filter
$months = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];

// Success/Error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - My Attendance History</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom styles to match admin exactly */
        .stats-card {
            @apply bg-white rounded-lg shadow-sm p-4 border border-gray-100 hover:shadow-md transition-shadow duration-200;
        }

        .filter-badge {
            @apply inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full;
        }

        .filter-badge .remove-filter {
            @apply ml-1 cursor-pointer hover:text-blue-600 font-bold;
        }

        .attendance-status {
            @apply px-2 py-1 text-xs font-medium rounded-full inline-flex items-center gap-1;
        }

        .status-present {
            @apply bg-green-100 text-green-800;
        }

        .status-absent {
            @apply bg-red-100 text-red-800;
        }

        .status-late {
            @apply bg-yellow-100 text-yellow-800;
        }

        .status-leave {
            @apply bg-purple-100 text-purple-800;
        }

        .action-btn {
            @apply p-1.5 rounded-lg transition-colors duration-150 border border-gray-200 hover:border-gray-300;
        }

        .edit-btn {
            @apply text-blue-600 hover:bg-blue-50;
        }

        .delete-btn {
            @apply text-red-600 hover:bg-red-50;
        }

        .pagination-container {
            @apply flex flex-col sm:flex-row justify-between items-center gap-4 px-4 py-3 bg-white rounded-lg;
        }

        .pagination-info {
            @apply text-sm text-gray-600;
        }

        .pagination-nav {
            @apply flex gap-1;
        }

        .pagination-btn {
            @apply min-w-[36px] h-9 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 text-sm hover:bg-gray-50 hover:border-gray-300 transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed;
        }

        .pagination-btn.active {
            @apply bg-blue-600 text-white border-blue-600 hover:bg-blue-700;
        }

        .pagination-ellipsis {
            @apply flex items-center px-2 text-gray-400;
        }

        .employee-summary {
            @apply bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 rounded-lg mb-6 shadow-md;
        }

        /* Mobile responsive */
        @media (max-width: 640px) {
            .mobile-hidden {
                display: none;
            }

            .mobile-text-sm {
                font-size: 0.75rem;
            }

            .mobile-table-container {
                overflow-x: auto;
            }

            .mobile-table {
                min-width: 600px;
            }
        }

        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
        }
    </style>
    <style>
        /* Modern Variables */
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #60a5fa;
            --secondary: #7c3aed;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --card-bg: #ffffff;
            --sidebar-bg: linear-gradient(180deg, #1e40af 0%, #1e3a8a 100%);
            --footer-bg: linear-gradient(180deg, #111827 0%, #1f2937 100%);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.7;
            overflow-x: hidden;
        }

        /* Layout Container */
        .app-container {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }

        /* Sidebar Navigation */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 1000;
            box-shadow: var(--shadow-xl);
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: white;
        }

        .logo-img {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-title {
            font-size: 1.1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .logo-subtitle {
            font-size: 0.8rem;
            opacity: 0.9;
            font-weight: 500;
        }

        /* Navigation Menu */
        .nav-menu {
            padding: 1rem 0;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            font-weight: 500;
        }

        .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            padding-left: 2rem;
        }

        .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            border-left: 4px solid var(--primary-light);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* User Profile Section */
        .user-profile {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            position: absolute;
            bottom: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 1.2rem;
        }

        .user-details h4 {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .user-details p {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 1.5rem;
            transition: var(--transition);
            width: 100%;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: var(--card-bg);
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--dark);
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            color: var(--gray);
            font-size: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: 50%;
        }

        .notification-btn:hover {
            color: var(--primary);
            background: var(--gray-light);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mobile-menu-btn {
            display: block;
            background: none;
            border: none;
            color: var(--dark);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .mobile-menu-btn:hover {
            background: var(--gray-light);
        }

        /* Settings Layout */
        .settings-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }

        /* Settings Sidebar */
        .settings-sidebar {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-light);
            padding: 1.5rem 0;
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .settings-nav {
            display: flex;
            flex-direction: column;
        }

        .settings-nav-item {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: var(--gray);
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .settings-nav-item:hover {
            background: var(--light);
            color: var(--primary);
        }

        .settings-nav-item.active {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            border-left: 3px solid var(--primary);
        }

        .settings-nav-item i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .settings-nav-item span {
            font-weight: 500;
        }

        /* Settings Content */
        .settings-content {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-light);
            padding: 2rem;
        }

        .settings-section {
            margin-bottom: 2.5rem;
            display: none;
        }

        .settings-section.active {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-light);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .section-title i {
            color: var(--primary);
        }

        .section-description {
            color: var(--gray);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-label span {
            color: var(--danger);
            margin-left: 0.25rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--dark);
            transition: var(--transition);
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-input:disabled {
            background: var(--light);
            cursor: not-allowed;
        }

        .form-hint {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-hint i {
            color: var(--warning);
        }

        /* Grid Layouts */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        /* Profile Picture */
        .profile-picture-container {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--gray-light);
            box-shadow: var(--shadow-md);
        }

        .profile-picture-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            border: 4px solid var(--gray-light);
            box-shadow: var(--shadow-md);
            flex-shrink: 0;
        }

        .profile-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* Checkbox and Toggle Styles */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .checkbox-input {
            width: 18px;
            height: 18px;
            border: 2px solid var(--gray-light);
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .checkbox-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .checkbox-label {
            font-weight: 500;
            color: var(--dark);
            cursor: pointer;
        }

        .toggle-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .toggle-group:hover {
            background: var(--gray-light);
        }

        .toggle-label {
            font-weight: 500;
            color: var(--dark);
        }

        .toggle-description {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }

        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            flex-shrink: 0;
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
            transition: var(--transition);
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
            transition: var(--transition);
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background-color: var(--success);
        }

        input:checked+.toggle-slider:before {
            transform: translateX(30px);
        }

        /* Select Styles */
        .select-wrapper {
            position: relative;
        }

        .select-wrapper i {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            pointer-events: none;
        }

        /* Button Styles */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 1px solid var(--gray-light);
        }

        .btn-secondary:hover {
            background: var(--light);
            border-color: var(--gray);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.2);
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3);
        }

        /* Security Devices */
        .device-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .device-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
            border: 1px solid var(--gray-light);
        }

        .device-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .device-info {
            flex: 1;
            min-width: 0;
        }

        .device-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .device-details {
            font-size: 0.85rem;
            color: var(--gray);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .device-status {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            background: #d1fae5;
            color: #065f46;
            flex-shrink: 0;
        }

        .device-status.inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Audit Log */
        .audit-log {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .audit-item {
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .audit-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .audit-action {
            font-weight: 600;
            color: var(--dark);
        }

        .audit-time {
            font-size: 0.85rem;
            color: var(--gray);
        }

        .audit-details {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .audit-ip {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            display: inline-block;
            margin-top: 0.25rem;
        }

        /* Danger Zone */
        .danger-zone {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 2px solid #f87171;
            border-radius: var(--radius);
            padding: 2rem;
            margin-top: 3rem;
        }

        .danger-zone-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .danger-zone-header i {
            color: #991b1b;
            font-size: 1.5rem;
        }

        .danger-zone-header h3 {
            color: #991b1b;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .danger-zone p {
            color: #7f1d1d;
            margin-bottom: 1.5rem;
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            padding: 1rem;
        }

        .modal.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: var(--radius);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: var(--transition);
        }

        .modal.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--gray);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--danger);
            background: #fee2e2;
        }

        /* --- Footer --- */
        .footer {
            background: var(--footer-bg);
            color: white;
            padding: 3rem 0 1.5rem;
            margin-left: 260px;
            transition: var(--transition);
            width: calc(100% - 260px);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-col {
            display: flex;
            flex-direction: column;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .footer-logo-img {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .footer-logo-text {
            display: flex;
            flex-direction: column;
        }

        .footer-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: white;
        }

        .footer-subtitle {
            font-size: 0.9rem;
            color: #9ca3af;
        }

        .footer-text {
            color: #9ca3af;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .footer-links h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            color: white;
            position: relative;
            padding-bottom: 0.75rem;
        }

        .footer-links h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--primary);
            border-radius: 2px;
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: #9ca3af;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .footer-links a:hover {
            color: white;
            padding-left: 0.5rem;
        }

        .footer-links a i {
            font-size: 0.8rem;
            color: var(--primary);
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 1rem;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            color: #9ca3af;
            font-size: 0.9rem;
        }

        .contact-item i {
            color: var(--primary);
            margin-top: 0.25rem;
            font-size: 1rem;
        }

        .social-links {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .social-link:hover {
            background: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .copyright {
            color: #9ca3af;
            font-size: 0.85rem;
        }

        .copyright strong {
            color: white;
            font-weight: 600;
        }

        .footer-bottom-links {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .footer-bottom-links a {
            color: #9ca3af;
            text-decoration: none;
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .footer-bottom-links a:hover {
            color: white;
        }

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            z-index: 100;
            opacity: 0;
            transform: translateY(100px);
            transition: var(--transition);
            box-shadow: var(--shadow-lg);
        }

        .back-to-top.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .back-to-top:hover {
            background: var(--primary-dark);
            transform: translateY(-5px);
        }

        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            backdrop-filter: blur(3px);
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Desktop Styles */
        @media (min-width: 1025px) {
            .app-container {
                flex-direction: row;
            }

            .sidebar {
                transform: translateX(0);
                position: fixed;
            }

            .main-content {
                margin-left: 260px;
                width: calc(100% - 260px);
            }

            .mobile-menu-btn {
                display: none;
            }

            .sidebar-overlay {
                display: none !important;
            }
        }

        /* Tablet Styles */
        @media (max-width: 1024px) and (min-width: 769px) {
            .settings-container {
                grid-template-columns: 1fr;
            }

            .settings-sidebar {
                position: static;
                margin-bottom: 2rem;
            }

            .profile-picture-container {
                flex-direction: row;
                align-items: center;
            }

            .device-item {
                flex-direction: row;
                align-items: center;
            }

            .section-header {
                flex-direction: row;
                align-items: center;
            }
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
                gap: 1rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .settings-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .settings-sidebar {
                position: static;
                margin-bottom: 1.5rem;
                padding: 1rem 0;
            }

            .settings-nav-item {
                padding: 0.875rem 1.25rem;
                font-size: 0.95rem;
            }

            .settings-content {
                padding: 1.5rem;
            }

            .profile-picture-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.5rem;
            }

            .profile-picture-placeholder {
                width: 100px;
                height: 100px;
                font-size: 2rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .device-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .device-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .section-title {
                font-size: 1.25rem;
            }

            .toggle-group {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .toggle-switch {
                align-self: flex-end;
            }

            .audit-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }

            .danger-zone {
                padding: 1.5rem;
            }

            .back-to-top {
                bottom: 1rem;
                right: 1rem;
                width: 45px;
                height: 45px;
                font-size: 1.25rem;
            }
        }

        /* Small Mobile Styles */
        @media (max-width: 480px) {
            .settings-nav-item {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            .settings-nav-item i {
                font-size: 1rem;
            }

            .btn {
                padding: 0.625rem 1.25rem;
                font-size: 0.9rem;
                width: 100%;
            }

            .btn-secondary,
            .btn-primary,
            .btn-danger {
                width: 100%;
            }

            .profile-actions {
                width: 100%;
            }

            .device-status {
                align-self: flex-start;
            }

            .modal-content {
                padding: 1.5rem;
            }

            .modal-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .modal-close {
                position: absolute;
                top: 1rem;
                right: 1rem;
            }

            .footer-logo {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }

            .social-links {
                justify-content: center;
            }
        }

        /* Large Desktop Styles */
        @media (min-width: 1400px) {
            .main-content {
                padding: 2rem;
            }

            .settings-container {
                max-width: 1200px;
                margin: 0 auto;
                width: 100%;
            }
        }

        /* Print Styles */
        @media print {

            .sidebar,
            .top-bar-actions,
            .mobile-menu-btn,
            .settings-sidebar,
            .btn,
            .toggle-switch,
            .back-to-top,
            .footer,
            .modal {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 0;
            }

            .settings-content {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }

            .toggle-group {
                background: none !important;
                border-bottom: 1px solid #ddd;
            }
        }

        /* Profile Picture Styles */
        .profile-image-wrapper {
            position: relative;
            display: inline-block;
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--gray-light);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            cursor: pointer;
        }

        .profile-picture:hover {
            transform: scale(1.05);
            border-color: var(--primary);
        }

        .upload-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 2rem;
            color: var(--primary);
            z-index: 10;
        }

        /* Profile picture placeholder with initials */
        .profile-picture-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            border: 4px solid var(--gray-light);
            box-shadow: var(--shadow-md);
            cursor: pointer;
            transition: var(--transition);
        }

        .profile-picture-placeholder:hover {
            transform: scale(1.05);
            border-color: var(--primary);
        }

        /* Image preview modal */
        .image-preview-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .image-preview-modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .preview-image {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .close-preview {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: var(--transition);
        }

        .close-preview:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* File upload progress */
        .upload-progress {
            width: 100%;
            height: 6px;
            background: var(--gray-light);
            border-radius: 3px;
            margin-top: 10px;
            overflow: hidden;
            display: none;
        }

        .upload-progress-bar {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: width 0.3s ease;
        }

        /* Password strength indicators */
        #passwordStrength,
        #passwordMatch {
            margin-top: 5px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        #passwordStrength.weak,
        #passwordMatch.mismatch {
            background-color: rgba(239, 68, 68, 0.1);
            border-left: 3px solid #ef4444;
        }

        #passwordStrength.medium {
            background-color: rgba(245, 158, 11, 0.1);
            border-left: 3px solid #f59e0b;
        }

        #passwordStrength.strong {
            background-color: rgba(16, 185, 129, 0.1);
            border-left: 3px solid #10b981;
        }

        #passwordMatch.match {
            background-color: rgba(16, 185, 129, 0.1);
            border-left: 3px solid #10b981;
        }

        /* Device status indicators */
        .device-status.active {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .device-status.inactive {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Security section specific styles */
        .security-preferences {
            margin: 2rem 0;
            padding: 1.5rem;
            background: var(--light);
            border-radius: 12px;
            border: 1px solid var(--gray-light);
        }

        .security-preferences .toggle-group {
            background: white;
            margin-bottom: 1rem;
        }
        .employee-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body class="bg-gray-50 font-['Inter']">
    <div class="app-container flex min-h-screen">
        <!-- Sidebar Navigation (same as before) -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="homepage.php" class="logo-container">
                    <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                        alt="Logo" class="logo-img">
                    <div class="logo-text">
                        <div class="logo-title">HR Management Office</div>
                        <div class="logo-subtitle">Occidental Mindoro</div>
                    </div>
                </a>
            </div>

            <nav class="nav-menu">
                <div class="nav-item">
                    <a href="homepage.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="attendance.php" class="nav-link active">
                        <i class="fas fa-history"></i>
                        <span>Attendance History</span>
                    </a>
                </div>

                <div class="nav-item">
                    <a href="paysliphistory.php" class="nav-link">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Payslip History</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="about.php" class="nav-link">
                        <i class="fas fa-info-circle"></i>
                        <span>About Municipality</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="settings.php" class="nav-link ">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="logout.php" class="nav-link" onclick="return confirm('Are you sure you want to logout?');">
                        <i class="fas fa-power-off"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>

            <div class="user-profile">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($full_name); ?></h4>
                        <p>Employee ID: <?php echo htmlspecialchars($employee_id); ?></p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content flex-1 ml-64 p-4 md:p-6">
            <!-- Top Bar -->
            <div class="flex justify-between items-center mb-6 bg-white p-4 rounded-lg shadow-sm">
                <div class="page-header">
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900">My Attendance History</h1>
                    <p class="text-gray-600 text-sm">Track and manage your attendance records</p>
                </div>
                <div class="top-bar-actions flex items-center gap-3">
                    <button class="notification-btn relative p-2 text-gray-600 hover:text-blue-600 transition-colors">
                        <i class="fas fa-bell text-xl"></i>
                        <span
                            class="notification-badge absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-xs flex items-center justify-center rounded-full">3</span>
                    </button>
                    <button class="mobile-menu-btn hidden p-2 text-gray-600 hover:text-blue-600" id="mobileMenuBtn">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Main Content Card - Exactly matching admin layout -->
            <div class="bg-white rounded-lg shadow-md p-4 md:p-6">
                <!-- Header with Back Button (simplified for user side) -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900">Attendance Records</h1>
                        <p class="text-gray-600">for <?php echo htmlspecialchars($employee_details['full_name']); ?></p>
                    </div>
                </div>

                <!-- Status Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="p-3 md:p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 import-success" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="p-3 md:p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 import-error" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Month/Year Filter Section -->
                <div class="bg-gray-50 p-4 rounded-lg mb-4 card-shadow">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Attendance Records</h3>
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-filter text-gray-500"></i>
                            <span class="font-medium text-gray-700">Filter by:</span>
                        </div>

                        <div class="flex flex-wrap gap-3 items-center">
                            <!-- Month Filter -->
                            <div class="relative">
                                <select id="monthFilter"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-40 p-2.5">
                                    <option value="">All Months</option>
                                    <?php
                                    $months = [
                                        1 => 'January',
                                        2 => 'February',
                                        3 => 'March',
                                        4 => 'April',
                                        5 => 'May',
                                        6 => 'June',
                                        7 => 'July',
                                        8 => 'August',
                                        9 => 'September',
                                        10 => 'October',
                                        11 => 'November',
                                        12 => 'December'
                                    ];
                                    $selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : '';
                                    foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo $selectedMonth == $num ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Year Filter -->
                            <div class="relative">
                                <select id="yearFilter"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-28 p-2.5">
                                    <option value="">All Years</option>
                                    <?php
                                    $currentYear = (int) date('Y');
                                    $selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : '';
                                    for ($year = $currentYear; $year >= $currentYear - 5; $year--): ?>
                                        <option value="<?php echo $year; ?>" <?php echo $selectedYear == $year ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <!-- Filter Action Buttons -->
                            <button type="button" onclick="applyAttendanceFilter()"
                                class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out flex items-center">
                                <i class="fas fa-search mr-2"></i>
                                Apply Filter
                            </button>

                            <button type="button" onclick="clearAttendanceFilter()"
                                class="text-gray-700 bg-gray-200 hover:bg-gray-300 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out flex items-center">
                                <i class="fas fa-times mr-2"></i>
                                Clear
                            </button>
                        </div>
                    </div>

                    <!-- Active Filter Display -->
                    <?php if (isset($_GET['month']) || isset($_GET['year'])): ?>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="text-sm text-gray-600 mr-2">Active filters:</span>
                            <?php if (isset($_GET['month']) && !empty($_GET['month'])): ?>
                                <span class="filter-badge">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo $months[(int) $_GET['month']] ?? ''; ?>
                                    <span class="remove-filter" onclick="clearAttendanceFilter()">×</span>
                                </span>
                            <?php endif; ?>
                            <?php if (isset($_GET['year']) && !empty($_GET['year'])): ?>
                                <span class="filter-badge">
                                    <i class="fas fa-calendar-alt"></i>
                                    Year <?php echo htmlspecialchars($_GET['year']); ?>
                                    <span class="remove-filter" onclick="clearAttendanceFilter()">×</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Employee Summary -->
                <div class="employee-summary">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                        <div>
                            <h2 class="text-xl font-bold mb-1">
                                <?php echo htmlspecialchars($employee_details['full_name']); ?>
                            </h2>
                            <p class="text-white/80">ID:
                                <?php echo htmlspecialchars($employee_details['employee_id']); ?> |
                                <?php echo htmlspecialchars($employee_details['type']); ?> Employee
                            </p>
                        </div>
                        <div class="mt-4 md:mt-0 text-right">
                            <p class="text-white/80">Department:
                                <?php echo htmlspecialchars($employee_details['department']); ?>
                            </p>
                            <p class="text-white/80">Total Records: <?php echo $attendance_all_records; ?></p>
                            <?php if (isset($_GET['month']) || isset($_GET['year'])): ?>
                                <p class="text-white/80 text-sm">Showing: <?php echo $attendance_total_records; ?> filtered
                                    records</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Attendance Statistics - Using FILTERED records -->
                <?php if (!empty($view_attendance_records)): ?>
                    <?php
                    // Calculate statistics based on FILTERED records only
                    $present_count = 0;
                    $absent_count = 0;
                    $late_count = 0;
                    $total_hours = 0;
                    $total_ot = 0;

                    foreach ($view_attendance_records as $record) {
                        if ($record['total_hours'] > 0)
                            $present_count++;
                        if ($record['total_hours'] == 0)
                            $absent_count++;
                        if ($record['under_time'] > 0)
                            $late_count++;
                        $total_hours += $record['total_hours'];
                        $total_ot += $record['ot_hours'];
                    }

                    $present_rate = count($view_attendance_records) > 0 ? round(($present_count / count($view_attendance_records)) * 100, 1) : 0;
                    ?>

                    <!-- Filter Info Banner -->
                    <?php if (isset($_GET['month']) || isset($_GET['year'])): ?>
                        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-4" role="alert">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle mr-2"></i>
                                <p>
                                    <span class="font-bold">Filtered View:</span>
                                    Showing statistics for
                                    <?php
                                    $filter_parts = [];
                                    if (isset($_GET['month'])) {
                                        $filter_parts[] = $months[(int) $_GET['month']];
                                    }
                                    if (isset($_GET['year'])) {
                                        $filter_parts[] = $_GET['year'];
                                    }
                                    echo implode(' ', $filter_parts);
                                    ?> only.
                                    <a href="?view_attendance=true&employee_id=<?php echo urlencode($view_employee_id); ?>"
                                        class="underline ml-2 font-semibold">
                                        Clear filter to see all records
                                    </a>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="stats-card">
                            <div class="flex items-center">
                                <div class="p-3 rounded-lg bg-green-100 mr-4">
                                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Present Days</p>
                                    <p class="text-2xl font-bold"><?php echo $present_count; ?></p>
                                    <p class="text-xs text-green-600"><?php echo $present_rate; ?>% Present Rate</p>
                                </div>
                            </div>
                        </div>

                        <div class="stats-card">
                            <div class="flex items-center">
                                <div class="p-3 rounded-lg bg-red-100 mr-4">
                                    <i class="fas fa-times-circle text-red-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Absent Days</p>
                                    <p class="text-2xl font-bold"><?php echo $absent_count; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="stats-card">
                            <div class="flex items-center">
                                <div class="p-3 rounded-lg bg-yellow-100 mr-4">
                                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Late Days</p>
                                    <p class="text-2xl font-bold"><?php echo $late_count; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="stats-card">
                            <div class="flex items-center">
                                <div class="p-3 rounded-lg bg-blue-100 mr-4">
                                    <i class="fas fa-chart-line text-blue-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Total Hours</p>
                                    <p class="text-2xl font-bold"><?php echo round($total_hours, 1); ?></p>
                                    <p class="text-xs text-blue-600"><?php echo round($total_ot, 1); ?>h OT</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search Results Info (showing filtered vs total) -->
                    <div class="mb-4 flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            <?php if (isset($_GET['month']) || isset($_GET['year'])): ?>
                                <span class="font-semibold text-blue-600">Filtered:</span>
                            <?php endif; ?>

                            Showing
                            <?php if (isset($attendance_total_records) && $attendance_total_records > 0): ?>
                                <span class="font-semibold"><?php echo $offset + 1; ?></span> to
                                <span
                                    class="font-semibold"><?php echo min($offset + $records_per_page, $attendance_total_records); ?></span>
                                of
                                <span class="font-semibold"><?php echo $attendance_total_records; ?></span> records
                            <?php else: ?>
                                <span class="font-semibold">0</span> records
                            <?php endif; ?>

                            <?php if (isset($_GET['month']) || isset($_GET['year'])): ?>
                                <span class="ml-2 text-blue-600">(filtered from <?php echo $attendance_all_records; ?> total
                                    records)</span>
                            <?php endif; ?>
                        </div>

                        <!-- Records per page selector -->
                        <?php if (isset($attendance_total_pages) && $attendance_total_pages > 1): ?>
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-600">Show:</label>
                                <select id="attPerPage" onchange="changeAttendancePerPage(this.value)"
                                    class="border border-gray-300 rounded-lg text-sm p-1.5">
                                    <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="20" <?php echo $records_per_page == 20 ? 'selected' : ''; ?>>20</option>
                                    <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Attendance Table -->
                    <div class="table-container overflow-x-auto rounded-lg border border-gray-200 mobile-table-container">
                        <table class="w-full text-sm text-left text-gray-900 mobile-table">
                            <thead class="text-xs text-white uppercase bg-blue-600">
                                <tr>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm">Date</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm">Day</th>
                                    <th scope="col" class="px-4 py-3 text-center mobile-text-sm" colspan="2">AM</th>
                                    <th scope="col" class="px-4 py-3 text-center mobile-text-sm" colspan="2">PM</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm mobile-hidden">OT</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm mobile-hidden">UnderTime</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm mobile-hidden">Total</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm">Status</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($view_attendance_records as $row):
                                    $day_name = date('D', strtotime($row['date']));
                                    $is_weekend = ($day_name == 'Sat' || $day_name == 'Sun');

                                    // Determine status
                                    $status = 'Present';
                                    $status_class = 'status-present';

                                    if ($row['total_hours'] == 0) {
                                        $status = 'Absent';
                                        $status_class = 'status-absent';
                                    } elseif ($row['under_time'] > 0) {
                                        $status = 'Late';
                                        $status_class = 'status-late';
                                    } elseif ($is_weekend && $row['total_hours'] > 0) {
                                        $status = 'Weekend Work';
                                        $status_class = 'status-leave';
                                    }
                                    ?>
                                    <tr class="bg-white hover:bg-gray-50 transition-colors duration-150">
                                        <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap mobile-text-sm">
                                            <?php echo date('M d, Y', strtotime($row['date'])); ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 mobile-text-sm"><?php echo $day_name; ?></td>
                                        <td class="px-4 py-3 text-center text-gray-700 mobile-text-sm">
                                            <?php echo !empty($row['am_time_in']) ? date('h:i A', strtotime($row['am_time_in'])) : '<span class="text-gray-400">--</span>'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center text-gray-700 mobile-text-sm">
                                            <?php echo !empty($row['am_time_out']) ? date('h:i A', strtotime($row['am_time_out'])) : '<span class="text-gray-400">--</span>'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center text-gray-700 mobile-text-sm">
                                            <?php echo !empty($row['pm_time_in']) ? date('h:i A', strtotime($row['pm_time_in'])) : '<span class="text-gray-400">--</span>'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center text-gray-700 mobile-text-sm">
                                            <?php echo !empty($row['pm_time_out']) ? date('h:i A', strtotime($row['pm_time_out'])) : '<span class="text-gray-400">--</span>'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 mobile-text-sm mobile-hidden">
                                            <?php echo $row['ot_hours']; ?>h
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 mobile-text-sm mobile-hidden">
                                            <?php echo $row['under_time']; ?>h
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-gray-900 mobile-text-sm mobile-hidden">
                                            <?php echo $row['total_hours']; ?>h
                                        </td>
                                        <td class="px-4 py-3 mobile-text-sm">
                                            <span class="attendance-status <?php echo $status_class; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 mobile-text-sm text-center">
                                            <div class="flex space-x-1 justify-center">
                                                <button type="button"
                                                    onclick="viewAttendanceDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                    class="action-btn edit-btn" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($attendance_total_pages > 0): ?>
                        <div id="attendancePaginationContainer" class="pagination-container mt-4">
                            <div class="pagination-info">
                                Page <span id="attCurrentPage"><?php echo $attendance_current_page; ?></span> of
                                <span id="attTotalPages"><?php echo $attendance_total_pages; ?></span>
                                <?php if ($attendance_total_records > 0): ?>
                                    (<?php echo $attendance_total_records; ?> records)
                                <?php else: ?>
                                    (No records found)
                                <?php endif; ?>
                            </div>

                            <?php if ($attendance_total_pages > 1): ?>
                                <div class="pagination-nav" id="attendancePaginationNav">
                                    <?php
                                    // First page button
                                    if ($attendance_current_page > 1) {
                                        echo '<button onclick="changeAttendancePage(1)" class="pagination-btn" title="First Page">
                                            <i class="fas fa-angle-double-left"></i>
                                        </button>';
                                    } else {
                                        echo '<button disabled class="pagination-btn" title="First Page">
                                            <i class="fas fa-angle-double-left"></i>
                                        </button>';
                                    }

                                    // Previous page button
                                    if ($attendance_current_page > 1) {
                                        echo '<button onclick="changeAttendancePage(' . ($attendance_current_page - 1) . ')" class="pagination-btn" title="Previous Page">
                                            <i class="fas fa-angle-left"></i>
                                        </button>';
                                    } else {
                                        echo '<button disabled class="pagination-btn" title="Previous Page">
                                            <i class="fas fa-angle-left"></i>
                                        </button>';
                                    }

                                    // Calculate range of page numbers to show
                                    $start_page = max(1, $attendance_current_page - 2);
                                    $end_page = min($attendance_total_pages, $attendance_current_page + 2);

                                    // Show first page with ellipsis if needed
                                    if ($start_page > 1) {
                                        echo '<button onclick="changeAttendancePage(1)" class="pagination-btn">1</button>';
                                        if ($start_page > 2) {
                                            echo '<span class="pagination-ellipsis">...</span>';
                                        }
                                    }

                                    // Show page numbers in range
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        $active_class = ($i == $attendance_current_page) ? 'active' : '';
                                        echo "<button onclick=\"changeAttendancePage($i)\" class=\"pagination-btn $active_class\">$i</button>";
                                    }

                                    // Show last page with ellipsis if needed
                                    if ($end_page < $attendance_total_pages) {
                                        if ($end_page < $attendance_total_pages - 1) {
                                            echo '<span class="pagination-ellipsis">...</span>';
                                        }
                                        echo "<button onclick=\"changeAttendancePage($attendance_total_pages)\" class=\"pagination-btn\">$attendance_total_pages</button>";
                                    }

                                    // Next page button
                                    if ($attendance_current_page < $attendance_total_pages) {
                                        echo '<button onclick="changeAttendancePage(' . ($attendance_current_page + 1) . ')" class="pagination-btn" title="Next Page">
                                            <i class="fas fa-angle-right"></i>
                                        </button>';
                                    } else {
                                        echo '<button disabled class="pagination-btn" title="Next Page">
                                            <i class="fas fa-angle-right"></i>
                                        </button>';
                                    }

                                    // Last page button
                                    if ($attendance_current_page < $attendance_total_pages) {
                                        echo '<button onclick="changeAttendancePage(' . $attendance_total_pages . ')" class="pagination-btn" title="Last Page">
                                            <i class="fas fa-angle-double-right"></i>
                                        </button>';
                                    } else {
                                        echo '<button disabled class="pagination-btn" title="Last Page">
                                            <i class="fas fa-angle-double-right"></i>
                                        </button>';
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-times text-5xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">No Attendance Records Found</h3>
                        <p class="text-gray-500">
                            <?php if (isset($_GET['month']) || isset($_GET['year'])): ?>
                                No records found for the selected filter.
                                <a href="attendance.php" class="text-blue-600 underline">
                                    Clear filter to see all records
                                </a>
                            <?php else: ?>
                                You don't have any attendance records yet.
                            <?php endif; ?>
                        </p>
                        <div class="mt-4">
                            <p class="text-sm text-gray-600">Your attendance records will appear here once processed by HR.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- View Details Modal -->
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50" id="viewModal">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-xl font-semibold text-gray-900">Attendance Details</h3>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-4" id="modalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="flex justify-end gap-3 p-6 border-t bg-gray-50">
                <button onclick="closeViewModal()"
                    class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function () {
                sidebar.classList.toggle('-translate-x-full');
            });
        }

        // Filter functions
        function applyAttendanceFilter() {
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;

            let url = 'attendance.php?';
            const params = [];

            if (month) params.push('month=' + month);
            if (year) params.push('year=' + year);

            window.location.href = url + params.join('&');
        }

        function clearAttendanceFilter() {
            window.location.href = 'attendance.php';
        }

        function changeAttendancePage(page) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);

            // Preserve existing filters
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;

            if (month) url.searchParams.set('month', month);
            if (year) url.searchParams.set('year', year);

            window.location.href = url.toString();
        }

        function changeAttendancePerPage(perPage) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', perPage);
            url.searchParams.set('page', 1); // Reset to first page

            // Preserve existing filters
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;

            if (month) url.searchParams.set('month', month);
            if (year) url.searchParams.set('year', year);

            window.location.href = url.toString();
        }

        // View attendance details
        function viewAttendanceDetails(record) {
            const modal = document.getElementById('viewModal');
            const modalBody = document.getElementById('modalBody');

            const day_name = new Date(record.date).toLocaleDateString('en-US', { weekday: 'short' });

            // Determine status
            let status = 'Present';
            let statusClass = 'bg-green-100 text-green-800';

            if (record.total_hours == 0) {
                status = 'Absent';
                statusClass = 'bg-red-100 text-red-800';
            } else if (record.under_time > 0) {
                status = 'Late';
                statusClass = 'bg-yellow-100 text-yellow-800';
            } else if ((day_name == 'Sat' || day_name == 'Sun') && record.total_hours > 0) {
                status = 'Weekend Work';
                statusClass = 'bg-purple-100 text-purple-800';
            }

            modalBody.innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 bg-gray-50 p-4 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Date:</span>
                            <span class="font-semibold">${new Date(record.date).toLocaleDateString('en-US', {
                year: 'numeric', month: 'long', day: 'numeric'
            })} (${day_name})</span>
                        </div>
                    </div>
                    
                    <div class="col-span-2">
                        <h4 class="font-semibold text-gray-700 mb-2">AM Session</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-blue-50 p-3 rounded-lg">
                                <span class="text-xs text-blue-600 block">Time In</span>
                                <span class="font-mono font-medium">${record.am_time_in ? new Date(record.am_time_in).toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit'
            }) : '—'}</span>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-lg">
                                <span class="text-xs text-blue-600 block">Time Out</span>
                                <span class="font-mono font-medium">${record.am_time_out ? new Date(record.am_time_out).toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit'
            }) : '—'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-span-2">
                        <h4 class="font-semibold text-gray-700 mb-2">PM Session</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-green-50 p-3 rounded-lg">
                                <span class="text-xs text-green-600 block">Time In</span>
                                <span class="font-mono font-medium">${record.pm_time_in ? new Date(record.pm_time_in).toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit'
            }) : '—'}</span>
                            </div>
                            <div class="bg-green-50 p-3 rounded-lg">
                                <span class="text-xs text-green-600 block">Time Out</span>
                                <span class="font-mono font-medium">${record.pm_time_out ? new Date(record.pm_time_out).toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit'
            }) : '—'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-purple-50 p-3 rounded-lg">
                        <span class="text-xs text-purple-600 block">OT Hours</span>
                        <span class="font-semibold ${record.ot_hours > 0 ? 'text-purple-700' : 'text-gray-500'}">${record.ot_hours} hours</span>
                    </div>
                    
                    <div class="bg-orange-50 p-3 rounded-lg">
                        <span class="text-xs text-orange-600 block">UnderTime</span>
                        <span class="font-semibold ${record.under_time > 0 ? 'text-orange-700' : 'text-gray-500'}">${record.under_time} hours</span>
                    </div>
                    
                    <div class="col-span-2 bg-gray-50 p-4 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Hours:</span>
                            <span class="text-xl font-bold text-blue-600">${record.total_hours} hours</span>
                        </div>
                    </div>
                    
                    <div class="col-span-2">
                        <div class="flex justify-between items-center p-3 rounded-lg ${statusClass}">
                            <span class="font-medium">Status:</span>
                            <span class="font-semibold">${status}</span>
                        </div>
                    </div>
                </div>
            `;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
            document.getElementById('viewModal').classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeViewModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('viewModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeViewModal();
            }
        });
    </script>
</body>

</html>