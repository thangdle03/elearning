
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Meta Tags -->
    <meta name="description" content="Nền tảng học trực tuyến hàng đầu với các khóa học lập trình chất lượng cao">
    <meta name="keywords" content="e-learning, học trực tuyến, lập trình, courses, học online">
    <meta name="author" content="E-Learning Platform">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.ico">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Enhanced Navigation with Light Theme -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <!-- Brand Logo -->
            <a class="navbar-brand d-flex align-items-center" href="<?php echo SITE_URL; ?>">
                <div class="brand-logo me-3">
                    <div class="logo-circle">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                </div>
                <div class="brand-text">
                    <div class="brand-name"><?php echo SITE_NAME; ?></div>
                    <div class="brand-tagline">E-Learning Platform</div>
                </div>
            </a>
            
            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon-custom">
                    <span class="line"></span>
                    <span class="line"></span>
                    <span class="line"></span>
                </span>
            </button>
            
            <!-- Navigation Menu -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Main Navigation -->
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>">
                            <i class="fas fa-home me-2"></i>Trang chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern <?php echo basename($_SERVER['PHP_SELF']) === 'courses.php' ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>/courses.php">
                            <i class="fas fa-book-open me-2"></i>Khóa học
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern" href="<?php echo SITE_URL; ?>/about.php">
                            <i class="fas fa-info-circle me-2"></i>Giới thiệu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-modern" href="<?php echo SITE_URL; ?>/contact.php">
                            <i class="fas fa-envelope me-2"></i>Liên hệ
                        </a>
                    </li>
                </ul>
                
                <!-- User Actions -->
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <!-- Search Button -->
                        <li class="nav-item me-2">
                            <button class="btn btn-outline-primary btn-sm rounded-pill px-3" 
                                    data-bs-toggle="modal" data-bs-target="#searchModal">
                                <i class="fas fa-search"></i>
                            </button>
                        </li>
                        
                        <!-- Notifications -->
                        <li class="nav-item dropdown me-2">
                            <a class="nav-link position-relative p-2 notification-bell" 
                               href="#" 
                               id="notificationDropdown" 
                               role="button" 
                               data-bs-toggle="dropdown" 
                               aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <span class="notification-badge">3</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                                <li class="dropdown-header">
                                    <i class="fas fa-bell me-2 text-primary"></i>Thông báo
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item notification-item" href="#">
                                    <div class="notification-content">
                                        <div class="notification-title">Khóa học mới</div>
                                        <div class="notification-text">Khóa học React Native đã được cập nhật</div>
                                        <div class="notification-time">5 phút trước</div>
                                    </div>
                                </a></li>
                                <li><a class="dropdown-item notification-item" href="#">
                                    <div class="notification-content">
                                        <div class="notification-title">Bài tập mới</div>
                                        <div class="notification-text">Bạn có bài tập mới cần hoàn thành</div>
                                        <div class="notification-time">1 giờ trước</div>
                                    </div>
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center small" href="#">Xem tất cả</a></li>
                            </ul>
                        </li>
                        
                        <!-- User Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle user-dropdown d-flex align-items-center" 
                               href="#" 
                               id="userDropdown" 
                               role="button" 
                               data-bs-toggle="dropdown" 
                               aria-expanded="false">
                                <div class="user-avatar me-2">
                                    <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                                </div>
                                <div class="user-info d-none d-md-block">
                                    <div class="user-name"><?php echo $_SESSION['username']; ?></div>
                                    <div class="user-role"><?php echo isAdmin() ? 'Quản trị viên' : 'Học viên'; ?></div>
                                </div>
                                <i class="fas fa-chevron-down ms-2 dropdown-arrow"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end user-menu" aria-labelledby="userDropdown">
                                <li class="dropdown-header">
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar-large me-3">
                                            <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name"><?php echo $_SESSION['username']; ?></div>
                                            <div class="user-email"><?php echo $_SESSION['email'] ?? 'user@example.com'; ?></div>
                                        </div>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                
                                <?php if (!isAdmin()): ?>
                                <li><a class="dropdown-item menu-item" href="<?php echo SITE_URL; ?>/dashboard/">
                                    <i class="fas fa-tachometer-alt me-3 text-primary"></i>Dashboard
                                </a></li>
                                <li><a class="dropdown-item menu-item" href="<?php echo SITE_URL; ?>/my-courses.php">
                                    <i class="fas fa-book me-3 text-success"></i>Khóa học của tôi
                                </a></li>
                                <li><a class="dropdown-item menu-item" href="<?php echo SITE_URL; ?>/progress.php">
                                    <i class="fas fa-chart-line me-3 text-info"></i>Tiến độ học tập
                                </a></li>
                                <li><a class="dropdown-item menu-item" href="<?php echo SITE_URL; ?>/certificates.php">
                                    <i class="fas fa-certificate me-3 text-warning"></i>Chứng chỉ
                                </a></li>
                                <?php endif; ?>
                                
                                <li><a class="dropdown-item menu-item" href="<?php echo SITE_URL; ?>/profile.php">
                                    <i class="fas fa-user me-3 text-secondary"></i>Hồ sơ cá nhân
                                </a></li>
                                <li><a class="dropdown-item menu-item" href="<?php echo SITE_URL; ?>/settings.php">
                                    <i class="fas fa-cog me-3 text-muted"></i>Cài đặt
                                </a></li>
                                
                                <?php if (isAdmin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li class="dropdown-header text-muted small">
                                        <i class="fas fa-shield-alt me-2"></i>QUẢN TRỊ
                                    </li>
                                    <li><a class="dropdown-item menu-item admin-item" href="<?php echo SITE_URL; ?>/admin/">
                                        <i class="fas fa-shield-alt me-3"></i>Bảng điều khiển
                                    </a></li>
                                    <li><a class="dropdown-item menu-item admin-item" href="<?php echo SITE_URL; ?>/admin/courses.php">
                                        <i class="fas fa-graduation-cap me-3"></i>Quản lý khóa học
                                    </a></li>
                                    <li><a class="dropdown-item menu-item admin-item" href="<?php echo SITE_URL; ?>/admin/users.php">
                                        <i class="fas fa-users me-3"></i>Quản lý người dùng
                                    </a></li>
                                <?php endif; ?>
                                
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item menu-item text-danger" href="<?php echo SITE_URL; ?>/logout.php">
                                    <i class="fas fa-sign-out-alt me-3"></i>Đăng xuất
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Guest Actions -->
                        <li class="nav-item me-2">
                            <button class="btn btn-outline-primary btn-sm rounded-pill px-3" 
                                    data-bs-toggle="modal" data-bs-target="#searchModal">
                                <i class="fas fa-search me-1"></i>Tìm kiếm
                            </button>
                        </li>
                        <li class="nav-item me-2">
                            <a class="btn btn-ghost" href="<?php echo SITE_URL; ?>/login.php">
                                <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary rounded-pill px-4" href="<?php echo SITE_URL; ?>/register.php">
                                <i class="fas fa-user-plus me-2"></i>Đăng ký
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Search Modal -->
    <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="searchModalLabel">
                        <i class="fas fa-search me-2 text-primary"></i>Tìm kiếm khóa học
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="<?php echo SITE_URL; ?>/courses.php" method="GET">
                        <div class="search-container">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-search text-primary"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" 
                                       placeholder="Nhập tên khóa học, chủ đề..." 
                                       autocomplete="off" id="searchInput">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-search me-2"></i>Tìm kiếm
                                </button>
                            </div>
                        </div>
                        
                        <!-- Quick Categories -->
                        <div class="mt-4">
                            <h6 class="text-muted mb-3">
                                <i class="fas fa-fire me-2"></i>Danh mục phổ biến:
                            </h6>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?php echo SITE_URL; ?>/courses.php?category=1" 
                                   class="badge bg-primary bg-opacity-10 text-primary text-decoration-none px-3 py-2 rounded-pill">
                                    <i class="fab fa-html5 me-1"></i>Frontend
                                </a>
                                <a href="<?php echo SITE_URL; ?>/courses.php?category=2" 
                                   class="badge bg-success bg-opacity-10 text-success text-decoration-none px-3 py-2 rounded-pill">
                                    <i class="fas fa-server me-1"></i>Backend
                                </a>
                                <a href="<?php echo SITE_URL; ?>/courses.php?category=3" 
                                   class="badge bg-info bg-opacity-10 text-info text-decoration-none px-3 py-2 rounded-pill">
                                    <i class="fas fa-mobile-alt me-1"></i>Mobile
                                </a>
                                <a href="<?php echo SITE_URL; ?>/courses.php?category=4" 
                                   class="badge bg-warning bg-opacity-10 text-warning text-decoration-none px-3 py-2 rounded-pill">
                                    <i class="fas fa-database me-1"></i>Database
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="loading-text mt-3">Đang tải...</div>
        </div>
    </div>

    <!-- Main Content Container -->
    <main class="main-content">

<!-- Enhanced Header Styles with Better Contrast -->
<style>
/* Base Styles */
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    line-height: 1.6;
    background-color: #f8fafc;
}

