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

// Set default user info if not in session (for demo purposes)
$user_name = $_SESSION['user_name'] ?? 'Admin User';
$user_email = $_SESSION['user_email'] ?? 'admin@example.com';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distributed Salary | HRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        /* NAVBAR MATCHING IMAGE */
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

        /* Mobile Brand Styling */
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

        /* Date & Time Display - MATCHING IMAGE */
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

        /* Mobile Menu Toggle */
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
            border: none;
            outline: none;
        }

        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        .mobile-toggle i {
            font-size: 1.25rem;
            color: white;
        }

        /* SIDEBAR MATCHING IMAGE */
        .sidebar-container {
            position: fixed;
            top: 70px;
            left: 0;
            height: calc(100vh - 70px);
            z-index: 90;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            width: 260px;
        }

        .sidebar-container.active {
            transform: translateX(0);
        }

        .sidebar {
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            padding: 20px 0;
        }

        .sidebar-content {
            flex: 1;
            padding: 0 15px;
            overflow-y: auto;
        }

        .sidebar-footer {
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
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

        /* Sidebar Menu Items - MATCHING IMAGE WITH IMPROVED HOVER */
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
            position: relative;
            border-left: 3px solid transparent;
            cursor: pointer;
        }

        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .sidebar-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 3px solid #ffffff;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .sidebar-item.active:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(5px);
        }

        .sidebar-item i {
            width: 24px;
            text-align: center;
            margin-right: 12px;
            font-size: 18px;
        }

        .sidebar-item span {
            flex: 1;
            font-weight: 500;
            font-size: 14px;
        }

        .sidebar-item .chevron {
            transition: transform 0.3s ease;
            font-size: 12px;
        }

        .sidebar-item .chevron.rotated {
            transform: rotate(180deg);
        }

        /* Sidebar Dropdown Menu */
        .sidebar-dropdown-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            margin-left: 20px;
            border-left: 2px solid rgba(255, 255, 255, 0.1);
            padding-left: 10px;
        }

        .sidebar-dropdown-menu.open {
            max-height: 500px;
        }

        .sidebar-dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 4px;
            transition: all 0.3s ease;
            font-size: 13px;
        }

        .sidebar-dropdown-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .sidebar-dropdown-item i {
            font-size: 10px;
            margin-right: 8px;
        }

        /* Logout button special styling */
        .sidebar-item.logout {
            background: rgba(220, 38, 38, 0.1);
            color: rgba(255, 255, 255, 0.9);
        }

        .sidebar-item.logout:hover {
            background: rgba(220, 38, 38, 0.2);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-item.logout i {
            color: rgba(255, 255, 255, 0.9);
        }

        /* IMPROVED TABLE DESIGN */
        .salary-table-container {
            overflow-x: auto;
            margin-top: 1.5rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        .salary-table {
            width: 100%;
            min-width: 800px;
            border-collapse: collapse;
        }

        .salary-table thead {
            background: linear-gradient(90deg, #1e40af 0%, #1e3a8a 100%);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .salary-table th {
            padding: 1.25rem 1.5rem;
            color: white;
            font-weight: 600;
            text-align: left;
            white-space: nowrap;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #1e3a8a;
        }

        .salary-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .salary-table tbody tr {
            transition: all 0.2s ease;
        }

        .salary-table tbody tr:hover {
            background-color: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .salary-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Improved Cell Styling */
        .employee-name-cell {
            font-weight: 600;
            color: #1f2937;
        }

        .department-cell {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .amount-cell {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Code', 'Droid Sans Mono', 'Courier New', monospace;
            font-weight: 600;
            text-align: right;
            min-width: 100px;
        }

        .positive-amount {
            color: #10b981;
        }

        .negative-amount {
            color: #ef4444;
        }

        .zero-amount {
            color: #9ca3af;
        }

        /* Action Buttons */
        .action-btn {
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            cursor: pointer;
            border: none;
            outline: none;
            font-weight: 600;
        }

        .view-btn {
            background-color: #3b82f6;
            color: white;
        }

        .view-btn:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3);
        }

        .export-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .export-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
        }

        /* Table Actions Column */
        .actions-cell {
            min-width: 120px;
        }

        /* IMPROVED SEARCH BAR */
        .search-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .search-input {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-input input {
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 0.875rem 1rem 0.875rem 3rem;
            font-size: 0.95rem;
            width: 100%;
            transition: all 0.3s ease;
            color: #1f2937;
        }

        .search-input input:focus {
            background: white;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .search-input .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.1rem;
        }

        .search-input input:focus+.search-icon {
            color: #3b82f6;
        }

        /* Search Action Buttons */
        .search-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .page-header p {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .page-header-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            font-size: 0.9rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Mobile Responsive Styles */
        @media (min-width: 768px) {
            .sidebar-container {
                transform: translateX(0);
                top: 0;
                height: 100vh;
                padding-top: 70px;
            }

            .main-content {
                margin-left: 260px;
                width: calc(100% - 260px);
            }
        }

        @media (max-width: 768px) {

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

            /* Main Content */
            .main-content {
                padding: 1rem;
                margin-top: 65px;
            }

            .search-container {
                flex-direction: column;
                align-items: stretch;
                padding: 1.25rem;
                gap: 1rem;
            }

            .search-input {
                width: 100%;
                min-width: unset;
            }

            .search-actions {
                width: 100%;
                justify-content: space-between;
            }

            .action-btn {
                flex: 1;
                justify-content: center;
            }

            .page-header {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .salary-table th,
            .salary-table td {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem;
            }

            .salary-table th,
            .salary-table td {
                padding: 0.875rem 0.75rem;
                font-size: 0.85rem;
            }

            .action-btn {
                padding: 0.5rem 1rem;
                font-size: 0.8125rem;
            }
        }

        /* IMPROVED MODAL STYLES */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            max-width: 950px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.25);
            animation: modalSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Modal Header */
        .modal-header {
            padding: 2rem 2rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            position: relative;
        }

        .modal-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }

        .modal-header p {
            color: #6b7280;
            margin-top: 0.5rem;
            font-size: 0.95rem;
        }

        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: #f3f4f6;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #6b7280;
            font-size: 1.25rem;
        }

        .modal-close:hover {
            background: #ef4444;
            color: white;
            transform: rotate(90deg);
        }

        /* Modal Body */
        .modal-body {
            padding: 0 2rem 2rem;
        }

        /* Employee Profile Section */
        .employee-profile {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            margin: 1.5rem 0;
        }

        .employee-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        .employee-details {
            flex: 1;
        }

        .employee-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0 0 0.25rem 0;
        }

        .employee-meta {
            display: flex;
            gap: 1.5rem;
            color: #6b7280;
            font-size: 0.95rem;
        }

        .employee-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Salary Cards */
        .salary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .salary-card {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .salary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .salary-card.earnings {
            border-top: 4px solid #10b981;
        }

        .salary-card.deductions {
            border-top: 4px solid #ef4444;
        }

        .salary-card.net-salary {
            border-top: 4px solid #3b82f6;
        }

        .salary-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .salary-card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .salary-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .earnings .salary-card-icon {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .deductions .salary-card-icon {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .net-salary .salary-card-icon {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .salary-card-amount {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0.5rem 0;
            font-family: 'SF Mono', monospace;
        }

        .earnings .salary-card-amount {
            color: #10b981;
        }

        .deductions .salary-card-amount {
            color: #ef4444;
        }

        .net-salary .salary-card-amount {
            color: #3b82f6;
        }

        .salary-card-desc {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0;
        }

        /* Breakdown Details */
        .breakdown-section {
            background: #f8fafc;
            border-radius: 16px;
            padding: 2rem;
            margin: 2rem 0;
        }

        .breakdown-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .breakdown-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .breakdown-label {
            font-size: 0.95rem;
            color: #6b7280;
        }

        .breakdown-value {
            font-size: 1.1rem;
            font-weight: 600;
            font-family: 'SF Mono', monospace;
        }

        /* Modal Footer */
        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
        }

        .modal-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-btn-print {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }

        .modal-btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
        }

        .modal-btn-export {
            background: white;
            color: #3b82f6;
            border: 2px solid #3b82f6;
        }

        .modal-btn-export:hover {
            background: #3b82f6;
            color: white;
            transform: translateY(-2px);
        }

        .modal-btn-close {
            background: #6b7280;
            color: white;
        }

        .modal-btn-close:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }

        /* Table Summary */
        .table-summary {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1.5rem;
            border: 1px solid #cbd5e1;
        }

        .table-summary-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-stat {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .summary-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
        }

        .summary-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }

        .summary-value.deductions {
            color: #ef4444;
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

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- Sidebar - UPDATED TO MATCH IMAGE -->
    <div class="sidebar-container" id="sidebar-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <!-- Menu Items matching the image -->
                <a href="dashboard.php" class="sidebar-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard Analytics</span>
                </a>

                <a href="./employees/Employee.php" class="sidebar-item">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>

                <a href="attendance.php" class="sidebar-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>

                <a href="#" class="sidebar-item" id="payroll-toggle">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                    <i class="fas fa-chevron-down chevron ml-auto"></i>
                </a>
                <div class="sidebar-dropdown-menu" id="payroll-dropdown">
                    <a href="Payrollmanagement/contractualpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Contractual
                    </a>
                    <a href="Payrollmanagement/joboerderpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Job Order
                    </a>
                    <a href="Payrollmanagement/permanentpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Permanent
                    </a>
                </div>


                <a href="paysliphistory.php" class="sidebar-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>

                <a href="sallarypayheads.php" class="sidebar-item active">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Salary Structure</span>
                </a>

                <a href="settings.php" class="sidebar-item">
                    <i class="fas fa-sliders-h"></i>
                    <span>Settings</span>
                </a>

                <a href="?logout=true" class="sidebar-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="text-center">
                    <p>HRMS v2.0</p>
                    <p class="mt-1 text-xs">© 2024 Paluan LGU</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container mx-auto">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Distributed Salary</h1>
                <p>View and manage salary distributions for all employees</p>
                <div class="page-header-info">
                    <i class="fas fa-info-circle"></i>
                    <span>Showing salary deductions for all departments</span>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-container">
                <div class="search-input">
                    <input type="text" id="simple-search" placeholder="Search employees by name, ID, or department..." />
                    <div class="search-icon">
                        <i class="fas fa-search"></i>
                    </div>
                </div>

                <div class="search-actions">
                    <button type="button" class="action-btn view-btn" id="view-details-btn">
                        <i class="fas fa-eye"></i>
                        <span class="hidden md:inline">View Details</span>
                        <span class="md:hidden">View</span>
                    </button>
                    <button type="button" class="action-btn export-btn">
                        <i class="fas fa-download"></i>
                        <span class="hidden md:inline">Export CSV</span>
                        <span class="md:hidden">Export</span>
                    </button>
                </div>
            </div>

            <!-- Salary Table -->
            <div class="salary-table-container">
                <table class="salary-table">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center">#</th>
                            <th scope="col">Employee ID</th>
                            <th scope="col">Employee Name</th>
                            <th scope="col">Department</th>
                            <th scope="col" class="text-right">Cash Advance</th>
                            <th scope="col" class="text-right">SSS</th>
                            <th scope="col" class="text-right">PHIC</th>
                            <th scope="col" class="text-right">PAGIBIG</th>
                            <th scope="col" class="text-center actions-cell">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="salary-table-body">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Table Summary -->
            <div class="table-summary">
                <div class="table-summary-content">
                    <div class="summary-stat">
                        <span class="summary-label">Total Records</span>
                        <span class="summary-value" id="total-records">10</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-label">Total Deductions</span>
                        <span class="summary-value deductions" id="total-deductions">₱1,000.00</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-label">Employees with Deductions</span>
                        <span class="summary-value" id="employees-with-deductions">1</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- IMPROVED VIEW MODAL -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h2>Employee Salary Details</h2>
                <p id="modal-employee-info">Complete salary breakdown and deductions</p>
                <button class="modal-close" onclick="closeModal('viewModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <!-- Employee Profile -->
                <div class="employee-profile">
                    <div class="employee-avatar" id="employee-avatar">
                        <!-- Initials will be set by JavaScript -->
                    </div>
                    <div class="employee-details">
                        <h3 class="employee-name" id="modal-employee-name">-</h3>
                        <div class="employee-meta">
                            <span>
                                <i class="fas fa-id-card"></i>
                                <span id="modal-employee-id">-</span>
                            </span>
                            <span>
                                <i class="fas fa-building"></i>
                                <span id="modal-employee-dept">-</span>
                            </span>
                            <span>
                                <i class="fas fa-calendar-alt"></i>
                                <span>Joined: Jan 2023</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Salary Cards Grid -->
                <div class="salary-grid">
                    <!-- Earnings Card -->
                    <div class="salary-card earnings">
                        <div class="salary-card-header">
                            <div class="salary-card-title">Total Earnings</div>
                            <div class="salary-card-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                        <div class="salary-card-amount" id="modal-gross-salary">₱0.00</div>
                        <p class="salary-card-desc">Basic Salary + Allowances</p>
                    </div>

                    <!-- Deductions Card -->
                    <div class="salary-card deductions">
                        <div class="salary-card-header">
                            <div class="salary-card-title">Total Deductions</div>
                            <div class="salary-card-icon">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                        </div>
                        <div class="salary-card-amount" id="modal-total-deductions">₱0.00</div>
                        <p class="salary-card-desc">SSS, PHIC, PAGIBIG & Advances</p>
                    </div>

                    <!-- Net Salary Card -->
                    <div class="salary-card net-salary">
                        <div class="salary-card-header">
                            <div class="salary-card-title">Net Salary</div>
                            <div class="salary-card-icon">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                        </div>
                        <div class="salary-card-amount" id="modal-net-salary">₱0.00</div>
                        <p class="salary-card-desc">Take-home amount</p>
                    </div>
                </div>

                <!-- Detailed Breakdown -->
                <div class="breakdown-section">
                    <h3 class="breakdown-title">
                        <i class="fas fa-list-alt"></i>
                        Salary Breakdown
                    </h3>

                    <div class="breakdown-grid">
                        <!-- Earnings Breakdown -->
                        <div class="breakdown-item">
                            <span class="breakdown-label">Basic Salary</span>
                            <span class="breakdown-value text-green-600" id="modal-basic-salary">₱0.00</span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-label">Allowances</span>
                            <span class="breakdown-value text-green-600" id="modal-allowances">₱0.00</span>
                        </div>

                        <!-- Deductions Breakdown -->
                        <div class="breakdown-item">
                            <span class="breakdown-label">Cash Advance</span>
                            <span class="breakdown-value text-red-600" id="modal-cash-advance">₱0.00</span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-label">SSS Contribution</span>
                            <span class="breakdown-value text-red-600" id="modal-sss">₱0.00</span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-label">PHIC Contribution</span>
                            <span class="breakdown-value text-red-600" id="modal-phic">₱0.00</span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-label">PAGIBIG Contribution</span>
                            <span class="breakdown-value text-red-600" id="modal-pagibig">₱0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <div class="modal-actions">
                    <button class="modal-btn modal-btn-print" onclick="printPayslip()">
                        <i class="fas fa-print"></i>
                        Print Payslip
                    </button>
                    <button class="modal-btn modal-btn-export" onclick="exportSalaryDetails()">
                        <i class="fas fa-file-export"></i>
                        Export Details
                    </button>
                </div>
                <button class="modal-btn modal-btn-close" onclick="closeModal('viewModal')">
                    <i class="fas fa-times"></i>
                    Close
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        // Employee Data
        const employeeData = [{
                id: '01',
                name: 'Russell Tadalan',
                department: 'SB Office',
                cashAdvance: 0.00,
                sss: 0.00,
                phic: 0.00,
                pagibig: 0.00,
                basicSalary: 25000,
                allowances: 5000
            },
            {
                id: 'BSC03',
                name: 'Allaine Adorio',
                department: 'Mayor\'s Office',
                cashAdvance: 0.00,
                sss: 0.00,
                phic: 0.00,
                pagibig: 0.00,
                basicSalary: 22000,
                allowances: 3500
            },
            {
                id: '123-123-123',
                name: 'Jerwin Sequijor',
                department: 'HRMO Office',
                cashAdvance: 0.00,
                sss: 0.00,
                phic: 0.00,
                pagibig: 0.00,
                basicSalary: 28000,
                allowances: 4000
            },
            {
                id: 'BSC02',
                name: 'Joy Ambrosio',
                department: 'Budget Office',
                cashAdvance: 1000.00,
                sss: 0.00,
                phic: 0.00,
                pagibig: 0.00,
                basicSalary: 24000,
                allowances: 3000
            },
            {
                id: 'BSC01',
                name: 'Roxane Calampiano',
                department: 'Tourism Office',
                cashAdvance: 0.00,
                sss: 0.00,
                phic: 0.00,
                pagibig: 0.00,
                basicSalary: 23500,
                allowances: 3200
            },
            {
                id: 'BSC010',
                name: 'Eunice Bunyi',
                department: 'HRMO Office',
                cashAdvance: 0.00,
                sss: 0.00,
                phic: 0.00,
                pagibig: 0.00,
                basicSalary: 26000,
                allowances: 4500
            },
            {
                id: 'BSC06',
                name: 'Cristina Chavez',
                department: 'HRMO Office',
                cashAdvance: 0.00,
                sss: 0.00,
                phic: 0.00,
                pagibig: 0.00,
                basicSalary: 27000,
                allowances: 4200
            },
            {
                id: 'BSC08',
                name: 'James Casalla',
                department: 'HRMO Office',
                cashAdvance: 0.00,
                sss: 0.00,
                phic: 0.00,
                pagibig: 0.00,
                basicSalary: 25500,
                allowances: 3800
            },
            {
                id: 'BSC09',
                name: 'Jovelyn Jenkins',
                department: 'HRMO Office',
                cashAdvance: 0.00,
                sss: 0.00,
                phic: 0.00,
                pagibig: 0.00,
                basicSalary: 24500,
                allowances: 3600
            },
            {
                id: 'BSC07',
                name: 'Lyka Marie Zapanta',
                department: 'HRMO Office',
                cashAdvance: 0.00,
                sss: 0.00,
                phic: 0.00,
                pagibig: 0.00,
                basicSalary: 26500,
                allowances: 4300
            }
        ];

        // DOM Elements
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarContainer = document.getElementById('sidebar-container');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const payrollToggle = document.getElementById('payroll-toggle');
        const payrollDropdown = document.getElementById('payroll-dropdown');
        const currentDate = document.getElementById('current-date');
        const currentTime = document.getElementById('current-time');
        const searchInput = document.getElementById('simple-search');
        const salaryTableBody = document.getElementById('salary-table-body');
        const viewDetailsBtn = document.getElementById('view-details-btn');
        const viewModal = document.getElementById('viewModal');
        const totalRecords = document.getElementById('total-records');
        const totalDeductions = document.getElementById('total-deductions');
        const employeesWithDeductions = document.getElementById('employees-with-deductions');

        // Initialize the table
        function initializeTable() {
            salaryTableBody.innerHTML = '';
            let totalDeductionsSum = 0;
            let employeesWithDeductionsCount = 0;

            employeeData.forEach((employee, index) => {
                const row = document.createElement('tr');
                const employeeDeductions = employee.cashAdvance + employee.sss + employee.phic + employee.pagibig;
                totalDeductionsSum += employeeDeductions;

                if (employeeDeductions > 0) {
                    employeesWithDeductionsCount++;
                }

                row.innerHTML = `
                    <td class="text-center text-gray-500 font-medium">${index + 1}</td>
                    <td class="font-bold text-gray-900">${employee.id}</td>
                    <td class="employee-name-cell">${employee.name}</td>
                    <td class="department-cell">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            ${employee.department}
                        </span>
                    </td>
                    <td class="amount-cell ${employee.cashAdvance > 0 ? 'negative-amount' : 'zero-amount'}">
                        ${employee.cashAdvance > 0 ? '₱' : ''}${employee.cashAdvance.toFixed(2)}
                    </td>
                    <td class="amount-cell ${employee.sss > 0 ? 'negative-amount' : 'zero-amount'}">
                        ${employee.sss > 0 ? '₱' : ''}${employee.sss.toFixed(2)}
                    </td>
                    <td class="amount-cell ${employee.phic > 0 ? 'negative-amount' : 'zero-amount'}">
                        ${employee.phic > 0 ? '₱' : ''}${employee.phic.toFixed(2)}
                    </td>
                    <td class="amount-cell ${employee.pagibig > 0 ? 'negative-amount' : 'zero-amount'}">
                        ${employee.pagibig > 0 ? '₱' : ''}${employee.pagibig.toFixed(2)}
                    </td>
                    <td class="text-center actions-cell">
                        <button class="action-btn view-btn" onclick="openEmployeeModal('${employee.id}')">
                            <i class="fas fa-eye"></i>
                            <span class="hidden md:inline">Details</span>
                        </button>
                    </td>
                `;

                row.dataset.employeeId = employee.id;
                salaryTableBody.appendChild(row);
            });

            totalRecords.textContent = employeeData.length;
            totalDeductions.textContent = `₱${totalDeductionsSum.toFixed(2)}`;
            employeesWithDeductions.textContent = employeesWithDeductionsCount;
        }

        // Sidebar Toggle
        sidebarToggle.addEventListener('click', () => {
            sidebarContainer.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        });

        // Sidebar Overlay Close
        sidebarOverlay.addEventListener('click', () => {
            sidebarContainer.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });

        // Payroll Dropdown Toggle
        if (payrollToggle && payrollDropdown) {
            payrollToggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                payrollDropdown.classList.toggle('open');
                const chevron = payrollToggle.querySelector('.chevron');
                chevron.classList.toggle('rotated');
            });
        }

        // Update Date and Time
        function updateDateTime() {
            const now = new Date();

            // Format date
            const dateOptions = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            const formattedDate = now.toLocaleDateString('en-US', dateOptions);
            currentDate.textContent = formattedDate;

            // Format time
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const formattedTime = now.toLocaleTimeString('en-US', timeOptions);
            currentTime.textContent = formattedTime;
        }

        // Search Functionality
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const tableRows = document.querySelectorAll('#salary-table-body tr');
            let visibleCount = 0;
            let visibleDeductions = 0;
            let totalVisibleDeductions = 0;

            tableRows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                const isVisible = searchTerm === '' || rowText.includes(searchTerm);
                row.style.display = isVisible ? '' : 'none';

                if (isVisible) {
                    visibleCount++;
                    // Calculate deductions for this row
                    const amountCells = row.querySelectorAll('.amount-cell');
                    let rowDeductions = 0;
                    amountCells.forEach(cell => {
                        const text = cell.textContent.trim();
                        if (text.includes('₱')) {
                            const amount = parseFloat(text.replace('₱', '').replace(/,/g, ''));
                            if (!isNaN(amount)) {
                                rowDeductions += amount;
                            }
                        }
                    });

                    if (rowDeductions > 0) {
                        visibleDeductions++;
                    }
                    totalVisibleDeductions += rowDeductions;
                }
            });

            totalRecords.textContent = visibleCount;
            employeesWithDeductions.textContent = visibleDeductions;
            totalDeductions.textContent = `₱${totalVisibleDeductions.toFixed(2)}`;
        });

        // Open Employee Modal
        function openEmployeeModal(employeeId) {
            const employee = employeeData.find(emp => emp.id === employeeId);
            if (!employee) return;

            // Calculate values
            const grossSalary = employee.basicSalary + employee.allowances;
            const totalDeductions = employee.cashAdvance + employee.sss + employee.phic + employee.pagibig;
            const netSalary = grossSalary - totalDeductions;

            // Update modal content
            document.getElementById('modal-employee-id').textContent = employee.id;
            document.getElementById('modal-employee-name').textContent = employee.name;
            document.getElementById('modal-employee-dept').textContent = employee.department;
            document.getElementById('modal-employee-info').textContent = `${employee.name} - ${employee.department} Department`;

            // Set avatar with initials
            const avatar = document.getElementById('employee-avatar');
            const initials = employee.name.split(' ').map(n => n[0]).join('').toUpperCase();
            avatar.textContent = initials;

            // Format currency
            const formatCurrency = (amount) => {
                return `₱${amount.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })}`;
            };

            // Update all modal values
            document.getElementById('modal-basic-salary').textContent = formatCurrency(employee.basicSalary);
            document.getElementById('modal-allowances').textContent = formatCurrency(employee.allowances);
            document.getElementById('modal-gross-salary').textContent = formatCurrency(grossSalary);

            document.getElementById('modal-cash-advance').textContent = formatCurrency(employee.cashAdvance);
            document.getElementById('modal-sss').textContent = formatCurrency(employee.sss);
            document.getElementById('modal-phic').textContent = formatCurrency(employee.phic);
            document.getElementById('modal-pagibig').textContent = formatCurrency(employee.pagibig);
            document.getElementById('modal-total-deductions').textContent = formatCurrency(totalDeductions);

            document.getElementById('modal-net-salary').textContent = formatCurrency(netSalary);

            // Show modal with animation
            viewModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        // Close modal when clicking outside
        viewModal.addEventListener('click', (event) => {
            if (event.target === viewModal) {
                closeModal('viewModal');
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && viewModal.classList.contains('active')) {
                closeModal('viewModal');
            }
        });

        // View Details Button Click
        viewDetailsBtn.addEventListener('click', () => {
            const firstEmployee = employeeData[0];
            if (firstEmployee) {
                openEmployeeModal(firstEmployee.id);
            }
        });

        // Export function
        function exportSalaryDetails() {
            alert('Export functionality would be implemented here!');
            // In a real app, this would generate and download a CSV/PDF
        }

        // Print function
        function printPayslip() {
            alert('Print functionality would be implemented here!');
            // In a real app, this would open print dialog with formatted payslip
        }

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            initializeTable();
            updateDateTime();
            setInterval(updateDateTime, 1000);

            // Focus search input on load
            searchInput.focus();

            // Close dropdowns when clicking outside
            document.addEventListener('click', (event) => {
                if (payrollDropdown && !payrollToggle.contains(event.target) && !payrollDropdown.contains(event.target)) {
                    payrollDropdown.classList.remove('open');
                    const chevron = payrollToggle.querySelector('.chevron');
                    chevron.classList.remove('rotated');
                }
            });

            // Add hover effects to all sidebar items
            const sidebarItems = document.querySelectorAll('.sidebar-item');
            sidebarItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('active')) {
                        this.style.transform = 'translateX(0)';
                    }
                });
            });
        });
    </script>
</body>

</html>