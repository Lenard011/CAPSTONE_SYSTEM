<?php
// homepage.php - SIMPLE WORKING VERSION
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set EXACTLY the same session configuration as login.php
$cookiePath = '/CAPSTONE_SYSTEM/userside/php/';

session_name('HRMS_SESSION');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => $cookiePath,
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Add debug output as HTML comment (view page source to see)
echo "<!-- =========== HOMEPAGE SESSION DEBUG =========== -->\n";
echo "<!-- Session ID: " . session_id() . " -->\n";
echo "<!-- Cookie Path: " . $cookiePath . " -->\n";
echo "<!-- Session Data: " . json_encode($_SESSION) . " -->\n";
echo "<!-- ============================================= -->\n";

// SIMPLE SESSION CHECK
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Redirect to login with error
    header('Location: login.php?error=session_missing');
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// User is logged in - get variables
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'] ?? '';
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$full_name = $_SESSION['full_name'] ?? ($first_name . ' ' . $last_name);
$role = $_SESSION['role'] ?? 'employee';
$access_level = $_SESSION['access_level'] ?? 1;
$employee_id = $_SESSION['employee_id'] ?? '';
$profile_image = $_SESSION['profile_image'] ?? '';

// Check for forced password change
if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] === true) {
    header('Location: change_password.php');
    exit();
}