/* Enhanced Navbar with White Background */
.navbar {
    background-color: #ffffff !important;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    padding: 1.2rem 0;
    transition: all 0.3s ease;
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
}

.navbar.scrolled {
    padding: 0.8rem 0;
    box-shadow: 0 2px 25px rgba(0, 0, 0, 0.15);
}

/* Brand Logo with Better Contrast */
.navbar-brand {
    font-weight: 700;
    font-size: 1.5rem;
    color: #1a202c !important;
    text-decoration: none;
    transition: all 0.3s ease;
}

.navbar-brand:hover {
    color: #2d3748 !important;
}

.logo-circle {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.6rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(66, 153, 225, 0.4);
}

.navbar-brand:hover .logo-circle {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(66, 153, 225, 0.5);
}

.brand-name {
    font-weight: 700;
    font-size: 1.3rem;
    color: #1a202c;
    font-family: 'Poppins', sans-serif;
}

.brand-tagline {
    font-size: 0.8rem;
    color: #4a5568;
    font-weight: 500;
}

/* Modern Navigation Links with High Contrast */
.nav-link-modern {
    font-weight: 600;
    color: #2d3748 !important;
    padding: 0.8rem 1.2rem !important;
    border-radius: 10px;
    transition: all 0.3s ease;
    position: relative;
    margin: 0 0.2rem;
    font-size: 0.95rem;
}

