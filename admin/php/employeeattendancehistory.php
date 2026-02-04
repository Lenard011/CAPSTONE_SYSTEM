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
  <title>Attendance History</title>
  <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
      --success: #10b981;
      --danger: #ef4444;
      --warning: #f59e0b;
      --info: #0ea5e9;
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
      color: #1f2937;
      overflow-x: hidden;
    }

    /* NAVBAR STYLES - Keep your existing navbar styles */
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

    /* Mobile Brand */
    .mobile-brand {
      display: flex;
      align-items: center;
      display: none;
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
      border: none;
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
      width: 16rem;
      z-index: 90;
      transform: translateX(-100%);
      transition: transform 0.3s ease-in-out;
    }

    .sidebar-container.active {
      transform: translateX(0);
    }

    .sidebar {
      width: 100%;
      height: 100%;
      background: var(--gradient-nav);
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

    .sidebar-dropdown-item:hover {
      background: rgba(255, 255, 255, 0.1);
      color: white;
      transform: translateX(5px);
    }

    .sidebar-dropdown-item i {
      font-size: 0.75rem;
      margin-right: 0.5rem;
    }

    /* IMPROVED MAIN CONTENT LAYOUT */
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
      }

      .main-content {
        margin-left: 16rem;
        width: calc(100% - 16rem);
      }
    }

    /* Page Header */
    .page-header {
      margin-bottom: 1.5rem;
    }

    .page-title {
      font-size: 1.875rem;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 0.5rem;
    }

    .page-subtitle {
      font-size: 1rem;
      color: #6b7280;
    }

    /* Improved Stats Section */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(1, 1fr);
      gap: 1rem;
      margin-bottom: 2rem;
    }

    @media (min-width: 640px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (min-width: 1024px) {
      .stats-grid {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      border: 1px solid #e5e7eb;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .stat-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.75rem;
    }

    .stat-title {
      font-size: 0.875rem;
      font-weight: 500;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .stat-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
    }

    .stat-icon.attendance {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.2) 100%);
      color: var(--accent);
    }

    .stat-icon.present {
      background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.2) 100%);
      color: var(--success);
    }

    .stat-icon.absent {
      background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.2) 100%);
      color: var(--danger);
    }

    .stat-icon.late {
      background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.2) 100%);
      color: var(--warning);
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: #1f2937;
      line-height: 1;
      margin-bottom: 0.5rem;
    }

    .stat-change {
      font-size: 0.875rem;
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }

    .stat-change.positive {
      color: var(--success);
    }

    .stat-change.negative {
      color: var(--danger);
    }

    /* Tabs Navigation */
    .tabs-navigation {
      display: flex;
      background: white;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      margin-bottom: 2rem;
      overflow-x: auto;
      border: 1px solid #e5e7eb;
    }

    .tab-link {
      padding: 1rem 1.5rem;
      font-weight: 500;
      color: #6b7280;
      text-decoration: none;
      white-space: nowrap;
      transition: all 0.3s ease;
      position: relative;
      border: none;
      background: none;
      cursor: pointer;
    }

    .tab-link:hover {
      color: var(--primary);
      background: #f9fafb;
    }

    .tab-link.active {
      color: var(--primary);
    }

    .tab-link.active::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 3px;
      background: var(--primary);
      border-radius: 3px 3px 0 0;
    }

    /* Main Dashboard Card */
    .dashboard-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      padding: 1.5rem;
      margin-bottom: 2rem;
      border: 1px solid #e5e7eb;
    }

    .card-header {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid #e5e7eb;
    }

    @media (min-width: 768px) {
      .card-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
      }
    }

    .card-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: #1f2937;
    }

    /* IMPROVED CONTROLS SECTION */
    .controls-container {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      width: 100%;
    }

    @media (min-width: 768px) {
      .controls-container {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
      }
    }

    .search-container {
      position: relative;
      flex: 1;
      min-width: 250px;
    }

    .search-input {
      width: 100%;
      padding: 0.75rem 1rem 0.75rem 3rem;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 0.875rem;
      transition: all 0.3s ease;
      background: #f9fafb;
    }

    .search-input:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
      background: white;
    }

    .search-icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #6b7280;
    }

    .action-buttons {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .btn {
      padding: 0.75rem 1rem;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      cursor: pointer;
      transition: all 0.3s ease;
      border: none;
      white-space: nowrap;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background: var(--secondary);
      transform: translateY(-2px);
    }

    .btn-outline {
      background: white;
      color: #374151;
      border: 1px solid #d1d5db;
    }

    .btn-outline:hover {
      background: #f9fafb;
      border-color: #9ca3af;
    }

    /* FILTERS SECTION */
    .filters-section {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin-top: 1rem;
    }

    .filter-group {
      position: relative;
      min-width: 150px;
    }

    .filter-select {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 0.875rem;
      background: white;
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 1rem;
    }

    .filter-select:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* IMPROVED TABLE DESIGN - RESPONSIVE */
    .table-responsive {
      width: 100%;
      overflow-x: auto;
      border-radius: 8px;
      border: 1px solid #e5e7eb;
      background: white;
      margin-top: 1.5rem;
      -webkit-overflow-scrolling: touch;
    }

    .attendance-table {
      width: 100%;
      min-width: 1200px;
      border-collapse: separate;
      border-spacing: 0;
    }

    .attendance-table thead {
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    }

    .attendance-table th {
      padding: 1rem 1.25rem;
      text-align: center;
      font-weight: 600;
      color: white;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      white-space: nowrap;
      border-bottom: none;
      position: sticky;
      top: 0;
      z-index: 10;
    }

    .attendance-table tbody tr {
      border-bottom: 1px solid #f3f4f6;
      transition: background-color 0.2s ease;
    }

    .attendance-table tbody tr:hover {
      background-color: #f9fafb;
    }

    .attendance-table td {
      padding: 1rem 1.25rem;
      color: #374151;
      font-size: 0.875rem;
      vertical-align: middle;
    }

    /* Compact Day Cells for Better Mobile View */
    .day-cell {
      width: 40px;
      min-width: 40px;
      text-align: center;
      padding: 0.5rem !important;
    }

    .day-header {
      width: 40px;
      min-width: 40px;
      text-align: center;
      padding: 0.75rem !important;
    }

    /* Employee Info Cell */
    .employee-cell {
      min-width: 180px;
      position: sticky;
      left: 0;
      background: white;
      z-index: 5;
      box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
    }

    .attendance-table tbody tr:hover .employee-cell {
      background-color: #f9fafb;
    }

    .employee-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 0.75rem;
    }

    .employee-info {
      display: flex;
      align-items: center;
    }

    .employee-details {
      display: flex;
      flex-direction: column;
    }

    .employee-name {
      font-weight: 600;
      color: #1f2937;
      font-size: 0.875rem;
    }

    .employee-id {
      font-size: 0.75rem;
      color: #6b7280;
    }

    /* IMPROVED STATUS BADGES */
    .status-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.375rem 0.5rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      min-width: 24px;
      height: 24px;
    }

    .status-present {
      background: rgba(16, 185, 129, 0.1);
      color: var(--success);
      border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .status-absent {
      background: rgba(239, 68, 68, 0.1);
      color: var(--danger);
      border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .status-late {
      background: rgba(245, 158, 11, 0.1);
      color: var(--warning);
      border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .status-halfday {
      background: rgba(59, 130, 246, 0.1);
      color: var(--accent);
      border: 1px solid rgba(59, 130, 246, 0.2);
    }

    .status-holiday {
      background: rgba(156, 163, 175, 0.1);
      color: #6b7280;
      border: 1px solid rgba(156, 163, 175, 0.2);
    }

    /* Summary Badges */
    .summary-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 600;
    }

    /* Pagination */
    .pagination {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 0.5rem;
      margin-top: 1.5rem;
      padding: 1rem;
      border-top: 1px solid #e5e7eb;
    }

    .pagination-btn {
      padding: 0.5rem 1rem;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      background: white;
      color: #374151;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .pagination-btn:hover:not(:disabled) {
      background: #f3f4f6;
      border-color: #9ca3af;
    }

    .pagination-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .page-numbers {
      display: flex;
      gap: 0.25rem;
    }

    .page-number {
      width: 2.5rem;
      height: 2.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      background: white;
      border: 1px solid #d1d5db;
      color: #374151;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .page-number:hover {
      background: #f3f4f6;
      border-color: #9ca3af;
    }

    .page-number.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }

    /* Mobile Optimizations */
    @media (max-width: 767px) {
      .navbar {
        height: 65px;
      }

      .navbar-container {
        padding: 0 1rem;
      }

      .mobile-toggle {
        display: flex;
      }

      .navbar-brand {
        display: none;
      }

      .mobile-brand {
        display: flex;
      }

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
        width: 250px;
      }

      .main-content {
        margin-top: 65px;
        padding: 1rem;
      }

      .page-title {
        font-size: 1.5rem;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .stat-value {
        font-size: 1.75rem;
      }

      .tabs-navigation {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      .tab-link {
        padding: 0.875rem 1rem;
        font-size: 0.875rem;
      }

      .controls-container {
        gap: 0.75rem;
      }

      .action-buttons {
        width: 100%;
      }

      .btn {
        flex: 1;
        justify-content: center;
      }

      .table-responsive {
        border-radius: 0;
        border-left: none;
        border-right: none;
        margin-left: -1rem;
        margin-right: -1rem;
        width: calc(100% + 2rem);
      }

      /* Better mobile table view */
      .day-cell {
        width: 36px;
        min-width: 36px;
        padding: 0.375rem !important;
      }

      .day-header {
        width: 36px;
        min-width: 36px;
        padding: 0.5rem !important;
        font-size: 0.7rem;
      }

      .employee-cell {
        min-width: 160px;
      }

      .attendance-table th,
      .attendance-table td {
        padding: 0.75rem 0.5rem;
      }

      .pagination {
        flex-wrap: wrap;
      }

      .pagination-btn span {
        display: none;
      }

      .pagination-btn i {
        margin: 0;
      }
    }

    @media (max-width: 480px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .stat-card {
        padding: 1.25rem;
      }

      .action-buttons {
        flex-direction: column;
      }

      .filters-section {
        flex-direction: column;
      }

      .filter-group {
        width: 100%;
      }

      .employee-avatar {
        width: 28px;
        height: 28px;
      }

      .day-cell {
        width: 32px;
        min-width: 32px;
      }

      .day-header {
        width: 32px;
        min-width: 32px;
        font-size: 0.65rem;
      }
    }

    /* Scrollbar Styling */
    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
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

    /* Loading State */
    .loading {
      opacity: 0.6;
      pointer-events: none;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 3rem;
      color: #6b7280;
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 1rem;
      opacity: 0.5;
    }

    .empty-state h3 {
      font-size: 1.25rem;
      margin-bottom: 0.5rem;
      color: #374151;
    }

    /* Table Summary Cells */
    .summary-cell {
      text-align: center;
      font-weight: 600;
    }

    /* Improved Filter Layout */
    .filter-section-container {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      align-items: center;
    }

    /* Fix for tabs layout */
    .tabs-container {
      margin-bottom: 2rem;
    }
  </style>
</head>

<body class="bg-gray-50">
  <!-- Navigation Header -->
  <nav class="navbar">
    <div class="navbar-container">
      <div class="navbar-left">
        <button class="mobile-toggle" id="sidebar-toggle">
          <i class="fas fa-bars"></i>
        </button>

        <a href="../dashboard.php" class="navbar-brand">
          <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
          <div class="brand-text">
            <span class="brand-title">HR Management System</span>
            <span class="brand-subtitle">Paluan Occidental Mindoro</span>
          </div>
        </a>

        <div class="mobile-brand">
          <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
          <div class="mobile-brand-text">
            <span class="mobile-brand-title">HRMS</span>
            <span class="mobile-brand-subtitle">Dashboard</span>
          </div>
        </div>
      </div>

      <div class="navbar-right">
        <div class="datetime-container">
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
          <li>
            <a href="../php/dashboard.php" class="sidebar-item">
              <i class="fas fa-chart-line"></i>
              <span>Dashboard Analytics</span>
            </a>
          </li>

          <li>
            <a href="../php/employees/Employee.php" class="sidebar-item ">
              <i class="fas fa-users"></i>
              <span>Employees</span>
            </a>
          </li>

          <li>
            <a href="../php/attendance.php" class="sidebar-item">
              <i class="fas fa-calendar-check"></i>
              <span>Attendance</span>
            </a>
          </li>

          <li>
            <a href="#" class="sidebar-item" id="payroll-toggle">
              <i class="fas fa-money-bill-wave"></i>
              <span>Payroll</span>
              <i class="fas fa-chevron-down chevron text-xs ml-auto"></i>
            </a>
            <div class="sidebar-dropdown-menu" id="payroll-dropdown">
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

        

          <li>
            <a href="paysliplist.php" class="sidebar-item active">
              <i class="fas fa-file-alt"></i>
              <span>Reports</span>
            </a>
          </li>

          <li>
            <a href="sallarypayheads.php" class="sidebar-item">
              <i class="fas fa-hand-holding-usd"></i>
              <span>Salary Structure</span>
            </a>
          </li>

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
    <!-- Page Header -->
    <div class="page-header">
      <h1 class="page-title">Attendance History</h1>
      <p class="page-subtitle">View and manage employee attendance records</p>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs-container">
      <div class="tabs-navigation">
        <a href="./paysliphistory.php" class="tab-link">
          <i class="fas fa-file-invoice-dollar mr-2"></i>
          Payslip History
        </a>
        <button class="tab-link active">
          <i class="fas fa-history mr-2"></i>
          Attendance History
        </button>
      
      </div>
    </div>

    <!-- Stats Overview -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-header">
          <h3 class="stat-title">Total Employees</h3>
          <div class="stat-icon attendance">
            <i class="fas fa-users"></i>
          </div>
        </div>
        <div class="stat-value">24</div>
        <div class="stat-change positive">
          <i class="fas fa-arrow-up"></i>
          <span>2 this month</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <h3 class="stat-title">Avg. Attendance</h3>
          <div class="stat-icon present">
            <i class="fas fa-check-circle"></i>
          </div>
        </div>
        <div class="stat-value">89.2%</div>
        <div class="stat-change positive">
          <i class="fas fa-arrow-up"></i>
          <span>1.5% from last month</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <h3 class="stat-title">Total Absences</h3>
          <div class="stat-icon absent">
            <i class="fas fa-times-circle"></i>
          </div>
        </div>
        <div class="stat-value">32</div>
        <div class="stat-change negative">
          <i class="fas fa-arrow-down"></i>
          <span>5 from last month</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <h3 class="stat-title">Late Arrivals</h3>
          <div class="stat-icon late">
            <i class="fas fa-clock"></i>
          </div>
        </div>
        <div class="stat-value">18</div>
        <div class="stat-change positive">
          <i class="fas fa-arrow-down"></i>
          <span>3 from last month</span>
        </div>
      </div>
    </div>

    <!-- Main Content Card -->
    <div class="dashboard-card">
      <div class="card-header">
        <h2 class="card-title">Attendance Records</h2>
        
        <div class="controls-container">
          <!-- Search -->
          <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" placeholder="Search employee name or ID..." id="searchInput">
          </div>

          <!-- Action Buttons -->
          <div class="action-buttons">
            <div class="filter-group">
              <select class="filter-select" id="monthFilter">
                <option value="">All Months</option>
                <option value="01">January</option>
                <option value="02">February</option>
                <option value="03">March</option>
                <option value="04">April</option>
                <option value="05">May</option>
                <option value="06">June</option>
                <option value="07">July</option>
                <option value="08">August</option>
                <option value="09">September</option>
                <option value="10">October</option>
                <option value="11">November</option>
                <option value="12" selected>December</option>
              </select>
            </div>

            <div class="filter-group">
              <select class="filter-select" id="statusFilter">
                <option value="">All Status</option>
                <option value="present">Present</option>
                <option value="absent">Absent</option>
                <option value="late">Late</option>
                <option value="halfday">Half Day</option>
              </select>
            </div>

            <button class="btn btn-primary" id="exportBtn">
              <i class="fas fa-file-export"></i>
              Export Excel
            </button>
          </div>
        </div>

        <!-- Additional Filters -->
        <div class="filters-section">
          <div class="filter-group">
            <select class="filter-select" id="departmentFilter">
              <option value="">All Departments</option>
              <option value="management">Management</option>
              <option value="finance">Finance</option>
              <option value="it">IT Department</option>
              <option value="hr">Human Resources</option>
              <option value="operations">Operations</option>
            </select>
          </div>

          <div class="filter-group">
            <select class="filter-select" id="yearFilter">
              <option value="2024" selected>2024</option>
              <option value="2023">2023</option>
              <option value="2022">2022</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Responsive Table -->
      <div class="table-responsive">
        <table class="attendance-table" id="attendanceTable">
          <thead>
            <tr>
              <th class="employee-cell">Employee</th>
              <th class="summary-cell">Total Days</th>
              <th class="summary-cell">Present</th>
              <th class="summary-cell">Absent</th>
              <th class="summary-cell">Late</th>
              <th class="summary-cell">Half Days</th>
              <!-- Day Headers -->
              <th class="day-header">1</th>
              <th class="day-header">2</th>
              <th class="day-header">3</th>
              <th class="day-header">4</th>
              <th class="day-header">5</th>
              <th class="day-header">6</th>
              <th class="day-header">7</th>
              <th class="day-header">8</th>
              <th class="day-header">9</th>
              <th class="day-header">10</th>
              <th class="day-header">11</th>
              <th class="day-header">12</th>
              <th class="day-header">13</th>
              <th class="day-header">14</th>
              <th class="day-header">15</th>
              <th class="day-header">16</th>
              <th class="day-header">17</th>
              <th class="day-header">18</th>
              <th class="day-header">19</th>
              <th class="day-header">20</th>
              <th class="day-header">21</th>
              <th class="day-header">22</th>
              <th class="day-header">23</th>
              <th class="day-header">24</th>
              <th class="day-header">25</th>
              <th class="day-header">26</th>
              <th class="day-header">27</th>
              <th class="day-header">28</th>
              <th class="day-header">29</th>
              <th class="day-header">30</th>
              <th class="day-header">31</th>
            </tr>
          </thead>
          <tbody id="tableBody">
            <!-- Table rows will be populated by JavaScript -->
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination">
        <button class="pagination-btn" id="prevBtn" disabled>
          <i class="fas fa-chevron-left"></i>
          <span>Previous</span>
        </button>
        
        <div class="page-numbers" id="pageNumbers">
          <!-- Page numbers will be generated by JavaScript -->
        </div>
        
        <button class="pagination-btn" id="nextBtn">
          <span>Next</span>
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize data with more sample employees
      const attendanceData = [
        {
          id: 1,
          name: "Emmanuel P. Recto",
          department: "Management",
          avatar: "https://ui-avatars.com/api/?name=Emmanuel+Recto&background=3b82f6&color=fff",
          totalDays: 22,
          attendance: ["P", "P", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "A", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "P"]
        },
        {
          id: 2,
          name: "Maria Cristina G. Santos",
          department: "Finance",
          avatar: "https://ui-avatars.com/api/?name=Maria+Santos&background=10b981&color=fff",
          totalDays: 20,
          attendance: ["P", "P", "L", "P", "A", "H", "H", "P", "P", "P", "P", "P", "H", "H", "L", "P", "A", "P", "P", "P", "P", "P", "A", "P", "P", "P", "H", "H", "P", "P", "P"]
        },
        {
          id: 3,
          name: "Juan Dela Cruz",
          department: "IT",
          avatar: "https://ui-avatars.com/api/?name=Juan+Cruz&background=8b5cf6&color=fff",
          totalDays: 23,
          attendance: ["P", "P", "P", "L", "P", "H", "H", "P", "P", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "L", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "P"]
        },
        {
          id: 4,
          name: "Andrea B. Lopez",
          department: "Human Resources",
          avatar: "https://ui-avatars.com/api/?name=Andrea+Lopez&background=ec4899&color=fff",
          totalDays: 21,
          attendance: ["P", "P", "P", "P", "A", "H", "H", "P", "P", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "A", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "P"]
        },
        {
          id: 5,
          name: "Robert S. Gonzales",
          department: "Operations",
          avatar: "https://ui-avatars.com/api/?name=Robert+Gonzales&background=f59e0b&color=fff",
          totalDays: 22,
          attendance: ["P", "P", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "A", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "P"]
        },
        {
          id: 6,
          name: "Carmen R. Reyes",
          department: "Finance",
          avatar: "https://ui-avatars.com/api/?name=Carmen+Reyes&background=ef4444&color=fff",
          totalDays: 19,
          attendance: ["P", "A", "P", "P", "P", "H", "H", "P", "P", "A", "P", "P", "H", "H", "P", "P", "P", "P", "A", "P", "P", "A", "P", "H", "H", "P", "P", "P", "P", "P", "P"]
        },
        {
          id: 7,
          name: "James M. Tan",
          department: "IT",
          avatar: "https://ui-avatars.com/api/?name=James+Tan&background=10b981&color=fff",
          totalDays: 22,
          attendance: ["P", "P", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "A", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "P"]
        },
        {
          id: 8,
          name: "Patricia L. Lim",
          department: "Human Resources",
          avatar: "https://ui-avatars.com/api/?name=Patricia+Lim&background=8b5cf6&color=fff",
          totalDays: 20,
          attendance: ["P", "P", "P", "P", "A", "H", "H", "P", "P", "P", "P", "A", "H", "H", "P", "P", "P", "P", "P", "A", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "P"]
        },
        {
          id: 9,
          name: "Michael A. Sy",
          department: "Management",
          avatar: "https://ui-avatars.com/api/?name=Michael+Sy&background=3b82f6&color=fff",
          totalDays: 23,
          attendance: ["P", "P", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "P", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "P"]
        },
        {
          id: 10,
          name: "Susan T. Ong",
          department: "Operations",
          avatar: "https://ui-avatars.com/api/?name=Susan+Ong&background=f59e0b&color=fff",
          totalDays: 21,
          attendance: ["P", "P", "P", "A", "P", "H", "H", "P", "P", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "A", "P", "P", "P", "H", "H", "P", "P", "P", "P", "P", "P"]
        }
      ];

      // Pagination variables
      let currentPage = 1;
      const rowsPerPage = 10;
      let filteredData = [...attendanceData];

      // Update Date and Time
      function updateDateTime() {
        const now = new Date();
        
        // Format date
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const formattedDate = now.toLocaleDateString('en-US', options);
        const dateElement = document.getElementById('current-date');
        if (dateElement) dateElement.textContent = formattedDate;
        
        // Format time
        const formattedTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const timeElement = document.getElementById('current-time');
        if (timeElement) timeElement.textContent = formattedTime;
      }
      
      updateDateTime();
      setInterval(updateDateTime, 1000);

      // Sidebar functionality
      const sidebarToggle = document.getElementById('sidebar-toggle');
      const sidebarOverlay = document.getElementById('sidebar-overlay');
      const sidebarContainer = document.getElementById('sidebar-container');
      const userMenuButton = document.getElementById('user-menu-button');
      const userDropdown = document.getElementById('user-dropdown');
      const payrollToggle = document.getElementById('payroll-toggle');
      const payrollDropdown = document.getElementById('payroll-dropdown');
      
      function toggleSidebar() {
        if (window.innerWidth < 768) {
          sidebarContainer.classList.toggle('active');
          sidebarOverlay.classList.toggle('active');
        }
      }
      
      function toggleUserDropdown() {
        userDropdown.classList.toggle('active');
        userMenuButton.classList.toggle('active');
      }
      
      function togglePayrollDropdown() {
        payrollDropdown.classList.toggle('open');
        const chevron = payrollToggle.querySelector('.chevron');
        chevron.classList.toggle('rotated');
      }
      
      if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
      if (sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);
      if (userMenuButton) userMenuButton.addEventListener('click', toggleUserDropdown);
      if (payrollToggle) payrollToggle.addEventListener('click', togglePayrollDropdown);
      
      // Close dropdowns when clicking outside
      document.addEventListener('click', function(event) {
        // Close user dropdown
        if (!event.target.closest('.user-menu')) {
          userDropdown.classList.remove('active');
          userMenuButton.classList.remove('active');
        }
        
        // Close mobile sidebar
        if (window.innerWidth < 768 && 
            !event.target.closest('.sidebar-container') && 
            !event.target.closest('#sidebar-toggle')) {
          sidebarContainer.classList.remove('active');
          sidebarOverlay.classList.remove('active');
        }
        
        // Close payroll dropdown
        if (!event.target.closest('#payroll-toggle')) {
          payrollDropdown.classList.remove('open');
          const chevron = payrollToggle.querySelector('.chevron');
          chevron.classList.remove('rotated');
        }
      });
      
      // Handle window resize
      function handleResize() {
        if (window.innerWidth >= 768) {
          sidebarContainer.classList.remove('active');
          sidebarOverlay.classList.remove('active');
        }
      }
      
      window.addEventListener('resize', handleResize);

      // Table rendering functions
      function renderTable() {
        const tableBody = document.getElementById('tableBody');
        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;
        const pageData = filteredData.slice(startIndex, endIndex);
        
        tableBody.innerHTML = '';
        
        if (pageData.length === 0) {
          tableBody.innerHTML = `
            <tr>
              <td colspan="37" class="empty-state">
                <i class="fas fa-search"></i>
                <h3>No records found</h3>
                <p>Try adjusting your search or filter criteria</p>
              </td>
            </tr>
          `;
          return;
        }
        
        pageData.forEach(employee => {
          const row = document.createElement('tr');
          
          // Calculate attendance summary
          const presentCount = employee.attendance.filter(a => a === 'P').length;
          const absentCount = employee.attendance.filter(a => a === 'A').length;
          const lateCount = employee.attendance.filter(a => a === 'L').length;
          const holidayCount = employee.attendance.filter(a => a === 'H').length;
          const workingDays = employee.totalDays - holidayCount;
          
          row.innerHTML = `
            <td class="employee-cell">
              <div class="employee-info">
                <img src="${employee.avatar}" alt="${employee.name}" class="employee-avatar">
                <div class="employee-details">
                  <div class="employee-name">${employee.name}</div>
                  <div class="employee-id">${employee.department}</div>
                </div>
              </div>
            </td>
            <td class="summary-cell"><span class="summary-badge status-present">${workingDays}</span></td>
            <td class="summary-cell"><span class="summary-badge status-present">${presentCount}</span></td>
            <td class="summary-cell"><span class="summary-badge status-absent">${absentCount}</span></td>
            <td class="summary-cell"><span class="summary-badge status-late">${lateCount}</span></td>
            <td class="summary-cell"><span class="summary-badge status-halfday">0</span></td>
            ${employee.attendance.map((status, index) => {
              let statusClass = 'status-present';
              if (status === 'A') statusClass = 'status-absent';
              else if (status === 'L') statusClass = 'status-late';
              else if (status === 'HD') statusClass = 'status-halfday';
              else if (status === 'H') statusClass = 'status-holiday';
              
              return `<td class="day-cell"><span class="status-badge ${statusClass}">${status}</span></td>`;
            }).join('')}
          `;
          
          tableBody.appendChild(row);
        });
        
        updatePagination();
      }
      
      function updatePagination() {
        const totalPages = Math.ceil(filteredData.length / rowsPerPage);
        const pageNumbers = document.getElementById('pageNumbers');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        
        // Update button states
        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages || totalPages === 0;
        
        // Generate page numbers
        pageNumbers.innerHTML = '';
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
          startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
          const pageBtn = document.createElement('button');
          pageBtn.className = `page-number ${i === currentPage ? 'active' : ''}`;
          pageBtn.textContent = i;
          pageBtn.addEventListener('click', () => {
            currentPage = i;
            renderTable();
          });
          pageNumbers.appendChild(pageBtn);
        }
      }
      
      // Filter functionality
      function applyFilters() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const department = document.getElementById('departmentFilter').value;
        const status = document.getElementById('statusFilter').value;
        const month = document.getElementById('monthFilter').value;
        const year = document.getElementById('yearFilter').value;
        
        filteredData = attendanceData.filter(employee => {
          // Search filter
          if (searchTerm && !employee.name.toLowerCase().includes(searchTerm) && 
              !employee.department.toLowerCase().includes(searchTerm)) {
            return false;
          }
          
          // Department filter
          if (department && employee.department.toLowerCase() !== department.toLowerCase()) {
            return false;
          }
          
          // Status filter (simplified for demo)
          if (status) {
            if (status === 'present' && employee.attendance.filter(a => a === 'P').length === 0) return false;
            if (status === 'absent' && employee.attendance.filter(a => a === 'A').length === 0) return false;
            if (status === 'late' && employee.attendance.filter(a => a === 'L').length === 0) return false;
            if (status === 'halfday' && employee.attendance.filter(a => a === 'HD').length === 0) return false;
          }
          
          return true;
        });
        
        currentPage = 1;
        renderTable();
      }
      
      // Event listeners for filters
      document.getElementById('searchInput').addEventListener('input', debounce(applyFilters, 300));
      document.getElementById('departmentFilter').addEventListener('change', applyFilters);
      document.getElementById('statusFilter').addEventListener('change', applyFilters);
      document.getElementById('monthFilter').addEventListener('change', applyFilters);
      document.getElementById('yearFilter').addEventListener('change', applyFilters);
      
      // Pagination buttons
      document.getElementById('prevBtn').addEventListener('click', () => {
        if (currentPage > 1) {
          currentPage--;
          renderTable();
        }
      });
      
      document.getElementById('nextBtn').addEventListener('click', () => {
        if (currentPage < Math.ceil(filteredData.length / rowsPerPage)) {
          currentPage++;
          renderTable();
        }
      });
      
      // Export functionality
      document.getElementById('exportBtn').addEventListener('click', function() {
        // Create a new workbook
        const wb = XLSX.utils.book_new();
        
        // Prepare data for export
        const exportData = filteredData.map(employee => {
          const row = {
            'Employee Name': employee.name,
            'Department': employee.department,
            'Total Working Days': employee.totalDays,
            'Days Present': employee.attendance.filter(a => a === 'P').length,
            'Days Absent': employee.attendance.filter(a => a === 'A').length,
            'Late Arrivals': employee.attendance.filter(a => a === 'L').length,
            'Holidays': employee.attendance.filter(a => a === 'H').length,
            'Attendance Rate': `${((employee.attendance.filter(a => a === 'P').length / employee.totalDays) * 100).toFixed(2)}%`
          };
          
          // Add day columns
          employee.attendance.forEach((status, index) => {
            row[`Day ${index + 1}`] = status;
          });
          
          return row;
        });
        
        // Create worksheet
        const ws = XLSX.utils.json_to_sheet(exportData);
        
        // Add worksheet to workbook
        XLSX.utils.book_append_sheet(wb, ws, "Attendance History");
        
        // Generate Excel file and trigger download
        XLSX.writeFile(wb, `attendance_history_${new Date().toISOString().split('T')[0]}.xlsx`);
        
        // Show success message
        showNotification('Attendance data exported successfully!', 'success');
      });
      
      // Utility function for debouncing
      function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
          const later = () => {
            clearTimeout(timeout);
            func(...args);
          };
          clearTimeout(timeout);
          timeout = setTimeout(later, wait);
        };
      }
      
      // Notification function
      function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg ${
          type === 'success' ? 'bg-green-500' : 
          type === 'error' ? 'bg-red-500' : 
          'bg-blue-500'
        } text-white font-medium`;
        notification.textContent = message;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
          notification.style.opacity = '0';
          notification.style.transition = 'opacity 0.3s ease';
          setTimeout(() => {
            document.body.removeChild(notification);
          }, 300);
        }, 3000);
      }
      
      // Initial render
      renderTable();
      
      // Set current month in filter
      const currentMonth = (new Date().getMonth() + 1).toString().padStart(2, '0');
      document.getElementById('monthFilter').value = currentMonth;
    });
  </script>
</body>
</html>