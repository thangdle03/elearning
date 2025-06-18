<?php
// filepath: d:\Xampp\htdocs\elearning\includes\header.php

// Include config if not already included
if (!defined('SITE_URL')) {
    require_once __DIR__ . '/config.php';
}

// Set default page variables
$page_title = $page_title ?? SITE_NAME;
$page_description = $page_description ?? 'Nền tảng học trực tuyến hàng đầu Việt Nam';
$show_page_header = $show_page_header ?? false;
$page_header_title = $page_header_title ?? '';
$page_header_subtitle = $page_header_subtitle ?? '';
$page_header_class = $page_header_class ?? '';

// Ensure functions exist
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        if (isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'] ?? 0,
                'username' => $_SESSION['username'] ?? 'User',
                'email' => $_SESSION['email'] ?? '',
                'role' => $_SESSION['role'] ?? 'student'
            ];
        }
        return null;
    }
}

if (!function_exists('isCurrentPage')) {
    function isCurrentPage($page) {
        $current = basename($_SERVER['PHP_SELF'], '.php');
        return $current === $page;
    }
}

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="keywords" content="học lập trình, khóa học online, e-learning, lập trình web">
    <meta name="author" content="<?php echo SITE_NAME; ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL . $_SERVER['REQUEST_URI']; ?>">
    
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Professional Color Palette */
        :root {
            /* Primary Colors - Professional Blue Gradient */
            --primary: #1e3a8a;
            --primary-light: #3b82f6;
            --primary-dark: #1e293b;
            --primary-50: #eff6ff;
            --primary-100: #dbeafe;
            --primary-500: #3b82f6;
            --primary-600: #2563eb;
            --primary-700: #1d4ed8;
            --primary-800: #1e40af;
            --primary-900: #1e3a8a;
            
            /* Secondary Colors - Professional Orange/Amber */
            --secondary: #f59e0b;
            --secondary-light: #fbbf24;
            --secondary-dark: #d97706;
            --accent: #06b6d4;
            
            /* Neutral Colors */
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            
            /* Semantic Colors */
            --success: #059669;
            --success-light: #10b981;
            --warning: #d97706;
            --danger: #dc2626;
            --info: #0891b2;
            
            /* Background Colors */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            
            /* Professional Gradients */
            --gradient-primary: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #06b6d4 100%);
            --gradient-secondary: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-success: linear-gradient(135deg, #059669 0%, #10b981 100%);
            --gradient-dark: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            
            /* Shadows */
            --shadow-xs: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --shadow-2xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            
            /* Border Radius */
            --radius-sm: 6px;
            --radius: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 24px;
            --radius-full: 9999px;
            
            /* Transitions */
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s ease-out;
            --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--gray-800);
            background: var(--bg-secondary);
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Professional Navigation Bar */
        .main-navbar {
            background: var(--bg-primary);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            padding: 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            transition: var(--transition);
        }

        .main-navbar.scrolled {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom-color: var(--gray-300);
            box-shadow: var(--shadow-lg);
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
        }

        /* Professional Brand */
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-900);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: var(--transition);
        }

        .navbar-brand:hover {
            color: var(--primary-600);
            transform: translateY(-1px);
        }

        .brand-icon {
            background: var(--gradient-primary);
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            box-shadow: var(--shadow-md);
        }

        /* Navigation Links Container */
        .nav-container {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex: 1;
            margin-left: 3rem;
        }

        /* Main Navigation */
        .main-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-item .nav-link {
            color: var(--gray-600);
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.75rem 1rem;
            border-radius: var(--radius);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            position: relative;
        }

        .nav-item .nav-link:hover {
            color: var(--primary-600);
            background: var(--primary-50);
        }

        .nav-item .nav-link.active {
            color: var(--primary-700);
            background: var(--primary-100);
            font-weight: 600;
        }

        .nav-item .nav-link i {
            font-size: 1rem;
            width: 18px;
            text-align: center;
        }

        /* Professional Search Bar */
        .search-container {
            position: relative;
            flex: 1;
            max-width: 400px;
            margin: 0 2rem;
        }

        .search-form {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input {
            width: 100%;
            height: 44px;
            padding: 0 1rem 0 3rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-full);
            background: var(--bg-primary);
            color: var(--gray-700);
            font-size: 0.95rem;
            font-weight: 500;
            transition: var(--transition);
            outline: none;
        }

        .search-input:focus {
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: white;
        }

        .search-input::placeholder {
            color: var(--gray-400);
            font-weight: 400;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1rem;
            pointer-events: none;
            z-index: 2;
        }

        .search-btn {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            background: var(--gradient-primary);
            border: none;
            border-radius: var(--radius-full);
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .search-btn:hover {
            background: var(--gradient-secondary);
            transform: translateY(-50%) scale(1.05);
        }

        /* User Menu */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-dropdown {
            position: relative;
        }

        .user-toggle {
            background: var(--bg-primary);
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-full);
            padding: 0.5rem 1rem;
            color: var(--gray-700);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: var(--transition);
            cursor: pointer;
            font-weight: 500;
            min-width: 120px;
        }

        .user-toggle:hover {
            background: var(--primary-50);
            border-color: var(--primary-300);
            color: var(--primary-700);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-full);
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.85rem;
            border: 2px solid var(--primary-100);
        }

        /* Professional Dropdown Menu */
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            padding: 0.75rem 0;
            min-width: 280px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: var(--transition);
            z-index: 1000;
        }

        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            padding: 0.75rem 1.25rem;
            color: var(--gray-700);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: var(--transition-fast);
            font-weight: 500;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }

        .dropdown-item:hover {
            background: var(--gray-50);
            color: var(--primary-700);
            padding-left: 1.5rem;
        }

        .dropdown-item.danger:hover {
            background: #fef2f2;
            color: var(--danger);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            font-size: 1rem;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--gray-200);
            margin: 0.5rem 0;
            border: none;
        }

        .dropdown-header {
            padding: 0.75rem 1.25rem 0.5rem;
            color: var(--gray-500);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 0.5rem;
        }

        /* Authentication Buttons */
        .auth-buttons {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-login {
            color: var(--gray-600);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-login:hover {
            color: var(--primary-600);
            background: var(--primary-50);
        }

        .btn-register {
            background: var(--gradient-primary);
            color: white;
            font-weight: 600;
            padding: 0.65rem 1.25rem;
            border-radius: var(--radius-full);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
        }

        .btn-register:hover {
            background: var(--gradient-secondary);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        /* Mobile Menu Toggle */
        .mobile-toggle {
            display: none;
            background: var(--bg-primary);
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 0.5rem;
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
        }

        .mobile-toggle:hover {
            background: var(--primary-50);
            border-color: var(--primary-300);
            color: var(--primary-600);
        }

        /* Professional Page Header */
        .page-header {
            background: var(--gradient-primary);
            color: white;
            padding: 3rem 0 2rem;
            margin-top: 72px;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
            opacity: 0.4;
        }

        .page-header .container {
            position: relative;
            z-index: 2;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .page-header p {
            font-size: 1.125rem;
            opacity: 0.9;
            margin: 0;
            font-weight: 400;
        }

        /* Page Header Variations */
        .page-header.courses-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #06b6d4 100%);
        }

        .page-header.dashboard-header {
            background: var(--gradient-success);
        }

        .page-header.profile-header {
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
        }

        /* Content Spacing */
        .main-content {
            padding-top: 72px;
            min-height: calc(100vh - 72px);
        }

        .main-content.with-page-header {
            padding-top: 0;
        }

        /* Mobile Responsive */
        @media (max-width: 1200px) {
            .navbar-container {
                max-width: 100%;
                padding: 0 1rem;
            }
            
            .nav-container {
                gap: 1rem;
                margin-left: 2rem;
            }
            
            .search-container {
                max-width: 300px;
                margin: 0 1rem;
            }
        }

        @media (max-width: 992px) {
            .main-nav,
            .search-container {
                display: none;
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .nav-container {
                margin-left: 1rem;
            }
        }

        @media (max-width: 768px) {
            .navbar-container {
                height: 64px;
                padding: 0 1rem;
            }
            
            .navbar-brand {
                font-size: 1.3rem;
            }
            
            .brand-icon {
                width: 36px;
                height: 36px;
                font-size: 1.1rem;
            }
            
            .page-header {
                padding: 2rem 0 1.5rem;
                margin-top: 64px;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }
            
            .main-content {
                padding-top: 64px;
            }
        }

        /* Mobile Collapse Menu */
        .mobile-menu {
            position: fixed;
            top: 72px;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border-bottom: 1px solid var(--gray-200);
            box-shadow: var(--shadow-lg);
            padding: 1.5rem;
            transform: translateY(-100%);
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 1000;
        }

        .mobile-menu.show {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }

        .mobile-menu .nav-link {
            color: var(--gray-700);
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            transition: var(--transition-fast);
        }

        .mobile-menu .nav-link:hover {
            color: var(--primary-600);
        }

        .mobile-menu .nav-link.active {
            color: var(--primary-700);
            font-weight: 600;
        }

        .mobile-search {
            margin: 1rem 0;
        }

        .mobile-search .search-input {
            width: 100%;
            margin-bottom: 1rem;
        }

        /* Scroll to Top */
        .scroll-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: var(--transition);
            z-index: 1000;
            box-shadow: var(--shadow-lg);
        }

        .scroll-top.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .scroll-top:hover {
            background: var(--gradient-secondary);
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }
    </style>
</head>
<body>
    <!-- Professional Navigation Bar -->
    <nav class="main-navbar" id="mainNavbar">
        <div class="navbar-container">
            <!-- Brand -->
            <a href="<?php echo SITE_URL; ?>" class="navbar-brand">
                <div class="brand-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <span>E-Learning</span>
            </a>

            <!-- Navigation Container -->
            <div class="nav-container">
                <!-- Main Navigation -->
                <ul class="main-nav">
                    <li class="nav-item">
                        <a class="nav-link <?php echo isCurrentPage('index') ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>">
                            <i class="fas fa-home"></i>
                            <span>Trang chủ</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isCurrentPage('courses') ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>/courses.php">
                            <i class="fas fa-book"></i>
                            <span>Khóa học</span>
                        </a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isCurrentPage('dashboard') ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isCurrentPage('my-courses') ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>/my-courses.php">
                            <i class="fas fa-book-open"></i>
                            <span>Khóa học của tôi</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                </ul>

                <!-- Professional Search Bar -->

            </div>

            <!-- User Menu -->
            <div class="user-menu">
                <?php if (isLoggedIn()): ?>
                <!-- User Dropdown -->
                <div class="user-dropdown">
                    <div class="user-toggle" onclick="toggleDropdown('userDropdown')">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($current_user['username'], 0, 2)); ?>
                        </div>
                        <span class="d-none d-lg-inline"><?php echo htmlspecialchars($current_user['username']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="dropdown-menu" id="userDropdown">
                        <div class="dropdown-header">
                            Tài khoản của tôi
                        </div>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/profile.php">
                            <i class="fas fa-user-circle"></i>
                            <span>Hồ sơ cá nhân</span>
                        </a>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/my-courses.php">
                            <i class="fas fa-book-open"></i>
                            <span>Khóa học của tôi</span>
                        </a>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/certificates.php">
                            <i class="fas fa-certificate"></i>
                            <span>Chứng chỉ</span>
                        </a>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/settings.php">
                            <i class="fas fa-cog"></i>
                            <span>Cài đặt</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item danger" href="<?php echo SITE_URL; ?>/logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Đăng xuất</span>
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <!-- Authentication Buttons -->
                <div class="auth-buttons">
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Đăng nhập</span>
                    </a>
                    <a href="<?php echo SITE_URL; ?>/register.php" class="btn-register">
                        <i class="fas fa-user-plus"></i>
                        <span>Đăng ký</span>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Mobile Toggle -->
                <button class="mobile-toggle" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div class="mobile-menu" id="mobileMenu">
            <!-- Mobile Search -->
            <div class="mobile-search">
                <form action="<?php echo SITE_URL; ?>/search.php" method="GET">
                    <input type="text" 
                           class="search-input" 
                           name="q" 
                           placeholder="Tìm kiếm khóa học..."
                           value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                </form>
            </div>

            <!-- Mobile Navigation -->
            <a class="nav-link <?php echo isCurrentPage('index') ? 'active' : ''; ?>" 
               href="<?php echo SITE_URL; ?>">
                <i class="fas fa-home"></i>
                <span>Trang chủ</span>
            </a>
            <a class="nav-link <?php echo isCurrentPage('courses') ? 'active' : ''; ?>" 
               href="<?php echo SITE_URL; ?>/courses.php">
                <i class="fas fa-book"></i>
                <span>Khóa học</span>
            </a>
            <?php if (isLoggedIn()): ?>
            <a class="nav-link <?php echo isCurrentPage('dashboard') ? 'active' : ''; ?>" 
               href="<?php echo SITE_URL; ?>/dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a class="nav-link <?php echo isCurrentPage('my-courses') ? 'active' : ''; ?>" 
               href="<?php echo SITE_URL; ?>/my-courses.php">
                <i class="fas fa-book-open"></i>
                <span>Khóa học của tôi</span>
            </a>
            <a class="nav-link" href="<?php echo SITE_URL; ?>/profile.php">
                <i class="fas fa-user-circle"></i>
                <span>Hồ sơ cá nhân</span>
            </a>
            <a class="nav-link" href="<?php echo SITE_URL; ?>/settings.php">
                <i class="fas fa-cog"></i>
                <span>Cài đặt</span>
            </a>
            <a class="nav-link danger" href="<?php echo SITE_URL; ?>/logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Đăng xuất</span>
            </a>
            <?php else: ?>
            <a class="nav-link" href="<?php echo SITE_URL; ?>/login.php">
                <i class="fas fa-sign-in-alt"></i>
                <span>Đăng nhập</span>
            </a>
            <a class="nav-link" href="<?php echo SITE_URL; ?>/register.php">
                <i class="fas fa-user-plus"></i>
                <span>Đăng ký</span>
            </a>
            <?php endif; ?>
            <a class="nav-link" href="<?php echo SITE_URL; ?>/about.php">
                <i class="fas fa-info-circle"></i>
                <span>Giới thiệu</span>
            </a>
            <a class="nav-link" href="<?php echo SITE_URL; ?>/contact.php">
                <i class="fas fa-envelope"></i>
                <span>Liên hệ</span>
            </a>
        </div>
    </nav>

    <!-- Page Header (nếu có) -->
    <?php if ($show_page_header): ?>
    <div class="page-header <?php echo $page_header_class; ?>">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1><?php echo htmlspecialchars($page_header_title); ?></h1>
                    <?php if ($page_header_subtitle): ?>
                    <p><?php echo htmlspecialchars($page_header_subtitle); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Start -->
    <main class="main-content <?php echo $show_page_header ? 'with-page-header' : ''; ?>">

    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scrollTop" onclick="scrollToTop()">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // User dropdown toggle
        function toggleDropdown(id) {
            const dropdown = document.getElementById(id);
            dropdown.classList.toggle('show');
        }

        // Mobile menu toggle
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('show');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-dropdown')) {
                document.getElementById('userDropdown')?.classList.remove('show');
            }
            if (!e.target.closest('.mobile-toggle') && !e.target.closest('.mobile-menu')) {
                document.getElementById('mobileMenu')?.classList.remove('show');
            }
        });

        // Enhanced navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('mainNavbar');
            const scrollTop = document.getElementById('scrollTop');
            
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }

            if (window.scrollY > 300) {
                scrollTop.classList.add('show');
            } else {
                scrollTop.classList.remove('show');
            }
        });

        // Scroll to top function
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // Auto-close mobile menu on link click
        document.querySelectorAll('.mobile-menu .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                document.getElementById('mobileMenu').classList.remove('show');
            });
        });

        // Search input focus effect
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            searchInput.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        }

        console.log('✅ Professional Header with Search loaded successfully!');
    </script>