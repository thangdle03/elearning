<?php

require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Báo cáo thống kê';
$current_page = 'reports';

// Get filter parameters
$date_range = $_GET['date_range'] ?? '30';
$report_type = $_GET['report_type'] ?? 'overview';

// Date range calculations
$date_conditions = [
    '7' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
    '30' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
    '90' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
    '365' => 'DATE_SUB(NOW(), INTERVAL 365 DAY)',
    'all' => '1970-01-01'
];

$date_condition = $date_conditions[$date_range] ?? $date_conditions['30'];

try {
    // Overall Statistics - SỬA LẠI
    $overall_stats = $pdo->query("
        SELECT 
            COALESCE((SELECT COUNT(*) FROM users WHERE role = 'student'), 0) as total_students,
            COALESCE((SELECT COUNT(*) FROM users WHERE role = 'admin'), 0) as total_admins,
            COALESCE((SELECT COUNT(*) FROM courses), 0) as total_courses,
            COALESCE((SELECT COUNT(*) FROM categories), 0) as total_categories,
            COALESCE((SELECT COUNT(*) FROM enrollments), 0) as total_enrollments,
            COALESCE((SELECT COUNT(*) FROM lessons), 0) as total_lessons,
            COALESCE((SELECT COUNT(*) FROM reviews), 0) as total_reviews,
            COALESCE((SELECT AVG(rating) FROM reviews), 0) as avg_rating,
            COALESCE((SELECT COUNT(*) FROM users WHERE created_at >= {$date_condition}), 0) as new_users_period,
            COALESCE((SELECT COUNT(*) FROM courses WHERE created_at >= {$date_condition}), 0) as new_courses_period,
            COALESCE((SELECT COUNT(*) FROM enrollments WHERE enrolled_at >= {$date_condition}), 0) as new_enrollments_period
    ")->fetch();

    // User Registration Trends (Last 30 days) - KHÔNG ĐỔI
    $user_trends = $pdo->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as registrations,
            COUNT(CASE WHEN role = 'student' THEN 1 END) as student_registrations,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_registrations
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
        LIMIT 30
    ")->fetchAll();

    // Course Performance Stats - SỬA LẠI (BỎ instructor_id)
    $course_stats = $pdo->query("
        SELECT 
            c.id,
            c.title,
            c.created_at,
            c.status,
            COUNT(DISTINCT e.user_id) as enrollment_count,
            COUNT(DISTINCT r.id) as review_count,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            cat.name as category_name
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN reviews r ON c.id = r.course_id AND r.status = 'approved'
        LEFT JOIN categories cat ON c.category_id = cat.id
        GROUP BY c.id, c.title, c.created_at, c.status, cat.name
        ORDER BY enrollment_count DESC, avg_rating DESC
        LIMIT 15
    ")->fetchAll();

    // Category Performance - KHÔNG ĐỔI
    $category_stats = $pdo->query("
        SELECT 
            cat.id,
            cat.name as category_name,
            COUNT(DISTINCT c.id) as course_count,
            COUNT(DISTINCT e.user_id) as total_enrollments,
            COUNT(DISTINCT r.id) as total_reviews,
            COALESCE(AVG(r.rating), 0) as avg_rating
        FROM categories cat
        LEFT JOIN courses c ON cat.id = c.category_id
        LEFT JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN reviews r ON c.id = r.course_id AND r.status = 'approved'
        GROUP BY cat.id, cat.name
        HAVING course_count > 0
        ORDER BY total_enrollments DESC
    ")->fetchAll();

    // Monthly Enrollment Trends - KHÔNG ĐỔI
    $monthly_enrollments = $pdo->query("
        SELECT 
            DATE_FORMAT(enrolled_at, '%Y-%m') as month,
            COUNT(*) as enrollments,
            COUNT(DISTINCT user_id) as unique_students
        FROM enrollments
        WHERE enrolled_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(enrolled_at, '%Y-%m')
        ORDER BY month ASC
        LIMIT 12
    ")->fetchAll();

    // Top Students Activity - KHÔNG ĐỔI
    $student_activity = $pdo->query("
        SELECT 
            u.id,
            u.username,
            u.email,
            u.created_at,
            u.status,
            COUNT(DISTINCT e.course_id) as enrolled_courses,
            COUNT(DISTINCT r.id) as reviews_given,
            MAX(e.enrolled_at) as last_enrollment,
            COALESCE(AVG(r.rating), 0) as avg_rating_given
        FROM users u
        LEFT JOIN enrollments e ON u.id = e.user_id
        LEFT JOIN reviews r ON u.id = r.user_id
        WHERE u.role = 'student'
        GROUP BY u.id, u.username, u.email, u.created_at, u.status
        ORDER BY enrolled_courses DESC, reviews_given DESC
        LIMIT 20
    ")->fetchAll();

    // Admin Activity Summary - SỬA LẠI (BỎ courses_created vì không có instructor_id)
    $admin_activity = $pdo->query("
        SELECT 
            u.id,
            u.username,
            u.email,
            u.created_at,
            u.status,
            0 as courses_created,
            0 as published_courses,
            COUNT(DISTINCT r.id) as admin_responses
        FROM users u
        LEFT JOIN reviews r ON u.id = r.admin_id
        WHERE u.role = 'admin'
        GROUP BY u.id, u.username, u.email, u.created_at, u.status
        ORDER BY admin_responses DESC
    ")->fetchAll();

    // System Health Check - KHÔNG ĐỔI
    $system_health = [
        'active_courses' => $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn(),
        'active_students' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn(),
        'pending_reviews' => $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn(),
        'recent_enrollments' => $pdo->query("SELECT COUNT(*) FROM enrollments WHERE enrolled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    ];

} catch (Exception $e) {
    $error = 'Có lỗi khi tải dữ liệu báo cáo: ' . $e->getMessage();
    // Set default values
    $overall_stats = array_fill_keys([
        'total_students', 'total_admins', 'total_courses', 'total_categories', 
        'total_enrollments', 'total_lessons', 'total_reviews', 'avg_rating',
        'new_users_period', 'new_courses_period', 'new_enrollments_period'
    ], 0);
    $user_trends = $course_stats = $category_stats = $monthly_enrollments = 
    $student_activity = $admin_activity = [];
    $system_health = array_fill_keys(['active_courses', 'active_students', 'pending_reviews', 'recent_enrollments'], 0);
}
?>

<?php include 'includes/admin-header.php'; ?>

<!-- Success/Error Messages -->
<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-chart-line me-2 text-primary"></i><?php echo $page_title; ?>
        </h1>
        <p class="text-muted mb-0">Thống kê và báo cáo chi tiết về hoạt động hệ thống e-learning</p>
    </div>
    <div class="btn-group" role="group">
        <button type="button" class="btn btn-outline-success" onclick="exportReport()">
            <i class="fas fa-download me-2"></i>Xuất Excel
        </button>
        <button type="button" class="btn btn-outline-info" onclick="printReport()">
            <i class="fas fa-print me-2"></i>In báo cáo
        </button>
        <button type="button" class="btn btn-outline-primary" onclick="location.reload()">
            <i class="fas fa-sync-alt me-2"></i>Làm mới
        </button>
    </div>
</div>

<!-- System Status Alert -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info border-left-info">
            <div class="row text-center">
                <div class="col-md-3">
                    <strong class="text-info">Khóa học đang hoạt động</strong><br>
                    <span class="h5 mb-0"><?php echo number_format($system_health['active_courses']); ?></span>
                </div>
                <div class="col-md-3">
                    <strong class="text-success">Học viên hoạt động</strong><br>
                    <span class="h5 mb-0"><?php echo number_format($system_health['active_students']); ?></span>
                </div>
                <div class="col-md-3">
                    <strong class="text-warning">Đánh giá chờ duyệt</strong><br>
                    <span class="h5 mb-0"><?php echo number_format($system_health['pending_reviews']); ?></span>
                </div>
                <div class="col-md-3">
                    <strong class="text-primary">Đăng ký tuần qua</strong><br>
                    <span class="h5 mb-0"><?php echo number_format($system_health['recent_enrollments']); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header bg-gradient-primary text-white">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-filter me-2"></i>Bộ lọc báo cáo
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">Khoảng thời gian</label>
                <select name="date_range" class="form-select" onchange="this.form.submit()">
                    <option value="7" <?php echo $date_range === '7' ? 'selected' : ''; ?>>📅 7 ngày qua</option>
                    <option value="30" <?php echo $date_range === '30' ? 'selected' : ''; ?>>📅 30 ngày qua</option>
                    <option value="90" <?php echo $date_range === '90' ? 'selected' : ''; ?>>📅 90 ngày qua</option>
                    <option value="365" <?php echo $date_range === '365' ? 'selected' : ''; ?>>📅 12 tháng qua</option>
                    <option value="all" <?php echo $date_range === 'all' ? 'selected' : ''; ?>>📅 Tất cả thời gian</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Loại báo cáo</label>
                <select name="report_type" class="form-select" onchange="this.form.submit()">
                    <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>📊 Tổng quan</option>
                    <option value="courses" <?php echo $report_type === 'courses' ? 'selected' : ''; ?>>📚 Khóa học</option>
                    <option value="students" <?php echo $report_type === 'students' ? 'selected' : ''; ?>>👨‍🎓 Học viên</option>
                    <option value="admins" <?php echo $report_type === 'admins' ? 'selected' : ''; ?>>👨‍💼 Quản trị</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Thời gian cập nhật</label>
                <div class="input-group">
                    <span class="input-group-text">🕒</span>
                    <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i:s'); ?>" readonly>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Overall Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 hover-shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            👨‍🎓 Tổng học viên
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overall_stats['total_students']); ?>
                        </div>
                        <div class="text-xs text-success mt-1">
                            <i class="fas fa-arrow-up"></i> +<?php echo number_format($overall_stats['new_users_period']); ?> mới (<?php echo $date_range; ?> ngày)
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 hover-shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            📚 Tổng khóa học
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overall_stats['total_courses']); ?>
                        </div>
                        <div class="text-xs text-success mt-1">
                            <i class="fas fa-arrow-up"></i> +<?php echo number_format($overall_stats['new_courses_period']); ?> mới
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-graduation-cap fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2 hover-shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            📝 Tổng đăng ký
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overall_stats['total_enrollments']); ?>
                        </div>
                        <div class="text-xs text-success mt-1">
                            <i class="fas fa-arrow-up"></i> +<?php echo number_format($overall_stats['new_enrollments_period']); ?> mới
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2 hover-shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            ⭐ Đánh giá trung bình
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overall_stats['avg_rating'], 1); ?>/5.0
                        </div>
                        <div class="text-xs text-info mt-1">
                            📊 <?php echo number_format($overall_stats['total_reviews']); ?> đánh giá
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-star fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- User Registration Trend -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-gradient-primary text-white">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-chart-area me-2"></i>Xu hướng đăng ký người dùng (30 ngày qua)
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="userTrendChart"></canvas>
                </div>
                <?php if (empty($user_trends)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-chart-line fa-3x mb-3"></i>
                    <p>Không có dữ liệu đăng ký trong khoảng thời gian này</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category Distribution -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-gradient-info text-white">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-chart-pie me-2"></i>Đăng ký theo danh mục
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="categoryChart"></canvas>
                </div>
                <?php if (empty($category_stats)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-chart-pie fa-3x mb-3"></i>
                    <p>Chưa có dữ liệu danh mục</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Reports Based on Selection -->
<?php if ($report_type === 'overview' || $report_type === 'courses'): ?>
<!-- Course Performance -->
<div class="card shadow mb-4">
    <div class="card-header py-3 bg-gradient-success text-white">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-trophy me-2"></i>Hiệu suất khóa học (Top 15)
        </h6>
    </div>
    <div class="card-body">
        <!-- Course Performance Table - SỬA LẠI -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="coursesTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Khóa học</th>
                        <th>Danh mục</th>
                        <th>Đăng ký</th>
                        <th>Đánh giá</th>
                        <th>Rating</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($course_stats)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="fas fa-graduation-cap fa-3x mb-3"></i><br>
                            Chưa có dữ liệu khóa học
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($course_stats as $index => $course): ?>
                        <tr class="<?php echo $course['status'] === 'published' ? '' : 'table-warning'; ?>">
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong class="text-primary"><?php echo htmlspecialchars($course['title']); ?></strong>
                                <br><small class="text-muted">ID: #<?php echo $course['id']; ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($course['category_name'] ?? 'N/A'); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary fs-6"><?php echo number_format($course['enrollment_count']); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-success fs-6"><?php echo number_format($course['review_count']); ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php 
                                    $rating = round($course['avg_rating'], 1);
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <i class="fas fa-star <?php echo $i <= $rating ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                    <small class="ms-2"><?php echo $rating; ?></small>
                                </div>
                            </td>
                            <td>
                                <?php if ($course['status'] === 'published'): ?>
                                    <span class="badge bg-success">Đã xuất bản</span>
                                <?php elseif ($course['status'] === 'draft'): ?>
                                    <span class="badge bg-warning">Nháp</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo ucfirst($course['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y', strtotime($course['created_at'])); ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($report_type === 'overview' || $report_type === 'students'): ?>
<!-- Student Activity -->
<div class="card shadow mb-4">
    <div class="card-header py-3 bg-gradient-warning text-white">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-user-graduate me-2"></i>Top 20 học viên tích cực nhất
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="studentsTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Học viên</th>
                        <th>Khóa học</th>
                        <th>Đánh giá</th>
                        <th>Rating TB</th>
                        <th>Trạng thái</th>
                        <th>Ngày đăng ký</th>
                        <th>Hoạt động gần nhất</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($student_activity)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="fas fa-users fa-3x mb-3"></i><br>
                            Chưa có dữ liệu học viên
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($student_activity as $index => $student): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-3">
                                        <?php echo strtoupper(substr($student['username'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($student['username']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary fs-6"><?php echo number_format($student['enrolled_courses']); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-success fs-6"><?php echo number_format($student['reviews_given']); ?></span>
                            </td>
                            <td>
                                <?php if ($student['reviews_given'] > 0): ?>
                                    <span class="badge bg-warning">⭐ <?php echo number_format($student['avg_rating_given'], 1); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'warning'; ?>">
                                    <?php echo $student['status'] === 'active' ? '✅ Active' : '⏸️ Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y', strtotime($student['created_at'])); ?>
                                </small>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php 
                                    echo $student['last_enrollment'] 
                                        ? date('d/m/Y H:i', strtotime($student['last_enrollment']))
                                        : 'Chưa có hoạt động';
                                    ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($report_type === 'overview' || $report_type === 'admins'): ?>
<!-- Admin Activity -->
<div class="card shadow mb-4">
    <div class="card-header py-3 bg-gradient-danger text-white">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-user-shield me-2"></i>Hoạt động quản trị viên
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="adminsTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Quản trị viên</th>
                        <th>Phản hồi đánh giá</th>
                        <th>Trạng thái</th>
                        <th>Ngày tham gia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($admin_activity)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="fas fa-user-shield fa-3x mb-3"></i><br>
                            Chưa có dữ liệu quản trị viên
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($admin_activity as $index => $admin): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-3" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);">
                                        <?php echo strtoupper(substr($admin['username'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($admin['username']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($admin['email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-warning fs-6"><?php echo number_format($admin['admin_responses']); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $admin['status'] === 'active' ? 'success' : 'warning'; ?>">
                                    <?php echo $admin['status'] === 'active' ? '✅ Active' : '⏸️ Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y H:i', strtotime($admin['created_at'])); ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Custom CSS -->
<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.bg-gradient-success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}
.bg-gradient-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.bg-gradient-warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}
.bg-gradient-danger {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
}

.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 14px;
    flex-shrink: 0;
}

.hover-shadow:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    transition: all 0.3s ease;
}

.table th {
    border-top: none;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    color: #5a5c69;
    background-color: #f8f9fc;
}

.badge {
    font-size: 0.75em;
}

.chart-area {
    height: 320px;
}

.chart-pie {
    height: 300px;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

@media print {
    .btn-group, .card-header, .no-print {
        -webkit-print-color-adjust: exact;
        display: none !important;
    }
    .card {
        border: 1px solid #dee2e6;
        box-shadow: none !important;
    }
}

@media (max-width: 768px) {
    .chart-area, .chart-pie {
        height: 250px;
    }
    .table-responsive {
        font-size: 0.85rem;
    }
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            try {
                new bootstrap.Alert(alert).close();
            } catch (e) {
                console.log('Alert already closed');
            }
        });
    }, 8000);

    // Initialize charts
    initializeCharts();
});

function initializeCharts() {
    // User Registration Trend Chart
    const userTrendCtx = document.getElementById('userTrendChart');
    if (userTrendCtx) {
        const userTrendChart = new Chart(userTrendCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($user_trends as $trend): ?>
                    '<?php echo date('d/m', strtotime($trend['date'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [
                    {
                        label: 'Học viên mới',
                        data: [
                            <?php foreach ($user_trends as $trend): ?>
                            <?php echo $trend['student_registrations']; ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#4e73df',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5
                    },
                    {
                        label: 'Admin mới',
                        data: [
                            <?php foreach ($user_trends as $trend): ?>
                            <?php echo $trend['admin_registrations']; ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#e74a3b',
                        backgroundColor: 'rgba(231, 74, 59, 0.1)',
                        borderWidth: 3,
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: '#e74a3b',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#dddfeb',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: '#858796'
                        },
                        grid: {
                            color: '#e3e6f0'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#858796'
                        },
                        grid: {
                            color: '#e3e6f0'
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }

    // Category Distribution Chart
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx) {
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($category_stats as $category): ?>
                    '<?php echo htmlspecialchars($category['category_name']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($category_stats as $category): ?>
                        <?php echo $category['total_enrollments']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', 
                        '#858796', '#5a5c69', '#6f42c1', '#e83e8c', '#fd7e14',
                        '#20c997', '#6610f2', '#e83e8c', '#fd7e14', '#20c997'
                    ],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverBorderWidth: 4,
                    hoverBorderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
}

// Export and Print Functions
function exportReport() {
    // Create CSV content
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Báo cáo thống kê E-Learning\n";
    csvContent += `Ngày xuất: ${new Date().toLocaleString('vi-VN')}\n\n`;
    
    // Add summary statistics
    csvContent += "Thống kê tổng quan:\n";
    csvContent += `Tổng học viên,${<?php echo $overall_stats['total_students']; ?>}\n`;
    csvContent += `Tổng khóa học,${<?php echo $overall_stats['total_courses']; ?>}\n`;
    csvContent += `Tổng đăng ký,${<?php echo $overall_stats['total_enrollments']; ?>}\n`;
    csvContent += `Đánh giá trung bình,${<?php echo number_format($overall_stats['avg_rating'], 1); ?>}\n\n`;
    
    // Create download link
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `bao-cao-${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Show success message
    showNotification('✅ Báo cáo đã được xuất thành công!', 'success');
}

function printReport() {
    // Hide non-essential elements before printing
    const nonPrintElements = document.querySelectorAll('.btn-group, .no-print');
    nonPrintElements.forEach(el => el.style.display = 'none');
    
    // Print
    window.print();
    
    // Restore elements after printing
    setTimeout(() => {
        nonPrintElements.forEach(el => el.style.display = '');
    }, 1000);
    
    showNotification('📄 Đang chuẩn bị in báo cáo...', 'info');
}

// Notification function
function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            new bootstrap.Alert(notification).close();
        }
    }, 5000);
}

// Auto refresh data every 10 minutes
setInterval(() => {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 600000);

console.log('📊 Reports dashboard loaded successfully!');
</script>

<?php include 'includes/admin-footer.php'; ?>