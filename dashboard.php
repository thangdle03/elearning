<?php
// filepath: d:\Xampp\htdocs\elearning\dashboard.php

require_once 'includes/config.php';

// Check login
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php');
}

$username = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'];
$page_title = 'Dashboard';

// Initialize stats
$user_stats = [
    'total_enrolled' => 0,
    'completed_courses' => 0,
    'in_progress' => 0,
    'certificates_earned' => 0,
    'avg_progress' => 0,
    'study_hours' => 0,
    'lessons_completed' => 0
];

$enrolled_courses = [];
$recent_activities = [];

try {
    // Get total enrolled courses
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $user_stats['total_enrolled'] = $stmt->fetchColumn();

    // Get completed courses (progress = 100)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND progress >= 100");
    $stmt->execute([$user_id]);
    $user_stats['completed_courses'] = $stmt->fetchColumn();

    // Get in progress courses
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND progress > 0 AND progress < 100");
    $stmt->execute([$user_id]);
    $user_stats['in_progress'] = $stmt->fetchColumn();

    // Get certificates count (only check if table exists)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_stats['certificates_earned'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $user_stats['certificates_earned'] = $user_stats['completed_courses']; // Fallback
    }

    // Calculate average progress
    if ($user_stats['total_enrolled'] > 0) {
        $stmt = $pdo->prepare("SELECT AVG(COALESCE(progress, 0)) FROM enrollments WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$user_id]);
        $user_stats['avg_progress'] = round($stmt->fetchColumn(), 1);
    }

    // Estimate study hours (2 hours per enrolled course, more for completed)
    $user_stats['study_hours'] = ($user_stats['total_enrolled'] * 1.5) + ($user_stats['completed_courses'] * 3);

    // Get enrolled courses details (latest 4)
    $stmt = $pdo->prepare("
        SELECT c.*, e.enrolled_at, e.progress, e.completion_date,
               cat.name as category_name, cat.color as category_color
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE e.user_id = ? AND e.status = 'active'
        ORDER BY e.enrolled_at DESC
        LIMIT 4
    ");
    $stmt->execute([$user_id]);
    $enrolled_courses = $stmt->fetchAll();

    // Get recent activities (enrollments and completions)
    $activities = [];
    
    // Recent enrollments
    $stmt = $pdo->prepare("
        SELECT 'enrollment' as type, c.title, c.id as course_id, e.enrolled_at as activity_date
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $activities = array_merge($activities, $stmt->fetchAll());
    
    // Recent completions
    $stmt = $pdo->prepare("
        SELECT 'completion' as type, c.title, c.id as course_id, e.completion_date as activity_date
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ? AND e.completion_date IS NOT NULL
        ORDER BY e.completion_date DESC
        LIMIT 2
    ");
    $stmt->execute([$user_id]);
    $activities = array_merge($activities, $stmt->fetchAll());
    
    // Sort by date
    usort($activities, function($a, $b) {
        return strtotime($b['activity_date']) - strtotime($a['activity_date']);
    });
    
    $recent_activities = array_slice($activities, 0, 5);

} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
}

// Time ago function
function timeAgo($datetime) {
    if (empty($datetime)) return 'Không xác định';
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'Vừa xong';
    if ($time < 3600) return floor($time / 60) . ' phút trước';
    if ($time < 86400) return floor($time / 3600) . ' giờ trước';
    if ($time < 2592000) return floor($time / 86400) . ' ngày trước';
    return floor($time / 2592000) . ' tháng trước';
}

include 'includes/header.php';
?>

<div class="dashboard-wrapper">
    <!-- Hero Section -->
    <div class="hero-banner">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="hero-content">
                        <div class="greeting-text">Xin chào,</div>
                        <h1 class="hero-title">
                            <?php echo htmlspecialchars($username); ?> 
                            <span class="wave-emoji">👋</span>
                        </h1>
                        <p class="hero-subtitle">
                            Hôm nay là ngày tuyệt vời để học điều gì đó mới. Hãy tiếp tục hành trình học tập của bạn!
                        </p>
                        <div class="hero-buttons">
                            <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-primary-custom">
                                <i class="fas fa-search me-2"></i>Khám phá khóa học
                            </a>
                            <a href="<?php echo SITE_URL; ?>/my-courses.php" class="btn btn-secondary-custom">
                                <i class="fas fa-play me-2"></i>Tiếp tục học
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="progress-widget">
                        <div class="circular-progress" data-percent="<?php echo $user_stats['avg_progress']; ?>">
                            <div class="progress-inner">
                                <div class="progress-number"><?php echo $user_stats['avg_progress']; ?>%</div>
                                <div class="progress-label">Tiến độ tổng</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card blue-card">
                        <div class="stats-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="stats-content">
                            <div class="stats-number" data-count="<?php echo $user_stats['total_enrolled']; ?>">0</div>
                            <div class="stats-label">Khóa học đã đăng ký</div>
                        </div>
                        <div class="stats-trend">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card green-card">
                        <div class="stats-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stats-content">
                            <div class="stats-number" data-count="<?php echo $user_stats['completed_courses']; ?>">0</div>
                            <div class="stats-label">Khóa học hoàn thành</div>
                        </div>
                        <div class="stats-trend">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card purple-card">
                        <div class="stats-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stats-content">
                            <div class="stats-number" data-count="<?php echo $user_stats['study_hours']; ?>">0</div>
                            <div class="stats-label">Giờ học tập</div>
                        </div>
                        <div class="stats-trend">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card orange-card">
                        <div class="stats-icon">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <div class="stats-content">
                            <div class="stats-number" data-count="<?php echo $user_stats['certificates_earned']; ?>">0</div>
                            <div class="stats-label">Chứng chỉ đạt được</div>
                        </div>
                        <div class="stats-trend">
                            <i class="fas fa-medal"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="dashboard-content">
        <div class="container">
            <div class="row g-4">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Current Courses -->
                    <div class="dashboard-card">
                        <div class="card-header-custom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="card-title-custom">
                                    <i class="fas fa-play-circle me-2"></i>Khóa học đang học
                                </h3>
                                <a href="<?php echo SITE_URL; ?>/my-courses.php" class="btn-link-custom">
                                    Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <?php if (!empty($enrolled_courses)): ?>
                                <div class="courses-grid">
                                    <?php foreach ($enrolled_courses as $course): ?>
                                        <div class="course-item">
                                            <div class="course-thumb">
                                                <?php if (!empty($course['thumbnail'])): ?>
                                                    <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>" 
                                                         alt="<?php echo htmlspecialchars($course['title']); ?>">
                                                <?php else: ?>
                                                    <div class="course-thumb-placeholder">
                                                        <i class="fas fa-graduation-cap"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="course-overlay">
                                                    <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>" 
                                                       class="play-btn">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="course-details">
                                                <h5 class="course-name"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                <div class="course-meta-info">
                                                    <span class="course-category">
                                                        <i class="fas fa-tag"></i>
                                                        <?php echo htmlspecialchars($course['category_name'] ?: 'Chung'); ?>
                                                    </span>
                                                    <span class="course-date">
                                                        <?php echo date('d/m/Y', strtotime($course['enrolled_at'])); ?>
                                                    </span>
                                                </div>
                                                <div class="progress-wrapper">
                                                    <div class="progress-bar-custom">
                                                        <div class="progress-fill" style="width: <?php echo intval($course['progress'] ?? 0); ?>%"></div>
                                                    </div>
                                                    <span class="progress-percent"><?php echo intval($course['progress'] ?? 0); ?>%</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-courses">
                                    <div class="empty-icon">
                                        <i class="fas fa-book-open"></i>
                                    </div>
                                    <h4>Chưa có khóa học nào</h4>
                                    <p>Hãy bắt đầu hành trình học tập bằng cách đăng ký khóa học đầu tiên!</p>
                                    <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-primary-custom">
                                        <i class="fas fa-plus me-2"></i>Đăng ký khóa học
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar -->
                <div class="col-lg-4">
                    <!-- Progress Overview -->
                    <div class="dashboard-card mb-4">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom">
                                <i class="fas fa-chart-pie me-2"></i>Tổng quan tiến độ
                            </h5>
                        </div>
                        <div class="card-body-custom text-center">
                            <div class="progress-stats">
                                <div class="row">
                                    <div class="col-4">
                                        <div class="stat-mini blue-stat">
                                            <div class="stat-mini-number"><?php echo $user_stats['total_enrolled']; ?></div>
                                            <div class="stat-mini-text">Đã đăng ký</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-mini green-stat">
                                            <div class="stat-mini-number"><?php echo $user_stats['completed_courses']; ?></div>
                                            <div class="stat-mini-text">Hoàn thành</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-mini purple-stat">
                                            <div class="stat-mini-number"><?php echo $user_stats['in_progress']; ?></div>
                                            <div class="stat-mini-text">Đang học</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="completion-rate">
                                <div class="rate-circle">
                                    <span class="rate-percent"><?php echo $user_stats['avg_progress']; ?>%</span>
                                    <span class="rate-label">Hoàn thành</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="dashboard-card mb-4">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom">
                                <i class="fas fa-history me-2"></i>Hoạt động gần đây
                            </h5>
                        </div>
                        <div class="card-body-custom">
                            <?php if (!empty($recent_activities)): ?>
                                <div class="activity-timeline">
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="activity-entry">
                                            <div class="activity-dot <?php echo $activity['type'] === 'completion' ? 'success' : 'info'; ?>">
                                                <i class="fas fa-<?php echo $activity['type'] === 'completion' ? 'check' : 'plus'; ?>"></i>
                                            </div>
                                            <div class="activity-details">
                                                <div class="activity-action">
                                                    <?php if ($activity['type'] === 'completion'): ?>
                                                        <strong>Hoàn thành khóa học</strong>
                                                    <?php else: ?>
                                                        <strong>Đăng ký khóa học</strong>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="activity-course"><?php echo htmlspecialchars($activity['title']); ?></div>
                                                <div class="activity-time"><?php echo timeAgo($activity['activity_date']); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-activity">
                                    <i class="fas fa-clock"></i>
                                    <p>Chưa có hoạt động nào</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="dashboard-card">
                        <div class="card-header-custom">
                            <h5 class="card-title-custom">
                                <i class="fas fa-zap me-2"></i>Thao tác nhanh
                            </h5>
                        </div>
                        <div class="card-body-custom">
                            <div class="quick-menu">
                                <a href="<?php echo SITE_URL; ?>/courses.php" class="quick-item blue-item">
                                    <div class="quick-icon">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <div class="quick-text">
                                        <div class="quick-name">Tìm khóa học</div>
                                        <div class="quick-desc">Khám phá khóa học mới</div>
                                    </div>
                                </a>
                                
                                <a href="<?php echo SITE_URL; ?>/my-courses.php" class="quick-item green-item">
                                    <div class="quick-icon">
                                        <i class="fas fa-play"></i>
                                    </div>
                                    <div class="quick-text">
                                        <div class="quick-name">Khóa học của tôi</div>
                                        <div class="quick-desc">Tiếp tục học tập</div>
                                    </div>
                                </a>
                                
                                <a href="<?php echo SITE_URL; ?>/certificates.php" class="quick-item purple-item">
                                    <div class="quick-icon">
                                        <i class="fas fa-certificate"></i>
                                    </div>
                                    <div class="quick-text">
                                        <div class="quick-name">Chứng chỉ</div>
                                        <div class="quick-desc">Xem chứng chỉ đạt được</div>
                                    </div>
                                </a>
                                
                                <a href="<?php echo SITE_URL; ?>/settings.php" class="quick-item orange-item">
                                    <div class="quick-icon">
                                        <i class="fas fa-cog"></i>
                                    </div>
                                    <div class="quick-text">
                                        <div class="quick-name">Cài đặt</div>
                                        <div class="quick-desc">Tùy chỉnh tài khoản</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard Styles */
.dashboard-wrapper {
    background: #f8fafc;
    min-height: 100vh;
    padding-top: 2rem;
}

/* Hero Banner */
.hero-banner {
    background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%);
    color: white;
    padding: 4rem 0;
    margin-bottom: 3rem;
    border-radius: 0 0 40px 40px;
    position: relative;
    overflow: hidden;
}

.hero-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.05);
    z-index: 1;
}

