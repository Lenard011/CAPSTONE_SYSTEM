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
  <title>Job Order Obligation Request</title>
  <!-- Use consistent CSS imports -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Add Inter font -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              "50": "#eff6ff",
              "100": "#dbeafe",
              "200": "#bfdbfe",
              "300": "#93c5fd",
              "400": "#60a5fa",
              "500": "#3b82f6",
              "600": "#2563eb",
              "700": "#1d4ed8",
              "800": "#1e40af",
              "900": "#1e3a8a",
              "950": "#172554"
            }
          },
          fontFamily: {
            'sans': ['Inter', 'system-ui', 'sans-serif'],
          }
        }
      }
    }
  </script>
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

    /* NAVBAR - FIXED RESPONSIVE STYLES */
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
      top: 70px;
      left: -300px;
      width: 250px;
      height: calc(100vh - 70px);
      background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
      z-index: 1000;
      transition: left 0.3s ease;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
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

    /* FORM CONTAINER STYLES - Matching the Image */
    .form-container {
      max-width: 900px;
      margin: 20px auto;
      background-color: white;
      font-size: 14px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      font-family: Arial, sans-serif;
    }

    /* Table styling for form */
    .table-container {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    .form-table {
      width: 100%;
      border-collapse: collapse;
    }

    .form-table th,
    .form-table td {
      border: 2px solid #000;
      border-top: 0;
      padding: 8px 10px;
      line-height: 1.3;
      height: 40px;
      vertical-align: middle;
      white-space: nowrap;
    }

    .header-cell {
      text-align: center;
      vertical-align: middle;
    }

    .certification-box {
      border: 1px solid #000;
      border-top: 0;
      padding-top: 5px;
      padding-bottom: 5px;
      padding-left: 5px;
      padding-right: 5px;
      background-color: #fff;
      font-size: 12px;
    }

    .signature-line {
      border-bottom: 1px solid #000;
      height: 20px;
    }

    /* Action buttons */
    .action-buttons {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin-top: 2rem;
      margin-bottom: 2rem;
    }

    /* Responsive styles */
    @media (max-width: 768px) {
      .form-container {
        padding: 10px;
        font-size: 12px;
      }

      .form-table th,
      .form-table td {
        padding: 6px 8px;
        height: 35px;
      }

      .mobile-hide {
        display: none !important;
      }

      .signature-line {
        min-width: 100px;
      }

      .certification-box {
        padding: 10px;
      }
    }

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
    }

    @media (min-width: 769px) {
      .mobile-brand {
        display: none;
      }

      .brand-text {
        display: flex;
      }

      .mobile-hide {
        display: table-cell !important;
      }
    }

    @media (max-width: 640px) {
      .action-buttons {
        flex-direction: column;
        gap: 0.5rem;
      }

      .action-buttons button {
        width: 100%;
      }

      .form-table {
        min-width: 800px;
      }

      .form-container {
        font-size: 11px;
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

    /* Print styles */
    @media print {

      .action-buttons,
      .action-cell,
      nav,
      aside,
      .sidebar,
      .navbar,
      .breadcrumb-container,
      .print-hide {
        display: none !important;
      }

      .form-container {
        border: none;
        margin: 0;
        padding: 0;
        box-shadow: none;
        border-radius: 0;
      }

      body {
        background: none;
      }

      main {
        margin-left: 0 !important;
        padding: 0 !important;
        margin-top: 0 !important;
      }
    }

    .breadcrumb-container {
      margin-top: 1rem;
      margin-bottom: 1.5rem;
    }

    /* Responsive utilities - NAVBAR SPECIFIC */
    @media (max-width: 768px) {
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
        position: fixed;
        top: 70px;
        right: 1rem;
        left: 1rem;
        width: auto;
        max-width: 300px;
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
    }

    @media (min-width: 769px) {
      .mobile-brand {
        display: none;
      }

      .brand-text {
        display: flex;
      }
    }

    @media (max-width: 640px) {
      .navbar {
        height: 65px;
      }

      .sidebar {
        top: 65px;
        height: calc(100vh - 65px);
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
    }
  </style>
</head>

<body class="bg-gray-50">
  <!-- Navigation Header -->
  <nav class="navbar print-hide">
    <div class="navbar-container">
      <!-- Left Section -->
      <div class="navbar-left">
        <!-- Mobile Menu Toggle -->
        <button class="mobile-toggle" id="mobile-menu-toggle">
          <i class="fas fa-bars"></i>
        </button>

        <!-- Logo and Brand (Desktop) -->
        <a href="../dashboard.php" class="navbar-brand hidden lg:flex">
          <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
          <div class="brand-text">
            <span class="brand-title">HR Management System</span>
            <span class="brand-subtitle">Paluan Occidental Mindoro</span>
          </div>
        </a>

        <!-- Logo and Brand (Mobile) -->
        <div class="mobile-brand lg:hidden">
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

        <!-- User Menu -->
        <div class="user-menu print-hide">
          <button class="user-button" id="user-menu-button">
            <img src="https://ui-avatars.com/api/?name=Admin+User&background=3b82f6&color=fff" alt="User" class="user-avatar">
            <div class="user-info hidden md:block">
              <span class="user-name">Admin User</span>
              <span class="user-role">Administrator</span>
            </div>
            <i class="user-chevron fas fa-chevron-down"></i>
          </button>

          <!-- User Dropdown -->
          <div class="user-dropdown" id="user-dropdown">
            <div class="dropdown-header">
              <h3>Admin User</h3>
              <p>Administrator</p>
            </div>
            <div class="dropdown-menu">
              <a href="../profile.php" class="dropdown-item">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
              </a>
              <a href="../settings.php" class="dropdown-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
              </a>
              <a href="?logout=true" class="dropdown-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <!-- Sidebar Overlay for Mobile -->
  <div class="sidebar-overlay print-hide" id="sidebar-overlay"></div>

  <!-- Sidebar -->
  <div class="sidebar print-hide" id="sidebar">
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
          <a href="../employees/Employee.php" class="sidebar-item">
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
          <div class="submenu" id="payroll-submenu">
            <a href="../Payrollmanagement/contractualpayrolltable1.php" class="submenu-item">
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

  <!-- MAIN CONTENT - APPLIED JOB ORDER OBLIGATION REQUEST STRUCTURE -->
  <main class="main-content">
    <!-- Breadcrumb navigation - PROPER HIERARCHY -->
    <div class="breadcrumb-container print-hide">
      <nav class="mt-4 flex" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-2">
          <li class="inline-flex items-center">
            <a href="joboerderpayrolltable1.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-primary-600 md:ml-2">
              <i class="fas fa-home mr-2"></i> Job Order Payroll
            </a>
          </li>
          <li>
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <a href="joborderpayroll.php" class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-700">General Payroll</a>
            </div>
          </li>
          <li aria-current="page">
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <span class="ml-1 text-sm font-medium text-blue-700 md:ml-2">Job Order Obligation Request</span>
            </div>
          </li>
        </ol>
      </nav>
    </div>

    <!-- FORM CONTAINER - MATCHING THE JOB ORDER STRUCTURE -->
    <div class="form-container">
      <!-- HEADER -->
      <div class="flex  border-2 border-black w-full">
        <div class="text-center font-bold text-sm w-[600px] ">
          <p>OBLIGATION REQUEST AND STATUS</p>
          <p>LOCAL GOVERNMENT UNIT OF PALUAN</p>
        </div>

        <!-- SERIAL NO, DATE, FUND CLUSTER -->
        <div class="flex flex-col font-semibold text-xs border-l-[2px] border-black">
          <div>Serial No.: <span class="border-b border-black w-[178px] inline-block"></span></div>
          <div>Date: <span class="border-b border-black w-[208px] inline-block"></span></div>
          <div>Fund Cluster: <span class="border-b border-black w-[160px] inline-block"></span></div>
        </div>
      </div>

      <!-- PAYEE INFORMATION TABLE -->
      <div class="table-container">
        <table class="w-full text-xs text-left border-t-0 text-gray-900 border-collapse form-table">
          <thead>
            <tr>
              <td class="w-[13.5%] header-cell">Payee</td>
              <td colspan="4" class="font-bold uppercase">CHARLENE U. CAJAYON</td>
            </tr>
            <tr>
              <td class="w-[13.5%] header-cell">Office</td>
              <td colspan="4">Office of the Municipal Assessor</td>
            </tr>
            <tr>
              <td class="w-[13.5%] header-cell">Address</td>
              <td colspan="4">Paluan, Occidental Mindoro</td>
            </tr>
            <tr class="border-t-2 border-black">
              <td class="w-[13.5%] header-cell">Responsibility Center</td>
              <td class="text-center w-[30%]">Particulars</td>
              <td class="text-center w-[7%]">MFO/PAP</td>
              <td class="text-center w-[10%]">UACS Object Code</td>
              <td class="text-center w-[20%]">Amount</td>
            </tr>
          </thead>
          <tbody>
            <tr class="">
              <td class="text-center"></td>
              <td class="font-bold text-center">
                <div class="flex flex-col h-[300px] justify-between">
                  <div class="flex flex-col">
                    <div>WAGES</div>
                    <div>September 1-15, 2025</div>
                  </div>
                  <div class="justify-end flex">Total</div>
                </div>
              </td>
              <td class="mobile-hide"></td>
              <td class="mobile-hide"></td>
              <td class="text-right font-bold">
                <div class="flex flex-col h-[300px] justify-between">
                  <div>
                    2,250.00
                  </div>
                  <div class="flex flex-row justify-between">
                    <div>
                      ₱
                    </div>
                    <div>
                      2,250.00
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- CERTIFICATION SECTION A -->

      <!-- CERTIFICATION SECTION B -->
      <div class="flex flex-row w-full border-t-0 border border-black">
        <!-- Set A  -->
        <div class="certification-box w-[740px]">
          <p class="mb-4 font-medium"><span class="font-bold">A. Certified:</span> Charges to appropriation/allotment are necessary, lawful and under my direct supervision; and supporting documents valid, proper and legal</p>

          <div class="flex flex-col font-semibold w-full">
            <div class="w-full mb-[-3px]">Signature<span class="ml-[22px] mr-1">:</span><span class="border-b border-black w-[365px] inline-block"></span></div>
            <div class="w-full mb-[-3px]">Printed Name: <span class="uppercase font-bold ml-[120px]">MELODY V. PAGLICAWAN</span></div>
            <div class="w-full">Position <span class="ml-[27px]">:</span><span class="font-bold ml-[143px]">Municipal Assessor</span></div>
          </div>
          <div class="mt-3 text-center">Head, Requesting Office/Authorized Representative</div>
          <div class="w-full font-semibold mt-2 mb-2">Date<span class="ml-[52px] mr-3">:</span><span class="border-b border-black w-[350px] inline-block "></span></div>
        </div>
        <!-- Set B  -->
        <div class="certification-box w-[685px]">
          <p class="mb-4 font-medium"><span class="font-bold">B. Certified:</span> Allotment available and obligated for the purpose/adjustment necessary as indicated above</p>

          <div class="flex flex-col font-semibold w-full">
            <div class="w-full mb-[-3px]">Signature<span class="ml-[22px] mr-1">:</span><span class="border-b border-black w-[330px] inline-block"></span></div>
            <div class="w-full mb-[-3px]">Printed Name: <span class="uppercase font-bold ml-[90px]">EFIGINIA V. SAN AGUSTIN</span></div>
            <div class="w-full">Position <span class="ml-[27px]">:</span><span class="font-bold ml-[97px]">Municipal Budget Officer</span></div>
          </div>
          <div class="w-full font-semibold mt-9 mb-2">Date<span class="ml-[52px] mr-3">:</span><span class="border-b border-black w-[316px] inline-block "></span></div>
        </div>
      </div>

      <!-- STATUS OF OBLIGATION SECTION -->
      <div class="">
        <p class="font-bold border-2 border-t-0 border-black px-1 py-1">C. STATUS OF OBLIGATION</p>
        <div class="table-container">
          <table class="w-full text-sm text-left text-gray-900 border-collapse form-table">
            <thead>
              <tr class="font-bold">
                <th class="text-center" colspan="3">Reference</th>
                <th class="text-center" colspan="5">Amount</th>
              </tr>
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
    <div class="action-buttons print-hide">
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

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Update date and time
      function updateDateTime() {
        const now = new Date();
        const dateOptions = {
          weekday: 'long',
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        };
        const timeOptions = {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit'
        };

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

      // User dropdown functionality
      const userMenuButton = document.getElementById('user-menu-button');
      const userDropdown = document.getElementById('user-dropdown');

      if (userMenuButton && userDropdown) {
        userMenuButton.addEventListener('click', function(e) {
          e.stopPropagation();
          userDropdown.classList.toggle('active');
          userMenuButton.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
          if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
            userDropdown.classList.remove('active');
            userMenuButton.classList.remove('active');
          }
        });
      }

      // Mobile sidebar toggle functionality
      const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
      const sidebar = document.getElementById('sidebar');
      const sidebarOverlay = document.getElementById('sidebar-overlay');

      if (mobileMenuToggle && sidebar && sidebarOverlay) {
        mobileMenuToggle.addEventListener('click', function() {
          sidebar.classList.toggle('active');
          sidebarOverlay.classList.toggle('active');
          // Prevent body scroll when sidebar is open
          document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        });

        sidebarOverlay.addEventListener('click', function() {
          sidebar.classList.remove('active');
          sidebarOverlay.classList.remove('active');
          document.body.style.overflow = '';
        });

        // Close sidebar when clicking on a link (for mobile)
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
          link.addEventListener('click', function() {
            if (window.innerWidth < 1024) {
              sidebar.classList.remove('active');
              sidebarOverlay.classList.remove('active');
              document.body.style.overflow = '';
            }
          });
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

        payrollToggle.addEventListener('click', function(e) {
          e.preventDefault();
          payrollDropdown.classList.toggle('open');
          const chevron = this.querySelector('.chevron');
          if (chevron) {
            chevron.classList.toggle('rotated');
          }
        });
      }

      // Handle window resize
      window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        if (window.innerWidth >= 1024) {
          // On desktop, ensure sidebar is visible and overlay is hidden
          if (sidebar) sidebar.classList.remove('active');
          if (sidebarOverlay) sidebarOverlay.classList.remove('active');
          document.body.style.overflow = '';
        }
      });

      // Save button functionality
      const saveButton = document.getElementById('save-button');
      if (saveButton) {
        saveButton.addEventListener('click', function() {
          // Create a simple alert or modal for save confirmation
          const modal = document.createElement('div');
          modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
          modal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4">
              <h3 class="text-lg font-semibold text-gray-900 mb-2">Save Obligation Request</h3>
              <p class="text-gray-600 mb-4">Are you sure you want to save this job order obligation request data?</p>
              <div class="flex justify-end space-x-3">
                <button class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg" id="cancel-save">Cancel</button>
                <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700" id="confirm-save">Save</button>
              </div>
            </div>
          `;

          document.body.appendChild(modal);

          document.getElementById('cancel-save').addEventListener('click', function() {
            document.body.removeChild(modal);
          });

          document.getElementById('confirm-save').addEventListener('click', function() {
            // Simulate save operation
            console.log('Saving job order obligation request data...');
            alert('Job order obligation request data has been saved successfully!');
            document.body.removeChild(modal);
          });

          // Close modal when clicking outside
          modal.addEventListener('click', function(e) {
            if (e.target === modal) {
              document.body.removeChild(modal);
            }
          });
        });
      }

      // Print form function
      window.printForm = function() {
        window.print();
      }

      // After print event
      window.addEventListener('afterprint', function() {
        // Restore body overflow
        document.body.style.overflow = '';
      });
    });
  </script>
</body>
</html>