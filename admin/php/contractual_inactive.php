<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Logout functionality
if (isset($_GET['logout'])) {
    // Destroy all session data
    session_destroy();

    // Clear remember me cookie
    setcookie('remember_user', '', time() - 3600, "/");

    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Set user variables from session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Administrator';
$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'admin@hrmo.gov.ph';

/**
 * PHP Script: contractual_inactive.php
 * Handles viewing inactive contractual employees
 */

// ===============================================
// 1. CONFIGURATION AND PDO CONNECTION SETUP
// ===============================================

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hrms_paluan');

// Initialize variables
$success = null;
$error = null;
$employees = [];
$error_message = '';

// Pagination variables
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 10;
$total_records = 0;
$total_pages = 0;

// Initialize PDO connection
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("ERROR: Could not connect to the database. Check server logs for details.");
}

// ===============================================
// 2. HANDLE ACTIVATE REQUEST
// ===============================================
if (isset($_GET['activate_id'])) {
    $activate_id = intval($_GET['activate_id']);
    
    try {
        // Update employee status to active
        $stmt = $pdo->prepare("UPDATE contractofservice SET status = 'active' WHERE id = ?");
        $stmt->execute([$activate_id]);
        
        $success = "Employee activated successfully!";
        
        // Redirect to remove activate_id from URL
        header("Location: contractual_inactive.php");
        exit();
    } catch (PDOException $e) {
        error_log("Activate error: " . $e->getMessage());
        $error = "Error activating employee. Please try again.";
    }
}

