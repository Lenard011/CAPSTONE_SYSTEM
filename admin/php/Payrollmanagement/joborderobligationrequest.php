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
  <title>Job Order Payroll</title>
  <!-- Remove duplicate CSS imports -->
  <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>

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
    /* Custom styles to achieve the precise border and typography look */
    .form-container {
      font-family: Arial, sans-serif;
      border: 2px solid #000;
      max-width: 1000px;
      margin: 20px auto;
      padding: 10px;
      font-size: 12px;
      background-color: white;
    }

    /* Styling for all table cells (th and td) for borders */
    .form-table th,
    .form-table td {
      border: 1px solid #000;
      padding: 4px;
      line-height: 1.2;
      vertical-align: top;
    }

    .header-cell {
      background-color: #f0f0f0;
      font-weight: bold;
    }

    .signature-line {
      border-bottom: 1px solid #000;
      margin: 5px 0 2px 0;
      line-height: 1;
      min-width: 150px;
      display: inline-block;
    }

    .certification-box {
      border: 1px solid #000;
      padding: 8px;
      margin: 10px 0;
    }

    /* Print styles to hide non-form elements when printing */
    @media print {

      /* Hide navigation, sidebar, breadcrumbs, and action buttons */
      nav,
      aside,
      .breadcrumb-container,
      .action-buttons,
      .action-cell,
      .sidebar-overlay,
      .flowbite-modal {
        display: none !important;
      }

      /* Reset main content margins for printing */
      main {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
      }

      /* Ensure form container fits bond paper */
      .form-container {
        border: 2px solid #000;
        margin: 0 auto;
        padding: 10px;
        width: 100%;
        max-width: 100%;
        box-shadow: none;
        font-size: 12px;
        page-break-inside: avoid;
      }

      /* Show all columns in print */
      .mobile-hide {
        display: table-cell !important;
      }

      body {
        background: white;
        margin: 0;
        padding: 0;
        width: 100%;
        height: 100%;
        font-size: 12px;
      }

      /* Ensure table cells are visible */
      .form-table th,
      .form-table td {
        border: 1px solid #000;
        padding: 4px;
      }

      /* Optimize for bond paper size */
      @page {
        size: portrait;
        margin: 10mm;
      }

      /* Prevent elements from breaking across pages */
      .certification-box,
      .table-container {
        page-break-inside: avoid;
      }
    }

    /* Mobile responsive styles */
    @media (max-width: 768px) {
      .form-container {
        font-size: 10px;
        padding: 5px;
        margin: 10px;
        overflow-x: auto;
      }

      .form-table th,
      .form-table td {
        padding: 2px;
      }

      .signature-line {
        min-width: 100px;
      }

      /* Make the table horizontally scrollable */
      .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      /* Adjust main content for mobile */
      main {
        margin-top: 65px !important;
        margin-left: 0 !important;
        padding: 0 10px;
      }

      /* Adjust breadcrumb for mobile */
      .breadcrumb-container {
        margin-left: 0 !important;
        padding: 0 10px;
      }

      /* Make action buttons stack on mobile */
      .action-buttons {
        flex-direction: column;
        gap: 10px;
      }

      .action-buttons button {
        width: 100%;
      }

      /* Hide less important columns on mobile */
      .mobile-hide {
        display: none;
      }
    }

    /* Navbar and sidebar styles */
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
        margin-top: 70px;
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
        margin-top: 70px;
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

    .sidebar-dropdown-item active {
      background: rgba(255, 255, 255, 0.2);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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

    /* Breadcrumb */
    .breadcrumb-container {
      margin-bottom: 1rem;
    }

    /* Action Buttons */
    .action-buttons {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin-top: 1.5rem;
      margin-bottom: 2rem;
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
            <div class="sidebar-dropdown-menu" id="payroll-dropdown">
              <a href="../Payrollmanagement/contractualpayrolltable1.php" class="sidebar-dropdown-item">
                <i class="fas fa-circle text-xs"></i>
                Contractual
              </a>
              <a href="../Payrollmanagement/joboerderpayrolltable1.php" class="sidebar-dropdown-item active">
                <i class="fas fa-circle text-xs"></i>
                Job Order
              </a>
              <a href="../Payrollmanagement/permanentpayrolltable1.php" class="sidebar-dropdown-item">
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

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <div class="breadcrumb-container">
      <nav aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-2">
          <li class="inline-flex items-center">
            <a href="joboerderpayrolltable1.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2 breadcrumb-item">
              <i class="fas fa-home mr-2"></i> Job Order Payroll
            </a>
          </li>
          <li>
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <a href="joborderpayroll.php" class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-blue-700 breadcrumb-item">General Payroll</a>
            </div>
          </li>
          <li>
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <a href="joborderobligationrequest.php" class="ml-1 text-sm font-medium text-blue-700 hover:text-blue-600 md:ml-2 breadcrumb-item"> Job Order Obligation Request</a>
            </div>
          </li>
        </ol>
      </nav>
    </div>

    <!-- FORM CONTAINER - MATCHING THE IMAGE -->
    <div class="form-container">
      <!-- HEADER -->
      <div class="text-center font-bold text-lg mb-4">
        <p>OBLIGATION REQUEST AND STATUS</p>
        <p>LOCAL GOVERNMENT UNIT OF PALUAN</p>
      </div>

      <!-- SERIAL NO, DATE, FUND CLUSTER -->
      <div class="flex justify-end mb-4">
        <div class="flex space-x-6">
          <div>Serial No.: <span class="border-b border-black w-24 inline-block"></span></div>
          <div>Date: <span class="border-b border-black w-24 inline-block"></span></div>
          <div>Fund Cluster: <span class="border-b border-black w-24 inline-block"></span></div>
        </div>
      </div>

      <!-- PAYEE INFORMATION TABLE -->
      <div class="table-container mb-4">
        <table class="w-full text-sm text-left text-gray-900 border-collapse form-table">
          <tbody>
            <tr>
              <td class="w-[15%] header-cell">Payee</td>
              <td class="w-[35%]">CHARLENE U. CAJAYON</td>
              <td class="w-[15%] header-cell">Office</td>
              <td class="w-[35%]">Office of the Municipal Assessor</td>
            </tr>
            <tr>
              <td class="header-cell">Address</td>
              <td colspan="3">Paluan, Occidental Mindoro</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- OBLIGATION ITEMS TABLE -->
      <div class="table-container mb-4">
        <table class="w-full text-sm text-left text-gray-900 border-collapse form-table">
          <thead>
            <tr>
              <th class="w-[15%] text-center header-cell">Responsibility Center</th>
              <th class="w-[45%] text-center header-cell">Particulars</th>
              <th class="w-[10%] text-center header-cell mobile-hide">MFO/PAP</th>
              <th class="w-[10%] text-center header-cell mobile-hide">UACS Object Code</th>
              <th class="w-[10%] text-center header-cell">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="text-center">A.</td>
              <td>WAGES<br>September 1-15, 2025</td>
              <td class="mobile-hide"></td>
              <td class="mobile-hide"></td>
              <td class="text-right">2,250.00</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- CERTIFICATION SECTION A -->
      <div class="certification-box">
        <p class="font-bold">A.</p>
        <p class="mb-4">Certified: Charges to appropriation/allotment are necessary, lawful and under my direct supervision; and supporting documents valid, proper and legal</p>

        <div class="grid grid-cols-2 gap-4 mb-2">
          <div>Signature: <span class="signature-line"></span></div>
          <div>Printed Name: <strong>MELODY V. PAGLICAWAN</strong></div>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-2">
          <div>Position: <em>Municipal Assessor</em></div>
          <div class="text-center font-bold">Head, Requesting</div>
        </div>
        <div class="mt-4">
          Date: <span class="signature-line"></span>
        </div>
      </div>

      <!-- CERTIFICATION SECTION B -->
      <div class="certification-box">
        <p class="font-bold">B.</p>
        <p class="mb-4">Certified: Allotment available and obligated for the purpose/adjustment necessary as indicated above</p>

        <div class="grid grid-cols-2 gap-4 mb-2">
          <div>Signature: <span class="signature-line"></span></div>
          <div>Printed Name: <strong>EFIGENIA V. SAN AGUSTIN</strong></div>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-2">
          <div>Position: <em>Municipal Budget Officer</em></div>
          <div></div>
        </div>
        <div class="mt-4">
          Date: <span class="signature-line"></span>
        </div>
      </div>

      <!-- STATUS OF OBLIGATION SECTION -->
      <div class="mt-6">
        <p class="font-bold border-b border-black px-1 py-1">C. STATUS OF OBLIGATION</p>
        <div class="table-container mt-2">
          <table class="w-full text-sm text-left text-gray-900 border-collapse form-table">
            <thead>
              <tr>
                <th class="text-center header-cell" rowspan="2">Date</th>
                <th class="text-center header-cell" rowspan="2">Particulars</th>
                <th class="text-center header-cell mobile-hide" rowspan="2">ORS/JEV/Check/ADA/TRA No.</th>
                <th class="text-center header-cell" colspan="3">Amount</th>
                <th class="text-center header-cell" colspan="2">Balance</th>
              </tr>
              <tr>
                <th class="text-center header-cell">Obligation<br>(a)</th>
                <th class="text-center header-cell">Payable<br>(b)</th>
                <th class="text-center header-cell mobile-hide">Payment<br>(c)</th>
                <th class="text-center header-cell">Not Yet Due<br>(a-b)</th>
                <th class="text-center header-cell">Due and Demandable<br>(b-c)</th>
              </tr>
            </thead>
            <tbody>
              <tr class="h-8">
                <td></td>
                <td></td>
                <td class="mobile-hide"></td>
                <td></td>
                <td></td>
                <td class="mobile-hide"></td>
                <td></td>
                <td></td>
              </tr>
              <tr class="h-8">
                <td></td>
                <td></td>
                <td class="mobile-hide"></td>
                <td></td>
                <td></td>
                <td class="mobile-hide"></td>
                <td></td>
                <td></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="action-buttons">
      <button onclick="printForm()" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5">
        <svg class="w-4 h-4 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v6a2 2 0 002 2h12a2 2 0 002-2v-6a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm2 5V4h6v5H7zm-2 2h10v4H5v-4z" clip-rule="evenodd"></path>
        </svg>
        Print
      </button>
      <button id="save-button" class="text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5">
        <svg class="w-4 h-4 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
          <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 9a1 1 0 011-1h10a1 1 0 011 1v7a1 1 0 01-1 1H5a1 1 0 01-1-1V9zM5 7a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2H5z"></path>
        </svg>
        Save Data
      </button>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
  <script>
    // Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarContainer = document.getElementById('sidebar-container');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    sidebarToggle.addEventListener('click', () => {
      sidebarContainer.classList.toggle('active');
      sidebarOverlay.classList.toggle('active');
    });

    sidebarOverlay.addEventListener('click', () => {
      sidebarContainer.classList.remove('active');
      sidebarOverlay.classList.remove('active');
    });

    // Payroll Dropdown Toggle
    const payrollToggle = document.getElementById('payroll-toggle');
    const payrollDropdown = document.getElementById('payroll-dropdown');

    payrollToggle.addEventListener('click', (e) => {
      e.preventDefault();
      payrollDropdown.classList.toggle('open');
      const chevron = payrollToggle.querySelector('.chevron');
      chevron.classList.toggle('rotated');
    });

    // User Menu Toggle
    const userMenuButton = document.getElementById('user-menu-button');
    const userDropdown = document.getElementById('user-dropdown');

    userMenuButton.addEventListener('click', () => {
      userDropdown.classList.toggle('active');
      userMenuButton.classList.toggle('active');
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
      if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.classList.remove('active');
        userMenuButton.classList.remove('active');
      }
    });

    // Date and Time Update
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
      document.getElementById('current-date').textContent = formattedDate;

      // Format time
      const timeOptions = {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
      };
      const formattedTime = now.toLocaleTimeString('en-US', timeOptions);
      document.getElementById('current-time').textContent = formattedTime;
    }

    // Update date/time immediately and every second
    updateDateTime();
    setInterval(updateDateTime, 1000);

    /**
     * Custom print function for bond paper
     */
    function printForm() {
      // Create a print-friendly version of the form
      const printContent = document.querySelector('.form-container').innerHTML;
      const originalContent = document.body.innerHTML;

      // Replace body content with just the form
      document.body.innerHTML = `
                <div class="form-container" style="border: 2px solid #000; max-width: 100%; margin: 0; padding: 10px; font-size: 12px; font-family: Arial, sans-serif;">
                    ${printContent}
                </div>
            `;

      // Set print options
      const printSettings = `
                <style>
                    @media print {
                        @page {
                            size: portrait;
                            margin: 10mm;
                        }
                        body {
                            margin: 0;
                            padding: 0;
                            font-size: 12px;
                        }
                        .form-container {
                            border: 2px solid #000;
                            max-width: 100%;
                            margin: 0 auto;
                            padding: 10px;
                            page-break-inside: avoid;
                        }
                        .form-table th,
                        .form-table td {
                            border: 1px solid #000;
                            padding: 4px;
                        }
                        .mobile-hide {
                            display: table-cell !important;
                        }
                        .signature-line {
                            border-bottom: 1px solid #000;
                            margin: 5px 0 2px 0;
                            line-height: 1;
                            min-width: 150px;
                            display: inline-block;
                        }
                        .certification-box {
                            border: 1px solid #000;
                            padding: 8px;
                            margin: 10px 0;
                        }
                    }
                </style>
            `;

      // Add print settings to head
      document.head.innerHTML += printSettings;

      // Print the form
      window.print();

      // Restore original content
      document.body.innerHTML = originalContent;

      // Re-initialize any necessary scripts
      window.location.reload();
    }

    // Global Action Button Logics
    document.getElementById('save-button').addEventListener('click', () => {
      alert('Saving Obligation Request Data...');
    });

    // Close sidebar on mobile when clicking a link
    document.querySelectorAll('.sidebar-item, .sidebar-dropdown-item').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth < 768) {
          sidebarContainer.classList.remove('active');
          sidebarOverlay.classList.remove('active');
        }
      });
    });
  </script>
</body>

</html>