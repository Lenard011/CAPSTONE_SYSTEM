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

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>

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
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
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
        :root {
            --primary: #1e40af;
            --secondary: #1e3a8a;
            --accent: #3b82f6;
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
            color: #1f2937;
            overflow-x: hidden;
        }

        /* NAVBAR STYLES */
        .navbar {
            background: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            height: 70px;
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
        }

        .brand-subtitle {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        /* Date & Time Display */
        .datetime-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .datetime-box {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 0.5rem 1rem;
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
            font-size: 1rem;
            color: white;
            opacity: 0.9;
        }

        .datetime-text {
            display: flex;
            flex-direction: column;
        }

        .datetime-label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .datetime-value {
            font-size: 0.85rem;
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
        }

        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        .mobile-toggle i {
            font-size: 1.25rem;
            color: white;
        }

        /* User Menu */
        .user-menu {
            position: relative;
        }

        .user-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 0.4rem 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .user-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .user-chevron {
            font-size: 0.8rem;
            color: white;
            opacity: 0.8;
            transition: transform 0.3s ease;
        }

        .user-button.active .user-chevron {
            transform: rotate(180deg);
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 280px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow: hidden;
        }

        .user-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: 1.25rem;
            background: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
        }

        .dropdown-header h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .dropdown-header p {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .dropdown-menu {
            padding: 0.5rem;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #4b5563;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: #f3f4f6;
            color: var(--primary);
            transform: translateX(5px);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            color: #9ca3af;
        }

        .dropdown-item:hover i {
            color: var(--primary);
        }

        /* Sidebar Styles */
        .sidebar-container {
            position: fixed;
            top: 70px;
            left: 0;
            height: calc(100vh - 70px);
            width: 16rem;
            z-index: 90;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
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

        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 89;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
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

        /* Main Content */
        .main-content {
            margin-top: 70px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            min-height: calc(100vh - 70px);
            width: 100%;
        }

        /* MAIN CONTENT CONTAINER */
        .main-container {
            width: 100%;
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            padding: 1.5rem;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
        }

        /* TOOLBAR STYLES */
        .toolbar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: #f8fafc;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
        }

        .search-input {
            flex-grow: 1;
            position: relative;
        }

        .search-input input {
            padding-left: 2.75rem;
            background-color: white;
            border: 1px solid #d1d5db;
            transition: all 0.2s;
            width: 100%;
            border-radius: 0.5rem;
            padding: 0.625rem 0.625rem 0.625rem 2.75rem;
        }

        .search-input input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            z-index: 10;
        }

        .toolbar-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .status-filter {
            min-width: 150px;
        }

        .status-filter select {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background-color: white;
            color: #374151;
        }

        .status-filter select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        /* TABLE CONTAINER */
        .table-container {
            width: 100%;
            overflow-x: auto;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            background-color: white;
            -webkit-overflow-scrolling: touch;
        }

        .data-table {
            width: 100%;
            min-width: 800px;
            border-collapse: separate;
            border-spacing: 0;
            background-color: white;
        }

        .data-table thead {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        }

        .data-table th {
            padding: 1rem 1rem;
            text-align: left;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            white-space: nowrap;
            border-bottom: none;
        }

        .data-table th:first-child {
            border-top-left-radius: 0.5rem;
        }

        .data-table th:last-child {
            border-top-right-radius: 0.5rem;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            color: #374151;
            font-size: 0.875rem;
        }

        .data-table tbody tr {
            transition: all 0.2s ease;
            background-color: white;
        }

        .data-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .table-checkbox {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 0.375rem;
            border: 2px solid #d1d5db;
            background-color: #ffffff;
            cursor: pointer;
        }

        .table-checkbox:checked {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }

        .employee-name-cell {
            font-weight: 600;
            color: #1f2937;
            min-width: 180px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8125rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .status-badge.approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-badge.pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-badge.rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-dot {
            display: inline-block;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 9999px;
            margin-right: 0.5rem;
        }

        .status-badge.approved .status-dot {
            background-color: #065f46;
        }

        .status-badge.pending .status-dot {
            background-color: #92400e;
        }

        .status-badge.rejected .status-dot {
            background-color: #991b1b;
        }

        .action-button-group {
            display: flex;
            gap: 0.5rem;
        }

        .action-button {
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all 0.2s;
            font-size: 0.75rem;
            white-space: nowrap;
            border: none;
            cursor: pointer;
        }

        .button-view {
            background-color: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .button-view:hover {
            background-color: #dbeafe;
        }

        .button-delete {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .button-delete:hover {
            background-color: #fee2e2;
        }

        /* MODAL STYLES */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 0.75rem;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* RESPONSIVE STYLES */
        @media (min-width: 768px) {
            .sidebar-container {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 16rem;
                width: calc(100% - 16rem);
            }

            .toolbar {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            
            .mobile-toggle {
                display: none;
            }
            
            .navbar-brand {
                display: flex;
            }
            
            .mobile-brand {
                display: none;
            }
        }

        @media (max-width: 767px) {
            .navbar {
                height: 65px;
            }

            .navbar-container {
                padding: 0 1rem;
            }

            .mobile-toggle {
                display: flex;
            }
            
            .navbar-brand {
                display: none;
            }
            
            .mobile-brand {
                display: flex;
                align-items: center;
                margin-left: 0.5rem;
            }
            
            .mobile-brand-logo {
                width: 40px;
                height: 40px;
                object-fit: contain;
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

            .datetime-container {
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

            .main-content {
                margin-top: 65px;
                padding: 1rem;
            }

            .main-container {
                padding: 1rem;
                border-radius: 0;
                box-shadow: none;
                border: none;
            }

            .section-title {
                font-size: 1.5rem;
            }

            .toolbar {
                padding: 0.75rem;
                margin-bottom: 1rem;
            }

            .table-container {
                border-radius: 0.5rem;
                border: 1px solid #e5e7eb;
            }

            .data-table {
                min-width: 850px;
            }

            .data-table th,
            .data-table td {
                padding: 0.75rem;
                font-size: 0.8125rem;
            }

            .action-button {
                padding: 0.375rem 0.5rem;
                font-size: 0.6875rem;
            }

            .action-button-group {
                flex-direction: column;
                gap: 0.25rem;
                min-width: 100px;
            }

            .status-badge {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }

            .sidebar-container {
                top: 65px;
                height: calc(100vh - 65px);
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem;
            }

            .section-title {
                font-size: 1.25rem;
            }

            .toolbar {
                padding: 0.75rem;
                gap: 0.75rem;
            }

            .toolbar-actions {
                width: 100%;
                flex-direction: column;
            }

            .status-filter {
                width: 100%;
            }

            .data-table {
                min-width: 900px;
            }

            .data-table th,
            .data-table td {
                padding: 0.5rem;
            }

            .action-button {
                padding: 0.25rem 0.375rem;
                font-size: 0.625rem;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Navigation Header -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Left Section -->
            <div class="navbar-left">
                <!-- Mobile Menu Toggle -->
                <button class="mobile-toggle" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Logo and Brand (Desktop) -->
                <a href="../dashboard.php" class="navbar-brand">
                    <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
                    <div class="brand-text">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Paluan Occidental Mindoro</span>
                    </div>
                </a>

                <!-- Logo and Brand (Mobile) -->
                <div class="mobile-brand">
                    <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
                    <div class="mobile-brand-text">
                        <span class="mobile-brand-title">HRMS</span>
                        <span class="mobile-brand-subtitle">Dashboard</span>
                    </div>
                </div>
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

                <!-- User Menu -->
               
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- Sidebar -->
    <div class="sidebar-container" id="sidebar-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <ul>
                    <!-- Dashboard -->
                    <li>
                        <a href="../php/dashboard.php" class="sidebar-item">
                            <i class="fas fa-chart-line"></i>
                            <span>Dashboard Analytics</span>
                        </a>
                    </li>

                    <!-- Employees -->
                    <li>
                        <a href="../php/Employee.php" class="sidebar-item">
                            <i class="fas fa-users"></i>
                            <span>Employees</span>
                        </a>
                    </li>

                    <!-- Attendance -->
                    <li>
                        <a href="../php/attendance.php" class="sidebar-item">
                            <i class="fas fa-calendar-check"></i>
                            <span>Attendance</span>
                        </a>
                    </li>

                    <!-- Payroll Dropdown -->
                    <li>
                        <a href="#" class="sidebar-item" id="payroll-toggle">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Payroll</span>
                            <i class="fas fa-chevron-down chevron text-xs ml-auto"></i>
                        </a>
                        <div class="sidebar-dropdown-menu" id="payroll-dropdown">
                            <a href="../php/Payrollmanagement/contractualpayrolltable1.php" class="sidebar-dropdown-item">
                                <i class="fas fa-circle text-xs"></i>
                                Contractual
                            </a>
                            <a href="../php/Payrollmanagement/joboerderpayrolltable1.php" class="sidebar-dropdown-item">
                                <i class="fas fa-circle text-xs"></i>
                                Job Order
                            </a>
                            <a href="../php/Payrollmanagement/permanentpayrolltable1.php" class="sidebar-dropdown-item">
                                <i class="fas fa-circle text-xs"></i>
                                Permanent
                            </a>
                        </div>
                    </li>

                    <!-- Leave -->
                    <li>
                        <a href="leaveemployee.php" class="sidebar-item active">
                            <i class="fas fa-umbrella-beach"></i>
                            <span>Leave Management</span>
                        </a>
                    </li>

                    <!-- Reports -->
                    <li>
                        <a href="paysliphistory.php" class="sidebar-item">
                            <i class="fas fa-file-alt"></i>
                            <span>Reports</span>
                        </a>
                    </li>

                    <!-- Salary -->
                    <li>
                        <a href="sallarypayheads.php" class="sidebar-item">
                            <i class="fas fa-hand-holding-usd"></i>
                            <span>Salary Structure</span>
                        </a>
                    </li>

                    <!-- Settings -->
                    <li>
                        <a href="settings.php" class="sidebar-item">
                            <i class="fas fa-sliders-h"></i>
                            <span>Settings</span>
                        </a>
                    </li>

                       <!-- Logout -->
                <a href="?logout=true" class="sidebar-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
                </ul>
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

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="main-container">
            <h1 class="section-title">All Leave</h1>

            <div class="toolbar">
                <div class="search-input">
                    <i class="fas fa-search text-gray-400 search-icon"></i>
                    <input type="text" id="table-search-users" class="w-full p-2.5 pl-10 text-sm text-gray-900 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Search for employees, leave types...">
                </div>

                <div class="toolbar-actions">
                    <div class="status-filter">
                        <select id="status-filter" class="w-full p-2.5 text-sm text-gray-900 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">All Status</option>
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">
                                <div class="flex items-center justify-center">
                                    <input id="checkbox-all" type="checkbox" class="table-checkbox">
                                    <label for="checkbox-all" class="sr-only">Select all</label>
                                </div>
                            </th>
                            <th style="min-width: 180px;">Employee Name</th>
                            <th style="min-width: 140px;">Leave Type</th>
                            <th style="min-width: 180px;">Requested Dates</th>
                            <th style="width: 80px; text-align: center;">Days</th>
                            <th style="width: 120px;">Status</th>
                            <th style="min-width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="leave-table-body">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- View Leave Modal -->
    <div id="viewLeaveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-eye mr-2 text-blue-600"></i>
                    View Leave Details
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-900" id="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Employee Name</p>
                            <p class="text-base font-semibold text-gray-900" id="view-employee-name">-</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Leave Type</p>
                            <p class="text-base font-semibold text-gray-900" id="view-leave-type">-</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Requested Dates</p>
                            <p class="text-base font-semibold text-gray-900" id="view-requested-dates">-</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Days Requested</p>
                            <p class="text-base font-semibold text-gray-900" id="view-days">-</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-1">Status</p>
                            <div id="view-status">-</div>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-2">Reason for Leave</p>
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <p class="text-gray-700" id="view-reason">-</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100" id="close-modal-btn">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Sample data for the leave table
        const leaveData = [
            {
                id: 1,
                employeeName: "Luz Melba Q. Tagumpay",
                leaveType: "Vacation Leave",
                requestedDates: "2023-04-01 - 2023-04-05",
                days: 5,
                status: "approved",
                reason: "Vacation with family to celebrate anniversary."
            },
            {
                id: 2,
                employeeName: "Roberta Casas",
                leaveType: "Sick Leave",
                requestedDates: "2023-04-10 - 2023-04-12",
                days: 3,
                status: "approved",
                reason: "Diagnosed with influenza, doctor recommended rest."
            },
            {
                id: 3,
                employeeName: "Michael Gough",
                leaveType: "Solo Parent Leave",
                requestedDates: "2023-05-01 - 2023-05-10",
                days: 10,
                status: "pending",
                reason: "Need to attend to child's school activities and medical appointments."
            },
            {
                id: 4,
                employeeName: "Jose Leos",
                leaveType: "Maternity Leave",
                requestedDates: "2023-06-01 - 2023-08-31",
                days: 92,
                status: "approved",
                reason: "Expected delivery date is June 5th, 2023. Following company maternity policy."
            }
        ];

        // DOM elements
        const tableBody = document.getElementById('leave-table-body');
        const searchInput = document.getElementById('table-search-users');
        const statusFilter = document.getElementById('status-filter');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const sidebarContainer = document.getElementById('sidebar-container');
        const userMenuButton = document.getElementById('user-menu-button');
        const userDropdown = document.getElementById('user-dropdown');
        const payrollToggle = document.getElementById('payroll-toggle');
        const payrollDropdown = document.getElementById('payroll-dropdown');
        const viewModal = document.getElementById('viewLeaveModal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const closeModalIcon = document.getElementById('close-modal');
        const currentDateElement = document.getElementById('current-date');
        const currentTimeElement = document.getElementById('current-time');

        // Initialize the table with data
        function renderTable(data) {
            if (data.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7">
                            <div class="py-12 text-center text-gray-500">
                                <i class="fas fa-clipboard-list text-4xl mb-4 text-gray-300"></i>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">No leave requests found</h3>
                                <p class="text-sm">Try adjusting your search or filter to find what you're looking for.</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            tableBody.innerHTML = '';

            data.forEach(item => {
                const statusClass = item.status === 'approved' ? 'approved' :
                    item.status === 'pending' ? 'pending' : 'rejected';
                const statusText = item.status === 'approved' ? 'Approved' :
                    item.status === 'pending' ? 'Pending' : 'Rejected';

                // Create table row
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="text-center">
                        <div class="flex items-center justify-center">
                            <input id="checkbox-${item.id}" type="checkbox" class="table-checkbox">
                            <label for="checkbox-${item.id}" class="sr-only">Select ${item.employeeName}</label>
                        </div>
                    </td>
                    <td class="employee-name-cell">
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                <span class="text-blue-600 font-semibold text-sm">${getInitials(item.employeeName)}</span>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900">${item.employeeName}</div>
                                <div class="text-xs text-gray-500">ID: EMP${item.id.toString().padStart(4, '0')}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="flex items-center">
                            <i class="fas ${getLeaveTypeIcon(item.leaveType)} text-blue-500 mr-2"></i>
                            <span>${item.leaveType}</span>
                        </div>
                    </td>
                    <td>
                        <div class="flex items-center">
                            <i class="far fa-calendar-alt text-gray-400 mr-2"></i>
                            <span class="font-medium">${item.requestedDates}</span>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-50 text-blue-700 font-bold">
                            ${item.days}
                        </span>
                    </td>
                    <td>
                        <span class="status-badge ${statusClass}">
                            <span class="status-dot"></span>
                            ${statusText}
                        </span>
                    </td>
                    <td>
                        <div class="action-button-group">
                            <button type="button" class="action-button button-view" 
                                data-id="${item.id}">
                                <i class="fas fa-eye"></i>
                                <span>View</span>
                            </button>
                            <button type="button" class="action-button button-delete" 
                                data-id="${item.id}">
                                <i class="fas fa-trash"></i>
                                <span>Delete</span>
                            </button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(row);
            });

            // Attach event listeners to buttons
            attachButtonListeners();
        }

        // Get initials from name
        function getInitials(name) {
            return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        }

        // Get appropriate icon for leave type
        function getLeaveTypeIcon(leaveType) {
            const icons = {
                'Vacation Leave': 'fa-umbrella-beach',
                'Sick Leave': 'fa-stethoscope',
                'Solo Parent Leave': 'fa-user-friends',
                'Maternity Leave': 'fa-baby',
                'Paternity Leave': 'fa-user-tie',
                'Bereavement Leave': 'fa-heart',
                'Emergency Leave': 'fa-exclamation-circle',
                'Study Leave': 'fa-graduation-cap',
                'Special Leave': 'fa-star'
            };
            return icons[leaveType] || 'fa-calendar-alt';
        }

        // Update date and time
        function updateDateTime() {
            const now = new Date();
            
            // Format date
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const formattedDate = now.toLocaleDateString('en-US', options);
            if (currentDateElement) currentDateElement.textContent = formattedDate;
            
            // Format time
            const formattedTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            if (currentTimeElement) currentTimeElement.textContent = formattedTime;
        }

        // Filter table based on search and status
        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value;

            const filteredData = leaveData.filter(item => {
                const matchesSearch = item.employeeName.toLowerCase().includes(searchTerm) ||
                    item.leaveType.toLowerCase().includes(searchTerm);
                const matchesStatus = statusValue === 'all' || item.status === statusValue;

                return matchesSearch && matchesStatus;
            });

            renderTable(filteredData);
        }

        // Attach event listeners to buttons
        function attachButtonListeners() {
            // View buttons
            document.querySelectorAll('.action-button.button-view').forEach(button => {
                button.addEventListener('click', function() {
                    const id = parseInt(this.getAttribute('data-id'));
                    viewLeave(id);
                });
            });

            // Delete buttons
            document.querySelectorAll('.action-button.button-delete').forEach(button => {
                button.addEventListener('click', function() {
                    const id = parseInt(this.getAttribute('data-id'));
                    deleteLeave(id);
                });
            });
        }

        // View leave function
        function viewLeave(id) {
            const leave = leaveData.find(item => item.id === id);
            if (!leave) return;

            const statusText = leave.status === 'approved' ? 'Approved' :
                leave.status === 'pending' ? 'Pending' : 'Rejected';
            const statusColor = leave.status === 'approved' ? 'text-green-600' :
                leave.status === 'pending' ? 'text-yellow-600' : 'text-red-600';

            document.getElementById('view-employee-name').textContent = leave.employeeName;
            document.getElementById('view-leave-type').textContent = leave.leaveType;
            document.getElementById('view-requested-dates').textContent = leave.requestedDates;
            document.getElementById('view-days').textContent = leave.days;
            document.getElementById('view-reason').textContent = leave.reason;
            
            const statusElement = document.getElementById('view-status');
            statusElement.innerHTML = `
                <span class="status-badge ${leave.status}">
                    <span class="status-dot"></span>
                    ${statusText}
                </span>
            `;

            viewModal.classList.add('active');
        }

        // Delete leave function
        function deleteLeave(id) {
            if (confirm('Are you sure you want to delete this leave request?')) {
                // In a real application, you would send a request to delete the leave
                const index = leaveData.findIndex(item => item.id === id);
                if (index !== -1) {
                    leaveData.splice(index, 1);
                    filterTable();
                    alert('Leave request has been deleted.');
                }
            }
        }

        // Toggle sidebar on mobile
        function toggleSidebar() {
            if (window.innerWidth < 768) {
                sidebarContainer.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
            }
        }

        // Toggle user dropdown
        function toggleUserDropdown() {
            userDropdown.classList.toggle('active');
            userMenuButton.classList.toggle('active');
        }

        // Toggle payroll dropdown
        function togglePayrollDropdown() {
            payrollDropdown.classList.toggle('open');
            const chevron = payrollToggle.querySelector('.chevron');
            chevron.classList.toggle('rotated');
        }

        // Close modal
        function closeModal() {
            viewModal.classList.remove('active');
        }

        // Close dropdowns when clicking outside
        function closeAllDropdowns(event) {
            // Close user dropdown
            if (!event.target.closest('.user-menu')) {
                userDropdown.classList.remove('active');
                userMenuButton.classList.remove('active');
            }
            
            // Close mobile sidebar
            if (window.innerWidth < 768 && 
                !event.target.closest('.sidebar-container') && 
                !event.target.closest('#sidebar-toggle')) {
                sidebarContainer.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            }
            
            // Close payroll dropdown
            if (!event.target.closest('#payroll-toggle')) {
                payrollDropdown.classList.remove('open');
                const chevron = payrollToggle.querySelector('.chevron');
                chevron.classList.remove('rotated');
            }
        }

        // Handle window resize
        function handleResize() {
            if (window.innerWidth >= 768) {
                sidebarContainer.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            }
        }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Render initial table
            renderTable(leaveData);
            
            // Initialize date and time
            updateDateTime();
            setInterval(updateDateTime, 1000);
            
            // Add event listeners
            searchInput.addEventListener('input', filterTable);
            statusFilter.addEventListener('change', filterTable);
            
            // Sidebar and dropdown functionality
            if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
            if (sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);
            if (userMenuButton) userMenuButton.addEventListener('click', toggleUserDropdown);
            if (payrollToggle) payrollToggle.addEventListener('click', togglePayrollDropdown);
            
            // Modal functionality
            if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
            if (closeModalIcon) closeModalIcon.addEventListener('click', closeModal);
            
            // Close modal when clicking outside
            viewModal.addEventListener('click', function(e) {
                if (e.target === viewModal) {
                    closeModal();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && viewModal.classList.contains('active')) {
                    closeModal();
                }
            });
            
            document.addEventListener('click', closeAllDropdowns);
            window.addEventListener('resize', handleResize);
            
            // Select all checkboxes
            const checkboxAll = document.getElementById('checkbox-all');
            if (checkboxAll) {
                checkboxAll.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('input[type="checkbox"].table-checkbox:not(#checkbox-all)');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }
            
            // Handle individual checkbox changes
            tableBody.addEventListener('change', function(e) {
                if (e.target.type === 'checkbox' && e.target.id !== 'checkbox-all') {
                    const allCheckboxes = document.querySelectorAll('input[type="checkbox"].table-checkbox:not(#checkbox-all)');
                    const checkedCheckboxes = document.querySelectorAll('input[type="checkbox"].table-checkbox:not(#checkbox-all):checked');
                    
                    if (checkboxAll) {
                        checkboxAll.checked = allCheckboxes.length === checkedCheckboxes.length;
                    }
                }
            });
        });
    </script>
</body>
</html>