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
  <title>Contractual Payroll</title>
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

    /* Certificate boxes */
    .cert-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .cert-grid {
      border: 1px solid #000;
    }
    
    .cert-box {
      padding: 8px;
      border: 1px solid #000;
    }
    
    .signature-line {
      border-bottom: 1px solid #000;
      min-width: 60px;
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
      
      /* Make certificate boxes stack vertically on very small screens */
      .cert-grid {
        grid-template-columns: 1fr !important;
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
    
    /* Mobile table container */
    .table-container {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
     .sidebar-item.active {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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

        <!-- User Menu -->
       
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
            <a href="../Payrollmanagement/contractualpayrolltable1.php" class="submenu-item active">
              <i class="fas fa-circle text-xs"></i>
              Contractual
            </a>
            <a href="../Payrollmanagement/joboerderpayrolltable1.php" class="submenu-item">
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
          <a href="../paysliphistory.php" class="sidebar-item">
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
    <div class="breadcrumb-container">
      <nav class="mt-4 flex" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-2">
          <li class="inline-flex items-center">
            <a href="contractualpayrolltable1.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-primary-600 md:ml-2">
              <i class="fas fa-home mr-2"></i> Contractual Payroll
            </a>
          </li>
          <li>
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <a href="contractualpayroll.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-primary-600 md:ml-2">General Payroll</a>
            </div>
          </li>
          <li>
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <a href="contractualobligationrequest.php" class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-700">Contractual Obligation Request</a>
            </div>
          </li>
        </ol>
      </nav>
    </div>
    
    <div class="payroll-container">
      <div class="flex justify-between text-[7px] mb-2">
        <div>
          <p><strong># PAYROLL</strong></p>
          <p><strong>For the period 35/TH/MB/13-15,2025</strong></p>
        </div>
        <div class="text-right">
          <p>Entity Name: <strong>LGU-PAULIAN</strong></p>
          <p>Fund Cluster: We acknowledge receipt of cash shown opposite our name as full compensation for services rendered for the period covered.</p>
          <p>Payroll No.: <span class="border-b border-black w-8 inline-block">1</span> Sheet No.: <span class="border-b border-black w-8 inline-block">1</span> of <span class="border-b border-black w-8 inline-block">1</span></p>
        </div>
      </div>
      
      <div class="table-container">
        <table class="payroll-table text-[7px]">
          <thead>
            <tr>
              <th rowspan="2" class="mobile-hide">Serial No.</th>
              <th rowspan="2">Name</th>
              <th rowspan="2" class="mobile-hide-extra">Position</th>
              <th rowspan="2" class="mobile-hide">Address</th>
              <th colspan="3">COMPENSATIONS</th>
              <th colspan="2" class="mobile-hide">DEDUCTIONS</th>
              <th colspan="2" class="mobile-hide-extra">CIGILINITY TAX</th>
              <th rowspan="2">Net Amount Due</th>
              <th rowspan="2" class="mobile-hide">Signature of Recipient</th>
            </tr>
            <tr>
              <th>Monthly Salaries and Wages</th>
              <th class="mobile-hide-extra">Other Compensation</th>
              <th>Gross Amount Earned</th>
              <th class="mobile-hide">Withholding Tax</th>
              <th class="mobile-hide">Total Deductions</th>
              <th class="mobile-hide-extra">CRITIFICATE</th>
              <th class="mobile-hide-extra">Date</th>
            </tr>
          </thead>
          <tbody>
            <tr class="employee-row" data-id="1">
              <td class="mobile-hide">1</td>
              <td>ANGELICA JANE V. ALFARO</td>
              <td class="mobile-hide-extra">MPIO Focal Person</td>
              <td class="mobile-hide">Paluan, Occ.</td>
              <td>20,000.00</td>
              <td class="mobile-hide-extra">-</td>
              <td>10,000.00</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide">760.00</td>
              <td class="mobile-hide-extra">GMT592</td>
              <td class="mobile-hide-extra">1/21/2025</td>
              <td>9,240.00</td>
              <td class="mobile-hide"></td>
            </tr>
            <tr class="employee-row" data-id="2">
              <td class="mobile-hide">2</td>
              <td>APRIL V. AGUILAR</td>
              <td class="mobile-hide-extra">MYDO Focal Person</td>
              <td class="mobile-hide">Paluan, Occ.</td>
              <td>20,000.00</td>
              <td class="mobile-hide-extra">-</td>
              <td>10,000.00</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide-extra">GMT592</td>
              <td class="mobile-hide-extra">1/15/2025</td>
              <td>10,000.00</td>
              <td class="mobile-hide"></td>
            </tr>
            <tr class="employee-row" data-id="3">
              <td class="mobile-hide">3</td>
              <td>INORYL V. GAMBOA</td>
              <td class="mobile-hide-extra">Public Relations Asset</td>
              <td class="mobile-hide">Paluan, Occ.</td>
              <td>15,000.00</td>
              <td class="mobile-hide-extra">-</td>
              <td>7,500.00</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide-extra">GMT592</td>
              <td class="mobile-hide-extra">1/9/2025</td>
              <td>7,500.00</td>
              <td class="mobile-hide"></td>
            </tr>
            <tr class="employee-row" data-id="4">
              <td class="mobile-hide">4</td>
              <td>ISMERALDO M. MANIPOL</td>
              <td class="mobile-hide-extra">Mln</td>
              <td class="mobile-hide">Mln</td>
              <td>10,000.00</td>
              <td class="mobile-hide-extra">-</td>
              <td>4,666.67</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide-extra">GMT592</td>
              <td class="mobile-hide-extra">1/6/2025</td>
              <td>4,666.67</td>
              <td class="mobile-hide"></td>
            </tr>
            <tr class="employee-row" data-id="5">
              <td class="mobile-hide">5</td>
              <td>LOVEY B. SALES</td>
              <td class="mobile-hide-extra">Clerk</td>
              <td class="mobile-hide">Mln</td>
              <td>8,000.00</td>
              <td class="mobile-hide-extra">-</td>
              <td>4,000.00</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide-extra">GMT592</td>
              <td class="mobile-hide-extra">1/17/2025</td>
              <td>4,000.00</td>
              <td class="mobile-hide"></td>
            </tr>
            <tr class="employee-row" data-id="6">
              <td class="mobile-hide">6</td>
              <td>ROMEO S. DELLUMA</td>
              <td class="mobile-hide-extra">Administrative Aide</td>
              <td class="mobile-hide">Mln</td>
              <td>7,500.00</td>
              <td class="mobile-hide-extra">-</td>
              <td>3,750.00</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide-extra">GMT592</td>
              <td class="mobile-hide-extra">1/6/2025</td>
              <td>3,750.00</td>
              <td class="mobile-hide"></td>
            </tr>
            <tr class="employee-row" data-id="7">
              <td class="mobile-hide">7</td>
              <td>JOSELIERO T. RENDON</td>
              <td class="mobile-hide-extra">Administrative Aide</td>
              <td class="mobile-hide">Mln</td>
              <td>7,500.00</td>
              <td class="mobile-hide-extra">-</td>
              <td>3,750.00</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide-extra">GMT592</td>
              <td class="mobile-hide-extra">1/6/2025</td>
              <td>3,750.00</td>
              <td class="mobile-hide"></td>
            </tr>
            <tr class="employee-row" data-id="8">
              <td class="mobile-hide">8</td>
              <td>JAYSON S. VILLAR</td>
              <td class="mobile-hide-extra">Security Guard</td>
              <td class="mobile-hide">Mln</td>
              <td>7,500.00</td>
              <td class="mobile-hide-extra">-</td>
              <td>3,750.00</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide-extra">GMT592</td>
              <td class="mobile-hide-extra">1/6/2025</td>
              <td>3,750.00</td>
              <td class="mobile-hide"></td>
            </tr>
            <tr class="employee-row" data-id="9">
              <td class="mobile-hide">9</td>
              <td>LEA V. DUEÑAS</td>
              <td class="mobile-hide-extra">Administrative Aide</td>
              <td class="mobile-hide">Mln</td>
              <td>7,500.00</td>
              <td class="mobile-hide-extra">-</td>
              <td>3,750.00</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide-extra">GMT592</td>
              <td class="mobile-hide-extra">1/15/2025</td>
              <td>3,750.00</td>
              <td class="mobile-hide"></td>
            </tr>
            <tr class="employee-row" data-id="10">
              <td class="mobile-hide">10</td>
              <td>VERMEL P. ALFARO</td>
              <td class="mobile-hide-extra">Administrative Aide</td>
              <td class="mobile-hide">Mln</td>
              <td>7,500.00</td>
              <td class="mobile-hide-extra">-</td>
              <td>3,750.00</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide-extra">GMT592</td>
              <td class="mobile-hide-extra">1/6/2025</td>
              <td>3,750.00</td>
              <td class="mobile-hide"></td>
            </tr>
            <tr class="employee-row" data-id="11">
              <td class="mobile-hide">11</td>
              <td>BERNAJOY M. UY</td>
              <td class="mobile-hide-extra">Administrative Asset</td>
              <td class="mobile-hide">Mln</td>
              <td>10,000.00</td>
              <td class="mobile-hide-extra">-</td>
              <td>5,000.00</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide-extra">GMT592</td>
              <td class="mobile-hide-extra">2/3/2025</td>
              <td>5,000.00</td>
              <td class="mobile-hide"></td>
            </tr>
            
            <tr class="font-bold bg-gray-100">
              <td colspan="4" class="text-right">TOTAL AMOUNT:</td>
              <td>120,500.00</td>
              <td class="mobile-hide-extra">-</td>
              <td>59,916.67</td>
              <td class="mobile-hide">-</td>
              <td class="mobile-hide">760.00</td>
              <td class="mobile-hide-extra" colspan="2"></td>
              <td>59,156.67</td>
              <td class="mobile-hide"></td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <div class="mt-4">
        <table class="cert-table text-[8px] border-none">
          <tr class="border-none">
            <td class="w-1/2 p-0 border-none">
              <div class="grid grid-cols-2 cert-grid">
                <div class="cert-box border-t-0 border-l-0">
                  <p class="font-bold border-b border-black inline-block px-1">A</p>
                  <p class="mt-2 text-[0.65rem] font-bold">CERTIFIED: Services duly rendered as stated.</p>
                  <div class="flex justify-between mt-8">
                    <p>Signature:</p>
                    <div class="signature-line w-1/2"></div>
                  </div>
                  <div class="text-left font-bold mt-2">
                    <p>JOREL B. VICENTE</p>
                    <p class="font-normal">Administrative Officer IV (HRMO II)</p>
                  </div>
                  <div class="flex justify-between mt-4">
                    <p>Date:</p>
                    <div class="signature-line w-1/2"></div>
                  </div>
                </div>
                
                <div class="cert-box border-t-0 border-r-0">
                  <p class="font-bold border-b border-black inline-block px-1">B</p>
                  <p class="mt-2 text-[0.65rem] font-bold">CERTIFIED: Supporting documents complete and proper.</p>
                  <div class="flex justify-between mt-8">
                    <p>Signature:</p>
                    <div class="signature-line w-1/2"></div>
                  </div>
                  <div class="text-left font-bold mt-2">
                    <p>JULIE ANNE T. VALLESTERO, CPA</p>
                    <p class="font-normal">Municipal Accountant</p>
                  </div>
                  <div class="flex justify-between mt-4">
                    <p>Date:</p>
                    <div class="signature-line w-1/2"></div>
                  </div>
                </div>
              </div>
            </td>
            
            <td class="w-1/2 p-0 border-none">
              <div class="grid grid-cols-2 cert-grid">
                <div class="cert-box border-t-0 border-l-0">
                  <p class="font-bold border-b border-black inline-block px-1">C</p>
                  <p class="mt-2 text-[0.65rem] font-bold">CERTIFIED: Cash available in the amount of <span class="font-bold">59,156.67</span></p>
                  <div class="flex justify-between mt-8">
                    <p>Signature:</p>
                    <div class="signature-line w-1/2"></div>
                  </div>
                  <div class="text-left font-bold mt-2">
                    <p>ARLENE A. DE VEAS</p>
                    <p class="font-normal">Municipal Treasurer</p>
                  </div>
                  <div class="flex justify-between mt-4">
                    <p>Date:</p>
                    <div class="signature-line w-1/2"></div>
                  </div>
                </div>
                
                <div class="cert-box border-t-0 border-r-0">
                  <p class="font-bold border-b border-black inline-block px-1">D</p>
                  <p class="mt-2 text-[0.65rem] font-bold">APPROVED: For payment.</p>
                  <div class="flex justify-between mt-8">
                    <p>Signature:</p>
                    <div class="signature-line w-1/2"></div>
                  </div>
                  <div class="text-left font-bold mt-2">
                    <p>HON. MICHAEL D. DIAZ</p>
                    <p class="font-normal">Municipal Mayor</p>
                  </div>
                  <div class="flex justify-between mt-4">
                    <p>Date:</p>
                    <div class="signature-line w-1/2"></div>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        </table>
      </div>
      
      <div class="grid grid-cols-2 gap-4 mt-4 cert-grid">
        <div class="cert-box text-[8px]">
          <p class="font-bold border-b border-black inline-block px-1">E</p>
          <div class="grid grid-cols-2 mt-4 gap-2">
            <p>ORS/BURS No.:</p>
            <div class="signature-line"></div>
            <p>JEV No.:</p>
            <div class="signature-line"></div>
          </div>
          <div class="grid grid-cols-2 mt-2 gap-2">
            <p>Date:</p>
            <div class="signature-line"></div>
            <p>Date:</p>
            <div class="signature-line"></div>
          </div>
        </div>
        
        <div class="cert-box text-[8px]">
          <p class="font-bold border-b border-black inline-block px-1">F</p>
          <p class="mt-2 text-[0.65rem] font-bold">CERTIFIED: Each employee whose name appears on this roll and opposite his/her name received the amount due him/her.</p>
          <div class="flex justify-between mt-8">
            <p>Signature:</p>
            <div class="signature-line w-1/2"></div>
          </div>
          <div class="text-left font-bold mt-2">
            <p>EVA V. DUEÑAS</p>
            <p class="font-normal">Disbursing Officer</p>
          </div>
        </div>
      </div>
    </div>

    <div class="action-buttons flex justify-center space-x-4 mt-6 mb-10">
      <button onclick="window.print()" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5">
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
        
        // Format date
        const dateOptions = { 
          weekday: 'long', 
          year: 'numeric', 
          month: 'long', 
          day: 'numeric' 
        };
        const dateString = now.toLocaleDateString('en-US', dateOptions);
        
        // Format time
        const timeOptions = { 
          hour: '2-digit', 
          minute: '2-digit',
          second: '2-digit',
          hour12: true 
        };
        const timeString = now.toLocaleTimeString('en-US', timeOptions);
        
        const dateElement = document.getElementById('current-date');
        const timeElement = document.getElementById('current-time');
        
        if (dateElement) dateElement.textContent = dateString;
        if (timeElement) timeElement.textContent = timeString;
      }

      // Update date/time immediately and every second
      updateDateTime();
      setInterval(updateDateTime, 1000);

      // Mobile menu toggle functionality
      const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
      const sidebar = document.getElementById('sidebar');
      const sidebarOverlay = document.getElementById('sidebar-overlay');
      
      if (mobileMenuToggle && sidebar && sidebarOverlay) {
        mobileMenuToggle.addEventListener('click', function() {
          sidebar.classList.toggle('active');
          sidebarOverlay.classList.toggle('active');
          
          // Prevent body scroll when sidebar is open on mobile
          if (window.innerWidth < 768) {
            if (sidebar.classList.contains('active')) {
              document.body.style.overflow = 'hidden';
            } else {
              document.body.style.overflow = '';
            }
          }
        });
        
        // Close sidebar when overlay is clicked
        sidebarOverlay.addEventListener('click', function() {
          sidebar.classList.remove('active');
          this.classList.remove('active');
          document.body.style.overflow = '';
        });
        
        // Close sidebar when clicking on a sidebar link (for mobile)
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
          link.addEventListener('click', function() {
            if (window.innerWidth < 768) {
              sidebar.classList.remove('active');
              sidebarOverlay.classList.remove('active');
              document.body.style.overflow = '';
            }
          });
        });
      }

      // User dropdown toggle
      const userMenuButton = document.getElementById('user-menu-button');
      const userDropdown = document.getElementById('user-dropdown');
      
      if (userMenuButton && userDropdown) {
        userMenuButton.addEventListener('click', function(e) {
          e.stopPropagation();
          userDropdown.classList.toggle('active');
          userMenuButton.classList.toggle('active');
        });

        // Close user dropdown when clicking outside
        document.addEventListener('click', function(event) {
          if (userDropdown && userMenuButton) {
            if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
              userDropdown.classList.remove('active');
              userMenuButton.classList.remove('active');
            }
          }
        });
      }

      // Payroll dropdown toggle in sidebar - FIXED VERSION
      const payrollToggle = document.getElementById('payroll-toggle');
      const payrollSubmenu = document.getElementById('payroll-submenu');
      
      if (payrollToggle && payrollSubmenu) {
        payrollToggle.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          const chevron = this.querySelector('.chevron');
          if (payrollSubmenu.classList.contains('open')) {
            payrollSubmenu.classList.remove('open');
            chevron.classList.remove('rotated');
          } else {
            payrollSubmenu.classList.add('open');
            chevron.classList.add('rotated');
          }
        });
        
        // Open payroll submenu by default since Contractual is active
        payrollSubmenu.classList.add('open');
        payrollToggle.querySelector('.chevron').classList.add('rotated');
      }

      // Handle window resize
      window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
          // On desktop, ensure sidebar is visible and overlay is hidden
          if (sidebar) {
            sidebar.classList.add('active');
          }
          if (sidebarOverlay) {
            sidebarOverlay.classList.remove('active');
          }
          document.body.style.overflow = '';
        } else {
          // On mobile, hide sidebar by default
          if (sidebar) {
            sidebar.classList.remove('active');
          }
          if (sidebarOverlay) {
            sidebarOverlay.classList.remove('active');
          }
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
              <h3 class="text-lg font-semibold text-gray-900 mb-2">Save Payroll Data</h3>
              <p class="text-gray-600 mb-4">Are you sure you want to save this payroll data?</p>
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
            console.log('Saving payroll data...');
            alert('Payroll data has been saved successfully!');
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

      // Initialize sidebar based on current page
      const currentPath = window.location.pathname;
      const sidebarLinks = document.querySelectorAll('.sidebar-item, .submenu-item');
      
      sidebarLinks.forEach(link => {
        // Remove all active classes first
        link.classList.remove('active');
        
        // Check if this link matches current page
        const href = link.getAttribute('href');
        if (href && href !== '#' && currentPath.includes(href)) {
          link.classList.add('active');
          
          // If it's a submenu item, ensure parent menu is open
          if (link.classList.contains('submenu-item')) {
            if (payrollSubmenu) {
              payrollSubmenu.classList.add('open');
              if (payrollToggle) {
                payrollToggle.querySelector('.chevron').classList.add('rotated');
              }
            }
          }
        }
      });

      // Initialize on page load
      if (window.innerWidth >= 1024) {
        if (sidebar) {
          sidebar.classList.add('active');
        }
      } else {
        if (sidebar) {
          sidebar.classList.remove('active');
        }
      }
    });
  </script>
</body>
</html>