.nav-link-modern:hover {
    background-color: rgba(66, 153, 225, 0.1);
    color: #2b6cb0 !important;
    transform: translateY(-1px);
}

.nav-link-modern.active {
    background-color: rgba(66, 153, 225, 0.15);
    color: #2b6cb0 !important;
    font-weight: 700;
}

.nav-link-modern.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    width: 6px;
    height: 6px;
    background: #4299e1;
    border-radius: 50%;
}

/* Custom Mobile Toggle with Better Visibility */
.navbar-toggler {
    padding: 0.6rem;
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}

.navbar-toggler-icon-custom {
    display: flex;
    flex-direction: column;
    width: 26px;
    height: 20px;
    justify-content: space-between;
}

.navbar-toggler-icon-custom .line {
    height: 3px;
    background-color: #2d3748;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.navbar-toggler:not(.collapsed) .navbar-toggler-icon-custom .line:nth-child(1) {
    transform: rotate(45deg) translate(6px, 6px);
}

.navbar-toggler:not(.collapsed) .navbar-toggler-icon-custom .line:nth-child(2) {
    opacity: 0;
}

.navbar-toggler:not(.collapsed) .navbar-toggler-icon-custom .line:nth-child(3) {
    transform: rotate(-45deg) translate(6px, -6px);
}

