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
    .course-row {
        transition: background-color 0.2s ease;
    }

    .course-row:hover {
        background-color: #f8f9fc !important;
    }

    .course-title-link {
        font-weight: 600;
        transition: color 0.2s ease;
    }

    .course-title-link:hover {
        color: #224abe !important;
        text-decoration: none !important;
    }

    .card {
        border: none;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }

    .border-left-primary {
        border-left: 0.25rem solid #4e73df !important;
    }

    .border-left-success {
        border-left: 0.25rem solid #1cc88a !important;
    }

    .border-left-warning {
        border-left: 0.25rem solid #f6c23e !important;
    }

    .border-left-danger {
        border-left: 0.25rem solid #e74a3b !important;
    }

    .text-primary {
        color: #4e73df !important;
    }

    .text-success {
        color: #1cc88a !important;
    }

    .text-warning {
        color: #f6c23e !important;
    }

    .text-danger {
        color: #e74a3b !important;
    }

    .badge-secondary {
        background-color: #6c757d;
        color: #fff;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
    }

    .badge-primary {
        background-color: #4e73df;
        color: #fff;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
    }

    .badge-danger {
        background-color: #e74a3b;
        color: #fff;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
    }

    .table th {
        border-top: none;
        border-bottom: 1px solid #e3e6f0;
        font-weight: 600;
        font-size: 0.8rem;
        color: #5a5c69;
        padding: 1rem 0.75rem;
    }

    .table td {
        border-top: 1px solid #e3e6f0;
        padding: 1rem 0.75rem;
        vertical-align: middle;
    }

    .bg-light {
        background-color: #f8f9fc !important;
    }
</style>

<?php include 'includes/admin-footer.php'; ?>