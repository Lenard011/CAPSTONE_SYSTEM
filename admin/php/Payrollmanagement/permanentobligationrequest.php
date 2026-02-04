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
  <title>Permanent Payroll - Obligation Request</title>
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
    /* Custom styles for the form */
    .form-container {
      font-family: Arial, sans-serif;
      border: 2px solid #000;
      max-width: 1000px;
      margin: 20px auto;
      padding: 10px;
      font-size: 10px;
      background-color: white;
    }

    .form-table {
      border-collapse: collapse;
      width: 100%;
    }

    .form-table th,
    .form-table td {
      border: 1px solid #000;
      padding: 4px;
      line-height: 1.2;
      height: 25px;
      vertical-align: top;
      font-size: 9px;
    }

    .form-table thead th {
      background-color: #f0f0f0;
      font-weight: bold;
      text-align: center;
      vertical-align: middle;
      padding: 6px 4px;
    }

    .header-cell {
      background-color: #f0f0f0;
      font-weight: bold;
      padding: 6px 4px;
    }

    .section-box {
      border: 1px solid #000;
      padding: 8px;
      min-height: 120px;
    }

    .signature-line {
      border-bottom: 1px solid #000;
      margin: 5px 0 2px 0;
      line-height: 1;
      min-height: 20px;
    }

    .signature-space {
      height: 20px;
    }

    /* Total row styling */
    .total-row {
      font-weight: bold;
      background-color: #f8f9fa;
    }

    /* Improved print styles - EXACT MATCH TO IMAGE */
    @media print {
      @page {
        size: A4 portrait;
        margin: 10mm;
      }

      body {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
        font-size: 10px !important;
        line-height: 1.2 !important;
        color: black !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      /* Hide all UI elements */
      .action-buttons,
      .action-cell,
      nav,
      aside,
      .breadcrumb-container,
      .navbar,
      .sidebar-container,
      #sidebar-overlay,
      #sidebar-toggle,
      .user-menu,
      .datetime-container,
      .mobile-toggle,
      .main-content > nav,
      .breadcrumb-container {
        display: none !important;
      }

      /* Center the form on the page */
      body * {
        visibility: hidden;
      }
      
      .form-container,
      .form-container * {
        visibility: visible;
      }
      
      .form-container {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        top: 0;
        border: 2px solid #000 !important;
        margin: 0 !important;
        padding: 8px !important;
        box-shadow: none !important;
        max-width: 100% !important;
        width: 100% !important;
        page-break-inside: avoid !important;
        font-size: 10px !important;
        background: white !important;
      }

      /* Fix table layout for print */
      .form-table {
        width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
      }

      .form-table th,
      .form-table td {
        font-size: 9px !important;
        padding: 4px !important;
        height: 25px !important;
        border: 1px solid #000 !important;
        page-break-inside: avoid !important;
        color: black !important;
        background: white !important;
      }

      .form-table thead th {
        background-color: #f0f0f0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      /* Ensure proper text visibility */
      .form-container,
      .form-table,
      .section-box {
        color: black !important;
        background: white !important;
      }

      /* Prevent page breaks inside important elements */
      .form-container {
        page-break-inside: avoid !important;
      }

      .section-box {
        page-break-inside: avoid !important;
      }

      /* Remove all margins and padding from main */
      main {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        background: white !important;
      }
    }

    /* Main layout styles */
    :root {
      --primary: #1e40af;
      --secondary: #1e3a8a;
      --accent: #3b82f6;
      --gradient-nav: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
    }

    body {
      font-family: 'Inter', sans-serif;
      background: #f8fafc;
      min-height: 100vh;
      overflow-x: hidden;
      color: #1f2937;
    }

    /* Navbar */
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

    /* Brand styling */
    .navbar-brand {
      display: flex;
      align-items: center;
      gap: 1rem;
      text-decoration: none;
      transition: transform 0.3s ease;
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

    /* Date & Time */
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

    /* Sidebar */
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

    .main-content {
      margin-top: 70px;
      padding: 1.5rem;
      transition: all 0.3s ease;
      min-height: calc(100vh - 70px);
      width: 100%;
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
        width: calc(100% - 16rem);
      }
    }

    /* Sidebar Overlay */
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

    /* Mobile Responsive */
    @media (max-width: 768px) {
      .datetime-container {
        display: none;
      }

      .brand-text {
        display: none;
      }

      .main-content {
        padding: 1rem;
        margin-left: 0 !important;
      }

      .sidebar-container.active + .main-content {
        margin-left: 0 !important;
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
        <button class="mobile-toggle md:hidden" id="sidebar-toggle">
          <i class="fas fa-bars"></i>
        </button>

        <!-- Logo and Brand -->
        <a href="../dashboard.php" class="navbar-brand">
          <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
          <div class="brand-text hidden md:flex">
            <span class="brand-title">HR Management System</span>
            <span class="brand-subtitle">Paluan Occidental Mindoro</span>
          </div>
          <div class="md:hidden ml-2">
            <span class="text-white font-bold">HRMS</span>
          </div>
        </a>
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

  <!-- Sidebar Overlay -->
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

  <!-- Main Content -->
  <main class="main-content">
    <!-- Breadcrumb -->
    <nav class="mb-6" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-2">
        <li class="inline-flex items-center">
          <a href="permanentpayrolltable1.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
            <i class="fas fa-home mr-2"></i> Permanent Payroll
          </a>
        </li>
        <li>
          <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
            <a href="permanentpayroll.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600">General Payroll</a>
          </div>
        </li>
        <li>
          <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
            <span class="ml-1 text-sm font-medium text-blue-600">Obligation Request</span>
          </div>
        </li>
      </ol>
    </nav>

    <!-- Obligation Request Form -->
    <div class="form-container">
      <div class="text-center font-bold text-sm mb-1">
        <p>OBLIGATION REQUEST AND STATUS</p>
        <p>LOCAL GOVERNMENT UNIT OF PALUAN</p>
      </div>
      <div class="text-right text-[8px] mb-2">Appendix 11</div>

      <!-- Header Information -->
      <div class="grid grid-cols-4 gap-2 mb-2 text-[10px]">
        <div class="col-span-2"></div>
        <div class="col-span-2 flex justify-between space-x-2">
          <p>Serial No.: <span class="border-b border-black w-24 inline-block"></span></p>
          <p>Date: <span class="border-b border-black w-24 inline-block"></span></p>
          <p>Fund Cluster: <span class="border-b border-black w-24 inline-block"></span></p>
        </div>
      </div>

      <!-- Payee Information -->
      <div class="overflow-x-auto relative mb-4">
        <table class="w-full text-xs text-left text-gray-900 border-collapse form-table">
          <tbody>
            <tr>
              <td class="w-[15%] header-cell">Payee</td>
              <td class="w-[85%]">EMILY E. ARCONADA & CO.</td>
            </tr>
            <tr>
              <td class="w-[15%] header-cell">Office</td>
              <td class="w-[85%]">Office of the MSWD, Agriculture, & MDRRM</td>
            </tr>
            <tr>
              <td class="w-[15%] header-cell">Address</td>
              <td class="w-[85%]">Paluan, Occidental Mindoro</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Main Table - IMPROVED DESIGN -->
      <div class="overflow-x-auto relative">
        <table class="w-full text-xs text-left text-gray-900 border-collapse form-table">
          <thead>
            <tr>
              <th class="w-[15%] text-center">Responsibility Center</th>
              <th class="w-[40%] text-center">Particulars</th>
              <th class="w-[10%] text-center">MFO/PAP</th>
              <th class="w-[15%] text-center">UACS Object Code</th>
              <th class="w-[10%] text-center">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr class="item-row" data-id="1">
              <td class="text-center align-top"></td>
              <td class="align-top">
                <div class="font-medium">SALARY</div>
                <div class="text-[8px]">September 16-30, 2025</div>
              </td>
              <td class="text-center align-top"></td>
              <td class="text-center align-top"></td>
              <td class="text-right align-top font-medium">274,959.00</td>
            </tr>
            <tr class="item-row" data-id="2">
              <td class="text-center align-top"></td>
              <td class="align-top"></td>
              <td class="text-center align-top"></td>
              <td class="text-center align-top"></td>
              <td class="text-right align-top"></td>
            </tr>
            <tr class="total-row">
              <td colspan="4" class="text-center font-bold">Total</td>
              <td class="text-right font-bold">274,959.00</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Certifications Section -->
      <div class="grid grid-cols-2 gap-x-4 mt-6 text-xs">
        <div class="section-box">
          <p class="font-bold border-b border-black inline-block px-1">A.</p>
          <p class="mt-2 text-[0.65rem] leading-tight">
            Certified: Charges to appropriation/allotment are necessary, lawful
            and under my direct supervision; and supporting documents valid, proper and legal
          </p>

          <div class="signature-space"></div>
          <div class="grid grid-cols-2 gap-2 mt-4">
            <p class="font-medium">Signature:</p>
            <div class="signature-line"></div>
          </div>

          <div class="grid grid-cols-2 gap-2 mt-2">
            <p class="font-medium">Printed Name:</p>
            <p class="font-bold text-[0.7rem]">HON. MICHAEL D. DIAZ</p>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <p class="font-medium">Position:</p>
            <p class="text-[0.7rem]">Municipal Mayor</p>
          </div>

          <div class="text-center font-bold text-[0.65rem] mt-4">
            <p>Head, Requesting Office/Authorized Representative</p>
          </div>

          <div class="grid grid-cols-2 gap-2 mt-4">
            <p class="font-medium">Date:</p>
            <div class="signature-line"></div>
          </div>
        </div>

        <div class="section-box">
          <p class="font-bold border-b border-black inline-block px-1">B.</p>
          <p class="mt-2 text-[0.65rem] leading-tight">
            Certified: Allotment available and obligated for the
            purpose/adjustment necessary as indicated above
          </p>

          <div class="signature-space"></div>
          <div class="grid grid-cols-2 gap-2 mt-4">
            <p class="font-medium">Signature:</p>
            <div class="signature-line"></div>
          </div>

          <div class="grid grid-cols-2 gap-2 mt-2">
            <p class="font-medium">Printed Name:</p>
            <p class="font-bold text-[0.7rem]">EFIGENIA V. SAN AGUSTIN</p>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <p class="font-medium">Position:</p>
            <p class="text-[0.7rem]">Municipal Budget Officer</p>
          </div>

          <div class="grid grid-cols-2 gap-2 mt-8">
            <p class="font-medium">Date:</p>
            <div class="signature-line"></div>
          </div>
        </div>
      </div>

      <!-- Status of Obligation Section -->
      <div class="mt-6 border border-black">
        <p class="font-bold border-b border-black px-2 py-1 bg-gray-100">C. STATUS OF OBLIGATION</p>
        <table class="w-full text-xs text-left text-gray-900 border-collapse form-table">
          <thead>
            <tr>
              <th class="w-[10%] text-center align-middle" rowspan="2">Date</th>
              <th class="w-[20%] text-center align-middle" rowspan="2">Particulars</th>
              <th class="w-[15%] text-center align-middle" rowspan="2">ORS/JEV/Check/ADA/TRA No.</th>
              <th class="w-[15%] text-center align-middle">Obligation</th>
              <th class="w-[15%] text-center align-middle">Payable</th>
              <th class="w-[10%] text-center align-middle">Payment</th>
              <th class="w-[15%] text-center align-middle" colspan="2">Balance</th>
            </tr>
            <tr>
              <th class="text-center align-middle text-[8px]">(a)</th>
              <th class="text-center align-middle text-[8px]">(b)</th>
              <th class="text-center align-middle text-[8px]">(c)</th>
              <th class="text-center align-middle text-[8px]">Not Yet Due<br>(a-b)</th>
              <th class="text-center align-middle text-[8px]">Due and Demandable<br>(b-c)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="h-10"></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
            </tr>
            <tr>
              <td class="h-10"></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons flex justify-center space-x-4 mt-6 mb-10">
      <button onclick="printForm()"
        class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 transition duration-200">
        <i class="fas fa-print mr-2"></i>
        Print
      </button>
      <button id="save-button"
        class="text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 transition duration-200">
        <i class="fas fa-save mr-2"></i>
        Save Data
      </button>
    </div>
  </main>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
  
  <script>
    // Date and Time functionality
    function updateDateTime() {
      const now = new Date();
      
      // Format date
      const dateOptions = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
      };
      const dateString = now.toLocaleDateString('en-US', dateOptions);
      
      // Format time
      const timeString = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit',
        hour12: true 
      });
      
      // Update elements
      document.getElementById('current-date').textContent = dateString;
      document.getElementById('current-time').textContent = timeString;
    }
    
    // Update immediately and every second
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Sidebar toggle functionality
    document.getElementById('sidebar-toggle').addEventListener('click', function() {
      const sidebar = document.getElementById('sidebar-container');
      const overlay = document.getElementById('sidebar-overlay');
      
      sidebar.classList.toggle('active');
      overlay.classList.toggle('active');
    });
    
    document.getElementById('sidebar-overlay').addEventListener('click', function() {
      const sidebar = document.getElementById('sidebar-container');
      const overlay = document.getElementById('sidebar-overlay');
      
      sidebar.classList.remove('active');
      overlay.classList.remove('active');
    });
    
    // Payroll dropdown toggle
    document.getElementById('payroll-toggle').addEventListener('click', function(e) {
      e.preventDefault();
      const dropdown = document.getElementById('payroll-dropdown');
      const chevron = this.querySelector('.chevron');
      
      dropdown.classList.toggle('open');
      chevron.classList.toggle('rotated');
    });
    
    // User dropdown toggle
    document.getElementById('user-menu-button').addEventListener('click', function() {
      const dropdown = document.getElementById('user-dropdown');
      dropdown.classList.toggle('active');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
      const userMenu = document.getElementById('user-menu');
      const dropdown = document.getElementById('user-dropdown');
      
      if (!userMenu.contains(event.target)) {
        dropdown.classList.remove('active');
      }
    });
    
    // Save button functionality
    document.getElementById('save-button').addEventListener('click', function() {
      alert('Obligation Request data saved successfully!');
    });
    
    // Print function with better control
    function printForm() {
      window.print();
    }
    
    // Data structure for the form
    const itemData = {
      1: {
        rc: "",
        particulars: "SALARY\nSeptember 16-30, 2025",
        amount: "274959.00",
        mfo: "",
        uacs: ""
      }
    };
    
    // Print optimization
    window.addEventListener('beforeprint', function() {
      // Add any pre-print adjustments here
      console.log('Preparing for print...');
    });
    
    window.addEventListener('afterprint', function() {
      console.log('Print completed or cancelled');
    });
    
    // Initialize sidebar for desktop
    if (window.innerWidth >= 768) {
      document.getElementById('sidebar-container').classList.add('active');
    }
  </script>
</body>
</html>