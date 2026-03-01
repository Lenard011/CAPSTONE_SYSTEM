<?php
// paysliphistory.php - Employee Payslip History Page
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set session configuration
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

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php?error=session_missing');
    exit();
}

// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'hrms_paluan';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user info from session
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? ($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
$employee_id = $_SESSION['employee_id'] ?? '';

// Get employee's payroll records
$sql = "SELECT 
            p.*,
            u.first_name,
            u.last_name,
            u.employee_id as emp_id_number,
            DATE_FORMAT(p.created_at, '%M %Y') as period_display,
            DATE_FORMAT(p.created_at, '%Y-%m') as period_sort,
            CASE 
                WHEN p.status = 'approved' THEN 'paid'
                WHEN p.status = 'pending' THEN 'pending'
                WHEN p.status = 'processing' THEN 'processing'
                ELSE p.status
            END as status_display
        FROM payroll p
        JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ?
        ORDER BY p.payroll_period DESC, p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$payslips = [];
$total_earnings = 0;
$total_payslips = 0;
$current_month_earnings = 0;
$pending_count = 0;

while ($row = $result->fetch_assoc()) {
    $payslips[] = $row;
    $total_earnings += floatval($row['net_amount'] ?? 0);
    $total_payslips++;
    
    // Check if current month
    if (date('Y-m') === date('Y-m', strtotime($row['created_at']))) {
        $current_month_earnings += floatval($row['net_amount'] ?? 0);
    }
    
    // Count pending/processing payslips
    if (in_array($row['status_display'], ['pending', 'processing'])) {
        $pending_count++;
    }
}

$stmt->close();

// Get distinct years and months for filters
$year_sql = "SELECT DISTINCT YEAR(payroll_period) as year FROM payroll WHERE user_id = ? ORDER BY year DESC";
$year_stmt = $conn->prepare($year_sql);
$year_stmt->bind_param("i", $user_id);
$year_stmt->execute();
$years_result = $year_stmt->get_result();
$years = [];
while ($row = $years_result->fetch_assoc()) {
    $years[] = $row['year'];
}
$year_stmt->close();

$conn->close();

// Format currency function
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Payslip History</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --radius: 12px;
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
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Layout Container */
        .app-container {
            display: flex;
            min-height: 100vh;
            position: relative;
            width: 100%;
        }

        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            display: none;
            backdrop-filter: blur(3px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
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
            z-index: 9999;
            box-shadow: var(--shadow-xl);
            left: -260px;
        }

        .sidebar.active {
            left: 0;
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
            flex-shrink: 0;
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
            flex-shrink: 0;
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
            padding: 1rem;
            transition: var(--transition);
            width: 100%;
        }

        @media (min-width: 1024px) {
            .sidebar {
                left: 0;
                position: fixed;
            }

            .main-content {
                margin-left: 260px;
                width: calc(100% - 260px);
                padding: 1.5rem;
            }
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            background: var(--card-bg);
            padding: 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            flex-wrap: wrap;
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .top-bar {
                padding: 1rem 1.5rem;
                margin-bottom: 2rem;
            }
        }

        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
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
            gap: 0.75rem;
            flex-wrap: wrap;
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
            display: flex;
            align-items: center;
            justify-content: center;
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
            z-index: 10000;
        }

        @media (min-width: 1024px) {
            .mobile-menu-btn {
                display: none;
            }
        }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 640px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .summary-cards {
                grid-template-columns: repeat(4, 1fr);
                gap: 1.5rem;
                margin-bottom: 2rem;
            }
        }

        .summary-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.25rem;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        @media (min-width: 1024px) {
            .summary-card {
                padding: 1.5rem;
                gap: 1.5rem;
            }
        }

        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            box-shadow: var(--shadow-md);
            flex-shrink: 0;
        }

        @media (min-width: 1024px) {
            .summary-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }

        .summary-info h3 {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .summary-info .value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
        }

        @media (min-width: 1024px) {
            .summary-info .value {
                font-size: 1.75rem;
            }
        }

        /* Color Variations */
        .summary-card:nth-child(1) .summary-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .summary-card:nth-child(2) .summary-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .summary-card:nth-child(3) .summary-icon {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .summary-card:nth-child(4) .summary-icon {
            background: linear-gradient(135deg, #06b6d4, #0ea5e9);
        }

        /* Filter Section */
        .filter-section {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.25rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-light);
        }

        @media (min-width: 1024px) {
            .filter-section {
                padding: 1.5rem;
                margin-bottom: 2rem;
            }
        }

        .filter-header {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 768px) {
            .filter-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
            }
        }

        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
        }

        .filter-controls {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            width: 100%;
        }

        @media (min-width: 640px) {
            .filter-controls {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .filter-controls {
                display: flex;
                flex-wrap: wrap;
                gap: 1rem;
                align-items: center;
                grid-template-columns: none;
            }
        }

        .filter-select {
            width: 100%;
        }

        @media (min-width: 1024px) {
            .filter-select {
                min-width: 180px;
                flex: 1;
            }
        }

        .filter-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
            color: var(--dark);
            transition: var(--transition);
            cursor: pointer;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-container {
            width: 100%;
        }

        @media (min-width: 1024px) {
            .search-container {
                position: relative;
                flex-grow: 1;
                min-width: 200px;
            }
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
            color: var(--dark);
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            pointer-events: none;
        }

        .btn {
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
            width: 100%;
        }

        @media (min-width: 1024px) {
            .btn {
                width: auto;
                padding: 0.75rem 1.5rem;
            }
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

        /* Mobile Card View */
        .mobile-card-view {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 768px) {
            .mobile-card-view {
                display: none !important;
            }
        }

        .payslip-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.25rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-light);
            position: relative;
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }

        .payslip-card:nth-child(1) { animation-delay: 0.1s; }
        .payslip-card:nth-child(2) { animation-delay: 0.2s; }
        .payslip-card:nth-child(3) { animation-delay: 0.3s; }
        .payslip-card:nth-child(4) { animation-delay: 0.4s; }
        .payslip-card:nth-child(5) { animation-delay: 0.5s; }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .card-title {
            font-weight: 600;
            font-size: 1rem;
        }

        .card-status {
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            white-space: nowrap;
        }

        .card-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 480px) {
            .card-body {
                grid-template-columns: 1fr;
            }
        }

        .card-item {
            display: flex;
            flex-direction: column;
        }

        .card-label {
            font-size: 0.75rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }

        .card-value {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .card-amount {
            font-family: 'Courier New', monospace;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-light);
        }

        /* Table Section */
        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-light);
        }

        @media (min-width: 1024px) {
            .table-container {
                margin-bottom: 2rem;
            }
        }

        .table-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--gray-light);
            background: linear-gradient(90deg, #f8fafc, #f1f5f9);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .table-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
            }
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }

        .table-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
            width: 100%;
        }

        @media (min-width: 768px) {
            .table-actions {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
                width: auto;
                grid-template-columns: none;
            }
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            display: none;
        }

        @media (min-width: 768px) {
            .table-responsive {
                display: block;
            }
        }

        .payslip-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        .payslip-table thead {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
        }

        .payslip-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            white-space: nowrap;
        }

        .payslip-table tbody tr {
            border-bottom: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .payslip-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .payslip-table td {
            padding: 1rem;
            color: var(--dark);
            font-size: 0.95rem;
            vertical-align: middle;
            white-space: nowrap;
        }

        .checkbox-cell {
            width: 40px;
            text-align: center;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .payslip-checkbox {
            width: 18px;
            height: 18px;
            border: 2px solid var(--gray-light);
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .payslip-checkbox:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .employee-name {
            font-weight: 600;
            color: var(--dark);
        }

        .employee-id {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }

        .amount-cell {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            text-align: right;
            min-width: 120px;
        }

        .amount-positive {
            color: var(--success);
        }

        .amount-negative {
            color: var(--danger);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
        }

        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-processing {
            background: #e0f2fe;
            color: #0369a1;
        }

        .action-btn {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .action-btn:hover {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-dark);
        }

        .view-btn {
            color: var(--primary);
        }

        .download-btn {
            color: var(--success);
        }

        /* Table Footer */
        .table-footer {
            padding: 1.25rem;
            border-top: 1px solid var(--gray-light);
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .table-footer {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
            }
        }

        .summary-info {
            color: var(--gray);
            font-size: 0.9rem;
            text-align: center;
        }

        @media (min-width: 768px) {
            .summary-info {
                text-align: left;
            }
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            width: 100%;
        }

        @media (min-width: 768px) {
            .summary-stats {
                display: flex;
                gap: 1.5rem;
                width: auto;
            }
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        @media (min-width: 768px) {
            .stat-item {
                align-items: flex-start;
            }
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--gray);
        }

        .stat-value {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .stat-total {
            color: var(--primary);
        }

        .stat-selected {
            color: var(--success);
        }

        /* Pagination */
        .pagination {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.25rem;
            border-top: 1px solid var(--gray-light);
        }

        @media (min-width: 768px) {
            .pagination {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
            }
        }

        .pagination-info {
            color: var(--gray);
            font-size: 0.9rem;
            text-align: center;
        }

        @media (min-width: 768px) {
            .pagination-info {
                text-align: left;
            }
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .pagination-btn {
            min-width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--gray-light);
            border-radius: 6px;
            background: white;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .pagination-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .pagination-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn:disabled:hover {
            border-color: var(--gray-light);
            color: var(--gray);
        }

        /* Export Modal */
        .export-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            padding: 1rem;
        }

        .export-modal.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: var(--transition);
        }

        .export-modal.active .modal-content {
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

        .export-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin: 1.5rem 0;
        }

        @media (min-width: 480px) {
            .export-options {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .export-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .export-option:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }

        .export-option.selected {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.1);
        }

        .export-option i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .export-option span {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark);
        }

        /* Footer */
        .footer {
            background: var(--footer-bg);
            color: white;
            padding: 2rem 0 1rem;
            width: 100%;
        }

        @media (min-width: 1024px) {
            .footer {
                margin-left: 260px;
                width: calc(100% - 260px);
                padding: 3rem 0 1.5rem;
            }
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        @media (min-width: 1024px) {
            .footer-content {
                padding: 0 1.5rem;
            }
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (min-width: 768px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .footer-grid {
                grid-template-columns: repeat(4, 1fr);
            }
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
            flex-direction: column;
            gap: 1rem;
            align-items: center;
        }

        @media (min-width: 768px) {
            .footer-bottom {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        .copyright {
            color: #9ca3af;
            font-size: 0.85rem;
            text-align: center;
        }

        @media (min-width: 768px) {
            .copyright {
                text-align: left;
            }
        }

        .copyright strong {
            color: white;
            font-weight: 600;
        }

        .footer-bottom-links {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        @media (min-width: 768px) {
            .footer-bottom-links {
                justify-content: flex-start;
            }
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

        /* Print Styles */
        @media print {

            .sidebar,
            .top-bar-actions,
            .mobile-menu-btn,
            .filter-section,
            .table-actions,
            .action-btn,
            .checkbox-cell,
            .pagination,
            .footer {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 0;
            }

            .table-container {
                box-shadow: none;
                border: 1px solid #000;
            }

            body {
                background: white;
                color: black;
            }

            .payslip-table {
                min-width: 100%;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-light);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Notification Toast */
        .notification-toast {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            z-index: 10001;
            animation: slideInRight 0.3s ease-out;
            max-width: 350px;
        }

        .notification-toast.success {
            background: var(--success);
            color: white;
        }

        .notification-toast.warning {
            background: var(--warning);
            color: white;
        }

        .notification-toast.info {
            background: var(--info);
            color: white;
        }

        .notification-toast.danger {
            background: var(--danger);
            color: white;
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
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Overlay for mobile sidebar -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="sidebar">
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
                    <a href="attendance.php" class="nav-link">
                        <i class="fas fa-history"></i>
                        <span>Attendance History</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="paysliphistory.php" class="nav-link active">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Payslip History</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="about.php" class="nav-link">
                        <i class="fas fa-info-circle"></i>
                        <span>About</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="settings.php" class="nav-link">
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
                        <?php echo strtoupper(substr($full_name, 0, 1) . (strpos($full_name, ' ') ? substr($full_name, strpos($full_name, ' ') + 1, 1) : '')); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($full_name); ?></h4>
                        <p>Employee ID: <?php echo htmlspecialchars($employee_id); ?></p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-header">
                    <h1>Payslip History</h1>
                    <p>View and manage your salary history and payslips</p>
                </div>
                <div class="top-bar-actions">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <?php if ($pending_count > 0): ?>
                        <span class="notification-badge"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="summary-info">
                        <h3>Total Earnings</h3>
                        <div class="value"><?php echo formatCurrency($total_earnings); ?></div>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="summary-info">
                        <h3>Payslips</h3>
                        <div class="value"><?php echo $total_payslips; ?></div>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="summary-info">
                        <h3>This Month</h3>
                        <div class="value"><?php echo formatCurrency($current_month_earnings); ?></div>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="summary-info">
                        <h3>Pending</h3>
                        <div class="value"><?php echo $pending_count; ?></div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header">
                    <h2 class="filter-title">Filter Payslips</h2>
                    <div class="filter-controls">
                        <div class="filter-select">
                            <select class="filter-input" id="yearFilter">
                                <option value="">All Years</option>
                                <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-select">
                            <select class="filter-input" id="monthFilter">
                                <option value="">All Months</option>
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>

                        <div class="filter-select">
                            <select class="filter-input" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                            </select>
                        </div>

                        <div class="search-container">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" placeholder="Search period..." id="searchInput">
                        </div>

                        <button class="btn btn-primary" id="applyFilter">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>

                        <button class="btn btn-secondary" id="resetFilter">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Payslip Table Container -->
            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">Payslip Records</h2>
                    <div class="table-actions">
                        <button class="btn btn-primary" id="exportBtn">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button class="btn btn-success" id="printBtn">
                            <i class="fas fa-print"></i> Print Selected
                        </button>
                        <button class="btn btn-secondary" id="selectAllBtn">
                            <i class="fas fa-check-double"></i> Select All
                        </button>
                    </div>
                </div>

                <!-- Desktop Table View -->
                <div class="table-responsive">
                    <table class="payslip-table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <div class="checkbox-wrapper">
                                        <input type="checkbox" class="payslip-checkbox" id="selectAll">
                                    </div>
                                </th>
                                <th>Period</th>
                                <th>Rate/Day</th>
                                <th>Days Worked</th>
                                <th>Gross Amount</th>
                                <th>Withholding Tax</th>
                                <th>SSS</th>
                                <th>PhilHealth</th>
                                <th>Pag-IBIG</th>
                                <th>Total Deductions</th>
                                <th>Net Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="payslipTableBody">
                            <?php if (empty($payslips)): ?>
                            <tr>
                                <td colspan="13" class="text-center py-8 text-gray-500">
                                    No payslip records found.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($payslips as $index => $payslip): ?>
                                <tr data-id="<?php echo $payslip['payroll_id']; ?>" data-period="<?php echo $payslip['period_display']; ?>" data-status="<?php echo $payslip['status_display']; ?>" data-year="<?php echo date('Y', strtotime($payslip['payroll_period'])); ?>" data-month="<?php echo date('n', strtotime($payslip['payroll_period'])); ?>">
                                    <td class="checkbox-cell">
                                        <div class="checkbox-wrapper">
                                            <input type="checkbox" class="payslip-checkbox" data-id="<?php echo $payslip['payroll_id']; ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="employee-name"><?php echo $payslip['period_display']; ?></div>
                                        <div class="employee-id">Cutoff: <?php echo ucfirst($payslip['payroll_cutoff'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="amount-cell"><?php echo formatCurrency($payslip['daily_rate'] ?? 0); ?></td>
                                    <td><?php echo number_format($payslip['days_present'] ?? 0, 1); ?></td>
                                    <td class="amount-cell"><?php echo formatCurrency($payslip['gross_amount'] ?? 0); ?></td>
                                    <td class="amount-cell amount-negative"><?php echo formatCurrency($payslip['withholding_tax'] ?? 0); ?></td>
                                    <td class="amount-cell amount-negative"><?php echo formatCurrency($payslip['sss'] ?? 0); ?></td>
                                    <td class="amount-cell amount-negative"><?php echo formatCurrency($payslip['philhealth'] ?? 0); ?></td>
                                    <td class="amount-cell amount-negative"><?php echo formatCurrency($payslip['pagibig'] ?? 0); ?></td>
                                    <td class="amount-cell amount-negative"><?php echo formatCurrency($payslip['total_deductions'] ?? 0); ?></td>
                                    <td class="amount-cell amount-positive"><?php echo formatCurrency($payslip['net_amount'] ?? 0); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $payslip['status_display']; ?>">
                                            <?php echo ucfirst($payslip['status_display']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="action-btn view-btn" onclick="viewPayslip(<?php echo $payslip['payroll_id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="action-btn download-btn" onclick="downloadPayslip(<?php echo $payslip['payroll_id']; ?>)">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="mobile-card-view" id="mobileCardView">
                    <?php if (!empty($payslips)): ?>
                        <?php foreach ($payslips as $payslip): ?>
                        <div class="payslip-card" data-id="<?php echo $payslip['payroll_id']; ?>" data-period="<?php echo $payslip['period_display']; ?>" data-status="<?php echo $payslip['status_display']; ?>" data-year="<?php echo date('Y', strtotime($payslip['payroll_period'])); ?>" data-month="<?php echo date('n', strtotime($payslip['payroll_period'])); ?>">
                            <input type="checkbox" class="payslip-checkbox card-checkbox" data-id="<?php echo $payslip['payroll_id']; ?>">
                            <div class="card-header">
                                <div>
                                    <div class="card-title"><?php echo $payslip['period_display']; ?></div>
                                    <div style="font-size: 0.85rem; color: var(--gray); margin-top: 0.25rem;">Cutoff: <?php echo ucfirst($payslip['payroll_cutoff'] ?? 'N/A'); ?></div>
                                </div>
                                <span class="card-status status-<?php echo $payslip['status_display']; ?>">
                                    <?php echo ucfirst($payslip['status_display']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="card-item">
                                    <span class="card-label">Rate/Day</span>
                                    <span class="card-value card-amount"><?php echo formatCurrency($payslip['daily_rate'] ?? 0); ?></span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">Days Worked</span>
                                    <span class="card-value"><?php echo number_format($payslip['days_present'] ?? 0, 1); ?></span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">Gross Amount</span>
                                    <span class="card-value card-amount"><?php echo formatCurrency($payslip['gross_amount'] ?? 0); ?></span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">Withholding Tax</span>
                                    <span class="card-value card-amount amount-negative"><?php echo formatCurrency($payslip['withholding_tax'] ?? 0); ?></span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">SSS</span>
                                    <span class="card-value card-amount amount-negative"><?php echo formatCurrency($payslip['sss'] ?? 0); ?></span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">PhilHealth</span>
                                    <span class="card-value card-amount amount-negative"><?php echo formatCurrency($payslip['philhealth'] ?? 0); ?></span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">Pag-IBIG</span>
                                    <span class="card-value card-amount amount-negative"><?php echo formatCurrency($payslip['pagibig'] ?? 0); ?></span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">Total Deductions</span>
                                    <span class="card-value card-amount amount-negative"><?php echo formatCurrency($payslip['total_deductions'] ?? 0); ?></span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">Net Amount</span>
                                    <span class="card-value card-amount amount-positive"><?php echo formatCurrency($payslip['net_amount'] ?? 0); ?></span>
                                </div>
                            </div>
                            <div class="card-actions">
                                <button class="action-btn view-btn" onclick="viewPayslip(<?php echo $payslip['payroll_id']; ?>)" style="flex: 1;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="action-btn download-btn" onclick="downloadPayslip(<?php echo $payslip['payroll_id']; ?>)" style="flex: 1;">
                                    <i class="fas fa-download"></i> Download
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            No payslip records found.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Table Footer -->
                <div class="table-footer">
                    <div class="summary-info">
                        Showing <strong id="showingCount"><?php echo count($payslips); ?></strong> of <strong id="totalCount"><?php echo count($payslips); ?></strong> payslips
                    </div>
                    <div class="summary-stats">
                        <div class="stat-item">
                            <span class="stat-label">Selected</span>
                            <span class="stat-value stat-selected" id="selectedCount">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Gross</span>
                            <span class="stat-value stat-total" id="totalGross">₱0.00</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Net</span>
                            <span class="stat-value stat-total" id="totalNet">₱0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <div class="pagination-info">
                        Page <strong id="currentPage">1</strong> of <strong id="totalPages">1</strong>
                    </div>
                    <div class="pagination-controls" id="paginationControls">
                        <!-- Pagination buttons will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Export Modal -->
    <div class="export-modal" id="exportModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Export Payslips</h3>
                <button class="modal-close" id="closeModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p>Select format for export:</p>
            <div class="export-options">
                <div class="export-option" data-format="pdf">
                    <i class="fas fa-file-pdf"></i>
                    <span>PDF</span>
                </div>
                <div class="export-option" data-format="excel">
                    <i class="fas fa-file-excel"></i>
                    <span>Excel</span>
                </div>
                <div class="export-option" data-format="csv">
                    <i class="fas fa-file-csv"></i>
                    <span>CSV</span>
                </div>
                <div class="export-option" data-format="print">
                    <i class="fas fa-print"></i>
                    <span>Print</span>
                </div>
            </div>
            <div style="margin-top: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Include:</label>
                <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" checked disabled> Employee Details
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" checked disabled> Salary Breakdown
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="exportAll"> All Payslips
                    </label>
                </div>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1.5rem;">
                <button class="btn btn-secondary" id="cancelExport" style="flex: 1; min-width: 120px;">
                    Cancel
                </button>
                <button class="btn btn-primary" id="confirmExport" style="flex: 1; min-width: 120px;">
                    <i class="fas fa-download mr-2"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="footer-logo">
                        <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                            alt="Logo" class="footer-logo-img">
                        <div>
                            <div class="footer-title">HR Management Office</div>
                            <div>Occidental Mindoro</div>
                        </div>
                    </div>
                    <p class="footer-text">
                        Republic of the Philippines<br>
                        All content is in the public domain unless otherwise stated.
                    </p>
                </div>

                <div class="footer-col">
                    <div class="footer-links">
                        <h4>About GOVPH</h4>
                        <ul>
                            <li><a href="#">Government Structure</a></li>
                            <li><a href="#">Open Data Portal</a></li>
                            <li><a href="#">Official Gazette</a></li>
                            <li><a href="#">Government Services</a></li>
                        </ul>
                    </div>
                </div>

                <div class="footer-col">
                    <div class="footer-links">
                        <h4>Quick Links</h4>
                        <ul>
                            <li><a href="homepage.php">Dashboard</a></li>
                            <li><a href="attendance.php">Attendance</a></li>
                            <li><a href="paysliphistory.php">Payslips</a></li>
                            <li><a href="about.php">About</a></li>
                        </ul>
                    </div>
                </div>

                <div class="footer-col">
                    <div class="footer-links">
                        <h4>Connect With Us</h4>
                        <p class="footer-text">
                            Occidental Mindoro Public Information Office
                        </p>
                        <div class="social-links">
                            <a href="#" class="social-link">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-youtube"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p class="copyright">© 2024 <strong>Human Resource Management Office</strong>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        // Store payslip data from PHP
        const payslipData = <?php echo json_encode($payslips); ?>;

        // State variables
        let currentData = [...payslipData];
        let filteredData = [...payslipData];
        let selectedCount = 0;
        let currentPage = 1;
        const itemsPerPage = 10;

        document.addEventListener('DOMContentLoaded', function () {
            // DOM elements
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const selectAllCheckbox = document.getElementById('selectAll');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const exportBtn = document.getElementById('exportBtn');
            const printBtn = document.getElementById('printBtn');
            const applyFilterBtn = document.getElementById('applyFilter');
            const resetFilterBtn = document.getElementById('resetFilter');
            const exportModal = document.getElementById('exportModal');
            const closeModalBtn = document.getElementById('closeModal');
            const cancelExportBtn = document.getElementById('cancelExport');
            const confirmExportBtn = document.getElementById('confirmExport');
            const exportOptions = document.querySelectorAll('.export-option');
            const searchInput = document.getElementById('searchInput');
            const yearFilter = document.getElementById('yearFilter');
            const monthFilter = document.getElementById('monthFilter');
            const statusFilter = document.getElementById('statusFilter');
            const exportAllCheckbox = document.getElementById('exportAll');
            const payslipTableBody = document.getElementById('payslipTableBody');
            const mobileCardView = document.getElementById('mobileCardView');
            const paginationControls = document.getElementById('paginationControls');
            const showingCountEl = document.getElementById('showingCount');
            const totalCountEl = document.getElementById('totalCount');
            const selectedCountEl = document.getElementById('selectedCount');
            const totalGrossEl = document.getElementById('totalGross');
            const totalNetEl = document.getElementById('totalNet');
            const currentPageEl = document.getElementById('currentPage');
            const totalPagesEl = document.getElementById('totalPages');

            // Initialize
            filteredData = [...payslipData];
            renderTable();
            updateSummary();
            setupPagination();

            // Mobile sidebar toggle
            mobileMenuBtn.addEventListener('click', function () {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
                this.querySelector('i').classList.toggle('fa-bars');
                this.querySelector('i').classList.toggle('fa-times');
            });

            // Close sidebar when clicking on overlay
            sidebarOverlay.addEventListener('click', function () {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
                mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                mobileMenuBtn.querySelector('i').classList.add('fa-bars');
            });

            // Close sidebar when window is resized to desktop size
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 1024 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                    mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                    mobileMenuBtn.querySelector('i').classList.add('fa-bars');
                }
            });

            // Select all checkbox
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function () {
                    const checkboxes = document.querySelectorAll('.payslip-checkbox:not(#selectAll)');
                    const pageData = getCurrentPageData();

                    checkboxes.forEach(cb => {
                        cb.checked = this.checked;
                    });

                    pageData.forEach(item => {
                        item.selected = this.checked;
                    });

                    updateSelectedCount();
                    updateSummary();
                });
            }

            // Select all button
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function () {
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = !selectAllCheckbox.checked;
                        selectAllCheckbox.dispatchEvent(new Event('change'));
                    }
                });
            }

            // Export button
            if (exportBtn) {
                exportBtn.addEventListener('click', function () {
                    exportModal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                });
            }

            // Print button
            if (printBtn) {
                printBtn.addEventListener('click', function () {
                    const selectedItems = filteredData.filter(item => item.selected);
                    if (selectedItems.length === 0) {
                        showNotification('Please select payslips to print', 'warning');
                        return;
                    }
                    printSelectedPayslips(selectedItems);
                });
            }

            // Filter buttons
            if (applyFilterBtn) {
                applyFilterBtn.addEventListener('click', applyFilters);
            }
            
            if (resetFilterBtn) {
                resetFilterBtn.addEventListener('click', resetFilters);
            }

            // Search input
            if (searchInput) {
                searchInput.addEventListener('input', debounce(applyFilters, 300));
            }

            // Modal controls
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', closeModal);
            }
            
            if (cancelExportBtn) {
                cancelExportBtn.addEventListener('click', closeModal);
            }
            
            if (confirmExportBtn) {
                confirmExportBtn.addEventListener('click', confirmExport);
            }

            // Export options
            exportOptions.forEach(option => {
                option.addEventListener('click', function () {
                    exportOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });

            // Export all checkbox
            if (exportAllCheckbox) {
                exportAllCheckbox.addEventListener('change', function () {
                    if (this.checked) {
                        const checkboxes = document.querySelectorAll('.payslip-checkbox:not(#selectAll)');
                        checkboxes.forEach(cb => cb.checked = true);
                        filteredData.forEach(item => item.selected = true);
                        updateSelectedCount();
                        updateSummary();
                    }
                });
            }
        });

        // Functions
        function getCurrentPageData() {
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            return filteredData.slice(startIndex, endIndex);
        }

        function renderTable() {
            const pageData = getCurrentPageData();
            
            // Update counts
            const showingCountEl = document.getElementById('showingCount');
            const totalCountEl = document.getElementById('totalCount');
            const currentPageEl = document.getElementById('currentPage');
            const totalPagesEl = document.getElementById('totalPages');
            
            if (showingCountEl) showingCountEl.textContent = pageData.length;
            if (totalCountEl) totalCountEl.textContent = filteredData.length;
            if (currentPageEl) currentPageEl.textContent = currentPage;
            if (totalPagesEl) totalPagesEl.textContent = Math.ceil(filteredData.length / itemsPerPage);

            // Update desktop table visibility
            const tableBody = document.getElementById('payslipTableBody');
            const mobileView = document.getElementById('mobileCardView');
            
            if (tableBody) {
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach(row => {
                    const id = row.dataset.id;
                    if (id) {
                        const item = filteredData.find(i => i.payroll_id == id);
                        row.style.display = item ? '' : 'none';
                        
                        // Update checkbox state
                        const checkbox = row.querySelector('.payslip-checkbox');
                        if (checkbox && item) {
                            checkbox.checked = item.selected || false;
                        }
                    }
                });
            }
            
            // Update mobile cards
            if (mobileView) {
                const cards = mobileView.querySelectorAll('.payslip-card');
                cards.forEach(card => {
                    const id = card.dataset.id;
                    if (id) {
                        const item = filteredData.find(i => i.payroll_id == id);
                        card.style.display = item ? '' : 'none';
                        
                        // Update checkbox state
                        const checkbox = card.querySelector('.payslip-checkbox');
                        if (checkbox && item) {
                            checkbox.checked = item.selected || false;
                        }
                    }
                });
            }

            // Add event listeners to checkboxes
            const checkboxes = document.querySelectorAll('.payslip-checkbox:not(#selectAll)');
            checkboxes.forEach(cb => {
                cb.addEventListener('change', function () {
                    const id = this.dataset.id;
                    const item = filteredData.find(item => item.payroll_id == id);
                    if (item) {
                        item.selected = this.checked;
                        updateSelectedCount();
                        updateSummary();
                    }
                });
            });

            // Update select all checkbox
            const selectAllCheckbox = document.getElementById('selectAll');
            if (selectAllCheckbox) {
                const pageDataSelected = pageData.filter(item => item.selected);
                selectAllCheckbox.checked = pageDataSelected.length > 0 && pageDataSelected.length === pageData.length;
            }
        }

        function updateSelectedCount() {
            selectedCount = filteredData.filter(item => item.selected).length;
            const selectedCountEl = document.getElementById('selectedCount');
            if (selectedCountEl) {
                selectedCountEl.textContent = selectedCount;
            }
        }

        function updateSummary() {
            const selectedItems = filteredData.filter(item => item.selected);
            let totalGross = 0;
            let totalNet = 0;

            selectedItems.forEach(item => {
                totalGross += parseFloat(item.gross_amount || 0);
                totalNet += parseFloat(item.net_amount || 0);
            });

            const totalGrossEl = document.getElementById('totalGross');
            const totalNetEl = document.getElementById('totalNet');
            
            if (totalGrossEl) {
                totalGrossEl.textContent = '₱' + totalGross.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            
            if (totalNetEl) {
                totalNetEl.textContent = '₱' + totalNet.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        function applyFilters() {
            const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
            const year = document.getElementById('yearFilter')?.value || '';
            const month = document.getElementById('monthFilter')?.value || '';
            const status = document.getElementById('statusFilter')?.value || '';

            filteredData = payslipData.filter(item => {
                // Search filter (by period)
                if (searchTerm && item.period_display && !item.period_display.toLowerCase().includes(searchTerm)) {
                    return false;
                }

                // Year filter
                if (year) {
                    const itemYear = new Date(item.payroll_period).getFullYear();
                    if (itemYear != year) return false;
                }

                // Month filter
                if (month) {
                    const itemMonth = new Date(item.payroll_period).getMonth() + 1;
                    if (itemMonth != month) return false;
                }

                // Status filter
                if (status && item.status_display !== status) {
                    return false;
                }

                return true;
            });

            currentPage = 1;
            renderTable();
            updateSummary();
            setupPagination();
        }

        function resetFilters() {
            const searchInput = document.getElementById('searchInput');
            const yearFilter = document.getElementById('yearFilter');
            const monthFilter = document.getElementById('monthFilter');
            const statusFilter = document.getElementById('statusFilter');
            
            if (searchInput) searchInput.value = '';
            if (yearFilter) yearFilter.value = '';
            if (monthFilter) monthFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            
            filteredData = [...payslipData];
            currentPage = 1;
            renderTable();
            updateSummary();
            setupPagination();
        }

        function setupPagination() {
            const paginationControls = document.getElementById('paginationControls');
            if (!paginationControls) return;

            // Clear existing controls
            paginationControls.innerHTML = '';

            const totalPages = Math.ceil(filteredData.length / itemsPerPage);

            // Previous button
            const prevBtn = document.createElement('button');
            prevBtn.className = 'pagination-btn';
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevBtn.disabled = currentPage === 1;
            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderTable();
                    setupPagination();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
            paginationControls.appendChild(prevBtn);

            // Page number buttons
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, startPage + 4);

            for (let i = startPage; i <= endPage; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
                pageBtn.textContent = i;
                pageBtn.addEventListener('click', () => {
                    currentPage = i;
                    renderTable();
                    setupPagination();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
                paginationControls.appendChild(pageBtn);
            }

            // Next button
            const nextBtn = document.createElement('button');
            nextBtn.className = 'pagination-btn';
            nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    renderTable();
                    setupPagination();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
            paginationControls.appendChild(nextBtn);

            // Update page info
            const currentPageEl = document.getElementById('currentPage');
            const totalPagesEl = document.getElementById('totalPages');
            
            if (currentPageEl) currentPageEl.textContent = currentPage;
            if (totalPagesEl) totalPagesEl.textContent = totalPages;
        }

        function closeModal() {
            const exportModal = document.getElementById('exportModal');
            if (exportModal) {
                exportModal.classList.remove('active');
            }
            document.body.style.overflow = 'auto';
            
            const exportOptions = document.querySelectorAll('.export-option');
            exportOptions.forEach(opt => opt.classList.remove('selected'));
            
            const exportAllCheckbox = document.getElementById('exportAll');
            if (exportAllCheckbox) exportAllCheckbox.checked = false;
        }

        function confirmExport() {
            const selectedFormat = document.querySelector('.export-option.selected')?.dataset.format || 'pdf';
            const exportAll = document.getElementById('exportAll')?.checked || false;
            const itemsToExport = exportAll ? filteredData : filteredData.filter(item => item.selected);

            if (itemsToExport.length === 0) {
                showNotification('Please select payslips to export', 'warning');
                return;
            }

            showNotification(`Exporting ${itemsToExport.length} payslip(s) as ${selectedFormat.toUpperCase()}`, 'success');
            closeModal();

            // In a real application, this would trigger the export/download
            setTimeout(() => {
                if (selectedFormat === 'print') {
                    printSelectedPayslips(itemsToExport);
                } else {
                    // Simulate download
                    const link = document.createElement('a');
                    link.href = '#';
                    link.download = `payslips_${new Date().toISOString().split('T')[0]}.${selectedFormat}`;
                    link.click();
                }
            }, 1000);
        }

        // Utility function for debouncing
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Global functions accessible from onclick handlers
        function viewPayslip(id) {
            const item = payslipData.find(item => item.payroll_id == id);
            if (item) {
                showNotification(`Opening payslip for ${item.period_display}`, 'info');
                
                // Create detailed modal view
                const modalHtml = `
                    <div class="export-modal active" id="payslipDetailModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title">Payslip Details - ${item.period_display}</h3>
                                <button class="modal-close" onclick="document.getElementById('payslipDetailModal').remove(); document.body.style.overflow='auto'">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div style="margin: 1.5rem 0;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                    <div>
                                        <strong>Employee:</strong><br>
                                        ${item.full_name || 'N/A'}<br>
                                        <small>ID: ${item.emp_id_number || 'N/A'}</small>
                                    </div>
                                    <div>
                                        <strong>Pay Period:</strong><br>
                                        ${item.period_display}<br>
                                        <small>Cutoff: ${item.payroll_cutoff || 'N/A'}</small>
                                    </div>
                                </div>
                                
                                <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                                    <h4 style="margin-bottom: 0.5rem;">Earnings</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                        <div>Daily Rate:</div>
                                        <div class="amount-cell">₱${parseFloat(item.daily_rate || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                                        <div>Days Present:</div>
                                        <div>${item.days_present || 0}</div>
                                        <div>Gross Amount:</div>
                                        <div class="amount-cell">₱${parseFloat(item.gross_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                                    </div>
                                </div>
                                
                                <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                                    <h4 style="margin-bottom: 0.5rem;">Deductions</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                        <div>Withholding Tax:</div>
                                        <div class="amount-cell amount-negative">₱${parseFloat(item.withholding_tax || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                                        <div>SSS:</div>
                                        <div class="amount-cell amount-negative">₱${parseFloat(item.sss || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                                        <div>PhilHealth:</div>
                                        <div class="amount-cell amount-negative">₱${parseFloat(item.philhealth || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                                        <div>Pag-IBIG:</div>
                                        <div class="amount-cell amount-negative">₱${parseFloat(item.pagibig || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                                        <div><strong>Total Deductions:</strong></div>
                                        <div class="amount-cell amount-negative"><strong>₱${parseFloat(item.total_deductions || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</strong></div>
                                    </div>
                                </div>
                                
                                <div style="background: #f8fafc; padding: 1rem; border-radius: 8px;">
                                    <h4 style="margin-bottom: 0.5rem;">Net Pay</h4>
                                    <div style="text-align: center; font-size: 1.5rem; font-weight: bold; color: var(--success);">
                                        ₱${parseFloat(item.net_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                    </div>
                                    <div style="text-align: center; margin-top: 0.5rem; color: var(--gray);">
                                        Status: <span class="status-badge status-${item.status_display}">${item.status_display.charAt(0).toUpperCase() + item.status_display.slice(1)}</span>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                                <button class="btn btn-secondary" onclick="document.getElementById('payslipDetailModal').remove(); document.body.style.overflow='auto'" style="flex: 1;">
                                    Close
                                </button>
                                <button class="btn btn-primary" onclick="downloadPayslip(${id})" style="flex: 1;">
                                    <i class="fas fa-download mr-2"></i> Download
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                document.body.style.overflow = 'hidden';
            }
        }

        function downloadPayslip(id) {
            const item = payslipData.find(item => item.payroll_id == id);
            if (item) {
                showNotification(`Downloading payslip for ${item.period_display}`, 'success');
                // In a real application, this would trigger a file download
                setTimeout(() => {
                    const link = document.createElement('a');
                    link.href = '#';
                    link.download = `payslip_${item.period_display.replace(' ', '_')}.pdf`;
                    link.click();
                }, 500);
            }
        }

        function printSelectedPayslips(items) {
            showNotification(`Printing ${items.length} payslip(s)`, 'info');
            // In a real application, this would open a print dialog
            setTimeout(() => {
                window.print();
            }, 1000);
        }

        function showNotification(message, type = 'info') {
            // Remove existing notification
            const existingNotification = document.querySelector('.notification-toast');
            if (existingNotification) {
                existingNotification.remove();
            }

            const notification = document.createElement('div');
            notification.className = `notification-toast ${type}`;
            notification.innerHTML = `
                <div style="display: flex; align-items: flex-start;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : type === 'danger' ? 'times-circle' : 'info-circle'}" style="margin-right: 0.75rem; margin-top: 0.25rem;"></i>
                    <span style="font-size: 0.9rem;">${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            // Remove after delay
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 4000);
        }
    </script>
</body>

</html>