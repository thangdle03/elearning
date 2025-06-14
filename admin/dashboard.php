<?php
// filepath: d:\Xampp\htdocs\elearning\admin\dashboard.php
require_once '../includes/config.php';

// Check authentication
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
$total_students = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM courses");
$total_courses = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM lessons");
$total_lessons = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM enrollments");
$total_enrollments = $stmt->fetchColumn();

// Get recent courses
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name 
    FROM courses c 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    ORDER BY c.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_courses = $stmt->fetchAll();

// Get recent users
$stmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE role = 'student' 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_users = $stmt->fetchAll();

$page_title = 'Dashboard';
$current_page = 'dashboard';
?>

<?php include 'includes/admin-header.php'; ?>

<div class="container-fluid px-4">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
        <div class="d-none d-sm-inline-block">
            <small class="text-muted">
                <i class="fas fa-clock me-1"></i>
                <?php echo date('H:i - d/m/Y'); ?>
            </small>
        </div>
    </div>

    <!-- Statistics Cards Row -->
    <div class="row">
        <!-- Users Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Người dùng
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_students); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Courses Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Khóa học
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_courses); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lessons Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Bài học
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_lessons); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-play-circle fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enrollments Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Ghi danh
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_enrollments); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-graduate fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Recent Courses -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-book me-2"></i>Khóa học mới nhất
                    </h6>
                    <a href="courses.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye me-1"></i>Xem tất cả
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (count($recent_courses) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th width="35%">TIÊU ĐỀ</th>
                                        <th width="20%">DANH MỤC</th>
                                        <th width="15%">GIÁ</th>
                                        <th width="15%">NGÀY TẠO</th>
                                        <th width="15%">THAO TÁC</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_courses as $course): ?>
                                        <tr class="course-row" style="cursor: pointer;" onclick="window.location.href='course-detail.php?id=<?php echo $course['id']; ?>'">
                                            <td class="font-weight-bold">
                                                <a href="course-detail.php?id=<?php echo $course['id']; ?>"
                                                    class="text-decoration-none text-primary course-title-link">
                                                    <?php echo htmlspecialchars($course['title']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($course['category_name']): ?>
                                                    <span class="badge badge-secondary">
                                                        <?php echo htmlspecialchars($course['category_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">Chưa phân loại</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="font-weight-bold">
                                                <?php if ($course['price'] > 0): ?>
                                                    <span class="text-success">
                                                        <?php echo number_format($course['price']); ?> VNĐ
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-info">Miễn phí</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($course['created_at'])); ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="course-detail.php?id=<?php echo $course['id']; ?>"
                                                        class="btn btn-sm btn-outline-info"
                                                        title="Xem chi tiết"
                                                        onclick="event.stopPropagation();">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit-course.php?id=<?php echo $course['id']; ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                        title="Chỉnh sửa"
                                                        onclick="event.stopPropagation();">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-book fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">Chưa có khóa học nào</h6>
                            <p class="text-muted">Hãy tạo khóa học đầu tiên để bắt đầu.</p>
                            <a href="add-course.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Tạo khóa học
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-users me-2"></i>Người dùng mới
                    </h6>
                    <a href="users.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye me-1"></i>Xem tất cả
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_users) > 0): ?>
                        <?php foreach ($recent_users as $user): ?>
                            <div class="d-flex align-items-center mb-3 p-2 rounded bg-light">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                                        style="width: 40px; height: 40px;">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0 font-weight-bold">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </small>
                                    <br>
                                    <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?> mt-1">
                                        <?php echo $user['role'] === 'admin' ? 'Admin' : 'Student'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-users fa-2x text-muted mb-2"></i>
                            <p class="text-muted">Chưa có người dùng mới</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
    /* === DASHBOARD SPECIFIC STYLES === */
    .course-row {
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .course-row:hover {
        background-color: #f8f9fc !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .course-title-link {
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none !important;
    }

    .course-title-link:hover {
        color: #224abe !important;
    }

    /* === CARDS STYLING === */
    .card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
    }

    /* === BORDER LEFT CARDS === */
    .border-left-primary {
        border-left: 0.375rem solid #6366f1 !important;
        background: linear-gradient(135deg, #ffffff 0%, #f8faff 100%);
    }

    .border-left-success {
        border-left: 0.375rem solid #10b981 !important;
        background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
    }

    .border-left-warning {
        border-left: 0.375rem solid #f59e0b !important;
        background: linear-gradient(135deg, #ffffff 0%, #fffbeb 100%);
    }

    .border-left-danger {
        border-left: 0.375rem solid #ef4444 !important;
        background: linear-gradient(135deg, #ffffff 0%, #fef2f2 100%);
    }

    /* === COLOR SCHEME === */
    .text-primary {
        color: #6366f1 !important;
    }

    .text-success {
        color: #10b981 !important;
    }

    .text-warning {
        color: #f59e0b !important;
    }

    .text-danger {
        color: #ef4444 !important;
    }

    .fa-users {
        color: #6366f1 !important;
    }

    .fa-book {
        color: #10b981 !important;
    }

    .fa-play-circle {
        color: #f59e0b !important;
    }

    .fa-user-graduate {
        color: #ef4444 !important;
    }

    /* === TABLE STYLING === */
    .table {
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .table thead th {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border: none;
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        padding: 1.25rem 1rem;
        text-align: center !important;
        vertical-align: middle !important;
        position: relative;
    }

    .table thead th::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 40%;
        height: 2px;
        background: linear-gradient(90deg, transparent, #6366f1, transparent);
        border-radius: 1px;
    }

    .table thead th:first-child {
        text-align: left !important;
    }

    .table thead th:first-child::after {
        left: 1rem;
        transform: none;
        width: 30%;
    }

    .table tbody td {
        border: none;
        border-bottom: 1px solid #f1f5f9;
        padding: 1.25rem 1rem;
        vertical-align: middle;
        text-align: center !important;
        transition: all 0.3s ease;
    }

    .table tbody td:first-child {
        text-align: left !important;
        font-weight: 600;
    }

    .table tbody tr:last-child td {
        border-bottom: none;
    }

    /* === BADGES === */
    .badge {
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        letter-spacing: 0.025em;
    }

    .badge-secondary {
        background: linear-gradient(135deg, #64748b, #475569);
        color: #fff;
        border: none;
    }

    .badge-primary {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #fff;
        border: none;
    }

    .badge-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff;
        border: none;
    }

    /* === BUTTONS === */
    .btn {
        border-radius: 0.5rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1.5px solid transparent;
    }

    .btn-outline-primary {
        color: #6366f1;
        border-color: #6366f1;
        background: transparent;
    }

    .btn-outline-primary:hover {
        background: #6366f1;
        border-color: #6366f1;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
    }

    .btn-outline-info {
        color: #06b6d4;
        border-color: #06b6d4;
    }

    .btn-outline-info:hover {
        background: #06b6d4;
        border-color: #06b6d4;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(6, 182, 212, 0.4);
    }

    /* === STATISTICS CARDS === */
    .text-xs {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.05em;
    }

    .h5 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0;
    }

    .fa-2x {
        font-size: 2.5rem;
        opacity: 0.8;
    }

    /* === USER CARDS === */
    .bg-light {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }

    .bg-light:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    /* === RESPONSIVE === */
    @media (max-width: 768px) {
        .table thead th {
            padding: 1rem 0.5rem;
            font-size: 0.7rem;
        }

        .table tbody td {
            padding: 1rem 0.5rem;
            font-size: 0.85rem;
        }

        .h5 {
            font-size: 1.5rem;
        }

        .fa-2x {
            font-size: 2rem;
        }
    }

    /* === ANIMATIONS === */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .card {
        animation: fadeInUp 0.6s ease forwards;
    }

    .card:nth-child(1) {
        animation-delay: 0.1s;
    }

    .card:nth-child(2) {
        animation-delay: 0.2s;
    }

    .card:nth-child(3) {
        animation-delay: 0.3s;
    }

    .card:nth-child(4) {
        animation-delay: 0.4s;
    }
</style>

<?php include 'includes/admin-footer.php'; ?>