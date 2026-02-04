<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            --radius: 16px;
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
            line-height: 1.7;
            overflow-x: hidden;
        }

        /* Layout Container */
        .app-container {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
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
            z-index: 1000;
            box-shadow: var(--shadow-xl);
            transform: translateX(-100%);
        }

        .sidebar.active {
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
            padding: 1.5rem;
            transition: var(--transition);
            width: 100%;
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
            font-size: 1.75rem;
            font-weight: 800;
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
            gap: 1rem;
            flex-shrink: 0;
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
            transition: var(--transition);
        }

        .mobile-menu-btn:hover {
            background: var(--gray-light);
        }

        /* Settings Layout */
        .settings-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }

        /* Settings Sidebar */
        .settings-sidebar {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-light);
            padding: 1.5rem 0;
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .settings-nav {
            display: flex;
            flex-direction: column;
        }

        .settings-nav-item {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: var(--gray);
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .settings-nav-item:hover {
            background: var(--light);
            color: var(--primary);
        }

        .settings-nav-item.active {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            border-left: 3px solid var(--primary);
        }

        .settings-nav-item i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .settings-nav-item span {
            font-weight: 500;
        }

        /* Settings Content */
        .settings-content {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-light);
            padding: 2rem;
        }

        .settings-section {
            margin-bottom: 2.5rem;
            display: none;
        }

        .settings-section.active {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-light);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .section-title i {
            color: var(--primary);
        }

        .section-description {
            color: var(--gray);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-label span {
            color: var(--danger);
            margin-left: 0.25rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--dark);
            transition: var(--transition);
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-input:disabled {
            background: var(--light);
            cursor: not-allowed;
        }

        .form-hint {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-hint i {
            color: var(--warning);
        }

        /* Grid Layouts */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        /* Profile Picture */
        .profile-picture-container {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--gray-light);
            box-shadow: var(--shadow-md);
        }

        .profile-picture-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            border: 4px solid var(--gray-light);
            box-shadow: var(--shadow-md);
            flex-shrink: 0;
        }

        .profile-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* Checkbox and Toggle Styles */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .checkbox-input {
            width: 18px;
            height: 18px;
            border: 2px solid var(--gray-light);
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .checkbox-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .checkbox-label {
            font-weight: 500;
            color: var(--dark);
            cursor: pointer;
        }

        .toggle-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .toggle-group:hover {
            background: var(--gray-light);
        }

        .toggle-label {
            font-weight: 500;
            color: var(--dark);
        }

        .toggle-description {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }

        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: var(--transition);
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: var(--transition);
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--success);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }

        /* Select Styles */
        .select-wrapper {
            position: relative;
        }

        .select-wrapper i {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            pointer-events: none;
        }

        /* Button Styles */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
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

        .btn-danger {
            background: var(--danger);
            color: white;
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.2);
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(239, 68, 68, 0.3);
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

        /* Security Devices */
        .device-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .device-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
            border: 1px solid var(--gray-light);
        }

        .device-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .device-info {
            flex: 1;
            min-width: 0;
        }

        .device-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .device-details {
            font-size: 0.85rem;
            color: var(--gray);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .device-status {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            background: #d1fae5;
            color: #065f46;
            flex-shrink: 0;
        }

        .device-status.inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Audit Log */
        .audit-log {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .audit-item {
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .audit-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .audit-action {
            font-weight: 600;
            color: var(--dark);
        }

        .audit-time {
            font-size: 0.85rem;
            color: var(--gray);
        }

        .audit-details {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .audit-ip {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            display: inline-block;
            margin-top: 0.25rem;
        }

        /* Danger Zone */
        .danger-zone {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 2px solid #f87171;
            border-radius: var(--radius);
            padding: 2rem;
            margin-top: 3rem;
        }

        .danger-zone-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .danger-zone-header i {
            color: #991b1b;
            font-size: 1.5rem;
        }

        .danger-zone-header h3 {
            color: #991b1b;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .danger-zone p {
            color: #7f1d1d;
            margin-bottom: 1.5rem;
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            padding: 1rem;
        }

        .modal.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: var(--radius);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: var(--transition);
        }

        .modal.active .modal-content {
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

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            z-index: 100;
            opacity: 0;
            transform: translateY(100px);
            transition: var(--transition);
            box-shadow: var(--shadow-lg);
        }

        .back-to-top.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .back-to-top:hover {
            background: var(--primary-dark);
            transform: translateY(-5px);
        }

        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            backdrop-filter: blur(3px);
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Desktop Styles */
        @media (min-width: 1025px) {
            .app-container {
                flex-direction: row;
            }
            
            .sidebar {
                transform: translateX(0);
                position: fixed;
            }
            
            .main-content {
                margin-left: 260px;
                width: calc(100% - 260px);
            }
            
            .mobile-menu-btn {
                display: none;
            }
            
            .sidebar-overlay {
                display: none !important;
            }
        }

        /* Tablet Styles */
        @media (max-width: 1024px) and (min-width: 769px) {
            .settings-container {
                grid-template-columns: 1fr;
            }
            
            .settings-sidebar {
                position: static;
                margin-bottom: 2rem;
            }
            
            .profile-picture-container {
                flex-direction: row;
                align-items: center;
            }
            
            .device-item {
                flex-direction: row;
                align-items: center;
            }
            
            .section-header {
                flex-direction: row;
                align-items: center;
            }
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
                gap: 1rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .settings-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .settings-sidebar {
                position: static;
                margin-bottom: 1.5rem;
                padding: 1rem 0;
            }
            
            .settings-nav-item {
                padding: 0.875rem 1.25rem;
                font-size: 0.95rem;
            }
            
            .settings-content {
                padding: 1.5rem;
            }
            
            .profile-picture-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.5rem;
            }
            
            .profile-picture-placeholder {
                width: 100px;
                height: 100px;
                font-size: 2rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .device-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .device-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .section-title {
                font-size: 1.25rem;
            }
            
            .toggle-group {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .toggle-switch {
                align-self: flex-end;
            }
            
            .audit-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .danger-zone {
                padding: 1.5rem;
            }
            
            .back-to-top {
                bottom: 1rem;
                right: 1rem;
                width: 45px;
                height: 45px;
                font-size: 1.25rem;
            }
        }

        /* Small Mobile Styles */
        @media (max-width: 480px) {
            .settings-nav-item {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
            
            .settings-nav-item i {
                font-size: 1rem;
            }
            
            .btn {
                padding: 0.625rem 1.25rem;
                font-size: 0.9rem;
                width: 100%;
            }
            
            .btn-secondary, .btn-primary, .btn-danger {
                width: 100%;
            }
            
            .profile-actions {
                width: 100%;
            }
            
            .device-status {
                align-self: flex-start;
            }
            
            .modal-content {
                padding: 1.5rem;
            }
            
            .modal-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .modal-close {
                position: absolute;
                top: 1rem;
                right: 1rem;
            }
            
            .footer-logo {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            
            .social-links {
                justify-content: center;
            }
        }

        /* Large Desktop Styles */
        @media (min-width: 1400px) {
            .main-content {
                padding: 2rem;
            }
            
            .settings-container {
                max-width: 1200px;
                margin: 0 auto;
                width: 100%;
            }
        }

        /* Print Styles */
        @media print {
            .sidebar,
            .top-bar-actions,
            .mobile-menu-btn,
            .settings-sidebar,
            .btn,
            .toggle-switch,
            .back-to-top,
            .footer,
            .modal {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 0;
            }
            
            .settings-content {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            
            .toggle-group {
                background: none !important;
                border-bottom: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
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
                    <a href="paysliphistory.php" class="nav-link">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Payslip History</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="about.php" class="nav-link">
                        <i class="fas fa-info-circle"></i>
                        <span>About Municipality</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="settings.php" class="nav-link active">
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
                    <h1>Settings</h1>
                    <p>Manage your account preferences and security settings</p>
                </div>
                <div class="top-bar-actions">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
            
            <!-- Settings Container -->
            <div class="settings-container">
                <!-- Settings Sidebar -->
                <div class="settings-sidebar">
                    <nav class="settings-nav">
                        <a href="#profile" class="settings-nav-item active" data-section="profile">
                            <i class="fas fa-user"></i>
                            <span>Profile Settings</span>
                        </a>
                        <a href="#security" class="settings-nav-item" data-section="security">
                            <i class="fas fa-shield-alt"></i>
                            <span>Security</span>
                        </a>
                        <a href="#notifications" class="settings-nav-item" data-section="notifications">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                        <a href="#privacy" class="settings-nav-item" data-section="privacy">
                            <i class="fas fa-lock"></i>
                            <span>Privacy</span>
                        </a>
                        <a href="#preferences" class="settings-nav-item" data-section="preferences">
                            <i class="fas fa-sliders-h"></i>
                            <span>Preferences</span>
                        </a>
                        <a href="#audit" class="settings-nav-item" data-section="audit">
                            <i class="fas fa-history"></i>
                            <span>Audit Log</span>
                        </a>
                        <a href="#danger" class="settings-nav-item" data-section="danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Danger Zone</span>
                        </a>
                    </nav>
                </div>
                
                <!-- Settings Content -->
                <div class="settings-content">
                    <!-- Profile Settings -->
                    <section id="profile" class="settings-section active">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-user"></i>
                                Profile Settings
                            </h2>
                            <button class="btn btn-primary" id="saveProfile">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                        
                        <p class="section-description">
                            Update your personal information, contact details, and profile picture.
                        </p>
                        
                        <div class="profile-picture-container">
                            <div class="profile-picture-placeholder">
                                JA
                            </div>
                            <div class="profile-actions">
                                <button class="btn btn-secondary">
                                    <i class="fas fa-camera"></i> Change Photo
                                </button>
                                <button class="btn btn-secondary">
                                    <i class="fas fa-trash"></i> Remove Photo
                                </button>
                                <p class="form-hint">
                                    <i class="fas fa-info-circle"></i> JPG, PNG or GIF. Max size 2MB
                                </p>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">First Name <span>*</span></label>
                                <input type="text" class="form-input" value="Joy" placeholder="Enter first name">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Last Name <span>*</span></label>
                                <input type="text" class="form-input" value="Ambrosio" placeholder="Enter last name">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Employee ID</label>
                                <input type="text" class="form-input" value="BSC02" disabled>
                                <p class="form-hint">Employee ID cannot be changed</p>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email Address <span>*</span></label>
                                <input type="email" class="form-input" value="joy.ambrosio@paluan.gov.ph" placeholder="Enter email address">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-input" value="+63 912 345 6789" placeholder="Enter phone number">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <div class="select-wrapper">
                                    <select class="form-input">
                                        <option value="hr">HR Office</option>
                                        <option value="budget">Budget Office</option>
                                        <option value="accounting">Accounting</option>
                                        <option value="it">IT Department</option>
                                    </select>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-input" value="HR Assistant" placeholder="Enter position">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-input" value="1985-06-15">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Bio</label>
                            <textarea class="form-input" rows="4" placeholder="Tell us about yourself...">Dedicated HR professional with 8 years of experience in government service. Passionate about employee welfare and organizational development.</textarea>
                        </div>
                    </section>
                    
                    <!-- Security Settings -->
                    <section id="security" class="settings-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-shield-alt"></i>
                                Security Settings
                            </h2>
                            <button class="btn btn-primary" id="saveSecurity">
                                <i class="fas fa-save"></i> Update Security
                            </button>
                        </div>
                        
                        <p class="section-description">
                            Manage your password, two-factor authentication, and connected devices.
                        </p>
                        
                        <div class="form-group">
                            <label class="form-label">Current Password <span>*</span></label>
                            <input type="password" class="form-input" placeholder="Enter current password">
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">New Password <span>*</span></label>
                                <input type="password" class="form-input" placeholder="Enter new password">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Confirm Password <span>*</span></label>
                                <input type="password" class="form-input" placeholder="Confirm new password">
                            </div>
                        </div>
                        
                        <p class="form-hint">
                            <i class="fas fa-info-circle"></i> Password must be at least 8 characters with uppercase, lowercase, number, and special character.
                        </p>
                        
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Two-Factor Authentication</div>
                                <div class="toggle-description">Add an extra layer of security to your account</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Email Notifications for Login</div>
                                <div class="toggle-description">Get notified when someone logs into your account</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <h3 class="text-lg font-bold mt-6 mb-4">Connected Devices</h3>
                        <div class="device-list">
                            <div class="device-item">
                                <div class="device-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                                    <i class="fas fa-desktop"></i>
                                </div>
                                <div class="device-info">
                                    <div class="device-name">Office Desktop - Windows 11</div>
                                    <div class="device-details">Last active: Today, 09:45 AM • IP: 192.168.1.100</div>
                                </div>
                                <div class="device-status">Active</div>
                            </div>
                            
                            <div class="device-item">
                                <div class="device-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div class="device-info">
                                    <div class="device-name">iPhone 14 - iOS 17</div>
                                    <div class="device-details">Last active: Yesterday, 8:30 PM • IP: 192.168.1.101</div>
                                </div>
                                <div class="device-status">Active</div>
                            </div>
                            
                            <div class="device-item">
                                <div class="device-icon" style="background: linear-gradient(135deg, #6b7280, #4b5563);">
                                    <i class="fas fa-laptop"></i>
                                </div>
                                <div class="device-info">
                                    <div class="device-name">MacBook Pro - macOS</div>
                                    <div class="device-details">Last active: 3 days ago • IP: 192.168.1.102</div>
                                </div>
                                <div class="device-status inactive">Inactive</div>
                            </div>
                        </div>
                        
                        <button class="btn btn-secondary mt-4">
                            <i class="fas fa-sign-out-alt"></i> Log Out All Devices
                        </button>
                    </section>
                    
                    <!-- Notification Settings -->
                    <section id="notifications" class="settings-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-bell"></i>
                                Notification Settings
                            </h2>
                            <button class="btn btn-primary" id="saveNotifications">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </div>
                        
                        <p class="section-description">
                            Customize how and when you receive notifications from the HR system.
                        </p>
                        
                        <h3 class="text-lg font-bold mb-4">Email Notifications</h3>
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Leave Request Updates</div>
                                <div class="toggle-description">Get notified when your leave requests are approved or rejected</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Payslip Availability</div>
                                <div class="toggle-description">Receive email when new payslips are available</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Attendance Alerts</div>
                                <div class="toggle-description">Get notified about attendance discrepancies</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <h3 class="text-lg font-bold mt-6 mb-4">System Notifications</h3>
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Desktop Notifications</div>
                                <div class="toggle-description">Show notifications on your desktop</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Sound Alerts</div>
                                <div class="toggle-description">Play sound for important notifications</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Push Notifications</div>
                                <div class="toggle-description">Receive push notifications on mobile devices</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <h3 class="text-lg font-bold mt-6 mb-4">Notification Frequency</h3>
                        <div class="form-group">
                            <div class="select-wrapper">
                                <select class="form-input">
                                    <option value="immediate">Immediate - As soon as available</option>
                                    <option value="hourly">Hourly Digest</option>
                                    <option value="daily" selected>Daily Summary</option>
                                    <option value="weekly">Weekly Report</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Privacy Settings -->
                    <section id="privacy" class="settings-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-lock"></i>
                                Privacy Settings
                            </h2>
                            <button class="btn btn-primary" id="savePrivacy">
                                <i class="fas fa-save"></i> Update Privacy
                            </button>
                        </div>
                        
                        <p class="section-description">
                            Control your privacy settings and data sharing preferences.
                        </p>
                        
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Show Profile to Colleagues</div>
                                <div class="toggle-description">Allow other employees to view your profile information</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Share Leave Status</div>
                                <div class="toggle-description">Allow colleagues to see when you're on leave</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Data Collection</div>
                                <div class="toggle-description">Allow collection of usage data to improve services</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <h3 class="text-lg font-bold mt-6 mb-4">Data Export</h3>
                        <div class="form-group">
                            <label class="form-label">Export Your Data</label>
                            <p class="form-hint mb-4">Download a copy of all your personal data stored in the system</p>
                            <button class="btn btn-secondary">
                                <i class="fas fa-download"></i> Request Data Export
                            </button>
                        </div>
                        
                        <h3 class="text-lg font-bold mt-6 mb-4">Cookie Preferences</h3>
                        <div class="form-group">
                            <div class="select-wrapper">
                                <select class="form-input">
                                    <option value="essential">Essential Cookies Only</option>
                                    <option value="all" selected>All Cookies</option>
                                    <option value="none">No Cookies</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <p class="form-hint">Essential cookies are required for the system to function properly</p>
                        </div>
                    </section>
                    
                    <!-- Preferences -->
                    <section id="preferences" class="settings-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-sliders-h"></i>
                                System Preferences
                            </h2>
                            <button class="btn btn-primary" id="savePreferences">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </div>
                        
                        <p class="section-description">
                            Customize your HR system experience with these preferences.
                        </p>
                        
                        <h3 class="text-lg font-bold mb-4">Interface Settings</h3>
                        <div class="form-group">
                            <label class="form-label">Language</label>
                            <div class="select-wrapper">
                                <select class="form-input">
                                    <option value="en" selected>English</option>
                                    <option value="fil">Filipino</option>
                                    <option value="tl">Tagalog</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Timezone</label>
                            <div class="select-wrapper">
                                <select class="form-input">
                                    <option value="pht" selected>Philippine Time (GMT+8)</option>
                                    <option value="utc">UTC</option>
                                    <option value="est">Eastern Time</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Theme</label>
                            <div class="select-wrapper">
                                <select class="form-input">
                                    <option value="light" selected>Light</option>
                                    <option value="dark">Dark</option>
                                    <option value="auto">Auto (System)</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        
                        <h3 class="text-lg font-bold mt-6 mb-4">Dashboard Preferences</h3>
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Show Quick Stats</div>
                                <div class="toggle-description">Display statistics on the dashboard</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Show Recent Activity</div>
                                <div class="toggle-description">Display recent activity on the dashboard</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Compact View</div>
                                <div class="toggle-description">Use compact view for tables and lists</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <h3 class="text-lg font-bold mt-6 mb-4">Default Views</h3>
                        <div class="form-group">
                            <label class="form-label">Default Dashboard View</label>
                            <div class="select-wrapper">
                                <select class="form-input">
                                    <option value="overview" selected>Overview</option>
                                    <option value="analytics">Analytics</option>
                                    <option value="personal">Personal Dashboard</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Default Date Range</label>
                            <div class="select-wrapper">
                                <select class="form-input">
                                    <option value="today">Today</option>
                                    <option value="week" selected>This Week</option>
                                    <option value="month">This Month</option>
                                    <option value="quarter">This Quarter</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Audit Log -->
                    <section id="audit" class="settings-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-history"></i>
                                Account Activity Log
                            </h2>
                            <button class="btn btn-secondary">
                                <i class="fas fa-download"></i> Export Log
                            </button>
                        </div>
                        
                        <p class="section-description">
                            View recent activity and security events for your account.
                        </p>
                        
                        <div class="audit-log">
                            <div class="audit-item">
                                <div class="audit-header">
                                    <div class="audit-action">Successful Login</div>
                                    <div class="audit-time">Today, 09:45 AM</div>
                                </div>
                                <div class="audit-details">
                                    Logged in from <span class="audit-ip">192.168.1.100</span> using Chrome on Windows
                                </div>
                            </div>
                            
                            <div class="audit-item">
                                <div class="audit-header">
                                    <div class="audit-action">Profile Updated</div>
                                    <div class="audit-time">Yesterday, 3:30 PM</div>
                                </div>
                                <div class="audit-details">
                                    Updated phone number and department information
                                </div>
                            </div>
                            
                            <div class="audit-item">
                                <div class="audit-header">
                                    <div class="audit-action">Password Changed</div>
                                    <div class="audit-time">2 days ago, 10:15 AM</div>
                                </div>
                                <div class="audit-details">
                                    Password successfully updated
                                </div>
                            </div>
                            
                            <div class="audit-item">
                                <div class="audit-header">
                                    <div class="audit-action">Failed Login Attempt</div>
                                    <div class="audit-time">3 days ago, 11:30 PM</div>
                                </div>
                                <div class="audit-details">
                                    Failed login from <span class="audit-ip">103.145.23.45</span> (Singapore)
                                </div>
                            </div>
                            
                            <div class="audit-item">
                                <div class="audit-header">
                                    <div class="audit-action">Leave Request Submitted</div>
                                    <div class="audit-time">5 days ago, 2:00 PM</div>
                                </div>
                                <div class="audit-details">
                                    Submitted leave request for December 25-27, 2024
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Danger Zone -->
                    <section id="danger" class="settings-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-exclamation-triangle"></i>
                                Danger Zone
                            </h2>
                        </div>
                        
                        <p class="section-description">
                            Irreversible actions that will affect your account permanently.
                        </p>
                        
                        <div class="danger-zone">
                            <div class="danger-zone-header">
                                <i class="fas fa-user-slash"></i>
                                <h3>Deactivate Account</h3>
                            </div>
                            <p>
                                Temporarily deactivate your account. Your data will be preserved but you won't be able to access the system until reactivation.
                            </p>
                            <button class="btn btn-secondary" id="deactivateAccount">
                                <i class="fas fa-user-slash"></i> Deactivate Account
                            </button>
                        </div>
                        
                        <div class="danger-zone" style="background: linear-gradient(135deg, #fecaca, #fca5a5); margin-top: 2rem;">
                            <div class="danger-zone-header">
                                <i class="fas fa-trash-alt"></i>
                                <h3>Delete Account</h3>
                            </div>
                            <p>
                                <strong>Warning:</strong> This action cannot be undone. All your data will be permanently deleted from our servers.
                            </p>
                            <button class="btn btn-danger" id="deleteAccount">
                                <i class="fas fa-trash-alt"></i> Delete My Account
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" aria-label="Back to top">
        <i class="fas fa-chevron-up"></i>
    </button>
    
    <!-- Modals -->
    <div class="modal" id="confirmationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Confirm Action</h3>
                <button class="modal-close" id="closeModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p id="modalMessage">Are you sure you want to perform this action?</p>
            <div class="flex flex-col sm:flex-row gap-3 mt-6">
                <button class="btn btn-secondary flex-1" id="cancelAction">
                    Cancel
                </button>
                <button class="btn btn-primary flex-1" id="confirmAction">
                    Confirm
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
                            <div>Municipality of Paluan</div>
                        </div>
                    </div>
                    <p class="footer-text">
                        Republic of the Philippines<br>
                        Provincial Government of Occidental Mindoro<br>
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
                            <li><a href="homepage.php">Employee Dashboard</a></li>
                            <li><a href="attendance.php">Attendance History</a></li>
                            <li><a href="leave.php">Leave Management</a></li>
                            <li><a href="paysliphistory.php">Payslip History</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="footer-col">
                    <div class="footer-links">
                        <h4>Connect With Us</h4>
                        <p class="footer-text">
                            Paluan Public Information Office
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
                        <p class="footer-text mt-4">
                            <i class="fas fa-phone mr-2"></i> (043) 123-4567<br>
                            <i class="fas fa-envelope mr-2"></i> hr@paluan.gov.ph
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>© 2024 <strong>Municipality of Paluan - Human Resource Management Office</strong>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        // State management
        let currentSection = 'profile';
        let settingsChanged = {};
        
        // DOM elements
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const backToTop = document.getElementById('backToTop');
        const navLinks = document.querySelectorAll('.settings-nav-item');
        const sections = document.querySelectorAll('.settings-section');
        const saveButtons = document.querySelectorAll('[id^="save"]');
        const confirmationModal = document.getElementById('confirmationModal');
        const closeModalBtn = document.getElementById('closeModal');
        const cancelActionBtn = document.getElementById('cancelAction');
        const confirmActionBtn = document.getElementById('confirmAction');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const deactivateAccountBtn = document.getElementById('deactivateAccount');
        const deleteAccountBtn = document.getElementById('deleteAccount');
        const toggles = document.querySelectorAll('.toggle-switch input');
        const formInputs = document.querySelectorAll('.form-input, select, textarea');
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile sidebar toggle
            function toggleSidebar() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                const icon = mobileMenuBtn.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            }
            
            mobileMenuBtn.addEventListener('click', toggleSidebar);
            sidebarOverlay.addEventListener('click', toggleSidebar);
            
            // Close sidebar when clicking a link on mobile
            if (window.innerWidth < 1025) {
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.addEventListener('click', toggleSidebar);
                });
            }
            
            // Back to top button
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    backToTop.classList.add('visible');
                } else {
                    backToTop.classList.remove('visible');
                }
            });
            
            backToTop.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // Navigation between sections
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all links
                    navLinks.forEach(l => l.classList.remove('active'));
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Get target section
                    const targetSection = this.getAttribute('data-section');
                    
                    // Hide all sections
                    sections.forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    // Show target section
                    document.getElementById(targetSection).classList.add('active');
                    
                    // Update current section
                    currentSection = targetSection;
                    
                    // Smooth scroll to top of content
                    document.querySelector('.settings-content').scrollTop = 0;
                    
                    // Close sidebar on mobile
                    if (window.innerWidth < 1025) {
                        toggleSidebar();
                    }
                });
            });
            
            // Save button handlers
            saveButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const section = this.id.replace('save', '').toLowerCase();
                    saveSettings(section);
                });
            });
            
            // Track form changes
            formInputs.forEach(input => {
                input.addEventListener('change', function() {
                    settingsChanged[currentSection] = true;
                    highlightSaveButton(currentSection);
                });
                
                input.addEventListener('input', function() {
                    if (this.type === 'password') {
                        checkPasswordStrength(this);
                    }
                });
            });
            
            toggles.forEach(toggle => {
                toggle.addEventListener('change', function() {
                    settingsChanged[currentSection] = true;
                    highlightSaveButton(currentSection);
                });
            });
            
            // Danger zone buttons
            deactivateAccountBtn.addEventListener('click', function() {
                showConfirmationModal(
                    'Deactivate Account',
                    'Your account will be temporarily deactivated. You can reactivate it by contacting HR department. Are you sure?',
                    'deactivate'
                );
            });
            
            deleteAccountBtn.addEventListener('click', function() {
                showConfirmationModal(
                    'Delete Account',
                    'This action cannot be undone. All your data will be permanently deleted. Are you absolutely sure?',
                    'delete'
                );
            });
            
            // Modal handlers
            closeModalBtn.addEventListener('click', closeModal);
            cancelActionBtn.addEventListener('click', closeModal);
            confirmActionBtn.addEventListener('click', handleConfirmation);
            
            // Initialize any dynamic functionality
            initializeAuditLog();
            
            // Window resize handler
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    // Adjust sidebar behavior on resize
                    if (window.innerWidth >= 1025) {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                        mobileMenuBtn.querySelector('i').classList.add('fa-bars');
                    }
                }, 250);
            });
        });
        
        // Highlight save button when changes are made
        function highlightSaveButton(section) {
            const saveBtn = document.getElementById(`save${capitalizeFirstLetter(section)}`);
            if (saveBtn) {
                saveBtn.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
                saveBtn.innerHTML = '<i class="fas fa-exclamation-circle"></i> Save Changes';
            }
        }
        
        // Save settings for a specific section
        function saveSettings(section) {
            const saveBtn = document.getElementById(`save${capitalizeFirstLetter(section)}`);
            
            if (saveBtn) {
                // Show loading state
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                saveBtn.disabled = true;
                
                // Simulate API call
                setTimeout(() => {
                    // Reset button
                    saveBtn.innerHTML = '<i class="fas fa-check"></i> Saved';
                    saveBtn.style.background = '';
                    saveBtn.disabled = false;
                    
                    // Reset changed state
                    settingsChanged[section] = false;
                    
                    // Show notification
                    showNotification(`${capitalizeFirstLetter(section)} settings saved successfully!`, 'success');
                    
                    // Revert button text after delay
                    setTimeout(() => {
                        saveBtn.innerHTML = originalText.replace('Save Changes', 'Save Changes');
                    }, 2000);
                }, 1500);
            }
        }
        
        // Check password strength
        function checkPasswordStrength(input) {
            const password = input.value;
            if (password.length === 0) return;
            
            let strength = 0;
            const feedbackContainer = input.parentElement;
            
            // Check length
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Check for mixed case
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            
            // Check for numbers
            if (/\d/.test(password)) strength++;
            
            // Check for special characters
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Remove existing feedback
            const existingFeedback = feedbackContainer.querySelector('.password-strength-feedback');
            if (existingFeedback) {
                existingFeedback.remove();
            }
            
            // Update feedback
            let message = '';
            let color = '';
            let icon = '';
            
            switch(strength) {
                case 0:
                case 1:
                    message = 'Very Weak';
                    color = '#ef4444';
                    icon = 'times-circle';
                    break;
                case 2:
                    message = 'Weak';
                    color = '#f59e0b';
                    icon = 'exclamation-triangle';
                    break;
                case 3:
                    message = 'Good';
                    color = '#3b82f6';
                    icon = 'check-circle';
                    break;
                case 4:
                    message = 'Strong';
                    color = '#10b981';
                    icon = 'shield-alt';
                    break;
                case 5:
                    message = 'Very Strong';
                    color = '#059669';
                    icon = 'shield-check';
                    break;
            }
            
            const feedback = document.createElement('div');
            feedback.className = 'password-strength-feedback form-hint';
            feedback.innerHTML = `<i class="fas fa-${icon}" style="color: ${color}"></i> Password strength: <strong style="color: ${color}">${message}</strong>`;
            feedbackContainer.appendChild(feedback);
        }
        
        // Initialize audit log
        function initializeAuditLog() {
            // In a real application, this would fetch audit log data from an API
            const auditLog = document.querySelector('.audit-log');
            
            // Simulate loading more entries
            window.addEventListener('scroll', function() {
                if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 100) {
                    // Load more audit entries
                    loadMoreAuditEntries();
                }
            });
        }
        
        // Load more audit entries
        function loadMoreAuditEntries() {
            const auditLog = document.querySelector('.audit-log');
            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'audit-item';
            loadingIndicator.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading more entries...</div>';
            auditLog.appendChild(loadingIndicator);
            
            // Simulate API call
            setTimeout(() => {
                loadingIndicator.remove();
                
                // Add new entries
                const newEntries = [
                    {
                        action: 'Profile Picture Updated',
                        time: '1 week ago',
                        details: 'Changed profile picture'
                    },
                    {
                        action: 'Notification Settings Updated',
                        time: '1 week ago',
                        details: 'Updated email notification preferences'
                    }
                ];
                
                newEntries.forEach(entry => {
                    const auditItem = document.createElement('div');
                    auditItem.className = 'audit-item';
                    auditItem.innerHTML = `
                        <div class="audit-header">
                            <div class="audit-action">${entry.action}</div>
                            <div class="audit-time">${entry.time}</div>
                        </div>
                        <div class="audit-details">${entry.details}</div>
                    `;
                    auditLog.appendChild(auditItem);
                });
            }, 1000);
        }
        
        // Show confirmation modal
        function showConfirmationModal(title, message, action) {
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            confirmActionBtn.dataset.action = action;
            
            if (action === 'delete') {
                confirmActionBtn.className = 'btn btn-danger flex-1';
                confirmActionBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Account';
            } else {
                confirmActionBtn.className = 'btn btn-primary flex-1';
                confirmActionBtn.innerHTML = '<i class="fas fa-check"></i> Confirm';
            }
            
            confirmationModal.classList.add('active');
        }
        
        // Close modal
        function closeModal() {
            confirmationModal.classList.remove('active');
        }
        
        // Handle confirmation
        function handleConfirmation() {
            const action = confirmActionBtn.dataset.action;
            
            if (action === 'deactivate') {
                // Handle deactivation
                showNotification('Account deactivation request sent to HR department.', 'info');
                closeModal();
            } else if (action === 'delete') {
                // Handle deletion
                confirmActionBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                confirmActionBtn.disabled = true;
                
                setTimeout(() => {
                    showNotification('Account deletion scheduled. You will receive a confirmation email.', 'warning');
                    closeModal();
                    setTimeout(() => {
                        window.location.href = 'logout.php';
                    }, 2000);
                }, 2000);
            }
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-600' : 
                           type === 'warning' ? 'bg-yellow-600' : 
                           type === 'danger' ? 'bg-red-600' : 'bg-blue-600';
            
            notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300 transform translate-x-0`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : type === 'danger' ? 'times-circle' : 'info-circle'} mr-3"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-0');
                notification.classList.add('translate-x-full');
                
                // Remove after animation
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
        
        // Helper function to capitalize first letter
        function capitalizeFirstLetter(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }
    </script>
</body>
</html>