// ===============================================
// 3. DATA FETCHING LOGIC WITH PAGINATION
// ===============================================
try {
    // Get total number of inactive employees
    $count_sql = "SELECT COUNT(*) FROM contractofservice WHERE status = 'inactive'";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute();
    $total_records = $stmt->fetchColumn();
    
    // Calculate total pages
    $total_pages = ceil($total_records / $records_per_page);
    
    // Ensure current page is within valid range
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
        header("Location: contractual_inactive.php?page=$current_page");
        exit();
    }
    
    // Calculate offset
    $offset = ($current_page - 1) * $records_per_page;
    
    // Fetch inactive employees with pagination
    $sql = "SELECT 
                id, employee_id, full_name, designation, office_assignment, 
                period_from, period_to, wages, contribution,
                email_address, mobile_number, status,
                DATE_FORMAT(updated_at, '%Y-%m-%d') as inactivated_date
            FROM 
                contractofservice 
            WHERE status = 'inactive'
            ORDER BY updated_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Query execution error in fetch block: " . $e->getMessage());
    $error_message = "Could not retrieve employee data.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inactive Contractual Employees - HR Management System</title>
    <!-- Flowbite CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.2.1/dist/flowbite.min.css" rel="stylesheet" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/output.css">
    <link rel="stylesheet" href="../css/dasboard.css">
    <style>
        /* Custom Styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 9998;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }

        body.modal-open {
            overflow: hidden;
        }

        body.modal-open .main-content,
        body.modal-open .navbar,
        body.modal-open .sidebar-container {
            filter: blur(2px);
            pointer-events: none;
            transition: filter 0.3s ease;
        }

        /* Action buttons in table */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }

        .action-btn.view {
            background-color: #3b82f6;
            color: white;
        }

        .action-btn.view:hover {
            background-color: #2563eb;
        }

        .action-btn.activate {
            background-color: #10b981;
            color: white;
        }

        .action-btn.activate:hover {
            background-color: #059669;
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-badge.active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.expired {
            background: #fef3c7;
            color: #92400e;
        }

        /* Pagination Footer Styles */
        .pagination-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-top: 1px solid #e5e7eb;
            background-color: #f9fafb;
        }

        .page-info {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .page-info .font-semibold {
            color: #374151;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-button {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            background-color: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            color: #6b7280;
            transition: all 0.2s ease;
            cursor: pointer;
            min-width: 40px;
        }

        .page-button:hover:not(:disabled) {
            background-color: #f3f4f6;
            color: #374151;
            border-color: #9ca3af;
        }

        .page-button.active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .page-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-button i {
            font-size: 0.75rem;
        }

        .page-ellipsis {
            padding: 0.5rem 0.25rem;
            color: #9ca3af;
            font-weight: 500;
        }

        :root {
          --primary: #1e40af;
          --secondary: #1e3a8a;
          --accent: #3b82f6;
          --gradient-nav: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
        }

        * {
          box-sizing: border-box;
          margin: 0;
          padding: 0;
        }

        body {
          font-family: 'Inter', sans-serif;
          background: #f8fafc;
          min-height: 100vh;
          overflow-x: hidden;
          color: #1f2937;
        }

        /* IMPROVED NAVBAR */
        .navbar {
          background: var(--gradient-nav);
          box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          z-index: 100;
          height: 70px;
          backdrop-filter: blur(10px);
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
          flex: 1;
        }

        .navbar-right {
          display: flex;
          align-items: center;
          gap: 1.5rem;
        }

        /* Logo and Brand */
        .navbar-brand {
          display: flex;
          align-items: center;
          gap: 1rem;
          text-decoration: none;
          transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
          transform: scale(1.02);
        }

        .brand-logo {
          width: 45px;
          height: 45px;
          object-fit: contain;
          filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .brand-text {
          display: flex;
          flex-direction: column;
        }

        .brand-title {
          font-size: 1.4rem;
          font-weight: 700;
          color: white;
          line-height: 1.2;
          letter-spacing: 0.5px;
          text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .brand-subtitle {
          font-size: 0.8rem;
          color: rgba(255, 255, 255, 0.9);
          font-weight: 500;
          letter-spacing: 0.3px;
        }

        /* Date & Time Display */
        .datetime-container {
          display: flex;
          align-items: center;
          gap: 1.5rem;
        }

        .datetime-box {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          background: rgba(255, 255, 255, 0.15);
          backdrop-filter: blur(10px);
          border-radius: 12px;
          padding: 0.6rem 1rem;
          border: 1px solid rgba(255, 255, 255, 0.1);
          transition: all 0.3s ease;
          min-width: 180px;
        }

        .datetime-box:hover {
          background: rgba(255, 255, 255, 0.2);
          transform: translateY(-2px);
          box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .datetime-icon {
          font-size: 1.1rem;
          color: white;
          opacity: 0.9;
        }

        .datetime-text {
          display: flex;
          flex-direction: column;
        }

        .datetime-label {
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.7);
          font-weight: 500;
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }

        .datetime-value {
          font-size: 0.95rem;
          color: white;
          font-weight: 600;
          line-height: 1.3;
        }

        /* Sidebar */
        .sidebar-container {
            position: fixed;
            left: 0;
            top: 70px;
            width: 260px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%);
            z-index: 999;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
        }

        .sidebar-container::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
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

        .sidebar-item.logout {
            color: #fecaca;
            margin-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
        }

        .sidebar-item.logout:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #fecaca;
        }

        /* Dropdown Menu in Sidebar */
        .dropdown-menu {
            display: none;
            padding-left: 1rem;
            margin-left: 2.5rem;
            border-left: 2px solid rgba(255, 255, 255, 0.1);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        /* Sidebar Styles */
        .sidebar-container {
          position: fixed;
          top: 70px;
          left: 0;
          height: calc(100vh - 70px);
          z-index: 90;
          transform: translateX(-100%);
          transition: transform 0.3s ease-in-out;
        }

        .sidebar-container.active {
          transform: translateX(0);
        }

        @media (min-width: 768px) {
          .sidebar-container {
            transform: translateX(0);
            top: 0;
            height: 100vh;
            padding-top: 70px;
          }

          .main-content {
            margin-left: 16rem;
          }
        }

        .sidebar {
          width: 16rem;
          height: 100%;
          background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
          box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
          overflow-y: auto;
          display: flex;
          flex-direction: column;
        }

        .sidebar-content {
          flex: 1;
          padding: 1.5rem 1rem;
          overflow-y: auto;
        }

        .sidebar-footer {
          padding: 1rem;
          border-top: 1px solid rgba(255, 255, 255, 0.1);
          text-align: center;
        }

        /* Sidebar Overlay for Mobile */
        .sidebar-overlay {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.5);
          backdrop-filter: blur(4px);
          z-index: 89;
          opacity: 0;
          visibility: hidden;
          transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
          opacity: 1;
          visibility: visible;
        }

        /* Main Content */
        .main-content {
          margin-top: 70px;
          padding: 1.5rem;
          transition: all 0.3s ease;
          min-height: calc(100vh - 70px);
          width: 100%;
        }

        @media (min-width: 768px) {
          .main-content {
            margin-left: 16rem;
            width: calc(100% - 16rem);
          }
        }

        /* Sidebar Menu Items */
        .sidebar-item {
          display: flex;
          align-items: center;
          padding: 0.875rem 1rem;
          color: white;
          text-decoration: none;
          border-radius: 12px;
          margin-bottom: 0.5rem;
          transition: all 0.3s ease;
          position: relative;
        }

        .sidebar-item:hover {
          background: rgba(255, 255, 255, 0.15);
          transform: translateX(5px);
        }

        .sidebar-item.active {
          background: rgba(255, 255, 255, 0.2);
          box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar-item i {
          width: 1.5rem;
          text-align: center;
          margin-right: 0.75rem;
          font-size: 1.1rem;
        }

        .sidebar-item span {
          flex: 1;
          font-weight: 500;
        }

        .sidebar-item .badge {
          background: rgba(255, 255, 255, 0.2);
          padding: 0.25rem 0.5rem;
          border-radius: 1rem;
          font-size: 0.75rem;
          font-weight: 600;
        }

        .sidebar-item .chevron {
          transition: transform 0.3s ease;
        }

        .sidebar-item .chevron.rotated {
          transform: rotate(180deg);
        }

        /* Dropdown Menu */
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

        /* Mobile Brand Styling */
        .mobile-brand {
          display: flex;
          align-items: center;
        }

        .mobile-brand-text {
          display: flex;
          flex-direction: column;
          margin-left: 0.5rem;
        }

        .mobile-brand-title {
          font-size: 1.1rem;
          font-weight: 700;
          color: white;
          line-height: 1.2;
        }

        .mobile-brand-subtitle {
          font-size: 0.7rem;
          color: rgba(255, 255, 255, 0.9);
          font-weight: 500;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
          .mobile-hidden {
            display: none;
          }

          .mobile-full {
            width: 100%;
          }

          .mobile-text-center {
            text-align: center;
          }

          .mobile-p-2 {
            padding: 0.5rem;
          }

          .mobile-stack {
            flex-direction: column;
          }

          .mobile-stack>* {
            margin-bottom: 0.5rem;
          }

          /* Navbar Mobile */
          .navbar {
            height: 65px;
          }

          .navbar-container {
            padding: 0 1rem;
          }

          .mobile-toggle {
            display: flex;
          }

          .datetime-container {
            display: none;
          }

          .brand-text {
            display: none;
          }

          .user-info {
            display: none;
          }

          .user-button {
            padding: 0.4rem;
          }

          .user-dropdown {
            width: 250px;
          }

          /* Main Content */
          .main-content {
            padding: 1rem;
          }
          
          /* Pagination Footer Mobile */
          .pagination-footer {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
          }
          
          .page-info {
            text-align: center;
          }
          
          .pagination-controls {
            justify-content: center;
            flex-wrap: wrap;
          }
        }

        @media (max-width: 480px) {
          .mobile-brand-text {
            margin-left: 0.25rem;
          }

          .mobile-brand-title {
            font-size: 1rem;
          }

          .mobile-brand-subtitle {
            font-size: 0.65rem;
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

        /* FIX: Ensure modals are hidden by default */
        .hidden {
            display: none !important;
        }

        .modal-container.hidden {
            display: none !important;
        }
        
        /* Inactive table specific styles */
        .inactive-row {
            background-color: #fef2f2;
        }
        
        .inactive-row:hover {
            background-color: #fee2e2;
        }
        
        .inactive-header {
            background-color: #dc2626 !important;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Success/Error Messages -->
    <?php if (isset($success)): ?>
        <div id="successMessage" class="fixed top-4 right-4 z-50 p-4 text-sm text-green-800 rounded-lg bg-green-50 shadow-lg" role="alert">
            <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
        </div>
        <script>
            setTimeout(() => {
                const element = document.getElementById('successMessage');
                if (element) element.remove();
            }, 5000);
        </script>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div id="errorMessage" class="fixed top-4 right-4 z-50 p-4 text-sm text-red-800 rounded-lg bg-red-50 shadow-lg" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
        </div>
        <script>
            setTimeout(() => {
                const element = document.getElementById('errorMessage');
                if (element) element.remove();
            }, 5000);
        </script>
    <?php endif; ?>

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
                <a href="../dashboard.php" class="navbar-brand">
                    <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
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

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

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
                <a href="./employees/Employee.php" class="sidebar-item active">
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

                <!-- Salary -->
                <a href="../sallarypayheads.php" class="sidebar-item">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Salary Structure</span>
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
        <div class="p-4">
            <div class="p-4 bg-white rounded-lg shadow">
                <!-- Breadcrumb -->
                <nav class="flex mb-4 overflow-x-auto">
                    <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse whitespace-nowrap">
                        <li class="inline-flex items-center">
                            <a href="Employee.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                                <i class="fas fa-home mr-2"></i>All Employee
                            </a>
                        </li>
                        <li>
                            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                            <a href="contractofservice.php" class="ms-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ms-2">Contractual</a>
                        </li>
                        <li>
                            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                            <a href="contractual_inactive.php" class="ms-1 text-sm font-medium text-blue-600 md:ms-2">Inactive Contractual</a>
                        </li>
                    </ol>
                </nav>

                <h1 class="text-2xl font-bold text-gray-900 mb-6">Inactive Contractual Employees</h1>

                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <!-- Header with Search and Back Button -->
                    <div class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                        <div class="w-full md:w-1/3">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search text-gray-500"></i>
                                </div>
                                <input type="text" id="search-employee" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5" placeholder="Search inactive employees...">
                            </div>
                        </div>
                        <div class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0 mobile-stack">
                            <a href="contractofservice.php" class="w-full md:w-auto flex items-center justify-center text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 mobile-full">
                                <i class="fas fa-arrow-left mr-2"></i>
                                <span class="md:inline">Back to Active Employees</span>
                                <span class="md:hidden">Back</span>
                            </a>
                        </div>
                    </div>

                    <!-- Inactive Employees Table -->
                    <div class="overflow-x-auto mobile-table-container">
                        <table class="w-full text-sm text-left text-gray-500 mobile-table">
                            <thead class="text-xs text-white uppercase bg-red-600 inactive-header">
                                <tr>
                                    <th class="px-4 py-3">No.</th>
                                    <th class="px-6 py-3">Employee ID</th>
                                    <th class="px-6 py-3">Name</th>
                                    <th class="px-6 py-3">Designation</th>
                                    <th class="px-6 py-3">Office Assignment</th>
                                    <th class="px-6 py-3">Period From</th>
                                    <th class="px-6 py-3">Period To</th>
                                    <th class="px-6 py-3">Contract Status</th>
                                    <th class="px-6 py-3">Inactivated On</th>
                                    <th class="px-6 py-3">Wages</th>
                                    <th class="px-6 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($error_message) && !empty($error_message)): ?>
                                    <tr>
                                        <td colspan="11" class="px-6 py-4 text-center text-red-600">
                                            <?php echo htmlspecialchars($error_message); ?>
                                        </td>
                                    </tr>
                                <?php elseif (empty($employees)): ?>
                                    <tr>
                                        <td colspan="11" class="px-6 py-4 text-center text-gray-500">
                                            No inactive contractual employees found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $start_number = ($current_page - 1) * $records_per_page + 1;
                                    foreach ($employees as $index => $employee): 
                                        $today = date('Y-m-d');
                                        $contract_status = ($employee['period_to'] >= $today) ? 'active' : 'expired';
                                    ?>
                                        <tr class="inactive-row border-b hover:bg-red-50">
                                            <td class="px-4 py-4 font-medium text-gray-900"><?php echo $start_number + $index; ?></td>
                                            <td class="px-6 py-4 font-medium text-gray-900">
                                                <span class="font-mono bg-gray-100 px-2 py-1 rounded text-sm">
                                                    <?php echo htmlspecialchars($employee['employee_id'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                                <?php echo htmlspecialchars($employee['full_name']); ?>
                                            </td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($employee['designation']); ?></td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($employee['office_assignment']); ?></td>
                                            <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($employee['period_from'])); ?></td>
                                            <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($employee['period_to'])); ?></td>
                                            <td class="px-6 py-4">
                                                <span class="status-badge <?php echo $contract_status; ?>">
                                                    <?php echo ucfirst($contract_status); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php echo $employee['inactivated_date'] ? date('M d, Y', strtotime($employee['inactivated_date'])) : 'N/A'; ?>
                                            </td>
                                            <td class="px-6 py-4 font-bold text-green-600">
                                                ₱<?php echo number_format($employee['wages'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="action-buttons">
                                                    <button onclick="viewEmployee(<?php echo $employee['id']; ?>)" class="action-btn view" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button onclick="confirmActivate(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars(addslashes($employee['full_name'])); ?>')" class="action-btn activate" title="Activate">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Footer -->
                    <div class="pagination-footer">
                        <!-- Showing X-Y of Z -->
                        <div class="page-info">
                            Showing 
                            <span class="font-semibold"><?php echo min($total_records, ($current_page - 1) * $records_per_page + 1); ?>-<?php echo min($total_records, $current_page * $records_per_page); ?></span> 
                            of <span class="font-semibold"><?php echo $total_pages > 0 ? $total_records : 0; ?></span> inactive employees
                        </div>

                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 0): ?>
                        <div class="pagination-controls">
                            <!-- Previous Button -->
                            <button 
                                onclick="goToPage(<?php echo max(1, $current_page - 1); ?>)" 
                                class="page-button <?php echo $current_page == 1 ? 'disabled' : ''; ?>"
                                <?php echo $current_page == 1 ? 'disabled' : ''; ?>
                            >
                                <i class="fas fa-chevron-left mr-1"></i>
                                Previous
                            </button>

                            <!-- Page Numbers -->
                            <div class="flex items-center space-x-1">
                                <?php
                                // Calculate page range to show
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                // Show first page if not in range
                                if ($start_page > 1) {
                                    echo '<button onclick="goToPage(1)" class="page-button">1</button>';
                                    if ($start_page > 2) {
                                        echo '<span class="page-ellipsis">...</span>';
                                    }
                                }
                                
                                // Show page numbers
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    if ($i == $current_page) {
                                        echo '<button class="page-button active">' . $i . '</button>';
                                    } else {
                                        echo '<button onclick="goToPage(' . $i . ')" class="page-button">' . $i . '</button>';
                                    }
                                }
                                
                                // Show last page if not in range
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="page-ellipsis">...</span>';
                                    }
                                    echo '<button onclick="goToPage(' . $total_pages . ')" class="page-button">' . $total_pages . '</button>';
                                }
                                ?>
                            </div>

                            <!-- Next Button -->
                            <button 
                                onclick="goToPage(<?php echo min($total_pages, $current_page + 1); ?>)" 
                                class="page-button <?php echo $current_page == $total_pages ? 'disabled' : ''; ?>"
                                <?php echo $current_page == $total_pages ? 'disabled' : ''; ?>
                            >
                                Next
                                <i class="fas fa-chevron-right ml-1"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Backdrop -->
    <div id="modalBackdrop" class="modal-backdrop hidden"></div>

    <!-- Activate Confirmation Modal -->
    <div id="activateModal" class="modal-container hidden">
        <div class="inactivate-modal-content">
            <div class="inactivate-icon" style="color: #10b981;">
                <i class="fas fa-user-check"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-4" id="activateEmployeeName"></h3>
            <p class="text-gray-600 mb-6">Are you sure you want to activate this employee?</p>
            
            <div class="inactivate-warning-list">
                <ul class="list-disc">
                    <li>Employee will be moved to active status</li>
                    <li>Will appear in the main employee list</li>
                    <li>Can be included in payroll processing</li>
                </ul>
            </div>
            
            <div class="flex justify-center space-x-4 mt-8">
                <button type="button" class="px-6 py-2.5 text-sm font-medium text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-100" onclick="closeActivateModal()">
                    Cancel
                </button>
                <button type="button" id="confirmActivateBtn" class="px-6 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-300">
                    Activate Employee
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom JavaScript -->
    <script>
        // ===============================================
        // NAVBAR DATE & TIME FUNCTIONALITY
        // ===============================================
        function updateDateTime() {
            const now = new Date();
            
            // Format date: Weekday, Month Day, Year
            const optionsDate = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
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
        setInterval(updateDateTime, 1000);

        // ===============================================
        // MODAL FUNCTIONS
        // ===============================================
        let activateEmployeeId = null;
        let activateEmployeeName = null;

        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            const backdrop = document.getElementById('modalBackdrop');
            
            if (modal && backdrop) {
                modal.classList.remove('hidden');
                backdrop.classList.remove('hidden');
                document.body.classList.add('modal-open');
                document.body.style.overflow = 'hidden';
            }
        }

        function hideModal(modalId) {
            const modal = document.getElementById(modalId);
            const backdrop = document.getElementById('modalBackdrop');
            
            if (modal && backdrop) {
                modal.classList.add('hidden');
                backdrop.classList.add('hidden');
                document.body.classList.remove('modal-open');
                document.body.style.overflow = 'auto';
            }
        }

        function confirmActivate(employeeId, employeeName) {
            activateEmployeeId = employeeId;
            activateEmployeeName = employeeName;
            
            document.getElementById('activateEmployeeName').textContent = `"${employeeName}"`;
            showModal('activateModal');
        }

        function closeActivateModal() {
            hideModal('activateModal');
            activateEmployeeId = null;
            activateEmployeeName = null;
        }

        // Handle activate confirmation
        document.getElementById('confirmActivateBtn').addEventListener('click', function() {
            if (activateEmployeeId) {
                window.location.href = `contractual_inactive.php?activate_id=${activateEmployeeId}`;
            }
        });

        // Close modal when clicking on backdrop
        document.getElementById('modalBackdrop').addEventListener('click', function() {
            closeActivateModal();
        });

        // ===============================================
        // PAGINATION FUNCTIONS
        // ===============================================
        function goToPage(page) {
            if (page >= 1 && page <= <?php echo $total_pages; ?>) {
                window.location.href = `contractual_inactive.php?page=${page}`;
            }
        }

        // ===============================================
        // VIEW EMPLOYEE FUNCTION
        // ===============================================
        function viewEmployee(employeeId) {
            // For now, redirect to the main page with view parameter
            // You can implement a modal view similar to the main page
            window.open(`contractofservice.php?view_id=${employeeId}`, '_blank');
        }

        // ===============================================
        // EVENT LISTENERS
        // ===============================================
        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const searchInput = document.getElementById('search-employee');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('tbody tr');

                    rows.forEach(row => {
                        const nameCell = row.querySelector('td:nth-child(3)');
                        const idCell = row.querySelector('td:nth-child(2)');
                        if ((nameCell && nameCell.textContent.toLowerCase().includes(searchTerm)) ||
                            (idCell && idCell.textContent.toLowerCase().includes(searchTerm))) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            // Sidebar functionality
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarContainer = document.getElementById('sidebar-container');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            
            if (sidebarToggle && sidebarContainer && sidebarOverlay) {
                sidebarToggle.addEventListener('click', function() {
                    sidebarContainer.classList.toggle('active');
                    sidebarOverlay.classList.toggle('active');
                });
                
                sidebarOverlay.addEventListener('click', function() {
                    sidebarContainer.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                });
            }

            // Payroll dropdown functionality
            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollDropdown = document.getElementById('payroll-dropdown');
            
            if (payrollToggle && payrollDropdown) {
                payrollToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    payrollDropdown.classList.toggle('open');
                    const chevron = payrollToggle.querySelector('.chevron');
                    chevron.classList.toggle('rotated');
                });
            }
        });
    </script>
</body>
</html>