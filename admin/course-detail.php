<?php
// filepath: d:\Xampp\htdocs\elearning\admin\course-detail.php
require_once '../includes/config.php';

// Check authentication
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Get course ID
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($course_id <= 0) {
    header('Location: courses.php');
    exit;
}

// Get course info with category
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name, cat.slug as category_slug
    FROM courses c 
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php');
    exit;
}

// Get course statistics - sửa lại query để phù hợp với structure thực tế
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(DISTINCT user_id) FROM enrollments WHERE course_id = ?) as total_enrollments,
        (SELECT COUNT(*) FROM lessons WHERE course_id = ?) as total_lessons,
        (SELECT COUNT(*) FROM reviews WHERE course_id = ? AND status = 'active') as total_reviews,
        (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE course_id = ? AND status = 'active') as avg_rating
");
$stmt->execute([$course_id, $course_id, $course_id, $course_id]);
$stats = $stmt->fetch();

// Get recent enrollments
$stmt = $pdo->prepare("
    SELECT e.user_id, e.course_id, e.enrolled_at, u.username, u.email
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    WHERE e.course_id = ?
    ORDER BY e.enrolled_at DESC
    LIMIT 8
");
$stmt->execute([$course_id]);
$recent_enrollments = $stmt->fetchAll();

// Get lessons - sửa query để match với columns thực tế (order_number thay vì lesson_order)
$stmt = $pdo->prepare("
    SELECT id, course_id, title, youtube_url, order_number
    FROM lessons 
    WHERE course_id = ? 
    ORDER BY order_number ASC, id ASC
    LIMIT 10
");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

// Get recent reviews
$stmt = $pdo->prepare("
    SELECT r.*, u.username
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.course_id = ? AND r.status = 'active'
    ORDER BY r.created_at DESC
    LIMIT 6
");
$stmt->execute([$course_id]);
$recent_reviews = $stmt->fetchAll();

$page_title = 'Chi tiết khóa học: ' . $course['title'];
$current_page = 'courses';
?>

<?php include 'includes/admin-header.php'; ?>

<div class="container-fluid px-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item"><a href="courses.php"><i class="fas fa-book me-1"></i>Khóa học</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($course['title']); ?></li>
        </ol>
    </nav>

    <!-- Course Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-lg">
                <div class="card-header bg-gradient-primary text-white py-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <div class="course-thumbnail me-4">
                                    <?php if (!empty($course['thumbnail'])): ?>
                                        <img src="<?php echo htmlspecialchars($course['thumbnail']); ?>"
                                            alt="" class="rounded shadow" style="width: 120px; height: 80px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-white bg-opacity-25 rounded d-flex align-items-center justify-content-center"
                                            style="width: 120px; height: 80px;">
                                            <i class="fas fa-image fa-2x text-white-50"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h1 class="h2 mb-2 text-white"><?php echo htmlspecialchars($course['title']); ?></h1>
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <span class="badge <?php echo $course['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?> fs-6">
                                            <i class="fas fa-<?php echo $course['status'] === 'active' ? 'check-circle' : 'pause-circle'; ?> me-1"></i>
                                            <?php echo $course['status'] === 'active' ? 'Hoạt động' : 'Tạm dừng'; ?>
                                        </span>
                                        <?php if ($course['category_name']): ?>
                                            <span class="badge bg-info fs-6">
                                                <i class="fas fa-folder me-1"></i>
                                                <?php echo htmlspecialchars($course['category_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($stats['avg_rating'] > 0): ?>
                                            <span class="badge bg-warning fs-6">
                                                <i class="fas fa-star me-1"></i>
                                                <?php echo number_format($stats['avg_rating'], 1); ?>/5
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-white-75">
                                        <i class="fas fa-calendar me-1"></i>
                                        Tạo ngày: <?php echo date('d/m/Y H:i', strtotime($course['created_at'])); ?>
                                        <?php if ($course['updated_at']): ?>
                                            | Cập nhật: <?php echo date('d/m/Y H:i', strtotime($course['updated_at'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 text-end">
                            <div class="btn-group" role="group">
                                <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="btn btn-warning btn-lg">
                                    <i class="fas fa-edit me-2"></i>Chỉnh sửa
                                </a>
                                <a href="course-reviews.php?course_id=<?php echo $course['id']; ?>" class="btn btn-success btn-lg">
                                    <i class="fas fa-star me-2"></i>Reviews
                                </a>
                                <a href="courses.php" class="btn btn-light btn-lg">
                                    <i class="fas fa-arrow-left me-2"></i>Quay lại
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm bg-gradient-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase fw-bold small mb-1">Học viên đăng ký</div>
                            <div class="h2 mb-0 fw-bold"><?php echo number_format($stats['total_enrollments'] ?? 0); ?></div>
                        </div>
                        <div class="fa-3x opacity-50">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm bg-gradient-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase fw-bold small mb-1">Tổng bài học</div>
                            <div class="h2 mb-0 fw-bold"><?php echo number_format($stats['total_lessons'] ?? 0); ?></div>
                        </div>
                        <div class="fa-3x opacity-50">
                            <i class="fas fa-play-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm bg-gradient-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase fw-bold small mb-1">Đánh giá</div>
                            <div class="h2 mb-0 fw-bold"><?php echo number_format($stats['total_reviews'] ?? 0); ?></div>
                            <?php if ($stats['avg_rating'] > 0): ?>
                                <div class="small">
                                    <i class="fas fa-star text-warning me-1"></i>
                                    <?php echo number_format($stats['avg_rating'], 1); ?>/5
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="fa-3x opacity-50">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm bg-gradient-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase fw-bold small mb-1">Giá khóa học</div>
                            <div class="h2 mb-0 fw-bold">
                                <?php echo $course['price'] > 0 ? number_format($course['price']) . 'đ' : 'Miễn phí'; ?>
                            </div>
                        </div>
                        <div class="fa-3x opacity-50">
                            <i class="fas fa-<?php echo $course['price'] > 0 ? 'dollar-sign' : 'gift'; ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Course Information -->
        <div class="col-lg-8 mb-4">
            <!-- Course Description -->
            <div class="card border-0 shadow mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="fas fa-info-circle me-2"></i>Mô tả khóa học
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($course['description'])): ?>
                        <div class="course-description">
                            <?php echo nl2br(htmlspecialchars($course['description'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Chưa có mô tả cho khóa học này.</p>
                            <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Thêm mô tả
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lessons List -->
            <div class="card border-0 shadow mb-4">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-primary">
                            <i class="fas fa-play-circle me-2"></i>Danh sách bài học (<?php echo count($lessons); ?>)
                        </h5>
                        <a href="course-lessons.php?course_id=<?php echo $course['id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-cog me-1"></i>Quản lý
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (count($lessons) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="8%">STT</th>
                                        <th width="50%">Tên bài học</th>
                                        <th width="25%">YouTube URL</th>
                                        <th width="17%">Thứ tự</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lessons as $index => $lesson): ?>
                                        <tr>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark"><?php echo $lesson['order_number'] ?? ($index + 1); ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="fab fa-youtube text-danger me-2 fa-lg"></i>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($lesson['title']); ?></h6>
                                                        <small class="text-muted">
                                                            <i class="fas fa-hashtag me-1"></i>Bài học #<?php echo $lesson['id']; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($lesson['youtube_url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($lesson['youtube_url']); ?>"
                                                        target="_blank" class="btn btn-outline-danger btn-sm">
                                                        <i class="fab fa-youtube me-1"></i>Xem video
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>Chưa có video
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary fs-6">
                                                    <?php echo $lesson['order_number'] ?? 'N/A'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($lessons) >= 10): ?>
                            <div class="card-footer text-center bg-light">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Hiển thị 10 bài học đầu tiên theo thứ tự
                                </small>
                                <a href="course-lessons.php?course_id=<?php echo $course['id']; ?>" class="btn btn-link btn-sm">
                                    <i class="fas fa-arrow-right me-1"></i>Xem tất cả
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fab fa-youtube fa-4x text-danger mb-3 opacity-75"></i>
                            <h6 class="text-muted mb-3">Chưa có bài học nào</h6>
                            <p class="text-muted mb-4">Khóa học này chưa có bài học nào. Hãy thêm bài học đầu tiên để bắt đầu.</p>
                            <a href="course-lessons.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus me-2"></i>Thêm bài học đầu tiên
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Recent Enrollments -->
            <div class="card border-0 shadow mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 text-primary">
                        <i class="fas fa-user-plus me-2"></i>Học viên mới nhất
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (count($recent_enrollments) > 0): ?>
                        <?php foreach ($recent_enrollments as $enrollment): ?>
                            <div class="d-flex align-items-center mb-3 p-2 rounded bg-light">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                                        style="width: 40px; height: 40px;">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($enrollment['username']); ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-envelope me-1"></i>
                                        <?php echo htmlspecialchars($enrollment['email']); ?>
                                    </small>
                                    <br><small class="text-success">
                                        <i class="fas fa-clock me-1"></i>
                                        Đăng ký: <?php echo date('d/m/Y H:i', strtotime($enrollment['enrolled_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3 pt-3 border-top">
                            <a href="course-students.php?course_id=<?php echo $course['id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-users me-1"></i>Xem tất cả <?php echo $stats['total_enrollments']; ?> học viên
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3 opacity-50"></i>
                            <h6 class="text-muted mb-2">Chưa có học viên nào đăng ký</h6>
                            <p class="text-muted small mb-0">Hãy chia sẻ khóa học để thu hút học viên.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Reviews -->
            <div class="card border-0 shadow">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-primary">
                            <i class="fas fa-star me-2"></i>Đánh giá gần đây
                        </h6>
                        <?php if (count($recent_reviews) > 0): ?>
                            <a href="course-reviews.php?course_id=<?php echo $course['id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i>Xem tất cả
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($recent_reviews) > 0): ?>
                        <?php foreach ($recent_reviews as $index => $review): ?>
                            <div class="mb-3 pb-3 <?php echo $index < count($recent_reviews) - 1 ? 'border-bottom' : ''; ?>">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center"
                                            style="width: 36px; height: 36px;">
                                            <i class="fas fa-user text-white small"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="mb-0 small fw-bold"><?php echo htmlspecialchars($review['username']); ?></h6>
                                            <small class="text-muted"><?php echo date('d/m', strtotime($review['created_at'])); ?></small>
                                        </div>
                                        <div class="text-warning mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?> fa-xs"></i>
                                            <?php endfor; ?>
                                            <span class="text-muted small ms-1">(<?php echo $review['rating']; ?>/5)</span>
                                        </div>
                                        <?php if (!empty($review['comment'])): ?>
                                            <p class="mb-0 small text-muted lh-sm">
                                                <i class="fas fa-quote-left me-1 text-primary"></i>
                                                <?php echo htmlspecialchars(mb_substr($review['comment'], 0, 120)); ?><?php echo mb_strlen($review['comment']) > 120 ? '...' : ''; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (count($recent_reviews) >= 6): ?>
                            <div class="text-center pt-2 border-top">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Hiển thị 6 đánh giá gần nhất
                                </small>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-star fa-3x text-muted mb-3 opacity-50"></i>
                            <h6 class="text-muted mb-2">Chưa có đánh giá nào</h6>
                            <p class="text-muted small mb-0">Khóa học này chưa có đánh giá từ học viên.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
<style>
    .bg-gradient-primary {
        background: linear-gradient(45deg, #4e73df, #224abe);
    }

    .bg-gradient-success {
        background: linear-gradient(45deg, #1cc88a, #13855c);
    }

    .bg-gradient-info {
        background: linear-gradient(45deg, #36b9cc, #258391);
    }

    .bg-gradient-warning {
        background: linear-gradient(45deg, #f6c23e, #d4a013);
    }

    .card {
        border: none !important;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
    }

    .course-description {
        line-height: 1.8;
        font-size: 1rem;
    }

    .table th {
        border-bottom: 2px solid #e3e6f0;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .table td {
        vertical-align: middle;
        border-bottom: 1px solid #f8f9fc;
    }

    .table tbody tr:hover {
        background-color: #f8f9fc;
    }

    .breadcrumb-item a {
        text-decoration: none;
        color: #5a5c69;
    }

    .breadcrumb-item a:hover {
        color: #4e73df;
    }

    .lh-sm {
        line-height: 1.3 !important;
    }

    .bg-light {
        background-color: #f8f9fc !important;
    }
</style>

<?php include 'includes/admin-footer.php'; ?>