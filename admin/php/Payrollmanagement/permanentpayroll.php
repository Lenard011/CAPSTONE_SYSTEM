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
  <title>Permanent Payroll</title>
  <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
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
    /* Custom styles to handle the complex layout and responsive design */
    .payroll-container {
      font-family: Arial, sans-serif;
      border: 2px solid #000;
      max-width: 100%;
      margin: 20px auto;
      padding: 10px;
      font-size: 10px;
      background-color: white;
      overflow-x: auto;
    }

    /* Styling for all table cells for borders */
    .payroll-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 2200px;
    }

    .payroll-table th,
    .payroll-table td {
      border: 1px solid #000;
      padding: 3px 5px;
      line-height: 1.1;
      height: 20px;
      vertical-align: middle;
      text-align: center;
    }

    /* Headers styling */
    .payroll-table thead th {
      font-weight: bold;
      text-transform: uppercase;
      background-color: #f8f9fa;
    }

    /* Signature/Certification sections */
    .cert-box {
      border: 1px solid #000;
      padding: 5px;
      min-height: 80px;
    }

    .signature-line {
      border-bottom: 1px solid #000;
      margin: 20px 0 2px 0;
      height: 1px;
    }

    /* Mobile-specific styles */
    @media (max-width: 768px) {
      .payroll-container {
        font-size: 8px;
        padding: 5px;
        margin: 10px;
      }

      .payroll-table th,
      .payroll-table td {
        padding: 1px 2px;
      }

      .payroll-table {
        min-width: 2000px;
      }

      /* Hide less important columns on mobile */
      .mobile-hide {
        display: none !important;
      }
    }

    /* Desktop-specific styles */
    @media (min-width: 769px) {
      .payroll-container {
        font-size: 10px;
      }

      .payroll-table th,
      .payroll-table td {
        padding: 3px 5px;
      }
    }

    /* Print styles */
    @media print {
      .action-buttons,
      .breadcrumb-container,
      .mobile-info,
      nav,
      aside {
        display: none !important;
      }

      .payroll-container {
        border: 2px solid #000;
        margin: 0;
        padding: 10px;
        box-shadow: none;
        font-size: 9px;
        max-width: 100%;
        width: 100%;
      }

      body {
        background: none;
        margin: 0;
        padding: 10px;
      }

      main {
        margin: 0 !important;
        padding: 0;
      }

      .payroll-table {
        min-width: 100%;
      }

      .payroll-table th,
      .payroll-table td {
        padding: 2px 3px;
      }

      .cert-box {
        min-height: 60px;
      }
      
      /* Ensure proper spacing for print */
      .text-center {
        text-align: center !important;
      }
      
      .text-right {
        text-align: right !important;
      }
      
      .font-bold {
        font-weight: bold !important;
      }
    }

    /* Sticky header for better navigation */
    .payroll-table thead th {
      position: sticky;
      top: 0;
      background-color: #f8f9fa;
      z-index: 10;
    }

    /* Highlight important columns */
    .important-column {
      background-color: #e8f4fd;
    }

    /* Style for totals row */
    .totals-row {
      background-color: #f0f0f0;
      font-weight: bold;
    }

    /* Responsive breadcrumb */
    .breadcrumb-container {
      margin-left: 10px;
    }

    @media (max-width: 768px) {
      .breadcrumb-container {
        margin-left: 5px;
      }
      
      main {
        margin-left: 0 !important;
        margin-top: 120px !important;
      }
    }
    
    /* Improved table design */
    .department-header {
      background-color: #374151;
      color: white;
      font-weight: bold;
    }
    
    .currency-cell {
      font-family: 'Courier New', monospace;
      letter-spacing: 0.5px;
    }
    
    .employee-row:hover {
      background-color: #f9fafb;
    }
    
    .payroll-table thead tr:first-child th {
      background-color: #e5e7eb;
    }
    
    .payroll-table thead tr:nth-child(2) th {
      background-color: #f3f4f6;
    }
    
    .signature-cell {
      min-height: 30px;
      vertical-align: bottom;
    }
  </style>
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

    /* IMPROVED NAVBAR - Matches Image */
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
    .dropdown-menu {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease;
      margin-left: 2.5rem;
    }

    .dropdown-menu.open {
      max-height: 500px;
    }

    .dropdown-item {
      display: flex;
      align-items: center;
      padding: 0.5rem 1rem;
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      border-radius: 8px;
      margin-bottom: 0.25rem;
      transition: all 0.3s ease;
    }

    .dropdown-item:hover {
      background: rgba(255, 255, 255, 0.1);
      color: white;
      transform: translateX(5px);
    }

    .dropdown-item i {
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
        <a href="../dashboard.php" class="navbar-brand hidden md:flex">
          <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
          <div class="brand-text">
            <span class="brand-title">HR Management System</span>
            <span class="brand-subtitle">Paluan Occidental Mindoro</span>
          </div>
        </a>

        <!-- Logo and Brand (Mobile) -->
        <div class="mobile-brand md:hidden">
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
        <div class="datetime-container hidden md:flex">
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
            <a href="../dashboard.php" class="sidebar-item">
              <i class="fas fa-chart-line"></i>
              <span>Dashboard Analytics</span>
            </a>
          </li>

          <!-- Employees -->
          <li>
            <a href="./Employee.php" class="sidebar-item ">
              <i class="fas fa-users"></i>
              <span>Employees</span>
            </a>
          </li>

          <!-- Attendance -->
          <li>
            <a href="../attendance.php" class="sidebar-item">
              <i class="fas fa-calendar-check"></i>
              <span>Attendance</span>
            </a>
          </li>

          <!-- Payroll Dropdown -->
          <li>
            <a href="#" class="sidebar-item active" id="payroll-toggle">
              <i class="fas fa-money-bill-wave"></i>
              <span>Payroll</span>
              <i class="fas fa-chevron-down chevron text-xs ml-auto"></i>
            </a>
            <div class="dropdown-menu" id="payroll-dropdown">
              <a href="../Payrollmanagement/contractualpayrolltable1.php" class="dropdown-item">
                <i class="fas fa-circle text-xs"></i>
                Contractual
              </a>
              <a href="../Payrollmanagement/joboerderpayrolltable1.php" class="dropdown-item">
                <i class="fas fa-circle text-xs"></i>
                Job Order
              </a>
              <a href="../Payrollmanagement/permanentpayrolltable1.php" class="dropdown-item active">
                <i class="fas fa-circle text-xs"></i>
                Permanent
              </a>
            </div>
          </li>

       

          <!-- Reports -->
          <li>
            <a href="paysliplist.php" class="sidebar-item">
              <i class="fas fa-file-alt"></i>
              <span>Reports</span>
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


  <!-- MAIN -->
  <main style="margin-left: 270px;margin-top: 150px;" class="bg-gray-100">
    <div class="breadcrumb-container">
      <!-- Breadcrumb -->
      <nav class="mt-8 flex ml-5" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-2">
          <li class="inline-flex items-center">
            <a href="permanentpayrolltable1.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2 breadcrumb-item">
              <i class="fas fa-home mr-2"></i> Permanent Payroll
            </a>
          </li>
          <li>
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <a href="permanentpayroll.php"  class="ml-1 text-sm font-medium text-blue-700 hover:text-blue-600 md:ml-2 breadcrumb-item">General Payroll</a>
            </div>
          </li>
          <li>
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <a href="permanentobligationrequest.php" class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-blue-700 breadcrumb-item">Obligation Request</a>
            </div>
          </li>
        </ol>
      </nav>
    </div>
    
    <!-- Mobile info banner -->
    <div class="md:hidden bg-yellow-100 p-2 mb-2 text-xs rounded mobile-info">
      <i class="fas fa-info-circle mr-1"></i> Scroll horizontally to view all columns
    </div>
    
    <div class="payroll-container" id="payroll-content">
      <div class="text-center font-bold text-sm mb-1">
        <p>GENERAL PAYROLL</p>
        <p>Palma, Occidental Minidora</p>
        <p>PERIOD: SEPTEMBER 16-30, 2015</p>
      </div>

      <p class="text-[8px] mb-2">We acknowledge receipt of the sum opposite our names as full compensation for services rendered for the period stated.</p>

      <div class="overflow-x-auto relative">
        <table class="payroll-table text-[7px] md:text-[8px]">
          <thead>
            <tr>
              <th rowspan="2" class="w-[3%]">No.</th>
              <th rowspan="2" class="w-[12%]">Name</th>
              <th rowspan="2" class="w-[8%]">Position</th>
              <th rowspan="2" class="w-[6%]">Monthly Salary</th>
              <th rowspan="2" class="w-[6%]">Amount Accrued</th>

              <th colspan="12">DEDUCTIONS</th>

              <th colspan="4">ADDITIONAL</th>
              <th rowspan="2" class="w-[6%] important-column">Amount Due</th>
              <th rowspan="2" class="w-[3%]">No.</th>
              <th rowspan="2" class="w-[8%]">Signature of Payee</th>
            </tr>
            <tr>
              <th class="w-[4%]">Withholding Tax</th>
              <th class="w-[4%]">PAG-IBIG LOAN - MPL</th>
              <th class="w-[4%]">Corso Loan</th>
              <th class="w-[4%]">Policy Loan</th>
              <th class="w-[4%]">PhilHealth P.S.</th>
              <th class="w-[4%]">UEF / Retirement</th>
              <th class="w-[4%]">Emergency Loan</th>
              <th class="w-[4%]">GFAL</th>
              <th class="w-[4%]">LBP Loan</th>
              <th class="w-[4%]">MPL</th>
              <th class="w-[4%]">MPL Lite</th>
              <th class="w-[4%]">SSS Contribution</th>
              
              <th class="w-[3%]">PAG-IBIG CONT.</th>
              <th class="w-[3%]">STATE INS. G.S.</th>
              <th class="w-[3%]">Amount Due</th>
              <th class="w-[3%]">No.</th>
            </tr>
          </thead>
          <tbody>
            <!-- Office of the Mayor Section -->
            <tr class="department-header">
              <td colspan="22" class="text-left pl-2">OFFICE OF THE MAYOR</td>
            </tr>
            
            <tr class="employee-row" data-id="1">
              <td>1</td>
              <td class="text-left pl-1">JENNY E. ARCONADA</td>
              <td>MSWOO</td>
              <td class="text-right currency-cell">95,312.00</td>
              <td class="text-right currency-cell">47,925.50</td>

              <td class="text-right currency-cell">7,024.19</td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell">1,193.89</td>
              <td class="text-right currency-cell">1,193.89</td>
              <td class="text-right currency-cell">6,031.86</td>
              <td class="text-right currency-cell">4,817.22</td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell">9,249.14</td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell"></td>
              
              <td class="text-right currency-cell">100.00</td>
              <td class="text-right currency-cell">150.00</td>
              <td class="text-right currency-cell">23,414.00</td>
              <td class="text-right currency-cell">1</td>
              <td class="text-right currency-cell data-net-amount important-column">23,414.00</td>
              <td>1</td>
              <td class="signature-cell"></td>
            </tr>

            <tr class="employee-row" data-id="2">
              <td>2</td>
              <td class="text-left pl-1">J.FRANCE GUT, FEDRAZA</td>
              <td>WYD II</td>
              <td class="text-right currency-cell">64,649.00</td>
              <td class="text-right currency-cell">23,734.50</td>

              <td class="text-right currency-cell">2,025.00</td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell">593.11</td>
              <td class="text-right currency-cell">629.12</td>
              <td class="text-right currency-cell">3,793.93</td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell">3,156.75</td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell"></td>
              
              <td class="text-right currency-cell">250.00</td>
              <td class="text-right currency-cell">150.00</td>
              <td class="text-right currency-cell">13,549.85</td>
              <td class="text-right currency-cell">2</td>
              <td class="text-right currency-cell data-net-amount important-column">13,549.85</td>
              <td>2</td>
              <td class="signature-cell"></td>
            </tr>

            <!-- Office of the MAO Section -->
            <tr class="department-header">
              <td colspan="22" class="text-left pl-2">OFFICE OF THE MAO</td>
            </tr>
            
            <tr class="employee-row" data-id="9">
              <td>9</td>
              <td class="text-left pl-1">JAMES PATRICK T. FEDRAZA</td>
              <td>Mun. Agricultural #1</td>
              <td class="text-right currency-cell">88,367.00</td>
              <td class="text-right currency-cell">44,183.50</td>

              <td class="text-right currency-cell">6,833.89</td>
              <td class="text-right currency-cell">1,104.38</td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell">1,104.39</td>
              <td class="text-right currency-cell">3,976.52</td>
              <td class="text-right currency-cell">5,302.02</td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell">13,574.04</td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell"></td>
              
              <td class="text-right currency-cell">100.00</td>
              <td class="text-right currency-cell">50.00</td>
              <td class="text-right currency-cell">18,594.47</td>
              <td class="text-right currency-cell">9</td>
              <td class="text-right currency-cell data-net-amount important-column">18,594.47</td>
              <td>9</td>
              <td class="signature-cell"></td>
            </tr>

            <tr class="font-bold totals-row">
              <td colspan="4" class="text-right pr-2">TOTALS:</td>
              <td class="text-right currency-cell">274,959.00</td>
              <td class="text-right currency-cell">18,666.67</td>
              <td class="text-right currency-cell">1,537.51</td>
              <td class="text-right currency-cell">757.28</td>
              <td class="text-right currency-cell">500.00</td>
              <td class="text-right currency-cell">6,873.86</td>
              <td class="text-right currency-cell">6,874.05</td>
              <td class="text-right currency-cell">26,296.21</td>
              <td class="text-right currency-cell">13,873.59</td>
              <td class="text-right currency-cell">35,555.59</td>
              <td class="text-right currency-cell">18,748.81</td>
              <td class="text-right currency-cell">1,783.33</td>
              <td class="text-right currency-cell">375.00</td>
              <td class="text-right currency-cell">2,050.00</td>
              <td class="text-right currency-cell">1,150.00</td>
              <td class="text-right currency-cell">147,414.27</td>
              <td class="text-right currency-cell"></td>
              <td class="text-right currency-cell important-column">147,414.27</td>
              <td colspan="2" class="text-center">TOTALS VERIFIED</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Certification Section -->
      <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-[8px]">
        <div class="cert-box">
          <p class="font-bold">CERTIFIED:</p>
          <p>Services have been duly rendered as stated</p>
          <div class="signature-line"></div>
          <p class="text-center">JULIE B. VICENTE</p>
          <p class="text-center">Municipal Accountant</p>
        </div>
        <div class="cert-box">
          <p class="font-bold">CERTIFIED:</p>
          <p>Fund is available in the amount of P226,096.00</p>
          <div class="signature-line"></div>
          <p class="text-center">ABEINE A. DE VEAS</p>
          <p class="text-center">Municipal Treasurer</p>
        </div>
      </div>

      <div class="cert-box mt-2 text-[8px]">
        <p class="font-bold">APPROVED FOR PAYMENT</p>
        <div class="signature-line"></div>
        <p class="text-center">HON. MICHAEL D. DMZ</p>
        <p class="text-center">Municipal Mayor</p>
      </div>
    </div>

    <div class="action-buttons flex justify-center space-x-4 mt-6 mb-10">
      <button id="print-button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5">
        <svg class="w-4 h-4 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd"></path>
        </svg>
        Print Payroll
      </button>
      <button id="save-button" class="text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5">
        <svg class="w-4 h-4 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
          <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 9a1 1 0 011-1h10a1 1 0 011 1v7a1 1 0 01-1 1H5a1 1 0 01-1-1V9zM5 7a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2H5z"></path>
        </svg>
        Save Data
      </button>
      <a href="permanentpayroll.php">
        <button id="next-button" class="text-white bg-indigo-700 hover:bg-indigo-800 focus:ring-4 focus:ring-indigo-300 font-medium rounded-lg text-sm px-5 py-2.5">
          Next Payroll <svg class="w-4 h-4 inline-block ml-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </a>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.46.0/dist/apexcharts.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
</body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
<script>
  // ==================== DATE AND TIME FUNCTIONS ====================
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
    
    // Format time
    const timeOptions = { 
      hour: '2-digit', 
      minute: '2-digit', 
      second: '2-digit',
      hour12: true 
    };
    const formattedTime = now.toLocaleTimeString('en-US', timeOptions);
    
    // Update DOM elements
    const dateElement = document.getElementById('current-date');
    const timeElement = document.getElementById('current-time');
    
    if (dateElement) dateElement.textContent = formattedDate;
    if (timeElement) timeElement.textContent = formattedTime;
  }
  
  // Initialize date/time display and update every second
  document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Initialize sidebar and user menu
    initSidebar();
    initUserMenu();
  });
  
  // ==================== NAVBAR FUNCTIONALITY ====================
  function initUserMenu() {
    const userMenuButton = document.getElementById('user-menu-button');
    const userDropdown = document.getElementById('user-dropdown');
    
    if (userMenuButton && userDropdown) {
      userMenuButton.addEventListener('click', function(e) {
        e.stopPropagation();
        userDropdown.classList.toggle('active');
        userMenuButton.classList.toggle('active');
      });
      
      // Close dropdown when clicking outside
      document.addEventListener('click', function(e) {
        if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
          userDropdown.classList.remove('active');
          userMenuButton.classList.remove('active');
        }
      });
    }
  }
  
  function initSidebar() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarContainer = document.getElementById('sidebar-container');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    
    if (sidebarToggle && sidebarContainer && sidebarOverlay) {
      sidebarToggle.addEventListener('click', function() {
        sidebarContainer.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        document.body.style.overflow = 'hidden';
      });
      
      sidebarOverlay.addEventListener('click', function() {
        sidebarContainer.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
      });
      
      // Payroll dropdown in sidebar
      const payrollToggle = document.getElementById('payroll-toggle');
      const payrollDropdown = document.getElementById('payroll-dropdown');
      const payrollChevron = payrollToggle?.querySelector('.chevron');
      
      if (payrollToggle && payrollDropdown) {
        payrollToggle.addEventListener('click', function(e) {
          e.preventDefault();
          payrollDropdown.classList.toggle('open');
          if (payrollChevron) {
            payrollChevron.classList.toggle('rotated');
          }
        });
      }
    }
  }

  // ==================== PRINT FUNCTIONALITY ====================
  document.getElementById('print-button').addEventListener('click', function() {
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    
    // Get the payroll content
    const payrollContent = document.getElementById('payroll-content').innerHTML;
    
    // Create the print document - simplified to match the image
    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>Permanent Payroll - Print</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
          @page {
            size: legal landscape;
            margin: 0.5cm;
          }
          body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 9px;
            width: 100%;
          }
          .payroll-container {
            border: 2px solid #000;
            padding: 10px;
            background-color: white;
            max-width: 100%;
            overflow: visible;
          }
          .payroll-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
          }
          .payroll-table th, .payroll-table td {
            border: 1px solid #000;
            padding: 2px 3px;
            text-align: center;
            font-size: 8px;
            line-height: 1.1;
            height: 18px;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
          }
          .payroll-table thead th {
            font-weight: bold;
            text-transform: uppercase;
            background-color: #f8f9fa;
          }
          .payroll-table thead tr:first-child th {
            background-color: #e5e7eb;
          }
          .payroll-table thead tr:nth-child(2) th {
            background-color: #f3f4f6;
          }
          .cert-box {
            border: 1px solid #000;
            padding: 5px;
            min-height: 60px;
            font-size: 8px;
          }
          .signature-line {
            border-bottom: 1px solid #000;
            margin: 15px 0 2px 0;
            height: 1px;
          }
          .important-column {
            background-color: #e8f4fd;
          }
          .totals-row {
            background-color: #f0f0f0;
            font-weight: bold;
          }
          .department-header {
            background-color: #374151;
            color: white;
            font-weight: bold;
          }
          .text-center {
            text-align: center;
          }
          .text-right {
            text-align: right;
          }
          .text-left {
            text-align: left;
          }
          .currency-cell {
            font-family: 'Courier New', monospace;
            letter-spacing: 0.5px;
          }
          /* Column widths matching original */
          .col-1 { width: 3%; }
          .col-2 { width: 12%; }
          .col-3 { width: 8%; }
          .col-4 { width: 6%; }
          .col-5 { width: 4%; }
          .col-6 { width: 3%; }
          
          /* Ensure table fits on one page */
          .payroll-table {
            page-break-inside: avoid;
          }
          
          /* Header styling for print */
          .print-header {
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 5px;
          }
        </style>
      </head>
      <body>
        <div class="payroll-container">
          <div class="print-header">
            <p>GENERAL PAYROLL</p>
            <p>Palma, Occidental Minidora</p>
            <p>PERIOD: SEPTEMBER 16-30, 2015</p>
          </div>
          
          <p style="font-size: 8px; margin-bottom: 5px;">We acknowledge receipt of the sum opposite our names as full compensation for services rendered for the period stated.</p>
          
          <table class="payroll-table">
            <thead>
              <tr>
                <th rowspan="2" class="col-1">No.</th>
                <th rowspan="2" class="col-2">Name</th>
                <th rowspan="2" class="col-3">Position</th>
                <th rowspan="2" class="col-4">Monthly Salary</th>
                <th rowspan="2" class="col-4">Amount Accrued</th>

                <th colspan="12">DEDUCTIONS</th>

                <th colspan="4">ADDITIONAL</th>
                <th rowspan="2" class="col-4 important-column">Amount Due</th>
                <th rowspan="2" class="col-1">No.</th>
                <th rowspan="2" class="col-2">Signature of Payee</th>
              </tr>
              <tr>
                <th class="col-5">Withholding Tax</th>
                <th class="col-5">PAG-IBIG LOAN - MPL</th>
                <th class="col-5">Corso Loan</th>
                <th class="col-5">Policy Loan</th>
                <th class="col-5">PhilHealth P.S.</th>
                <th class="col-5">UEF / Retirement</th>
                <th class="col-5">Emergency Loan</th>
                <th class="col-5">GFAL</th>
                <th class="col-5">LBP Loan</th>
                <th class="col-5">MPL</th>
                <th class="col-5">MPL Lite</th>
                <th class="col-5">SSS Contribution</th>
                
                <th class="col-6">PAG-IBIG CONT.</th>
                <th class="col-6">STATE INS. G.S.</th>
                <th class="col-6">Amount Due</th>
                <th class="col-6">No.</th>
              </tr>
            </thead>
            <tbody>
              <!-- Office of the Mayor Section -->
              <tr class="department-header">
                <td colspan="22" class="text-left pl-2">OFFICE OF THE MAYOR</td>
              </tr>
              
              <tr>
                <td>1</td>
                <td class="text-left pl-1">JENNY E. ARCONADA</td>
                <td>MSWOO</td>
                <td class="text-right currency-cell">95,312.00</td>
                <td class="text-right currency-cell">47,925.50</td>

                <td class="text-right currency-cell">7,024.19</td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell">1,193.89</td>
                <td class="text-right currency-cell">1,193.89</td>
                <td class="text-right currency-cell">6,031.86</td>
                <td class="text-right currency-cell">4,817.22</td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell">9,249.14</td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell"></td>
                
                <td class="text-right currency-cell">100.00</td>
                <td class="text-right currency-cell">150.00</td>
                <td class="text-right currency-cell">23,414.00</td>
                <td class="text-right currency-cell">1</td>
                <td class="text-right currency-cell important-column">23,414.00</td>
                <td>1</td>
                <td class="text-center"></td>
              </tr>

              <tr>
                <td>2</td>
                <td class="text-left pl-1">J.FRANCE GUT, FEDRAZA</td>
                <td>WYD II</td>
                <td class="text-right currency-cell">64,649.00</td>
                <td class="text-right currency-cell">23,734.50</td>

                <td class="text-right currency-cell">2,025.00</td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell">593.11</td>
                <td class="text-right currency-cell">629.12</td>
                <td class="text-right currency-cell">3,793.93</td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell">3,156.75</td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell"></td>
                
                <td class="text-right currency-cell">250.00</td>
                <td class="text-right currency-cell">150.00</td>
                <td class="text-right currency-cell">13,549.85</td>
                <td class="text-right currency-cell">2</td>
                <td class="text-right currency-cell important-column">13,549.85</td>
                <td>2</td>
                <td class="text-center"></td>
              </tr>

              <!-- Office of the MAO Section -->
              <tr class="department-header">
                <td colspan="22" class="text-left pl-2">OFFICE OF THE MAO</td>
              </tr>
              
              <tr>
                <td>9</td>
                <td class="text-left pl-1">JAMES PATRICK T. FEDRAZA</td>
                <td>Mun. Agricultural #1</td>
                <td class="text-right currency-cell">88,367.00</td>
                <td class="text-right currency-cell">44,183.50</td>

                <td class="text-right currency-cell">6,833.89</td>
                <td class="text-right currency-cell">1,104.38</td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell">1,104.39</td>
                <td class="text-right currency-cell">3,976.52</td>
                <td class="text-right currency-cell">5,302.02</td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell">13,574.04</td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell"></td>
                
                <td class="text-right currency-cell">100.00</td>
                <td class="text-right currency-cell">50.00</td>
                <td class="text-right currency-cell">18,594.47</td>
                <td class="text-right currency-cell">9</td>
                <td class="text-right currency-cell important-column">18,594.47</td>
                <td>9</td>
                <td class="text-center"></td>
              </tr>

              <tr class="totals-row">
                <td colspan="4" class="text-right pr-2">TOTALS:</td>
                <td class="text-right currency-cell">274,959.00</td>
                <td class="text-right currency-cell">18,666.67</td>
                <td class="text-right currency-cell">1,537.51</td>
                <td class="text-right currency-cell">757.28</td>
                <td class="text-right currency-cell">500.00</td>
                <td class="text-right currency-cell">6,873.86</td>
                <td class="text-right currency-cell">6,874.05</td>
                <td class="text-right currency-cell">26,296.21</td>
                <td class="text-right currency-cell">13,873.59</td>
                <td class="text-right currency-cell">35,555.59</td>
                <td class="text-right currency-cell">18,748.81</td>
                <td class="text-right currency-cell">1,783.33</td>
                <td class="text-right currency-cell">375.00</td>
                <td class="text-right currency-cell">2,050.00</td>
                <td class="text-right currency-cell">1,150.00</td>
                <td class="text-right currency-cell">147,414.27</td>
                <td class="text-right currency-cell"></td>
                <td class="text-right currency-cell important-column">147,414.27</td>
                <td colspan="2" class="text-center">TOTALS VERIFIED</td>
              </tr>
            </tbody>
          </table>

          <div style="margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 8px;">
            <div class="cert-box">
              <p style="font-weight: bold;">CERTIFIED:</p>
              <p>Services have been duly rendered as stated</p>
              <div class="signature-line"></div>
              <p class="text-center">JULIE B. VICENTE</p>
              <p class="text-center">Municipal Accountant</p>
            </div>
            <div class="cert-box">
              <p style="font-weight: bold;">CERTIFIED:</p>
              <p>Fund is available in the amount of P226,096.00</p>
              <div class="signature-line"></div>
              <p class="text-center">ABEINE A. DE VEAS</p>
              <p class="text-center">Municipal Treasurer</p>
            </div>
          </div>

          <div class="cert-box" style="margin-top: 10px; font-size: 8px;">
            <p style="font-weight: bold;">APPROVED FOR PAYMENT</p>
            <div class="signature-line"></div>
            <p class="text-center">HON. MICHAEL D. DMZ</p>
            <p class="text-center">Municipal Mayor</p>
          </div>
        </div>
        
        <script>
          // Auto-print and close
          window.onload = function() {
            setTimeout(function() {
              window.print();
              setTimeout(function() {
                window.close();
              }, 100);
            }, 100);
          };
          
          // Also allow manual print
          window.onafterprint = function() {
            setTimeout(function() {
              window.close();
            }, 100);
          };
        <\/script>
      </body>
      </html>
    `);
    
    printWindow.document.close();
  });

  // ==================== OTHER BUTTON FUNCTIONALITY ====================
  document.getElementById('save-button').addEventListener('click', () => {
    alert('Saving General Payroll Data to database...');
  });

  document.getElementById('next-button').addEventListener('click', () => {
    alert('Moving to the next payroll sheet or period.');
  });
</script>

</html>