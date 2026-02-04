<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Municipality of Paluan</title>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-blue': '#1048cb',
                        'secondary-blue': '#0C379D',
                        'dark-blue': '#0a2e7a',
                        'light-blue': '#4d7df0',
                        'gold': '#FFD700',
                        'light-gold': '#FFF8DC',
                        'platinum': '#E5E4E2',
                        'silver': '#C0C0C0',
                        'success-green': '#10B981',
                        'warning-orange': '#F59E0B',
                        'glass-white': 'rgba(255, 255, 255, 0.1)',
                        'glass-dark': 'rgba(0, 0, 0, 0.1)',
                    },
                    animation: {
                        'float-slow': 'float 8s ease-in-out infinite',
                        'float-medium': 'float 6s ease-in-out infinite',
                        'float-fast': 'float 4s ease-in-out infinite',
                        'pulse-glow': 'pulse-glow 3s ease-in-out infinite',
                        'slide-up': 'slide-up 0.7s ease-out forwards',
                        'fade-in': 'fade-in 1s ease-out forwards',
                        'gradient-shift': 'gradient-shift 15s ease infinite',
                        'shine': 'shine 2.5s ease-in-out infinite',
                        'bounce-subtle': 'bounce-subtle 2.5s ease-in-out infinite',
                        'spin-slow': 'spin 20s linear infinite',
                        'wiggle': 'wiggle 1s ease-in-out infinite',
                        'border-glow': 'border-glow 2s ease-in-out infinite',
                        'text-shimmer': 'text-shimmer 3s ease-in-out infinite',
                        'card-enter': 'card-enter 0.8s cubic-bezier(0.22, 1, 0.36, 1) forwards',
                        'text-gradient': 'text-gradient 4s ease infinite',
                        'letter-spacing': 'letter-spacing 2s ease-in-out infinite',
                        'spin': 'spin 1s linear infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0) rotate(0deg)' },
                            '33%': { transform: 'translateY(-20px) rotate(5deg)' },
                            '66%': { transform: 'translateY(10px) rotate(-5deg)' },
                        },
                        'pulse-glow': {
                            '0%, 100%': { 
                                boxShadow: '0 0 25px rgba(16, 72, 203, 0.4), 0 0 50px rgba(255, 215, 0, 0.2)',
                                transform: 'scale(1)'
                            },
                            '50%': { 
                                boxShadow: '0 0 40px rgba(16, 72, 203, 0.7), 0 0 80px rgba(255, 215, 0, 0.4)',
                                transform: 'scale(1.02)'
                            },
                        },
                        'slide-up': {
                            '0%': { transform: 'translateY(40px)', opacity: 0 },
                            '100%': { transform: 'translateY(0)', opacity: 1 },
                        },
                        'fade-in': {
                            '0%': { opacity: 0 },
                            '100%': { opacity: 1 },
                        },
                        'gradient-shift': {
                            '0%, 100%': { backgroundPosition: '0% 50%' },
                            '50%': { backgroundPosition: '100% 50%' },
                        },
                        'shine': {
                            '0%': { transform: 'translateX(-100%) rotate(45deg)' },
                            '100%': { transform: 'translateX(200%) rotate(45deg)' },
                        },
                        'bounce-subtle': {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-12px)' },
                        },
                        'wiggle': {
                            '0%, 100%': { transform: 'rotate(-3deg)' },
                            '50%': { transform: 'rotate(3deg)' },
                        },
                        'border-glow': {
                            '0%, 100%': { 
                                'box-shadow': '0 0 10px rgba(16, 72, 203, 0.3), inset 0 0 10px rgba(255, 215, 0, 0.1)',
                                'border-color': 'rgba(16, 72, 203, 0.3)'
                            },
                            '50%': { 
                                'box-shadow': '0 0 20px rgba(16, 72, 203, 0.6), inset 0 0 20px rgba(255, 215, 0, 0.3)',
                                'border-color': 'rgba(255, 215, 0, 0.5)'
                            },
                        },
                        'text-shimmer': {
                            '0%': { backgroundPosition: '-500px 0' },
                            '100%': { backgroundPosition: '500px 0' },
                        },
                        'card-enter': {
                            '0%': { 
                                transform: 'translateY(30px) scale(0.95)',
                                opacity: 0,
                                filter: 'blur(10px)'
                            },
                            '100%': { 
                                transform: 'translateY(0) scale(1)',
                                opacity: 1,
                                filter: 'blur(0px)'
                            },
                        },
                        'text-gradient': {
                            '0%, 100%': { 
                                'background-position': '0% 50%',
                                'background-size': '200% 200%'
                            },
                            '50%': { 
                                'background-position': '100% 50%',
                                'background-size': '200% 200%'
                            },
                        },
                        'letter-spacing': {
                            '0%, 100%': { 'letter-spacing': 'normal' },
                            '50%': { 'letter-spacing': '2px' },
                        },
                        'spin': {
                            '0%': { transform: 'rotate(0deg)' },
                            '100%': { transform: 'rotate(360deg)' }
                        }
                    },
                    backgroundImage: {
                        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                        'gradient-conic': 'conic-gradient(var(--tw-gradient-stops))',
                        'gradient-shimmer': 'linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent)',
                        'gradient-text': 'linear-gradient(135deg, #FFD700 0%, #FFED4E 25%, #FFD700 50%, #FFED4E 75%, #FFD700 100%)',
                        'gradient-blue-gold': 'linear-gradient(135deg, #1048cb 0%, #0C379D 25%, #FFD700 50%, #0C379D 75%, #1048cb 100%)',
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800&family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Enhanced Custom Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #2D3748;
            overflow-x: hidden;
            background-color: #0a0e17;
        }

        .hero-bg {
            position: relative;
            min-height: 100vh;
            background: linear-gradient(-45deg, #0a0e17, #0a2e7a, #1048cb, #1e40af);
            background-size: 400% 400%;
            animation: gradient-shift 20s ease infinite;
            overflow: hidden;
        }

        .animated-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .floating-shape {
            position: absolute;
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            background: linear-gradient(45deg, rgba(16, 72, 203, 0.15), rgba(255, 215, 0, 0.1));
            filter: blur(30px);
            mix-blend-mode: screen;
        }

        .shape-1 {
            width: 500px;
            height: 500px;
            top: 5%;
            left: 3%;
            animation: float-slow 12s ease-in-out infinite;
        }

        .shape-2 {
            width: 400px;
            height: 400px;
            top: 65%;
            right: 8%;
            animation: float-medium 10s ease-in-out infinite 1s;
        }

        .shape-3 {
            width: 300px;
            height: 300px;
            bottom: 15%;
            left: 10%;
            animation: float-fast 8s ease-in-out infinite 2s;
        }

        .shape-4 {
            width: 450px;
            height: 450px;
            top: 15%;
            right: 15%;
            animation: float-medium 11s ease-in-out infinite 0.5s;
        }

        .shape-5 {
            width: 350px;
            height: 350px;
            bottom: 8%;
            right: 3%;
            animation: float-slow 14s ease-in-out infinite 3s;
        }

        .grid-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25px 25px, rgba(255, 255, 255, 0.05) 2px, transparent 2px),
                radial-gradient(circle at 75px 75px, rgba(255, 255, 255, 0.05) 2px, transparent 2px);
            background-size: 100px 100px, 100px 100px;
            background-position: 0 0, 50px 50px;
            opacity: 0.4;
        }

        /* Enhanced Typography */
        .text-gradient-primary {
            background: linear-gradient(135deg, #FFD700 0%, #FFED4E 50%, #FFD700 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            background-size: 200% 200%;
            animation: text-gradient 4s ease infinite;
        }

        .text-gradient-secondary {
            background: linear-gradient(135deg, #4d7df0 0%, #1048cb 50%, #4d7df0 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            background-size: 200% 200%;
            animation: text-gradient 5s ease infinite;
        }

        .text-glow {
            text-shadow: 0 0 30px rgba(255, 215, 0, 0.5),
                         0 0 60px rgba(16, 72, 203, 0.3),
                         0 0 90px rgba(16, 72, 203, 0.1);
        }

        .text-glow-subtle {
            text-shadow: 0 0 20px rgba(255, 255, 255, 0.2),
                         0 0 40px rgba(16, 72, 203, 0.1);
        }

        .text-3d {
            text-shadow: 1px 1px 0 rgba(0,0,0,0.1),
                         2px 2px 0 rgba(0,0,0,0.1),
                         3px 3px 0 rgba(0,0,0,0.1);
        }

        .text-stroke {
            -webkit-text-stroke: 1px rgba(255, 255, 255, 0.1);
            paint-order: stroke fill;
        }

        /* Enhanced Header */
        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(25px) saturate(180%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.4);
            position: relative;
            z-index: 100;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .logo-container {
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .logo-container:hover {
            transform: translateY(-3px);
        }

        .logo-glow {
            filter: drop-shadow(0 5px 15px rgba(16, 72, 203, 0.4));
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .logo-glow:hover {
            filter: drop-shadow(0 10px 25px rgba(16, 72, 203, 0.6));
            transform: scale(1.05) rotate(5deg);
        }

        .header-text h1 {
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #0a2e7a 0%, #1048cb 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .header-text p {
            font-weight: 600;
            color: #4a5568;
            position: relative;
            padding-left: 10px;
        }

        .header-text p::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 70%;
            background: linear-gradient(to bottom, #FFD700, #1048cb);
            border-radius: 2px;
        }

        /* Enhanced Hero Section */
        .hero-section {
            padding: 100px 0;
            position: relative;
            z-index: 10;
        }

        .hero-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 900;
            line-height: 1.05;
            color: white;
            text-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            letter-spacing: -1px;
            position: relative;
            display: inline-block;
            font-size: 4rem;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.8rem;
            }
        }

        .hero-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #FFD700, #1048cb, #FFD700);
            border-radius: 3px;
            transform: scaleX(0);
            transform-origin: left;
            animation: text-shimmer 3s ease-in-out infinite;
        }

        .hero-subtitle {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            line-height: 1.2;
            margin-top: 1.5rem;
            position: relative;
        }

        .hero-accent {
            display: inline-block;
            position: relative;
            padding: 0 10px;
        }

        .hero-accent::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent 0%, 
                rgba(255, 215, 0, 0.2) 25%, 
                rgba(16, 72, 203, 0.3) 50%, 
                rgba(255, 215, 0, 0.2) 75%, 
                transparent 100%);
            transform: translateY(-50%) skewX(-15deg);
            border-radius: 10px;
            z-index: -1;
        }

        .hero-description {
            font-family: 'Inter', sans-serif;
            font-size: 1.25rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 400;
            max-width: 800px;
            margin: 2rem auto 3rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            padding: 0 1rem;
        }

        .hero-description strong {
            color: #FFD700;
            font-weight: 700;
            position: relative;
        }

        .hero-description strong::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #FFD700, transparent);
        }

        /* Enhanced Feature Cards */
        .feature-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px) saturate(180%);
            border-radius: 24px;
            padding: 40px 30px;
            transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(255, 255, 255, 0.15);
            position: relative;
            overflow: hidden;
            height: 100%;
            transform-style: preserve-3d;
            perspective: 1000px;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.8s ease;
        }

        .feature-card:hover::before {
            left: 100%;
        }

        .feature-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 24px;
            padding: 2px;
            background: linear-gradient(45deg, rgba(255, 215, 0, 0.3), rgba(16, 72, 203, 0.3), rgba(255, 215, 0, 0.3));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .feature-card:hover::after {
            opacity: 1;
        }

        .feature-card:hover {
            transform: translateY(-15px) scale(1.03) rotateX(5deg);
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3), 0 0 40px rgba(16, 72, 203, 0.2);
            border-color: rgba(255, 255, 255, 0.25);
        }

        .feature-icon {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .feature-card:hover .feature-icon {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.3), rgba(255, 215, 0, 0.2));
            transform: scale(1.15) rotate(15deg);
            border-color: rgba(255, 215, 0, 0.5);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.3);
        }

        .feature-icon i {
            font-size: 2.5rem;
            color: white;
            position: relative;
            z-index: 2;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.3));
        }

        .feature-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: white;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
            text-align: center;
            width: 100%;
        }

        .feature-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, #FFD700, #1048cb);
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .feature-card:hover .feature-title::after {
            width: 100px;
        }

        .feature-description {
            font-family: 'Inter', sans-serif;
            font-size: 1.1rem;
            line-height: 1.7;
            color: rgba(226, 232, 240, 0.9);
            font-weight: 400;
            text-align: center;
        }

        /* Enhanced Button Styles */
        .hero-login-btn {
            padding: 22px 45px;
            font-size: 1.2rem;
            border: none;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            text-decoration: none;
            font-weight: 700;
            position: relative;
            overflow: hidden;
            z-index: 1;
            letter-spacing: 0.5px;
            transform-style: preserve-3d;
            perspective: 1000px;
            font-family: 'Poppins', sans-serif;
        }

        .hero-login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.7s ease;
            z-index: -1;
        }

        .hero-login-btn:hover::before {
            left: 100%;
        }

        .hero-login-btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: inherit;
            border-radius: inherit;
            z-index: -2;
            filter: blur(20px);
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .hero-login-btn:hover::after {
            opacity: 0.7;
        }

        .hero-login-btn.secondary {
            background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
            color: #1048cb;
            border: 2px solid rgba(255, 255, 255, 0.4);
        }

        .hero-login-btn.secondary:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #0a2e7a;
            transform: translateY(-10px) scale(1.05) rotateX(10deg);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4), 0 0 30px rgba(255, 215, 0, 0.3);
            border-color: rgba(255, 215, 0, 0.5);
            animation: border-glow 2s ease-in-out infinite;
        }

        .hero-login-btn.admin-login {
            background: linear-gradient(135deg, #1048cb 0%, #0a2e7a 100%);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .hero-login-btn.admin-login:hover {
            background: linear-gradient(135deg, #0C379D 0%, #082158 100%);
            color: white;
            transform: translateY(-10px) scale(1.05) rotateX(10deg);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4), 0 0 30px rgba(16, 72, 203, 0.5);
            border-color: rgba(16, 72, 203, 0.7);
            animation: border-glow 2s ease-in-out infinite;
        }

        .hero-login-btn i {
            transition: all 0.3s ease;
        }

        .hero-login-btn:hover i {
            transform: translateX(5px) scale(1.2);
        }

        /* Button spinner */
        .btn-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        .btn-spinner.secondary {
            border: 3px solid rgba(16, 72, 203, 0.3);
            border-top-color: #1048cb;
        }

        /* Enhanced Weather Widget */
        .weather-widget {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(25px) saturate(180%);
            border-radius: 20px;
            padding: 20px 25px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            flex-direction: column;
            gap: 15px;
            min-width: 260px;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .weather-widget::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #FFD700, #1048cb, #0a2e7a);
            border-radius: 20px 20px 0 0;
        }

        .weather-widget:hover {
            transform: translateY(-8px) rotateX(5deg);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25), 0 0 30px rgba(16, 72, 203, 0.2);
        }

        .weather-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .weather-temp {
            font-size: 2rem;
            font-weight: 900;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 12px;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .weather-temp i {
            color: #f59e0b;
            font-size: 2.2rem;
            filter: drop-shadow(0 2px 5px rgba(245, 158, 11, 0.3));
            animation: float-medium 4s ease-in-out infinite;
        }

        .weather-condition {
            font-size: 1rem;
            color: #4a5568;
            font-weight: 700;
            padding: 5px 12px;
            background: rgba(16, 72, 203, 0.1);
            border-radius: 20px;
            border: 1px solid rgba(16, 72, 203, 0.2);
        }

        .language-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #718096;
        }

        .language-selector span {
            cursor: pointer;
            padding: 4px 12px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .language-selector .lang-active {
            background: linear-gradient(135deg, #1048cb, #0C379D);
            color: white;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(16, 72, 203, 0.3);
        }

        .language-selector span:not(.lang-active):hover {
            background: rgba(16, 72, 203, 0.1);
            color: #1048cb;
            transform: translateY(-2px);
        }

        .datetime-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #718096;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            padding-top: 15px;
        }

        .time-display {
            font-weight: 800;
            color: #2d3748;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
        }

        .date-display {
            color: #4a5568;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Enhanced Footer */
        footer {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(25px);
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            position: relative;
            z-index: 10;
        }

        .footer-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 900;
            font-size: 1.75rem;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #FFD700, #FFF8DC);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 2px 20px rgba(255, 215, 0, 0.3);
        }

        .footer-description {
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 400;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 30px;
            right: 30px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            color: #2d3748;
            padding: 20px 25px;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            z-index: 1000;
            transform: translateX(100px);
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            max-width: 350px;
        }

        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }

        /* Animations */
        .animate-slide-up {
            animation: slide-up 0.7s ease-out forwards;
            opacity: 0;
        }

        .animate-fade-in {
            animation: fade-in 1s ease-out forwards;
            opacity: 0;
        }

        .animate-card-enter {
            animation: card-enter 0.8s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            opacity: 0;
        }

        .delay-1 {
            animation-delay: 0.1s;
        }

        .delay-2 {
            animation-delay: 0.2s;
        }

        .delay-3 {
            animation-delay: 0.4s;
        }

        .delay-4 {
            animation-delay: 0.6s;
        }

        .delay-5 {
            animation-delay: 0.8s;
        }

        .delay-6 {
            animation-delay: 1s;
        }

        /* Badge Styling */
        .badge-new {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, #FFD700, #FF9800);
            color: #0a2e7a;
            font-size: 0.75rem;
            font-weight: 900;
            padding: 4px 12px;
            border-radius: 20px;
            transform: rotate(15deg);
            box-shadow: 0 4px 15px rgba(255, 152, 0, 0.3);
            animation: wiggle 3s ease-in-out infinite;
            z-index: 5;
            font-family: 'Poppins', sans-serif;
        }

        .badge-live {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
        }

        .badge-ai {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: white;
        }

        /* Shine effect for buttons */
        .shine-effect {
            position: relative;
            overflow: hidden;
        }

        .shine-effect::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -60%;
            width: 20%;
            height: 200%;
            opacity: 0;
            transform: rotate(30deg);
            background: rgba(255, 255, 255, 0.13);
            background: linear-gradient(
                to right,
                rgba(255, 255, 255, 0.13) 0%,
                rgba(255, 255, 255, 0.13) 77%,
                rgba(255, 255, 255, 0.5) 92%,
                rgba(255, 255, 255, 0.0) 100%
            );
        }

        .shine-effect:hover::after {
            opacity: 1;
            left: 130%;
            transition-property: left, opacity;
            transition-duration: 0.7s, 0.15s;
            transition-timing-function: ease;
        }

        /* Particle effect */
        .particle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            pointer-events: none;
            z-index: 1;
        }

        /* Scroll indicator */
        .scroll-indicator {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-size: 0.9rem;
            opacity: 0.8;
            animation: bounce-subtle 2s ease-in-out infinite;
            z-index: 10;
        }

        /* Enhanced Responsive */
        @media (max-width: 767px) {
            .hero-title {
                font-size: 2.8rem;
                text-align: center;
            }

            .hero-title::after {
                left: 50%;
                transform: translateX(-50%);
                width: 80%;
            }

            .hero-subtitle {
                font-size: 1.8rem;
                text-align: center;
            }

            .hero-description {
                font-size: 1.1rem;
                text-align: center;
                padding: 0 10px;
            }

            .hero-buttons-container {
                flex-direction: column;
                gap: 20px;
                width: 100%;
                margin-top: 50px !important;
                margin-left: 0 !important;
                padding: 0 15px;
            }

            .hero-login-btn {
                width: 100%;
                padding: 20px 30px;
                font-size: 1.1rem;
                margin: 0 !important;
            }

            .hero-section {
                padding: 60px 0;
            }

            .header-container {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }

            .logo-container {
                margin-left: 0 !important;
                margin-bottom: 15px;
            }

            .header-text h1 {
                font-size: 1.3rem;
            }

            .header-text p {
                font-size: 0.95rem;
            }

            .header-logo {
                height: 85px !important;
            }

            .floating-shape {
                opacity: 0.4;
            }

            .weather-widget-container {
                order: 3;
                width: 100%;
                margin-top: 20px;
            }

            .weather-widget {
                width: 100%;
                min-width: unset;
            }

            .feature-card {
                padding: 25px 20px;
            }

            .feature-title {
                font-size: 1.5rem;
            }

            .feature-icon {
                width: 70px;
                height: 70px;
            }

            .feature-icon i {
                font-size: 2rem;
            }
        }

        @media (min-width: 768px) and (max-width: 1023px) {
            .hero-title {
                font-size: 3.5rem;
            }

            .hero-subtitle {
                font-size: 2.2rem;
            }

            .hero-buttons-container {
                margin-top: 120px !important;
                margin-left: 180px !important;
            }

            .logo-container {
                margin-left: -10px !important;
            }
        }

        /* Additional enhancements */
        .hero-buttons-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 25px;
            width: 100%;
        }

        @media (min-width: 768px) {
            .hero-buttons-container {
                flex-direction: row;
                justify-content: center;
                gap: 30px;
            }
        }

        .municipal-text {
            color: #1a1a1a;
            position: relative;
            display: inline-block;
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
        }

        .municipal-text::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #1048cb, #FFD700, #1048cb);
            border-radius: 2px;
            animation: text-shimmer 4s ease-in-out infinite;
        }

        /* Tagline styling */
        .tagline {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            letter-spacing: 2px;
            text-transform: uppercase;
            position: relative;
            display: inline-block;
            margin-bottom: 2rem;
        }

        .tagline::before, .tagline::after {
            content: '✦';
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: #FFD700;
            font-size: 1.2rem;
        }

        .tagline::before {
            left: -35px;
        }

        .tagline::after {
            right: -35px;
        }

        /* Section divider */
        .section-divider {
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, #FFD700, #1048cb);
            margin: 3rem auto;
            border-radius: 2px;
            position: relative;
            overflow: hidden;
        }

        .section-divider::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
            animation: shine 2s infinite;
        }
    </style>
