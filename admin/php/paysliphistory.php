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
  <title>Payslip History - HRMS</title>
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
            background: linear-gradient(135deg, #193aa4 0%, #1c3c92 100%);
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
      --gradient-nav: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
      --gradient-primary: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
      --gradient-success: linear-gradient(135deg, #10b981 0%, #34d399 100%);
      --gradient-danger: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
      --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
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
      background:  linear-gradient(135deg, #223e9b 0%, #1e3a8a 100%);
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

    /* Logo and Brand - FIXED DUPLICATION */
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
      margin-top: -15px;
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

    /* Main Content Styles */
    .tab-container {
      background-color: white;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      overflow: hidden;
      margin-bottom: 24px;
      border: 1px solid #e5e7eb;
    }

    .tab-header {
      display: flex;
      border-bottom: 1px solid #e5e7eb;
      background-color: #f9fafb;
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

    .stat-card.earnings::before {
      background: var(--gradient-success);
    }

    .stat-card.deductions::before {
      background: var(--gradient-danger);
    }

    .stat-card.net-pay::before {
      background: var(--gradient-primary);
    }

    .stat-card.count::before {
      background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
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

    .stat-card.earnings .stat-value {
      background: var(--gradient-success);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .stat-card.deductions .stat-value {
      background: var(--gradient-danger);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .stat-card.net-pay .stat-value {
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .stat-card.count .stat-value {
      background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
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

    .stat-card.earnings .stat-icon {
      background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
      color: var(--success-color);
    }

    .stat-card.deductions .stat-icon {
      background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
      color: var(--danger-color);
    }

    .stat-card.net-pay .stat-icon {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(30, 64, 175, 0.1) 100%);
      color: var(--primary-color);
    }

    .stat-card.count .stat-icon {
      background: linear-gradient(135deg, rgba(107, 114, 128, 0.1) 0%, rgba(75, 85, 99, 0.1) 100%);
      color: #6b7280;
    }

    .stat-icon i {
      font-size: 1.75rem;
    }

    /* Improved Card styles */
    .card {
      background: white;
      border-radius: 16px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
      padding: 28px;
      margin-bottom: 30px;
      overflow-x: auto;
      border: 1px solid #e5e7eb;
    }

    @media (max-width: 768px) {
      .card {
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

    /* Improved Search and filter styles */
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
      min-width: 200px;
    }

    .search-box input {
      width: 100%;
      padding: 12px 16px 12px 44px;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      font-size: 14px;
      transition: all 0.3s ease;
      background: white;
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

    .filter-dropdown {
      position: relative;
    }

    .dropdown-button {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 12px 16px;
      background-color: white;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
      width: 100%;
      color: #4b5563;
    }

    @media (min-width: 768px) {
      .dropdown-button {
        width: auto;
        min-width: 180px;
      }
    }

    .dropdown-button:hover {
      background-color: #f9fafb;
      border-color: #d1d5db;
    }

    .dropdown-content {
      position: absolute;
      top: 100%;
      right: 0;
      z-index: 50;
      margin-top: 8px;
      background-color: white;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
      min-width: 220px;
      padding: 8px;
      display: none;
      border: 1px solid #e5e7eb;
    }

    .dropdown-content.show {
      display: block;
      animation: fadeIn 0.2s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .dropdown-item {
      display: block;
      width: 100%;
      padding: 10px 14px;
      text-align: left;
      background: none;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s;
      color: #4b5563;
      font-weight: 500;
      font-size: 14px;
    }

    .dropdown-item:hover {
      background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
      color: var(--primary-color);
    }

    .dropdown-item.active {
      background: var(--gradient-primary);
      color: white;
    }

    /* IMPROVED Table styles */
    .table-container {
      overflow-x: auto;
      border-radius: 12px;
      border: 1px solid #e5e7eb;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      min-width: 800px;
    }

    @media (max-width: 768px) {
      table {
        min-width: 100%;
      }
    }

    thead {
      background: var(--gradient-primary);
    }

    th {
      padding: 16px 20px;
      text-align: left;
      font-weight: 600;
      color: white;
      white-space: nowrap;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: none;
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
    }

    tbody tr:hover {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    td {
      padding: 18px 20px;
      color: var(--dark-text);
      font-weight: 500;
      border-bottom: 1px solid #f3f4f6;
    }

    td:first-child {
      font-weight: 600;
      color: var(--primary-color);
    }

    .action-buttons {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    @media (max-width: 480px) {
      .action-buttons {
        flex-direction: column;
      }
    }

    .action-btn {
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 6px;
      border: none;
      white-space: nowrap;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .view-btn {
      background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
      color: white;
    }

    .view-btn:hover {
      background: linear-gradient(135deg, var(--primary-color) 0%, #1e3a8a 100%);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
    }

    .delete-btn {
      background: linear-gradient(135deg, #f87171 0%, var(--danger-color) 100%);
      color: white;
    }

    .delete-btn:hover {
      background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    /* Currency styling */
    .currency {
      font-weight: 700;
      color: #059669;
    }

    .currency.deduction {
      color: var(--danger-color);
    }

    /* Payslip Modal Styles */
    .payslip-modal {
      max-height: 90vh;
      overflow-y: auto;
      border-radius: 20px;
    }

    .payslip-modal-content {
      background-color: white;
      padding: 30px;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
      position: relative;
    }

    .close-modal {
      position: absolute;
      top: 20px;
      right: 20px;
      background: white;
      border: 2px solid #e5e7eb;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      cursor: pointer;
      color: #555;
      z-index: 10;
      transition: all 0.3s ease;
    }

    .close-modal:hover {
      background: var(--danger-color);
      color: white;
      border-color: var(--danger-color);
      transform: rotate(90deg);
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

      th, td {
        padding: 12px 16px;
        font-size: 14px;
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
      
      .card {
        padding: 16px;
      }
      
      .card-title {
        font-size: 1.3rem;
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

    /* Utility classes */
    .hidden {
      display: none !important;
    }

    .flex {
      display: flex !important;
    }

    .block {
      display: block !important;
    }

    /* Override Flowbite modal styles for better mobile experience */
    [data-modal] {
      align-items: flex-start !important;
      padding-top: 80px !important;
      padding-bottom: 20px !important;
    }

    @media (max-width: 768px) {
      [data-modal] {
        padding-left: 10px !important;
        padding-right: 10px !important;
        padding-top: 60px !important;
      }

      [data-modal] .relative {
        width: 100% !important;
        margin: 0 !important;
      }
    }

    /* Status badges */
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .status-paid {
      background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.15) 100%);
      color: var(--success-color);
    }

    .status-pending {
      background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(217, 119, 6, 0.15) 100%);
      color: var(--warning-color);
    }

    /* Loading animation */
    @keyframes shimmer {
      0% { background-position: -200px 0; }
      100% { background-position: 200px 0; }
    }

    .loading-shimmer {
      background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
      background-size: 200px 100%;
      animation: shimmer 1.5s infinite;
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

        <!-- Logo and Brand (Desktop) - FIXED: Removed duplicate mobile brand -->
        <a href="../dashboard.php" class="navbar-brand">
          <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
          <div class="brand-text mobile-hidden">
            <span class="brand-title">HR Management System</span>
            <span class="brand-subtitle">Paluan Occidental Mindoro</span>
          </div>
        </a>

        <!-- Mobile Brand Text - Only shows on mobile -->
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


          <!-- Reports -->
          <li>
            <a href="paysliplist.php" class="sidebar-item active">
              <i class="fas fa-file-alt"></i>
              <span>Reports</span>
              <span class="badge">4</span>
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
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-800 mb-2">Payslip History</h1>
      <p class="text-gray-600">View and manage all employee salary slips and payment records</p>
    </div>
    
    <div class="tab-container">
      <div class="tab-header">
        <button class="tab-button active" id="payslip-tab">
          <i class="fas fa-file-invoice-dollar mr-2"></i> Payslip History
        </button>
        <a href="./employeeattendancehistory.php">
          <button class="tab-button">
            <i class="fas fa-calendar-alt mr-2"></i> Attendance History
          </button>
        </a>
       
      </div>
    </div>
    
    <!-- IMPROVED Stats Cards -->
    <div class="stats-container">
      <div class="stat-card earnings">
        <div class="stat-icon">
          <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-value">₱79,492.00</div>
        <div class="stat-label">Total Earnings</div>
        <div class="text-xs text-gray-500 mt-2">↑ 12.5% from last month</div>
      </div>
      <div class="stat-card deductions">
        <div class="stat-icon">
          <i class="fas fa-minus-circle"></i>
        </div>
        <div class="stat-value">₱11,581.58</div>
        <div class="stat-label">Total Deductions</div>
        <div class="text-xs text-gray-500 mt-2">↓ 3.2% from last month</div>
      </div>
      <div class="stat-card net-pay">
        <div class="stat-icon">
          <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-value">₱67,910.42</div>
        <div class="stat-label">Total Net Pay</div>
        <div class="text-xs text-gray-500 mt-2">↑ 15.8% from last month</div>
      </div>
      <div class="stat-card count">
        <div class="stat-icon">
          <i class="fas fa-file-invoice"></i>
        </div>
        <div class="stat-value">4</div>
        <div class="stat-label">Total Payslips</div>
        <div class="text-xs text-gray-500 mt-2">2 pending, 4 processed</div>
      </div>
    </div>
    
    <!-- IMPROVED Table Card -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">
          <i class="fas fa-file-invoice-dollar mr-3 text-blue-600"></i> Salary Slips
        </h2>
        <div class="search-filter-container">
          <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="search" placeholder="Search employee name, ID, or position...">
          </div>
          <div class="filter-dropdown">
            <button class="dropdown-button" id="filterDropdownButton">
              <i class="fas fa-filter"></i>
              <span id="filter-text">All Months</span>
              <i class="fas fa-chevron-down ml-2"></i>
            </button>
            <div class="dropdown-content" id="dropdownFilter">
              <button class="dropdown-item filter-option active" data-month="All">
                <i class="fas fa-layer-group mr-2"></i> All Months
              </button>
              <button class="dropdown-item filter-option" data-month="January 2024">
                <i class="fas fa-calendar-alt mr-2"></i> January 2024
              </button>
              <button class="dropdown-item filter-option" data-month="February 2024">
                <i class="fas fa-calendar-alt mr-2"></i> February 2024
              </button>
              <button class="dropdown-item filter-option" data-month="March 2024">
                <i class="fas fa-calendar-alt mr-2"></i> March 2024
              </button>
              <button class="dropdown-item filter-option" data-month="September 2025">
                <i class="fas fa-calendar-alt mr-2"></i> September 2025
              </button>
            </div>
          </div>
          <button class="action-btn view-btn" onclick="exportToExcel()">
            <i class="fas fa-file-export"></i> Export
          </button>
        </div>
      </div>
      
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>EMP CODE</th>
              <th>NAME</th>
              <th>SALARY MONTH</th>
              <th>EARNINGS</th>
              <th>DEDUCTIONS</th>
              <th>NET SALARY</th>
              <th class="text-center">STATUS</th>
              <th class="text-center">ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <tr class="border-b dark:border-gray-700" data-month="September 2025">
              <td class="font-semibold text-blue-700">P-001</td>
              <td class="font-medium">VILLAROZA, VEXTER D.</td>
              <td>
                <div class="flex flex-col">
                  <span class="font-semibold">September 2025</span>
                  <span class="text-xs text-gray-500">Issued: 10/05/2025</span>
                </div>
              </td>
              <td class="font-bold text-green-600">₱20,492.00</td>
              <td class="font-bold text-red-600">₱1,981.58</td>
              <td class="font-bold text-blue-700">₱18,510.42</td>
              <td class="text-center">
                <span class="status-badge status-paid">Paid</span>
              </td>
              <td class="action-buttons">
                <button type="button" class="action-btn view-btn view-payslip-btn" data-payslip-id="101">
                  <i class="fas fa-eye"></i> View
                </button>
                <button type="button" class="action-btn delete-btn" data-modal-target="deleteConfirmationModal" data-modal-toggle="deleteConfirmationModal" data-payslip-id="101">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </td>
            </tr>
            <tr class="border-b dark:border-gray-700" data-month="February 2024">
              <td class="font-semibold text-blue-700">C-015</td>
              <td class="font-medium">Jane Smith</td>
              <td>
                <div class="flex flex-col">
                  <span class="font-semibold">February 2024</span>
                  <span class="text-xs text-gray-500">Issued: 03/01/2024</span>
                </div>
              </td>
              <td class="font-bold text-green-600">₱18,000.00</td>
              <td class="font-bold text-red-600">₱3,500.00</td>
              <td class="font-bold text-blue-700">₱14,500.00</td>
              <td class="text-center">
                <span class="status-badge status-paid">Paid</span>
              </td>
              <td class="action-buttons">
                <button type="button" class="action-btn view-btn view-payslip-btn" data-payslip-id="102">
                  <i class="fas fa-eye"></i> View
                </button>
                <button type="button" class="action-btn delete-btn" data-modal-target="deleteConfirmationModal" data-modal-toggle="deleteConfirmationModal" data-payslip-id="102">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </td>
            </tr>
            <tr class="border-b dark:border-gray-700" data-month="January 2024">
              <td class="font-semibold text-blue-700">J-005</td>
              <td class="font-medium">Robert Brown</td>
              <td>
                <div class="flex flex-col">
                  <span class="font-semibold">January 2024</span>
                  <span class="text-xs text-gray-500">Issued: 02/01/2024</span>
                </div>
              </td>
              <td class="font-bold text-green-600">₱15,000.00</td>
              <td class="font-bold text-red-600">₱1,000.00</td>
              <td class="font-bold text-blue-700">₱14,000.00</td>
              <td class="text-center">
                <span class="status-badge status-paid">Paid</span>
              </td>
              <td class="action-buttons">
                <button type="button" class="action-btn view-btn view-payslip-btn" data-payslip-id="103">
                  <i class="fas fa-eye"></i> View
                </button>
                <button type="button" class="action-btn delete-btn" data-modal-target="deleteConfirmationModal" data-modal-toggle="deleteConfirmationModal" data-payslip-id="103">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </td>
            </tr>
            <tr class="border-b dark:border-gray-700" data-month="March 2024">
              <td class="font-semibold text-blue-700">P-002</td>
              <td class="font-medium">Alice Johnson</td>
              <td>
                <div class="flex flex-col">
                  <span class="font-semibold">March 2024</span>
                  <span class="text-xs text-gray-500">Issued: 04/01/2024</span>
                </div>
              </td>
              <td class="font-bold text-green-600">₱26,000.00</td>
              <td class="font-bold text-red-600">₱5,100.00</td>
              <td class="font-bold text-blue-700">₱20,900.00</td>
              <td class="text-center">
                <span class="status-badge status-pending">Pending</span>
              </td>
              <td class="action-buttons">
                <button type="button" class="action-btn view-btn view-payslip-btn" data-payslip-id="104">
                  <i class="fas fa-eye"></i> View
                </button>
                <button type="button" class="action-btn delete-btn" data-modal-target="deleteConfirmationModal" data-modal-toggle="deleteConfirmationModal" data-payslip-id="104">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Table Footer -->
      <div class="flex flex-col sm:flex-row justify-between items-center mt-6 pt-6 border-t border-gray-200">
        <div class="text-sm text-gray-600 mb-4 sm:mb-0">
          Showing <span class="font-semibold">4</span> out of <span class="font-semibold">4</span> records
        </div>
        <div class="flex items-center space-x-2">
          <button class="px-3 py-2 rounded-lg border border-gray-300 text-sm font-medium hover:bg-gray-50">
            <i class="fas fa-chevron-left"></i>
          </button>
          <button class="px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
            1
          </button>
          <button class="px-3 py-2 rounded-lg border border-gray-300 text-sm font-medium hover:bg-gray-50">
            2
          </button>
          <button class="px-3 py-2 rounded-lg border border-gray-300 text-sm font-medium hover:bg-gray-50">
            3
          </button>
          <button class="px-3 py-2 rounded-lg border border-gray-300 text-sm font-medium hover:bg-gray-50">
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </main>

  <!-- Delete Confirmation Modal -->
  <div id="deleteConfirmationModal" tabindex="-1" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-md max-h-full">
      <div class="relative bg-white rounded-2xl shadow-lg dark:bg-gray-800 border border-gray-200">
        <button type="button" class="absolute top-4 end-4 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white" data-modal-hide="deleteConfirmationModal">
          <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
          </svg>
          <span class="sr-only">Close modal</span>
        </button>
        <div class="p-6 text-center">
          <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
          </div>
          <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-300">Delete Salary Slip</h3>
          <p class="mb-6 text-gray-500 dark:text-gray-400">
            Are you sure you want to delete this salary slip? This action cannot be undone and all data will be permanently removed.
          </p>
          <div class="flex justify-center space-x-4">
            <button data-modal-hide="deleteConfirmationModal" type="button" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:outline-none focus:ring-gray-200">
              Cancel
            </button>
            <button id="confirmDeleteBtn" type="button" class="px-6 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 rounded-lg hover:from-red-700 hover:to-red-800 focus:ring-4 focus:outline-none focus:ring-red-300 shadow-lg">
              Yes, Delete It
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Payslip Modal -->
  <div id="payslipModal" tabindex="-1" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full bg-gray-900 bg-opacity-50 backdrop-blur-sm">
    <div class="relative p-4 w-full max-w-5xl max-h-full m-auto">
      <div class="relative bg-white rounded-2xl shadow-2xl dark:bg-gray-800 payslip-modal border border-gray-200">
        <div class="payslip-modal-content">
          <button type="button" class="close-modal" data-modal-hide="payslipModal">
            <i class="fas fa-times"></i>
          </button>
          
          <div class="payslip-container">
            <!-- Payslip Header -->
            <div class="payslip-header">
              <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Paluan Logo">
              <div class="payslip-header-text">
                <h3 class="text-lg font-bold text-gray-800">Republic of the Philippines</h3>
                <h4 class="text-md font-semibold text-gray-700">PROVINCE OF OCCIDENTAL MINDORO</h4>
                <h4 class="text-md font-semibold text-blue-600">Municipality of Paluan</h4>
                <p class="text-sm text-gray-600 mt-2">Official Salary Statement</p>
              </div>
              <div class="text-right">
                <div class="status-badge status-paid text-lg px-4 py-2">PAID</div>
                <p class="text-sm text-gray-600 mt-2">Issued: October 5, 2025</p>
              </div>
            </div>

            <!-- Employee Information -->
            <div class="section-title">
              <i class="fas fa-user-circle mr-2"></i> EMPLOYEE INFORMATION
            </div>
            <div class="section">
              <div class="form-row">
                <div class="form-field form-half">
                  <label for="name"><i class="fas fa-user mr-1"></i> Full Name</label>
                  <input type="text" id="name" name="name" value="VILLAROZA, VEXTER D." readonly>
                </div>
                <div class="form-field form-half">
                  <label for="position"><i class="fas fa-briefcase mr-1"></i> Position</label>
                  <input type="text" id="position" name="position" value="Administrative Assistant I" readonly>
                </div>
              </div>

              <div class="form-row">
                <div class="form-field form-half">
                  <label for="idNumber"><i class="fas fa-id-card mr-1"></i> Employee ID</label>
                  <input type="text" id="idNumber" name="idNumber" value="P-001" readonly>
                </div>
                <div class="form-field form-half">
                  <label for="period"><i class="fas fa-calendar-alt mr-1"></i> Pay Period</label>
                  <input type="text" id="period" name="period" value="September 1 - 30, 2025" readonly>
                </div>
              </div>

              <div class="form-row">
                <div class="form-field form-half">
                  <label for="salaryGrade"><i class="fas fa-chart-line mr-1"></i> Salary Grade & Step</label>
                  <input type="text" id="salaryGrade" name="salaryGrade" value="Grade 7, Step 1" readonly>
                </div>
                <div class="form-field form-half">
                  <label for="payType"><i class="fas fa-money-bill-wave mr-1"></i> Pay Type</label>
                  <input type="text" id="payType" name="payType" value="Monthly" readonly>
                </div>
              </div>
            </div>

            <!-- Earnings -->
            <div class="section-title">
              <i class="fas fa-plus-circle mr-2"></i> EARNINGS
            </div>
            <div class="section">
              <table class="earnings-table">
                <tr>
                  <td>Basic Salary</td>
                  <td><input type="text" value="₱15,492.00" readonly class="text-right font-semibold"></td>
                </tr>
                <tr>
                  <td>Transportation Allowance</td>
                  <td><input type="text" value="₱1,000.00" readonly class="text-right"></td>
                </tr>
                <tr>
                  <td>Meal Allowance</td>
                  <td><input type="text" value="₱1,000.00" readonly class="text-right"></td>
                </tr>
                <tr>
                  <td>Performance Bonus</td>
                  <td><input type="text" value="₱3,000.00" readonly class="text-right"></td>
                </tr>
                <tr class="total-row">
                  <td style="background: var(--gradient-success); color: white; font-size: 1rem;">GROSS PAY</td>
                  <td style="background: var(--gradient-success); color: white;"><input type="text" value="₱20,492.00" readonly class="text-right font-bold text-lg"></td>
                </tr>
              </table>
            </div>

            <!-- Deductions -->
            <div class="section-title">
              <i class="fas fa-minus-circle mr-2"></i> DEDUCTIONS
            </div>
            <div class="section">
              <table class="deductions-table">
                <tr><td>Philhealth Contribution</td><td><input type="text" value="₱587.50" readonly class="text-right"></td></tr>
                <tr><td>GSIS Contribution</td><td><input type="text" value="₱1,394.28" readonly class="text-right"></td></tr>
                <tr><td>Pag-Ibig Contribution</td><td><input type="text" value="₱100.00" readonly class="text-right"></td></tr>
                <tr class="total-row">
                  <td style="background: var(--gradient-danger); color: white; font-size: 1rem;">TOTAL DEDUCTIONS</td>
                  <td style="background: var(--gradient-danger); color: white;"><input type="text" value="₱1,981.58" readonly class="text-right font-bold text-lg"></td>
                </tr>
              </table>
            </div>

            <!-- Net Pay -->
            <div class="net-pay">
              <h4 class="text-xl font-bold mb-2">NET PAYABLE AMOUNT</h4>
              <input type="text" value="₱18,510.42" readonly class="text-3xl font-extrabold text-blue-700">
              <p class="text-sm text-gray-600 mt-2">Eighteen Thousand Five Hundred Ten Pesos and 42/100 Only</p>
            </div>

            <!-- Approval Section -->
            <div class="flex flex-wrap justify-between items-center mt-8 pt-8 border-t border-gray-300">
              <div class="text-center mb-4 md:mb-0">
                <p class="font-semibold">_______________________</p>
                <p class="text-sm text-gray-600">Employee's Signature</p>
              </div>
              <div class="text-center mb-4 md:mb-0">
                <p class="font-semibold">_______________________</p>
                <p class="text-sm text-gray-600">HR Officer</p>
              </div>
              <div class="text-center">
                <p class="font-semibold">_______________________</p>
                <p class="text-sm text-gray-600">Municipal Treasurer</p>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="payslip-buttons">
              <button class="save-btn" onclick="saveForm()">
                <i class="fas fa-save"></i> Save PDF
              </button>
              <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Slip
              </button>
              <button class="edit-btn" onclick="editForm()">
                <i class="fas fa-edit"></i> Edit Details
              </button>
              <button class="action-btn view-btn" onclick="sharePayslip()">
                <i class="fas fa-share-alt"></i> Share
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
  
  <script>
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
          minute: '2-digit'
        });
        
        document.getElementById('current-date').textContent = date;
        document.getElementById('current-time').textContent = time;
      }
      
      updateDateTime();
      setInterval(updateDateTime, 60000); // Update every minute
      
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
      
      // View payslip buttons
      const viewButtons = document.querySelectorAll('.view-payslip-btn');
      const payslipModal = document.getElementById('payslipModal');
      const closeModal = document.querySelector('.close-modal');
      
      viewButtons.forEach(button => {
        button.addEventListener('click', function() {
          const payslipId = this.getAttribute('data-payslip-id');
          // In a real application, you would fetch the payslip data based on the ID
          // For now, we'll just show the modal with the static data
          payslipModal.classList.remove('hidden');
          payslipModal.style.display = 'flex';
          document.body.style.overflow = 'hidden';
        });
      });
      
      // Close payslip modal
      if (closeModal) {
        closeModal.addEventListener('click', function() {
          payslipModal.classList.add('hidden');
          payslipModal.style.display = 'none';
          document.body.style.overflow = 'auto';
        });
      }
      
      // Close modal when clicking outside
      payslipModal.addEventListener('click', function(event) {
        if (event.target === payslipModal) {
          payslipModal.classList.add('hidden');
          payslipModal.style.display = 'none';
          document.body.style.overflow = 'auto';
        }
      });
      
      // Toggle filter dropdown
      const filterDropdownButton = document.getElementById('filterDropdownButton');
      const dropdownFilter = document.getElementById('dropdownFilter');
      
      if (filterDropdownButton) {
        filterDropdownButton.addEventListener('click', function() {
          dropdownFilter.classList.toggle('show');
        });
      }
      
      // Close dropdown when clicking outside
      document.addEventListener('click', function(event) {
        if (filterDropdownButton && dropdownFilter && 
            !filterDropdownButton.contains(event.target) && 
            !dropdownFilter.contains(event.target)) {
          dropdownFilter.classList.remove('show');
        }
      });
      
      // Filter functionality
      const filterButtons = document.querySelectorAll('.filter-option');
      const tableRows = document.querySelectorAll('tbody tr');
      const filterText = document.getElementById('filter-text');
      
      filterButtons.forEach(button => {
        button.addEventListener('click', function() {
          const month = this.getAttribute('data-month');
          
          // Update active state
          filterButtons.forEach(btn => btn.classList.remove('active'));
          this.classList.add('active');
          
          // Update button text
          filterText.textContent = month === 'All' ? 'All Months' : month;
          
          // Filter rows
          tableRows.forEach(row => {
            if (month === 'All' || row.getAttribute('data-month') === month) {
              row.style.display = '';
            } else {
              row.style.display = 'none';
            }
          });
          
          // Close dropdown
          dropdownFilter.classList.remove('show');
        });
      });
      
      // Search functionality
      const searchInput = document.getElementById('search');
      
      if (searchInput) {
        searchInput.addEventListener('input', function() {
          const searchTerm = this.value.toLowerCase().trim();
          
          tableRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (searchTerm === '' || text.includes(searchTerm)) {
              row.style.display = '';
            } else {
              row.style.display = 'none';
            }
          });
        });
      }
      
      // Delete functionality
      const deleteButtons = document.querySelectorAll('.delete-btn');
      const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
      let payslipToDelete = null;
      
      deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
          payslipToDelete = this.getAttribute('data-payslip-id');
        });
      });
      
      if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
          if (payslipToDelete) {
            // In a real application, you would send a request to delete the payslip
            showNotification(`Payslip #${payslipToDelete} has been deleted successfully.`, 'success');
            // Close the modal
            const modalCloseBtn = document.querySelector('[data-modal-hide="deleteConfirmationModal"]');
            if (modalCloseBtn) modalCloseBtn.click();
            // Remove the row from the table
            const rowToDelete = document.querySelector(`[data-payslip-id="${payslipToDelete}"]`);
            if (rowToDelete) {
              const tableRow = rowToDelete.closest('tr');
              if (tableRow) {
                tableRow.style.opacity = '0.5';
                setTimeout(() => {
                  tableRow.remove();
                  updateStatsAfterDelete();
                }, 300);
              }
            }
            payslipToDelete = null;
          }
        });
      }
      
      // Handle window resize
      function handleResize() {
        if (window.innerWidth >= 768) {
          sidebarContainer.classList.remove('active');
          sidebarOverlay.classList.remove('active');
          document.body.style.overflow = 'auto';
        }
      }
      
      window.addEventListener('resize', handleResize);
      
      // Close sidebar when clicking on a link (for mobile)
      document.querySelectorAll('.sidebar-item, .sidebar-dropdown-item').forEach(item => {
        item.addEventListener('click', function() {
          if (window.innerWidth < 768) {
            document.getElementById('sidebar-container').classList.remove('active');
            document.getElementById('sidebar-overlay').classList.remove('active');
            document.body.style.overflow = 'auto';
          }
        });
      });
      
      // Initialize tooltips
      initTooltips();
    });
    
    // Placeholder functions for form actions
    function saveForm() {
      showNotification('Payslip saved as PDF successfully!', 'success');
    }
    
    function editForm() {
      showNotification('Edit mode activated! You can now modify the payslip details.', 'info');
    }
    
    function sharePayslip() {
      if (navigator.share) {
        navigator.share({
          title: 'Salary Slip - Paluan LGU',
          text: 'Check out my salary slip from Paluan LGU',
          url: window.location.href,
        })
        .then(() => showNotification('Payslip shared successfully!', 'success'))
        .catch(error => showNotification('Sharing failed: ' + error, 'error'));
      } else {
        showNotification('Web Share API not supported in your browser.', 'warning');
      }
    }
    
    function exportToExcel() {
      showNotification('Exporting data to Excel...', 'info');
      // In a real app, this would generate and download an Excel file
      setTimeout(() => {
        showNotification('Data exported successfully!', 'success');
      }, 1500);
    }
    
    function updateStatsAfterDelete() {
      // Update the total payslips count
      const countElement = document.querySelector('.stat-card.count .stat-value');
      if (countElement) {
        const currentCount = parseInt(countElement.textContent);
        if (currentCount > 0) {
          countElement.textContent = (currentCount - 1).toString();
        }
      }
    }
    
    function showNotification(message, type = 'info') {
      // Create notification element
      const notification = document.createElement('div');
      notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        type === 'warning' ? 'bg-yellow-500 text-white' :
        'bg-blue-500 text-white'
      }`;
      notification.innerHTML = `
        <div class="flex items-center">
          <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} mr-3"></i>
          <span>${message}</span>
        </div>
      `;
      
      document.body.appendChild(notification);
      
      // Animate in
      setTimeout(() => {
        notification.style.transform = 'translateX(0)';
      }, 10);
      
      // Remove after 3 seconds
      setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
          document.body.removeChild(notification);
        }, 300);
      }, 3000);
    }
    
    function initTooltips() {
      // Initialize any tooltips if needed
      const tooltipElements = document.querySelectorAll('[data-tooltip]');
      tooltipElements.forEach(el => {
        el.addEventListener('mouseenter', function() {
          const tooltip = document.createElement('div');
          tooltip.className = 'absolute z-50 px-2 py-1 text-xs text-white bg-gray-900 rounded shadow-lg';
          tooltip.textContent = this.getAttribute('data-tooltip');
          document.body.appendChild(tooltip);
          
          const rect = this.getBoundingClientRect();
          tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
          tooltip.style.left = (rect.left + (rect.width - tooltip.offsetWidth) / 2) + 'px';
          
          this._tooltip = tooltip;
        });
        
        el.addEventListener('mouseleave', function() {
          if (this._tooltip) {
            document.body.removeChild(this._tooltip);
            delete this._tooltip;
          }
        });
      });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
      // Close modals with ESC key
      if (e.key === 'Escape') {
        const modals = document.querySelectorAll('[data-modal]');
        modals.forEach(modal => {
          if (!modal.classList.contains('hidden')) {
            const closeBtn = modal.querySelector('[data-modal-hide]');
            if (closeBtn) closeBtn.click();
          }
        });
      }
      
      // Open search with Ctrl+K or Cmd+K
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.getElementById('search');
        if (searchInput) {
          searchInput.focus();
        }
      }
    });
  </script>
</body>
</html>