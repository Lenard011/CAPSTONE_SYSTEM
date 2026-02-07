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
  <!-- Use only one version of Flowbite -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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

    /* Table responsive design */
    .table-container {
      overflow-x: auto;
      border-radius: 0.5rem;
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
      -webkit-overflow-scrolling: touch;
    }

    .payroll-table {
      min-width: 1200px;
      width: 100%;
      border-collapse: collapse;
    }

    .payroll-table th,
    .payroll-table td {
      padding: 0.75rem;
      border: 1px solid #e5e7eb;
      white-space: nowrap;
      text-align: left;
    }

    .payroll-table th {
      background-color: #f8fafc;
      font-weight: 600;
      color: #374151;
    }

    .payroll-table tbody tr:hover {
      background-color: #f9fafb;
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

    /* Custom styles for the table to ensure border collapse and thin borders */
    .payroll-table,
    .payroll-table th,
    .payroll-table td {
      border: 1px solid #e5e7eb;
      border-collapse: collapse;
      font-size: 0.65rem;
      padding: 4px;
      vertical-align: top;
    }

    /* Styling for the header rows */
    .payroll-table thead tr th {
      background-color: #eff6ff;
      color: #1f2937;
      text-align: center;
      font-weight: 700;
    }

    /* Make the column headers more readable by forcing text wrap */
    .payroll-table th {
      word-wrap: break-word;
    }

    /* Center all cells in the first column (#) */
    .payroll-table tbody tr td:first-child {
      text-align: center;
    }

    /* CSS to ensure only the form content is visible and formatted for print */
    @media print {

      /* Force all text to be black */
      * {
        color: #000000 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      /* Ensure the payroll container prints properly */
      .max-w-7xl.mx-auto.bg-white.shadow-lg.p-2.mt-4 {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 5mm !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        border: 1px solid #000 !important;
        width: 100% !important;
        page-break-inside: avoid !important;
        page-break-after: avoid !important;
      }

      /* Ensure table fits perfectly */
      .payroll-table {
        width: 100% !important;
        font-size: 8px !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
        page-break-inside: avoid !important;
      }

      /* Thin borders for all cells */
      .payroll-table th,
      .payroll-table td {
        border: 0.5px solid #000000 !important;
        padding: 2px 3px !important;
        line-height: 1.1 !important;
        min-height: 18px !important;
        vertical-align: middle !important;
      }

      /* Table header styling */
      .payroll-table thead th {
        background-color: #f0f0f0 !important;
        font-weight: bold !important;
        border: 0.5px solid #000000 !important;
        font-size: 8px !important;
        padding: 3px 4px !important;
      }

      /* Signature sections */
      .section-box {
        page-break-inside: avoid !important;
        border: 0.5px solid #000000 !important;
        margin-bottom: 2mm !important;
        padding: 1.5mm !important;
        min-height: 25mm !important;
        font-size: 9px !important;
      }
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
  </style>
  <style>
    .payroll-table {
      min-width: 1400px;
    }

    .payroll-table th,
    .payroll-table td {
      white-space: nowrap;
      border: 1px solid #e2e8f0;
    }

    .payroll-table td {
      padding: 8px 12px;
    }

    .payroll-table thead th {
      font-weight: 600;
      background: #f7fafc;
    }

    /* Make sure columns don't overlap */
    .payroll-table {
      table-layout: auto;
    }

    /* For printing */
    @media print {
      .payroll-table {
        min-width: 100% !important;
        font-size: 10px !important;
      }

      .payroll-table th,
      .payroll-table td {
        padding: 4px 6px !important;
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

  <!-- MAIN CONTENT (Added from contractualpayroll.php) -->
  <main class="main-content">
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
          <li>
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <a href="joborderobligationrequest.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-primary-600 md:ml-2"> Job Order Obligation Request</a>
            </div>
          </li>
        </ol>
      </nav>
    </div>

    <div class="max-w-7xl mx-auto bg-white shadow-lg rounded-lg p-2 mt-4">
      <!-- to print -->
      <div>
        <div class="absolute right-12">
          <p class="text-xs font-medium">Appendix 33</p>
        </div>
        <!-- Header Section with Appendix on top right -->
        <div class="relative mb-4 mt-5">
          <div class="text-center">
            <h1 class="text-lg font-bold">PAYROLL</h1>
          </div>

          <!-- Period section below PAYROLL title -->
          <div class="flex justify-center font-bold">
            <div class="flex items-center">
              <span class="whitespace-nowrap text-[12px]">For the period:</span>
              <div class="relative">
                <span class="relative z-10 px-1 text-[12px] uppercase">September 29, 2025</span>
                <div class="absolute bottom-0 mb-[4px] left-0 right-0 border-b border-gray-500"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Entity Information Section -->
        <div class="flex flex-col md:flex-row justify-between text-xs mb-4  pb-2">
          <div class="mb-2 md:mb-0">
            <div class="flex items-center mb-2">
              <strong class="whitespace-nowrap text-[12px]">Entity Name:</strong>
              <div class="relative flex-1">
                <span class="relative z-10 px-1 bg-white text-[11px]">LGU PALUAN</span>
                <div class="absolute bottom-0 left-0 right-0 w-[200px] border-b border-gray-500"></div>
              </div>
            </div>
            <div class="flex">
              <strong class="whitespace-nowrap text-[12px]">Fund/Cluster:</strong>
              <div class="relative flex-1">
                <div class="absolute bottom-0 left-0 right-0 w-[195px] border-b border-gray-500"></div>
              </div>
            </div>
          </div>
          <div class="text-left mr-20">
            <div class="flex justify-end mb-2">
              <strong class="whitespace-nowrap">Payroll No. :</strong>
              <div class="relative w-[119px]">
                <span class="relative z-10 px-1 bg-white"></span>
                <div class="absolute bottom-0 left-0 right-0 border-b border-gray-500"></div>
              </div>
            </div>
            <div class="flex justify-end">
              <strong class="whitespace-nowrap">Sheet:</strong>
              <div class="relative">
                <!-- Split the text into separate underlined parts -->
                <div class="flex justify-between">
                  <div class="relative">
                    <span class="relative z-10 px-1 bg-white ml-4">1</span>
                    <div class="absolute bottom-0 left-0 w-[50px] right-0 border-b border-gray-500"></div>
                  </div>
                  <span class="mx-1 ml-5 mr-1">of</span>
                  <div class="relative">
                    <span class="relative z-10 px-1 bg-white ml-4">1</span>
                    <div class="absolute bottom-0 left-0 w-[50px] right-0 border-b border-gray-500"></div>
                  </div>
                  <span class="ml-5">Sheets</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <p class="text-xs italic mb-4">
          We acknowledge receipt of cash shown opposite our names as full compensation for services rendered for the period covered.
        </p>

        <div class="payroll-table-container overflow-auto border bg-white">
          <table class="payroll-table w-full border-collapse text-sm">
            <thead class="bg-gray-100 text-gray-700">
              <!-- Header Row 1 -->
              <tr class="border-b uppercase">
                <th rowspan="2" class="border p-2 w-10">#</th>
                <th rowspan="2" class="border p-2">Name</th>
                <th rowspan="2" class="border p-2">Position</th>
                <th rowspan="2" class="border p-2">Address</th>
                <th colspan="3" class="border p-2 text-center bg-blue-50">Compensation</th>
                <th colspan="3" class="border p-2 text-center bg-red-50">Deductions</th>
                <th colspan="2" class="border p-2 text-center bg-green-50">Community Tax Certificate</th>
                <th rowspan="2" class="border p-2 text-center bg-green-50">
                  <div>Net Amount</div>
                  <div>Due</div>
                </th>
                <th rowspan="2" class="border p-2">Signature</th>
              </tr>

              <!-- Header Row 2 (Sub-headers) -->
              <tr class="border-b text-xs">
                <!-- Compensation sub-headers -->
                <th class="border p-1 print-header">
                  <div>Monthly</div>
                  <div>Salaries</div>
                  <div>and Wages</div>
                </th>
                <th class="border p-1 print-header">
                  <div>Other</div>
                  <div>Compen-</div>
                  <div>sation</div>
                </th>
                <th class="border p-1 print-header">
                  <div>Gross</div>
                  <div>Amount</div>
                  <div>Earned</div>
                </th>

                <!-- Deductions sub-headers -->
                <th class="border p-1 print-header">
                  <div>With-</div>
                  <div>holding</div>
                  <div>Tax</div>
                </th>
                <th class="border p-1 print-header">
                  <div>SSS</div>
                  <div>Contri-</div>
                  <div>bution</div>
                </th>
                <th class="border p-1 print-header">
                  <div>Total</div>
                  <div>Deduc-</div>
                  <div>tions</div>
                </th>

                <!-- Community Tax Certificate sub-headers -->
                <th class="border p-1 print-header">
                  <div>Number</div>
                </th>
                <th class="border p-1 print-header">
                  <div>Date</div>
                </th>
              </tr>
            </thead>

            <tbody>
              <!-- Row 1 -->
              <tr class="border-b hover:bg-gray-50">
                <td class="border p-2 text-center">1</td>
                <td class="border p-2">JASPER A. GARCIA</td>
                <td class="border p-2">MPIO Focal Person</td>
                <td class="border p-2">Paluan Occ. Mdo.</td>

                <!-- Compensation -->
                <td class="border p-2 text-right">20,000.00</td>
                <td class="border p-2 text-right"></td>
                <td class="border p-2 text-right">10,000.00</td>

                <!-- Deductions -->
                <td class="border p-2 text-right"></td>
                <td class="border p-2 text-right">780.00</td>
                <td class="border p-2 text-right">780.00</td>

                <!-- Community Tax Cert -->
                <td class="border p-2 text-center">08411568</td>
                <td class="border p-2 text-center">9/29/2025</td>

                <!-- Net Amount -->
                <td class="border p-2 text-right font-bold">19,220.00</td>

                <!-- Signature -->
                <td class="border p-2"></td>
              </tr>

              <!-- Row 2 -->
              <tr class="border-b hover:bg-gray-50">
                <td class="border p-2 text-center">2</td>
                <td class="border p-2">APRIL V. AGUAVILLA</td>
                <td class="border p-2">MPIO Focal Person</td>
                <td class="border p-2">Paluan Occ. Mdo.</td>

                <!-- Compensation -->
                <td class="border p-2 text-right">20,000.00</td>
                <td class="border p-2 text-right"></td>
                <td class="border p-2 text-right">10,000.00</td>

                <!-- Deductions -->
                <td class="border p-2 text-right"></td>
                <td class="border p-2 text-right">780.00</td>
                <td class="border p-2 text-right">780.00</td>

                <!-- Community Tax Cert -->
                <td class="border p-2 text-center">08411568</td>
                <td class="border p-2 text-center">9/29/2025</td>

                <!-- Net Amount -->
                <td class="border p-2 text-right font-bold">19,220.00</td>

                <!-- Signature -->
                <td class="border p-2"></td>
              </tr>

              <!-- Total Row -->
              <tr class="bg-gray-100 font-bold border-t-2">
                <td colspan="4" class="border p-2 text-right">TOTAL AMOUNT</td>
                <td class="border p-2 text-right">40,000.00</td>
                <td class="border p-2 text-right">0.00</td>
                <td class="border p-2 text-right">20,000.00</td>
                <td class="border p-2 text-right">0.00</td>
                <td class="border p-2 text-right">1,560.00</td>
                <td class="border p-2 text-right">1,560.00</td>
                <td class="border p-2"></td>
                <td class="border p-2"></td>
                <td class="border p-2 text-right">38,440.00</td>
                <td class="border p-2"></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div>

          <div class="grid grid-cols-1 md:grid-cols-3 text-xs">
            <!-- Set A -->
            <div class="section-box border  border-gray-300 px-2">
              <p class="font-bold">A. CERTIFIED: Services duly rendered as stated.</p>
              <div class="flex flex-row mt-10 items-end justify-center w-full">
                <div class="">
                  <p class="font-bold text-center">JOREL B. VICENTE</p>
                  <p class="text-center  border-gray-600 italic text-[11px] ">Administrative Officer IV (HRMO II)</p>
                </div>
                <div class="flex flex-row  ml-4">
                  <p class="relative">Date</p>
                  <p class="border-b border-gray-600 min-w-[70px]"></p>
                </div>
              </div>
            </div>

            <!-- Set C -->
            <div class="section-box border border-gray-300 px-2">
              <div class="flex justify-between">
                <p class="font-bold">C. CERTIFIED: Cash available in the amount of</p>
                <p class="font-bold text-right border-b border-gray-600 min-w-[50px]"><span>₱ </span>2,250.00</p>
              </div>
              <div class="flex flex-row mt-10 items-end justify-between w-full">
                <div class="flex-1"></div> <!-- Empty spacer left -->
                <div class="mr-5">
                  <p class="font-bold text-center">ARLENE A. DE VEAS</p>
                  <p class="text-center border-gray-600 italic text-[11px]">Municipal Treasurer</p>
                </div>
                <div class="flex flex-row items-end">
                  <p class="relative">Date</p>
                  <p class="border-b border-gray-600 min-w-[70px] ml-2"></p>
                </div>
              </div>
            </div>

            <!-- Set F -->
            <div class="section-box border border-gray-300 px-2">
              <div class="flex justify-between">
                <p class="font-bold">F. CERTIFIED: Each employee whose name appears on the payroll has been paid the amount as indicated opposite his/her name.</p>
              </div>
              <div class="mt-5">
                <p class="font-bold text-center">EVA V. DUEÑAS</p>
                <p class="text-center border-gray-600 italic text-[11px]">Disbursing Officer</p>
              </div>
            </div>
          </div>


          <div class="grid grid-cols-1 md:grid-cols-3 text-xs">
            <!-- Set B -->
            <div class="section-box border  border-gray-300 px-2">
              <p class="font-bold">B. CERTIFIED: Supporting documents complete and proper.</p>
              <div class="flex flex-row mt-5 items-end justify-center w-full">
                <div class="">
                  <p class="font-bold text-center">JULIE ANNE T. VALLESTERO, CPA</p>
                  <p class="text-center  border-gray-600 italic text-[11px] ">Municipal Accountant</p>
                </div>
                <div class="flex flex-row  ml-4">
                  <p class="relative">Date</p>
                  <p class="border-b border-gray-600 min-w-[70px]"></p>
                </div>
              </div>
            </div>

            <!-- Set D -->
            <div class="section-box border  border-gray-300 px-2">
              <p class="font-bold">D. APPROVED: For payment</p>
              <div class="flex flex-row mt-10 items-end justify-center w-full">
                <div class="">
                  <p class="font-bold text-center">HON. MICHAEL D. DIAZ</p>
                  <p class="text-center  border-gray-600 italic text-[11px] ">Municipal Mayor</p>
                </div>
                <div class="flex flex-row  ml-4">
                  <p class="relative">Date</p>
                  <p class="border-b border-gray-600 min-w-[70px]"></p>
                </div>
              </div>
            </div>

            <!-- Set E -->
            <div class="section-box border border-gray-300 p-2">
              <p class="font-bold">E.</p>
              <div class="flex justify-between">
                <p>ORS/BURS No. :</p>
                <p class="border-b border-gray-600 w-3/5"></p>
              </div>
              <div class="flex justify-between">
                <p>Date</p>
                <p class="border-b border-gray-600 w-3/5"></p>
              </div>
              <div class="flex justify-between">
                <p>Jev No. :</p>
                <p class="border-b border-gray-600 w-3/5"></p>
              </div>
              <div class="flex justify-between">
                <p>Date</p>
                <p class="border-b border-gray-600 w-3/5"></p>
              </div>
            </div>

          </div>
        </div>
      </div>

      <div class="mt-8 pt-4 border-t border-gray-200 print-hide">
        <div class="flex flex-col md:flex-row justify-end space-y-3 md:space-y-0 md:space-x-4">
          <button id="print-btn" type="button" class="flex items-center justify-center text-gray-900 bg-white border border-gray-300 hover:bg-gray-50 focus:ring-2 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-3 w-full md:w-auto transition-all duration-200 shadow-sm hover:shadow">
            <svg class="w-4 h-4 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
              <path d="M5 20h10a1 1 0 0 0 1-1v-5H4v5a1 1 0 0 0 1 1Z" />
              <path d="M18 7H2a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2v-3a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Zm-1-2V2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3h14Z" />
            </svg>
            Print Payroll
          </button>

          <button id="save-btn" type="button" class="flex items-center justify-center text-white bg-green-600 hover:bg-green-700 focus:ring-2 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-3 w-full md:w-auto transition-all duration-200 shadow-sm hover:shadow">
            <svg class="w-4 h-4 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
              <path d="M14.707 7.793a1 1 0 0 0-1.414 0L11 10.086V1.5a1 1 0 0 0-2 0v8.586L6.707 7.793a1 1 0 1 0-1.414 1.414l4 4a1 1 0 0 0 1.416 0l4-4a1 1 0 0 0-.002-1.414Z" />
              <path d="M18 12h-2.55l-2.975 2.975a3.5 3.5 0 0 1-4.95 0L4.55 12H2a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2Zm-3 5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z" />
            </svg>
            Save Changes
          </button>

          <a href="joborderobligationrequest.php" class="w-full md:w-auto">
            <button id="next-btn" type="button" class="flex items-center justify-center text-white bg-blue-600 hover:bg-blue-700 focus:ring-2 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-3 w-full md:w-auto transition-all duration-200 shadow-sm hover:shadow">
              Next
              <svg class="w-4 h-4 ml-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9" />
              </svg>
            </button>
          </a>
        </div>
      </div>
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

      // Print button functionality
      const printButton = document.getElementById('print-btn');
      if (printButton) {
        printButton.addEventListener('click', function() {
          // Store original values
          const originalBodyOverflow = document.body.style.overflow;
          const originalBodyBackground = document.body.style.backgroundColor;

          // Get the exact payroll container div
          const payrollContent = document.querySelector('.max-w-7xl.mx-auto.bg-white.shadow-lg.rounded-lg.p-2.mt-4');

          // Create a deep clone with all styles preserved
          const clone = payrollContent.cloneNode(true);

          // Remove any print-hide elements from the clone
          clone.querySelectorAll('.print-hide').forEach(el => {
            if (el.parentNode) {
              el.parentNode.removeChild(el);
            }
          });

          // Remove any buttons and navigation from the clone
          clone.querySelectorAll('button, a[href*="logout"], .mt-8.pt-4.border-t.border-gray-200').forEach(el => {
            if (el.parentNode) {
              el.parentNode.removeChild(el);
            }
          });

          // Create a clean print container
          const printContainer = document.createElement('div');
          printContainer.id = 'print-container';
          printContainer.style.cssText = `
                        position: fixed !important;
                        top: 0 !important;
                        left: 0 !important;
                        width: 100% !important;
                        height: 100% !important;
                        z-index: 99999 !important;
                        background: white !important;
                        padding: 0 !important;
                        margin: 0 !important;
                        overflow: visible !important;
                        visibility: visible !important;
                        display: block !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    `;

          // Style the cloned content for printing
          clone.style.cssText = `
                        max-width: 100% !important;
                        margin: 0 auto !important;
                        padding: 10mm !important;
                        box-shadow: none !important;
                        border-radius: 0 !important;
                        border: 1px solid #000 !important;
                        width: 100% !important;
                        page-break-inside: avoid !important;
                        page-break-after: avoid !important;
                        background: white !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    `;

          // Ensure all text is black for printing
          clone.querySelectorAll('*').forEach(el => {
            el.style.color = '#000000 !important';
            el.style.backgroundColor = 'transparent !important';
          });

          // Apply print-specific table styling
          const table = clone.querySelector('.payroll-table');
          if (table) {
            table.style.cssText = `
                            width: 100% !important;
                            font-size: 8px !important;
                            table-layout: fixed !important;
                            border-collapse: collapse !important;
                            page-break-inside: avoid !important;
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;
                        `;

            // Style all table cells
            table.querySelectorAll('th, td').forEach(cell => {
              cell.style.cssText = `
                                border: 0.5px solid #000000 !important;
                                padding: 2px 3px !important;
                                line-height: 1.1 !important;
                                height: auto !important;
                                min-height: 18px !important;
                                vertical-align: middle !important;
                                color: #000000 !important;
                                background-color: transparent !important;
                                -webkit-print-color-adjust: exact !important;
                                print-color-adjust: exact !important;
                                font-size: 8px !important;
                            `;
            });

            // Style table headers
            table.querySelectorAll('thead th').forEach(th => {
              th.style.cssText = `
                                background-color: #f0f0f0 !important;
                                font-weight: bold !important;
                                border: 0.5px solid #000000 !important;
                                font-size: 8px !important;
                                padding: 3px 4px !important;
                                color: #000000 !important;
                                -webkit-print-color-adjust: exact !important;
                                print-color-adjust: exact !important;
                            `;
            });
          }

          // Style section boxes
          clone.querySelectorAll('.section-box').forEach(box => {
            box.style.cssText = `
                            page-break-inside: avoid !important;
                            border: 0.5px solid #000000 !important;
                            margin-bottom: 2mm !important;
                            padding: 1.5mm !important;
                            height: auto !important;
                            min-height: 25mm !important;
                            font-size: 9px !important;
                            color: #000000 !important;
                            background-color: transparent !important;
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;
                        `;
          });

          // Add the clone to print container
          printContainer.appendChild(clone);

          // Add print container to body
          document.body.appendChild(printContainer);

          // Hide all other elements
          const allElements = document.body.children;
          for (let element of allElements) {
            if (element.id !== 'print-container') {
              element.style.visibility = 'hidden';
              element.style.display = 'none';
            }
          }

          // Set body for printing
          document.body.style.cssText = `
                        background: white !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        width: 100% !important;
                        height: auto !important;
                        overflow: visible !important;
                        visibility: visible !important;
                    `;

          // Set print page size and margins
          const style = document.createElement('style');
          style.innerHTML = `
                    @page {
                        size: landscape;
                        margin: 0.25cm 0.5cm;
                    }
                    
                    @media print {
                        * {
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;
                        }
                        
                        body {
                            background: white !important;
                            margin: 0 !important;
                            padding: 0 !important;
                        }
                        
                        #print-container {
                            position: absolute !important;
                            left: 0 !important;
                            top: 0 !important;
                            width: 100% !important;
                            height: auto !important;
                            margin: 0 !important;
                            padding: 0 !important;
                            background: white !important;
                            overflow: visible !important;
                        }
                    }
                `;
          document.head.appendChild(style);

          // Wait a moment for styles to apply, then print
          setTimeout(() => {
            window.print();

            // Clean up after printing
            setTimeout(() => {
              // Remove print container and style
              if (document.getElementById('print-container')) {
                document.body.removeChild(printContainer);
              }
              if (style.parentNode) {
                style.parentNode.removeChild(style);
              }

              // Restore visibility of all elements
              for (let element of allElements) {
                element.style.visibility = '';
                element.style.display = '';
              }

              // Restore original values
              document.body.style.overflow = originalBodyOverflow;
              document.body.style.backgroundColor = originalBodyBackground;
              document.body.style.background = '';
            }, 500);
          }, 100);
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
      const saveButton = document.getElementById('save-btn');
      if (saveButton) {
        saveButton.addEventListener('click', function() {
          alert('Changes saved successfully!');
        });
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