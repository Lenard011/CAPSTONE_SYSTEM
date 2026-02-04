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
      top: 1%;
      left: -300px;
      width: 250px;
      height: calc(100vh - 70px);
      background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
      z-index: 1000;
      transition: left 0.3s ease;
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
            <a href="../Payrollmanagement/joboerderpayrolltable1.php" class="submenu-item">
              <i class="fas fa-circle text-xs"></i>
              Job Order
            </a>
            <a href="../Payrollmanagement/permanentpayrolltable1.php" class="submenu-item active">
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
    <div class="breadcrumb-container">
      <!-- Breadcrumb -->
      <nav class="mt-8 flex ml-5" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-2">
          <li class="inline-flex items-center">
            <a href="permanentpayrolltable1.php"
              class="ml-1 text-sm font-medium text-blue-700 hover:text-blue-600 md:ml-2 breadcrumb-item">
              <i class="fas fa-home mr-2"></i> Permanent Payroll
            </a>
          </li>
          <li>
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <a href="permanentpayroll.php"
                class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2 breadcrumb-item">General
                Payroll</a>
            </div>
          </li>
          <li>
            <div class="flex items-center">
              <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
              <a href="permanentobligationrequest.php"
                class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-blue-700 breadcrumb-item">Obligation
                Request</a>
            </div>
          </li>
        </ol>
      </nav>
    </div>

    <!-- Mobile info banner -->
    <div class="md:hidden bg-yellow-100 p-3 mb-3 text-xs rounded mx-4 mobile-info-banner">
      <i class="fas fa-info-circle mr-1"></i> Scroll horizontally to view all columns
    </div>

    <div class="payroll-container mx-4">
      <div class="text-center font-bold text-sm mb-2">
        <p class="text-base mb-1">GENERAL PAYROLL</p>
        <p class="text-sm mb-1">Palma, Occidental Mindoro</p>
        <p class="text-sm text-blue-600">PERIOD: SEPTEMBER 16-30, 2015</p>
      </div>

      <p class="text-[9px] mb-3 text-gray-600 italic">We acknowledge receipt of the sum opposite our names as full
        compensation for services rendered for the period stated.</p>

      <div class="overflow-x-auto relative">
        <table class="payroll-table text-[8px] md:text-[9px]">
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
              <th rowspan="2" class="w-[7%] action-cell mobile-hide">Actions</th>
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
              <td colspan="23" class="text-left pl-3">OFFICE OF THE MAYOR</td>
            </tr>

            <tr class="employee-row" data-id="1">
              <td>1</td>
              <td class="text-left pl-2">JENNY E. ARCONADA</td>
              <td>MSWOO</td>
              <td class="text-right currency-cell">95,312.00</td>
              <td class="text-right currency-cell">47,925.50</td>

              <td class="text-right currency-cell">7,024.19</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">1,193.89</td>
              <td class="text-right currency-cell">1,193.89</td>
              <td class="text-right currency-cell">6,031.86</td>
              <td class="text-right currency-cell">4,817.22</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">9,249.14</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">-</td>

              <td class="text-right currency-cell">100.00</td>
              <td class="text-right currency-cell">150.00</td>
              <td class="text-right currency-cell">23,414.00</td>
              <td class="text-right currency-cell">1</td>
              <td class="text-right currency-cell data-net-amount important-column">23,414.00</td>
              <td>1</td>
              <td class="italic text-gray-500">(Not signed)</td>

              <td class="text-center action-cell mobile-hide">
                <div class="flex space-x-1 justify-center">
                  <button data-modal-target="view-modal" data-modal-toggle="view-modal" type="button"
                    class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-2 py-1 rounded text-[0.65rem] font-medium"
                    onclick="viewEmployeeData(1)">View</button>
                  <button data-modal-target="edit-modal" data-modal-toggle="edit-modal" type="button"
                    class="text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50 px-2 py-1 rounded text-[0.65rem] font-medium"
                    onclick="loadEditData(1)">Edit</button>
                  <button data-modal-target="delete-modal" data-modal-toggle="delete-modal" type="button"
                    class="text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded text-[0.65rem] font-medium"
                    onclick="setDeleteId(1)">Delete</button>
                </div>
              </td>
            </tr>

            <tr class="employee-row" data-id="2">
              <td>2</td>
              <td class="text-left pl-2">J.FRANCE GUT, FEDRAZA</td>
              <td>WYD II</td>
              <td class="text-right currency-cell">64,649.00</td>
              <td class="text-right currency-cell">23,734.50</td>

              <td class="text-right currency-cell">2,025.00</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">593.11</td>
              <td class="text-right currency-cell">629.12</td>
              <td class="text-right currency-cell">3,793.93</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">3,156.75</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">-</td>

              <td class="text-right currency-cell">250.00</td>
              <td class="text-right currency-cell">150.00</td>
              <td class="text-right currency-cell">13,549.85</td>
              <td class="text-right currency-cell">2</td>
              <td class="text-right currency-cell data-net-amount important-column">13,549.85</td>
              <td>2</td>
              <td class="italic text-gray-500">(Not signed)</td>

              <td class="text-center action-cell mobile-hide">
                <div class="flex space-x-1 justify-center">
                  <button data-modal-target="view-modal" data-modal-toggle="view-modal" type="button"
                    class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-2 py-1 rounded text-[0.65rem] font-medium"
                    onclick="viewEmployeeData(2)">View</button>
                  <button data-modal-target="edit-modal" data-modal-toggle="edit-modal" type="button"
                    class="text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50 px-2 py-1 rounded text-[0.65rem] font-medium"
                    onclick="loadEditData(2)">Edit</button>
                  <button data-modal-target="delete-modal" data-modal-toggle="delete-modal" type="button"
                    class="text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded text-[0.65rem] font-medium"
                    onclick="setDeleteId(2)">Delete</button>
                </div>
              </td>
            </tr>

            <!-- Office of the MAID Section -->
            <tr class="department-header">
              <td colspan="23" class="text-left pl-3">OFFICE OF THE MAO</td>
            </tr>

            <tr class="employee-row" data-id="9">
              <td>9</td>
              <td class="text-left pl-2">JAMES PATRICK T. FEDRAZA</td>
              <td>Mun. Agricultural #1</td>
              <td class="text-right currency-cell">88,367.00</td>
              <td class="text-right currency-cell">44,183.50</td>

              <td class="text-right currency-cell">6,833.89</td>
              <td class="text-right currency-cell">1,104.38</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">1,104.39</td>
              <td class="text-right currency-cell">3,976.52</td>
              <td class="text-right currency-cell">5,302.02</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">13,574.04</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">-</td>
              <td class="text-right currency-cell">-</td>

              <td class="text-right currency-cell">100.00</td>
              <td class="text-right currency-cell">50.00</td>
              <td class="text-right currency-cell">18,594.47</td>
              <td class="text-right currency-cell">9</td>
              <td class="text-right currency-cell data-net-amount important-column">18,594.47</td>
              <td>9</td>
              <td class="italic text-gray-500">(Not signed)</td>

              <td class="text-center action-cell mobile-hide">
                <div class="flex space-x-1 justify-center">
                  <button data-modal-target="view-modal" data-modal-toggle="view-modal" type="button"
                    class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-2 py-1 rounded text-[0.65rem] font-medium"
                    onclick="viewEmployeeData(9)">View</button>
                  <button data-modal-target="edit-modal" data-modal-toggle="edit-modal" type="button"
                    class="text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50 px-2 py-1 rounded text-[0.65rem] font-medium"
                    onclick="loadEditData(9)">Edit</button>
                  <button data-modal-target="delete-modal" data-modal-toggle="delete-modal" type="button"
                    class="text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded text-[0.65rem] font-medium"
                    onclick="setDeleteId(9)">Delete</button>
                </div>
              </td>
            </tr>

            <tr class="totals-row">
              <td colspan="4" class="text-right pr-3 font-bold">TOTALS:</td>
              <td class="text-right currency-cell font-bold">274,959.00</td>
              <td class="text-right currency-cell font-bold">18,666.67</td>
              <td class="text-right currency-cell font-bold">1,537.51</td>
              <td class="text-right currency-cell font-bold">757.28</td>
              <td class="text-right currency-cell font-bold">500.00</td>
              <td class="text-right currency-cell font-bold">6,873.86</td>
              <td class="text-right currency-cell font-bold">6,874.05</td>
              <td class="text-right currency-cell font-bold">26,296.21</td>
              <td class="text-right currency-cell font-bold">13,873.59</td>
              <td class="text-right currency-cell font-bold">35,555.59</td>
              <td class="text-right currency-cell font-bold">18,748.81</td>
              <td class="text-right currency-cell font-bold">1,783.33</td>
              <td class="text-right currency-cell font-bold">375.00</td>
              <td class="text-right currency-cell font-bold">2,050.00</td>
              <td class="text-right currency-cell font-bold">1,150.00</td>
              <td class="text-right currency-cell font-bold">147,414.27</td>
              <td class="text-right currency-cell font-bold">-</td>
              <td class="text-right currency-cell font-bold important-column">147,414.27</td>
              <td colspan="3" class="font-bold text-center">TOTALS VERIFIED</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Additional Notes Section -->
      <div class="mt-6 text-[9px] text-gray-600">
        <p><strong>Notes:</strong></p>
        <ul class="list-disc pl-5 mt-1">
          <li>All amounts are in Philippine Peso (₱)</li>
          <li>Deductions include mandatory contributions and loans</li>
          <li>Amount Due = Amount Accrued - Total Deductions + Additional Amounts</li>
          <li>This payroll is for the period September 16-30, 2015</li>
        </ul>
      </div>

      <!-- Action Buttons -->
      <div class="action-buttons flex flex-wrap justify-center gap-3 mt-6 mb-8">
        <button id="add-button"
          class="flex items-center justify-center text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 transition-all duration-200 hover:scale-105">
          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd"
              d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
              clip-rule="evenodd"></path>
          </svg>
          Add Payroll Record
        </button>
        <button id="save-button"
          class="flex items-center justify-center text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 transition-all duration-200 hover:scale-105">
          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path
              d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 9a1 1 0 011-1h10a1 1 0 011 1v7a1 1 0 01-1 1H5a1 1 0 01-1-1V9zM5 7a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2H5z">
            </path>
          </svg>
          Save All Data
        </button>

        <a href="permanentpayroll.php">
          <button id="next-button"
            class="flex items-center justify-center text-white bg-indigo-700 hover:bg-indigo-800 focus:ring-4 focus:ring-indigo-300 font-medium rounded-lg text-sm px-5 py-2.5 transition-all duration-200 hover:scale-105">
            Next Payroll Period
            <svg class="w-4 h-4 ml-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd"
                d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z"
                clip-rule="evenodd"></path>
            </svg>
          </button>
        </a>
      </div>

      <!-- Mobile action buttons for each row -->
      <div class="md:hidden flex flex-col space-y-3 mt-4 mb-6 px-2 mobile-actions">
        <div class="grid grid-cols-2 gap-2">
          <button onclick="viewEmployeeData(1)"
            class="text-blue-600 hover:underline font-medium text-xs p-2 bg-blue-50 rounded border border-blue-100">View
            Emp. 1</button>
          <button onclick="viewEmployeeData(2)"
            class="text-blue-600 hover:underline font-medium text-xs p-2 bg-blue-50 rounded border border-blue-100">View
            Emp. 2</button>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <button onclick="viewEmployeeData(9)"
            class="text-blue-600 hover:underline font-medium text-xs p-2 bg-blue-50 rounded border border-blue-100">View
            Emp. 9</button>
          <button onclick="loadEditData(1)"
            class="text-yellow-600 hover:underline font-medium text-xs p-2 bg-yellow-50 rounded border border-yellow-100">Edit
            Emp. 1</button>
        </div>
      </div>
    </div>
  </main>

  <!-- View Modal -->
  <!-- View Modal -->
  <div id="view-modal" tabindex="-1" aria-hidden="true"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-4xl max-h-full">
      <div class="relative bg-white rounded-lg shadow-lg">
        <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-blue-50">
          <h3 class="text-lg font-semibold text-gray-900">
            Employee Payroll Details - ID: <span id="view-employee-id" class="text-blue-700"></span>
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
        <div class="p-4 md:p-5">
          <div class="modal-grid">
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">Name</label>
              <p id="view-name" class="text-gray-700 font-medium"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">Position</label>
              <p id="view-position" class="text-gray-700"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">Monthly Salary</label>
              <p id="view-monthly-salary" class="text-gray-700 font-mono"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">Amount Accrued</label>
              <p id="view-amount-accrued" class="text-gray-700 font-mono"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">Withholding Tax</label>
              <p id="view-withholding-tax" class="text-gray-700 font-mono"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">PAG-IBIG LOAN - MPL</label>
              <p id="view-pagibig-loan" class="text-gray-700 font-mono"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">PhilHealth P.S.</label>
              <p id="view-philhealth" class="text-gray-700 font-mono"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">UEF / Retirement</label>
              <p id="view-uef" class="text-gray-700 font-mono"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">Emergency Loan</label>
              <p id="view-emergency-loan" class="text-gray-700 font-mono"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">GFAL</label>
              <p id="view-gfal" class="text-gray-700 font-mono"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">LBP Loan</label>
              <p id="view-lbp-loan" class="text-gray-700 font-mono"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">MPL</label>
              <p id="view-mpl" class="text-gray-700 font-mono"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">PAG-IBIG CONT.</label>
              <p id="view-pagibig-cont" class="text-gray-700 font-mono"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">STATE INS. G.S.</label>
              <p id="view-state-ins" class="text-gray-700 font-mono"></p>
            </div>
            <div class="mb-4">
              <label class="block mb-2 text-sm font-medium text-gray-900">Amount Due</label>
              <p id="view-amount-due" class="text-gray-700 font-bold text-lg text-blue-700"></p>
            </div>
          </div>
        </div>
        <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
          <button data-modal-hide="view-modal" type="button"
            class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="edit-modal" tabindex="-1" aria-hidden="true"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-4xl max-h-full">
      <div class="relative bg-white rounded-lg shadow-lg">
        <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-yellow-50">
          <h3 class="text-lg font-semibold text-gray-900">
            Edit Employee Payroll - ID: <span id="edit-employee-id" class="text-yellow-700"></span>
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
        <form id="edit-form">
          <div class="p-4 md:p-5">
            <div class="modal-grid">
              <div class="mb-4">
                <label for="edit-name" class="block mb-2 text-sm font-medium text-gray-900">Name</label>
                <input type="text" id="edit-name"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-position" class="block mb-2 text-sm font-medium text-gray-900">Position</label>
                <input type="text" id="edit-position"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-monthly-salary" class="block mb-2 text-sm font-medium text-gray-900">Monthly
                  Salary</label>
                <input type="number" step="0.01" id="edit-monthly-salary"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-amount-accrued" class="block mb-2 text-sm font-medium text-gray-900">Amount
                  Accrued</label>
                <input type="number" step="0.01" id="edit-amount-accrued"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-withholding-tax" class="block mb-2 text-sm font-medium text-gray-900">Withholding
                  Tax</label>
                <input type="number" step="0.01" id="edit-withholding-tax"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-pagibig-loan" class="block mb-2 text-sm font-medium text-gray-900">PAG-IBIG LOAN -
                  MPL</label>
                <input type="number" step="0.01" id="edit-pagibig-loan"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-philhealth" class="block mb-2 text-sm font-medium text-gray-900">PhilHealth
                  P.S.</label>
                <input type="number" step="0.01" id="edit-philhealth"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-uef" class="block mb-2 text-sm font-medium text-gray-900">UEF / Retirement</label>
                <input type="number" step="0.01" id="edit-uef"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-emergency-loan" class="block mb-2 text-sm font-medium text-gray-900">Emergency
                  Loan</label>
                <input type="number" step="0.01" id="edit-emergency-loan"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-gfal" class="block mb-2 text-sm font-medium text-gray-900">GFAL</label>
                <input type="number" step="0.01" id="edit-gfal"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-lbp-loan" class="block mb-2 text-sm font-medium text-gray-900">LBP Loan</label>
                <input type="number" step="0.01" id="edit-lbp-loan"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-mpl" class="block mb-2 text-sm font-medium text-gray-900">MPL</label>
                <input type="number" step="0.01" id="edit-mpl"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-pagibig-cont" class="block mb-2 text-sm font-medium text-gray-900">PAG-IBIG
                  CONT.</label>
                <input type="number" step="0.01" id="edit-pagibig-cont"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="edit-state-ins" class="block mb-2 text-sm font-medium text-gray-900">STATE INS.
                  G.S.</label>
                <input type="number" step="0.01" id="edit-state-ins"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
            </div>
          </div>
          <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
            <button type="submit"
              class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Save
              Changes</button>
            <button data-modal-hide="edit-modal" type="button"
              class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="delete-modal" tabindex="-1"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-md max-h-full">
      <div class="relative bg-white rounded-lg shadow-lg">
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
          <svg class="mx-auto mb-4 text-red-500 w-12 h-12" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
            fill="none" viewBox="0 0 20 20">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M10 11V6m0 8h.01M19 10a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
          <h3 class="mb-5 text-lg font-normal text-gray-500">Are you sure you want to delete this employee payroll
            record?</h3>
          <p class="mb-5 text-sm">Employee ID: <span id="delete-employee-id" class="font-bold text-red-600"></span>
          </p>
          <p class="mb-5 text-xs text-gray-400">This action cannot be undone.</p>
          <button type="button" onclick="confirmDelete()"
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

  <!-- Add Modal -->
  <div id="add-modal" tabindex="-1" aria-hidden="true"
    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-4xl max-h-full">
      <div class="relative bg-white rounded-lg shadow-lg">
        <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-green-50">
          <h3 class="text-lg font-semibold text-gray-900">
            Add New Payroll Record
          </h3>
          <button type="button"
            class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center"
            data-modal-hide="add-modal">
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
            </svg>
            <span class="sr-only">Close modal</span>
          </button>
        </div>
        <form id="add-form">
          <div class="p-4 md:p-5">
            <div class="modal-grid">
              <div class="mb-4">
                <label for="add-name" class="block mb-2 text-sm font-medium text-gray-900">Name</label>
                <input type="text" id="add-name"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                  required>
              </div>
              <div class="mb-4">
                <label for="add-position" class="block mb-2 text-sm font-medium text-gray-900">Position</label>
                <input type="text" id="add-position"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                  required>
              </div>
              <div class="mb-4">
                <label for="add-monthly-salary" class="block mb-2 text-sm font-medium text-gray-900">Monthly
                  Salary</label>
                <input type="number" step="0.01" id="add-monthly-salary"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                  required>
              </div>
              <div class="mb-4">
                <label for="add-amount-accrued" class="block mb-2 text-sm font-medium text-gray-900">Amount
                  Accrued</label>
                <input type="number" step="0.01" id="add-amount-accrued"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                  required>
              </div>
              <div class="mb-4">
                <label for="add-withholding-tax" class="block mb-2 text-sm font-medium text-gray-900">Withholding
                  Tax</label>
                <input type="number" step="0.01" id="add-withholding-tax"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="add-pagibig-loan" class="block mb-2 text-sm font-medium text-gray-900">PAG-IBIG LOAN -
                  MPL</label>
                <input type="number" step="0.01" id="add-pagibig-loan"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="add-philhealth" class="block mb-2 text-sm font-medium text-gray-900">PhilHealth
                  P.S.</label>
                <input type="number" step="0.01" id="add-philhealth"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="add-uef" class="block mb-2 text-sm font-medium text-gray-900">UEF / Retirement</label>
                <input type="number" step="0.01" id="add-uef"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="add-emergency-loan" class="block mb-2 text-sm font-medium text-gray-900">Emergency
                  Loan</label>
                <input type="number" step="0.01" id="add-emergency-loan"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="add-gfal" class="block mb-2 text-sm font-medium text-gray-900">GFAL</label>
                <input type="number" step="0.01" id="add-gfal"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="add-lbp-loan" class="block mb-2 text-sm font-medium text-gray-900">LBP Loan</label>
                <input type="number" step="0.01" id="add-lbp-loan"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="add-mpl" class="block mb-2 text-sm font-medium text-gray-900">MPL</label>
                <input type="number" step="0.01" id="add-mpl"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="add-pagibig-cont" class="block mb-2 text-sm font-medium text-gray-900">PAG-IBIG
                  CONT.</label>
                <input type="number" step="0.01" id="add-pagibig-cont"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
              <div class="mb-4">
                <label for="add-state-ins" class="block mb-2 text-sm font-medium text-gray-900">STATE INS.
                  G.S.</label>
                <input type="number" step="0.01" id="add-state-ins"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
              </div>
            </div>
          </div>
          <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
            <button type="submit"
              class="text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Add
              Record</button>
            <button data-modal-hide="add-modal" type="button"
              class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100">Cancel</button>
          </div>
        </form>
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
    document.addEventListener('DOMContentLoaded', function () {
      updateDateTime();
      setInterval(updateDateTime, 1000);
    });

    // ==================== NAVBAR FUNCTIONALITY ====================
    document.addEventListener('DOMContentLoaded', function () {
      // User menu toggle
      const userMenuButton = document.getElementById('user-menu-button');
      const userDropdown = document.getElementById('user-dropdown');

      if (userMenuButton && userDropdown) {
        userMenuButton.addEventListener('click', function (e) {
          e.stopPropagation();
          userDropdown.classList.toggle('active');
          userMenuButton.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
          if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.classList.remove('active');
            userMenuButton.classList.remove('active');
          }
        });
      }

      // Sidebar toggle
      const sidebarToggle = document.getElementById('sidebar-toggle');
      const sidebarContainer = document.getElementById('sidebar-container');
      const sidebarOverlay = document.getElementById('sidebar-overlay');

      if (sidebarToggle && sidebarContainer && sidebarOverlay) {
        sidebarToggle.addEventListener('click', function () {
          sidebarContainer.classList.toggle('active');
          sidebarOverlay.classList.toggle('active');
          document.body.style.overflow = 'hidden';
        });

        sidebarOverlay.addEventListener('click', function () {
          sidebarContainer.classList.remove('active');
          sidebarOverlay.classList.remove('active');
          document.body.style.overflow = '';
        });

        // Close sidebar when clicking on overlay
        sidebarOverlay.addEventListener('click', function () {
          sidebarContainer.classList.remove('active');
          sidebarOverlay.classList.remove('active');
          document.body.style.overflow = '';
        });

        // Payroll dropdown in sidebar
        const payrollToggle = document.getElementById('payroll-toggle');
        const payrollDropdown = document.getElementById('payroll-dropdown');
        const payrollChevron = payrollToggle?.querySelector('.chevron');

        if (payrollToggle && payrollDropdown) {
          payrollToggle.addEventListener('click', function (e) {
            e.preventDefault();
            payrollDropdown.classList.toggle('open');
            if (payrollChevron) {
              payrollChevron.classList.toggle('rotated');
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
      }
    });

    // ==================== PAYROLL TABLE FUNCTIONALITY ====================
    let selectedEmployeeId = null;
    let nextEmployeeId = 10; // Starting ID for new employees

    /** Dummy Data Structure (Matching the example rows) */
    const payrollData = {
      1: {
        name: "JENNY E. ARCONADA",
        position: "MSWOO",
        monthlySalary: 95312.00,
        amountAccrued: 47925.50,
        withholdingTax: 7024.19,
        pagibigLoan: 0,
        philhealth: 1193.89,
        uef: 1193.89,
        emergencyLoan: 6031.86,
        gfal: 4817.22,
        lbpLoan: 0,
        mpl: 9249.14,
        pagibigCont: 100.00,
        stateIns: 150.00,
        amountDue: 23414.00,
        netAmount: 23414.00
      },
      2: {
        name: "J.FRANCE GUT, FEDRAZA",
        position: "WYD II",
        monthlySalary: 64649.00,
        amountAccrued: 23734.50,
        withholdingTax: 2025.00,
        pagibigLoan: 0,
        philhealth: 593.11,
        uef: 629.12,
        emergencyLoan: 3793.93,
        gfal: 0,
        lbpLoan: 3156.75,
        mpl: 0,
        pagibigCont: 250.00,
        stateIns: 150.00,
        amountDue: 13549.85,
        netAmount: 13549.85
      },
      9: {
        name: "JAMES PATRICK T. FEDRAZA",
        position: "Mun. Agricultural #1",
        monthlySalary: 88367.00,
        amountAccrued: 44183.50,
        withholdingTax: 6833.89,
        pagibigLoan: 1104.38,
        philhealth: 1104.39,
        uef: 3976.52,
        emergencyLoan: 5302.02,
        gfal: 0,
        lbpLoan: 13574.04,
        mpl: 0,
        pagibigCont: 100.00,
        stateIns: 50.00,
        amountDue: 18594.47,
        netAmount: 18594.47
      },
    };

    const formatCurrency = (value) => {
      if (value === 0 || value === "0" || value === "-") return "-";
      return parseFloat(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    };

    /**
     * Load data into the View modal
     * @param {number} id - The employee ID to view.
     */
    function viewEmployeeData(id) {
      const data = payrollData[id];
      document.getElementById('view-employee-id').textContent = id;

      if (data) {
        document.getElementById('view-name').textContent = data.name;
        document.getElementById('view-position').textContent = data.position;
        document.getElementById('view-monthly-salary').textContent = "₱" + formatCurrency(data.monthlySalary);
        document.getElementById('view-amount-accrued').textContent = "₱" + formatCurrency(data.amountAccrued);
        document.getElementById('view-withholding-tax').textContent = "₱" + formatCurrency(data.withholdingTax);
        document.getElementById('view-pagibig-loan').textContent = "₱" + formatCurrency(data.pagibigLoan);
        document.getElementById('view-philhealth').textContent = "₱" + formatCurrency(data.philhealth);
        document.getElementById('view-uef').textContent = "₱" + formatCurrency(data.uef);
        document.getElementById('view-emergency-loan').textContent = "₱" + formatCurrency(data.emergencyLoan);
        document.getElementById('view-gfal').textContent = "₱" + formatCurrency(data.gfal);
        document.getElementById('view-lbp-loan').textContent = "₱" + formatCurrency(data.lbpLoan);
        document.getElementById('view-mpl').textContent = "₱" + formatCurrency(data.mpl);
        document.getElementById('view-pagibig-cont').textContent = "₱" + formatCurrency(data.pagibigCont);
        document.getElementById('view-state-ins').textContent = "₱" + formatCurrency(data.stateIns);
        document.getElementById('view-amount-due').textContent = "₱" + formatCurrency(data.amountDue);

        // Show the modal
        const viewModalElement = document.getElementById('view-modal');
        const viewModal = new Modal(viewModalElement);
        viewModal.show();
      }
    }

    /**
     * Load data into the Edit modal form
     * @param {number} id - The employee ID to edit.
     */
    function loadEditData(id) {
      selectedEmployeeId = id;
      const data = payrollData[id];

      document.getElementById('edit-employee-id').textContent = id;

      if (data) {
        document.getElementById('edit-name').value = data.name;
        document.getElementById('edit-position').value = data.position;
        document.getElementById('edit-monthly-salary').value = data.monthlySalary;
        document.getElementById('edit-amount-accrued').value = data.amountAccrued;
        document.getElementById('edit-withholding-tax').value = data.withholdingTax;
        document.getElementById('edit-pagibig-loan').value = data.pagibigLoan;
        document.getElementById('edit-philhealth').value = data.philhealth;
        document.getElementById('edit-uef').value = data.uef;
        document.getElementById('edit-emergency-loan').value = data.emergencyLoan;
        document.getElementById('edit-gfal').value = data.gfal;
        document.getElementById('edit-lbp-loan').value = data.lbpLoan;
        document.getElementById('edit-mpl').value = data.mpl;
        document.getElementById('edit-pagibig-cont').value = data.pagibigCont;
        document.getElementById('edit-state-ins').value = data.stateIns;

        // Show the modal
        const editModalElement = document.getElementById('edit-modal');
        const editModal = new Modal(editModalElement);
        editModal.show();
      }
    }

    /**
     * Handle Edit Form Submission
     */
    document.getElementById('edit-form').addEventListener('submit', function (e) {
      e.preventDefault();

      // Update the data in our dummy structure
      payrollData[selectedEmployeeId].name = document.getElementById('edit-name').value;
      payrollData[selectedEmployeeId].position = document.getElementById('edit-position').value;
      payrollData[selectedEmployeeId].monthlySalary = parseFloat(document.getElementById('edit-monthly-salary').value);
      payrollData[selectedEmployeeId].amountAccrued = parseFloat(document.getElementById('edit-amount-accrued').value);
      payrollData[selectedEmployeeId].withholdingTax = parseFloat(document.getElementById('edit-withholding-tax').value);
      payrollData[selectedEmployeeId].pagibigLoan = parseFloat(document.getElementById('edit-pagibig-loan').value);
      payrollData[selectedEmployeeId].philhealth = parseFloat(document.getElementById('edit-philhealth').value);
      payrollData[selectedEmployeeId].uef = parseFloat(document.getElementById('edit-uef').value);
      payrollData[selectedEmployeeId].emergencyLoan = parseFloat(document.getElementById('edit-emergency-loan').value);
      payrollData[selectedEmployeeId].gfal = parseFloat(document.getElementById('edit-gfal').value);
      payrollData[selectedEmployeeId].lbpLoan = parseFloat(document.getElementById('edit-lbp-loan').value);
      payrollData[selectedEmployeeId].mpl = parseFloat(document.getElementById('edit-mpl').value);
      payrollData[selectedEmployeeId].pagibigCont = parseFloat(document.getElementById('edit-pagibig-cont').value);
      payrollData[selectedEmployeeId].stateIns = parseFloat(document.getElementById('edit-state-ins').value);

      // Recalculate amount due (simplified calculation)
      const totalDeductions =
        payrollData[selectedEmployeeId].withholdingTax +
        payrollData[selectedEmployeeId].pagibigLoan +
        payrollData[selectedEmployeeId].philhealth +
        payrollData[selectedEmployeeId].uef +
        payrollData[selectedEmployeeId].emergencyLoan +
        payrollData[selectedEmployeeId].gfal +
        payrollData[selectedEmployeeId].lbpLoan +
        payrollData[selectedEmployeeId].mpl +
        payrollData[selectedEmployeeId].pagibigCont +
        payrollData[selectedEmployeeId].stateIns;

      payrollData[selectedEmployeeId].amountDue =
        payrollData[selectedEmployeeId].amountAccrued - totalDeductions;

      payrollData[selectedEmployeeId].netAmount = payrollData[selectedEmployeeId].amountDue;

      // Update the table row
      const row = document.querySelector(`.employee-row[data-id="${selectedEmployeeId}"]`);
      if (row) {
        row.querySelector('td:nth-child(2)').textContent = payrollData[selectedEmployeeId].name;
        row.querySelector('td:nth-child(3)').textContent = payrollData[selectedEmployeeId].position;
        row.querySelector('td:nth-child(4)').textContent = formatCurrency(payrollData[selectedEmployeeId].monthlySalary);
        row.querySelector('td:nth-child(5)').textContent = formatCurrency(payrollData[selectedEmployeeId].amountAccrued);
        row.querySelector('td:nth-child(6)').textContent = formatCurrency(payrollData[selectedEmployeeId].withholdingTax);
        row.querySelector('td:nth-child(7)').textContent = formatCurrency(payrollData[selectedEmployeeId].pagibigLoan);
        row.querySelector('td:nth-child(10)').textContent = formatCurrency(payrollData[selectedEmployeeId].philhealth);
        row.querySelector('td:nth-child(11)').textContent = formatCurrency(payrollData[selectedEmployeeId].uef);
        row.querySelector('td:nth-child(12)').textContent = formatCurrency(payrollData[selectedEmployeeId].emergencyLoan);
        row.querySelector('td:nth-child(13)').textContent = formatCurrency(payrollData[selectedEmployeeId].gfal);
        row.querySelector('td:nth-child(14)').textContent = formatCurrency(payrollData[selectedEmployeeId].lbpLoan);
        row.querySelector('td:nth-child(15)').textContent = formatCurrency(payrollData[selectedEmployeeId].mpl);
        row.querySelector('td:nth-child(18)').textContent = formatCurrency(payrollData[selectedEmployeeId].pagibigCont);
        row.querySelector('td:nth-child(19)').textContent = formatCurrency(payrollData[selectedEmployeeId].stateIns);
        row.querySelector('td:nth-child(20)').textContent = formatCurrency(payrollData[selectedEmployeeId].amountDue);
        row.querySelector('.data-net-amount').textContent = formatCurrency(payrollData[selectedEmployeeId].netAmount);
      }

      // Close modal
      const editModalElement = document.getElementById('edit-modal');
      const editModal = new Modal(editModalElement);
      editModal.hide();

      // Show success notification
      showNotification(`Employee ID ${selectedEmployeeId} data updated successfully!`, 'success');
    });

    /**
     * Set the ID for the Delete confirmation modal
     * @param {number} id - The employee ID to delete.
     */
    function setDeleteId(id) {
      selectedEmployeeId = id;
      document.getElementById('delete-employee-id').textContent = id;

      // Show the modal
      const deleteModalElement = document.getElementById('delete-modal');
      const deleteModal = new Modal(deleteModalElement);
      deleteModal.show();
    }

    /**
     * Handle Delete confirmation
     */
    function confirmDelete() {
      console.log(`Confirmed delete for Employee ID ${selectedEmployeeId}`);

      // Remove the corresponding row from the table
      const rowToDelete = document.querySelector(`.employee-row[data-id="${selectedEmployeeId}"]`);
      if (rowToDelete) {
        rowToDelete.remove();
        delete payrollData[selectedEmployeeId];
        // In a real application, you would also update the totals
        showNotification(`Employee ID ${selectedEmployeeId} successfully deleted.`, 'success');
      } else {
        showNotification(`Error: Employee ID ${selectedEmployeeId} not found.`, 'error');
      }

      // Close modal
      const deleteModalElement = document.getElementById('delete-modal');
      const deleteModal = new Modal(deleteModalElement);
      deleteModal.hide();
    }

    // Add new payroll record
    document.getElementById('add-button').addEventListener('click', function () {
      const addModalElement = document.getElementById('add-modal');
      const addModal = new Modal(addModalElement);
      addModal.show();
    });

    // Handle Add Form Submission
    document.getElementById('add-form').addEventListener('submit', function (e) {
      e.preventDefault();

      const newEmployeeId = nextEmployeeId++;

      // Create new employee data
      payrollData[newEmployeeId] = {
        name: document.getElementById('add-name').value,
        position: document.getElementById('add-position').value,
        monthlySalary: parseFloat(document.getElementById('add-monthly-salary').value),
        amountAccrued: parseFloat(document.getElementById('add-amount-accrued').value),
        withholdingTax: parseFloat(document.getElementById('add-withholding-tax').value) || 0,
        pagibigLoan: parseFloat(document.getElementById('add-pagibig-loan').value) || 0,
        philhealth: parseFloat(document.getElementById('add-philhealth').value) || 0,
        uef: parseFloat(document.getElementById('add-uef').value) || 0,
        emergencyLoan: parseFloat(document.getElementById('add-emergency-loan').value) || 0,
        gfal: parseFloat(document.getElementById('add-gfal').value) || 0,
        lbpLoan: parseFloat(document.getElementById('add-lbp-loan').value) || 0,
        mpl: parseFloat(document.getElementById('add-mpl').value) || 0,
        pagibigCont: parseFloat(document.getElementById('add-pagibig-cont').value) || 0,
        stateIns: parseFloat(document.getElementById('add-state-ins').value) || 0,
        amountDue: 0,
        netAmount: 0
      };

      // Calculate amount due
      const totalDeductions =
        payrollData[newEmployeeId].withholdingTax +
        payrollData[newEmployeeId].pagibigLoan +
        payrollData[newEmployeeId].philhealth +
        payrollData[newEmployeeId].uef +
        payrollData[newEmployeeId].emergencyLoan +
        payrollData[newEmployeeId].gfal +
        payrollData[newEmployeeId].lbpLoan +
        payrollData[newEmployeeId].mpl +
        payrollData[newEmployeeId].pagibigCont +
        payrollData[newEmployeeId].stateIns;

      payrollData[newEmployeeId].amountDue =
        payrollData[newEmployeeId].amountAccrued - totalDeductions;

      payrollData[newEmployeeId].netAmount = payrollData[newEmployeeId].amountDue;

      // Add new row to the table
      const tbody = document.querySelector('.payroll-table tbody');
      const newRow = document.createElement('tr');
      newRow.className = 'employee-row';
      newRow.setAttribute('data-id', newEmployeeId);

      newRow.innerHTML = `
        <td>${newEmployeeId}</td>
        <td class="text-left pl-2">${payrollData[newEmployeeId].name}</td>
        <td>${payrollData[newEmployeeId].position}</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].monthlySalary)}</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].amountAccrued)}</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].withholdingTax)}</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].pagibigLoan)}</td>
        <td class="text-right currency-cell">-</td>
        <td class="text-right currency-cell">-</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].philhealth)}</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].uef)}</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].emergencyLoan)}</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].gfal)}</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].lbpLoan)}</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].mpl)}</td>
        <td class="text-right currency-cell">-</td>
        <td class="text-right currency-cell">-</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].pagibigCont)}</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].stateIns)}</td>
        <td class="text-right currency-cell">${formatCurrency(payrollData[newEmployeeId].amountDue)}</td>
        <td class="text-right currency-cell">${newEmployeeId}</td>
        <td class="text-right currency-cell data-net-amount important-column">${formatCurrency(payrollData[newEmployeeId].netAmount)}</td>
        <td>${newEmployeeId}</td>
        <td class="italic text-gray-500">(Not signed)</td>
        <td class="text-center action-cell mobile-hide">
          <div class="flex space-x-1 justify-center">
            <button data-modal-target="view-modal" data-modal-toggle="view-modal" type="button" class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-2 py-1 rounded text-[0.65rem] font-medium" onclick="viewEmployeeData(${newEmployeeId})">View</button>
            <button data-modal-target="edit-modal" data-modal-toggle="edit-modal" type="button" class="text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50 px-2 py-1 rounded text-[0.65rem] font-medium" onclick="loadEditData(${newEmployeeId})">Edit</button>
            <button data-modal-target="delete-modal" data-modal-toggle="delete-modal" type="button" class="text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded text-[0.65rem] font-medium" onclick="setDeleteId(${newEmployeeId})">Delete</button>
          </div>
        </td>
      `;

      // Insert before the totals row
      const totalsRow = document.querySelector('.totals-row');
      tbody.insertBefore(newRow, totalsRow);

      // Close modal and reset form
      const addModalElement = document.getElementById('add-modal');
      const addModal = new Modal(addModalElement);
      addModal.hide();

      document.getElementById('add-form').reset();

      showNotification(`New payroll record added with ID: ${newEmployeeId}`, 'success');
    });

    // Global Action Button Logics
    document.getElementById('save-button').addEventListener('click', () => {
      showNotification('Saving General Payroll Data to database...', 'info');
    });

    document.getElementById('next-button').addEventListener('click', () => {
      showNotification('Moving to the next payroll sheet or period.', 'info');
    });

    // Print button functionality
    document.getElementById('print-button').addEventListener('click', () => {
      window.print();
    });

    // Notification function
    function showNotification(message, type = 'info') {
      // Remove existing notification
      const existingNotification = document.querySelector('.custom-notification');
      if (existingNotification) {
        existingNotification.remove();
      }

      const notification = document.createElement('div');
      notification.className = `custom-notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white ${type === 'success' ? 'bg-green-500' :
        type === 'error' ? 'bg-red-500' :
          type === 'warning' ? 'bg-yellow-500' :
            'bg-blue-500'
        }`;

      notification.innerHTML = `
        <div class="flex items-center">
          <i class="fas ${type === 'success' ? 'fa-check-circle' :
          type === 'error' ? 'fa-exclamation-circle' :
            type === 'warning' ? 'fa-exclamation-triangle' :
              'fa-info-circle'
        } mr-2"></i>
          <span>${message}</span>
        </div>
      `;

      document.body.appendChild(notification);

      // Auto remove after 3 seconds
      setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.5s';
        setTimeout(() => {
          if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
          }
        }, 500);
      }, 3000);
    }
  </script>
</body>

</html>