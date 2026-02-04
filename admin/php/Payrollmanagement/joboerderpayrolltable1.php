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
  <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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

    /* IMPROVED NAVBAR - Fixed Responsive */
    .navbar {
      background: var(--gradient-nav);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
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
      border: none;
      outline: none;
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
      display: flex;
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

    @media (min-width: 1024px) {
      .mobile-toggle {
        display: none;
      }
    }

    /* Sidebar Styles */
    .sidebar-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
      z-index: 999;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }

    .sidebar-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .sidebar {
      position: fixed;
      top: 10px;
      left: -300px;
      width: 250px;
      height: calc(100vh - 70px);
      background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
      z-index: 1000;
      transition: left 0.3s ease;
      display: flex;
      flex-direction: column;

      overflow-x: hidden;
    }

    .sidebar.active {
      left: 0;
    }

    @media (min-width: 1024px) {
      .sidebar {
        left: 0;
        top: 70px;
        height: calc(100vh - 70px);
      }

      .sidebar-overlay {
        display: none !important;
      }

      main {
        margin-left: 250px;
      }
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
      color: rgba(255, 255, 255, 0.7);
      font-size: 0.8rem;
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
      cursor: pointer;
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
      font-size: 0.9rem;
    }

    .sidebar-item .chevron {
      transition: transform 0.3s ease;
      font-size: 0.7rem;
    }

    .sidebar-item .chevron.rotated {
      transform: rotate(180deg);
    }

    /* Dropdown Menu in Sidebar */
    .submenu {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease;
      margin-left: 2.5rem;
    }

    .submenu.open {
      max-height: 500px;
    }

    .submenu-item {
      display: flex;
      align-items: center;
      padding: 0.5rem 1rem;
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      border-radius: 8px;
      margin-bottom: 0.25rem;
      transition: all 0.3s ease;
      font-size: 0.85rem;
    }

    .submenu-item:hover {
      background: rgba(255, 255, 255, 0.1);
      color: white;
      transform: translateX(5px);
    }

    .submenu-item.active {
      background: rgba(255, 255, 255, 0.2);
      color: white;
    }

    .submenu-item i {
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
      font-size: 1rem;
      font-weight: 700;
      color: white;
      line-height: 1.2;
    }

    .mobile-brand-subtitle {
      font-size: 0.7rem;
      color: rgba(255, 255, 255, 0.9);
      font-weight: 500;
    }

    /* Main Content */
    main {
      margin-top: 70px;
      padding: 1.5rem;
      min-height: calc(100vh - 70px);
      width: 100%;
      transition: margin-left 0.3s ease;
    }

    @media (min-width: 1024px) {
      main {
        margin-left: 250px;
        width: calc(100% - 250px);
      }
    }

    @media (max-width: 768px) {
      main {
        padding: 1rem;
      }
    }

    /* Payroll Container */
    .payroll-container {
      font-family: Arial, sans-serif;
      border: 2px solid #000;
      max-width: 1400px;
      margin: 20px auto;
      padding: 15px;
      font-size: 10px;
      background-color: white;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      border-radius: 8px;
      overflow-x: auto;
    }

    /* Table styling */
    .payroll-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 1200px;
    }

    .payroll-table th,
    .payroll-table td {
      border: 1px solid #000;
      padding: 6px 8px;
      line-height: 1.3;
      height: 30px;
      vertical-align: middle;
      white-space: nowrap;
    }

    .payroll-table thead th {
      text-align: center;
      vertical-align: middle;
      background-color: #f8fafc;
      font-weight: 600;
    }

    /* Action buttons with icons */
    .action-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 4px;
      transition: all 0.2s ease;
      margin: 0 2px;
      cursor: pointer;
      border: none;
      outline: none;
    }

    .action-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .view-btn {
      background-color: #3b82f6;
      color: white;
    }

    .view-btn:hover {
      background-color: #2563eb;
    }

    .edit-btn {
      background-color: #f59e0b;
      color: white;
    }

    .edit-btn:hover {
      background-color: #d97706;
    }

    .delete-btn {
      background-color: #ef4444;
      color: white;
    }

    .delete-btn:hover {
      background-color: #dc2626;
    }

    /* Enhanced button styles */
    .nav-button {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.75rem 1.5rem;
      font-weight: 600;
      border-radius: 0.5rem;
      transition: all 0.3s ease;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      cursor: pointer;
      border: none;
      outline: none;
      text-decoration: none;
    }

    .nav-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .nav-button-primary {
      background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
      color: white;
      border: none;
    }

    .nav-button-primary:hover {
      background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    }

    .nav-button-success {
      background: linear-gradient(135deg, #10b981 0%, #047857 100%);
      color: white;
      border: none;
    }

    .nav-button-success:hover {
      background: linear-gradient(135deg, #047857 0%, #065f46 100%);
    }

    /* Print styles */
    @media print {

      .action-buttons,
      .action-cell {
        display: none !important;
      }

      .payroll-container {
        border: none;
        margin: 0;
        padding: 0;
        box-shadow: none;
        border-radius: 0;
      }

      body {
        background: none;
      }

      nav,
      aside,
      .sidebar,
      .navbar,
      .breadcrumb-container,
      .action-buttons {
        display: none !important;
      }
    }

    /* RESPONSIVE STYLES */
    @media (max-width: 1024px) {
      .datetime-container {
        display: none;
      }

      .user-info {
        display: none;
      }

      .user-button {
        padding: 0.4rem;
      }

      .navbar-container {
        padding: 0 1rem;
      }

      .brand-text {
        display: none;
      }

      .mobile-brand {
        display: flex;
      }

      .payroll-container {
        margin: 10px auto;
        padding: 10px;
        font-size: 9px;
      }

      .payroll-table th,
      .payroll-table td {
        padding: 4px 6px;
        height: 25px;
      }

      .action-button {
        width: 28px;
        height: 28px;
      }
    }

    @media (min-width: 769px) {
      .mobile-brand {
        display: none;
      }

      .brand-text {
        display: flex;
      }

      /* Show all columns on desktop */
      .mobile-hide {
        display: table-cell !important;
      }

      .mobile-hide-extra {
        display: table-cell !important;
      }
    }

    @media (max-width: 768px) {
      .navbar {
        height: 65px;
      }


      main {
        margin-top: 65px;
      }

      .mobile-toggle {
        width: 36px;
        height: 36px;
      }

      .user-avatar {
        width: 32px;
        height: 32px;
      }

      .brand-logo {
        width: 40px;
        height: 40px;
      }

      .payroll-container {
        font-size: 8px;
        padding: 8px;
      }

      .payroll-table th,
      .payroll-table td {
        padding: 3px 4px;
        height: 22px;
      }

      .action-button {
        width: 24px;
        height: 24px;
        font-size: 0.7rem;
      }

      .nav-button {
        padding: 0.6rem 1.2rem;
        font-size: 0.875rem;
      }

      /* Hide some columns on mobile */
      .mobile-hide {
        display: none;
      }

      /* Reduce column widths on mobile */
      .payroll-table th[rowspan="2"].mobile-hide,
      .payroll-table td.mobile-hide {
        display: none;
      }
    }

    @media (max-width: 640px) {

      /* Stack action buttons vertically on very small screens */
      .action-buttons {
        flex-direction: column;
        gap: 0.5rem;
      }

      .action-buttons .nav-button {
        width: 100%;
      }

      /* Hide more columns on very small screens */
      .mobile-hide-extra {
        display: none;
      }

      .payroll-table th.mobile-hide-extra,
      .payroll-table td.mobile-hide-extra {
        display: none;
      }

      /* Reduce font size for mobile */
      .payroll-container {
        font-size: 7px;
      }

      .payroll-table th,
      .payroll-table td {
        padding: 2px 3px;
      }

      .action-button {
        width: 20px;
        height: 20px;
        font-size: 0.6rem;
      }
    }

    /* Breadcrumb improvements */
    .breadcrumb-item {
      position: relative;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      transition: all 0.3s ease;
      text-decoration: none;
    }

    .breadcrumb-item:hover {
      background-color: #eff6ff;
    }

    .breadcrumb-item.active {
      background-color: #3b82f6;
      color: white;
      font-weight: 600;
    }

    /* Scrollbar Styling */
    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
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

    /* Page Header */
    .page-header {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      margin-bottom: 1.5rem;
    }

    @media (min-width: 768px) {
      .page-header {
        flex-direction: row;
        align-items: center;
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
        <button class="mobile-toggle" id="mobile-menu-toggle">
          <i class="fas fa-bars"></i>
        </button>

        <!-- Logo and Brand (Desktop) -->
        <a href="../dashboard.php" class="navbar-brand hidden lg:flex">
          <img class="brand-logo"
            src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
            alt="Logo" />
          <div class="brand-text">
            <span class="brand-title">HR Management System</span>
            <span class="brand-subtitle">Paluan Occidental Mindoro</span>
          </div>
        </a>

        <!-- Logo and Brand (Mobile) -->
        <div class="mobile-brand lg:hidden">
          <img class="brand-logo"
            src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
            alt="Logo" />
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
  <div class="sidebar" id="sidebar">
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
          <a href="./Employee.php" class="sidebar-item">
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

        <li>
          <a href="#" class="sidebar-item active" id="payroll-toggle">
            <i class="fas fa-money-bill-wave"></i>
            <span>Payroll</span>
            <i class="fas fa-chevron-down chevron text-xs ml-auto"></i>
          </a>
          <div class="submenu" id="payroll-submenu">
            <a href="contractualpayrolltable1.php" class="submenu-item ">
              <i class="fas fa-circle text-xs"></i>
              Contractual
            </a>
            <a href="../Payrollmanagement/joboerderpayrolltable1.php" class="submenu-item active">
              <i class="fas fa-circle text-xs"></i>
              Job Order
            </a>
            <a href="../Payrollmanagement/permanentpayrolltable1.php" class="submenu-item">
              <i class="fas fa-circle text-xs"></i>
              Permanent
            </a>
          </div>
        </li>



        <!-- Reports -->
        <li>
          <a href="../paysliplist.php" class="sidebar-item">
            <i class="fas fa-file-alt"></i>
            <span>Reports</span>
          </a>
        </li>

        <!-- Settings -->
        <li>
          <a href="../settings.php" class="sidebar-item">
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

  <!-- MAIN -->
  <main class="main-content">
    <!-- Breadcrumb -->
    <div class="mb-4">
      <nav class="flex" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-2">
          <li class="inline-flex items-center">
            <a href="joboerderpayrolltable1.php"
              class="inline-flex items-center text-sm font-medium  text-blue-600  hover:text-blue-600">
              <i class="fas fa-home mr-2"></i> Job Order Payroll
            </a>
          </li>
          <li>
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <a href="joborderpayroll.php"
                class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">General Payroll</a>
            </div>
          </li>
          <li>
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <a href="joborderobligationrequest.php" class="ml-1 text-sm font-medium hover:text-blue-700">Job Order
                Obligation Request</a>
            </div>
          </li>
        </ol>
      </nav>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Job Order Payroll</h1>
        <p class="text-gray-600 mt-1">Manage and process job order employee payroll</p>
      </div>
      <div class="mt-4 md:mt-0">
        <button id="add-payroll-btn" class="nav-button nav-button-primary">
          <i class="fas fa-plus mr-2"></i> Add Payroll
        </button>
      </div>
    </div>

    <!-- Payroll Container -->
    <div class="payroll-container">
      <!-- Header -->
      <div class="mb-4">
        <div class="text-center font-bold">
          <h2 class="text-lg">PAYROLL</h2>
          <p class="text-sm">For the period <strong>SEPTEMBER 1-15, 2015</strong></p>
        </div>
      </div>

      <!-- Payroll Table -->
      <div class="table-container">
        <table class="payroll-table">
          <thead>
            <tr>
              <th rowspan="2" class="w-12">No.</th>
              <th rowspan="2" class="min-w-[120px]">Name</th>
              <th rowspan="2" class="mobile-hide">Designation</th>
              <th rowspan="2" class="mobile-hide mobile-hide-extra">Address</th>
              <th colspan="4">COMPENSATIONS</th>
              <th colspan="2">DEDUCTIONS</th>
              <th colspan="2" class="mobile-hide">COMMUNITY TAX CERTIFICATE</th>
              <th rowspan="2" class="action-cell">Actions</th>
            </tr>
            <tr>
              <th>Rate per hour/day</th>
              <th>Number of hours/days</th>
              <th>Rate per day/amount Earned</th>
              <th>Total Amount Earned</th>
              <th>GSIS Total Deductions</th>
              <th>Total Net Amount Due</th>
              <th class="mobile-hide">Number</th>
              <th class="mobile-hide">Date</th>
            </tr>
          </thead>
          <tbody>
            <!-- Row 1 -->
            <tr class="bg-white hover:bg-gray-50">
              <td class="text-center font-medium text-gray-900">1</td>
              <td class="font-medium text-gray-900">CHARLENE D. DIALATON</td>
              <td class="text-center mobile-hide">Clerk I</td>
              <td class="text-center mobile-hide mobile-hide-extra">Purok Ony</td>
              <td class="text-right">256.00</td>
              <td class="text-center">9 days</td>
              <td class="text-right">2,256.00</td>
              <td class="text-right">2,256.00</td>
              <td class="text-right">84,950.00</td>
              <td class="text-right">2,256.00</td>
              <td class="text-center mobile-hide">10/1/2015</td>
              <td class="text-center mobile-hide">10/1/2015</td>
              <td class="action-cell">
                <div class="flex justify-center space-x-1">
                  <button data-modal-target="view-modal" data-modal-toggle="view-modal" type="button"
                    class="action-button view-btn" title="View">
                    <i class="fas fa-eye"></i>
                  </button>
                  <button data-modal-target="edit-modal" data-modal-toggle="edit-modal" type="button"
                    class="action-button edit-btn" title="Edit" onclick="loadEditData(1)">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button data-modal-target="delete-modal" data-modal-toggle="delete-modal" type="button"
                    class="action-button delete-btn" title="Delete" onclick="setDeleteId(1)">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Additional rows will be generated by JavaScript -->
          </tbody>
        </table>
      </div>
    </div>

    <!-- Action Buttons -->
    <div
      class="action-buttons flex flex-col md:flex-row justify-center md:justify-end space-y-3 md:space-y-0 md:space-x-4 mt-6 mb-10">
      <button id="save-button" class="nav-button nav-button-success">
        <i class="fas fa-save mr-2"></i> Save Data
      </button>
      <a href="joborderpayroll.php">
        <button id="next-button" class="nav-button nav-button-primary">
          Next Payroll <i class="fas fa-arrow-right ml-2"></i>
        </button>
      </a>
    </div>
  </main>

  <!-- View Modal -->
  <div id="view-modal" tabindex="-1" aria-hidden="true"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-2xl max-h-full">
      <div class="relative bg-white rounded-lg shadow">
        <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t">
          <h3 class="text-xl font-semibold text-gray-900">
            View Employee Payroll Data
          </h3>
          <button type="button"
            class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center"
            data-modal-hide="view-modal">
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
            </svg>
            <span class="sr-only">Close modal</span>
          </button>
        </div>
        <div class="p-4 md:p-5 space-y-4 text-sm" id="view-modal-content">
          <p><strong>Name:</strong> <span id="view-name"></span></p>
          <p><strong>Designation:</strong> <span id="view-designation"></span></p>
          <hr>
          <p><strong>Total Amount Earned:</strong> <span id="view-amount-earned"></span></p>
          <p><strong>Total Net Amount Due:</strong> <span id="view-net-amount"></span></p>
          <hr>
          <p class="text-gray-500">Full details of the selected row would appear here.</p>
        </div>
        <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
          <button data-modal-hide="view-modal" type="button"
            class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="edit-modal" tabindex="-1" aria-hidden="true"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-3xl max-h-full">
      <div class="relative bg-white rounded-lg shadow">
        <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t">
          <h3 class="text-xl font-semibold text-gray-900">
            Edit Payroll Entry (Row <span id="edit-row-id"></span>)
          </h3>
          <button type="button"
            class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center"
            data-modal-hide="edit-modal">
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
            </svg>
            <span class="sr-only">Close modal</span>
          </button>
        </div>
        <form class="p-4 md:p-5" id="edit-form">
          <div class="grid gap-4 mb-4 grid-cols-1 md:grid-cols-2">
            <div class="col-span-2 md:col-span-1">
              <label for="name" class="block mb-2 text-sm font-medium text-gray-900">Name</label>
              <input type="text" name="name" id="edit-name"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5"
                placeholder="Employee Name" required>
            </div>
            <div class="col-span-2 md:col-span-1">
              <label for="designation" class="block mb-2 text-sm font-medium text-gray-900">Designation</label>
              <input type="text" name="designation" id="edit-designation"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5"
                placeholder="Designation">
            </div>
            <div class="col-span-2 md:col-span-1">
              <label for="amount" class="block mb-2 text-sm font-medium text-gray-900">Total Amount Earned</label>
              <input type="number" name="amount" id="edit-amount"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5"
                step="0.01" required>
            </div>
            <div class="col-span-2 md:col-span-1">
              <label for="net-amount" class="block mb-2 text-sm font-medium text-gray-900">Total Net Amount Due</label>
              <input type="number" name="net-amount" id="edit-net-amount"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5"
                step="0.01" required>
            </div>
          </div>
          <button type="submit"
            class="text-white inline-flex items-center bg-yellow-400 hover:bg-yellow-500 focus:ring-4 focus:outline-none focus:ring-yellow-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
            <i class="fas fa-edit mr-2"></i> Update Entry
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="delete-modal" tabindex="-1"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-md max-h-full">
      <div class="relative bg-white rounded-lg shadow">
        <button type="button"
          class="absolute top-3 end-2.5 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center"
          data-modal-hide="delete-modal">
          <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
          </svg>
          <span class="sr-only">Close modal</span>
        </button>
        <div class="p-4 md:p-5 text-center">
          <i class="fas fa-exclamation-triangle text-red-500 text-5xl mb-4"></i>
          <h3 class="mb-5 text-lg font-normal text-gray-500">Are you sure you want to delete this payroll entry for Row
            <span id="delete-row-id" class="font-bold"></span>?
          </h3>
          <button onclick="confirmDelete()" data-modal-hide="delete-modal" type="button"
            class="text-white bg-red-600 hover:bg-red-800 focus:ring-4 focus:outline-none focus:ring-red-300 font-medium rounded-lg text-sm inline-flex items-center px-5 py-2.5 text-center">
            Yes, I'm sure
          </button>
          <button data-modal-hide="delete-modal" type="button"
            class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100">No,
            cancel</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
  <script>
    let selectedRowId = null;

    /** Dummy Data Structure (In a real app, this would come from a database/API) */
    const payrollData = {
      1: { name: "CHARLENE D. DIALATON", designation: "Clerk I", address: "Purok Ony", amountEarned: "2,256.00", netAmountDue: "2,256.00" },
      2: { name: "John Doe", designation: "Staff", address: "Sample St", amountEarned: "1,500.00", netAmountDue: "1,500.00" },
      3: { name: "Jane Smith", designation: "Manager", address: "Another Ave", amountEarned: "5,000.00", netAmountDue: "4,800.00" },
    };

    /**
     * JavaScript to dynamically generate empty rows
     */
    document.addEventListener('DOMContentLoaded', () => {
      // Update date and time
      function updateDateTime() {
        const now = new Date();
        const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' };

        const dateElement = document.getElementById('current-date');
        const timeElement = document.getElementById('current-time');

        if (dateElement) {
          dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
        }
        if (timeElement) {
          timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
        }
      }

      updateDateTime();
      setInterval(updateDateTime, 1000);

      // Generate additional rows
      const tableBody = document.querySelector('.payroll-table tbody');
      for (let i = 2; i <= 10; i++) {
        const row = document.createElement('tr');
        row.className = 'bg-white hover:bg-gray-50';
        row.innerHTML = `
          <td class="text-center font-medium text-gray-900">${i}</td>
          <td></td>
          <td class="mobile-hide"></td>
          <td class="mobile-hide mobile-hide-extra"></td>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td class="mobile-hide"></td>
          <td class="mobile-hide"></td>
          <td class="action-cell">
            <div class="flex justify-center space-x-1">
              <button data-modal-target="view-modal" data-modal-toggle="view-modal" type="button" class="action-button view-btn" title="View">
                <i class="fas fa-eye"></i>
              </button>
              <button data-modal-target="edit-modal" data-modal-toggle="edit-modal" type="button" class="action-button edit-btn" title="Edit" onclick="loadEditData(${i})">
                <i class="fas fa-edit"></i>
              </button>
              <button data-modal-target="delete-modal" data-modal-toggle="delete-modal" type="button" class="action-button delete-btn" title="Delete" onclick="setDeleteId(${i})">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </td>
        `;
        tableBody.appendChild(row);
      }

      // Mobile sidebar toggle functionality
      const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
      const sidebar = document.getElementById('sidebar');
      const sidebarOverlay = document.getElementById('sidebar-overlay');

      if (mobileMenuToggle && sidebar && sidebarOverlay) {
        mobileMenuToggle.addEventListener('click', function () {
          sidebar.classList.toggle('active');
          sidebarOverlay.classList.toggle('active');
          // Prevent body scroll when sidebar is open
          document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        });

        sidebarOverlay.addEventListener('click', function () {
          sidebar.classList.remove('active');
          sidebarOverlay.classList.remove('active');
          document.body.style.overflow = '';
        });

        // Close sidebar when clicking on a link (for mobile)
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
          link.addEventListener('click', function () {
            if (window.innerWidth < 1024) {
              sidebar.classList.remove('active');
              sidebarOverlay.classList.remove('active');
              document.body.style.overflow = '';
            }
          });
        });
      }

      // User dropdown functionality
      const userMenuButton = document.getElementById('user-menu-button');
      const userDropdown = document.getElementById('user-dropdown');

      if (userMenuButton && userDropdown) {
        userMenuButton.addEventListener('click', function (e) {
          e.stopPropagation();
          userDropdown.classList.toggle('active');
          userMenuButton.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (event) {
          if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
            userDropdown.classList.remove('active');
            userMenuButton.classList.remove('active');
          }
        });
      }

      // Payroll dropdown in sidebar
      const payrollToggle = document.getElementById('payroll-toggle');
      const payrollDropdown = document.getElementById('payroll-submenu');

      if (payrollToggle && payrollDropdown) {
        // Open payroll dropdown by default since we're on payroll page
        payrollDropdown.classList.add('open');
        const chevron = payrollToggle.querySelector('.chevron');
        if (chevron) {
          chevron.classList.add('rotated');
        }

        payrollToggle.addEventListener('click', function (e) {
          e.preventDefault();
          payrollDropdown.classList.toggle('open');
          const chevron = this.querySelector('.chevron');
          if (chevron) {
            chevron.classList.toggle('rotated');
          }
        });
      }

      // Add this to your existing JavaScript in each payroll page:

      // Payroll dropdown toggle
      document.addEventListener('DOMContentLoaded', function () {
        const payrollToggle = document.getElementById('payroll-toggle');
        const payrollDropdown = document.getElementById('payroll-dropdown');

        if (payrollToggle && payrollDropdown) {
          // Open dropdown by default on payroll pages
          payrollDropdown.classList.add('show');
          const chevron = payrollToggle.querySelector('.chevron');
          if (chevron) {
            chevron.classList.add('rotate');
          }

          payrollToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Toggle the 'show' class
            payrollDropdown.classList.toggle('show');

            // Toggle chevron rotation
            const chevron = this.querySelector('.chevron');
            if (chevron) {
              chevron.classList.toggle('rotate');
            }
          });
        }
      });

      // Handle window resize
      window.addEventListener('resize', function () {
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        if (window.innerWidth >= 1024) {
          // On desktop, ensure sidebar is visible and overlay is hidden
          if (sidebar) sidebar.classList.remove('active');
          if (sidebarOverlay) sidebarOverlay.classList.remove('active');
          document.body.style.overflow = '';
        }
      });

      // Add event listeners for view modal buttons
      document.querySelectorAll('[data-modal-target="view-modal"]').forEach(button => {
        button.addEventListener('click', (e) => {
          const row = e.target.closest('tr');
          const rowId = parseInt(row.querySelector('td:first-child').textContent);
          const data = payrollData[rowId];

          if (data) {
            document.getElementById('view-name').textContent = data.name;
            document.getElementById('view-designation').textContent = data.designation;
            document.getElementById('view-amount-earned').textContent = data.amountEarned;
            document.getElementById('view-net-amount').textContent = data.netAmountDue;
          } else {
            document.getElementById('view-name').textContent = 'N/A';
            document.getElementById('view-designation').textContent = 'N/A';
            document.getElementById('view-amount-earned').textContent = 'N/A';
            document.getElementById('view-net-amount').textContent = 'N/A';
          }
        });
      });
    });

    /**
     * Load data into the Edit modal form
     * @param {number} id - The row ID to edit.
     */
    function loadEditData(id) {
      selectedRowId = id;
      const data = payrollData[id];

      document.getElementById('edit-row-id').textContent = id;

      if (data) {
        // Remove commas and convert to number for input fields
        document.getElementById('edit-name').value = data.name;
        document.getElementById('edit-designation').value = data.designation;
        document.getElementById('edit-amount').value = parseFloat(data.amountEarned.replace(/,/g, ''));
        document.getElementById('edit-net-amount').value = parseFloat(data.netAmountDue.replace(/,/g, ''));
      } else {
        // Clear or set default values if no data exists
        document.getElementById('edit-name').value = '';
        document.getElementById('edit-designation').value = '';
        document.getElementById('edit-amount').value = 0.00;
        document.getElementById('edit-net-amount').value = 0.00;
      }
    }

    /**
     * Handle Edit Form Submission
     */
    document.getElementById('edit-form').addEventListener('submit', function (e) {
      e.preventDefault();
      // In a real application, you would send this data to a server

      const updatedData = {
        name: document.getElementById('edit-name').value,
        designation: document.getElementById('edit-designation').value,
        amountEarned: parseFloat(document.getElementById('edit-amount').value).toFixed(2),
        netAmountDue: parseFloat(document.getElementById('edit-net-amount').value).toFixed(2),
      };

      console.log(`Updating Row ${selectedRowId} with:`, updatedData);

      // Close modal manually since the form submit was prevented
      const editModalElement = document.getElementById('edit-modal');
      const editModal = Flowbite.getModal(editModalElement);
      if (editModal) editModal.hide();

      alert(`Row ${selectedRowId} updated! (Check console for data)`);
      // **TO-DO:** Update the table row visually here.
    });

    /**
     * Set the ID for the Delete confirmation modal
     * @param {number} id - The row ID to delete.
     */
    function setDeleteId(id) {
      selectedRowId = id;
      document.getElementById('delete-row-id').textContent = id;
    }

    /**
     * Handle Delete confirmation
     */
    function confirmDelete() {
      // In a real application, you would send a delete request to a server
      console.log(`Confirmed delete for Row ${selectedRowId}`);

      // **TO-DO:** Remove the corresponding row from the table (using the selectedRowId) and update total.

      alert(`Row ${selectedRowId} successfully deleted.`);
    }

    // Global Action Button Logics
    document.getElementById('save-button').addEventListener('click', () => {
      alert('Saving all payroll data... (In a real app, this would persist the entire form data)');
    });

    document.getElementById('next-button').addEventListener('click', () => {
      alert('Navigating to the next payroll period or blank form.');
      // window.location.href = '/new-payroll-form';
    });

    // Add Payroll Button
    document.getElementById('add-payroll-btn').addEventListener('click', () => {
      alert('Add new payroll functionality would open a form here.');
    });

    // Payroll Dropdown Management
    document.addEventListener('DOMContentLoaded', function () {
      const payrollToggle = document.getElementById('payroll-toggle');
      const payrollDropdown = document.getElementById('payroll-dropdown');

      if (payrollToggle && payrollDropdown) {
        // Check if we're on a payroll page
        const currentPath = window.location.pathname;
        const isPayrollPage = currentPath.includes('contractual') ||
          currentPath.includes('joborder') ||
          currentPath.includes('permanent') ||
          currentPath.includes('Payrollmanagement');

        // Auto-open dropdown on payroll pages
        if (isPayrollPage) {
          payrollToggle.classList.add('active');
          payrollDropdown.style.display = 'block';
          const chevron = payrollToggle.querySelector('.chevron');
          if (chevron) {
            chevron.classList.add('rotate');
          }
        }

        // Toggle dropdown on click
        payrollToggle.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();

          if (payrollDropdown.style.display === 'block') {
            payrollDropdown.style.display = 'none';
            this.classList.remove('active');
            const chevron = this.querySelector('.chevron');
            if (chevron) {
              chevron.classList.remove('rotate');
            }
          } else {
            payrollDropdown.style.display = 'block';
            this.classList.add('active');
            const chevron = this.querySelector('.chevron');
            if (chevron) {
              chevron.classList.add('rotate');
            }
          }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
          if (!payrollToggle.contains(e.target) && !payrollDropdown.contains(e.target)) {
            payrollDropdown.style.display = 'none';
            payrollToggle.classList.remove('active');
            const chevron = payrollToggle.querySelector('.chevron');
            if (chevron) {
              chevron.classList.remove('rotate');
            }
          }
        });
      }
    });
  </script>
</body>

</html>