</head>

<body>
    <!-- Enhanced Animated Background -->
    <div class="animated-background">
        <div class="floating-shape shape-1"></div>
        <div class="floating-shape shape-2"></div>
        <div class="floating-shape shape-3"></div>
        <div class="floating-shape shape-4"></div>
        <div class="floating-shape shape-5"></div>
        <div class="grid-pattern"></div>
    </div>

    <div class="hero-bg">
        <!-- Enhanced Header -->
        <header class="w-full">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col md:flex-row items-start md:items-center justify-between header-container">
                <div class="flex items-center space-x-5 logo-container w-full md:w-auto animate-slide-up">
                    <a href="#" class="logo-glow">
                        <img style="height: 110px; width: 100px;" class="md:h-24 md:w-24 header-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Municipality of Paluan Logo" />
                    </a>

                    <div class="header-text">
                        <h1 class="text-lg md:text-xl font-black text-gray-900 tracking-tight">REPUBLIC OF THE PHILIPPINES</h1>
                        <h1 class="text-lg md:text-xl font-black municipal-text tracking-tight">PROVINCE OF OCCIDENTAL MINDORO</h1>
                        <p class="text-base md:text-lg font-semibold text-gray-700 mt-1">MUNICIPALITY OF PALUAN</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Enhanced Main Content -->
        <section class="hero-section flex-grow">
            <div class="container mx-auto flex flex-col items-center justify-center px-4 ">
                <div class="hero-content z-10 p-4 md:p-0 w-full text-center">
                    
                    <!-- Tagline -->
                    <div class="tagline animate-fade-in delay-1 mb-8">
                        Transforming HR Management
                    </div>
                    
                    <!-- Main Title -->
                    <h1 class="hero-title mb-6 animate-slide-up delay-1">
                        <span class="text-gradient-primary text-glow">Human Resource Management System</span>
                    </h1>
                    
                    <!-- Accent Text -->
                    <div class="hero-subtitle mb-12 animate-slide-up delay-3 text-gradient-primary text-glow">
                        <span class="hero-accent text-3d">"HRMS"</span>
                    </div>
                    
                    <!-- Section Divider -->
                    <div class="section-divider animate-fade-in delay-4"></div>
                    
                    <!-- Description -->
                    <p class="hero-description animate-slide-up delay-4">
                        <strong>Streamline</strong> employee records, <strong>manage</strong> attendance, and <strong>facilitate</strong> key HR processes with our <strong class="text-gradient-primary">integrated system</strong> designed specifically for the <strong class="text-gradient-secondary">Municipality of Paluan</strong>. Experience the future of public sector human resource management.
                    </p>

                    <!-- Enhanced Feature Highlights -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-10 my-16">
                        <div class="feature-card text-center animate-card-enter delay-2">
                            <div class="relative">
                                <div class="feature-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                               
                            </div>
                            <h3 class="feature-title">Employee Management</h3>
                            <p class="feature-description">Efficiently manage comprehensive employee records, personal information, and employment history in one centralized, secure system designed for government efficiency.</p>
                        </div>
                        <div class="feature-card text-center animate-card-enter delay-3">
                            <div class="relative">
                                <div class="feature-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                              
                            </div>
                            <h3 class="feature-title">Attendance Tracking</h3>
                            <p class="feature-description">Monitor and manage employee attendance, leaves, and time-off requests with real-time automated tracking, advanced reporting, and compliance monitoring.</p>
                        </div>
                        <div class="feature-card text-center animate-card-enter delay-4">
                            <div class="relative">
                                <div class="feature-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                
                            </div>
                            <h3 class="feature-title">HR Analytics</h3>
                            <p class="feature-description">Gain valuable insights with comprehensive HR reporting, predictive analytics, and interactive data visualization tools for informed government decision-making.</p>
                        </div>
                    </div>

                    <!-- Enhanced Login Buttons -->
                    <div class="hero-buttons-container animate-slide-up delay-5 mt-12">
                        <a href="../../userside/php/login.php" class="hero-login-btn secondary user-login shine-effect">
                            <i class="fas fa-user-tie mr-3 text-lg"></i>
                            Employee Portal Login
                        </a>
                        <a href="login.php" class="hero-login-btn admin-login shine-effect">
                            <i class="fas fa-user-shield mr-3 text-lg"></i>
                            Administrator Access
                        </a>
                    </div>
                    
                    
                </div>
            </div>
        </section>

        <!-- Enhanced Footer -->
        <footer class="p-8 text-center mt-auto">
            <div class="max-w-7xl mx-auto">
                <h2 class="footer-title mb-4">&copy; 2025 Municipality of Paluan HRMO</h2>
                <p class="footer-description mb-6">
                    Streamlining HR processes for better governance and public service. Committed to excellence in local government service delivery through innovative technology solutions.
                </p>
                <div class="flex justify-center space-x-8 mt-6 text-gray-400 text-2xl">
                    <a href="#" class="hover:text-white transition-all duration-300 hover:scale-125 hover:text-primary-blue transform hover:rotate-12" title="Facebook">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="#" class="hover:text-white transition-all duration-300 hover:scale-125 hover:text-success-green transform hover:rotate-12" title="Email">
                        <i class="fas fa-envelope"></i>
                    </a>
                    <a href="#" class="hover:text-white transition-all duration-300 hover:scale-125 hover:text-warning-orange transform hover:rotate-12" title="Phone">
                        <i class="fas fa-phone"></i>
                    </a>
                    <a href="#" class="hover:text-white transition-all duration-300 hover:scale-125 hover:text-light-blue transform hover:rotate-12" title="Twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="hover:text-white transition-all duration-300 hover:scale-125 hover:text-gold transform hover:rotate-12" title="LinkedIn">
                        <i class="fab fa-linkedin"></i>
                    </a>
                </div>
                <div class="mt-8 pt-6 border-t border-white/10">
                    <p class="text-xs text-gray-400 font-medium">
                        Version 2.5.1 • Last Updated: January 2025 • SSL Secured • 
                        <span class="text-success-green ml-2">
                            <i class="fas fa-check-circle"></i> System Operational
                        </span>
                    </p>
                </div>
            </div>
        </footer>
    </div>

    <!-- Notification Container -->
    <div id="notificationContainer"></div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const adminLoginLink = document.querySelector('.admin-login');
            const userLoginLink = document.querySelector('.user-login');
            
            // Initialize animations
            const animatedElements = document.querySelectorAll('.animate-slide-up, .animate-fade-in, .animate-card-enter');
            animatedElements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = 1;
                }, index * 100);
            });

            // Enhanced login click handler - simple spinner only
            function handleLoginClick(event) {
                event.preventDefault();
                event.stopPropagation();
                
                const btn = event.currentTarget;
                const isAdmin = btn.classList.contains('admin-login');
                const originalText = btn.innerHTML;
                const isSecondary = btn.classList.contains('secondary');
                
                // Add spinner to button
                btn.innerHTML = `
                    <span class="btn-spinner ${isSecondary ? 'secondary' : ''}"></span>
                    ${isAdmin ? 'Redirecting to Admin Portal...' : 'Redirecting to Employee Portal...'}
                `;
                
                // Disable button to prevent multiple clicks
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.8';
                
                // Store original href
                const targetUrl = btn.getAttribute('href');
                
                // Add ripple effect
                const ripple = document.createElement('span');
                const rect = btn.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = event.clientX - rect.left - size / 2;
                const y = event.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.7);
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    width: ${size}px;
                    height: ${size}px;
                    top: ${y}px;
                    left: ${x}px;
                    pointer-events: none;
                `;
                
                btn.appendChild(ripple);
                setTimeout(() => ripple.remove(), 600);
                
                // Redirect after short delay
                setTimeout(() => {
                    window.location.href = targetUrl;
                }, 800);
                
                // Add CSS for ripple animation
                if (!document.getElementById('rippleStyle')) {
                    const style = document.createElement('style');
                    style.id = 'rippleStyle';
                    style.textContent = `
                        @keyframes ripple {
                            to {
                                transform: scale(4);
                                opacity: 0;
                            }
                        }
                    `;
                    document.head.appendChild(style);
                }
            }

            if (adminLoginLink) adminLoginLink.addEventListener('click', handleLoginClick);
            if (userLoginLink) userLoginLink.addEventListener('click', handleLoginClick);
            
            // Enhanced feature card interactions
            const featureCards = document.querySelectorAll('.feature-card');
            featureCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    const icon = this.querySelector('.feature-icon i');
                    const title = this.querySelector('.feature-title');
                    
                    if (icon) {
                        icon.style.transform = 'scale(1.2) rotate(10deg)';
                        icon.style.transition = 'transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
                    }
                    
                    if (title) {
                        title.style.letterSpacing = '1px';
                        title.style.transition = 'letter-spacing 0.3s ease';
                    }
                    
                    // Add particle effect
                    for (let i = 0; i < 5; i++) {
                        createParticle(this);
                    }
                });
                
                card.addEventListener('mouseleave', function() {
                    const icon = this.querySelector('.feature-icon i');
                    const title = this.querySelector('.feature-title');
                    
                    if (icon) {
                        icon.style.transform = 'scale(1) rotate(0deg)';
                    }
                    
                    if (title) {
                        title.style.letterSpacing = 'normal';
                    }
                });
                
                // Click effect
                card.addEventListener('click', function(e) {
                    if (!e.target.closest('a')) {
                        this.style.transform = 'translateY(-5px) scale(0.98)';
                        setTimeout(() => {
                            this.style.transform = '';
                        }, 200);
                    }
                });
            });
            
            // Particle effect function
            function createParticle(element) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                const size = Math.random() * 10 + 5;
                const rect = element.getBoundingClientRect();
                const x = Math.random() * rect.width;
                const y = Math.random() * rect.height;
                
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${x}px`;
                particle.style.top = `${y}px`;
                particle.style.background = `radial-gradient(circle, rgba(255,215,0,0.3), transparent)`;
                particle.style.boxShadow = `0 0 ${size}px ${size/2}px rgba(255,215,0,0.2)`;
                
                element.appendChild(particle);
                
                // Animate particle
                const angle = Math.random() * Math.PI * 2;
                const speed = Math.random() * 50 + 30;
                const tx = Math.cos(angle) * speed;
                const ty = Math.sin(angle) * speed;
                
                let opacity = 1;
                const fade = setInterval(() => {
                    opacity -= 0.05;
                    particle.style.opacity = opacity;
                    particle.style.transform = `translate(${tx}px, ${ty}px) scale(${1 - opacity})`;
                    
                    if (opacity <= 0) {
                        clearInterval(fade);
                        particle.remove();
                    }
                }, 30);
            }
            
            // Enhanced date/time functionality
            function updateDateTime() {
                const now = new Date();
                const options = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                };
                
                // Format time
                let hours = now.getHours();
                let minutes = now.getMinutes();
                let seconds = now.getSeconds();
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12;
                minutes = minutes < 10 ? '0' + minutes : minutes;
                seconds = seconds < 10 ? '0' + seconds : seconds;
                const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;
                
                // Format date
                const dateString = now.toLocaleDateString('en-US', options);
                
                // Update DOM
                const timeElement = document.getElementById('currentTime');
                const dateElement = document.getElementById('currentDate');
                
                if (timeElement) {
                    timeElement.textContent = timeString;
                    // Add pulsing animation to seconds
                    if (seconds % 2 === 0) {
                        timeElement.style.color = '#FFD700';
                        setTimeout(() => {
                            timeElement.style.color = '#2d3748';
                        }, 500);
                    }
                }
                if (dateElement) dateElement.textContent = dateString;
            }
            
            // Update time immediately and then every second
            updateDateTime();
            setInterval(updateDateTime, 1000);
            
            // Notification function
            function showNotification(message, type = 'info') {
                const container = document.getElementById('notificationContainer');
                const notification = document.createElement('div');
                notification.className = 'notification';
                
                let icon = 'fas fa-info-circle';
                let color = 'text-primary-blue';
                
                switch(type) {
                    case 'success': 
                        icon = 'fas fa-check-circle'; 
                        color = 'text-success-green';
                        break;
                    case 'warning': 
                        icon = 'fas fa-exclamation-triangle'; 
                        color = 'text-warning-orange';
                        break;
                    case 'error': 
                        icon = 'fas fa-times-circle'; 
                        color = 'text-red-500';
                        break;
                }
                
                notification.innerHTML = `
                    <div class="flex items-center">
                        <i class="${icon} ${color} mr-3 text-xl"></i>
                        <div class="flex-1">
                            ${message}
                        </div>
                        <button class="ml-4 text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                
                container.appendChild(notification);
                
                // Show with animation
                setTimeout(() => {
                    notification.classList.add('show');
                }, 10);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 500);
                }, 5000);
            }
            
            // Add parallax effect to floating shapes on scroll
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const shapes = document.querySelectorAll('.floating-shape');
                
                shapes.forEach((shape, index) => {
                    const speed = 0.5 + (index * 0.1);
                    const yPos = -(scrolled * speed);
                    shape.style.transform = `translateY(${yPos}px)`;
                });
            });
            
            // Add text animation effects
            const textElements = document.querySelectorAll('.text-gradient-primary, .text-gradient-secondary');
            textElements.forEach(text => {
                text.addEventListener('mouseenter', function() {
                    this.style.animationDuration = '2s';
                });
                
                text.addEventListener('mouseleave', function() {
                    this.style.animationDuration = '4s';
                });
            });
            
            // Auto-typing effect for tagline
            const tagline = document.querySelector('.tagline');
            if (tagline) {
                const originalText = tagline.textContent;
                tagline.textContent = '';
                let i = 0;
                const typeWriter = () => {
                    if (i < originalText.length) {
                        tagline.textContent += originalText.charAt(i);
                        i++;
                        setTimeout(typeWriter, 50);
                    }
                };
                // Start typing after 1.5 seconds
                setTimeout(typeWriter, 1500);
            }
        });
    </script>
</body>

</html>