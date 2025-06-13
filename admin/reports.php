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
    // Overall Statistics - Based on actual database structure
    $overall_stats = $pdo->query("
        SELECT 
            COALESCE((SELECT COUNT(*) FROM users WHERE role = 'student'), 0) as total_students,
            COALESCE((SELECT COUNT(*) FROM users WHERE role = 'admin'), 0) as total_admins,
            COALESCE((SELECT COUNT(*) FROM courses), 0) as total_courses,
            COALESCE((SELECT COUNT(*) FROM categories), 0) as total_categories,
            COALESCE((SELECT COUNT(*) FROM enrollments), 0) as total_enrollments,
            COALESCE((SELECT COUNT(*) FROM lessons), 0) as total_lessons,
            COALESCE((SELECT COUNT(*) FROM users WHERE created_at >= {$date_condition}), 0) as new_users_period,
            COALESCE((SELECT COUNT(*) FROM courses WHERE created_at >= {$date_condition}), 0) as new_courses_period,
            COALESCE((SELECT COUNT(*) FROM enrollments WHERE enrolled_at >= {$date_condition}), 0) as new_enrollments_period
    ")->fetch();

    // Ensure all stats have default values
    $overall_stats = array_merge([
        'total_students' => 0,
        'total_admins' => 0,
        'total_courses' => 0,
        'total_categories' => 0,
        'total_enrollments' => 0,
        'total_lessons' => 0,
        'new_users_period' => 0,
        'new_courses_period' => 0,
        'new_enrollments_period' => 0
    ], $overall_stats ?: []);

    // User Registration Trends (Last 30 days)
    $user_trends = $pdo->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as registrations,
            COUNT(CASE WHEN role = 'student' THEN 1 END) as student_registrations,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_registrations
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
        LIMIT 30
    ")->fetchAll();

    // Course Enrollment Stats
    $course_stats = $pdo->query("
        SELECT 
            c.title,
            c.created_at,
            COUNT(e.user_id) as enrollment_count,
            COUNT(DISTINCT e.user_id) as unique_students,
            cat.name as category_name
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN categories cat ON c.category_id = cat.id
        GROUP BY c.id, c.title, c.created_at, cat.name
        ORDER BY enrollment_count DESC
        LIMIT 10
    ")->fetchAll();

    // Category Performance - Based on actual structure
    $category_stats = $pdo->query("
        SELECT 
            cat.name as category_name,
            COUNT(DISTINCT c.id) as course_count,
            COUNT(e.user_id) as total_enrollments,
            ROUND(AVG(course_enrollments.enrollment_count), 1) as avg_enrollments_per_course
        FROM categories cat
        LEFT JOIN courses c ON cat.id = c.category_id
        LEFT JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN (
            SELECT course_id, COUNT(*) as enrollment_count
            FROM enrollments
            GROUP BY course_id
        ) course_enrollments ON c.id = course_enrollments.course_id
        GROUP BY cat.id, cat.name
        ORDER BY total_enrollments DESC
    ")->fetchAll();

    // Monthly Enrollment Trends
    $monthly_enrollments = $pdo->query("
        SELECT 
            DATE_FORMAT(enrolled_at, '%Y-%m') as month,
            COUNT(*) as enrollments
        FROM enrollments
        WHERE enrolled_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(enrolled_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ")->fetchAll();

    // Top Performing Courses - Simplified for actual structure
    $top_courses = $pdo->query("
        SELECT 
            c.title,
            c.description,
            c.created_at,
            COUNT(e.user_id) as enrollment_count,
            COUNT(CASE WHEN e.status = 'active' THEN 1 END) as active_enrollments,
            cat.name as category_name
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN categories cat ON c.category_id = cat.id
        GROUP BY c.id, c.title, c.description, c.created_at, cat.name
        HAVING enrollment_count > 0
        ORDER BY enrollment_count DESC, c.created_at DESC
        LIMIT 10
    ")->fetchAll();

    // Active Student Summary
    $student_activity = $pdo->query("
        SELECT 
            u.username,
            u.email,
            u.created_at,
            u.status,
            COUNT(DISTINCT e.course_id) as enrolled_courses,
            MAX(e.enrolled_at) as last_enrollment
        FROM users u
        LEFT JOIN enrollments e ON u.id = e.user_id
        WHERE u.role = 'student'
        GROUP BY u.id, u.username, u.email, u.created_at, u.status
        ORDER BY enrolled_courses DESC, last_enrollment DESC
        LIMIT 20
    ")->fetchAll();

    // Admin Activity Summary
    $admin_activity = $pdo->query("
        SELECT 
            u.username,
            u.email,
            u.created_at,
            u.status,
            COUNT(DISTINCT c.id) as courses_created
        FROM users u
        LEFT JOIN courses c ON u.id = c.instructor_id
        WHERE u.role = 'admin'
        GROUP BY u.id, u.username, u.email, u.created_at, u.status
        ORDER BY courses_created DESC, u.created_at DESC
    ")->fetchAll();

} catch (Exception $e) {
    $error = 'Có lỗi khi tải dữ liệu báo cáo: ' . $e->getMessage();
    // Set default empty arrays
    $overall_stats = [
        'total_students' => 0,
        'total_admins' => 0,
        'total_courses' => 0,
        'total_categories' => 0,
        'total_enrollments' => 0,
        'total_lessons' => 0,
        'new_users_period' => 0,
        'new_courses_period' => 0,
        'new_enrollments_period' => 0
    ];
    $user_trends = [];
    $course_stats = [];
    $category_stats = [];
    $monthly_enrollments = [];
    $top_courses = [];
    $student_activity = [];
    $admin_activity = [];
}
?>

<?php include 'includes/admin-header.php'; ?>

<!-- Error Message -->
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
            <i class="fas fa-chart-line me-2"></i><?php echo $page_title; ?>
        </h1>
        <p class="text-muted mb-0">Thống kê và báo cáo chi tiết về hoạt động hệ thống e-learning</p>
    </div>
    <div class="btn-group" role="group">
        <button type="button" class="btn btn-outline-success" onclick="exportReport()">
            <i class="fas fa-download me-2"></i>Xuất báo cáo
        </button>
        <button type="button" class="btn btn-outline-info" onclick="printReport()">
            <i class="fas fa-print me-2"></i>In báo cáo
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-filter me-2"></i>Bộ lọc báo cáo
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Khoảng thời gian</label>
                <select name="date_range" class="form-select" onchange="this.form.submit()">
                    <option value="7" <?php echo $date_range === '7' ? 'selected' : ''; ?>>7 ngày qua</option>
                    <option value="30" <?php echo $date_range === '30' ? 'selected' : ''; ?>>30 ngày qua</option>
                    <option value="90" <?php echo $date_range === '90' ? 'selected' : ''; ?>>90 ngày qua</option>
                    <option value="365" <?php echo $date_range === '365' ? 'selected' : ''; ?>>12 tháng qua</option>
                    <option value="all" <?php echo $date_range === 'all' ? 'selected' : ''; ?>>Tất cả thời gian</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Loại báo cáo</label>
                <select name="report_type" class="form-select" onchange="this.form.submit()">
                    <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Tổng quan</option>
                    <option value="courses" <?php echo $report_type === 'courses' ? 'selected' : ''; ?>>Khóa học</option>
                    <option value="students" <?php echo $report_type === 'students' ? 'selected' : ''; ?>>Học viên</option>
                    <option value="admins" <?php echo $report_type === 'admins' ? 'selected' : ''; ?>>Quản trị</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Cập nhật</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-sync-alt me-2"></i>Làm mới dữ liệu
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Overall Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Tổng học viên
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overall_stats['total_students']); ?>
                        </div>
                        <div class="text-xs text-success">
                            +<?php echo number_format($overall_stats['new_users_period']); ?> mới
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
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Tổng khóa học
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overall_stats['total_courses']); ?>
                        </div>
                        <div class="text-xs text-success">
                            +<?php echo number_format($overall_stats['new_courses_period']); ?> mới
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
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Tổng đăng ký
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overall_stats['total_enrollments']); ?>
                        </div>
                        <div class="text-xs text-success">
                            +<?php echo number_format($overall_stats['new_enrollments_period']); ?> mới
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
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Quản trị viên
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overall_stats['total_admins']); ?>
                        </div>
                        <div class="text-xs text-muted">
                            <?php 
                            $total_courses = (int)$overall_stats['total_courses'];
                            $total_lessons = (int)$overall_stats['total_lessons'];
                            $avg_lessons = $total_courses > 0 ? round($total_lessons / $total_courses, 1) : 0;
                            echo $avg_lessons; 
                            ?> bài/khóa học
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-shield fa-2x text-gray-300"></i>
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
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-area me-2"></i>Xu hướng đăng ký người dùng (30 ngày qua)
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="userTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrollment by Category -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-pie me-2"></i>Đăng ký theo danh mục
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Reports Based on Selection -->
<?php if ($report_type === 'overview' || $report_type === 'courses'): ?>
<!-- Top Courses -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-trophy me-2"></i>Top 10 khóa học phổ biến nhất
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Khóa học</th>
                        <th>Danh mục</th>
                        <th>Lượt đăng ký</th>
                        <th>Đang hoạt động</th>
                        <th>Ngày tạo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_courses as $index => $course): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong class="text-primary"><?php echo htmlspecialchars($course['title']); ?></strong>
                            <br>
                            <small class="text-muted">
                                <?php echo htmlspecialchars(mb_substr($course['description'] ?? '', 0, 50)) . '...'; ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($course['category_name'] ?? 'N/A'); ?></span>
                        </td>
                        <td>
                            <span class="badge bg-info fs-6"><?php echo number_format($course['enrollment_count']); ?></span>
                        </td>
                        <td>
                            <span class="badge bg-success fs-6"><?php echo number_format($course['active_enrollments']); ?></span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo date('d/m/Y', strtotime($course['created_at'])); ?>
                            </small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($report_type === 'overview' || $report_type === 'students'): ?>
<!-- Student Activity -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-user-graduate me-2"></i>Top 20 học viên tích cực nhất
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Học viên</th>
                        <th>Khóa học đăng ký</th>
                        <th>Trạng thái</th>
                        <th>Ngày đăng ký</th>
                        <th>Đăng ký gần nhất</th>
                    </tr>
                </thead>
                <tbody>
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
                            <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($student['status']); ?>
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
                                    : 'Chưa có đăng ký';
                                ?>
                            </small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($report_type === 'overview' || $report_type === 'admins'): ?>
<!-- Admin Activity -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-user-shield me-2"></i>Hoạt động quản trị viên
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Quản trị viên</th>
                        <th>Khóa học tạo</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo tài khoản</th>
                    </tr>
                </thead>
                <tbody>
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
                            <span class="badge bg-info fs-6"><?php echo number_format($admin['courses_created']); ?></span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $admin['status'] === 'active' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($admin['status']); ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo date('d/m/Y H:i', strtotime($admin['created_at'])); ?>
                            </small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
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
}

.table th {
    border-top: none;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    color: #5a5c69;
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

@media print {
    .btn-group, .card-header {
        -webkit-print-color-adjust: exact;
    }
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom JavaScript -->
<script>
// User Registration Trend Chart
const userTrendCtx = document.getElementById('userTrendChart').getContext('2d');
const userTrendChart = new Chart(userTrendCtx, {
    type: 'line',
    data: {
        labels: [
            <?php foreach (array_reverse($user_trends) as $trend): ?>
            '<?php echo date('d/m', strtotime($trend['date'])); ?>',
            <?php endforeach; ?>
        ],
        datasets: [
            {
                label: 'Học viên mới',
                data: [
                    <?php foreach (array_reverse($user_trends) as $trend): ?>
                    <?php echo $trend['student_registrations']; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Admin mới',
                data: [
                    <?php foreach (array_reverse($user_trends) as $trend): ?>
                    <?php echo $trend['admin_registrations']; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: '#e74a3b',
                backgroundColor: 'rgba(231, 74, 59, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Category Distribution Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
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
                '#858796', '#5a5c69', '#6f42c1', '#e83e8c', '#fd7e14'
            ],
            borderWidth: 2,
            borderColor: '#ffffff'
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
                    usePointStyle: true
                }
            }
        }
    }
});

// Export and Print Functions
function exportReport() {
    alert('Tính năng xuất báo cáo đang được phát triển!');
}

function printReport() {
    window.print();
}

// Auto refresh data every 5 minutes
setInterval(() => {
    location.reload();
}, 300000);
</script>

<?php include 'includes/admin-footer.php'; ?>