.hero-content {
    position: relative;
    z-index: 2;
}

.greeting-text {
    font-size: 1.2rem;
    color: #bfdbfe;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.hero-title {
    font-size: 3.5rem;
    font-weight: 800;
    margin-bottom: 1rem;
    color: white;
}

.wave-emoji {
    font-size: 3rem;
    margin-left: 1rem;
    animation: wave 2s infinite;
}

@keyframes wave {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(20deg); }
    75% { transform: rotate(-10deg); }
}

.hero-subtitle {
    font-size: 1.1rem;
    color: #dbeafe;
    margin-bottom: 2rem;
    line-height: 1.6;
}

.hero-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn-primary-custom {
    background: #f59e0b;
    color: #1f2937;
    border: none;
    padding: 1rem 2rem;
    border-radius: 50px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
}

.btn-primary-custom:hover {
    background: #d97706;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
    color: #1f2937;
}

.btn-secondary-custom {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
    padding: 1rem 2rem;
    border-radius: 50px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.btn-secondary-custom:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
    color: white;
}

/* Progress Widget */
.progress-widget {
    text-align: center;
}

.circular-progress {
    width: 180px;
    height: 180px;
    border-radius: 50%;
    background: conic-gradient(
        #f59e0b 0deg,
        #f59e0b calc(var(--percent, 0) * 3.6deg),
        rgba(255, 255, 255, 0.2) calc(var(--percent, 0) * 3.6deg)
    );
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    position: relative;
}

