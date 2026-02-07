<?php
// homepage.php - SIMPLE WORKING VERSION
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

// Add debug output as HTML comment (view page source to see)
echo "<!-- =========== HOMEPAGE SESSION DEBUG =========== -->\n";
echo "<!-- Session ID: " . session_id() . " -->\n";
echo "<!-- Cookie Path: " . $cookiePath . " -->\n";
echo "<!-- Session Data: " . json_encode($_SESSION) . " -->\n";
echo "<!-- ============================================= -->\n";

// SIMPLE SESSION CHECK
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Redirect to login with error
    header('Location: login.php?error=session_missing');
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// User is logged in - get variables
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'] ?? '';
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$full_name = $_SESSION['full_name'] ?? ($first_name . ' ' . $last_name);
$role = $_SESSION['role'] ?? 'employee';
$access_level = $_SESSION['access_level'] ?? 1;
$employee_id = $_SESSION['employee_id'] ?? '';
$profile_image = $_SESSION['profile_image'] ?? '';

// Check for forced password change
if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] === true) {
    header('Location: change_password.php');
    exit();
}

// Log successful access
error_log("User " . $username . " accessed homepage successfully");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Attendance History</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
        }

        /* Layout Container */
        .app-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
            display: none;
            backdrop-filter: blur(3px);
        }

        .sidebar-overlay.active {
            display: block;
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
            z-index: 100;
            box-shadow: var(--shadow-xl);
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
            margin-left: 260px;
            padding: 1.5rem;
            transition: var(--transition);
            width: calc(100% - 260px);
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
            font-size: clamp(1.5rem, 4vw, 1.75rem);
            font-weight: 700;
            color: var(--dark);
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: var(--gray);
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            margin-top: 0.25rem;
        }

        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
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
            display: none;
            background: none;
            border: none;
            color: var(--dark);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius);
            z-index: 101;
        }

        /* Attendance Overview Cards */
        .overview-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .overview-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .overview-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: var(--shadow-md);
            flex-shrink: 0;
        }

        .card-info h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .card-info .value {
            font-size: clamp(1.5rem, 4vw, 1.75rem);
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
        }

        /* Color Variations */
        .overview-card:nth-child(1) .card-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .overview-card:nth-child(2) .card-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .overview-card:nth-child(3) .card-icon {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .overview-card:nth-child(4) .card-icon {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        /* Filter Section */
        .filter-section {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            border: 1px solid var(--gray-light);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
        }

        .filter-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }

        .date-range-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .date-input-wrapper {
            position: relative;
            flex: 1;
            min-width: 180px;
        }

        .date-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
            color: var(--dark);
            transition: var(--transition);
            cursor: pointer;
        }

        .date-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .date-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            pointer-events: none;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: flex;
            align-items: center;
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

        .btn-icon {
            padding: 0.75rem;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--gray-light);
            color: var(--dark);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon:hover {
            background: var(--light);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Table Section */
        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 2rem;
            border: 1px solid var(--gray-light);
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            background: linear-gradient(90deg, #f8fafc, #f1f5f9);
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .attendance-table thead {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
        }

        .attendance-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            white-space: nowrap;
        }

        .attendance-table tbody tr {
            border-bottom: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .attendance-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .attendance-table td {
            padding: 1rem;
            color: var(--dark);
            font-size: 0.95rem;
            vertical-align: middle;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-present {
            background: #d1fae5;
            color: #065f46;
        }

        .status-late {
            background: #fef3c7;
            color: #92400e;
        }

        .status-absent {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-pending {
            background: #e0f2fe;
            color: #0369a1;
        }

        .time-cell {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            background: #f8fafc;
            padding: 0.5rem;
            border-radius: 6px;
            text-align: center;
            min-width: 70px;
            display: block;
            margin-bottom: 0.25rem;
        }

        .time-cell:last-child {
            margin-bottom: 0;
        }

        .time-early {
            color: var(--success);
        }

        .time-late {
            color: var(--warning);
        }

        .time-missing {
            color: var(--danger);
            font-style: italic;
        }

        .hours-cell {
            font-weight: 700;
            text-align: center;
        }

        .hours-positive {
            color: var(--success);
        }

        .hours-negative {
            color: var(--danger);
        }

        .table-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-light);
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .summary-info {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .summary-stats {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--gray);
        }

        .stat-value {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .stat-ot {
            color: var(--success);
        }

        .stat-ut {
            color: var(--danger);
        }

        .stat-total {
            color: var(--primary);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-top: 1px solid var(--gray-light);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .pagination-info {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
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

        /* Mobile Card View (Alternative to table) */
        .mobile-card-view {
            display: none;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .attendance-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-light);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .card-date {
            font-weight: 600;
            font-size: 1rem;
        }

        .card-status {
            font-size: 0.85rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }

        .card-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
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
            font-size: 0.95rem;
        }

        .card-footer {
            padding-top: 1rem;
            border-top: 1px solid var(--gray-light);
            font-size: 0.85rem;
            color: var(--gray);
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

        /* Responsive Design */

        /* Large Desktop (1200px and up) */
        @media (min-width: 1200px) {
            .overview-cards {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        /* Desktop (1024px to 1199px) */
        @media (min-width: 1024px) and (max-width: 1199px) {
            .overview-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Tablet (768px to 1023px) */
        @media (max-width: 1023px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }

            .mobile-menu-btn {
                display: block;
            }

            .overview-cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .top-bar {
                flex-direction: row;
                align-items: center;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .filter-header {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .date-range-container {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }

            .date-input-wrapper {
                min-width: 100%;
            }
        }

        /* Small Tablet (600px to 767px) */
        @media (max-width: 767px) {
            .overview-cards {
                grid-template-columns: 1fr;
            }

            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .top-bar-actions {
                width: 100%;
                justify-content: space-between;
            }

            .table-header {
                padding: 1rem;
            }

            .table-title {
                font-size: 1.1rem;
            }

            .attendance-table {
                min-width: 1000px;
            }

            .attendance-table th,
            .attendance-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.85rem;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
                flex: 1;
                justify-content: center;
            }

            .table-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .summary-stats {
                width: 100%;
                justify-content: space-between;
            }

            /* Show mobile cards, hide table */
            .table-responsive {
                display: none;
            }

            .mobile-card-view {
                display: grid;
                grid-template-columns: 1fr;
            }
        }

        /* Mobile (480px to 599px) */
        @media (max-width: 599px) {
            .main-content {
                padding: 0.75rem;
            }

            .card-info .value {
                font-size: 1.5rem;
            }

            .overview-card {
                padding: 1rem;
                gap: 1rem;
            }

            .card-icon {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }

            .filter-section {
                padding: 1rem;
            }

            .btn-icon {
                padding: 0.5rem;
            }

            .table-footer {
                padding: 1rem;
            }

            .footer {
                padding: 2rem 0 1rem;
            }
        }

        /* Small Mobile (below 480px) */
        @media (max-width: 479px) {
            .overview-card {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .card-icon {
                width: 60px;
                height: 60px;
            }

            .filter-controls {
                gap: 0.75rem;
            }

            .btn {
                width: 100%;
            }

            .summary-stats {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .stat-item {
                flex-direction: row;
                align-items: center;
                gap: 1rem;
                width: 100%;
                justify-content: space-between;
            }

            .pagination {
                flex-direction: column;
                align-items: flex-start;
            }

            .pagination-controls {
                width: 100%;
                justify-content: center;
            }

            .logo-title {
                font-size: 1rem;
            }

            .logo-subtitle {
                font-size: 0.75rem;
            }

            .user-details h4 {
                font-size: 0.85rem;
            }

            .user-details p {
                font-size: 0.75rem;
            }

            .footer-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Print Styles */
        @media print {

            .sidebar,
            .top-bar-actions,
            .filter-section,
            .pagination,
            .footer {
                display: none !important;
            }

            .stat-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
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
                    <div class="user-avatar">JA</div>
                    <div class="user-details">
                        <h4>Joy Ambrosio</h4>
                        <p>Employee ID: BSC02</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-header">
                    <h1>Attendance History</h1>
                    <p>Track and manage your attendance records</p>
                </div>
                <div class="top-bar-actions">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Overview Cards -->
            <div class="overview-cards">
                <div class="overview-card">
                    <div class="card-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="card-info">
                        <h3>Days Present</h3>
                        <div class="value">18</div>
                    </div>
                </div>

                <div class="overview-card">
                    <div class="card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="card-info">
                        <h3>Late Arrivals</h3>
                        <div class="value">4</div>
                    </div>
                </div>

                <div class="overview-card">
                    <div class="card-icon">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="card-info">
                        <h3>Absences</h3>
                        <div class="value">2</div>
                    </div>
                </div>

                <div class="overview-card">
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-info">
                        <h3>Avg. Hours/Day</h3>
                        <div class="value">7.6</div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header">
                    <h2 class="filter-title">Filter Records</h2>
                    <div class="filter-controls">
                        <div class="date-range-container">
                            <div class="date-input-wrapper">
                                <i class="fas fa-calendar date-icon"></i>
                                <input type="text" class="date-input" placeholder="Start Date" id="startDate" readonly>
                            </div>
                            <span class="text-gray-500">to</span>
                            <div class="date-input-wrapper">
                                <i class="fas fa-calendar date-icon"></i>
                                <input type="text" class="date-input" placeholder="End Date" id="endDate" readonly>
                            </div>
                        </div>

                        <button class="btn btn-primary" id="applyFiltersBtn">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>

                        <button class="btn btn-secondary" id="resetFiltersBtn">
                            <i class="fas fa-redo"></i> Reset
                        </button>

                        <button class="btn-icon" id="exportBtn" title="Export Data">
                            <i class="fas fa-download"></i>
                        </button>

                        <button class="btn-icon" id="printBtn" title="Print Report">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Attendance Table Container -->
            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">Detailed Attendance Records</h2>
                    <div class="table-actions">
                        <select class="date-input" id="departmentFilter"
                            style="width: auto; padding: 0.5rem 1rem; min-width: 150px;">
                            <option value="all">All Departments</option>
                            <option value="hr">HR Office</option>
                            <option value="budget">Budget Office</option>
                            <option value="accounting">Accounting</option>
                        </select>
                        <select class="date-input" id="statusFilter"
                            style="width: auto; padding: 0.5rem 1rem; min-width: 120px;">
                            <option value="all">All Status</option>
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                        </select>
                    </div>
                </div>

                <!-- Desktop Table View -->
                <div class="table-responsive">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>AM Session</th>
                                <th>PM Session</th>
                                <th>OT Hours</th>
                                <th>UnderTime</th>
                                <th>Total Hours</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <!-- Table rows will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="mobile-card-view" id="mobileCardView">
                    <!-- Mobile cards will be populated by JavaScript -->
                </div>

                <!-- Summary Footer -->
                <div class="table-footer">
                    <div class="summary-info" id="summaryInfo">
                        Loading attendance records...
                    </div>
                    <div class="summary-stats">
                        <div class="stat-item">
                            <span class="stat-label">Total OT</span>
                            <span class="stat-value stat-ot" id="totalOT">4.21 hrs</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total UT</span>
                            <span class="stat-value stat-ut" id="totalUT">19.52 hrs</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Grand Total</span>
                            <span class="stat-value stat-total" id="totalHours">60.31 hrs</span>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <div class="pagination-info" id="paginationInfo">
                        Page 1 of 6
                    </div>
                    <div class="pagination-controls" id="paginationControls">
                        <!-- Pagination buttons will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </main>
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
                            <li><a href="leave.php">Leave Management</a></li>
                            <li><a href="paysliphistory.php">Payslips</a></li>
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
                <p>© 2024 <strong>Human Resource Management Office</strong>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Include flatpickr for better date picker -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        // Sample attendance data
        const attendanceData = [
            {
                date: "Oct 22, 2024",
                day: "Sunday",
                status: "Present",
                amIn: "7:17 AM",
                amOut: "10:33 AM",
                pmIn: "11:31 AM",
                pmOut: "4:04 PM",
                otHours: "0.00",
                underTime: "0.22",
                totalHours: "7.78",
                remarks: "Regular day"
            },
            {
                date: "Oct 12, 2024",
                day: "Saturday",
                status: "Late AM",
                amIn: "7:12 AM",
                amOut: "—",
                pmIn: "12:22 PM",
                pmOut: "5:46 PM",
                otHours: "0.00",
                underTime: "2.83",
                totalHours: "5.17",
                remarks: "Half day AM, Filed leave"
            },
            {
                date: "Dec 18, 2024",
                day: "Wednesday",
                status: "Absent",
                amIn: "—",
                amOut: "—",
                pmIn: "—",
                pmOut: "—",
                otHours: "0.00",
                underTime: "8.00",
                totalHours: "0.00",
                remarks: "Sick leave approved"
            },
            {
                date: "Dec 15, 2024",
                day: "Sunday",
                status: "Present",
                amIn: "6:45 AM",
                amOut: "11:30 AM",
                pmIn: "12:15 PM",
                pmOut: "6:30 PM",
                otHours: "1.50",
                underTime: "0.00",
                totalHours: "9.50",
                remarks: "Overtime approved"
            },
            {
                date: "Dec 14, 2024",
                day: "Saturday",
                status: "Present",
                amIn: "7:00 AM",
                amOut: "11:45 AM",
                pmIn: "12:30 PM",
                pmOut: "5:15 PM",
                otHours: "0.00",
                underTime: "0.30",
                totalHours: "7.50",
                remarks: "Regular day"
            },
            {
                date: "Dec 13, 2024",
                day: "Friday",
                status: "Late PM",
                amIn: "7:05 AM",
                amOut: "11:40 AM",
                pmIn: "12:45 PM",
                pmOut: "5:20 PM",
                otHours: "0.75",
                underTime: "0.50",
                totalHours: "8.25",
                remarks: "Traffic delay"
            }
        ];

        document.addEventListener('DOMContentLoaded', function () {
            // DOM Elements
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const attendanceTableBody = document.getElementById('attendanceTableBody');
            const mobileCardView = document.getElementById('mobileCardView');
            const summaryInfo = document.getElementById('summaryInfo');
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationControls = document.getElementById('paginationControls');
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');
            const departmentFilter = document.getElementById('departmentFilter');
            const statusFilter = document.getElementById('statusFilter');
            const applyFiltersBtn = document.getElementById('applyFiltersBtn');
            const resetFiltersBtn = document.getElementById('resetFiltersBtn');
            const exportBtn = document.getElementById('exportBtn');
            const printBtn = document.getElementById('printBtn');
            const totalOT = document.getElementById('totalOT');
            const totalUT = document.getElementById('totalUT');
            const totalHours = document.getElementById('totalHours');

            // Initialize datepickers
            flatpickr(startDateInput, {
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function (selectedDates, dateStr) {
                    if (dateStr) {
                        endDatePicker.set('minDate', dateStr);
                    }
                }
            });

            const endDatePicker = flatpickr(endDateInput, {
                dateFormat: "Y-m-d",
                maxDate: "today"
            });

            // Toggle sidebar on mobile
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
                if (window.innerWidth > 1023 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                    mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                    mobileMenuBtn.querySelector('i').classList.add('fa-bars');
                }
            });

            // Initialize table with data
            renderAttendanceData(attendanceData);
            setupPagination(attendanceData.length, 4);

            // Filter button functionality
            applyFiltersBtn.addEventListener('click', function () {
                const startDate = startDateInput.value;
                const endDate = endDateInput.value;
                const department = departmentFilter.value;
                const status = statusFilter.value;

                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                this.disabled = true;

                // Simulate filtering
                setTimeout(() => {
                    // In a real app, this would be an API call
                    let filteredData = [...attendanceData];

                    // Apply date filters
                    if (startDate || endDate) {
                        showNotification('Date filter applied!', 'success');
                    }

                    // Apply status filter
                    if (status !== 'all') {
                        filteredData = filteredData.filter(item =>
                            item.status.toLowerCase().includes(status)
                        );
                    }

                    // Update display
                    renderAttendanceData(filteredData);
                    setupPagination(filteredData.length, 4);

                    // Restore button
                    this.innerHTML = originalText;
                    this.disabled = false;

                    if (filteredData.length === 0) {
                        showNotification('No records found for the selected filters.', 'warning');
                    }
                }, 800);
            });

            // Reset button functionality
            resetFiltersBtn.addEventListener('click', function () {
                startDateInput.value = '';
                endDateInput.value = '';
                departmentFilter.value = 'all';
                statusFilter.value = 'all';

                renderAttendanceData(attendanceData);
                setupPagination(attendanceData.length, 4);
                showNotification('Filters cleared!', 'info');
            });

            // Export button functionality
            exportBtn.addEventListener('click', function () {
                showNotification('Exporting data to CSV...', 'info');
                // In a real app, this would trigger a CSV download
            });

            // Print button functionality
            printBtn.addEventListener('click', function () {
                window.print();
            });

            // Functions
            function renderAttendanceData(data) {
                // Clear existing content
                attendanceTableBody.innerHTML = '';
                mobileCardView.innerHTML = '';

                // Update summary
                const totalRecords = data.length;
                summaryInfo.textContent = `Showing 1-${Math.min(4, totalRecords)} of ${totalRecords} attendance records`;

                // Calculate totals
                let otTotal = 0;
                let utTotal = 0;
                let hoursTotal = 0;

                data.forEach((record, index) => {
                    // Add to totals
                    otTotal += parseFloat(record.otHours);
                    utTotal += parseFloat(record.underTime);
                    hoursTotal += parseFloat(record.totalHours);

                    // Create table row for desktop
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <div class="font-medium">${record.date}</div>
                            <div class="text-sm text-gray-500">${record.day}</div>
                        </td>
                        <td>
                            <span class="status-indicator ${getStatusClass(record.status)}">
                                <i class="${getStatusIcon(record.status)}"></i> ${record.status}
                            </span>
                        </td>
                        <td>
                            <div class="time-cell ${getTimeClass(record.amIn)}">${record.amIn}</div>
                            <div class="time-cell">${record.amOut}</div>
                        </td>
                        <td>
                            <div class="time-cell ${getTimeClass(record.pmIn)}">${record.pmIn}</div>
                            <div class="time-cell">${record.pmOut}</div>
                        </td>
                        <td class="hours-cell ${parseFloat(record.otHours) > 0 ? 'hours-positive' : ''}">${record.otHours}</td>
                        <td class="hours-cell ${parseFloat(record.underTime) > 0 ? 'hours-negative' : ''}">${record.underTime}</td>
                        <td class="hours-cell">${record.totalHours}</td>
                        <td class="text-sm text-gray-600">${record.remarks}</td>
                    `;
                    row.addEventListener('click', () => showAttendanceDetails(record));
                    attendanceTableBody.appendChild(row);

                    // Create card for mobile view
                    const card = document.createElement('div');
                    card.className = 'attendance-card';
                    card.innerHTML = `
                        <div class="card-header">
                            <div class="card-date">${record.date} (${record.day})</div>
                            <div class="card-status ${getStatusClass(record.status)}">${record.status}</div>
                        </div>
                        <div class="card-body">
                            <div class="card-item">
                                <span class="card-label">AM In</span>
                                <span class="card-value ${getTimeClass(record.amIn)}">${record.amIn}</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">AM Out</span>
                                <span class="card-value">${record.amOut}</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">PM In</span>
                                <span class="card-value ${getTimeClass(record.pmIn)}">${record.pmIn}</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">PM Out</span>
                                <span class="card-value">${record.pmOut}</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">OT Hours</span>
                                <span class="card-value ${parseFloat(record.otHours) > 0 ? 'hours-positive' : ''}">${record.otHours}</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">UnderTime</span>
                                <span class="card-value ${parseFloat(record.underTime) > 0 ? 'hours-negative' : ''}">${record.underTime}</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">Total Hours</span>
                                <span class="card-value">${record.totalHours}</span>
                            </div>
                        </div>
                        <div class="card-footer">
                            ${record.remarks}
                        </div>
                    `;
                    card.addEventListener('click', () => showAttendanceDetails(record));
                    mobileCardView.appendChild(card);
                });

                // Update totals
                totalOT.textContent = `${otTotal.toFixed(2)} hrs`;
                totalUT.textContent = `${utTotal.toFixed(2)} hrs`;
                totalHours.textContent = `${hoursTotal.toFixed(2)} hrs`;
            }

            function setupPagination(totalItems, itemsPerPage) {
                const totalPages = Math.ceil(totalItems / itemsPerPage);
                paginationInfo.textContent = `Page 1 of ${totalPages}`;

                // Clear existing controls
                paginationControls.innerHTML = '';

                // Previous button
                const prevBtn = document.createElement('button');
                prevBtn.className = 'pagination-btn';
                prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
                prevBtn.addEventListener('click', () => changePage(0));
                paginationControls.appendChild(prevBtn);

                // Page number buttons
                for (let i = 1; i <= totalPages; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = 'pagination-btn' + (i === 1 ? ' active' : '');
                    pageBtn.textContent = i;
                    pageBtn.addEventListener('click', () => changePage(i));
                    paginationControls.appendChild(pageBtn);
                }

                // Next button
                const nextBtn = document.createElement('button');
                nextBtn.className = 'pagination-btn';
                nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
                nextBtn.addEventListener('click', () => changePage(totalPages + 1));
                paginationControls.appendChild(nextBtn);
            }

            function changePage(pageNum) {
                // In a real app, this would fetch new data from the server
                showNotification(`Loading page ${pageNum}...`, 'info');

                // Update active button
                document.querySelectorAll('.pagination-btn').forEach((btn, index) => {
                    btn.classList.remove('active');
                    if (btn.textContent === pageNum.toString()) {
                        btn.classList.add('active');
                    }
                });
            }

            function getStatusClass(status) {
                if (status.includes('Present')) return 'status-present';
                if (status.includes('Late')) return 'status-late';
                if (status.includes('Absent')) return 'status-absent';
                return 'status-pending';
            }

            function getStatusIcon(status) {
                if (status.includes('Present')) return 'fas fa-check-circle';
                if (status.includes('Late')) return 'fas fa-clock';
                if (status.includes('Absent')) return 'fas fa-ban';
                return 'fas fa-question-circle';
            }

            function getTimeClass(time) {
                if (time === '—') return 'time-missing';
                if (time.includes('6:') || time.includes('7:')) return 'time-early';
                if (time.includes('8:') || time.includes('9:')) return 'time-late';
                return '';
            }

            function showAttendanceDetails(data) {
                const modalHtml = `
                    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <h3 class="text-xl font-bold text-gray-900">Attendance Details</h3>
                                    <button class="text-gray-400 hover:text-gray-600" onclick="this.closest('.fixed').remove()">
                                        <i class="fas fa-times text-xl"></i>
                                    </button>
                                </div>
                                
                                <div class="space-y-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Date:</span>
                                        <span class="font-semibold">${data.date} (${data.day})</span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Status:</span>
                                        <span class="${getStatusClass(data.status)} px-3 py-1 rounded-full text-sm font-semibold">
                                            <i class="${getStatusIcon(data.status)} mr-1"></i> ${data.status}
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 pt-4 border-t">
                                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                                            <div class="text-sm text-gray-500 mb-1">AM In</div>
                                            <div class="text-lg font-mono font-semibold ${getTimeClass(data.amIn)}">${data.amIn}</div>
                                        </div>
                                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                                            <div class="text-sm text-gray-500 mb-1">AM Out</div>
                                            <div class="text-lg font-mono font-semibold">${data.amOut}</div>
                                        </div>
                                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                                            <div class="text-sm text-gray-500 mb-1">PM In</div>
                                            <div class="text-lg font-mono font-semibold ${getTimeClass(data.pmIn)}">${data.pmIn}</div>
                                        </div>
                                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                                            <div class="text-sm text-gray-500 mb-1">PM Out</div>
                                            <div class="text-lg font-mono font-semibold">${data.pmOut}</div>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-3 gap-4 pt-4 border-t">
                                        <div class="text-center p-3 bg-blue-50 rounded-lg">
                                            <div class="text-sm text-blue-600 mb-1">OT Hours</div>
                                            <div class="text-lg font-bold ${parseFloat(data.otHours) > 0 ? 'text-green-600' : 'text-gray-600'}">${data.otHours}</div>
                                        </div>
                                        <div class="text-center p-3 bg-red-50 rounded-lg">
                                            <div class="text-sm text-red-600 mb-1">UnderTime</div>
                                            <div class="text-lg font-bold ${parseFloat(data.underTime) > 0 ? 'text-red-600' : 'text-gray-600'}">${data.underTime}</div>
                                        </div>
                                        <div class="text-center p-3 bg-green-50 rounded-lg">
                                            <div class="text-sm text-green-600 mb-1">Total Hours</div>
                                            <div class="text-lg font-bold">${data.totalHours}</div>
                                        </div>
                                    </div>
                                    
                                    <div class="pt-4 border-t">
                                        <div class="text-sm text-gray-500 mb-2">Remarks:</div>
                                        <div class="bg-gray-50 p-3 rounded-lg text-gray-700">${data.remarks}</div>
                                    </div>
                                </div>
                                
                                <div class="mt-6 flex justify-end space-x-3">
                                    <button class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50" onclick="this.closest('.fixed').remove()">
                                        Close
                                    </button>
                                    <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700" onclick="showNotification('Correction request submitted!', 'success'); this.closest('.fixed').remove()">
                                        <i class="fas fa-edit mr-2"></i> Request Correction
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                document.body.insertAdjacentHTML('beforeend', modalHtml);
            }

            function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                const bgColor = type === 'success' ? 'bg-green-600' :
                    type === 'warning' ? 'bg-yellow-600' :
                        type === 'danger' ? 'bg-red-600' : 'bg-blue-600';
                const icon = type === 'success' ? 'check-circle' :
                    type === 'warning' ? 'exclamation-triangle' :
                        type === 'danger' ? 'times-circle' : 'info-circle';

                notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300 transform translate-x-0`;
                notification.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-${icon} mr-3"></i>
                        <span>${message}</span>
                    </div>
                `;

                document.body.appendChild(notification);

                // Remove existing notifications
                const existingNotifications = document.querySelectorAll('.fixed.top-4.right-4');
                existingNotifications.forEach((notif, index) => {
                    if (index > 0) notif.remove();
                });

                // Animate in
                requestAnimationFrame(() => {
                    notification.classList.remove('translate-x-0');
                    notification.classList.add('translate-x-full');

                    // Remove after animation
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 3000);
            }

            // Add keyboard shortcuts
            document.addEventListener('keydown', function (e) {
                // Close modal with Escape key
                if (e.key === 'Escape') {
                    const modal = document.querySelector('.fixed.inset-0.bg-black');
                    if (modal) modal.remove();
                }

                // Close sidebar with Escape key on mobile
                if (e.key === 'Escape' && window.innerWidth <= 1023 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                    mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                    mobileMenuBtn.querySelector('i').classList.add('fa-bars');
                }
            });
        });
    </script>
</body>

</html>