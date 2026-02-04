<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Leave Policy & Requirements</title>
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
        }

        /* Layout Container */
        .app-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
            display: none;
            backdrop-filter: blur(3px);
        }

        .sidebar-overlay.active {
            display: block;
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
            z-index: 100;
            box-shadow: var(--shadow-xl);
            transform: translateX(0);
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
            font-size: clamp(1rem, 3vw, 1.1rem);
            font-weight: 700;
            line-height: 1.2;
        }

        .logo-subtitle {
            font-size: clamp(0.75rem, 2vw, 0.8rem);
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
            margin-left: 260px;
            padding: 1.5rem;
            transition: var(--transition);
            width: calc(100% - 260px);
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: var(--card-bg);
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            font-size: clamp(1.5rem, 4vw, 1.75rem);
            font-weight: 700;
            color: var(--dark);
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: var(--gray);
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            margin-top: 0.25rem;
        }

        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
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
            display: none;
            background: none;
            border: none;
            color: var(--dark);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius);
            z-index: 101;
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: var(--shadow-md);
            flex-shrink: 0;
        }

        .stat-info h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .stat-info .value {
            font-size: clamp(1.5rem, 4vw, 1.75rem);
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
        }

        /* Color Variations */
        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        /* Policy Header */
        .policy-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: clamp(1.5rem, 4vw, 2rem);
            border-radius: var(--radius);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .policy-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .policy-header h1 {
            font-size: clamp(1.5rem, 5vw, 2rem);
            font-weight: 800;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .policy-header p {
            font-size: clamp(0.9rem, 2.5vw, 1.1rem);
            opacity: 0.9;
            max-width: 800px;
            position: relative;
            z-index: 1;
        }

        /* Policy Navigation */
        .policy-nav {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-light);
            position: sticky;
            top: 20px;
            z-index: 10;
        }

        .policy-nav ul {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            list-style: none;
            justify-content: center;
        }

        .policy-nav a {
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            color: var(--gray);
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            font-size: clamp(0.8rem, 2vw, 1rem);
        }

        .policy-nav a:hover {
            background: var(--gray-light);
            color: var(--dark);
        }

        .policy-nav a.active {
            background: var(--primary);
            color: white;
        }

        /* Policy Content */
        .policy-content {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: clamp(1.5rem, 4vw, 2rem);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-light);
        }

        .policy-content h2 {
            font-size: clamp(1.25rem, 3vw, 1.5rem);
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--gray-light);
        }

        /* Leave Cards */
        .leave-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(350px, 100%), 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .leave-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            position: relative;
            overflow: hidden;
        }

        .leave-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .leave-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .leave-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .leave-title {
            font-size: clamp(1.1rem, 3vw, 1.25rem);
            font-weight: 700;
            color: var(--dark);
        }

        .leave-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .leave-details {
            margin-bottom: 1rem;
        }

        .leave-details p {
            color: var(--gray);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }

        .leave-requirements {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            border-left: 4px solid var(--primary);
        }

        .leave-requirements h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .leave-requirements ul {
            list-style: none;
            padding-left: 0;
        }

        .leave-requirements li {
            padding: 0.5rem 0;
            color: var(--gray);
            font-size: 0.9rem;
            position: relative;
            padding-left: 1.5rem;
        }

        .leave-requirements li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: bold;
        }

        .leave-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-light);
            font-size: 0.85rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .leave-duration {
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .leave-action {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .leave-action:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Important Notice */
        .important-notice {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 2px solid #f59e0b;
            border-radius: var(--radius);
            padding: 1.5rem;
            margin: 2rem 0;
            position: relative;
        }

        .important-notice h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .important-notice p {
            color: #92400e;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .important-notice::before {
            content: '⚠️';
            position: absolute;
            top: -15px;
            left: -15px;
            background: #f59e0b;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        /* Table for Requirements */
        .requirements-table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
            background: var(--card-bg);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .requirements-table thead {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
        }

        .requirements-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .requirements-table tbody tr {
            border-bottom: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .requirements-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .requirements-table td {
            padding: 1rem;
            color: var(--dark);
            font-size: 0.95rem;
            vertical-align: top;
        }

        .requirements-table .requirement-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .requirements-table .badge-required {
            background: #fee2e2;
            color: #991b1b;
        }

        .requirements-table .badge-optional {
            background: #d1fae5;
            color: #065f46;
        }

        .requirements-table .badge-depends {
            background: #fef3c7;
            color: #92400e;
        }

        /* Quick Links */
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 2rem;
        }

        /* Mobile Navigation Toggle */
        .mobile-nav-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: var(--shadow-lg);
            z-index: 90;
            cursor: pointer;
            transition: var(--transition);
        }

        .mobile-nav-toggle:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

       /* --- Footer --- */
        .footer {
            background: var(--footer-bg);
            color: white;
            padding: 3rem 0 1.5rem;
            margin-left: 260px;
            transition: var(--transition);
            width: calc(100% - 260px);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
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
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .copyright {
            color: #9ca3af;
            font-size: 0.85rem;
        }

        .copyright strong {
            color: white;
            font-weight: 600;
        }

        .footer-bottom-links {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
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

        /* Responsive Design */
        
        /* Large Desktop (1200px and up) */
        @media (min-width: 1200px) {
            .quick-stats {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        /* Desktop (1024px to 1199px) */
        @media (min-width: 1024px) and (max-width: 1199px) {
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .leave-cards-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Tablet (768px to 1023px) */
        @media (max-width: 1023px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .policy-nav {
                position: static;
                margin-top: 1rem;
            }
            
            .policy-nav ul {
                overflow-x: auto;
                padding-bottom: 0.5rem;
                justify-content: flex-start;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            
            .policy-nav ul::-webkit-scrollbar {
                display: none;
            }
            
            .mobile-nav-toggle {
                display: flex;
            }
        }

        /* Small Tablet (600px to 767px) */
        @media (max-width: 767px) {
            .quick-stats {
                grid-template-columns: 1fr;
            }
            
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .top-bar-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .leave-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .leave-footer {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            
            .leave-action {
                width: 100%;
                justify-content: center;
            }
            
            .important-notice::before {
                top: -10px;
                left: -10px;
                width: 25px;
                height: 25px;
                font-size: 0.8rem;
            }
            
            .requirements-table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .requirements-table th,
            .requirements-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.85rem;
            }
            
            .quick-links {
                flex-direction: column;
            }
            
            .quick-links .leave-action {
                width: 100%;
            }
        }

        /* Mobile (480px to 599px) */
        @media (max-width: 599px) {
            .main-content {
                padding: 0.75rem;
            }
            
            .stat-card {
                padding: 1rem;
                gap: 1rem;
            }
            
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }
            
            .stat-info .value {
                font-size: 1.5rem;
            }
            
            .policy-header {
                padding: 1rem;
            }
            
            .leave-card {
                padding: 1rem;
            }
            
            .leave-duration {
                font-size: 0.8rem;
            }
            
            .footer {
                padding: 2rem 0 1rem;
            }
        }

        /* Small Mobile (below 480px) */
        @media (max-width: 479px) {
            .policy-nav a {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }
            
            .leave-cards-container {
                grid-template-columns: 1fr;
            }
            
            .leave-badge {
                font-size: 0.75rem;
                padding: 0.2rem 0.5rem;
            }
            
            .leave-requirements {
                padding: 0.75rem;
            }
            
            .leave-requirements li {
                font-size: 0.85rem;
                padding-left: 1.25rem;
            }
            
            .logo-title {
                font-size: 1rem;
            }
            
            .logo-subtitle {
                font-size: 0.75rem;
            }
            
            .user-details h4 {
                font-size: 0.85rem;
            }
            
            .user-details p {
                font-size: 0.75rem;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
            }
            
            .mobile-nav-toggle {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
                bottom: 15px;
                right: 15px;
            }
        }
        
        /* Print Styles */
        @media print {
            .sidebar,
            .top-bar-actions,
            .mobile-nav-toggle,
            .policy-nav,
            .quick-links,
            .footer {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 0;
            }
            
            .policy-content {
                box-shadow: none;
                border: none;
            }
            
            body {
                background: white;
                color: black;
            }
            
            .leave-card {
                break-inside: avoid;
                page-break-inside: avoid;
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
        
        .leave-card {
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }
        
        .leave-card:nth-child(1) { animation-delay: 0.1s; }
        .leave-card:nth-child(2) { animation-delay: 0.2s; }
        .leave-card:nth-child(3) { animation-delay: 0.3s; }
        .leave-card:nth-child(4) { animation-delay: 0.4s; }
        .leave-card:nth-child(5) { animation-delay: 0.5s; }
        .leave-card:nth-child(6) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Overlay for mobile sidebar -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Mobile Navigation Toggle -->
        <div class="mobile-nav-toggle" id="mobileNavToggle">
            <i class="fas fa-bars"></i>
        </div>
        
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
                    <a href="leave.php" class="nav-link active">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Leave Policy</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="paysliphistory.php" class="nav-link">
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
                    <h1>Leave Policy & Requirements</h1>
                    <p>Complete guide to leave types, requirements, and procedures</p>
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
            
            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-umbrella-beach"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Leave Balance</h3>
                        <div class="value">18 Days</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Approved Leaves</h3>
                        <div class="value">3 Requests</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Policy Types</h3>
                        <div class="value">15 Types</div>
                    </div>
                </div>
            </div>
            
            <!-- Policy Header -->
            <div class="policy-header">
                <h1>Leave Application Instructions</h1>
                <p>Application for any type of leave shall be made on the official form and accompanied by required documentary requirements. Please review the specific requirements for each leave type below.</p>
            </div>
            
            <!-- Policy Navigation -->
            <nav class="policy-nav">
                <ul>
                    <li><a href="#vacation" class="active"><i class="fas fa-sun"></i> Vacation</a></li>
                    <li><a href="#sick"><i class="fas fa-heartbeat"></i> Sick</a></li>
                    <li><a href="#maternity"><i class="fas fa-baby"></i> Maternity</a></li>
                    <li><a href="#paternity"><i class="fas fa-user-friends"></i> Paternity</a></li>
                    <li><a href="#special"><i class="fas fa-star"></i> Special</a></li>
                    <li><a href="#emergency"><i class="fas fa-exclamation-triangle"></i> Emergency</a></li>
                    <li><a href="#other"><i class="fas fa-ellipsis-h"></i> Other Types</a></li>
                </ul>
            </nav>
            
            <!-- Policy Content -->
            <div class="policy-content">
                <h2>Leave Types & Requirements</h2>
                
                <div class="leave-cards-container">
                    <!-- Vacation Leave Card -->
                    <div class="leave-card" id="vacation">
                        <div class="leave-header">
                            <h3 class="leave-title">Vacation Leave</h3>
                            <span class="leave-badge" style="background: #d1fae5; color: #065f46;">Mandatory</span>
                        </div>
                        <div class="leave-details">
                            <p>For personal matters, rest, and recreation. Must be filed in advance when possible.</p>
                            <div class="leave-requirements">
                                <h4><i class="fas fa-clipboard-list"></i> Requirements:</h4>
                                <ul>
                                    <li>File at least 5 days in advance</li>
                                    <li>For travel abroad: Include travel clearance request</li>
                                    <li>Complete clearance from work accountabilities</li>
                                </ul>
                            </div>
                        </div>
                        <div class="leave-footer">
                            <div class="leave-duration">
                                <i class="far fa-clock"></i> Up to 15 days/year
                            </div>
                            <a href="#" class="leave-action apply-leave-btn" data-type="Vacation Leave">
                                <i class="fas fa-file-export"></i> Apply Now
                            </a>
                        </div>
                    </div>
                    
                    <!-- Sick Leave Card -->
                    <div class="leave-card" id="sick">
                        <div class="leave-header">
                            <h3 class="leave-title">Sick Leave</h3>
                            <span class="leave-badge" style="background: #fee2e2; color: #991b1b;">Medical</span>
                        </div>
                        <div class="leave-details">
                            <p>For medical treatment and recovery from illness or injury.</p>
                            <div class="leave-requirements">
                                <h4><i class="fas fa-clipboard-list"></i> Requirements:</h4>
                                <ul>
                                    <li>File immediately upon return</li>
                                    <li>5+ days: Medical certificate required</li>
                                    <li>Foreign consultation: Certified medical certificate</li>
                                </ul>
                            </div>
                        </div>
                        <div class="leave-footer">
                            <div class="leave-duration">
                                <i class="far fa-clock"></i> Up to 15 days/year
                            </div>
                            <a href="#" class="leave-action apply-leave-btn" data-type="Sick Leave">
                                <i class="fas fa-file-export"></i> Apply Now
                            </a>
                        </div>
                    </div>
                    
                    <!-- Maternity Leave Card -->
                    <div class="leave-card" id="maternity">
                        <div class="leave-header">
                            <h3 class="leave-title">Maternity Leave</h3>
                            <span class="leave-badge" style="background: #fce7f3; color: #9d174d;">Gender-based</span>
                        </div>
                        <div class="leave-details">
                            <p>For female employees before, during, and after childbirth.</p>
                            <div class="leave-requirements">
                                <h4><i class="fas fa-clipboard-list"></i> Requirements:</h4>
                                <ul>
                                    <li>Proof of pregnancy (ultrasound/doctor's certificate)</li>
                                    <li>Notice of Allocation of Maternity Leave Credits</li>
                                    <li>Seconded employees: Full pay for all cases</li>
                                </ul>
                            </div>
                        </div>
                        <div class="leave-footer">
                            <div class="leave-duration">
                                <i class="far fa-clock"></i> 105 days
                            </div>
                            <a href="#" class="leave-action apply-leave-btn" data-type="Maternity Leave">
                                <i class="fas fa-file-export"></i> Apply Now
                            </a>
                        </div>
                    </div>
                    
                    <!-- Paternity Leave Card -->
                    <div class="leave-card" id="paternity">
                        <div class="leave-header">
                            <h3 class="leave-title">Paternity Leave</h3>
                            <span class="leave-badge" style="background: #dbeafe; color: #1e40af;">Gender-based</span>
                        </div>
                        <div class="leave-details">
                            <p>For married male employees upon the delivery of their spouse.</p>
                            <div class="leave-requirements">
                                <h4><i class="fas fa-clipboard-list"></i> Requirements:</h4>
                                <ul>
                                    <li>Proof of child's delivery</li>
                                    <li>Birth certificate or medical certificate</li>
                                    <li>Marriage certificate</li>
                                </ul>
                            </div>
                        </div>
                        <div class="leave-footer">
                            <div class="leave-duration">
                                <i class="far fa-clock"></i> 7 days
                            </div>
                            <a href="#" class="leave-action apply-leave-btn" data-type="Paternity Leave">
                                <i class="fas fa-file-export"></i> Apply Now
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Important Notice -->
                <div class="important-notice">
                    <h3><i class="fas fa-exclamation-circle"></i> Important Notice</h3>
                    <p>For leave of absence for thirty (30) calendar days or more and terminal leave, application shall be accompanied by a <strong>clearance from money, property, and work-related accountabilities</strong> (pursuant to CSC Memorandum Circular No. 2, s. 1985).</p>
                </div>
                
                <!-- Other Leave Types -->
                <h2 style="margin-top: 2rem;">Other Leave Types</h2>
                
                <div class="leave-cards-container">
                    <!-- Special Privilege Leave -->
                    <div class="leave-card" id="special">
                        <div class="leave-header">
                            <h3 class="leave-title">Special Privilege Leave</h3>
                            <span class="leave-badge" style="background: #fef3c7; color: #92400e;">Special</span>
                        </div>
                        <div class="leave-details">
                            <p>For personal milestones, celebrations, or emergencies.</p>
                            <div class="leave-requirements">
                                <h4><i class="fas fa-clipboard-list"></i> Requirements:</h4>
                                <ul>
                                    <li>File at least 1 week in advance</li>
                                    <li>Except in emergency cases</li>
                                    <li>Indicate if travel is involved</li>
                                </ul>
                            </div>
                        </div>
                        <div class="leave-footer">
                            <div class="leave-duration">
                                <i class="far fa-clock"></i> 3 days/year
                            </div>
                            <a href="#" class="leave-action apply-leave-btn" data-type="Special Privilege Leave">
                                <i class="fas fa-info-circle"></i> Apply Now
                            </a>
                        </div>
                    </div>
                    
                    <!-- Solo Parent Leave -->
                    <div class="leave-card">
                        <div class="leave-header">
                            <h3 class="leave-title">Solo Parent Leave</h3>
                            <span class="leave-badge" style="background: #dcfce7; color: #166534;">Special</span>
                        </div>
                        <div class="leave-details">
                            <p>For single parents to attend to family needs.</p>
                            <div class="leave-requirements">
                                <h4><i class="fas fa-clipboard-list"></i> Requirements:</h4>
                                <ul>
                                    <li>Updated Solo Parent Identification Card</li>
                                    <li>File 5 days in advance when possible</li>
                                </ul>
                            </div>
                        </div>
                        <div class="leave-footer">
                            <div class="leave-duration">
                                <i class="far fa-clock"></i> 7 days/year
                            </div>
                            <a href="#" class="leave-action apply-leave-btn" data-type="Solo Parent Leave">
                                <i class="fas fa-info-circle"></i> Apply Now
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Requirements Summary Table -->
                <h2 style="margin-top: 2rem;">Documentary Requirements Summary</h2>
                
                <table class="requirements-table">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Key Requirements</th>
                            <th>Filing Period</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Vacation</strong></td>
                            <td>Advance notice, clearance for travel abroad</td>
                            <td>5 days advance</td>
                            <td><span class="requirement-badge badge-required">Required</span></td>
                        </tr>
                        <tr>
                            <td><strong>Sick (5+ days)</strong></td>
                            <td>Medical certificate, file upon return</td>
                            <td>Immediate upon return</td>
                            <td><span class="requirement-badge badge-required">Required</span></td>
                        </tr>
                        <tr>
                            <td><strong>Maternity</strong></td>
                            <td>Proof of pregnancy, allocation notice</td>
                            <td>Before delivery</td>
                            <td><span class="requirement-badge badge-required">Required</span></td>
                        </tr>
                        <tr>
                            <td><strong>Study Leave</strong></td>
                            <td>Contract, course details</td>
                            <td>30 days advance</td>
                            <td><span class="requirement-badge badge-depends">Case-by-case</span></td>
                        </tr>
                        <tr>
                            <td><strong>Special Emergency</strong></td>
                            <td>Proof of calamity, place of residence</td>
                            <td>Within 30 days of calamity</td>
                            <td><span class="requirement-badge badge-optional">As needed</span></td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Quick Links -->
                <div class="quick-links">
                    <a href="#" class="leave-action" style="background: var(--secondary);" id="downloadFormBtn">
                        <i class="fas fa-download"></i> Download Leave Form
                    </a>
                    <a href="#" class="leave-action" style="background: var(--success);" id="faqBtn">
                        <i class="fas fa-question-circle"></i> FAQs
                    </a>
                    <a href="#" class="leave-action" style="background: var(--gray);" id="contactHrBtn">
                        <i class="fas fa-phone-alt"></i> Contact HR
                    </a>
                </div>
            </div>
        </main>
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
                            <li><a href="leave.php">Leave Policy</a></li>
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
                <p>© 2024 <strong>Human Resource Management Office</strong>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileNavToggle = document.getElementById('mobileNavToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const applyLeaveBtns = document.querySelectorAll('.apply-leave-btn');
            const downloadFormBtn = document.getElementById('downloadFormBtn');
            const faqBtn = document.getElementById('faqBtn');
            const contactHrBtn = document.getElementById('contactHrBtn');
            const policyNavLinks = document.querySelectorAll('.policy-nav a');

            // Toggle sidebar from top menu button
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
                this.querySelector('i').classList.toggle('fa-bars');
                this.querySelector('i').classList.toggle('fa-times');
            });

            // Toggle sidebar from floating button
            mobileNavToggle.addEventListener('click', function() {
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
                mobileNavToggle.querySelector('i').classList.remove('fa-times');
                mobileNavToggle.querySelector('i').classList.add('fa-bars');
            });

            // Close sidebar when window is resized to desktop size
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1023 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                    mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                    mobileMenuBtn.querySelector('i').classList.add('fa-bars');
                    mobileNavToggle.querySelector('i').classList.remove('fa-times');
                    mobileNavToggle.querySelector('i').classList.add('fa-bars');
                }
                
                // Show/hide mobile nav toggle based on screen size
                if (window.innerWidth <= 1023) {
                    mobileNavToggle.style.display = 'flex';
                } else {
                    mobileNavToggle.style.display = 'none';
                }
            });

            // Initialize mobile nav toggle display
            if (window.innerWidth <= 1023) {
                mobileNavToggle.style.display = 'flex';
            }

            // Smooth scrolling for anchor links
            policyNavLinks.forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Update active state
                    policyNavLinks.forEach(a => a.classList.remove('active'));
                    this.classList.add('active');
                    
                    const targetId = this.getAttribute('href');
                    const targetElement = document.querySelector(targetId);
                    
                    if (targetElement) {
                        const offset = 100;
                        const elementPosition = targetElement.getBoundingClientRect().top;
                        const offsetPosition = elementPosition + window.pageYOffset - offset;
                        
                        window.scrollTo({
                            top: offsetPosition,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Interactive leave cards
            applyLeaveBtns.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const leaveType = this.getAttribute('data-type');
                    showApplicationModal(leaveType);
                });
            });

            // Download form button
            downloadFormBtn.addEventListener('click', function(e) {
                e.preventDefault();
                showNotification('Leave form download started!', 'success');
                // In real app, this would trigger download
                setTimeout(() => {
                    window.open('https://www.csc.gov.ph/images/downloadables/2018_CSC_Leave_Form_v.2.pdf', '_blank');
                }, 500);
            });

            // FAQ button
            faqBtn.addEventListener('click', function(e) {
                e.preventDefault();
                showNotification('Opening FAQs...', 'info');
                // In real app, this would open FAQ page
            });

            // Contact HR button
            contactHrBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'mailto:hr@occidentalmindoro.gov.ph?subject=Leave%20Policy%20Inquiry';
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Close modal with Escape key
                if (e.key === 'Escape') {
                    const modal = document.querySelector('.fixed.inset-0.bg-black');
                    if (modal) modal.remove();
                }
                
                // Close sidebar with Escape key on mobile
                if (e.key === 'Escape' && window.innerWidth <= 1023 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                    mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                    mobileMenuBtn.querySelector('i').classList.add('fa-bars');
                    mobileNavToggle.querySelector('i').classList.remove('fa-times');
                    mobileNavToggle.querySelector('i').classList.add('fa-bars');
                }
                
                // Quick navigation with number keys (1-7)
                if (e.key >= '1' && e.key <= '7') {
                    const index = parseInt(e.key) - 1;
                    if (policyNavLinks[index]) {
                        policyNavLinks[index].click();
                    }
                }
            });

            // Update active nav link based on scroll position
            window.addEventListener('scroll', function() {
                const sections = document.querySelectorAll('.leave-card');
                const navLinks = document.querySelectorAll('.policy-nav a');
                
                let current = '';
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    if (window.scrollY >= (sectionTop - 150)) {
                        current = '#' + section.getAttribute('id');
                    }
                });
                
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === current) {
                        link.classList.add('active');
                    }
                });
            });

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
            
            document.querySelectorAll('.leave-card').forEach(card => {
                card.style.animationPlayState = 'paused';
                observer.observe(card);
            });
        });

        function showApplicationModal(leaveType) {
            // Check if modal already exists
            if (document.querySelector('.leave-application-modal')) {
                return;
            }
            
            const modalHtml = `
                <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 leave-application-modal">
                    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                        <div class="p-4 md:p-6">
                            <div class="flex justify-between items-center mb-4 md:mb-6">
                                <h3 class="text-lg md:text-xl font-bold text-gray-900">Apply for ${leaveType}</h3>
                                <button class="text-gray-400 hover:text-gray-600 close-modal">
                                    <i class="fas fa-times text-lg md:text-xl"></i>
                                </button>
                            </div>
                            
                            <div class="space-y-3 md:space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1 md:mb-2">Leave Duration</label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
                                        <div>
                                            <input type="date" class="w-full p-2 border border-gray-300 rounded-lg text-sm md:text-base">
                                            <span class="text-xs text-gray-500">Start Date</span>
                                        </div>
                                        <div>
                                            <input type="date" class="w-full p-2 border border-gray-300 rounded-lg text-sm md:text-base">
                                            <span class="text-xs text-gray-500">End Date</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1 md:mb-2">Reason</label>
                                    <textarea class="w-full p-2 border border-gray-300 rounded-lg text-sm md:text-base" rows="3" placeholder="Specify your reason for leave..."></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1 md:mb-2">Upload Documents</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 md:p-4 text-center cursor-pointer hover:bg-gray-50" id="fileUploadArea">
                                        <i class="fas fa-cloud-upload-alt text-2xl md:text-3xl text-gray-400 mb-2"></i>
                                        <p class="text-xs md:text-sm text-gray-500">Click to select files or drag & drop here</p>
                                        <input type="file" class="hidden" id="fileUploadInput" multiple>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">Max file size: 10MB. Allowed: PDF, JPG, PNG</div>
                                </div>
                                
                                <div class="bg-yellow-50 p-3 md:p-4 rounded-lg">
                                    <h4 class="font-semibold text-yellow-800 mb-2 text-sm md:text-base">
                                        <i class="fas fa-info-circle mr-2"></i> Requirements for ${leaveType}
                                    </h4>
                                    <ul class="text-xs md:text-sm text-yellow-700 space-y-1">
                                        ${getLeaveRequirements(leaveType)}
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="mt-4 md:mt-6 flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                                <button class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm md:text-base close-modal">
                                    Cancel
                                </button>
                                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm md:text-base submit-application">
                                    <i class="fas fa-paper-plane mr-2"></i> Submit Application
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Add event listeners to modal elements
            const modal = document.querySelector('.leave-application-modal');
            const closeButtons = modal.querySelectorAll('.close-modal');
            const submitButton = modal.querySelector('.submit-application');
            const fileUploadArea = modal.querySelector('#fileUploadArea');
            const fileUploadInput = modal.querySelector('#fileUploadInput');
            
            closeButtons.forEach(button => {
                button.addEventListener('click', () => modal.remove());
            });
            
            submitButton.addEventListener('click', () => submitLeaveApplication(leaveType, modal));
            
            fileUploadArea.addEventListener('click', () => fileUploadInput.click());
            fileUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileUploadArea.classList.add('bg-blue-50', 'border-blue-300');
            });
            fileUploadArea.addEventListener('dragleave', () => {
                fileUploadArea.classList.remove('bg-blue-50', 'border-blue-300');
            });
            fileUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                fileUploadArea.classList.remove('bg-blue-50', 'border-blue-300');
                // Handle dropped files
                showNotification('Files added for upload', 'success');
            });
            
            fileUploadInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    showNotification(`${e.target.files.length} file(s) selected`, 'success');
                }
            });
            
            // Close modal when clicking outside
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        }
        
        function getLeaveRequirements(leaveType) {
            const requirements = {
                'Vacation Leave': ['File at least 5 days in advance', 'For travel abroad: Include travel clearance', 'Complete work accountability clearance'],
                'Sick Leave': ['File immediately upon return', 'For 5+ days: Medical certificate required', 'Foreign consultation: Certified medical certificate'],
                'Maternity Leave': ['Proof of pregnancy', 'Allocation of Maternity Leave Credits form', 'Estimated date of delivery certificate'],
                'Paternity Leave': ['Proof of child delivery', 'Birth certificate', 'Marriage certificate'],
                'Special Privilege Leave': ['File at least 1 week in advance', 'Except in emergency cases', 'Travel clearance if applicable'],
                'Solo Parent Leave': ['Solo Parent ID', 'File at least 5 days in advance', 'Proof of relationship to child']
            };
            
            const reqList = requirements[leaveType] || ['Review policy requirements above'];
            return reqList.map(req => `<li class="flex items-start"><span class="mr-2">•</span> <span>${req}</span></li>`).join('');
        }
        
        function submitLeaveApplication(leaveType, modal) {
            // Show loading state
            const submitBtn = modal.querySelector('.submit-application');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            submitBtn.disabled = true;
            
            // Simulate API call
            setTimeout(() => {
                // Remove modal
                modal.remove();
                
                // Show success notification
                showNotification(`Your ${leaveType} application has been submitted successfully! You'll receive an email confirmation shortly.`, 'success');
            }, 2000);
        }
        
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification-toast');
            existingNotifications.forEach(notification => notification.remove());
            
            const notification = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-600' : 
                           type === 'warning' ? 'bg-yellow-600' : 
                           type === 'danger' ? 'bg-red-600' : 'bg-blue-600';
            const icon = type === 'success' ? 'check-circle' : 
                        type === 'warning' ? 'exclamation-triangle' : 
                        type === 'danger' ? 'times-circle' : 'info-circle';
            
            notification.className = `notification-toast fixed top-4 right-4 ${bgColor} text-white px-4 py-3 rounded-lg shadow-lg z-50 transition-all duration-300 transform translate-x-full max-w-sm`;
            notification.innerHTML = `
                <div class="flex items-start">
                    <i class="fas fa-${icon} mr-3 mt-0.5 flex-shrink-0"></i>
                    <span class="text-sm md:text-base">${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Animate in
            requestAnimationFrame(() => {
                notification.classList.remove('translate-x-full');
                notification.classList.add('translate-x-0');
            });
            
            // Remove after delay
            setTimeout(() => {
                notification.classList.remove('translate-x-0');
                notification.classList.add('translate-x-full');
                
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 4000);
        }
    </script>
</body>
</html>