// Log successful access
error_log("User " . $username . " accessed homepage successfully");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - About Paluan Municipality</title>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
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
            background: linear-gradient(90deg, #1e40af, #7c3aed);
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

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
            color: white;
            border-radius: var(--radius);
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 800px;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }

        .hero-stats {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .hero-stat {
            display: flex;
            flex-direction: column;
            min-width: 120px;
        }

        .hero-stat-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .hero-stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Content Navigation */
        .content-nav {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-light);
            position: sticky;
            top: 20px;
            z-index: 10;
            overflow-x: auto;
        }

        .content-nav ul {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.5rem;
            list-style: none;
            min-width: min-content;
        }

        .content-nav a {
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            color: var(--gray);
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            border: 2px solid transparent;
            flex-shrink: 0;
        }

        .content-nav a:hover {
            color: var(--primary);
            background: var(--gray-light);
        }

        .content-nav a.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Content Sections */
        .content-section {
            margin-bottom: 3rem;
            padding-top: 2rem;
            scroll-margin-top: 80px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-light);
            flex-wrap: wrap;
        }

        .section-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: var(--shadow-md);
            flex-shrink: 0;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--dark);
            flex: 1;
            min-width: 250px;
        }

        /* Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .feature-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            box-shadow: var(--shadow-md);
            flex-shrink: 0;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            flex: 1;
        }

        .card-content {
            color: var(--gray);
            line-height: 1.6;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
            margin: 2rem 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, var(--primary), var(--secondary));
        }

        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2.5rem;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            border: 3px solid white;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .timeline-date {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .timeline-content {
            background: var(--light);
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        /* Gallery */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .gallery-item {
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            cursor: pointer;
            transition: var(--transition);
            aspect-ratio: 4/3;
        }

        .gallery-item:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-lg);
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .gallery-item:hover img {
            transform: scale(1.1);
        }

        .gallery-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.5rem;
            font-size: 0.85rem;
            text-align: center;
        }

        /* Interactive Map */
        .map-container {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            margin: 2rem 0;
            border: 1px solid var(--gray-light);
        }

        .map-placeholder {
            width: 100%;
            height: 300px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .map-placeholder::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
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

            .content-nav ul {
                flex-wrap: wrap;
            }
        }

        /* Tablet Styles */
        @media (max-width: 1024px) and (min-width: 769px) {
            .hero-title {
                font-size: 2.25rem;
            }

            .hero-stats {
                gap: 1.5rem;
            }

            .hero-stat {
                min-width: 140px;
            }

            .cards-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .gallery-grid {
                grid-template-columns: repeat(3, 1fr);
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
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .hero-section {
                padding: 2rem 1rem;
                margin-bottom: 1.5rem;
            }

            .hero-title {
                font-size: 1.75rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .hero-stats {
                gap: 1rem;
                justify-content: space-between;
            }

            .hero-stat {
                min-width: calc(50% - 0.5rem);
                margin-bottom: 1rem;
            }

            .hero-stat-value {
                font-size: 1.75rem;
            }

            .content-nav {
                padding: 0.75rem;
                margin-bottom: 1.5rem;
                position: static;
                top: 0;
            }

            .content-nav ul {
                gap: 0.25rem;
            }

            .content-nav a {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }

            .content-nav a i {
                font-size: 0.9rem;
            }

            .section-header {
                gap: 0.75rem;
            }

            .section-title {
                font-size: 1.5rem;
                min-width: 100%;
            }

            .section-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
            }

            .cards-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 2rem;
            }

            .gallery-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .gallery-item {
                aspect-ratio: 1/1;
            }

            .timeline {
                padding-left: 1.5rem;
            }

            .timeline-item::before {
                left: -2rem;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
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
            .hero-stat {
                min-width: 100%;
            }

            .hero-stats {
                flex-direction: column;
                gap: 0.5rem;
            }

            .hero-stat-value {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .gallery-grid {
                grid-template-columns: 1fr;
            }

            .content-nav a {
                padding: 0.4rem 0.6rem;
                font-size: 0.8rem;
            }

            .content-nav a i {
                display: none;
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

        /* Print Styles */
        @media print {

            .sidebar,
            .top-bar-actions,
            .content-nav,
            .footer,
            .back-to-top,
            .mobile-menu-btn {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 0;
            }

            .hero-section {
                background: none !important;
                color: black !important;
                box-shadow: none !important;
            }

            .hero-section::before {
                display: none;
            }

            .feature-card,
            .stat-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                break-inside: avoid;
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
                    <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                        alt="Logo" class="logo-img">
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
                    <a href="about.php" class="nav-link active">
                        <i class="fas fa-info-circle"></i>
                        <span>About Municipality</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="logout.php" class="nav-link" onclick="return confirm('Are you sure you want to logout?');">
                        <i class="fas fa-power-off"></i>
                        <span>Logout</span>
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
                    <h1>About Municipality of Paluan</h1>
                    <p>Discover the rich heritage, geography, and culture of Paluan</p>
                </div>
                <div class="top-bar-actions">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">1</span>
                    </button>
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Hero Section -->
            <div class="hero-section">
                <h1 class="hero-title">Municipality of Paluan</h1>
                <p class="hero-subtitle">
                    A 3rd class municipality located at the northwestern tip of Mindoro Island,
                    officially known as <strong>Bayan ng Paluan</strong> in the province of Occidental Mindoro,
                    Philippines.
                </p>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="hero-stat-value">18,566</span>
                        <span class="hero-stat-label">2020 Population</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-value">3rd</span>
                        <span class="hero-stat-label">Municipality Class</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-value">564.3 km²</span>
                        <span class="hero-stat-label">Total Area</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-value">186 m</span>
                        <span class="hero-stat-label">Elevation</span>
                    </div>
                </div>
            </div>

            <!-- Content Navigation -->
            <nav class="content-nav">
                <ul>
                    <li><a href="#geography" class="active"><i class="fas fa-map-marked-alt"></i> Geography</a></li>
                    <li><a href="#history"><i class="fas fa-landmark"></i> History</a></li>
                    <li><a href="#economy"><i class="fas fa-chart-line"></i> Economy</a></li>
                    <li><a href="#biodiversity"><i class="fas fa-tree"></i> Biodiversity</a></li>
                    <li><a href="#culture"><i class="fas fa-users"></i> Culture</a></li>
                    <li><a href="#government"><i class="fas fa-university"></i> Government</a></li>
                </ul>
            </nav>


            <!-- Economy Section -->
            <section id="economy" class="content-section">
                <div class="section-header">
                    <div class="section-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h2 class="section-title">Economic Profile</h2>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">56%</div>
                        <div class="stat-label">Forestland Coverage</div>
                        <p class="text-sm text-gray-600 mt-2">Majority of land is covered by natural vegetation</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-value">24%</div>
                        <div class="stat-label">Agricultural Land</div>
                        <p class="text-sm text-gray-600 mt-2">13,842 hectares dedicated to farming</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-value">80%</div>
                        <div class="stat-label">Uncultivated Potential</div>
                        <p class="text-sm text-gray-600 mt-2">Available land for agricultural expansion</p>
                    </div>

                    <div class="stat-card">
                        <div class="stat-value">17%</div>
                        <div class="stat-label">Rice Production</div>
                        <p class="text-sm text-gray-600 mt-2">Percentage of agricultural land for rice</p>
                    </div>
                </div>

                <div class="feature-card">
                    <div class="card-header">
                        <div class="card-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <i class="fas fa-seedling"></i>
                        </div>
                        <h3 class="card-title">Agricultural Economy</h3>
                    </div>
                    <p class="card-content">
                        Paluan is predominantly rural with agriculture as the main economic driver. The municipality has
                        significant potential for agricultural expansion with 80% of agricultural land currently
                        uncultivated.
                        Rice production occupies 17% of agricultural land, while open grasslands for pasture cover 18%
                        of total land area.
                    </p>
                </div>
            </section>

            <!-- Biodiversity Section -->
            <section id="biodiversity" class="content-section">
                <div class="section-header">
                    <div class="section-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="fas fa-tree"></i>
                    </div>
                    <h2 class="section-title">Protected Areas & Biodiversity</h2>
                </div>

                <div class="cards-grid">
                    <div class="feature-card">
                        <div class="card-header">
                            <div class="card-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                                <i class="fas fa-mountain"></i>
                            </div>
                            <h3 class="card-title">Mt. Calavite Wildlife Sanctuary</h3>
                        </div>
                        <p class="card-content">
                            <strong>Area:</strong> 181.5 square kilometers (70.1 sq mi)<br>
                            <strong>Purpose:</strong> Preservation area for wildlife and watershed<br>
                            <strong>Key Species:</strong> Rare Mindoro tamaraw and critically endangered Mindoro
                            bleeding-heart (Gallicolumba platenae)<br>
                            Restricted access to protect endemic species and maintain ecological balance.
                        </p>
                    </div>

                    <div class="feature-card">
                        <div class="card-header">
                            <div class="card-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h3 class="card-title">NIPAS Protected Areas</h3>
                        </div>
                        <p class="card-content">
                            <strong>Protected Area:</strong> 18,016.19 hectares (44,519.0 acres)<br>
                            <strong>System:</strong> National Integrated Protected Areas System<br>
                            <strong>Importance:</strong> Underlines ecological significance of Paluan<br>
                            These areas are crucial for biodiversity conservation and environmental protection.
                        </p>
                    </div>
                </div>
            </section>

            <!-- History Section -->
            <section id="history" class="content-section">
                <div class="section-header">
                    <div class="section-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                        <i class="fas fa-landmark"></i>
                    </div>
                    <h2 class="section-title">Historical Timeline</h2>
                </div>

                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-date">Pre-colonial Era</div>
                        <div class="timeline-content">
                            Indigenous communities inhabited the area, living in harmony with the rich natural resources
                            of Paluan Bay and surrounding mountains.
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-date">Spanish Colonial Period</div>
                        <div class="timeline-content">
                            Paluan was established as a visita (mission station) under the jurisdiction of the Spanish
                            colonial government. The area was primarily used for missionary work and resource
                            extraction.
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-date">1901</div>
                        <div class="timeline-content">
                            Paluan was officially recognized as a municipality during the American colonial period, with
                            formal local government structures established.
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-date">1975</div>
                        <div class="timeline-content">
                            Mount Calavite was declared a wildlife sanctuary through Presidential Proclamation No. 1465,
                            recognizing its ecological importance.
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-date">Present Day</div>
                        <div class="timeline-content">
                            Paluan continues to balance development with environmental conservation, maintaining its
                            status as a 3rd class municipality while preserving its natural heritage.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Photo Gallery -->
            <section class="content-section">
                <div class="section-header">
                    <div class="section-icon" style="background: linear-gradient(135deg, #ec4899, #db2777);">
                        <i class="fas fa-images"></i>
                    </div>
                    <h2 class="section-title">Gallery of Paluan</h2>
                </div>

                <div class="gallery-grid">
                    <div class="gallery-item">
                        <img src="https://images.unsplash.com/photo-1544551763-46a013bb70d5?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                            alt="Mountain View">
                        <div class="gallery-caption">Mount Calavite</div>
                    </div>

                    <div class="gallery-item">
                        <img src="https://images.unsplash.com/photo-1506744038136-46273834b3fb?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                            alt="Coastal Area">
                        <div class="gallery-caption">Paluan Bay</div>
                    </div>

                    <div class="gallery-item">
                        <img src="https://images.unsplash.com/photo-1500382017468-9049fed747ef?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                            alt="Rice Fields">
                        <div class="gallery-caption">Agricultural Land</div>
                    </div>

                    <div class="gallery-item">
                        <img src="https://images.unsplash.com/photo-1441974231531-c6227db76b6e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                            alt="Forest">
                        <div class="gallery-caption">Protected Forest</div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" aria-label="Back to top">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="footer-logo">
                        <img src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                            alt="Logo" class="footer-logo-img">
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
                            <i class="fas fa-envelope mr-2"></i> info@paluan.gov.ph
                        </p>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>© 2024 <strong>Municipality of Paluan - Human Resource Management Office</strong>. All Rights
                    Reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const backToTop = document.getElementById('backToTop');
            const contentNavLinks = document.querySelectorAll('.content-nav a');
            const mapButton = document.getElementById('mapButton');
            const galleryItems = document.querySelectorAll('.gallery-item');

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
            window.addEventListener('scroll', function () {
                if (window.scrollY > 300) {
                    backToTop.classList.add('visible');
                } else {
                    backToTop.classList.remove('visible');
                }
            });

            backToTop.addEventListener('click', function () {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            // Smooth scrolling for content navigation with active state update
            contentNavLinks.forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();

                    const targetId = this.getAttribute('href');
                    const targetElement = document.querySelector(targetId);

                    if (targetElement) {
                        // Update active state
                        contentNavLinks.forEach(a => a.classList.remove('active'));
                        this.classList.add('active');

                        // Scroll to section
                        window.scrollTo({
                            top: targetElement.offsetTop - 100,
                            behavior: 'smooth'
                        });

                        // Close sidebar on mobile
                        if (window.innerWidth < 1025) {
                            toggleSidebar();
                        }
                    }
                });
            });

            // Update active nav based on scroll position
            const sections = document.querySelectorAll('.content-section');
            function updateActiveNav() {
                let current = '';
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    if (window.scrollY >= (sectionTop - 150)) {
                        current = section.getAttribute('id');
                    }
                });

                contentNavLinks.forEach(a => {
                    a.classList.remove('active');
                    if (a.getAttribute('href') === `#${current}`) {
                        a.classList.add('active');
                    }
                });
            }

            window.addEventListener('scroll', updateActiveNav);

            // Map button functionality
            if (mapButton) {
                mapButton.addEventListener('click', function () {
                    showNotification('Opening interactive map...', 'info');
                    setTimeout(() => {
                        window.open('https://www.google.com/maps/place/Paluan,+Occidental+Mindoro', '_blank');
                    }, 500);
                });
            }

            // Gallery image modal
            galleryItems.forEach(item => {
                item.addEventListener('click', function () {
                    const caption = this.querySelector('.gallery-caption').textContent;
                    const imgSrc = this.querySelector('img').src;
                    showImageModal(imgSrc, caption);
                });
            });

            // Window resize handler
            let resizeTimer;
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function () {
                    // Adjust sidebar behavior on resize
                    if (window.innerWidth >= 1025) {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                        mobileMenuBtn.querySelector('i').classList.add('fa-bars');
                    }

                    // Update content nav width for desktop
                    if (window.innerWidth >= 1025) {
                        const contentNav = document.querySelector('.content-nav');
                        const mainContentWidth = document.querySelector('.main-content').offsetWidth;
                        contentNav.style.width = `${mainContentWidth - 30}px`;
                    } else {
                        const contentNav = document.querySelector('.content-nav');
                        contentNav.style.width = 'auto';
                    }
                }, 250);
            });

            // Initial setup
            if (window.innerWidth >= 1025) {
                const contentNav = document.querySelector('.content-nav');
                const mainContentWidth = document.querySelector('.main-content').offsetWidth;
                contentNav.style.width = `${mainContentWidth - 30}px`;
            }
        });

        // Utility Functions
        function showImageModal(src, caption) {
            const modalHtml = `
                <div class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 p-4" id="imageModal">
                    <div class="relative max-w-4xl w-full max-h-[90vh]">
                        <button class="absolute top-4 right-4 text-white text-2xl z-10 hover:text-gray-300 bg-black/50 rounded-full w-10 h-10 flex items-center justify-center" onclick="closeImageModal()">
                            <i class="fas fa-times"></i>
                        </button>
                        <img src="${src}" alt="${caption}" class="w-full h-auto max-h-[70vh] object-contain rounded-lg">
                        <div class="text-center mt-4 text-white">
                            <h3 class="text-xl font-bold">${caption}</h3>
                            <p class="text-gray-300">Municipality of Paluan</p>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Add keyboard close
            document.addEventListener('keydown', function closeOnEscape(e) {
                if (e.key === 'Escape') {
                    closeImageModal();
                    document.removeEventListener('keydown', closeOnEscape);
                }
            });
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            if (modal) {
                modal.remove();
            }
        }

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
    </script>
</body>

</html>