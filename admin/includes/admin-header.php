<?php

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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom Admin CSS -->
    <style>
        :root {
            --admin-primary: #4e73df;
            --admin-primary-dark: #224abe;
            --admin-sidebar-bg: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            --admin-text-muted: #5a5c69;
            --admin-bg: #f8f9fc;
        }
        
        body { 
            background-color: var(--admin-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .admin-sidebar {
            width: 250px;
            background: var(--admin-sidebar-bg);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .admin-sidebar .sidebar-brand {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .admin-sidebar .sidebar-brand h4 {
            color: white;
            margin-bottom: 0;
            font-weight: 600;
        }
        
        .admin-sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 1rem 1.5rem;
            border-radius: 0.35rem;
            margin: 2px 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .admin-sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,0.1);
            transform: translateX(2px);
        }
        
        .admin-sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.2);
            font-weight: 600;
        }
        
        .admin-sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }
        
        /* Main Content */
        .admin-content {
            flex: 1;
            margin-left: 250px;
            display: flex;
            flex-direction: column;
        }
        
        /* Top Header */
        .admin-topbar {
            background: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 1rem 2rem;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .admin-topbar .welcome-text {
            color: var(--admin-text-muted);
        }
        
        .admin-topbar .user-name {
            color: var(--admin-primary);
            font-weight: 600;
        }
        
        /* Page Content */
        .admin-page-content {
            flex: 1;
            padding: 2rem;
            background: var(--admin-bg);
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .card-header {
            background: #fff;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
        }
        
        /* Buttons */
        .btn-primary {
            background-color: var(--admin-primary);
            border-color: var(--admin-primary);
        }
        
        .btn-primary:hover {
            background-color: var(--admin-primary-dark);
            border-color: var(--admin-primary-dark);
        }
        
        /* Alerts */
        .alert {
            border: none;
            border-radius: 0.5rem;
        }
        
        /* Tables */
        .table {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: #f8f9fc;
            border: none;
            font-weight: 600;
            color: var(--admin-text-muted);
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                margin-left: -250px;
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
        }
        
        /* Loading States */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    
    <!-- Page specific CSS -->
    <?php if (isset($page_css)): ?>
        <?php foreach ($page_css as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <nav class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-brand">
                <h4>
                    <i class="fas fa-graduation-cap me-2"></i>
                    Admin Panel
                </h4>
                <small class="text-white-50">Quản lý E-Learning</small>
            </div>
            
            <ul class="nav flex-column py-3">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page ?? '') === 'dashboard' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>/admin/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page ?? '') === 'courses' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>/admin/courses.php">
                        <i class="fas fa-graduation-cap"></i>
                        Quản lý khóa học
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page ?? '') === 'add-course' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>/admin/add-course.php">
                        <i class="fas fa-plus-circle"></i>
                        Thêm khóa học
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page ?? '') === 'users' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>/admin/users.php">
                        <i class="fas fa-users"></i>
                        Quản lý người dùng
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page ?? '') === 'categories' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>/admin/categories.php">
                        <i class="fas fa-tags"></i>
                        Danh mục
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page ?? '') === 'enrollments' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>/admin/enrollments.php">
                        <i class="fas fa-user-graduate"></i>
                        Đăng ký học
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page ?? '') === 'reports' ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>/admin/reports.php">
                        <i class="fas fa-chart-bar"></i>
                        Thống kê & Báo cáo
                    </a>
                </li>
                
                <!-- Divider -->
                <hr class="my-3" style="border-color: rgba(255,255,255,0.2); margin: 1rem 1.5rem;">
                
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>/" target="_blank">
                        <i class="fas fa-external-link-alt"></i>
                        Xem trang web
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>/profile.php">
                        <i class="fas fa-user-circle"></i>
                        Hồ sơ cá nhân
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning" href="<?php echo SITE_URL; ?>/logout.php" 
                       onclick="return confirm('Bạn có chắc chắn muốn đăng xuất?')">
                        <i class="fas fa-sign-out-alt"></i>
                        Đăng xuất
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Main Content -->
        <div class="admin-content">
            <!-- Top Header -->
            <div class="admin-topbar">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <button class="btn btn-link d-md-none p-0 me-3" onclick="toggleSidebar()">
                            <i class="fas fa-bars"></i>
                        </button>
                        <span class="welcome-text">Chào mừng trở lại, </span>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <!-- Current Time -->
                        <div class="d-none d-md-flex align-items-center text-muted small">
                            <i class="fas fa-clock me-2"></i>
                            <span id="current-time"><?php echo date('H:i - d/m/Y'); ?></span>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="btn-group" role="group">
                            <a href="<?php echo SITE_URL; ?>/" target="_blank" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i>
                                <span class="d-none d-sm-inline">Xem trang web</span>
                            </a>
                            <a href="<?php echo SITE_URL; ?>/logout.php" 
                               class="btn btn-outline-danger btn-sm"
                               onclick="return confirm('Đăng xuất khỏi Admin Panel?')">
                                <i class="fas fa-sign-out-alt me-1"></i>
                                <span class="d-none d-sm-inline">Đăng xuất</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Page Content -->
            <div class="admin-page-content">
                <!-- Alert Container -->
                <div class="alert-container mb-3"></div>
                
                <!-- Page Title -->
                <?php if (isset($page_title)): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <?php echo htmlspecialchars($page_title); ?>
                    </h1>
                    <?php if (isset($page_breadcrumb)): ?>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <?php foreach ($page_breadcrumb as $item): ?>
                                <?php if (isset($item['url'])): ?>
                                    <li class="breadcrumb-item">
                                        <a href="<?php echo $item['url']; ?>"><?php echo $item['title']; ?></a>
                                    </li>
                                <?php else: ?>
                                    <li class="breadcrumb-item active"><?php echo $item['title']; ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
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