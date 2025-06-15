<?php
// filepath: d:\Xampp\htdocs\elearning\admin\user-detail.php
require_once '../includes/config.php';

// Check admin authentication
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$page_title = 'Chi tiết người dùng';
$current_page = 'users';

// Get user ID
$user_id = (int)($_GET['id'] ?? 0);

if ($user_id <= 0) {
    header('Location: users.php');
    exit;
}

// Get user information
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: users.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: users.php');
    exit;
}

// Get user's enrollments
try {
    $stmt = $pdo->prepare("
        SELECT e.*, c.title, c.price, c.thumbnail 
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$user_id]);
    $enrollments = $stmt->fetchAll();
} catch (Exception $e) {
    $enrollments = [];
}

// Get user's progress
try {
    $stmt = $pdo->prepare("
        SELECT p.*, l.title as lesson_title, c.title as course_title
        FROM progress p
        JOIN lessons l ON p.lesson_id = l.id
        JOIN courses c ON l.course_id = c.id
        WHERE p.user_id = ?
        ORDER BY p.completed_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $progress = $stmt->fetchAll();
} catch (Exception $e) {
    $progress = [];
}

// Update page title with username
$page_title = 'Chi tiết: ' . htmlspecialchars($user['username']);
?>

<?php include 'includes/admin-header.php'; ?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="users.php">Người dùng</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($user['username']); ?></li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-user me-2"></i>Chi tiết người dùng
            </h1>
            <p class="text-muted mb-0">Thông tin chi tiết và hoạt động của người dùng</p>
        </div>
        <div class="d-flex gap-2">
            <a href="users.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Quay lại
            </a>
            <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i>Chỉnh sửa
            </a>
        </div>
    </div>

    <div class="row">
        <!-- User Profile Card -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-gradient-primary text-white text-center py-4">
                    <div class="mb-3">
                        <div class="avatar-circle-large bg-white text-primary mx-auto">
                            <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'user-shield' : 'user'; ?> fa-3x"></i>
                        </div>
                    </div>
                    <h4 class="mb-1 fw-bold"><?php echo htmlspecialchars($user['username']); ?></h4>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-envelope me-1"></i>
                        <?php echo htmlspecialchars($user['email']); ?>
                    </p>
                </div>
                <div class="card-body p-4">
                    <div class="user-info">
                        <div class="info-item">
                            <strong>ID:</strong>
                            <span class="badge bg-secondary fs-6"><?php echo $user['id']; ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Vai trò:</strong>
                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'primary' : 'success'; ?> fs-6">
                                <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'shield-alt' : 'user'; ?> me-1"></i>
                                <?php echo $user['role'] === 'admin' ? 'Quản trị viên' : 'Người dùng'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <strong>Trạng thái:</strong>
                            <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                                <i class="fas fa-<?php echo $user['status'] === 'active' ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                <?php echo $user['status'] === 'active' ? 'Hoạt động' : 'Tạm dừng'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <strong>Ngày tham gia:</strong>
                            <span class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <strong>Cập nhật cuối:</strong>
                            <span class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="mb-3 fw-bold">Thao tác nhanh</h6>
                            <div class="d-grid gap-2">
                                <form method="POST" action="users.php" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $user['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <button type="submit" class="btn btn-sm btn-<?php echo $user['status'] === 'active' ? 'warning' : 'success'; ?> w-100">
                                        <i class="fas fa-<?php echo $user['status'] === 'active' ? 'pause' : 'play'; ?> me-1"></i>
                                        <?php echo $user['status'] === 'active' ? 'Tạm dừng' : 'Kích hoạt'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm border-start border-primary border-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                        Khóa học đã đăng ký
                                    </div>
                                    <div class="h4 mb-0 fw-bold text-dark">
                                        <?php echo count($enrollments); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-graduation-cap fa-2x text-primary opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm border-start border-success border-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                        Tổng chi tiêu
                                    </div>
                                    <div class="h4 mb-0 fw-bold text-dark">
                                        <?php
                                        $total = array_sum(array_column($enrollments, 'price'));
                                        echo $total > 0 ? number_format($total) . ' VNĐ' : 'Miễn phí';
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-success opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="card shadow border-0">
                <div class="card-header bg-white border-bottom-0">
                    <ul class="nav nav-tabs card-header-tabs" id="userDetailTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button" role="tab">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Khóa học (<?php echo count($enrollments); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="progress-tab" data-bs-toggle="tab" data-bs-target="#progress" type="button" role="tab">
                                <i class="fas fa-chart-line me-2"></i>
                                Tiến độ học tập
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="userDetailTabsContent">
                        <!-- Courses Tab -->
                        <div class="tab-pane fade show active" id="courses" role="tabpanel">
                            <?php if ($enrollments): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="border-0">Khóa học</th>
                                                <th class="border-0 text-center">Giá</th>
                                                <th class="border-0 text-center">Ngày đăng ký</th>
                                                <th class="border-0 text-center">Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($enrollments as $enrollment): ?>
                                                <tr>
                                                    <td class="border-0">
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?php echo $enrollment['thumbnail'] ?: 'https://via.placeholder.com/60x40?text=No+Image'; ?>"
                                                                class="rounded me-3 shadow-sm"
                                                                style="width: 60px; height: 40px; object-fit: cover;">
                                                            <div>
                                                                <h6 class="mb-0 fw-semibold">
                                                                    <?php echo htmlspecialchars($enrollment['title']); ?>
                                                                </h6>
                                                                <small class="text-muted">ID: <?php echo $enrollment['course_id']; ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="border-0 text-center">
                                                        <span class="badge bg-<?php echo $enrollment['price'] > 0 ? 'success' : 'primary'; ?> fs-6">
                                                            <?php echo $enrollment['price'] > 0 ? number_format($enrollment['price']) . ' VNĐ' : 'Miễn phí'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="border-0 text-center">
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar-alt me-1"></i>
                                                            <?php echo date('d/m/Y', strtotime($enrollment['enrolled_at'])); ?>
                                                        </small>
                                                    </td>
                                                    <td class="border-0 text-center">
                                                        <a href="course-detail.php?id=<?php echo $enrollment['course_id']; ?>"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i>Xem
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-graduation-cap fa-4x text-muted mb-4"></i>
                                    <h4 class="text-muted mb-2">Chưa đăng ký khóa học nào</h4>
                                    <p class="text-muted">Người dùng này chưa đăng ký khóa học nào trong hệ thống.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Progress Tab -->
                        <div class="tab-pane fade" id="progress" role="tabpanel">
                            <?php if ($progress): ?>
                                <div class="timeline">
                                    <?php foreach ($progress as $item): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-success">
                                                <i class="fas fa-check text-white"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($item['lesson_title']); ?></h6>
                                                <p class="text-muted mb-1">
                                                    <i class="fas fa-book me-1"></i>
                                                    <?php echo htmlspecialchars($item['course_title']); ?>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Hoàn thành: <?php echo date('d/m/Y H:i', strtotime($item['completed_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-chart-line fa-4x text-muted mb-4"></i>
                                    <h4 class="text-muted mb-2">Chưa có tiến độ học tập</h4>
                                    <p class="text-muted">Người dùng này chưa hoàn thành bài học nào.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .avatar-circle-large {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .user-info .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e9ecef;
    }

    .user-info .info-item:last-child {
        border-bottom: none;
    }

    .text-xs {
        font-size: 0.75rem;
    }

    .nav-tabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        background: none;
        color: #6c757d;
        font-weight: 500;
    }

    .nav-tabs .nav-link.active {
        color: #495057;
        border-bottom-color: #667eea;
        background: none;
    }

    .nav-tabs .nav-link:hover {
        border-bottom-color: #667eea;
        color: #495057;
    }

    .timeline {
        position: relative;
        padding-left: 3rem;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 1rem;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 2rem;
    }

    .timeline-marker {
        position: absolute;
        left: -2.5rem;
        top: 0;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 3px solid #fff;
        box-shadow: 0 0 0 3px #e9ecef;
    }

    .timeline-content {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 0.5rem;
        border-left: 3px solid #28a745;
    }
</style>

<?php include 'includes/admin-footer.php'; ?>