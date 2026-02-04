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
  <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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
    /* Custom styles for payroll form */
    .payroll-container {
      font-family: Arial, sans-serif;
      border: 2px solid #000;
      max-width: 1100px;
      margin: 20px auto;
      padding: 10px;
      font-size: 10px;
      background-color: white;
    }

    /* Table styling */
    .payroll-table th, .payroll-table td {
      border: 1px solid #000;
      padding: 2px 4px;
      line-height: 1.2;
      height: 25px;
    }
    
    .payroll-table thead th {
      text-align: center;
      vertical-align: middle;
    }

    .section-box {
      border: 1px solid #000;
      padding: 5px;
      min-height: 100px;
    }
    
    .signature-line {
      border-bottom: 1px solid #000;
      margin: 25px 0 2px 0;
    }

    /* Print styles optimized for landscape on one page */
    @media print {
      @page {
        size: landscape;
        margin: 0.2in;
      }
      
      body {
        margin: 0;
        padding: 0;
        background: white;
        font-size: 10px;
        line-height: 1.2;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      
      /* Hide navigation, sidebar, breadcrumb, and action buttons */
      nav, aside, .breadcrumb-container, .action-buttons,
      .navbar, .sidebar-container, .sidebar-overlay,
      .mobile-toggle, .datetime-container, .user-menu {
        display: none !important;
      }
      
      main {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
      }
      
      .payroll-container {
        border: 2px solid #000 !important;
        margin: 0 auto !important;
        padding: 10px !important;
        max-width: 100% !important;
        box-shadow: none !important;
        page-break-inside: avoid !important;
        font-size: 9px !important;
        width: 100% !important;
      }
      
      /* Ensure table fits on one page */
      .payroll-table {
        font-size: 8px !important;
        width: 100% !important;
        border-collapse: collapse !important;
      }
      
      .payroll-table th, .payroll-table td {
        padding: 1px 2px !important;
        border: 1px solid #000 !important;
      }
      
      /* Adjust section boxes for print */
      .section-box {
        min-height: 80px !important;
        padding: 3px !important;
        border: 1px solid #000 !important;
      }
      
      .signature-line {
        margin: 15px 0 2px 0 !important;
      }
      
      /* Force background colors to print */
      .bg-gray-200 {
        background-color: #e5e7eb !important;
      }
      
      .bg-white {
        background-color: white !important;
      }
      
      /* Ensure proper spacing for signature sections */
      .grid-cols-12 {
        display: grid !important;
        grid-template-columns: repeat(12, 1fr) !important;
        gap: 8px !important;
      }
      
      .md\:col-span-4 {
        grid-column: span 4 !important;
      }
      
      .md\:col-span-3 {
        grid-column: span 3 !important;
      }
      
      .md\:col-span-5 {
        grid-column: span 5 !important;
      }
      
      /* Fix table layout */
      .payroll-table-container {
        width: 100% !important;
        overflow: visible !important;
      }
      
      /* Ensure proper text alignment */
      .text-right {
        text-align: right !important;
      }
      
      .text-center {
        text-align: center !important;
      }
      
      .text-left {
        text-align: left !important;
      }
    }

    /* Mobile responsive styles */
    @media (max-width: 1024px) {
      main {
        margin-left: 0 !important;
        margin-top: 100px !important;
        padding: 10px !important;
      }
      
      .payroll-container {
        margin: 10px;
        padding: 5px;
      }
      
      #drawer-navigation {
        transform: translateX(-100%);
      }
      
      .breadcrumb-container {
        overflow-x: auto;
        white-space: nowrap;
      }
    }

    @media (max-width: 768px) {
      .payroll-table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      
      .payroll-table {
        min-width: 900px;
        font-size: 0.6rem;
      }
      
      .payroll-table th, .payroll-table td {
        padding: 1px 2px;
      }
      
      .grid-cols-12 {
        grid-template-columns: 1fr;
        gap: 10px;
      }
      
      .col-span-4, .col-span-3, .col-span-5 {
        grid-column: span 1;
      }
      
      .action-buttons {
        flex-direction: column;
        gap: 10px;
      }
      
      .action-buttons button,
      .action-buttons a {
        width: 100%;
        text-align: center;
      }
      
      .flex.justify-between.items-start.text-xs.mb-2 {
        flex-direction: column;
        gap: 10px;
      }
      
      .text-center.font-bold.text-sm {
        order: -1;
      }
    }

    @media (max-width: 480px) {
      .text-sm {
        font-size: 0.8rem;
      }
      
      .section-box {
        padding: 8px;
      }
      
      .section-box p {
        font-size: 0.7rem;
      }
      
      .signature-line {
        margin: 15px 0 2px 0;
      }
    }

    /* Improved navigation styles */
    .navbar {
      background-color: #1e40af;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .sidebar {
      background-color: #1e40af;
    }
    
    .sidebar-item.active {
      background-color: #1e3a8a;
    }
    
    .sidebar-item:hover {
      background-color: #1e3a8a;
    }
    
    .dropdown-item {
      padding-left: 2.75rem;
    }
    
    .dropdown-item:hover {
      background-color: #1e3a8a;
    }
    
    .mobile-menu-button {
      color: white;
    }
    
    .mobile-menu-button:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }
    
    .user-menu-button {
      border: 2px solid white;
      transition: all 0.2s;
    }
    
    .user-menu-button:hover {
      border-color: #93c5fd;
      box-shadow: 0 0 0 2px rgba(147, 197, 253, 0.5);
    }

    /* Enhanced button styles */
    .nav-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
      font-weight: 500;
      transition: all 0.2s;
      text-decoration: none;
      border: none;
      cursor: pointer;
    }
    
    .btn-primary {
      background-color: #2563eb;
      color: white;
    }
    
    .btn-primary:hover {
      background-color: #1d4ed8;
      transform: translateY(-1px);
      box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
    }
    
    .btn-success {
      background-color: #059669;
      color: white;
    }
    
    .btn-success:hover {
      background-color: #047857;
      transform: translateY(-1px);
      box-shadow: 0 4px 6px rgba(5, 150, 105, 0.2);
    }
    
    .btn-indigo {
      background-color: #4f46e5;
      color: white;
    }
    
    .btn-indigo:hover {
      background-color: #4338ca;
      transform: translateY(-1px);
      box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2);
    }

    /* Breadcrumb improvements */
    .breadcrumb-container {
      background-color: #f3f4f6;
      padding: 0.5rem;
      border-radius: 0.375rem;
      margin-bottom: 1rem;
    }
    
    .breadcrumb-link {
      color: #4b5563;
      transition: color 0.2s;
    }
    
    .breadcrumb-link:hover {
      color: #2563eb;
    }
    
    .breadcrumb-active {
      color: #2563eb;
      font-weight: 600;
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

    .payroll-table th, .payroll-table td {
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
      .action-buttons, .action-cell {
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
      nav, aside, .sidebar, .navbar, .breadcrumb-container, .action-buttons {
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
      
      .payroll-table th, .payroll-table td {
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
      
      .payroll-container {
        font-size: 8px;
        padding: 8px;
      }
      
      .payroll-table th, .payroll-table td {
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
      
      .payroll-table th, .payroll-table td {
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


  <!-- MAIN -->
  <main class="bg-gray-100" style="margin-left: 250px; margin-top: 40px;">
    <div class="breadcrumb-container">
      <!-- Breadcrumb -->
        <nav class="mt-8 flex mr-6" aria-label="Breadcrumb">
          <ol class="inline-flex items-center space-x-1 md:space-x-2">
            <li class="inline-flex items-center">
              <a href="joboerderpayrolltable1.php"  class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2 breadcrumb-item">
                <i class="fas fa-home mr-2"></i> Job Order Payroll
              </a>
            </li>
            <li>
              <div class="flex items-center">
                <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                <a href="joborderpayroll.php" class="ml-1 text-sm font-medium text-blue-700 hover:text-blue-600 md:ml-2 breadcrumb-item">General Payroll</a>
              </div>
            </li>
            <li>
              <div class="flex items-center">
                <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                <a href="joborderobligationrequest.php"class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-blue-700 breadcrumb-item">Job Order Obligation Request</a>
              </div>
            </li>
          </ol>
        </nav>
    </div>

  
    
    <div class="payroll-container">
      <!-- Header Section -->
      <div class="mb-2">
        <div class="text-center font-bold text-sm">
          <p>PAYROLL</p>
          <p>For the period <strong>SEPTEMBER 1-15, 2025</strong></p>
        </div>
        <div class="flex justify-between text-xs">
          <div>
            <p><strong>Entity Name: LOU CORPALAIAN</strong></p>
            <p>We acknowledge receipt of cash shown opposite our name as full compensation for services received for the period stated.</p>
          </div>
          <div>
            <p>Payroll No. <span class="border-b border-black w-12 inline-block text-center"><strong>1</strong></span> Sheet <span class="border-b border-black w-12 inline-block text-center"><strong>1</strong></span> of <span class="border-b border-black w-12 inline-block text-center"><strong>1</strong></span> Sheets.</p>
          </div>
        </div>
      </div>

      <!-- Table Section -->
      <div class="payroll-table-container">
        <table class="payroll-table text-left text-gray-500 bg-white-600" style="table-layout: fixed; width: 100%;">
          <thead class="text-gray-900 font-bold uppercase">
            <tr>
              <th rowspan="2" class="w-[3%]">SERIAL NO.</th>
              <th rowspan="2" class="w-[10%]">NAME</th>
              <th rowspan="2" class="w-[7%]">DESIGNATION</th>
              <th rowspan="2" class="w-[10%]">ADDRESS</th>
              <th colspan="4" class="w-[35%]">COMPENSATIONS</th>
              <th colspan="2" class="w-[20%]">DEDUCTIONS</th>
              <th colspan="2" class="w-[10%]">COMMUNITY TAX CERTIFICATE</th>
              <th rowspan="2" class="w-[9%]">SIGNATURE OF RECIPIENT</th>
            </tr>
            <tr>
              <th class="w-[7%]">RATE PER DAY</th>
              <th class="w-[7%]">NUMBER OF DAYS</th>
              <th class="w-[8%]">AMOUNT EARNED</th>
              <th class="w-[8%]">TOTAL AMOUNT EARNED</th>
              <th class="w-[7%]">GSIS TOTAL CONTRIBUTION</th>
              <th class="w-[7%]">TOTAL NET AMOUNT DUE</th>
              <th class="w-[5%]">NUMBER</th>
              <th class="w-[5%]">DATE</th>
            </tr>
          </thead>
          <tbody>
            <!-- First row with data -->
            <tr class="employee-row bg-white hover:bg-gray-50">
              <td class="text-center font-medium text-gray-900">1</td>
              <td class="font-medium text-gray-900">CHARLENE D. DALATON</td>
              <td class="text-center">Client</td>
              <td class="text-center">Prime Only</td>
              <td class="text-right">256.00</td>
              <td class="text-center">9 days</td>
              <td class="text-right">2,256.00</td>
              <td class="text-right data-amount">2,256.00</td>
              <td class="text-right">84,950.00</td>
              <td class="text-right data-net-amount">2,256.00</td>
              <td class="text-center">101/120/15</td>
              <td class="text-center">101/120/15</td>
              <td class="signature-cell"></td>
            </tr>

            <!-- Total row -->
            <tr class="bg-gray-200 border-t border-black font-bold">
              <td colspan="4" class="text-center">TOTAL AMOUNT</td>
              <td colspan="3" class="text-right"></td>
              <td class="text-right">2,256.00</td>
              <td class="text-right"></td>
              <td class="text-right">2,256.00</td>
              <td colspan="4" class="action-cell"></td>
            </tr>

            <!-- Empty rows -->
            <tr class="bg-white hover:bg-gray-50">
              <td class="text-center font-medium text-gray-900">2</td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td class="data-amount"></td>
              <td></td>
              <td class="data-net-amount"></td>
              <td></td>
              <td></td>
              <td class="signature-cell"></td>
            </tr>
            <tr class="bg-white hover:bg-gray-50">
              <td class="text-center font-medium text-gray-900">3</td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td class="data-amount"></td>
              <td></td>
              <td class="data-net-amount"></td>
              <td></td>
              <td></td>
              <td class="signature-cell"></td>
            </tr>
            <tr class="bg-white hover:bg-gray-50">
              <td class="text-center font-medium text-gray-900">4</td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td class="data-amount"></td>
              <td></td>
              <td class="data-net-amount"></td>
              <td></td>
              <td></td>
              <td class="signature-cell"></td>
            </tr>
            <tr class="bg-white hover:bg-gray-50">
              <td class="text-center font-medium text-gray-900">5</td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td class="data-amount"></td>
              <td></td>
              <td class="data-net-amount"></td>
              <td></td>
              <td></td>
              <td class="signature-cell"></td>
            </tr>
            <tr class="bg-white hover:bg-gray-50">
              <td class="text-center font-medium text-gray-900">6</td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td class="data-amount"></td>
              <td></td>
              <td class="data-net-amount"></td>
              <td></td>
              <td></td>
              <td class="signature-cell"></td>
            </tr>
            <tr class="bg-white hover:bg-gray-50">
              <td class="text-center font-medium text-gray-900">7</td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td class="data-amount"></td>
              <td></td>
              <td class="data-net-amount"></td>
              <td></td>
              <td></td>
              <td class="signature-cell"></td>
            </tr>
            <tr class="bg-white hover:bg-gray-50">
              <td class="text-center font-medium text-gray-900">8</td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td class="data-amount"></td>
              <td></td>
              <td class="data-net-amount"></td>
              <td></td>
              <td></td>
              <td class="signature-cell"></td>
            </tr>
            <tr class="bg-white hover:bg-gray-50">
              <td class="text-center font-medium text-gray-900">9</td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td class="data-amount"></td>
              <td></td>
              <td class="data-net-amount"></td>
              <td></td>
              <td></td>
              <td class="signature-cell"></td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Signature Sections - ARRANGED EXACTLY LIKE THE IMAGE -->
      <div class="grid grid-cols-1 md:grid-cols-12 gap-x-2 gap-y-4 mt-4 text-xs">
        <!-- First Row - A, B, C -->
        <div class="md:col-span-4 section-box">
          <p class="font-bold border-b border-black inline-block px-1">A. CERTIFIED</p>
          <p class="mt-2">Services duly rendered as stated.</p>
          <div class="signature-line w-full mt-4"></div>
          <div class="text-center font-bold">
            <p>JONELE, VICENTE</p>
            <p class="font-normal text-[0.6rem]">Administration Officer IV (HYMIO III)</p>
          </div>
          <div class="flex justify-between mt-2">
            <p>Date</p>
            <p>Date</p>
          </div>
        </div>

        <div class="md:col-span-4 section-box">
          <p class="font-bold border-b border-black inline-block px-1">B. CERTIFIED</p>
          <p class="mt-2">Supporting documents complete and proper.</p>
          <div class="signature-line w-full mt-8"></div>
          <div class="text-center font-bold">
            <p>JULIE ANNE V. VALLESTERO, CPA</p>
            <p class="font-normal text-[0.6rem]">Municipal Accountant</p>
          </div>
          <div class="flex justify-end mt-2">
            <p>Date</p>
          </div>
        </div>

        <div class="md:col-span-4 section-box">
          <p class="font-bold border-b border-black inline-block px-1">C. CERTIFIED</p>
          <p class="mt-2">Cash available in the amount of</p>
          <p class="font-bold">P 2,266.00</p>
          <div class="signature-line w-full mt-4"></div>
          <div class="text-center font-bold">
            <p>ARLENE A. DE VEAS</p>
            <p class="font-normal text-[0.6rem]">Municipal Treasurer</p>
          </div>
          <div class="flex justify-end mt-2">
            <p>Date</p>
          </div>
        </div>

        <!-- Second Row - D, E, F -->
        <div class="md:col-span-4 section-box">
          <p class="font-bold border-b border-black inline-block px-1">D. APPROVED</p>
          <p class="mt-2">For payment</p>
          <div class="signature-line w-full mt-8"></div>
          <div class="text-center font-bold">
            <p>HON. MICHAEL D. PALZ</p>
            <p class="font-normal text-[0.6rem]">Municipal Mayor</p>
          </div>
          <div class="flex justify-end mt-2">
            <p>Date</p>
          </div>
        </div>

        <div class="md:col-span-4 section-box">
          <p class="font-bold border-b border-black inline-block px-1">E.</p>
          <div class="mt-2">
            <p>OROB/BUR'S No.:</p>
            <div class="signature-line w-full mt-1 mb-2"></div>
            <p>JEV No.:</p>
            <div class="signature-line w-full mt-1 mb-2"></div>
            <p>Date:</p>
            <div class="signature-line w-full mt-1"></div>
          </div>
        </div>

        <div class="md:col-span-4 section-box">
          <p class="font-bold border-b border-black inline-block px-1">F. CERTIFIED</p>
          <p class="mt-2">Each employee whose name appears on this roll and opposite his/her name "received the amount due" him/her.</p>
          <div class="signature-line w-full mt-8"></div>
          <div class="text-center font-bold">
            <p>EVA V. OVENAS</p>
            <p class="font-normal text-[0.6rem]">Disbursing Officer</p>
          </div>
          <div class="flex justify-end mt-2">
            <p>Date</p>
          </div>
        </div>
      </div>
    </div>

    <div class="action-buttons flex flex-col md:flex-row justify-center space-y-2 md:space-y-0 md:space-x-4 mt-6 mb-10">
      <button onclick="printPayroll()" class="nav-button btn-primary">
        <svg class="w-4 h-4 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v6a2 2 0 002 2h12a2 2 0 002-2v-6a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm2 5V4h6v5H7zm-2 2h10v4H5v-4z" clip-rule="evenodd"></path></svg>
        Print
      </button>
      <button id="save-button" class="nav-button btn-success">
        <svg class="w-4 h-4 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 9a1 1 0 011-1h10a1 1 0 011 1v7a1 1 0 01-1 1H5a1 1 0 01-1-1V9zM5 7a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2H5z"></path></svg>
        Save Data
      </button>
      <a href="joborderobligationrequest.php" class="w-full md:w-auto">
        <button id="next-button" class="nav-button btn-indigo">
          Next Payroll <svg class="w-4 h-4 inline-block ml-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
        </button>
      </a>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.46.0/dist/apexcharts.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
  
  <script>
    // Custom print function to ensure clean print output
    function printPayroll() {
      // Store original body content
      const originalContent = document.body.innerHTML;
      
      // Get the payroll container
      const payrollContent = document.querySelector('.payroll-container').outerHTML;
      
      // Create a clean print document
      const printWindow = window.open('', '_blank');
      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Job Order Payroll - Print</title>
          <style>
            @page {
              size: landscape;
              margin: 0.2in;
            }
            body {
              margin: 0;
              padding: 0;
              font-family: Arial, sans-serif;
              font-size: 9px;
              background: white;
            }
            .payroll-container {
              border: 2px solid #000;
              padding: 10px;
              max-width: 100%;
              margin: 0 auto;
            }
            table {
              width: 100%;
              border-collapse: collapse;
              font-size: 8px;
            }
            th, td {
              border: 1px solid #000;
              padding: 2px 4px;
              text-align: center;
              vertical-align: middle;
            }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            .bg-gray-200 { 
              background-color: #e5e7eb !important; 
              -webkit-print-color-adjust: exact;
            }
            .section-box {
              border: 1px solid #000;
              padding: 5px;
              min-height: 80px;
            }
            .signature-line {
              border-bottom: 1px solid #000;
              margin: 15px 0 2px 0;
            }
            .grid-cols-12 {
              display: grid;
              grid-template-columns: repeat(12, 1fr);
              gap: 8px;
              margin-top: 15px;
            }
            .md\\:col-span-4 { grid-column: span 4; }
            * {
              -webkit-print-color-adjust: exact;
              print-color-adjust: exact;
            }
          </style>
        </head>
        <body>
          ${payrollContent}
          <script>
            window.onload = function() {
              window.print();
              setTimeout(function() {
                window.close();
              }, 500);
            };
          <\/script>
        </body>
        </html>
      `);
      printWindow.document.close();
    }
    
    // Update date and time
    function updateDateTime() {
      const now = new Date();
      
      // Format date
      const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
      const dateString = now.toLocaleDateString('en-US', dateOptions);
      document.getElementById('current-date').textContent = dateString;
      
      // Format time
      const timeString = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit' });
      document.getElementById('current-time').textContent = timeString;
    }
    
    // Update date/time every minute
    updateDateTime();
    setInterval(updateDateTime, 60000);
    
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
    
    // User menu toggle
    document.getElementById('user-menu-button').addEventListener('click', function() {
      const dropdown = document.getElementById('user-dropdown');
      this.classList.toggle('active');
      dropdown.classList.toggle('active');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
      const userButton = document.getElementById('user-menu-button');
      const dropdown = document.getElementById('user-dropdown');
      
      if (!userButton.contains(event.target) && !dropdown.contains(event.target)) {
        userButton.classList.remove('active');
        dropdown.classList.remove('active');
      }
    });
    
    // Payroll dropdown toggle
    document.getElementById('payroll-toggle').addEventListener('click', function(e) {
      e.preventDefault();
      const dropdown = document.getElementById('payroll-dropdown');
      const chevron = this.querySelector('.chevron');
      
      dropdown.classList.toggle('open');
      chevron.classList.toggle('rotated');
    });
  </script>
</body>
</html>