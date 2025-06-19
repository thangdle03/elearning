<?php
// filepath: d:\Xampp\htdocs\elearning\admin\includes\admin-header.php
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Panel'; ?> - <?php echo SITE_NAME ?? 'E-Learning'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom Admin CSS -->
    <style>
        :root {
            --admin-primary: #6366f1;
            --admin-primary-dark: #4f46e5;
            --admin-secondary: #8b5cf6;
            --admin-success: #10b981;
            --admin-warning: #f59e0b;
            --admin-danger: #ef4444;
            --admin-info: #06b6d4;
            --admin-dark: #1f2937;
            --admin-light: #f8fafc;
            --admin-muted: #64748b;
            --admin-border: #e2e8f0;
            --admin-sidebar-width: 280px;
            --admin-sidebar-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --admin-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --admin-shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--admin-light);
            color: var(--admin-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            background: var(--admin-light);
        }

        /* === SIDEBAR STYLES === */
        .admin-sidebar {
            width: var(--admin-sidebar-width);
            background: var(--admin-sidebar-bg);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1000;
            box-shadow: var(--admin-shadow-lg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .admin-sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .admin-sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .admin-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .sidebar-brand {
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .sidebar-brand::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {

            0%,
            100% {
                transform: scale(0) rotate(0deg);
                opacity: 0;
            }

            50% {
                transform: scale(1) rotate(180deg);
                opacity: 1;
            }
        }

        .sidebar-brand h4 {
            color: #fff;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
            position: relative;
        }

        .sidebar-brand small {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 400;
            font-size: 0.875rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-section {
            margin-bottom: 1.5rem;
        }

        .nav-section-title {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0 1.5rem 0.75rem;
            padding-left: 0.5rem;
        }

        .nav-item {
            margin: 0 1rem;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.875rem 1rem;
            border-radius: 0.75rem;
            margin: 0.125rem 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: all 0.5s;
        }

        .nav-link:hover::before {
            left: 100%;
        }

        .nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(4px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .nav-link.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.2);
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 12px;
            font-size: 1rem;
        }

        .nav-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 1rem 1.5rem;
        }

        /* === MAIN CONTENT === */
        .admin-content {
            flex: 1;
            margin-left: var(--admin-sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* === TOP HEADER === */
        .admin-topbar {
            background: #fff;
            backdrop-filter: blur(10px);
            box-shadow: var(--admin-shadow);
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--admin-border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-section h5 {
            color: var(--admin-dark);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .welcome-section small {
            color: var(--admin-muted);
            font-weight: 400;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .time-display {
            background: var(--admin-light);
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid var(--admin-border);
            color: var(--admin-muted);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .time-display i {
            color: var(--admin-primary);
            margin-right: 0.5rem;
        }

        .btn-topbar {
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
            font-weight: 500;
            font-size: 0.875rem;
            border: 1px solid transparent;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-outline-primary {
            color: var(--admin-primary);
            border-color: var(--admin-primary);
        }

        .btn-outline-primary:hover {
            background: var(--admin-primary);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        .btn-outline-danger {
            color: var(--admin-danger);
            border-color: var(--admin-danger);
        }

        .btn-outline-danger:hover {
            background: var(--admin-danger);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }

        /* === PAGE CONTENT === */
        .admin-page-content {
            flex: 1;
            padding: 2rem;
            background: var(--admin-light);
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            color: var(--admin-dark);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--admin-muted);
            font-size: 1rem;
        }

        /* === CARDS === */
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--admin-shadow);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--admin-shadow-lg);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid var(--admin-border);
            font-weight: 600;
            padding: 1.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* === ALERTS === */
        .alert {
            border: none;
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff;
        }

        /* === BUTTONS === */
        .btn {
            border-radius: 0.75rem;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        /* === MOBILE RESPONSIVE === */
        @media (max-width: 768px) {
            .admin-sidebar {
                margin-left: calc(-1 * var(--admin-sidebar-width));
                transition: margin-left 0.3s ease;
            }

            .admin-sidebar.show {
                margin-left: 0;
            }

            .admin-content {
                margin-left: 0;
            }

            .admin-topbar {
                padding: 1rem;
            }

            .admin-page-content {
                padding: 1rem;
            }

            .topbar-content {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .topbar-actions {
                width: 100%;
                justify-content: space-between;
            }

            .time-display {
                display: none;
            }
        }

        /* === LOADING ANIMATION === */
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* === MOBILE MENU OVERLAY === */
        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .mobile-overlay.show {
            display: block;
            opacity: 1;
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

        <!-- Sidebar -->
        <nav class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-brand">
                <h4>
                    <i class="fas fa-graduation-cap me-2"></i>
                    Admin Panel
                </h4>
                <small>Quản lý E-Learning</small>
            </div>

            <div class="sidebar-nav">
                <!-- Main Navigation -->
                <div class="nav-section">
                    <div class="nav-section-title">Tổng quan</div>
                    <div class="nav-item">
                        <a class="nav-link <?php echo ($current_page ?? '') === 'dashboard' ? 'active' : ''; ?>"
                            href="<?php echo SITE_URL; ?>/admin/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </div>
                </div>

                <!-- Course Management -->
                <div class="nav-section">
                    <div class="nav-section-title">Quản lý khóa học</div>
                    <div class="nav-item">
                        <a class="nav-link <?php echo ($current_page ?? '') === 'courses' ? 'active' : ''; ?>"
                            href="<?php echo SITE_URL; ?>/admin/courses.php">
                            <i class="fas fa-book"></i>
                            Danh sách khóa học
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link <?php echo ($current_page ?? '') === 'add-course' ? 'active' : ''; ?>"
                            href="<?php echo SITE_URL; ?>/admin/add-course.php">
                            <i class="fas fa-plus-circle"></i>
                            Thêm khóa học
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link <?php echo ($current_page ?? '') === 'categories' ? 'active' : ''; ?>"
                            href="<?php echo SITE_URL; ?>/admin/categories.php">
                            <i class="fas fa-tags"></i>
                            Danh mục
                        </a>
                    </div>
                </div>

                <!-- Lesson Management -->


                <!-- User Management -->
                <div class="nav-section">
                    <div class="nav-section-title">Quản lý người dùng</div>
                    <div class="nav-item">
                        <a class="nav-link <?php echo ($current_page ?? '') === 'users' ? 'active' : ''; ?>"
                            href="<?php echo SITE_URL; ?>/admin/users.php">
                            <i class="fas fa-users"></i>
                            Danh sách người dùng
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link <?php echo ($current_page ?? '') === 'enrollments' ? 'active' : ''; ?>"
                            href="<?php echo SITE_URL; ?>/admin/enrollments.php">
                            <i class="fas fa-user-graduate"></i>
                            Đăng ký học
                        </a>
                    </div>
                </div>

                <!-- Reports -->
                <div class="nav-section">
                    <div class="nav-section-title">Báo cáo</div>
                    <div class="nav-item">
                        <a class="nav-link <?php echo ($current_page ?? '') === 'reports' ? 'active' : ''; ?>"
                            href="<?php echo SITE_URL; ?>/admin/reports.php">
                            <i class="fas fa-chart-bar"></i>
                            Thống kê & Báo cáo
                        </a>
                    </div>
                </div>

                <!-- Reviews -->
                <div class="nav-section">
                    <div class="nav-section-title">Đánh giá</div>
                    <div class="nav-item">
                        <a class="nav-link <?php echo ($current_page ?? '') === 'reviews' ? 'active' : ''; ?>"
                            href="<?php echo SITE_URL; ?>/admin/reviews.php">
                            <i class="fas fa-star"></i>
                            Quản lý Đánh giá
                        </a>
                    </div>
                </div>

                <div class="nav-divider"></div>

                <!-- Quick Actions -->
                <div class="nav-section">
                    <div class="nav-section-title">Liên kết nhanh</div>
                    
                    <div class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/profile.php">
                            <i class="fas fa-user-circle"></i>
                            Hồ sơ cá nhân
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/logout.php"
                            onclick="return confirm('Bạn có chắc chắn muốn đăng xuất?')">
                            <i class="fas fa-sign-out-alt"></i>
                            Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="admin-content">
            <!-- Top Header -->
            <div class="admin-topbar">
                <div class="topbar-content">
                    <div class="welcome-section">
                        <button class="btn btn-link d-md-none p-0 me-3" onclick="toggleSidebar()">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h5>Chào mừng trở lại!</h5>
                        <small>Xin chào <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></small>
                    </div>

                    <div class="topbar-actions">
                        <!-- Current Time -->
                        <div class="time-display d-none d-lg-flex align-items-center">
                            <i class="fas fa-clock"></i>
                            <span id="current-time"><?php echo date('H:i - d/m/Y'); ?></span>
                        </div>

                        <!-- Quick Actions -->
                        <div class="d-flex gap-2">
                            <a href="<?php echo SITE_URL; ?>/" target="_blank" class="btn-topbar btn-outline-primary">
                                <i class="fas fa-eye"></i>
                                <span class="d-none d-sm-inline">Xem trang web</span>
                            </a>
                            <a href="<?php echo SITE_URL; ?>/logout.php" class="btn-topbar btn-outline-danger"
                                onclick="return confirm('Đăng xuất khỏi Admin Panel?')">
                                <i class="fas fa-sign-out-alt"></i>
                                <span class="d-none d-sm-inline">Đăng xuất</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Content -->
            <div class="admin-page-content">
                <!-- Success/Error Messages -->
                <?php if (isset($message) && $message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Page Title -->
                <?php if (isset($page_title)): ?>
                <div class="page-header">
                    <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
                    <?php if (isset($page_subtitle)): ?>
                    <p class="page-subtitle"><?php echo htmlspecialchars($page_subtitle); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Bootstrap JS -->
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

                <!-- Custom JS -->
                <script>
                    // Toggle Sidebar for Mobile
                    function toggleSidebar() {
                        const sidebar = document.getElementById('adminSidebar');
                        const overlay = document.getElementById('mobileOverlay');
                        sidebar.classList.toggle('show');
                        overlay.classList.toggle('show');
                    }

                    // Close sidebar when clicking outside
                    document.addEventListener('click', function (e) {
                        const sidebar = document.getElementById('adminSidebar');
                        const overlay = document.getElementById('mobileOverlay');
                        if (!sidebar.contains(e.target) && !overlay.contains(e.target)) {
                            sidebar.classList.remove('show');
                            overlay.classList.remove('show');
                        }
                    });

                    // Current Time Display
                    function updateTime() {
                        const now = new Date();
                        const options = { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' };
                        document.getElementById('current-time').textContent = now.toLocaleString('vi-VN', options);
                    }
                    setInterval(updateTime, 1000);

                    // Initial call to display time immediately on load
                    updateTime();
                </script>