/* User Avatar with Better Contrast */
.user-avatar {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.9rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 2px 10px rgba(66, 153, 225, 0.3);
}

.user-avatar-large {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.2rem;
    box-shadow: 0 4px 15px rgba(66, 153, 225, 0.3);
}

.user-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: #1a202c;
}

.user-role {
    font-size: 0.75rem;
    color: #4a5568;
}

.user-details .user-name {
    font-weight: 700;
    color: #1a202c;
    margin-bottom: 0.25rem;
}

.user-details .user-email {
    font-size: 0.8rem;
    color: #4a5568;
}

/* User Dropdown Enhancement */
.user-dropdown {
    color: #2d3748 !important;
    text-decoration: none;
    padding: 0.5rem 0.8rem !important;
    border-radius: 12px;
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
}

.user-dropdown:hover {
    background-color: rgba(66, 153, 225, 0.1);
    color: #2b6cb0 !important;
    border-color: rgba(66, 153, 225, 0.2);
    transform: translateY(-1px);
}

.dropdown-arrow {
    font-size: 0.8rem;
    transition: transform 0.3s ease;
    color: #4a5568;
}

.dropdown.show .dropdown-arrow {
    transform: rotate(180deg);
}

/* Notification Bell */
.notification-bell {
    color: #2d3748 !important;
    border-radius: 50%;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    position: relative;
    border: 2px solid transparent;
}

.notification-bell:hover {
    background-color: rgba(66, 153, 225, 0.1);
    color: #2b6cb0 !important;
    border-color: rgba(66, 153, 225, 0.2);
    transform: scale(1.05);
}

.notification-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    border: 2px solid white;
    box-shadow: 0 2px 6px rgba(229, 62, 62, 0.3);
}

/* Enhanced Dropdown Menus */
.dropdown-menu {
    border: none;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    border-radius: 16px;
    padding: 0.75rem 0;
    margin-top: 0.5rem;
    background: white;
    min-width: 220px;
}

.dropdown-header {
    padding: 1rem 1.5rem 0.5rem;
    font-weight: 700;
    color: #1a202c;
    font-size: 0.9rem;
}

.menu-item {
    padding: 0.8rem 1.5rem;
    font-weight: 500;
    color: #2d3748;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    text-decoration: none;
}

.menu-item:hover {
    background-color: rgba(66, 153, 225, 0.1);
    color: #2b6cb0;
    transform: translateX(5px);
}

.menu-item i {
    width: 20px;
    text-align: center;
    opacity: 0.8;
}

.admin-item {
    color: #e53e3e !important;
}

.admin-item:hover {
    background-color: rgba(229, 62, 62, 0.1) !important;
    color: #c53030 !important;
}

/* Enhanced User Menu */
.user-menu {
    min-width: 280px;
    border: none;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    border-radius: 16px;
    padding: 0;
    margin-top: 0.75rem;
    background: white;
    overflow: hidden;
}

.user-menu .dropdown-header {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    margin: 0;
}

.user-menu .dropdown-divider {
    margin: 0;
    border-color: #e2e8f0;
}

.user-menu .menu-item {
    padding: 1rem 1.5rem;
    font-weight: 500;
    color: #2d3748;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    border: none;
    background: transparent;
    text-decoration: none;
}

.user-menu .menu-item:hover {
    background-color: rgba(66, 153, 225, 0.08);
    color: #2b6cb0;
    transform: translateX(8px);
    border-left: 3px solid #4299e1;
    padding-left: 1.3rem;
}

.user-menu .menu-item i {
    width: 22px;
    text-align: center;
    opacity: 0.8;
    font-size: 0.9rem;
}

/* Admin Items Special Styling */
.user-menu .admin-item {
    color: #e53e3e !important;
    font-weight: 600;
}

