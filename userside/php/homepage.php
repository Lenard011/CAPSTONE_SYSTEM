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
    <title>HRMS - Employee Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- Modern Color Palette & Variables --- */
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

        /* --- Base Styles --- */
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
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* --- Layout Container --- */
        .app-container {
            display: flex;
            min-height: 100vh;
            position: relative;
            flex: 1;
        }

        /* --- Sidebar Navigation --- */
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

        /* --- Navigation Menu --- */
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

        /* --- User Profile Section --- */
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

        /* --- Main Content Area --- */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 1.5rem;
            transition: var(--transition);
            width: calc(100% - 260px);
            display: flex;
            flex-direction: column;
        }

        /* --- Top Bar --- */
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

        .page-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-title p {
            color: var(--gray);
            font-size: 0.9rem;
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

        /* --- Dashboard Stats Grid --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            box-shadow: var(--shadow-md);
            flex-shrink: 0;
        }

        .stat-content {
            margin-bottom: 1rem;
            flex-grow: 1;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .stat-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-light);
            font-size: 0.85rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 600;
        }

        .trend-up {
            color: var(--success);
        }

        .trend-down {
            color: var(--danger);
        }

        .stat-action {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.25rem;
            white-space: nowrap;
        }

        .stat-action:hover {
            color: var(--primary-dark);
            gap: 0.5rem;
        }

        /* --- Color Variations for Stat Cards --- */
        .stat-card.total-attendance .stat-icon {
            background: linear-gradient(135deg, var(--success), #34d399);
        }

        .stat-card.on-time-days .stat-icon {
            background: linear-gradient(135deg, var(--info), #0ea5e9);
        }

        .stat-card.late-days .stat-icon {
            background: linear-gradient(135deg, var(--warning), #fbbf24);
        }

        .stat-card.total-working-days .stat-icon {
            background: linear-gradient(135deg, var(--primary), #3b82f6);
        }

        /* --- Recent Activity Section --- */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }

        .section-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .section-link:hover {
            color: var(--primary-dark);
            gap: 0.75rem;
        }

        /* --- Table Styling --- */
        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 2rem;
            flex: 1;
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

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .data-table thead {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
        }

        .data-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .data-table tbody tr {
            border-bottom: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .data-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .data-table td {
            padding: 1rem;
            color: var(--dark);
            font-size: 0.95rem;
        }

        .action-btn {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            padding: 0.5rem 1rem;
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

        /* --- Status Badges --- */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            min-width: 70px;
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

        /* --- Pagination --- */
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

        /* --- Overlay for mobile menu --- */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* --- Responsive Design --- */

        /* Large Desktop (1200px and up) */
        @media (min-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        /* Desktop (1024px to 1199px) */
        @media (min-width: 1024px) and (max-width: 1199px) {
            .stats-grid {
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

            .footer {
                margin-left: 0;
                width: 100%;
            }

            .mobile-menu-btn {
                display: block;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .top-bar {
                flex-direction: row;
                align-items: center;
            }

            .page-title h1 {
                font-size: 1.5rem;
            }
        }

        /* Small Tablet (600px to 767px) */
        @media (max-width: 767px) {
            .stats-grid {
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

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.85rem;
            }

            .stat-value {
                font-size: 2rem;
            }

            .pagination {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .pagination-controls {
                width: 100%;
                justify-content: center;
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .footer-bottom-links {
                justify-content: center;
            }
        }

        /* Mobile (480px to 599px) */
        @media (max-width: 599px) {
            .main-content {
                padding: 0.75rem;
            }

            .stat-value {
                font-size: 1.75rem;
            }

            .table-title {
                font-size: 1.1rem;
            }

            .section-title {
                font-size: 1.25rem;
            }

            .footer-grid {
                grid-template-columns: 1fr;
            }

            .footer {
                padding: 2rem 0 1rem;
            }

            .footer-logo {
                flex-direction: column;
                text-align: center;
                gap: 0.75rem;
            }

            .footer-logo-text {
                align-items: center;
            }
        }

        /* Small Mobile (below 480px) */
        @media (max-width: 479px) {
            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .stat-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .action-btn {
                padding: 0.4rem 0.75rem;
                font-size: 0.85rem;
            }

            .status-badge {
                font-size: 0.75rem;
                padding: 0.2rem 0.5rem;
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

            .social-link {
                width: 36px;
                height: 36px;
            }
        }

        /* --- Animation for Stats --- */
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

        .stat-card {
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }

        .stat-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .stat-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .stat-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .stat-card:nth-child(4) {
            animation-delay: 0.4s;
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
                <a href="#" class="logo-container">
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
                    <a href="homepage.php" class="nav-link active">
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
                <div class="page-title">
                    <h1>Employee Dashboard</h1>
                    <p>Welcome back! Here's your HR overview</p>
                </div>
                <!-- <div class="top-bar-actions">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div> -->
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card total-attendance">
                    <div class="stat-header">
                        <div class="stat-title">Total Attendance</div>
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">24</div>
                        <div class="stat-label">Days This Month</div>
                    </div>
                    <div class="stat-footer">
                        <div>
                            <span class="stat-trend trend-up">
                                <i class="fas fa-arrow-up"></i> 2 days
                            </span>
                            <div>from last month</div>
                        </div>
                        <a href="attendance.php" class="stat-action">
                            View Details <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="stat-card on-time-days">
                    <div class="stat-header">
                        <div class="stat-title">On Time Days</div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">22</div>
                        <div class="stat-label">This Month</div>
                    </div>
                    <div class="stat-footer">
                        <div>
                            <span class="stat-trend trend-up">
                                <i class="fas fa-arrow-up"></i> 3 days
                            </span>
                            <div>from last month</div>
                        </div>
                        <a href="attendance.php" class="stat-action">
                            View History <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="stat-card late-days">
                    <div class="stat-header">
                        <div class="stat-title">Late Days</div>
                        <div class="stat-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">2</div>
                        <div class="stat-label">This Month</div>
                    </div>
                    <div class="stat-footer">
                        <div>
                            <span class="stat-trend trend-down">
                                <i class="fas fa-arrow-down"></i> 1 day
                            </span>
                            <div>from last month</div>
                        </div>
                        <a href="attendance.php" class="stat-action">
                            Check Details <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="stat-card total-working-days">
                    <div class="stat-header">
                        <div class="stat-title">Total Working Days</div>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">26</div>
                        <div class="stat-label">This Month</div>
                    </div>
                    <div class="stat-footer">
                        <div>
                            <span class="stat-trend trend-up">
                                <i class="fas fa-arrow-up"></i> 0 days
                            </span>
                            <div>same as last month</div>
                        </div>
                        <a href="attendance.php" class="stat-action">
                            View Calendar <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Payslips Section -->
            <div class="section-header">
                <h2 class="section-title">Recent Payslips</h2>
                <a href="paysliphistory.php" class="section-link">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <!-- Table Container -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">Payslip History</h3>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Days Worked</th>
                                <th>Gross Amount</th>
                                <th>Total Deductions</th>
                                <th>Payslip Month</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Charlene U. Cajayon</strong>
                                </td>
                                <td>9</td>
                                <td>₱2,250.00</td>
                                <td>-</td>
                                <td>September 2023</td>
                                <td>
                                    <span class="status-badge status-present">Paid</span>
                                </td>
                                <td>
                                    <button class="action-btn">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <strong>Charlene U. Cajayon</strong>
                                </td>
                                <td>24</td>
                                <td>₱6,000.00</td>
                                <td>₱450.00</td>
                                <td>August 2023</td>
                                <td>
                                    <span class="status-badge status-present">Paid</span>
                                </td>
                                <td>
                                    <button class="action-btn">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <strong>Charlene U. Cajayon</strong>
                                </td>
                                <td>25</td>
                                <td>₱6,250.00</td>
                                <td>₱475.00</td>
                                <td>July 2023</td>
                                <td>
                                    <span class="status-badge status-present">Paid</span>
                                </td>
                                <td>
                                    <button class="action-btn">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <strong>1-3</strong> of <strong>10</strong> payslips
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">3</button>
                        <button class="pagination-btn">4</button>
                        <button class="pagination-btn">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance Section -->
            <div class="section-header">
                <h2 class="section-title">Recent Attendance</h2>
                <a href="attendance.php" class="section-link">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <!-- Attendance Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Today's Attendance</div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">Present</div>
                        <div class="stat-label">Time In: 8:05 AM</div>
                    </div>
                    <div class="stat-footer">
                        <div>
                            <span class="status-badge status-present">On Time</span>
                        </div>
                        <div>Date: Nov 20, 2023</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Yesterday</div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <i class="fas fa-calendar-minus"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">Late</div>
                        <div class="stat-label">Time In: 8:32 AM</div>
                    </div>
                    <div class="stat-footer">
                        <div>
                            <span class="status-badge status-late">32 mins late</span>
                        </div>
                        <div>Date: Nov 19, 2023</div>
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
                        <div class="footer-logo-text">
                            <div class="footer-title">HR Management Office</div>
                            <div class="footer-subtitle">Occidental Mindoro</div>
                        </div>
                    </div>
                    <p class="footer-text">
                        Republic of the Philippines<br>
                        All content is in the public domain unless otherwise stated.
                    </p>

                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Provincial Capitol Complex, Mamburao, Occidental Mindoro</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span>(043) 123-4567</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>hrmo@occidentalmindoro.gov.ph</span>
                        </div>
                    </div>

                    <div class="social-links">
                        <a href="#" class="social-link" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-link" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-link" title="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <a href="#" class="social-link" title="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>

                <div class="footer-col">
                    <div class="footer-links">
                        <h4>About GOVPH</h4>
                        <ul>
                            <li><a href="#"><i class="fas fa-chevron-right"></i> Government Structure</a></li>
                            <li><a href="#"><i class="fas fa-chevron-right"></i> Open Data Portal</a></li>
                            <li><a href="#"><i class="fas fa-chevron-right"></i> Official Gazette</a></li>
                            <li><a href="#"><i class="fas fa-chevron-right"></i> Government Services</a></li>
                            <li><a href="#"><i class="fas fa-chevron-right"></i> Transparency Seal</a></li>
                            <li><a href="#"><i class="fas fa-chevron-right"></i> Citizen's Charter</a></li>
                        </ul>
                    </div>
                </div>

                <div class="footer-col">
                    <div class="footer-links">
                        <h4>Quick Links</h4>
                        <ul>
                            <li><a href="homepage.php"><i class="fas fa-chevron-right"></i> Dashboard</a></li>
                            <li><a href="attendance.php"><i class="fas fa-chevron-right"></i> Attendance</a></li>
                            <li><a href="paysliphistory.php"><i class="fas fa-chevron-right"></i> Payslips</a></li>
                            <li><a href="about.php"><i class="fas fa-chevron-right"></i> About HRMO</a></li>
                            <li><a href="settings.php"><i class="fas fa-chevron-right"></i> Account Settings</a></li>
                        </ul>
                    </div>
                </div>

                <div class="footer-col">
                    <div class="footer-links">
                        <h4>Connect With Us</h4>
                        <p class="footer-text">
                            Occidental Mindoro Public Information Office<br>
                            Stay updated with the latest announcements and news.
                        </p>

                        <div class="contact-info">
                            <div class="contact-item">
                                <i class="fas fa-clock"></i>
                                <span>Office Hours: Mon-Fri, 8:00 AM - 5:00 PM</span>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-users"></i>
                                <span>HR Hotline: (043) 987-6543</span>
                            </div>
                        </div>

                        <a href="#" class="stat-action" style="margin-top: 1rem; color: white;">
                            <i class="fas fa-headset"></i> Contact Support
                        </a>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="copyright">
                    © 2023 <strong>Human Resource Management Office</strong>. All Rights Reserved.
                </div>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Accessibility</a>
                    <a href="#">Sitemap</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // DOM Elements
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const mainContent = document.querySelector('.main-content');
            const footer = document.querySelector('.footer');

            // Toggle sidebar on mobile
            mobileMenuBtn.addEventListener('click', function () {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
            });

            // Close sidebar when clicking on overlay
            sidebarOverlay.addEventListener('click', function () {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function (event) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickInsideMenuBtn = mobileMenuBtn.contains(event.target);
                const isClickOnOverlay = sidebarOverlay.contains(event.target);

                if (window.innerWidth <= 1023 && !isClickInsideSidebar && !isClickInsideMenuBtn && !isClickOnOverlay && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                }
            });

            // Close sidebar when window is resized to desktop size
            window.addEventListener('resize', function () {
                if (window.innerWidth > 1023 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                }

                // Adjust footer margin based on sidebar
                if (window.innerWidth > 1023) {
                    footer.style.marginLeft = '260px';
                    footer.style.width = 'calc(100% - 260px)';
                } else {
                    footer.style.marginLeft = '0';
                    footer.style.width = '100%';
                }
            });

            // Initialize tooltips
            const tooltipTriggerList = document.querySelectorAll('[data-tooltip-target]');
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new Flowbite.Tooltip(tooltipTriggerEl);
            });

            // Animate stat cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.stat-card').forEach(card => {
                card.style.animationPlayState = 'paused';
                observer.observe(card);
            });

            // Add click events for pagination buttons
            document.querySelectorAll('.pagination-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    // Remove active class from all buttons
                    document.querySelectorAll('.pagination-btn').forEach(b => {
                        b.classList.remove('active');
                    });

                    // Add active class to clicked button if it's a number button
                    if (!this.querySelector('i')) {
                        this.classList.add('active');
                    }
                });
            });

            // Add click events for action buttons
            document.querySelectorAll('.action-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    alert('View details functionality would open here in a real application.');
                });
            });

            // Initialize footer margin on page load
            if (window.innerWidth > 1023) {
                footer.style.marginLeft = '260px';
                footer.style.width = 'calc(100% - 260px)';
            }
        });

        
    </script>
</body>

</html>