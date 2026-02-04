<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Management System - Municipality of Paluan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Modern Color Palette - Updated to match image */
        :root {
            --navy-blue: #0235a2ff;        /* Main header color from image */
            --button-blue: #2c6bc4;      /* Button blue from image */
            --button-hover: #1e4a8a;     /* Button hover state */
            --primary-blue: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #60a5fa;
            --accent-teal: #0d9488;
            --accent-orange: #f97316;
            --accent-green: #10b981;
            --light-bg: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.98);
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-light: #64748b;
            --border-light: #e2e8f0;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --shadow-blue: 0 10px 25px -3px rgba(44, 107, 196, 0.15);
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 50%, #f8fafc 100%);
            min-height: 100vh;
            color: var(--text-primary);
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-shape {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(44, 107, 196, 0.08) 0%, rgba(13, 148, 136, 0.04) 100%);
            filter: blur(60px);
        }

        .bg-shape:nth-child(1) {
            width: 600px;
            height: 600px;
            top: -200px;
            left: -200px;
            animation: float 25s infinite linear;
        }

        .bg-shape:nth-child(2) {
            width: 400px;
            height: 400px;
            bottom: -150px;
            right: -150px;
            animation: float 30s infinite linear reverse;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) rotate(0deg);
            }

            33% {
                transform: translate(40px, 60px) rotate(120deg);
            }

            66% {
                transform: translate(-30px, 100px) rotate(240deg);
            }

            100% {
                transform: translate(0, 0) rotate(360deg);
            }
        }

        /* Header Styles - Updated to match image */
        .header {
            background: var(--navy-blue); /* Solid navy blue from image */
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
            border-bottom: 4px solid rgba(255, 255, 255, 0.2);
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg,
                    var(--primary-light),
                    #ffffff,
                    var(--accent-teal),
                    var(--primary-light));
            background-size: 400% 100%;
            animation: shimmer 3s infinite linear;
        }

        @keyframes shimmer {
            0% {
                background-position: -400% center;
            }

            100% {
                background-position: 400% center;
            }
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .logo-container:hover {
            transform: translateY(-2px);
        }

        .logo-img {
            height: 80px;
            width: auto;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .logo-img:hover {
            transform: scale(1.05);
        }

        .header-title {
            display: flex;
            flex-direction: column;
        }

        .header-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.25rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .header-title .municipality {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.95);
        }

        .header-title .republic {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }

        /* About Us Button - Updated to match image */
        .about-btn a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .about-btn a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Main Container */
        .main-container {
            display: flex;
            min-height: calc(100vh - 120px);
            padding: 2rem;
            align-items: center;
            justify-content: center;
        }

        /* Login Card */
        .login-card {
            width: 100%;
            max-width: 1000px;
            min-height: 550px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            box-shadow: var(--shadow-xl), var(--shadow-blue);
            border: 1px solid rgba(255, 255, 255, 0.4);
            display: flex;
            overflow: hidden;
            position: relative;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .login-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.2),
                0 20px 40px -8px rgba(44, 107, 196, 0.3);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg,
                    var(--button-blue),
                    var(--accent-teal),
                    var(--button-blue));
            background-size: 300% 100%;
            animation: shimmer 2s infinite linear;
        }

        /* Form Section */
        .form-section {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .form-section::before {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 1px;
            height: 80%;
            background: linear-gradient(to bottom,
                    transparent,
                    var(--border-light),
                    transparent);
        }

        /* Form Header */
        .form-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .form-logo {
            width: 90px;
            height: 90px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--button-blue), var(--navy-blue));
            border-radius: 18px;
            padding: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(44, 107, 196, 0.25);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .form-logo:hover {
            transform: scale(1.08) rotate(3deg);
            box-shadow: 0 15px 35px rgba(44, 107, 196, 0.35);
        }

        .form-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .form-logo::after {
            content: '';
            position: absolute;
            top: -100%;
            left: -100%;
            width: 300%;
            height: 300%;
            background: linear-gradient(45deg,
                    transparent,
                    rgba(255, 255, 255, 0.2),
                    transparent);
            transform: rotate(45deg);
            transition: transform 0.8s ease;
        }

        .form-logo:hover::after {
            transform: rotate(45deg) translate(30%, 30%);
        }

        .form-title {
            font-size: 2.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--button-blue), var(--navy-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .form-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.75rem;
            position: relative;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 1.1rem 1.1rem 1.1rem 3.8rem;
            font-size: 1rem;
            border: 2px solid var(--border-light);
            border-radius: 14px;
            background: white;
            color: var(--text-primary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--button-blue);
            box-shadow: 0 0 0 4px rgba(44, 107, 196, 0.15);
            transform: translateY(-2px);
        }

        .form-input.error {
            border-color: var(--error);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 1.3rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1.3rem;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .form-input:focus+.input-icon {
            color: var(--button-blue);
            transform: translateY(-50%) scale(1.15);
        }

        .error-message-text {
            display: none;
            color: var(--error);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            margin-left: 0.5rem;
            animation: slideDown 0.3s ease;
        }

        .error-message-text.show {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 1.3rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 1.2rem;
            transition: color 0.2s ease;
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--button-blue);
        }

        /* Form Options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 0.95rem;
            transition: color 0.2s ease;
        }

        .remember-label:hover {
            color: var(--button-blue);
        }

        .remember-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-light);
            border-radius: 6px;
            background: white;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }

        .remember-checkbox.checked {
            background: var(--button-blue);
            border-color: var(--button-blue);
            animation: checkPop 0.3s ease;
        }

        @keyframes checkPop {
            0% {
                transform: scale(0.8);
            }

            70% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        .remember-checkbox.checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        .forgot-link {
            color: var(--button-blue);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
            padding-bottom: 3px;
        }

        .forgot-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--button-blue);
            transition: width 0.3s ease;
            border-radius: 1px;
        }

        .forgot-link:hover::after {
            width: 100%;
        }

        /* Submit Button - Updated to match image color */
        .submit-btn {
            width: 100%;
            padding: 1.1rem;
            background: var(--button-blue); /* Solid button blue from image */
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 1.15rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(44, 107, 196, 0.3);
        }

        .submit-btn:hover {
            background: var(--button-hover);
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(44, 107, 196, 0.4);
        }

        .submit-btn:active {
            transform: translateY(-1px);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .submit-btn:hover::before {
            width: 400px;
            height: 400px;
        }

        .btn-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        /* Welcome Section */
        .welcome-section {
            flex: 1;
            background: linear-gradient(135deg, rgba(44, 107, 196, 0.03) 0%, rgba(13, 148, 136, 0.03) 100%);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%232c6bc4' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .welcome-title {
            font-size: 2.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--button-blue), var(--navy-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
            letter-spacing: -0.5px;
        }

        .welcome-text {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .features-list {
            text-align: left;
            margin-bottom: 2rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
            transition: transform 0.3s ease;
        }

        .feature-item:hover {
            transform: translateX(5px);
            color: var(--button-blue);
        }

        .feature-icon {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, var(--button-blue), var(--navy-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            flex-shrink: 0;
        }

        /* Image Container */
        .image-container {
            width: 100%;
            max-width: 320px;
            margin: 0 auto;
            position: relative;
        }

        .welcome-image {
            width: 100%;
            height: auto;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .welcome-image:hover {
            transform: scale(1.02) rotate(1deg);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.15);
        }

        .image-placeholder {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, var(--button-blue), var(--navy-blue));
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            box-shadow: 0 15px 35px rgba(44, 107, 196, 0.2);
        }

        .image-placeholder i {
            font-size: 64px;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        /* Messages */
        .alert-message {
            display: none;
            padding: 1rem 1.5rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .alert-message.success {
            background: linear-gradient(135deg, var(--success), #34d399);
            color: white;
        }

        .alert-message.error {
            background: linear-gradient(135deg, var(--error), #f87171);
            color: white;
        }

        /* Loader */
        .loader-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            z-index: 10;
            border-radius: 28px;
            animation: fadeIn 0.3s ease;
        }

        .spinner {
            width: 70px;
            height: 70px;
            border: 4px solid #f1f5f9;
            border-top: 4px solid var(--button-blue);
            border-right: 4px solid var(--accent-teal);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loader-text {
            margin-top: 1.5rem;
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--button-blue);
            animation: pulse 2s infinite;
        }

        /* Animations */
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .login-card {
                max-width: 900px;
            }
        }

        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
                max-width: 500px;
            }

            .form-section::before {
                display: none;
            }

            .form-section {
                border-bottom: 1px solid var(--border-light);
            }

            .welcome-section {
                padding: 2rem;
            }

            .header-container {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
                padding: 1rem 0;
            }

            .logo-container {
                margin: 0 auto;
            }

            .about-btn {
                margin-top: 1rem;
            }
        }

        @media (max-width: 480px) {
            .main-container {
                padding: 1rem;
            }

            .form-section,
            .welcome-section {
                padding: 2rem 1.5rem;
            }

            .form-title {
                font-size: 1.75rem;
            }

            .welcome-title {
                font-size: 1.75rem;
            }

            .form-input {
                padding: 1rem 1rem 1rem 3.5rem;
            }

            .form-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .header-container {
                padding: 0.75rem;
            }
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 1.5rem;
            color: var(--text-light);
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Additional Utility Classes */
        .shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-8px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(8px);
            }
        }

        .hidden {
            display: none !important;
        }

        .visible {
            display: flex !important;
        }
    </style>
</head>

<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
    </div>

    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <div class="logo-container">
                <img class="logo-img"
                    src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                    alt="Municipality of Paluan Logo" />
                <div class="header-title">
                    <h1>PROVINCE OF OCCIDENTAL MINDORO</h1>
                    <h1 class="municipality">MUNICIPALITY OF PALUAN</h1>
                    <p class="republic">REPUBLIC OF THE PHILIPPINES</p>
                </div>
            </div>

            <div class="about-btn">
                <a href="#">
                    <i class="fas fa-info-circle"></i>
                    <span>About Us</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-container">
        <div class="login-card">
            <!-- Loader Overlay -->
            <div class="loader-overlay" id="loaderOverlay">
                <div class="spinner"></div>
                <div class="loader-text">Authenticating...</div>
            </div>

            <!-- Login Form Section -->
            <div class="form-section">
                <!-- Alert Message Container -->
                <div class="alert-message" id="alertMessage">
                    <i class="fas" id="alertIcon"></i>
                    <span id="alertText"></span>
                </div>

                <!-- Form Header -->
                <div class="form-header">
                    <div class="form-logo">
                        <img  src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                            alt="HR Management System" />
                    </div>
                    <h2 class="form-title">HR Management System</h2>
                    <p class="form-subtitle">Sign in to your account</p>
                </div>

                <!-- Login Form -->
                <form id="loginForm">
                    <!-- Username Input -->
                    <div class="form-group">
                        <div class="input-wrapper">
                            <input type="text"
                                id="username"
                                name="username"
                                class="form-input"
                                placeholder="Enter your username or email"
                                required
                                autocomplete="username">
                            <i class="fas fa-user input-icon"></i>
                        </div>
                        <div class="error-message-text" id="usernameError"></div>
                    </div>

                    <!-- Password Input -->
                    <div class="form-group">
                        <div class="input-wrapper">
                            <input type="password"
                                id="password"
                                name="password"
                                class="form-input"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password">
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" id="passwordToggle">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="error-message-text" id="passwordError"></div>
                    </div>

                    <!-- Form Options -->
                    <div class="form-options">
                        <label class="remember-label" id="rememberLabel">
                            <div class="remember-checkbox" id="rememberCheckbox"></div>
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="forgot-link" id="forgotPasswordLink">Forgot password?</a>
                    </div>

                    <!-- Submit Button - Updated to match image color -->
                    <button type="submit" class="submit-btn" id="submitBtn">
                        <div class="btn-content">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>SIGN IN TO PORTAL</span>
                        </div>
                    </button>
                </form>
            </div>

            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <h3 class="welcome-title">Welcome Back!</h3>
                    <p class="welcome-text">We're glad to see you again. Sign in to access your HR Account and manage your information.</p>

                    <div class="features-list">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <span>Employee Management</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <span>Performance Analytics</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-file-contract"></i>
                            </div>
                            <span>Document Management</span>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <span>Leave Management</span>
                        </div>
                    </div>

                    <!-- Image Container -->
                    <div class="image-container">
                        <img id="welcomeImage" class="welcome-image"
                            src="image.jpg"
                            alt="HR Management Dashboard"
                            onerror="handleImageError(this)">

                        <div id="imagePlaceholder" class="image-placeholder hidden">
                            <i class="fas fa-users"></i>
                            <span>HR Dashboard Preview</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2024 Municipality of Paluan HR Management System. All rights reserved.</p>
        <p style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-light);">
            Secure login | Privacy protected | Official Government System
        </p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const loginForm = document.getElementById('loginForm');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('passwordToggle');
            const loaderOverlay = document.getElementById('loaderOverlay');
            const alertMessage = document.getElementById('alertMessage');
            const alertIcon = document.getElementById('alertIcon');
            const alertText = document.getElementById('alertText');
            const usernameError = document.getElementById('usernameError');
            const passwordError = document.getElementById('passwordError');
            const rememberCheckbox = document.getElementById('rememberCheckbox');
            const rememberLabel = document.getElementById('rememberLabel');
            const forgotPasswordLink = document.getElementById('forgotPasswordLink');
            const submitBtn = document.getElementById('submitBtn');
            const welcomeImage = document.getElementById('welcomeImage');
            const imagePlaceholder = document.getElementById('imagePlaceholder');

            // Mock credentials
            const VALID_USERNAME = 'jerwinsequijor@gmail.com';
            const VALID_PASSWORD = 'jerwin123';

            // Alternative images for fallback
            const alternativeImages = [
                'https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1565688534245-05d6b5be184a?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1573164713714-d95e436ab8d6?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1551434678-e076c223a692?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
                'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
            ];

            let currentImageIndex = 0;
            let isPasswordVisible = false;

            // Initialize the application
            function init() {
                loadSavedCredentials();
                setupEventListeners();
                preloadImages();
            }

            // Load saved credentials from localStorage
            function loadSavedCredentials() {
                if (localStorage.getItem('rememberCredentials') === 'true') {
                    rememberCheckbox.classList.add('checked');
                    const savedUsername = localStorage.getItem('lastUsername');
                    if (savedUsername) {
                        usernameInput.value = savedUsername;
                    }
                }
            }

            // Setup all event listeners
            function setupEventListeners() {
                // Password toggle
                passwordToggle.addEventListener('click', togglePasswordVisibility);

                // Remember me
                rememberLabel.addEventListener('click', toggleRememberMe);

                // Forgot password link
                forgotPasswordLink.addEventListener('click', handleForgotPassword);

                // Form submission
                loginForm.addEventListener('submit', handleFormSubmit);

                // Input validation on blur
                usernameInput.addEventListener('blur', validateUsername);
                passwordInput.addEventListener('blur', validatePassword);

                // Input validation on input
                usernameInput.addEventListener('input', clearError);
                passwordInput.addEventListener('input', clearError);
            }

            // Preload alternative images
            function preloadImages() {
                alternativeImages.forEach(src => {
                    const img = new Image();
                    img.src = src;
                });
            }

            // Handle image error
            function handleImageError(img) {
                console.log('Image failed to load:', img.src);

                currentImageIndex = (currentImageIndex + 1) % alternativeImages.length;

                if (currentImageIndex === 0) {
                    // We've tried all images, show placeholder
                    img.classList.add('hidden');
                    imagePlaceholder.classList.remove('hidden');
                } else {
                    // Try next image
                    img.src = alternativeImages[currentImageIndex];
                }
            }

            // Toggle password visibility
            function togglePasswordVisibility() {
                isPasswordVisible = !isPasswordVisible;
                passwordInput.type = isPasswordVisible ? 'text' : 'password';
                passwordToggle.innerHTML = isPasswordVisible ?
                    '<i class="fas fa-eye-slash"></i>' :
                    '<i class="fas fa-eye"></i>';
            }

            // Toggle remember me
            function toggleRememberMe() {
                rememberCheckbox.classList.toggle('checked');

                if (rememberCheckbox.classList.contains('checked')) {
                    localStorage.setItem('rememberCredentials', 'true');
                } else {
                    localStorage.removeItem('rememberCredentials');
                }
            }

            // Handle forgot password
            function handleForgotPassword(e) {
                e.preventDefault();
                showAlert('Please contact your HR administrator to reset your password.', 'info');
            }

            // Validate username
            function validateUsername() {
                const username = usernameInput.value.trim();
                if (!username) {
                    showInputError(usernameInput, usernameError, 'Username/Email is required');
                    return false;
                }

                // Check if it's an email format
                if (username.includes('@')) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(username)) {
                        showInputError(usernameInput, usernameError, 'Please enter a valid email address');
                        return false;
                    }
                }

                clearInputError(usernameInput, usernameError);
                return true;
            }

            // Validate password
            function validatePassword() {
                const password = passwordInput.value;
                if (!password) {
                    showInputError(passwordInput, passwordError, 'Password is required');
                    return false;
                }

                if (password.length < 6) {
                    showInputError(passwordInput, passwordError, 'Password must be at least 6 characters');
                    return false;
                }

                clearInputError(passwordInput, passwordError);
                return true;
            }

            // Show input error
            function showInputError(input, errorElement, message) {
                input.classList.add('error');
                errorElement.textContent = message;
                errorElement.classList.add('show');
            }

            // Clear input error
            function clearInputError(input, errorElement) {
                input.classList.remove('error');
                errorElement.classList.remove('show');
                errorElement.textContent = '';
            }

            // Clear all errors
            function clearError() {
                clearInputError(usernameInput, usernameError);
                clearInputError(passwordInput, passwordError);
                hideAlert();
            }

            // Show alert message
            function showAlert(message, type = 'error') {
                alertText.textContent = message;
                alertMessage.className = 'alert-message ' + type;
                alertIcon.className = type === 'success' ? 'fas fa-check-circle' :
                    type === 'info' ? 'fas fa-info-circle' :
                    'fas fa-exclamation-circle';
                alertMessage.classList.add('visible');

                // Auto-hide error messages after 5 seconds
                if (type === 'error') {
                    setTimeout(hideAlert, 5000);
                }
            }

            // Hide alert message
            function hideAlert() {
                alertMessage.classList.remove('visible');
            }

            // Create celebration particles
            function createCelebrationParticles() {
                const colors = ['#10b981', '#2c6bc4', '#0d9488', '#f97316'];

                for (let i = 0; i < 20; i++) {
                    setTimeout(() => {
                        createParticle(colors[Math.floor(Math.random() * colors.length)]);
                    }, i * 100);
                }
            }

            // Create a single particle
            function createParticle(color) {
                const particle = document.createElement('div');
                particle.style.position = 'absolute';
                particle.style.width = '8px';
                particle.style.height = '8px';
                particle.style.background = color;
                particle.style.borderRadius = '50%';
                particle.style.pointerEvents = 'none';
                particle.style.zIndex = '100';
                particle.style.top = '50%';
                particle.style.left = '50%';
                particle.style.boxShadow = `0 0 12px ${color}`;

                document.querySelector('.login-card').appendChild(particle);

                const angle = Math.random() * Math.PI * 2;
                const distance = 150 + Math.random() * 150;
                const size = 0.5 + Math.random() * 1.5;
                const duration = 1000 + Math.random() * 1000;

                particle.animate([{
                        transform: 'translate(-50%, -50%) scale(1)',
                        opacity: 1
                    },
                    {
                        transform: `translate(
                            ${Math.cos(angle) * distance}px, 
                            ${Math.sin(angle) * distance}px
                        ) scale(${size})`,
                        opacity: 0
                    }
                ], {
                    duration: duration,
                    easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
                }).onfinish = () => particle.remove();
            }

            // Handle form submission
            async function handleFormSubmit(e) {
                e.preventDefault();

                // Validate inputs
                const isUsernameValid = validateUsername();
                const isPasswordValid = validatePassword();

                if (!isUsernameValid || !isPasswordValid) {
                    showAlert('Please fix the errors in the form.', 'error');
                    return;
                }

                // Get form values
                const username = usernameInput.value.trim();
                const password = passwordInput.value;

                // Disable submit button and show loader
                submitBtn.disabled = true;
                loaderOverlay.classList.remove('hidden');

                try {
                    // Simulate API call
                    await simulateApiCall(username, password);

                    // Handle success
                    handleLoginSuccess(username);
                } catch (error) {
                    // Handle error
                    handleLoginError(error.message);
                } finally {
                    // Re-enable submit button and hide loader
                    submitBtn.disabled = false;
                    loaderOverlay.classList.add('hidden');
                }
            }

            // Simulate API call
            function simulateApiCall(username, password) {
                return new Promise((resolve, reject) => {
                    setTimeout(() => {
                        if (username === VALID_USERNAME && password === VALID_PASSWORD) {
                            resolve({
                                success: true
                            });
                        } else {
                            reject(new Error('Invalid credentials. Please check your username and password.'));
                        }
                    }, 1500);
                });
            }

            // Handle successful login
            function handleLoginSuccess(username) {
                // Save credentials if "Remember me" is checked
                if (rememberCheckbox.classList.contains('checked')) {
                    localStorage.setItem('lastUsername', username);
                } else {
                    localStorage.removeItem('lastUsername');
                }

                // Show success message and particles
                showAlert('Login successful! Redirecting to dashboard...', 'success');
                createCelebrationParticles();

                // Redirect to homepage
                setTimeout(() => {
                    window.location.href = 'homepage.php';
                }, 2000);
            }

            // Handle login error
            function handleLoginError(message) {
                // Show error message
                showAlert(message, 'error');

                // Shake animation
                loginForm.classList.add('shake');
                setTimeout(() => {
                    loginForm.classList.remove('shake');
                }, 500);

                // Clear password and focus on it
                passwordInput.value = '';
                passwordInput.focus();
            }

            // Initialize the application
            init();

            // Make handleImageError available globally for onerror attribute
            window.handleImageError = function(img) {
                handleImageError(img);
            };
        });
    </script>
</body>
</html>