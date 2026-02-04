<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Payslip History</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Modern Variables */
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #60a5fa;
            --secondary: #7c3aed;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --card-bg: #ffffff;
            --sidebar-bg: linear-gradient(180deg, #1e40af 0%, #1e3a8a 100%);
            --footer-bg: linear-gradient(180deg, #111827 0%, #1f2937 100%);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Layout Container */
        .app-container {
            display: flex;
            min-height: 100vh;
            position: relative;
            width: 100%;
        }

        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            display: none;
            backdrop-filter: blur(3px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* Sidebar Navigation */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 9999;
            box-shadow: var(--shadow-xl);
            left: -260px;
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: white;
        }

        .logo-img {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-title {
            font-size: 1.1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .logo-subtitle {
            font-size: 0.8rem;
            opacity: 0.9;
            font-weight: 500;
        }

        /* Navigation Menu */
        .nav-menu {
            padding: 1rem 0;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            font-weight: 500;
        }

        .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            padding-left: 2rem;
        }

        .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            border-left: 4px solid var(--primary-light);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        /* User Profile Section */
        .user-profile {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            position: absolute;
            bottom: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .user-details h4 {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .user-details p {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 1rem;
            transition: var(--transition);
            width: 100%;
        }

        @media (min-width: 1024px) {
            .sidebar {
                left: 0;
                position: fixed;
            }
            
            .main-content {
                margin-left: 260px;
                width: calc(100% - 260px);
                padding: 1.5rem;
            }
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            background: var(--card-bg);
            padding: 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            flex-wrap: wrap;
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .top-bar {
                padding: 1rem 1.5rem;
                margin-bottom: 2rem;
            }
        }

        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            color: var(--gray);
            font-size: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-btn:hover {
            color: var(--primary);
            background: var(--gray-light);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mobile-menu-btn {
            display: block;
            background: none;
            border: none;
            color: var(--dark);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius);
            z-index: 10000;
        }

        @media (min-width: 1024px) {
            .mobile-menu-btn {
                display: none;
            }
        }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 640px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .summary-cards {
                grid-template-columns: repeat(4, 1fr);
                gap: 1.5rem;
                margin-bottom: 2rem;
            }
        }

        .summary-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.25rem;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        @media (min-width: 1024px) {
            .summary-card {
                padding: 1.5rem;
                gap: 1.5rem;
            }
        }

        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            box-shadow: var(--shadow-md);
            flex-shrink: 0;
        }

        @media (min-width: 1024px) {
            .summary-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }

        .summary-info h3 {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .summary-info .value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
        }

        @media (min-width: 1024px) {
            .summary-info .value {
                font-size: 1.75rem;
            }
        }

        /* Color Variations */
        .summary-card:nth-child(1) .summary-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .summary-card:nth-child(2) .summary-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .summary-card:nth-child(3) .summary-icon {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .summary-card:nth-child(4) .summary-icon {
            background: linear-gradient(135deg, #06b6d4, #0ea5e9);
        }

        /* Filter Section */
        .filter-section {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.25rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-light);
        }

        @media (min-width: 1024px) {
            .filter-section {
                padding: 1.5rem;
                margin-bottom: 2rem;
            }
        }

        .filter-header {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 768px) {
            .filter-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
            }
        }

        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
        }

        .filter-controls {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            width: 100%;
        }

        @media (min-width: 640px) {
            .filter-controls {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .filter-controls {
                display: flex;
                flex-wrap: wrap;
                gap: 1rem;
                align-items: center;
                grid-template-columns: none;
            }
        }

        .filter-select {
            width: 100%;
        }

        @media (min-width: 1024px) {
            .filter-select {
                min-width: 180px;
                flex: 1;
            }
        }

        .filter-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
            color: var(--dark);
            transition: var(--transition);
            cursor: pointer;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-container {
            width: 100%;
        }

        @media (min-width: 1024px) {
            .search-container {
                position: relative;
                flex-grow: 1;
                min-width: 200px;
            }
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
            color: var(--dark);
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            pointer-events: none;
        }

        .btn {
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
            width: 100%;
        }

        @media (min-width: 1024px) {
            .btn {
                width: auto;
                padding: 0.75rem 1.5rem;
            }
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 1px solid var(--gray-light);
        }

        .btn-secondary:hover {
            background: var(--light);
            border-color: var(--gray);
        }

        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3);
        }

        /* Mobile Card View (Alternative to table) */
        .mobile-card-view {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 768px) {
            .mobile-card-view {
                display: none !important;
            }
        }

        .payslip-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.25rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-light);
            position: relative;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .card-title {
            font-weight: 600;
            font-size: 1rem;
        }

        .card-status {
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            white-space: nowrap;
        }

        .card-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 480px) {
            .card-body {
                grid-template-columns: 1fr;
            }
        }

        .card-item {
            display: flex;
            flex-direction: column;
        }

        .card-label {
            font-size: 0.75rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }

        .card-value {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .card-amount {
            font-family: 'Courier New', monospace;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-light);
        }

        .card-checkbox {
            position: absolute;
            top: 1rem;
            left: 1rem;
            width: 18px;
            height: 18px;
        }

        /* Table Section */
        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-light);
        }

        @media (min-width: 1024px) {
            .table-container {
                margin-bottom: 2rem;
            }
        }

        .table-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--gray-light);
            background: linear-gradient(90deg, #f8fafc, #f1f5f9);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .table-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
            }
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }

        .table-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
            width: 100%;
        }

        @media (min-width: 768px) {
            .table-actions {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
                width: auto;
                grid-template-columns: none;
            }
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            display: none;
        }

        @media (min-width: 768px) {
            .table-responsive {
                display: block;
            }
        }

        .payslip-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        .payslip-table thead {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
        }

        .payslip-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            white-space: nowrap;
        }

        .payslip-table tbody tr {
            border-bottom: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .payslip-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .payslip-table td {
            padding: 1rem;
            color: var(--dark);
            font-size: 0.95rem;
            vertical-align: middle;
            white-space: nowrap;
        }

        .checkbox-cell {
            width: 40px;
            text-align: center;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .payslip-checkbox {
            width: 18px;
            height: 18px;
            border: 2px solid var(--gray-light);
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .payslip-checkbox:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .employee-name {
            font-weight: 600;
            color: var(--dark);
        }

        .employee-id {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }

        .amount-cell {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            text-align: right;
            min-width: 120px;
        }

        .amount-positive {
            color: var(--success);
        }

        .amount-negative {
            color: var(--danger);
        }

        .amount-zero {
            color: var(--gray);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
        }

        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-processing {
            background: #e0f2fe;
            color: #0369a1;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-btn {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .action-btn:hover {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-dark);
        }

        .view-btn {
            color: var(--primary);
        }

        .download-btn {
            color: var(--success);
        }

        /* Table Footer */
        .table-footer {
            padding: 1.25rem;
            border-top: 1px solid var(--gray-light);
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .table-footer {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
            }
        }

        .summary-info {
            color: var(--gray);
            font-size: 0.9rem;
            text-align: center;
        }

        @media (min-width: 768px) {
            .summary-info {
                text-align: left;
            }
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            width: 100%;
        }

        @media (min-width: 768px) {
            .summary-stats {
                display: flex;
                gap: 1.5rem;
                width: auto;
            }
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        @media (min-width: 768px) {
            .stat-item {
                align-items: flex-start;
            }
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--gray);
        }

        .stat-value {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .stat-total {
            color: var(--primary);
        }

        .stat-selected {
            color: var(--success);
        }

        /* Pagination */
        .pagination {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.25rem;
            border-top: 1px solid var(--gray-light);
        }

        @media (min-width: 768px) {
            .pagination {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
            }
        }

        .pagination-info {
            color: var(--gray);
            font-size: 0.9rem;
            text-align: center;
        }

        @media (min-width: 768px) {
            .pagination-info {
                text-align: left;
            }
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .pagination-btn {
            min-width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--gray-light);
            border-radius: 6px;
            background: white;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .pagination-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .pagination-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn:disabled:hover {
            border-color: var(--gray-light);
            color: var(--gray);
        }

        /* Export Modal */
        .export-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            padding: 1rem;
        }

        .export-modal.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: var(--transition);
        }

        .export-modal.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--gray);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--danger);
            background: #fee2e2;
        }

        .export-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin: 1.5rem 0;
        }

        @media (min-width: 480px) {
            .export-options {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .export-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .export-option:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }

        .export-option.selected {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.1);
        }

        .export-option i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .export-option span {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark);
        }

        /* Footer */
        .footer {
            background: var(--footer-bg);
            color: white;
            padding: 2rem 0 1rem;
            width: 100%;
        }

        @media (min-width: 1024px) {
            .footer {
                margin-left: 260px;
                width: calc(100% - 260px);
                padding: 3rem 0 1.5rem;
            }
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        @media (min-width: 1024px) {
            .footer-content {
                padding: 0 1.5rem;
            }
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (min-width: 768px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .footer-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .footer-col {
            display: flex;
            flex-direction: column;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .footer-logo-img {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .footer-logo-text {
            display: flex;
            flex-direction: column;
        }

        .footer-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: white;
        }

        .footer-subtitle {
            font-size: 0.9rem;
            color: #9ca3af;
        }

        .footer-text {
            color: #9ca3af;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .footer-links h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            color: white;
            position: relative;
            padding-bottom: 0.75rem;
        }

        .footer-links h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--primary);
            border-radius: 2px;
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: #9ca3af;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .footer-links a:hover {
            color: white;
            padding-left: 0.5rem;
        }

        .footer-links a i {
            font-size: 0.8rem;
            color: var(--primary);
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 1rem;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            color: #9ca3af;
            font-size: 0.9rem;
        }

        .contact-item i {
            color: var(--primary);
            margin-top: 0.25rem;
            font-size: 1rem;
        }

        .social-links {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .social-link:hover {
            background: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            align-items: center;
        }

        @media (min-width: 768px) {
            .footer-bottom {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        .copyright {
            color: #9ca3af;
            font-size: 0.85rem;
            text-align: center;
        }

        @media (min-width: 768px) {
            .copyright {
                text-align: left;
            }
        }

        .copyright strong {
            color: white;
            font-weight: 600;
        }

        .footer-bottom-links {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        @media (min-width: 768px) {
            .footer-bottom-links {
                justify-content: flex-start;
            }
        }

        .footer-bottom-links a {
            color: #9ca3af;
            text-decoration: none;
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .footer-bottom-links a:hover {
            color: white;
        }

        /* Print Styles */
        @media print {
            .sidebar,
            .top-bar-actions,
            .mobile-menu-btn,
            .filter-section,
            .table-actions,
            .action-btn,
            .checkbox-cell,
            .pagination,
            .footer {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 0;
            }
            
            .table-container {
                box-shadow: none;
                border: 1px solid #000;
            }
            
            body {
                background: white;
                color: black;
            }
            
            .payslip-table {
                min-width: 100%;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-light);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }
        
        /* Animation for cards */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .payslip-card {
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }
        
        .payslip-card:nth-child(1) { animation-delay: 0.1s; }
        .payslip-card:nth-child(2) { animation-delay: 0.2s; }
        .payslip-card:nth-child(3) { animation-delay: 0.3s; }
        .payslip-card:nth-child(4) { animation-delay: 0.4s; }
        .payslip-card:nth-child(5) { animation-delay: 0.5s; }

        /* Notification Toast */
        .notification-toast {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            z-index: 10001;
            animation: slideInRight 0.3s ease-out;
            max-width: 350px;
        }

        .notification-toast.success {
            background: var(--success);
            color: white;
        }

        .notification-toast.warning {
            background: var(--warning);
            color: white;
        }

        .notification-toast.info {
            background: var(--info);
            color: white;
        }

        .notification-toast.danger {
            background: var(--danger);
            color: white;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Utility Classes */
        .d-none {
            display: none !important;
        }

        .d-block {
            display: block !important;
        }

        @media (min-width: 768px) {
            .d-md-none {
                display: none !important;
            }
            
            .d-md-block {
                display: block !important;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Overlay for mobile sidebar -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="homepage.php" class="logo-container">
                    <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" class="logo-img">
                    <div class="logo-text">
                        <div class="logo-title">HR Management Office</div>
                        <div class="logo-subtitle">Occidental Mindoro</div>
                    </div>
                </a>
            </div>
            
            <nav class="nav-menu">
                <div class="nav-item">
                    <a href="homepage.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="attendance.php" class="nav-link">
                        <i class="fas fa-history"></i>
                        <span>Attendance History</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="paysliphistory.php" class="nav-link active">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Payslip History</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="about.php" class="nav-link">
                        <i class="fas fa-info-circle"></i>
                        <span>About</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
            </nav>
            
            <div class="user-profile">
                <div class="user-info">
                    <div class="user-avatar">JA</div>
                    <div class="user-details">
                        <h4>Joy Ambrosio</h4>
                        <p>Employee ID: BSC02</p>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-header">
                    <h1>Payslip History</h1>
                    <p>View and manage your salary history and payslips</p>
                </div>
                <div class="top-bar-actions">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">2</span>
                    </button>
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="summary-info">
                        <h3>Total Earnings</h3>
                        <div class="value">₱24,500.00</div>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="summary-info">
                        <h3>Payslips</h3>
                        <div class="value">12</div>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="summary-info">
                        <h3>This Month</h3>
                        <div class="value">₱6,250.00</div>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="summary-info">
                        <h3>Pending</h3>
                        <div class="value">1</div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header">
                    <h2 class="filter-title">Filter Payslips</h2>
                    <div class="filter-controls">
                        <div class="filter-select">
                            <select class="filter-input" id="yearFilter">
                                <option value="">All Years</option>
                                <option value="2024">2024</option>
                                <option value="2023">2023</option>
                                <option value="2022">2022</option>
                                <option value="2021">2021</option>
                            </select>
                        </div>
                        
                        <div class="filter-select">
                            <select class="filter-input" id="monthFilter">
                                <option value="">All Months</option>
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        
                        <div class="filter-select">
                            <select class="filter-input" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                            </select>
                        </div>
                        
                        <div class="search-container">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" placeholder="Search employee..." id="searchInput">
                        </div>
                        
                        <button class="btn btn-primary" id="applyFilter">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        
                        <button class="btn btn-secondary" id="resetFilter">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Payslip Table Container -->
            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">Payslip Records</h2>
                    <div class="table-actions">
                        <button class="btn btn-primary" id="exportBtn">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button class="btn btn-success" id="printBtn">
                            <i class="fas fa-print"></i> Print Selected
                        </button>
                        <button class="btn btn-secondary" id="selectAllBtn">
                            <i class="fas fa-check-double"></i> Select All
                        </button>
                    </div>
                </div>
                
                <!-- Desktop Table View -->
                <div class="table-responsive">
                    <table class="payslip-table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <div class="checkbox-wrapper">
                                        <input type="checkbox" class="payslip-checkbox" id="selectAll">
                                    </div>
                                </th>
                                <th>Employee Details</th>
                                <th>Period</th>
                                <th>Rate/Day</th>
                                <th>Days Worked</th>
                                <th>Total Wage</th>
                                <th>Overtime</th>
                                <th>Holiday Pay</th>
                                <th>Gross Amount</th>
                                <th>Net Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="payslipTableBody">
                            <!-- Table rows will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Mobile Card View -->
                <div class="mobile-card-view" id="mobileCardView">
                    <!-- Mobile cards will be populated by JavaScript -->
                </div>
                
                <!-- Table Footer -->
                <div class="table-footer">
                    <div class="summary-info">
                        Showing <strong id="showingCount">0</strong> of <strong id="totalCount">0</strong> payslips
                    </div>
                    <div class="summary-stats">
                        <div class="stat-item">
                            <span class="stat-label">Selected</span>
                            <span class="stat-value stat-selected" id="selectedCount">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Gross</span>
                            <span class="stat-value stat-total" id="totalGross">₱0.00</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Net</span>
                            <span class="stat-value stat-total" id="totalNet">₱0.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="pagination">
                    <div class="pagination-info">
                        Page <strong id="currentPage">1</strong> of <strong id="totalPages">1</strong>
                    </div>
                    <div class="pagination-controls" id="paginationControls">
                        <!-- Pagination buttons will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Export Modal -->
    <div class="export-modal" id="exportModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Export Payslips</h3>
                <button class="modal-close" id="closeModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p>Select format for export:</p>
            <div class="export-options">
                <div class="export-option" data-format="pdf">
                    <i class="fas fa-file-pdf"></i>
                    <span>PDF</span>
                </div>
                <div class="export-option" data-format="excel">
                    <i class="fas fa-file-excel"></i>
                    <span>Excel</span>
                </div>
                <div class="export-option" data-format="csv">
                    <i class="fas fa-file-csv"></i>
                    <span>CSV</span>
                </div>
                <div class="export-option" data-format="print">
                    <i class="fas fa-print"></i>
                    <span>Print</span>
                </div>
            </div>
            <div style="margin-top: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Include:</label>
                <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" checked> Employee Details
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" checked> Salary Breakdown
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="exportAll"> All Payslips
                    </label>
                </div>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1.5rem;">
                <button class="btn btn-secondary" id="cancelExport" style="flex: 1; min-width: 120px;">
                    Cancel
                </button>
                <button class="btn btn-primary" id="confirmExport" style="flex: 1; min-width: 120px;">
                    <i class="fas fa-download mr-2"></i> Export
                </button>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="footer-logo">
                        <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" class="footer-logo-img">
                        <div>
                            <div class="footer-title">HR Management Office</div>
                            <div>Occidental Mindoro</div>
                        </div>
                    </div>
                    <p class="footer-text">
                        Republic of the Philippines<br>
                        All content is in the public domain unless otherwise stated.
                    </p>
                </div>
                
                <div class="footer-col">
                    <div class="footer-links">
                        <h4>About GOVPH</h4>
                        <ul>
                            <li><a href="#">Government Structure</a></li>
                            <li><a href="#">Open Data Portal</a></li>
                            <li><a href="#">Official Gazette</a></li>
                            <li><a href="#">Government Services</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="footer-col">
                    <div class="footer-links">
                        <h4>Quick Links</h4>
                        <ul>
                            <li><a href="homepage.php">Dashboard</a></li>
                            <li><a href="attendance.php">Attendance</a></li>
                            <li><a href="leave.php">Leave Management</a></li>
                            <li><a href="paysliphistory.php">Payslips</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="footer-col">
                    <div class="footer-links">
                        <h4>Connect With Us</h4>
                        <p class="footer-text">
                            Occidental Mindoro Public Information Office
                        </p>
                        <div class="social-links">
                            <a href="#" class="social-link">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-youtube"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p class="copyright">© 2024 <strong>Human Resource Management Office</strong>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        // Sample data
        const payslipData = [
            {
                id: 1,
                employeeName: "Joy Ambrosio",
                employeeId: "BSC02",
                period: "January 2024",
                rate: "520.00",
                daysWorked: "22",
                totalWage: "11,440.00",
                overtime: "1,560.00",
                holidayPay: "1,040.00",
                grossAmount: "14,040.00",
                netAmount: "12,636.00",
                status: "paid",
                selected: false
            },
            {
                id: 2,
                employeeName: "Joy Ambrosio",
                employeeId: "BSC02",
                period: "February 2024",
                rate: "520.00",
                daysWorked: "20",
                totalWage: "10,400.00",
                overtime: "1,040.00",
                holidayPay: "520.00",
                grossAmount: "11,960.00",
                netAmount: "10,764.00",
                status: "paid",
                selected: false
            },
            {
                id: 3,
                employeeName: "Joy Ambrosio",
                employeeId: "BSC02",
                period: "March 2024",
                rate: "520.00",
                daysWorked: "23",
                totalWage: "11,960.00",
                overtime: "2,080.00",
                holidayPay: "0.00",
                grossAmount: "14,040.00",
                netAmount: "12,636.00",
                status: "paid",
                selected: false
            },
            {
                id: 4,
                employeeName: "Joy Ambrosio",
                employeeId: "BSC02",
                period: "April 2024",
                rate: "520.00",
                daysWorked: "21",
                totalWage: "10,920.00",
                overtime: "1,560.00",
                holidayPay: "520.00",
                grossAmount: "13,000.00",
                netAmount: "11,700.00",
                status: "pending",
                selected: false
            },
            {
                id: 5,
                employeeName: "Joy Ambrosio",
                employeeId: "BSC02",
                period: "May 2024",
                rate: "520.00",
                daysWorked: "22",
                totalWage: "11,440.00",
                overtime: "1,040.00",
                holidayPay: "1,040.00",
                grossAmount: "13,520.00",
                netAmount: "12,168.00",
                status: "processing",
                selected: false
            },
            {
                id: 6,
                employeeName: "Joy Ambrosio",
                employeeId: "BSC02",
                period: "June 2024",
                rate: "520.00",
                daysWorked: "21",
                totalWage: "10,920.00",
                overtime: "2,600.00",
                holidayPay: "0.00",
                grossAmount: "13,520.00",
                netAmount: "12,168.00",
                status: "paid",
                selected: false
            },
            {
                id: 7,
                employeeName: "Joy Ambrosio",
                employeeId: "BSC02",
                period: "July 2024",
                rate: "520.00",
                daysWorked: "23",
                totalWage: "11,960.00",
                overtime: "1,560.00",
                holidayPay: "1,040.00",
                grossAmount: "14,560.00",
                netAmount: "13,104.00",
                status: "paid",
                selected: false
            }
        ];

        // State variables
        let currentData = [...payslipData];
        let selectedCount = 0;
        let currentPage = 1;
        const itemsPerPage = 5;

        document.addEventListener('DOMContentLoaded', function() {
            // DOM elements
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const selectAllCheckbox = document.getElementById('selectAll');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const exportBtn = document.getElementById('exportBtn');
            const printBtn = document.getElementById('printBtn');
            const applyFilterBtn = document.getElementById('applyFilter');
            const resetFilterBtn = document.getElementById('resetFilter');
            const exportModal = document.getElementById('exportModal');
            const closeModalBtn = document.getElementById('closeModal');
            const cancelExportBtn = document.getElementById('cancelExport');
            const confirmExportBtn = document.getElementById('confirmExport');
            const exportOptions = document.querySelectorAll('.export-option');
            const searchInput = document.getElementById('searchInput');
            const yearFilter = document.getElementById('yearFilter');
            const monthFilter = document.getElementById('monthFilter');
            const statusFilter = document.getElementById('statusFilter');
            const exportAllCheckbox = document.getElementById('exportAll');
            const payslipTableBody = document.getElementById('payslipTableBody');
            const mobileCardView = document.getElementById('mobileCardView');
            const paginationControls = document.getElementById('paginationControls');
            const showingCountEl = document.getElementById('showingCount');
            const totalCountEl = document.getElementById('totalCount');
            const selectedCountEl = document.getElementById('selectedCount');
            const totalGrossEl = document.getElementById('totalGross');
            const totalNetEl = document.getElementById('totalNet');
            const currentPageEl = document.getElementById('currentPage');
            const totalPagesEl = document.getElementById('totalPages');

            // Initialize data
            renderTable();
            updateSummary();
            setupPagination();

            // Mobile sidebar toggle
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
                this.querySelector('i').classList.toggle('fa-bars');
                this.querySelector('i').classList.toggle('fa-times');
            });

            // Close sidebar when clicking on overlay
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
                mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                mobileMenuBtn.querySelector('i').classList.add('fa-bars');
            });

            // Close sidebar when window is resized to desktop size
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                    mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                    mobileMenuBtn.querySelector('i').classList.add('fa-bars');
                }
            });

            // Select all checkbox
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.payslip-checkbox:not(#selectAll)');
                const currentPageData = getCurrentPageData();
                
                checkboxes.forEach(cb => {
                    cb.checked = this.checked;
                    const id = cb.dataset.id;
                    const item = currentData.find(item => item.id == id);
                    if (item) {
                        item.selected = this.checked;
                    }
                });
                
                updateSelectedCount();
                updateSummary();
            });

            // Select all button
            selectAllBtn.addEventListener('click', function() {
                selectAllCheckbox.checked = !selectAllCheckbox.checked;
                selectAllCheckbox.dispatchEvent(new Event('change'));
            });

            // Export button
            exportBtn.addEventListener('click', function() {
                exportModal.classList.add('active');
                document.body.style.overflow = 'hidden';
            });

            // Print button
            printBtn.addEventListener('click', function() {
                const selectedItems = currentData.filter(item => item.selected);
                if (selectedItems.length === 0) {
                    showNotification('Please select payslips to print', 'warning');
                    return;
                }
                printSelectedPayslips(selectedItems);
            });

            // Filter buttons
            applyFilterBtn.addEventListener('click', applyFilters);
            resetFilterBtn.addEventListener('click', resetFilters);

            // Search input
            searchInput.addEventListener('input', debounce(applyFilters, 300));

            // Modal controls
            closeModalBtn.addEventListener('click', closeModal);
            cancelExportBtn.addEventListener('click', closeModal);
            confirmExportBtn.addEventListener('click', confirmExport);

            // Export options
            exportOptions.forEach(option => {
                option.addEventListener('click', function() {
                    exportOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });

            // Export all checkbox
            exportAllCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    const checkboxes = document.querySelectorAll('.payslip-checkbox:not(#selectAll)');
                    checkboxes.forEach(cb => cb.checked = true);
                    currentData.forEach(item => item.selected = true);
                    updateSelectedCount();
                    updateSummary();
                }
            });

            // Functions
            function getCurrentPageData() {
                const startIndex = (currentPage - 1) * itemsPerPage;
                const endIndex = startIndex + itemsPerPage;
                return currentData.slice(startIndex, endIndex);
            }

            function renderTable() {
                // Clear existing content
                payslipTableBody.innerHTML = '';
                mobileCardView.innerHTML = '';
                
                const pageData = getCurrentPageData();
                
                // Update counts
                showingCountEl.textContent = pageData.length;
                totalCountEl.textContent = currentData.length;
                currentPageEl.textContent = currentPage;
                totalPagesEl.textContent = Math.ceil(currentData.length / itemsPerPage);
                
                // Render desktop table
                pageData.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="checkbox-cell">
                            <div class="checkbox-wrapper">
                                <input type="checkbox" class="payslip-checkbox" data-id="${item.id}" ${item.selected ? 'checked' : ''}>
                            </div>
                        </td>
                        <td>
                            <div class="employee-name">${item.employeeName}</div>
                            <div class="employee-id">ID: ${item.employeeId}</div>
                        </td>
                        <td>${item.period}</td>
                        <td class="amount-cell">₱${item.rate}</td>
                        <td>${item.daysWorked}</td>
                        <td class="amount-cell">₱${item.totalWage}</td>
                        <td class="amount-cell amount-positive">₱${item.overtime}</td>
                        <td class="amount-cell amount-positive">₱${item.holidayPay}</td>
                        <td class="amount-cell">₱${item.grossAmount}</td>
                        <td class="amount-cell">₱${item.netAmount}</td>
                        <td>
                            <span class="status-badge status-${item.status}">
                                ${item.status.charAt(0).toUpperCase() + item.status.slice(1)}
                            </span>
                        </td>
                        <td>
                            <button class="action-btn view-btn" onclick="viewPayslip(${item.id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="action-btn download-btn" onclick="downloadPayslip(${item.id})">
                                <i class="fas fa-download"></i>
                            </button>
                        </td>
                    `;
                    payslipTableBody.appendChild(row);
                });
                
                // Render mobile cards
                pageData.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'payslip-card';
                    card.innerHTML = `
                        <input type="checkbox" class="payslip-checkbox card-checkbox" data-id="${item.id}" ${item.selected ? 'checked' : ''}>
                        <div class="card-header">
                            <div>
                                <div class="card-title">${item.period}</div>
                                <div style="font-size: 0.85rem; color: var(--gray); margin-top: 0.25rem;">${item.employeeName}</div>
                            </div>
                            <span class="card-status status-${item.status}">
                                ${item.status.charAt(0).toUpperCase() + item.status.slice(1)}
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="card-item">
                                <span class="card-label">Rate/Day</span>
                                <span class="card-value card-amount">₱${item.rate}</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">Days Worked</span>
                                <span class="card-value">${item.daysWorked}</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">Total Wage</span>
                                <span class="card-value card-amount">₱${item.totalWage}</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">Overtime</span>
                                <span class="card-value card-amount amount-positive">₱${item.overtime}</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">Holiday Pay</span>
                                <span class="card-value card-amount amount-positive">₱${item.holidayPay}</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">Gross Amount</span>
                                <span class="card-value card-amount">₱${item.grossAmount}</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">Net Amount</span>
                                <span class="card-value card-amount">₱${item.netAmount}</span>
                            </div>
                        </div>
                        <div class="card-actions">
                            <button class="action-btn view-btn" onclick="viewPayslip(${item.id})" style="flex: 1;">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="action-btn download-btn" onclick="downloadPayslip(${item.id})" style="flex: 1;">
                                <i class="fas fa-download"></i> Download
                            </button>
                        </div>
                    `;
                    mobileCardView.appendChild(card);
                });
                
                // Add event listeners to checkboxes
                const checkboxes = document.querySelectorAll('.payslip-checkbox:not(#selectAll)');
                checkboxes.forEach(cb => {
                    cb.addEventListener('change', function() {
                        const id = this.dataset.id;
                        const item = currentData.find(item => item.id == id);
                        if (item) {
                            item.selected = this.checked;
                            updateSelectedCount();
                            updateSummary();
                        }
                    });
                });
                
                // Update select all checkbox
                const pageDataSelected = pageData.filter(item => item.selected);
                selectAllCheckbox.checked = pageDataSelected.length > 0 && pageDataSelected.length === pageData.length;
                
                // Initialize animations
                const observerOptions = {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                };
                
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.animationPlayState = 'running';
                        }
                    });
                }, observerOptions);
                
                document.querySelectorAll('.payslip-card').forEach(card => {
                    card.style.animationPlayState = 'paused';
                    observer.observe(card);
                });
            }

            function updateSelectedCount() {
                selectedCount = currentData.filter(item => item.selected).length;
                selectedCountEl.textContent = selectedCount;
            }

            function updateSummary() {
                const selectedItems = currentData.filter(item => item.selected);
                let totalGross = 0;
                let totalNet = 0;
                
                selectedItems.forEach(item => {
                    totalGross += parseFloat(item.grossAmount.replace(/,/g, ''));
                    totalNet += parseFloat(item.netAmount.replace(/,/g, ''));
                });
                
                totalGrossEl.textContent = `₱${totalGross.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                totalNetEl.textContent = `₱${totalNet.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            }

            function applyFilters() {
                const searchTerm = searchInput.value.toLowerCase();
                const year = yearFilter.value;
                const month = monthFilter.value;
                const status = statusFilter.value;
                
                currentData = payslipData.filter(item => {
                    // Search filter
                    if (searchTerm && !item.employeeName.toLowerCase().includes(searchTerm) && 
                        !item.employeeId.toLowerCase().includes(searchTerm)) {
                        return false;
                    }
                    
                    // Year filter
                    if (year) {
                        const itemYear = item.period.split(' ')[1];
                        if (itemYear !== year) return false;
                    }
                    
                    // Month filter
                    if (month) {
                        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                           'July', 'August', 'September', 'October', 'November', 'December'];
                        const itemMonth = item.period.split(' ')[0];
                        if (monthNames[parseInt(month) - 1] !== itemMonth) return false;
                    }
                    
                    // Status filter
                    if (status && item.status !== status) {
                        return false;
                    }
                    
                    return true;
                });
                
                currentPage = 1;
                renderTable();
                updateSummary();
                setupPagination();
            }

            function resetFilters() {
                searchInput.value = '';
                yearFilter.value = '';
                monthFilter.value = '';
                statusFilter.value = '';
                applyFilters();
            }

            function setupPagination() {
                // Clear existing controls
                paginationControls.innerHTML = '';
                
                const totalPages = Math.ceil(currentData.length / itemsPerPage);
                
                // Previous button
                const prevBtn = document.createElement('button');
                prevBtn.className = 'pagination-btn';
                prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
                prevBtn.disabled = currentPage === 1;
                prevBtn.addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage--;
                        renderTable();
                        setupPagination();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
                paginationControls.appendChild(prevBtn);
                
                // Page number buttons
                const startPage = Math.max(1, currentPage - 2);
                const endPage = Math.min(totalPages, startPage + 4);
                
                for (let i = startPage; i <= endPage; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
                    pageBtn.textContent = i;
                    pageBtn.addEventListener('click', () => {
                        currentPage = i;
                        renderTable();
                        setupPagination();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                    paginationControls.appendChild(pageBtn);
                }
                
                // Next button
                const nextBtn = document.createElement('button');
                nextBtn.className = 'pagination-btn';
                nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
                nextBtn.disabled = currentPage === totalPages;
                nextBtn.addEventListener('click', () => {
                    if (currentPage < totalPages) {
                        currentPage++;
                        renderTable();
                        setupPagination();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
                paginationControls.appendChild(nextBtn);
                
                // Update page info
                currentPageEl.textContent = currentPage;
                totalPagesEl.textContent = totalPages;
            }

            function closeModal() {
                exportModal.classList.remove('active');
                document.body.style.overflow = 'auto';
                exportOptions.forEach(opt => opt.classList.remove('selected'));
                exportAllCheckbox.checked = false;
            }

            function confirmExport() {
                const selectedFormat = document.querySelector('.export-option.selected')?.dataset.format || 'pdf';
                const exportAll = exportAllCheckbox.checked;
                const itemsToExport = exportAll ? currentData : currentData.filter(item => item.selected);
                
                if (itemsToExport.length === 0) {
                    showNotification('Please select payslips to export', 'warning');
                    return;
                }
                
                showNotification(`Exporting ${itemsToExport.length} payslip(s) as ${selectedFormat.toUpperCase()}`, 'success');
                closeModal();
                
                // In a real application, this would trigger the export/download
                setTimeout(() => {
                    if (selectedFormat === 'print') {
                        window.print();
                    } else {
                        // Simulate download
                        const link = document.createElement('a');
                        link.href = '#';
                        link.download = `payslips_${new Date().toISOString().split('T')[0]}.${selectedFormat}`;
                        link.click();
                    }
                }, 1000);
            }

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

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Close modal with Escape key
                if (e.key === 'Escape') {
                    const modal = document.querySelector('.export-modal.active');
                    if (modal) closeModal();
                }
                
                // Close sidebar with Escape key on mobile
                if (e.key === 'Escape' && window.innerWidth < 1024 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                    mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                    mobileMenuBtn.querySelector('i').classList.add('fa-bars');
                }
            });
        });

        // Global functions accessible from onclick handlers
        function viewPayslip(id) {
            const item = payslipData.find(item => item.id == id);
            if (item) {
                showNotification(`Opening payslip for ${item.period}`, 'info');
                // In a real application, this would open a detailed view or PDF
                setTimeout(() => {
                    const modalHtml = `
                        <div class="export-modal active">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3 class="modal-title">Payslip Details - ${item.period}</h3>
                                    <button class="modal-close" onclick="this.closest('.export-modal').remove(); document.body.style.overflow='auto'">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div style="margin: 1.5rem 0;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                        <div>
                                            <strong>Employee:</strong><br>
                                            ${item.employeeName}<br>
                                            <small>ID: ${item.employeeId}</small>
                                        </div>
                                        <div>
                                            <strong>Pay Period:</strong><br>
                                            ${item.period}
                                        </div>
                                    </div>
                                    <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                                        <h4 style="margin-bottom: 0.5rem;">Earnings</h4>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                            <div>Basic Wage (${item.daysWorked} days):</div>
                                            <div class="amount-cell">₱${item.totalWage}</div>
                                            <div>Overtime:</div>
                                            <div class="amount-cell">₱${item.overtime}</div>
                                            <div>Holiday Pay:</div>
                                            <div class="amount-cell">₱${item.holidayPay}</div>
                                            <div><strong>Gross Amount:</strong></div>
                                            <div class="amount-cell"><strong>₱${item.grossAmount}</strong></div>
                                        </div>
                                    </div>
                                    <div style="background: #f8fafc; padding: 1rem; border-radius: 8px;">
                                        <h4 style="margin-bottom: 0.5rem;">Net Pay</h4>
                                        <div style="text-align: center; font-size: 1.5rem; font-weight: bold; color: var(--success);">
                                            ₱${item.netAmount}
                                        </div>
                                        <div style="text-align: center; margin-top: 0.5rem; color: var(--gray);">
                                            Status: <span class="status-badge status-${item.status}">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</span>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                                    <button class="btn btn-secondary" onclick="this.closest('.export-modal').remove(); document.body.style.overflow='auto'" style="flex: 1;">
                                        Close
                                    </button>
                                    <button class="btn btn-primary" onclick="downloadPayslip(${id})" style="flex: 1;">
                                        <i class="fas fa-download mr-2"></i> Download
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                    document.body.style.overflow = 'hidden';
                }, 500);
            }
        }

        function downloadPayslip(id) {
            const item = payslipData.find(item => item.id == id);
            if (item) {
                showNotification(`Downloading payslip for ${item.period}`, 'success');
                // In a real application, this would trigger a file download
                setTimeout(() => {
                    const link = document.createElement('a');
                    link.href = '#';
                    link.download = `payslip_${item.period.replace(' ', '_')}.pdf`;
                    link.click();
                }, 500);
            }
        }

        function printSelectedPayslips(items) {
            showNotification(`Printing ${items.length} payslip(s)`, 'info');
            // In a real application, this would open a print dialog
            setTimeout(() => {
                window.print();
            }, 1000);
        }

        function showNotification(message, type = 'info') {
            // Remove existing notification
            const existingNotification = document.querySelector('.notification-toast');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            const notification = document.createElement('div');
            notification.className = `notification-toast ${type}`;
            notification.innerHTML = `
                <div class="flex items-start">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : type === 'danger' ? 'times-circle' : 'info-circle'} mr-3 mt-0.5"></i>
                    <span class="text-sm md:text-base">${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Remove after delay
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 4000);
        }
    </script>
</body>
</html>