.progress-inner {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: rgba(30, 58, 138, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    backdrop-filter: blur(10px);
}

.progress-number {
    font-size: 2rem;
    font-weight: 800;
    color: white;
}

.progress-label {
    font-size: 0.9rem;
    color: #bfdbfe;
    margin-top: 0.25rem;
}

/* Stats Section */
.stats-section {
    margin-bottom: 3rem;
}

.stats-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    border-left: 4px solid;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
}

.blue-card {
    border-left-color: #3b82f6;
}

.green-card {
    border-left-color: #10b981;
}

.purple-card {
    border-left-color: #8b5cf6;
}

.orange-card {
    border-left-color: #f59e0b;
}

.stats-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin-bottom: 1rem;
}

.blue-card .stats-icon {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.green-card .stats-icon {
    background: linear-gradient(135deg, #10b981, #059669);
}

.purple-card .stats-icon {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

.orange-card .stats-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.stats-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.stats-label {
    color: #6b7280;
    font-weight: 600;
    font-size: 0.95rem;
}

.stats-trend {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
}

/* Dashboard Content */
.dashboard-content {
    margin-bottom: 3rem;
}

.dashboard-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid #f1f5f9;
}

.card-header-custom {
    padding: 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    background: #fafbfc;
}

.card-title-custom {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

.card-body-custom {
    padding: 1.5rem;
}

.btn-link-custom {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
}

.btn-link-custom:hover {
    color: #2563eb;
    text-decoration: none;
}

/* Course Grid */
.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.course-item {
    background: #f8fafc;
    border-radius: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.course-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.course-thumb {
    position: relative;
    height: 140px;
    overflow: hidden;
}

.course-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.course-thumb-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #3b82f6, #1e40af);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
}

.course-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.course-item:hover .course-overlay {
    opacity: 1;
}

.play-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: white;
    color: #3b82f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.play-btn:hover {
    transform: scale(1.1);
    color: #2563eb;
}

.course-details {
    padding: 1.25rem;
}

.course-name {
    font-size: 1rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.75rem;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.course-meta-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    font-size: 0.8rem;
    color: #6b7280;
}

.course-category {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.progress-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.progress-bar-custom {
    flex: 1;
    height: 6px;
    background: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #1e40af);
    border-radius: 10px;
    transition: width 0.8s ease;
}

.progress-percent {
    font-weight: 700;
    color: #3b82f6;
    font-size: 0.85rem;
}

/* Empty State */
.empty-courses {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
}

.empty-icon {
    font-size: 4rem;
    color: #d1d5db;
    margin-bottom: 1rem;
}

.empty-courses h4 {
    color: #1f2937;
    margin-bottom: 1rem;
}

/* Progress Stats */
.progress-stats {
    margin-bottom: 2rem;
}

.stat-mini {
    text-align: center;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 1rem;
}

.blue-stat {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
}

.green-stat {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
}

.purple-stat {
    background: linear-gradient(135deg, #ede9fe, #ddd6fe);
}

.stat-mini-number {
    font-size: 1.25rem;
    font-weight: 800;
    color: #1f2937;
}

.stat-mini-text {
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 600;
}

.completion-rate {
    text-align: center;
}

.rate-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    margin: 0 auto;
    border: 4px solid #3b82f6;
}

.rate-percent {
    font-size: 1.5rem;
    font-weight: 800;
    color: #1f2937;
}

.rate-label {
    font-size: 0.75rem;
    color: #6b7280;
}

/* Activity Timeline */
.activity-timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-entry {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.activity-dot {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.activity-dot.success {
    background: linear-gradient(135deg, #10b981, #059669);
}

.activity-dot.info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.activity-action {
    font-weight: 700;
    color: #1f2937;
    font-size: 0.9rem;
}

.activity-course {
    color: #6b7280;
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.activity-time {
    color: #9ca3af;
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.empty-activity {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
}

.empty-activity i {
    font-size: 2rem;
    color: #d1d5db;
    margin-bottom: 0.5rem;
}

/* Quick Menu */
.quick-menu {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.quick-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.quick-item:hover {
    background: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    text-decoration: none;
}

.quick-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
}

.blue-item .quick-icon {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.green-item .quick-icon {
    background: linear-gradient(135deg, #10b981, #059669);
}

.purple-item .quick-icon {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

.orange-item .quick-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.quick-name {
    font-weight: 700;
    color: #1f2937;
    font-size: 0.9rem;
}

.quick-desc {
    color: #6b7280;
    font-size: 0.8rem;
    margin-top: 0.25rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-wrapper {
        padding-top: 1rem;
    }
    
    .hero-banner {
        padding: 2.5rem 0;
        margin-bottom: 2rem;
    }
    
    .hero-title {
        font-size: 2.5rem;
    }
    
    .hero-buttons {
        justify-content: center;
    }
    
    .circular-progress {
        width: 140px;
        height: 140px;
    }
    
    .progress-inner {
        width: 110px;
        height: 110px;
    }
    
    .courses-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-number {
        font-size: 2rem;
    }
}

@media (max-width: 576px) {
    .hero-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-primary-custom,
    .btn-secondary-custom {
        width: 100%;
        text-align: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate counter numbers
    const counters = document.querySelectorAll('.stats-number[data-count]');
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-count'));
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                counter.textContent = target;
                clearInterval(timer);
            } else {
                counter.textContent = Math.floor(current);
            }
        }, 30);
    });

    // Set circular progress
    const circularProgress = document.querySelector('.circular-progress');
    if (circularProgress) {
        const percent = circularProgress.getAttribute('data-percent');
        circularProgress.style.setProperty('--percent', percent);
    }

    // Animate progress bars
    setTimeout(() => {
        const progressBars = document.querySelectorAll('.progress-fill');
        progressBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 100);
        });
    }, 1000);

    console.log('✅ Dashboard loaded with accurate data!');
});
</script>

<?php include 'includes/footer.php'; ?>