.user-menu .admin-item:hover {
    background-color: rgba(229, 62, 62, 0.08) !important;
    color: #c53030 !important;
    border-left-color: #e53e3e !important;
}

.user-menu .admin-item i {
    color: #e53e3e;
}

/* Notification Dropdown */
.notification-dropdown {
    width: 360px;
    max-height: 420px;
    overflow-y: auto;
}

.notification-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    text-decoration: none;
}

.notification-item:hover {
    background-color: rgba(66, 153, 225, 0.05);
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-title {
    font-weight: 600;
    color: #1a202c;
    font-size: 0.9rem;
}

.notification-text {
    color: #4a5568;
    font-size: 0.8rem;
    margin: 0.25rem 0;
    line-height: 1.4;
}

.notification-time {
    color: #718096;
    font-size: 0.75rem;
}

/* Enhanced Buttons */
.btn-ghost {
    background: transparent;
    border: none;
    color: #2d3748;
    font-weight: 600;
    padding: 0.8rem 1.2rem;
    border-radius: 10px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-ghost:hover {
    background-color: rgba(66, 153, 225, 0.1);
    color: #2b6cb0;
    transform: translateY(-1px);
}

.btn-primary {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    border: none;
    font-weight: 600;
    padding: 0.8rem 1.6rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(66, 153, 225, 0.4);
    border-radius: 10px;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(66, 153, 225, 0.5);
    background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
}

.btn-outline-primary {
    border: 2px solid #4299e1;
    color: #2b6cb0;
    background: transparent;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-outline-primary:hover {
    background: #4299e1;
    border-color: #4299e1;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(66, 153, 225, 0.4);
}

/* Search Modal */
.modal-content {
    border-radius: 16px;
    border: none;
}

.search-container .input-group-text {
    background: #f7fafc;
    border: 2px solid #e2e8f0;
    border-right: none;
}

.search-container .form-control {
    border: 2px solid #e2e8f0;
    border-left: none;
    font-size: 1.1rem;
    padding: 1rem;
    color: #1a202c;
}

.search-container .form-control:focus {
    border-color: #4299e1;
    box-shadow: 0 0 0 0.2rem rgba(66, 153, 225, 0.25);
    color: #1a202c;
}

.search-container .input-group-text:focus-within {
    border-color: #4299e1;
}

.search-container .form-control::placeholder {
    color: #a0aec0;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}

.loading-text {
    color: #2d3748;
    font-weight: 600;
    font-size: 1.1rem;
}

/* Responsive Design */
@media (max-width: 991.98px) {
    .navbar {
        padding: 1rem 0;
    }
    
    .navbar-nav {
        padding: 1rem 0;
        background: rgba(248, 250, 252, 0.95);
        border-radius: 12px;
        margin-top: 1rem;
    }
    
    .nav-link-modern {
        margin: 0.3rem 0;
        border-radius: 8px;
    }
    
    .notification-dropdown {
        width: 300px;
    }
    
    .user-menu {
        min-width: 260px;
        margin-top: 0.5rem;
    }
    
    .user-dropdown {
        padding: 0.6rem !important;
    }
    
    .dropdown-arrow {
        display: none;
    }
}

@media (max-width: 767.98px) {
    .navbar {
        padding: 0.8rem 0;
    }
    
    .brand-name {
        font-size: 1.1rem;
    }
    
    .brand-tagline {
        font-size: 0.7rem;
    }
    
    .logo-circle {
        width: 44px;
        height: 44px;
        font-size: 1.3rem;
    }
    
    .user-info {
        display: none !important;
    }
    
    .notification-dropdown {
        width: 280px;
    }
    
    .user-menu {
        min-width: 240px;
        position: fixed !important;
        top: auto !important;
        left: 10px !important;
        right: 10px !important;
        transform: none !important;
        margin: 0;
        max-height: 80vh;
        overflow-y: auto;
    }
    
    .user-menu .dropdown-header {
        padding: 1rem;
    }
    
    .user-menu .menu-item {
        padding: 0.8rem 1rem;
        font-size: 0.85rem;
    }
    
    .user-menu .menu-item:hover {
        transform: translateX(5px);
        padding-left: 1.2rem;
    }
}

/* Focus States for Accessibility */
.nav-link-modern:focus,
.btn:focus,
.navbar-toggler:focus {
    outline: 2px solid #4299e1;
    outline-offset: 2px;
}

.user-dropdown:focus {
    outline: 2px solid #4299e1;
    outline-offset: 2px;
    box-shadow: 0 0 0 0.2rem rgba(66, 153, 225, 0.25);
}

.notification-bell:focus {
    outline: 2px solid #4299e1;
    outline-offset: 2px;
}

/* Custom Scrollbar */
.notification-dropdown::-webkit-scrollbar {
    width: 6px;
}

.notification-dropdown::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

.notification-dropdown::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 3px;
}

.notification-dropdown::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

/* Animations */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-menu {
    animation: fadeInDown 0.3s ease;
}

/* High Contrast Mode Support */
@media (prefers-contrast: high) {
    .navbar {
        border-bottom: 2px solid #000;
    }
    
    .nav-link-modern {
        color: #000 !important;
    }
    
    .btn-primary {
        background: #000;
        border-color: #000;
    }
}
</style>

<!-- Bootstrap 5 JavaScript (Load first) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Simplified Header JavaScript - Load after Bootstrap -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Header loading...');
    
    // Navbar scroll effect
    const navbar = document.querySelector('.navbar');
    let lastScrollTop = 0;
    
    function handleNavbarScroll() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
    }
    
    window.addEventListener('scroll', handleNavbarScroll, { passive: true });
    
    // Search modal functionality
    const searchModal = document.getElementById('searchModal');
    if (searchModal) {
        searchModal.addEventListener('shown.bs.modal', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
        });
    }
    
    // Loading overlay functions
    window.showLoading = function() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'flex';
        }
    };
    
    window.hideLoading = function() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    };
    
    // Auto-hide loading on page load
    window.addEventListener('load', function() {
        setTimeout(function() {
            if (window.hideLoading) {
                window.hideLoading();
            }
        }, 100);
    });
    
    // Debug: Test dropdown elements
    const userDropdown = document.getElementById('userDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (userDropdown) {
        console.log('✅ User dropdown found');
        userDropdown.addEventListener('click', function(e) {
            console.log('🔍 User dropdown clicked');
        });
    } else {
        console.log('❌ User dropdown not found');
    }
    
    if (notificationDropdown) {
        console.log('✅ Notification dropdown found');
        notificationDropdown.addEventListener('click', function(e) {
            console.log('🔔 Notification dropdown clicked');
        });
    } else {
        console.log('❌ Notification dropdown not found');
    }
    
    // Test Bootstrap dropdowns
    const dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]');
    console.log(`📊 Found ${dropdownElements.length} dropdown elements`);
    
    dropdownElements.forEach(function(element, index) {
        console.log(`Dropdown ${index + 1}:`, element.id || 'no-id', element.className);
        
        element.addEventListener('shown.bs.dropdown', function() {
            console.log(`✅ Dropdown ${index + 1} shown`);
        });
        
        element.addEventListener('hidden.bs.dropdown', function() {
            console.log(`❌ Dropdown ${index + 1} hidden`);
        });
        
        element.addEventListener('click', function(e) {
            console.log(`🖱️ Dropdown ${index + 1} clicked`);
        });
    });
    
    // Active page detection
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    const navLinks = document.querySelectorAll('.nav-link-modern');
    
    navLinks.forEach(function(link) {
        const href = link.getAttribute('href');
        if (href && href.includes(currentPage)) {
            link.classList.add('active');
        }
    });
    
    console.log('✅ Header loaded successfully!');
});
</script>