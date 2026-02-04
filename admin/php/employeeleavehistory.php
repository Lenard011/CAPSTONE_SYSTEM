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
    <title>Leave History | HR Management System</title>
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
            background: var(--gradient-nav);
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
            --primary-color: #1e40af;
            --primary-light: #3b82f6;
            --secondary-color: #6b7280;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #0ea5e9;
            --light-bg: #f8fafc;
            --dark-text: #1f2937;
            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            --gradient-danger: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            --gradient-nav: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
            overflow-x: hidden;
            color: #1f2937;
        }

        /* IMPROVED NAVBAR */
        .navbar {
            background: var(--gradient-nav);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            height: 70px;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
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
            gap: 1rem;
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
            min-width: 160px;
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

        /* User Menu */
        .user-menu {
            position: relative;
        }

        .user-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 0.4rem 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
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
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .user-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: 1.25rem;
            background: var(--gradient-nav);
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
            font-weight: 500;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            color: #9ca3af;
        }

        .dropdown-item:hover i {
            color: var(--primary-color);
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
        }

        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        .mobile-toggle i {
            font-size: 1.25rem;
            color: white;
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
            width: 16rem;
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
        }

        .sidebar {
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, var(--primary-color) 0%, #1e3a8a 100%);
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

        .sidebar-item .chevron {
            transition: transform 0.3s ease;
        }

        .sidebar-item .chevron.rotated {
            transform: rotate(180deg);
        }

        /* Dropdown Menu */
        .sidebar-dropdown {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            margin-left: 2.5rem;
        }

        .sidebar-dropdown.open {
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
            font-size: 0.9rem;
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

        /* IMPROVED MAIN CONTENT STYLES */

        /* Tab Container */
        .tab-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 30px;
            border: 1px solid #e5e7eb;
        }

        .tab-header {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            flex-wrap: wrap;
        }

        .tab-button {
            padding: 16px 24px;
            background: none;
            border: none;
            font-weight: 600;
            color: var(--secondary-color);
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            white-space: nowrap;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-button.active {
            color: var(--primary-color);
        }

        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--gradient-primary);
            border-radius: 3px 3px 0 0;
        }

        .tab-button:hover {
            color: var(--primary-color);
            background-color: #f3f4f6;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark-text);
            margin-bottom: 8px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: var(--secondary-color);
            font-size: 1.1rem;
        }

        /* IMPROVED Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            padding: 24px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .stat-card.total::before {
            background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
        }

        .stat-card.approved::before {
            background: var(--gradient-success);
        }

        .stat-card.pending::before {
            background: var(--gradient-warning);
        }

        .stat-card.rejected::before {
            background: var(--gradient-danger);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 8px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.total .stat-value {
            background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.approved .stat-value {
            background: var(--gradient-success);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.pending .stat-value {
            background: var(--gradient-warning);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.rejected .stat-value {
            background: var(--gradient-danger);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 14px;
            color: var(--secondary-color);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(30, 64, 175, 0.1) 100%);
            color: var(--primary-color);
        }

        .stat-card.total .stat-icon {
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.1) 0%, rgba(75, 85, 99, 0.1) 100%);
            color: #6b7280;
        }

        .stat-card.approved .stat-icon {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
            color: var(--success-color);
        }

        .stat-card.pending .stat-icon {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
            color: var(--warning-color);
        }

        .stat-card.rejected .stat-icon {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
            color: var(--danger-color);
        }

        .stat-icon i {
            font-size: 1.75rem;
        }

        .stat-trend {
            font-size: 12px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .trend-up {
            color: var(--success-color);
        }

        .trend-down {
            color: var(--danger-color);
        }

        .trend-neutral {
            color: var(--secondary-color);
        }

        /* IMPROVED Main Card */
        .main-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            padding: 28px;
            margin-bottom: 30px;
            border: 1px solid #e5e7eb;
            position: relative;
            overflow: hidden;
        }

        .main-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        @media (max-width: 768px) {
            .main-card {
                padding: 20px;
            }
        }

        .card-header {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 24px;
        }

        @media (min-width: 768px) {
            .card-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-text);
            position: relative;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--gradient-primary);
            border-radius: 3px;
        }

        /* IMPROVED Search and filter styles */
        .search-filter-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }

        @media (min-width: 768px) {
            .search-filter-container {
                flex-direction: row;
                align-items: center;
                justify-content: flex-end;
                width: auto;
                gap: 12px;
            }
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
            font-weight: 500;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .filter-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }

        @media (min-width: 768px) {
            .filter-container {
                flex-direction: row;
                align-items: center;
                gap: 8px;
                width: auto;
            }
        }

        .filter-select {
            background: white;
            border: 2px solid #e5e7eb;
            color: #4b5563;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 160px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-select:hover {
            border-color: #d1d5db;
        }

        /* IMPROVED Button styles */
        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.35);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            color: #4b5563;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--gradient-success);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.35);
        }

        .btn-danger {
            background: var(--gradient-danger);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.35);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
            border-radius: 10px;
        }

        /* ENHANCED TABLE DESIGN */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            border-bottom: 1px solid #e5e7eb;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-controls {
            display: flex;
            gap: 8px;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 900px;
        }

        @media (max-width: 768px) {
            table {
                min-width: 100%;
            }
        }

        thead {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-bottom: 2px solid #e5e7eb;
        }

        th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-text);
            white-space: nowrap;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e5e7eb;
            position: sticky;
            top: 57px;
            z-index: 9;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        }

        th:first-child {
            border-top-left-radius: 12px;
        }

        th:last-child {
            border-top-right-radius: 12px;
        }

        tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f3f4f6;
            position: relative;
        }

        tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        tbody tr:nth-child(even) {
            background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%);
        }

        tbody tr:nth-child(even):hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        td {
            padding: 18px 20px;
            color: var(--dark-text);
            font-weight: 500;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        /* Enhanced Employee Column */
        .employee-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 200px;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .employee-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .employee-name {
            font-weight: 600;
            color: var(--dark-text);
            font-size: 0.95rem;
        }

        .employee-id {
            font-size: 0.8rem;
            color: var(--secondary-color);
            font-weight: 500;
        }

        /* Enhanced Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            gap: 6px;
            width: fit-content;
            min-width: 100px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .status-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .status-approved {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.15) 100%);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-pending {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(217, 119, 6, 0.15) 100%);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-rejected {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.15) 100%);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .status-icon {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }

        .status-approved .status-icon {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
        }

        .status-pending .status-icon {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
        }

        .status-rejected .status-icon {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger-color);
        }

        /* Enhanced Action buttons in table */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        @media (max-width: 480px) {
            .action-buttons {
                flex-direction: column;
            }
        }

        .action-btn {
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            min-width: 80px;
        }

        .action-btn.view {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.1) 0%, rgba(56, 189, 248, 0.1) 100%);
            color: var(--info-color);
            border: 1px solid rgba(14, 165, 233, 0.2);
        }

        .action-btn.view:hover {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.2) 0%, rgba(56, 189, 248, 0.2) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.15);
        }

        .action-btn.approve {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(34, 197, 94, 0.1) 100%);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .action-btn.approve:hover {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(34, 197, 94, 0.2) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        .action-btn.reject {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(248, 113, 113, 0.1) 100%);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .action-btn.reject:hover {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(248, 113, 113, 0.2) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
        }

        /* Column highlight on hover */
        th:hover {
            background: linear-gradient(135deg, #e2e8f0 0%, #d1d5db 100%);
            cursor: pointer;
        }

        /* Table empty state */
        .table-empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .table-empty-state i {
            font-size: 48px;
            color: #d1d5db;
            margin-bottom: 16px;
        }

        .table-empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-text);
            margin-bottom: 8px;
        }

        .table-empty-state p {
            color: var(--secondary-color);
            font-size: 0.95rem;
        }

        /* Loading State */
        .loading-state {
            text-align: center;
            padding: 60px 20px;
        }

        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: var(--primary-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-bottom: 16px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Pagination */
        .pagination {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        @media (min-width: 768px) {
            .pagination {
                flex-direction: row;
                justify-content: space-between;
            }
        }

        .pagination-info {
            font-size: 14px;
            color: var(--secondary-color);
            font-weight: 500;
        }

        .pagination-info strong {
            color: var(--dark-text);
            font-weight: 600;
        }

        .pagination-controls {
            display: flex;
            gap: 8px;
        }

        .pagination-btn {
            padding: 10px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            background: white;
            color: var(--secondary-color);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            gap: 6px;
        }

        .pagination-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            border-color: #d1d5db;
            transform: translateY(-2px);
        }

        .pagination-btn.active {
            background: var(--gradient-primary);
            color: white;
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.25);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-numbers {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .page-ellipsis {
            padding: 0 8px;
            color: var(--secondary-color);
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .mobile-hidden {
                display: none !important;
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
                right: -10px;
            }

            /* Main Content */
            .main-content {
                padding: 1rem;
                margin-left: 0 !important;
            }

            .tab-button {
                padding: 12px 16px;
                font-size: 14px;
            }

            th,
            td {
                padding: 14px 16px;
                font-size: 14px;
            }

            .employee-avatar {
                width: 36px;
                height: 36px;
                font-size: 0.8rem;
            }

            .status-badge {
                padding: 6px 12px;
                font-size: 11px;
                min-width: 90px;
            }

            .action-btn {
                padding: 6px 12px;
                font-size: 12px;
                min-width: 70px;
            }

            .stat-value {
                font-size: 24px;
            }

            .stat-icon {
                width: 48px;
                height: 48px;
            }

            .stat-icon i {
                font-size: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }

            .page-header p {
                font-size: 1rem;
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

            .stat-value {
                font-size: 22px;
            }

            .stat-icon {
                width: 44px;
                height: 44px;
            }

            .stat-icon i {
                font-size: 1.4rem;
            }

            .main-card {
                padding: 16px;
            }

            .card-title {
                font-size: 1.3rem;
            }

            th,
            td {
                padding: 12px;
            }

            .employee-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .employee-avatar {
                width: 32px;
                height: 32px;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.3);
        }

        /* Utility Classes */
        .hidden {
            display: none !important;
        }

        .fade-in {
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

        .slide-up {
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(10px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Badge for days */
        .days-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%);
            color: #8b5cf6;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        /* Enhanced hover effects */
        .hover-lift {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        /* Smooth transitions */
        * {
            transition: background-color 0.2s ease, border-color 0.2s ease;
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
                    <div class="brand-text mobile-hidden">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Paluan Occidental Mindoro</span>
                    </div>
                </a>

                <!-- Mobile Brand Text -->
                <div class="mobile-brand-text md:hidden ml-2">
                    <span class="mobile-brand-title">HRMS</span>
                    <span class="mobile-brand-subtitle">Dashboard</span>
                </div>
            </div>

            <!-- Right Section -->
            <div class="navbar-right">
                <!-- Date & Time -->
                <div class="datetime-container mobile-hidden">
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
                <ul class="space-y-1">
                    <!-- Dashboard -->
                    <li>
                        <a href="../php/dashboard.php" class="sidebar-item">
                            <i class="fas fa-chart-line"></i>
                            <span>Dashboard Analytics</span>
                        </a>
                    </li>

                    <!-- Employees -->
                    <li>
                        <a href="../php/employees/Employee.php" class="sidebar-item ">
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
                        <div class="sidebar-dropdown" id="payroll-dropdown">
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
                    </li>

                    <!-- Leave -->
                    <li>
                        <a href="leaveemployee.php" class="sidebar-item">
                            <i class="fas fa-umbrella-beach"></i>
                            <span>Leave Management</span>
                        </a>
                    </li>

                    <!-- Reports -->
                    <li>
                        <a href="paysliplist.php" class="sidebar-item active">
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
    <main class="main-content" id="main-content">
       

        <!-- Page Header -->
        <div class="page-header">
            <h1>Leave History</h1>
            <p>Track and manage all employee leave requests and approvals</p>
        </div>
 <!-- Tab Navigation -->
        <div class="tab-container">
            <div class="tab-header">
                <a href="./paysliphistory.php">
                    <button class="tab-button">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span class="mobile-hidden">Payslip History</span>
                        <span class="md:hidden">Payslip</span>
                    </button>
                </a>
                <a href="./employeeattendancehistory.php">
                    <button class="tab-button">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="mobile-hidden">Attendance History</span>
                        <span class="md:hidden">Attendance</span>
                    </button>
                </a>
                <button class="tab-button active">
                    <i class="fas fa-umbrella-beach"></i>
                    <span class="mobile-hidden">Leave History</span>
                    <span class="md:hidden">Leave</span>
                </button>
            </div>
        </div>
        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-value">24</div>
                <div class="stat-label">Total Requests</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>12.5% from last month</span>
                </div>
            </div>
            <div class="stat-card approved">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value">16</div>
                <div class="stat-label">Approved</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span>8.3% from last month</span>
                </div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value">5</div>
                <div class="stat-label">Pending</div>
                <div class="stat-trend trend-neutral">
                    <i class="fas fa-minus"></i>
                    <span>No change</span>
                </div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-value">3</div>
                <div class="stat-label">Rejected</div>
                <div class="stat-trend trend-down">
                    <i class="fas fa-arrow-down"></i>
                    <span>25% from last month</span>
                </div>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="main-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-list-alt text-blue-600"></i>
                    Leave Requests
                </h2>
                <div class="search-filter-container">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="search-input" placeholder="Search by employee, department, or leave type...">
                    </div>
                    <div class="filter-container">
                        <select id="department-filter" class="filter-select">
                            <option value="all">All Departments</option>
                            <option value="Budget Office">Budget Office</option>
                            <option value="PDRRMO">PDRRMO</option>
                            <option value="Mayor's Office">Mayor's Office</option>
                            <option value="Tourism Office">Tourism Office</option>
                            <option value="MSWDO Office">MSWDO Office</option>
                        </select>
                        <select id="status-filter" class="filter-select">
                            <option value="all">All Status</option>
                            <option value="Approved">Approved</option>
                            <option value="Pending">Pending</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                        <button id="export-btn" class="btn btn-secondary">
                            <i class="fas fa-download"></i>
                            <span class="mobile-hidden">Export</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Table Container -->
            <div class="table-wrapper">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-table text-blue-600"></i>
                        Leave Request Records
                    </h3>
                    <div class="table-controls">
                        <button id="refresh-btn" class="btn btn-sm btn-secondary">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button id="help-btn" class="btn btn-sm btn-secondary">
                            <i class="fas fa-question-circle"></i>
                        </button>
                    </div>
                </div>

                <table id="leave-table">
                    <thead>
                        <tr>
                            <th>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-user text-blue-600"></i>
                                    Employee
                                </div>
                            </th>
                            <th class="mobile-hidden">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-building text-blue-600"></i>
                                    Department
                                </div>
                            </th>
                            <th>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-calendar-day text-blue-600"></i>
                                    Leave Date
                                </div>
                            </th>
                            <th class="mobile-hidden">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-tag text-blue-600"></i>
                                    Leave Type
                                </div>
                            </th>
                            <th>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-circle text-blue-600"></i>
                                    Status
                                </div>
                            </th>
                            <th>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-cogs text-blue-600"></i>
                                    Actions
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="leave-table-body">
                        <!-- Table rows will be dynamically generated -->
                    </tbody>
                </table>

                <!-- Loading State -->
                <div id="loading-state" class="loading-state hidden">
                    <div class="spinner"></div>
                    <p>Loading leave data...</p>
                </div>

                <!-- Empty State -->
                <div id="no-results" class="table-empty-state hidden">
                    <i class="fas fa-inbox"></i>
                    <h3 class="text-lg font-semibold mb-2">No Leave Records Found</h3>
                    <p class="text-gray-600">Try adjusting your search or filter criteria</p>
                    <button id="clear-filters-btn" class="btn btn-primary mt-4">
                        <i class="fas fa-filter"></i>
                        Clear All Filters
                    </button>
                </div>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <div class="pagination-info" id="pagination-info">
                    Showing <strong>0</strong> to <strong>0</strong> of <strong>0</strong> entries
                </div>
                <div class="pagination-controls" id="pagination-controls">
                    <!-- Pagination buttons will be dynamically generated -->
                </div>
            </div>
        </div>
    </main>

    <!-- View Leave Modal -->
    <div class="modal-overlay" id="viewLeaveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-file-alt text-blue-600"></i>
                    Leave Request Details
                </h3>
                <button class="modal-close" id="close-modal-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Employee Name</label>
                        <div class="form-value" id="modal-employee-name">-</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <div class="form-value" id="modal-department">-</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Leave Date</label>
                        <div class="form-value" id="modal-leave-date">-</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date Submitted</label>
                        <div class="form-value" id="modal-date-submitted">-</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Leave Type</label>
                        <div class="form-value" id="modal-leave-type">-</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div class="form-value" id="modal-status">-</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason for Leave</label>
                    <div class="form-textarea" id="modal-reason">-</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Manager's Comments</label>
                    <div class="form-textarea" id="modal-comments">-</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="print-modal-btn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn btn-primary" id="close-modal-btn-2">
                    <i class="fas fa-check"></i> Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Sample leave data
        const leaveData = [{
                id: 1,
                employeeName: "Russel James Tadalan",
                department: "Budget Office",
                leaveDate: "October 29, 2024",
                dateSubmitted: "October 28, 2024",
                leaveType: "Vacation Leave",
                status: "Approved",
                reason: "Family vacation out of town. Planning to visit relatives in another province for family reunion.",
                comments: "Approved. Please ensure all pending tasks are completed before your leave. Coordinate with your team lead for proper handover.",
                avatarInitials: "RJ"
            },
            {
                id: 2,
                employeeName: "Jerwin Sequijor",
                department: "PDRRMO",
                leaveDate: "November 13, 2024",
                dateSubmitted: "November 12, 2024",
                leaveType: "Sick Leave",
                status: "Pending",
                reason: "Medical check-up and recovery. Doctor's appointment for routine health assessment.",
                comments: "Awaiting medical certificate submission. Please submit your medical certificate within 3 working days.",
                avatarInitials: "JS"
            },
            {
                id: 3,
                employeeName: "Jirah Jay Cuyos",
                department: "Mayor's Office",
                leaveDate: "November 22, 2024",
                dateSubmitted: "November 16, 2024",
                leaveType: "Emergency Leave",
                status: "Rejected",
                reason: "Family emergency requiring immediate attention out of town.",
                comments: "Rejected due to critical project deadline during requested period. Please reschedule for a later date.",
                avatarInitials: "JC"
            },
            {
                id: 4,
                employeeName: "Joy Ambrosio",
                department: "Tourism Office",
                leaveDate: "December 16, 2024",
                dateSubmitted: "November 16, 2024",
                leaveType: "Vacation Leave",
                status: "Approved",
                reason: "Year-end holiday with family. Planning a week-long vacation with immediate family members.",
                comments: "Approved. Please coordinate with team for coverage. Ensure all year-end reports are submitted before leave.",
                avatarInitials: "JA"
            },
            {
                id: 5,
                employeeName: "Dexter Balanza",
                department: "MSWDO Office",
                leaveDate: "December 4, 2024",
                dateSubmitted: "December 3, 2024",
                leaveType: "Personal Leave",
                status: "Pending",
                reason: "Personal matters to attend to. Need to handle important personal documentation.",
                comments: "Pending supervisor review. Additional information requested regarding the personal matter.",
                avatarInitials: "DB"
            },
            {
                id: 6,
                employeeName: "Maria Santos",
                department: "Budget Office",
                leaveDate: "December 10, 2024",
                dateSubmitted: "December 5, 2024",
                leaveType: "Sick Leave",
                status: "Approved",
                reason: "Flu and fever. Doctor advised 3-day rest for recovery.",
                comments: "Approved. Get well soon. Please submit medical certificate upon return.",
                avatarInitials: "MS"
            },
            {
                id: 7,
                employeeName: "Juan Dela Cruz",
                department: "PDRRMO",
                leaveDate: "December 20, 2024",
                dateSubmitted: "December 15, 2024",
                leaveType: "Vacation Leave",
                status: "Pending",
                reason: "Christmas vacation with family. Planning to visit hometown for holiday celebration.",
                comments: "Awaiting approval from department head due to holiday staffing requirements.",
                avatarInitials: "JD"
            },
            {
                id: 8,
                employeeName: "Ana Reyes",
                department: "Mayor's Office",
                leaveDate: "January 5, 2025",
                dateSubmitted: "December 28, 2024",
                leaveType: "Maternity Leave",
                status: "Approved",
                reason: "Maternity leave for childbirth and postnatal care.",
                comments: "Approved. Best wishes for the new addition to your family. Please submit all required documents to HR.",
                avatarInitials: "AR"
            },
            {
                id: 9,
                employeeName: "Carlos Lopez",
                department: "Tourism Office",
                leaveDate: "January 15, 2025",
                dateSubmitted: "January 10, 2025",
                leaveType: "Vacation Leave",
                status: "Approved",
                reason: "Winter vacation with family to northern regions.",
                comments: "Approved. Enjoy your vacation!",
                avatarInitials: "CL"
            },
            {
                id: 10,
                employeeName: "Sofia Garcia",
                department: "MSWDO Office",
                leaveDate: "February 1, 2025",
                dateSubmitted: "January 25, 2025",
                leaveType: "Study Leave",
                status: "Pending",
                reason: "Professional development course for career advancement.",
                comments: "Awaiting training approval from HR department.",
                avatarInitials: "SG"
            }
        ];

        // Global variables
        let currentPage = 1;
        const itemsPerPage = 8;
        let filteredData = [...leaveData];
        let sortColumn = 'employeeName';
        let sortDirection = 'asc';

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Update date and time
            function updateDateTime() {
                const now = new Date();
                const date = now.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                const time = now.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });

                document.getElementById('current-date').textContent = date;
                document.getElementById('current-time').textContent = time;
            }

            updateDateTime();
            setInterval(updateDateTime, 1000);

            // Sidebar toggle
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarContainer = document.getElementById('sidebar-container');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            sidebarToggle.addEventListener('click', function() {
                sidebarContainer.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = 'hidden';
            });

            sidebarOverlay.addEventListener('click', function() {
                sidebarContainer.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            });

            // User menu toggle
            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-dropdown');

            userMenuButton.addEventListener('click', function() {
                userMenuButton.classList.toggle('active');
                userDropdown.classList.toggle('active');
            });

            // Close user dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
                    userMenuButton.classList.remove('active');
                    userDropdown.classList.remove('active');
                }
            });

            // Payroll dropdown toggle
            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollDropdown = document.getElementById('payroll-dropdown');

            if (payrollToggle) {
                payrollToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const chevron = this.querySelector('.chevron');
                    chevron.classList.toggle('rotated');
                    payrollDropdown.classList.toggle('open');
                });
            }

            initializePage();
            setupEventListeners();
            renderTable();
        });

        function initializePage() {
            updatePaginationInfo();
            renderPaginationControls();
            setupTableSorting();
        }

        function setupEventListeners() {
            // Search input
            const searchInput = document.getElementById('search-input');
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(handleSearch, 300);
            });

            // Filter changes
            document.getElementById('department-filter').addEventListener('change', handleSearch);
            document.getElementById('status-filter').addEventListener('change', handleSearch);

            // Export button
            document.getElementById('export-btn').addEventListener('click', handleExport);

            // Refresh button
            document.getElementById('refresh-btn').addEventListener('click', handleRefresh);

            // Help button
            document.getElementById('help-btn').addEventListener('click', showHelp);

            // Clear filters button
            document.getElementById('clear-filters-btn')?.addEventListener('click', clearFilters);

            // Modal close buttons
            document.getElementById('close-modal-btn').addEventListener('click', closeModal);
            document.getElementById('close-modal-btn-2').addEventListener('click', closeModal);

            // Close modal when clicking overlay
            document.getElementById('viewLeaveModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });

            // Print modal button
            document.getElementById('print-modal-btn').addEventListener('click', handlePrintModal);

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
                if (e.key === 'r' && e.ctrlKey) {
                    e.preventDefault();
                    handleRefresh();
                }
                if (e.key === 'f' && e.ctrlKey) {
                    e.preventDefault();
                    document.getElementById('search-input').focus();
                }
            });
        }

        function setupTableSorting() {
            const tableHeaders = document.querySelectorAll('#leave-table thead th');

            tableHeaders.forEach((header, index) => {
                header.addEventListener('click', function() {
                    const columns = ['employeeName', 'department', 'leaveDate', 'leaveType', 'status', 'actions'];
                    if (index < columns.length && columns[index] !== 'actions') {
                        if (sortColumn === columns[index]) {
                            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                        } else {
                            sortColumn = columns[index];
                            sortDirection = 'asc';
                        }

                        // Remove sort indicators from all headers
                        tableHeaders.forEach(h => {
                            h.querySelector('.sort-indicator')?.remove();
                        });

                        // Add sort indicator to current header
                        const indicator = document.createElement('span');
                        indicator.className = 'sort-indicator ml-1';
                        indicator.innerHTML = sortDirection === 'asc' ?
                            '<i class="fas fa-arrow-up text-xs"></i>' :
                            '<i class="fas fa-arrow-down text-xs"></i>';
                        this.appendChild(indicator);

                        // Sort and render table
                        sortData();
                        renderTable();
                    }
                });
            });
        }

        function sortData() {
            filteredData.sort((a, b) => {
                let aValue = a[sortColumn];
                let bValue = b[sortColumn];

                if (sortColumn === 'leaveDate') {
                    aValue = new Date(a.leaveDate);
                    bValue = new Date(b.leaveDate);
                }

                if (sortDirection === 'asc') {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });
        }

        function handleSearch() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase();
            const departmentFilter = document.getElementById('department-filter').value;
            const statusFilter = document.getElementById('status-filter').value;

            // Show loading state
            showLoading(true);

            // Simulate API call delay
            setTimeout(() => {
                filteredData = leaveData.filter(item => {
                    const matchesSearch = item.employeeName.toLowerCase().includes(searchTerm) ||
                        item.department.toLowerCase().includes(searchTerm) ||
                        item.leaveType.toLowerCase().includes(searchTerm) ||
                        item.reason.toLowerCase().includes(searchTerm);
                    const matchesDepartment = departmentFilter === 'all' || item.department === departmentFilter;
                    const matchesStatus = statusFilter === 'all' || item.status === statusFilter;

                    return matchesSearch && matchesDepartment && matchesStatus;
                });

                currentPage = 1;
                sortData();
                renderTable();
                updatePaginationInfo();
                renderPaginationControls();
                showLoading(false);

                // Show no results if needed
                const noResults = document.getElementById('no-results');
                const tableBody = document.getElementById('leave-table-body');
                if (filteredData.length === 0) {
                    noResults.classList.remove('hidden');
                    tableBody.classList.add('hidden');
                } else {
                    noResults.classList.add('hidden');
                    tableBody.classList.remove('hidden');
                }
            }, 300);
        }

        function handleExport() {
            // Show loading state
            const exportBtn = document.getElementById('export-btn');
            const originalHTML = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Exporting...';
            exportBtn.disabled = true;

            // Simulate export process
            setTimeout(() => {
                showToast('Leave data exported successfully! File will download shortly.', 'success');

                // Reset button
                exportBtn.innerHTML = originalHTML;
                exportBtn.disabled = false;
            }, 1500);
        }

        function handleRefresh() {
            const refreshBtn = document.getElementById('refresh-btn');
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            refreshBtn.disabled = true;

            // Simulate refresh
            setTimeout(() => {
                currentPage = 1;
                sortData();
                renderTable();
                updatePaginationInfo();
                renderPaginationControls();
                showToast('Data refreshed successfully', 'success');

                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
                refreshBtn.disabled = false;
            }, 800);
        }

        function showHelp() {
            showToast('Click on column headers to sort. Use filters to narrow down results.', 'info');
        }

        function clearFilters() {
            document.getElementById('search-input').value = '';
            document.getElementById('department-filter').value = 'all';
            document.getElementById('status-filter').value = 'all';
            handleSearch();
        }

        function handlePrintModal() {
            window.print();
        }

        function openModal(data) {
            // Populate modal with data
            document.getElementById('modal-employee-name').textContent = data.employeeName;
            document.getElementById('modal-department').textContent = data.department;
            document.getElementById('modal-leave-date').textContent = data.leaveDate;
            document.getElementById('modal-date-submitted').textContent = data.dateSubmitted;
            document.getElementById('modal-leave-type').textContent = data.leaveType;

            // Set status with appropriate styling
            const statusElement = document.getElementById('modal-status');
            statusElement.textContent = data.status;
            statusElement.className = 'form-value status-badge';

            if (data.status === 'Approved') {
                statusElement.classList.add('status-approved');
            } else if (data.status === 'Pending') {
                statusElement.classList.add('status-pending');
            } else if (data.status === 'Rejected') {
                statusElement.classList.add('status-rejected');
            }

            document.getElementById('modal-reason').textContent = data.reason;
            document.getElementById('modal-comments').textContent = data.comments;

            // Show the modal
            document.getElementById('viewLeaveModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('viewLeaveModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function renderTable() {
            const tableBody = document.getElementById('leave-table-body');
            tableBody.innerHTML = '';

            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const currentData = filteredData.slice(startIndex, endIndex);

            currentData.forEach(item => {
                const row = document.createElement('tr');
                row.className = 'fade-in';

                // Determine status class and icon
                let statusClass = '';
                let statusIcon = '';
                if (item.status === 'Approved') {
                    statusClass = 'status-approved';
                    statusIcon = '<i class="fas fa-check-circle"></i>';
                } else if (item.status === 'Pending') {
                    statusClass = 'status-pending';
                    statusIcon = '<i class="fas fa-clock"></i>';
                } else if (item.status === 'Rejected') {
                    statusClass = 'status-rejected';
                    statusIcon = '<i class="fas fa-times-circle"></i>';
                }

                // Create avatar with initials
                const avatarColor = getAvatarColor(item.avatarInitials);

                row.innerHTML = `
                    <td>
                        <div class="employee-cell">
                            <div class="employee-avatar" style="background: ${avatarColor}">
                                ${item.avatarInitials}
                            </div>
                            <div class="employee-info">
                                <div class="employee-name">${item.employeeName}</div>
                                <div class="employee-id">ID: ${String(item.id).padStart(4, '0')}</div>
                            </div>
                        </div>
                    </td>
                    <td class="mobile-hidden">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-building text-gray-400"></i>
                            ${item.department}
                        </div>
                    </td>
                    <td>
                        <div class="flex flex-col gap-1">
                            <span class="font-semibold">${item.leaveDate}</span>
                            <span class="text-xs text-gray-500">Submitted: ${item.dateSubmitted}</span>
                        </div>
                    </td>
                    <td class="mobile-hidden">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-tag text-gray-400"></i>
                            ${item.leaveType}
                        </div>
                    </td>
                    <td>
                        <span class="${statusClass} status-badge hover-lift">
                            <span class="status-icon">${statusIcon}</span>
                            <span>${item.status}</span>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn view view-leave-btn" data-leave-id="${item.id}">
                                <i class="fas fa-eye"></i>
                                <span class="mobile-hidden">View</span>
                            </button>
                            ${item.status === 'Pending' ? `
                                <button class="action-btn approve approve-leave-btn" data-leave-id="${item.id}">
                                    <i class="fas fa-check"></i>
                                    <span class="mobile-hidden">Approve</span>
                                </button>
                                <button class="action-btn reject reject-leave-btn" data-leave-id="${item.id}">
                                    <i class="fas fa-times"></i>
                                    <span class="mobile-hidden">Reject</span>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                `;

                tableBody.appendChild(row);
            });

            // Add event listeners
            document.querySelectorAll('.view-leave-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const leaveId = parseInt(this.getAttribute('data-leave-id'));
                    const data = leaveData.find(item => item.id === leaveId);
                    if (data) {
                        openModal(data);
                    }
                });
            });

            document.querySelectorAll('.approve-leave-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const leaveId = parseInt(this.getAttribute('data-leave-id'));
                    approveLeave(leaveId);
                });
            });

            document.querySelectorAll('.reject-leave-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const leaveId = parseInt(this.getAttribute('data-leave-id'));
                    rejectLeave(leaveId);
                });
            });
        }

        function approveLeave(id) {
            if (confirm('Are you sure you want to approve this leave request?')) {
                // Find and update the leave request
                const leaveIndex = leaveData.findIndex(item => item.id === id);
                if (leaveIndex !== -1) {
                    leaveData[leaveIndex].status = 'Approved';
                    leaveData[leaveIndex].comments = 'Leave approved on ' + new Date().toLocaleDateString();

                    // Refresh table
                    handleSearch();
                    showToast('Leave request approved successfully', 'success');
                }
            }
        }

        function rejectLeave(id) {
            if (confirm('Are you sure you want to reject this leave request?')) {
                // Find and update the leave request
                const leaveIndex = leaveData.findIndex(item => item.id === id);
                if (leaveIndex !== -1) {
                    leaveData[leaveIndex].status = 'Rejected';
                    leaveData[leaveIndex].comments = 'Leave rejected on ' + new Date().toLocaleDateString() + '. Please contact HR for more information.';

                    // Refresh table
                    handleSearch();
                    showToast('Leave request rejected', 'warning');
                }
            }
        }

        function updatePaginationInfo() {
            const startIndex = (currentPage - 1) * itemsPerPage + 1;
            const endIndex = Math.min(currentPage * itemsPerPage, filteredData.length);
            const total = filteredData.length;

            document.getElementById('pagination-info').innerHTML =
                `Showing <strong>${startIndex}</strong> to <strong>${endIndex}</strong> of <strong>${total}</strong> entries`;
        }

        function renderPaginationControls() {
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
            const paginationControls = document.getElementById('pagination-controls');

            if (totalPages <= 1) {
                paginationControls.innerHTML = '';
                return;
            }

            let html = '';

            // Previous button
            if (currentPage > 1) {
                html += `<button class="pagination-btn hover-lift" data-page="${currentPage - 1}">
                    <i class="fas fa-chevron-left"></i>
                    <span class="mobile-hidden">Previous</span>
                </button>`;
            } else {
                html += `<button class="pagination-btn" disabled>
                    <i class="fas fa-chevron-left"></i>
                    <span class="mobile-hidden">Previous</span>
                </button>`;
            }

            // Page numbers
            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            if (startPage > 1) {
                html += `<button class="pagination-btn hover-lift" data-page="1">1</button>`;
                if (startPage > 2) {
                    html += `<span class="page-ellipsis">...</span>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += `<button class="pagination-btn active hover-lift" data-page="${i}">${i}</button>`;
                } else {
                    html += `<button class="pagination-btn hover-lift" data-page="${i}">${i}</button>`;
                }
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="page-ellipsis">...</span>`;
                }
                html += `<button class="pagination-btn hover-lift" data-page="${totalPages}">${totalPages}</button>`;
            }

            // Next button
            if (currentPage < totalPages) {
                html += `<button class="pagination-btn hover-lift" data-page="${currentPage + 1}">
                    <span class="mobile-hidden">Next</span>
                    <i class="fas fa-chevron-right"></i>
                </button>`;
            } else {
                html += `<button class="pagination-btn" disabled>
                    <span class="mobile-hidden">Next</span>
                    <i class="fas fa-chevron-right"></i>
                </button>`;
            }

            paginationControls.innerHTML = html;

            // Add event listeners to pagination buttons
            document.querySelectorAll('.pagination-btn:not(:disabled):not(.page-ellipsis)').forEach(button => {
                button.addEventListener('click', function() {
                    const page = parseInt(this.getAttribute('data-page'));
                    currentPage = page;
                    renderTable();
                    updatePaginationInfo();
                    renderPaginationControls();

                    // Smooth scroll to table
                    document.querySelector('.table-wrapper').scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                });
            });
        }

        function getAvatarColor(initials) {
            const colors = [
                'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)',
                'linear-gradient(135deg, #10b981 0%, #047857 100%)',
                'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                'linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%)',
                'linear-gradient(135deg, #ec4899 0%, #db2777 100%)',
                'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)'
            ];

            // Simple hash function to get consistent color for same initials
            let hash = 0;
            for (let i = 0; i < initials.length; i++) {
                hash = initials.charCodeAt(i) + ((hash << 5) - hash);
            }

            return colors[Math.abs(hash) % colors.length];
        }

        function showLoading(show) {
            const loadingState = document.getElementById('loading-state');
            const tableBody = document.getElementById('leave-table-body');

            if (show) {
                loadingState.classList.remove('hidden');
                tableBody.classList.add('hidden');
            } else {
                loadingState.classList.add('hidden');
                tableBody.classList.remove('hidden');
            }
        }

        function showToast(message, type = 'success') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg flex items-center gap-3 animate-fade-in ${type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'} text-white font-medium`;
            toast.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
                <span>${message}</span>
            `;

            document.body.appendChild(toast);

            // Remove after 4 seconds
            setTimeout(() => {
                toast.classList.add('animate-fade-out');
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 4000);
        }

        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fade-in {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @keyframes fade-out {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(10px); }
            }
            
            .animate-fade-in {
                animation: fade-in 0.3s ease forwards;
            }
            
            .animate-fade-out {
                animation: fade-out 0.3s ease forwards;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>