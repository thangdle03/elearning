<?php
// filepath: d:\Xampp\htdocs\elearning\dashboard.php
// Include config first (it will handle session_start)
require_once 'includes/config.php';

// Check login
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'];

// Get user stats with error handling
$user_stats = [
    'total_enrolled' => 0,
    'completed_courses' => 0,
    'completed_lessons' => 0,
    'certificates_earned' => 0,
    'avg_progress' => 0
];

$enrolled_courses = [];
$recent_activities = [];

try {
    // Get enrolled courses count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_stats['total_enrolled'] = $stmt->fetchColumn();

    // Get completed courses count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND completion_date IS NOT NULL");
    $stmt->execute([$user_id]);
    $user_stats['completed_courses'] = $stmt->fetchColumn();

    // Get enrolled courses details
    $stmt = $pdo->prepare("
        SELECT c.*, e.enrolled_at, e.progress, e.completion_date,
               cat.name as category_name
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC
        LIMIT 6
    ");
    $stmt->execute([$user_id]);
    $enrolled_courses = $stmt->fetchAll();

    // Get recent enrollment activities
    $stmt = $pdo->prepare("
        SELECT c.title as course_title, e.enrolled_at
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_activities = $stmt->fetchAll();

    // Calculate average progress
    if ($user_stats['total_enrolled'] > 0) {
        $stmt = $pdo->prepare("SELECT AVG(COALESCE(progress, 0)) FROM enrollments WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_stats['avg_progress'] = round($stmt->fetchColumn());
    }
} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
}

// Time ago function
if (!function_exists('timeAgo')) {
    function timeAgo($datetime)
    {
        if (empty($datetime)) return 'Không xác định';
        $time = time() - strtotime($datetime);
        if ($time < 60) return 'Vừa xong';
        if ($time < 3600) return floor($time / 60) . ' phút trước';
        if ($time < 86400) return floor($time / 3600) . ' giờ trước';
        if ($time < 2592000) return floor($time / 86400) . ' ngày trước';
        return floor($time / 2592000) . ' tháng trước';
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4299e1;
            --success-color: #48bb78;
            --warning-color: #ed8936;
            --info-color: #38b2ac;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Enhanced Navigation với dropdown như index */
        .navbar {
            background: rgba(102, 126, 234, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 0;
            box-shadow: 0 2px 20px rgba(102, 126, 234, 0.3);
        }

        .navbar-brand {
            font-weight: 800 !important;
            font-size: 1.5rem;
            color: #ffffff !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 600;
            padding: 0.8rem 1.2rem !important;
            border-radius: 8px;
            margin: 0 0.2rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar-nav .nav-link:hover {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .navbar-nav .nav-link.active {
            color: #fbbf24 !important;
            background: rgba(251, 191, 36, 0.2);
            font-weight: 700;
        }

        /* Enhanced Dropdown Menu */
        .dropdown-menu {
            background: #ffffff;
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 0.5rem 0;
            min-width: 220px;
            margin-top: 0.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dropdown-item {
            padding: 0.8rem 1.5rem;
            color: #374151 !important;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            opacity: 0.7;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: #ffffff !important;
            transform: translateX(5px);
        }

        .dropdown-item:hover i {
            opacity: 1;
            transform: scale(1.1);
        }

        .dropdown-item.text-danger:hover {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            color: #ffffff !important;
        }

        .dropdown-divider {
            border-color: rgba(0, 0, 0, 0.1);
            margin: 0.5rem 0;
        }

        /* User Avatar trong dropdown */
        .navbar .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem !important;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .navbar .dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .navbar .dropdown-toggle::after {
            margin-left: 0.5rem;
            vertical-align: middle;
        }

        /* User Avatar Circle */
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a202c;
            font-weight: bold;
            font-size: 0.9rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        /* Dashboard Header */
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .welcome-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.primary::before {
            background: var(--primary-color);
        }

        .stat-card.success::before {
            background: var(--success-color);
        }

        .stat-card.warning::before {
            background: var(--warning-color);
        }

        .stat-card.info::before {
            background: var(--info-color);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: white;
        }

        .stat-card.primary .stat-icon {
            background: var(--primary-color);
        }

        .stat-card.success .stat-icon {
            background: var(--success-color);
        }

        .stat-card.warning .stat-icon {
            background: var(--warning-color);
        }

        .stat-card.info .stat-icon {
            background: var(--info-color);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #2d3748;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            color: #4a5568;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .section-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 16px 16px 0 0;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0;
        }

        .course-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }

        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .course-thumbnail {
            position: relative;
            height: 160px;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }

        .course-info {
            padding: 1.5rem;
        }

        .course-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .course-meta {
            color: #4a5568;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .progress-bar-container {
            background: #e2e8f0;
            border-radius: 10px;
            height: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
            border-radius: 10px;
            transition: width 0.8s ease;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-left: 4px solid var(--primary-color);
            background: #f8fafc;
            border-radius: 0 8px 8px 0;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background: #edf2f7;
            transform: translateX(4px);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(66, 153, 225, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #4a5568;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e0;
            margin-bottom: 1rem;
        }

        .progress-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .welcome-title {
                font-size: 1.8rem;
            }

            .dashboard-header {
                text-align: center;
            }

            .stat-number {
                font-size: 2rem;
            }

            .navbar .dropdown-toggle {
                padding: 0.4rem 0.8rem !important;
            }

            .user-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <!-- Enhanced Navigation với dropdown giống index -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>">
                            <i class="fas fa-home me-1"></i>Trang chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/courses.php">
                            <i class="fas fa-book me-1"></i>Khóa học
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo SITE_URL; ?>/dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($username, 0, 2)); ?>
                            </div>
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($username); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <h6 class="dropdown-header d-flex align-items-center">
                                    <div class="user-avatar me-2" style="width: 25px; height: 25px; font-size: 0.7rem;">
                                        <?php echo strtoupper(substr($username, 0, 2)); ?>
                                    </div>
                                    <?php echo htmlspecialchars($username); ?>
                                </h6>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i>Dashboard
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/my-courses.php">
                                    <i class="fas fa-book-open"></i>Khóa học của tôi
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/profile.php">
                                    <i class="fas fa-user"></i>Hồ sơ cá nhân
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/certificates.php">
                                    <i class="fas fa-certificate"></i>Chứng chỉ
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/settings.php">
                                    <i class="fas fa-cog"></i>Cài đặt
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/logout.php">
                                    <i class="fas fa-sign-out-alt"></i>Đăng xuất
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Add padding top to compensate for fixed navbar -->
    <div style="padding-top: 80px;"></div>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">
                        Chào mừng trở lại, <?php echo htmlspecialchars($username); ?>! 👋
                    </h1>
                    <p class="mb-0" style="font-size: 1.1rem; opacity: 0.9;">
                        Tiếp tục hành trình học tập của bạn. Hôm nay bạn muốn học gì?
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="courses.php" class="btn btn-light btn-lg">
                        <i class="fas fa-plus-circle me-2"></i>Tìm khóa học mới
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-number" data-target="<?php echo $user_stats['total_enrolled']; ?>">0</div>
                    <div class="stat-label">Khóa học đã đăng ký</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-number" data-target="<?php echo $user_stats['completed_courses']; ?>">0</div>
                    <div class="stat-label">Khóa học hoàn thành</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-number" data-target="<?php echo $user_stats['completed_lessons']; ?>">0</div>
                    <div class="stat-label">Bài học hoàn thành</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="stat-number" data-target="<?php echo $user_stats['certificates_earned']; ?>">0</div>
                    <div class="stat-label">Chứng chỉ đạt được</div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row">
            <!-- Left Column - Courses -->
            <div class="col-lg-8">
                <div class="section-card">
                    <div class="section-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="section-title">
                                <i class="fas fa-play-circle me-2 text-primary"></i>Khóa học đang học
                            </h3>
                            <a href="my-courses.php" class="btn btn-outline-primary btn-sm">
                                Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($enrolled_courses)): ?>
                            <div class="row">
                                <?php foreach ($enrolled_courses as $course): ?>
                                    <div class="col-md-6">
                                        <div class="course-card">
                                            <div class="course-thumbnail">
                                                <?php if (!empty($course['thumbnail'])): ?>
                                                    <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>"
                                                        alt="<?php echo htmlspecialchars($course['title']); ?>"
                                                        style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                    <i class="fas fa-book-open"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="course-info">
                                                <h5 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                <div class="course-meta">
                                                    <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($course['category_name'] ?: 'Chưa phân loại'); ?>
                                                    <span class="ms-3">
                                                        <i class="fas fa-calendar me-1"></i><?php echo date('d/m/Y', strtotime($course['enrolled_at'])); ?>
                                                    </span>
                                                </div>

                                                <div class="progress-bar-container">
                                                    <div class="progress-bar-fill" style="width: <?php echo intval($course['progress'] ?? 0); ?>%"></div>
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted"><?php echo intval($course['progress'] ?? 0); ?>% hoàn thành</small>
                                                    <a href="course-detail.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-play me-1"></i>Tiếp tục
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-book-open"></i>
                                <h5>Chưa có khóa học nào</h5>
                                <p>Hãy đăng ký khóa học đầu tiên để bắt đầu hành trình học tập!</p>
                                <a href="courses.php" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Tìm khóa học
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Progress Overview -->
                <div class="section-card mb-4">
                    <div class="section-header">
                        <h5 class="section-title">
                            <i class="fas fa-chart-pie me-2 text-info"></i>Tổng quan tiến độ
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <div class="progress-circle" style="background: conic-gradient(var(--primary-color) 0deg, var(--primary-color) <?php echo ($user_stats['avg_progress'] * 3.6); ?>deg, #e2e8f0 <?php echo ($user_stats['avg_progress'] * 3.6); ?>deg);">
                                <div style="width: 90px; height: 90px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                                    <span style="font-size: 1.5rem; font-weight: bold; color: #2d3748;"><?php echo $user_stats['avg_progress']; ?>%</span>
                                    <small style="color: #4a5568;">Hoàn thành</small>
                                </div>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="fw-bold text-primary"><?php echo $user_stats['total_enrolled']; ?></div>
                                <small class="text-muted">Khóa học</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-success"><?php echo $user_stats['completed_courses']; ?></div>
                                <small class="text-muted">Hoàn thành</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-info"><?php echo $user_stats['certificates_earned']; ?></div>
                                <small class="text-muted">Chứng chỉ</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="section-card mb-4">
                    <div class="section-header">
                        <h5 class="section-title">
                            <i class="fas fa-history me-2 text-success"></i>Hoạt động gần đây
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-plus"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">Đăng ký khóa học</div>
                                        <div class="text-primary"><?php echo htmlspecialchars($activity['course_title']); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i><?php echo timeAgo($activity['enrolled_at']); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-history fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Chưa có hoạt động nào</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="section-title">
                            <i class="fas fa-bolt me-2 text-warning"></i>Hành động nhanh
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="courses.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Tìm khóa học
                            </a>
                            <a href="profile.php" class="btn btn-outline-primary">
                                <i class="fas fa-user me-2"></i>Hồ sơ cá nhân
                            </a>
                            <a href="certificates.php" class="btn btn-outline-success">
                                <i class="fas fa-certificate me-2"></i>Chứng chỉ
                            </a>
                            <a href="settings.php" class="btn btn-outline-secondary">
                                <i class="fas fa-cog me-2"></i>Cài đặt
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Spacing -->
    <div style="height: 100px;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Enhanced Dashboard with dropdown loaded!');

    // Initialize Bootstrap dropdowns properly
    const dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    const dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl, {
            boundary: 'viewport',
            display: 'dynamic'
        });
    });

    // Animate stat numbers
    const statNumbers = document.querySelectorAll('.stat-number[data-target]');
    statNumbers.forEach(stat => {
        const target = parseInt(stat.getAttribute('data-target'));
        animateCounter(stat, target, 1500);
    });

    function animateCounter(element, target, duration) {
        let start = 0;
        const increment = target / (duration / 16);

        const timer = setInterval(() => {
            start += increment;
            if (start >= target) {
                element.textContent = target;
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(start);
            }
        }, 16);
    }

    // Animate progress bars
    setTimeout(() => {
        const progressBars = document.querySelectorAll('.progress-bar-fill');
        progressBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 100);
        });
    }, 500);

    // Enhanced hover effects cho dropdown items (không override click)
    const dropdownItems = document.querySelectorAll('.dropdown-item');
    dropdownItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            if (!this.classList.contains('dropdown-header')) {
                this.style.transform = 'translateX(5px)';
                this.style.transition = 'all 0.3s ease';
            }
        });

        item.addEventListener('mouseleave', function() {
            if (!this.classList.contains('dropdown-header')) {
                this.style.transform = 'translateX(0)';
            }
        });
    });

    // Debug dropdown
    const navbarDropdown = document.getElementById('navbarDropdown');
    if (navbarDropdown) {
        navbarDropdown.addEventListener('click', function(e) {
            console.log('Dropdown clicked!');
        });
        
        // Listen for Bootstrap dropdown events
        navbarDropdown.addEventListener('show.bs.dropdown', function () {
            console.log('Dropdown is about to show');
        });
        
        navbarDropdown.addEventListener('shown.bs.dropdown', function () {
            console.log('Dropdown shown');
        });
    }

    console.log('✅ Dashboard initialized with working dropdown!');
});
</script>
</body>
</html>