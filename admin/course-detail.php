<?php
// filepath: d:\Xampp\htdocs\elearning\admin\course-detail.php
require_once '../includes/config.php';

// Handle AJAX requests first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if (!isLoggedIn() || !isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
        exit;
    }

    if ($_POST['ajax_action'] === 'add_lesson') {
        try {
            $course_id = (int)$_POST['course_id'];
            $title = trim($_POST['title']);
            $youtube_url = trim($_POST['youtube_url'] ?? '');
            $order_number = (int)$_POST['order_number'];

            // Validation (copy từ file add-lesson.php của bạn)
            if (empty($title)) {
                throw new Exception('Tiêu đề bài học không được để trống');
            }

            // Thêm logic từ file add-lesson.php của bạn vào đây
            // ...

            echo json_encode(['success' => true, 'message' => 'Thêm bài học thành công!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

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

// Get recent reviews - sửa lại query để lấy đúng tên column
$stmt = $pdo->prepare("
    SELECT r.*, u.username
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.course_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$course_id]);
$recent_reviews = $stmt->fetchAll();

// Check if course has any enrollments (for edit/delete lesson permissions)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
$stmt->execute([$course_id]);
$has_enrollments = $stmt->fetchColumn() > 0;

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
                            <div class="btn-group" role="group" style="display: flex; flex-wrap: nowrap; gap: 0.375rem;">
                                <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="btn btn-warning" style="white-space: nowrap; font-size: 0.875rem;">
                                    <i class="fas fa-edit me-1"></i>Chỉnh sửa
                                </a>

                                <a href="courses.php" class="btn btn-light" style="white-space: nowrap; font-size: 0.875rem;">
                                    <i class="fas fa-arrow-left me-1"></i>Quay lại
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
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addLessonModal">
                                <i class="fas fa-plus me-1"></i>Thêm bài học
                            </button>

                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (count($lessons) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="8%" class="text-center">STT</th>
                                        <th width="35%">TÊN BÀI HỌC</th>
                                        <th width="25%" class="text-center">YOUTUBE URL</th>
                                        <th width="12%" class="text-center">THỨ TỰ</th>
                                        <th width="20%" class="text-center">THAO TÁC</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lessons as $index => $lesson): ?>
                                        <tr>
                                            <td class="text-center align-middle">
                                                <span class="badge bg-light text-dark"><?php echo $index + 1; ?></span>
                                            </td>
                                            <td class="align-middle">
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
                                            <td class="text-center align-middle">
                                                <?php if (!empty($lesson['youtube_url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($lesson['youtube_url']); ?>"
                                                        target="_blank" class="btn btn-outline-danger btn-sm">
                                                        <i class="fab fa-youtube me-1"></i>Xem video
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>Chưa có video
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center align-middle">
                                                <span class="badge bg-secondary fs-6">
                                                    <?php echo $lesson['order_number'] ?? 'N/A'; ?>
                                                </span>
                                            </td>
                                            <td class="text-center align-middle">
                                                <div class="btn-group" role="group">
                                                    <?php if (!$has_enrollments): ?>
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-warning"
                                                            onclick="editLesson(<?php echo $lesson['id']; ?>, '<?php echo addslashes($lesson['title']); ?>', '<?php echo addslashes($lesson['youtube_url'] ?? ''); ?>', <?php echo $lesson['order_number']; ?>)"
                                                            title="Chỉnh sửa">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="deleteLesson(<?php echo $lesson['id']; ?>, '<?php echo addslashes($lesson['title']); ?>')"
                                                            title="Xóa">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-lock me-1"></i>Đã có học viên
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fab fa-youtube fa-4x text-danger mb-3 opacity-75"></i>
                            <h6 class="text-muted mb-3">Chưa có bài học nào</h6>
                            <p class="text-muted mb-4">Khóa học này chưa có bài học nào. Hãy thêm bài học đầu tiên để bắt đầu.</p>
                            <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addLessonModal">
                                <i class="fas fa-plus me-2"></i>Thêm bài học đầu tiên
                            </button>
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
                                <i class="fas fa-users me-1"></i>Xem tất cả <?php ?> học viên
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

                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($recent_reviews) > 0): ?>
                        <?php foreach ($recent_reviews as $review): ?>
                            <div class="d-flex align-items-center mb-3 p-2 rounded bg-light">
                                <div class="flex-shrink-0">
                                    <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center"
                                        style="width: 40px; height: 40px;">
                                        <i class="fas fa-star text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($review['username']); ?></h6>
                                    <div class="d-flex align-items-center mb-1">
                                        <div class="text-warning me-2" style="font-size: 0.8rem;">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="badge bg-warning text-dark"><?php echo $review['rating']; ?>/5</span>
                                    </div>
                                    <?php if (!empty($review['comment'])): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-quote-left me-1"></i>
                                            <?php echo htmlspecialchars(mb_substr($review['comment'], 0, 50)); ?><?php echo mb_strlen($review['comment']) > 50 ? '...' : ''; ?>
                                        </small>
                                    <?php endif; ?>
                                    <small class="text-success">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($review['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3 pt-3 border-top">
                            <a href="course-reviews.php?course_id=<?php echo $course['id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-star me-1"></i>Xem tất cả <?php  ?> đánh giá
                            </a>
                        </div>
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

<!-- Add Lesson Modal -->
<div class="modal fade" id="addLessonModal" tabindex="-1" aria-labelledby="addLessonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title" id="addLessonModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Thêm bài học mới
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Alert containers -->
                <div id="lesson_error_alert" class="alert alert-danger d-none"></div>
                <div id="lesson_success_alert" class="alert alert-success d-none"></div>

                <form id="addLessonForm">
                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">

                    <!-- Course Info -->
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-book me-2"></i>
                        <strong>Khóa học:</strong> <?php echo htmlspecialchars($course['title']); ?>
                    </div>

                    <!-- Lesson Title -->
                    <div class="mb-3">
                        <label for="lesson_title" class="form-label fw-bold">
                            Tiêu đề bài học <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                            class="form-control form-control-lg"
                            id="lesson_title"
                            name="title"
                            placeholder="Nhập tiêu đề bài học..."
                            required>
                    </div>

                    <!-- YouTube URL -->
                    <div class="mb-3">
                        <label for="lesson_youtube_url" class="form-label fw-bold">
                            <i class="fab fa-youtube text-danger me-1"></i>
                            URL YouTube
                        </label>
                        <input type="url"
                            class="form-control form-control-lg"
                            id="lesson_youtube_url"
                            name="youtube_url"
                            placeholder="https://www.youtube.com/watch?v=...">
                    </div>

                    <!-- Order Number -->
                    <div class="mb-3">
                        <label for="lesson_order" class="form-label fw-bold">
                            Thứ tự bài học <span class="text-danger">*</span>
                        </label>
                        <input type="number"
                            class="form-control form-control-lg"
                            id="lesson_order"
                            name="order_number"
                            value="<?php echo (count($lessons) > 0 ? max(array_column($lessons, 'order_number')) + 1 : 1); ?>"
                            min="1"
                            required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Hủy
                </button>
                <button type="button" class="btn btn-primary btn-lg" id="saveLesson">
                    <i class="fas fa-save me-2"></i>Thêm bài học
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Lesson Modal -->
<div class="modal fade" id="editLessonModal" tabindex="-1" aria-labelledby="editLessonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-warning text-white">
                <h5 class="modal-title" id="editLessonModalLabel">
                    <i class="fas fa-edit me-2"></i>Chỉnh sửa bài học
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editLessonForm">
                    <input type="hidden" name="lesson_id" id="edit_lesson_id">
                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">

                    <!-- Course Info -->
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-book me-2"></i>
                        <strong>Khóa học:</strong> <?php echo htmlspecialchars($course['title']); ?>
                    </div>

                    <!-- Lesson Title -->
                    <div class="mb-3">
                        <label for="edit_lesson_title" class="form-label fw-bold">
                            Tiêu đề bài học <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                            class="form-control form-control-lg"
                            id="edit_lesson_title"
                            name="title"
                            required>
                    </div>

                    <!-- YouTube URL -->
                    <div class="mb-3">
                        <label for="edit_lesson_youtube_url" class="form-label fw-bold">
                            <i class="fab fa-youtube text-danger me-1"></i>
                            URL YouTube
                        </label>
                        <input type="url"
                            class="form-control form-control-lg"
                            id="edit_lesson_youtube_url"
                            name="youtube_url">
                    </div>

                    <!-- Order Number -->
                    <div class="mb-3">
                        <label for="edit_lesson_order" class="form-label fw-bold">
                            Thứ tự bài học <span class="text-danger">*</span>
                        </label>
                        <input type="number"
                            class="form-control form-control-lg"
                            id="edit_lesson_order"
                            name="order_number"
                            min="1"
                            required>
                    </div>

                    <!-- Error/Success Display -->
                    <div id="edit_lesson_error_alert" class="alert alert-danger d-none"></div>
                    <div id="edit_lesson_success_alert" class="alert alert-success d-none"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Hủy
                </button>
                <button type="button" class="btn btn-warning btn-lg" id="updateLesson">
                    <i class="fas fa-save me-2"></i>Cập nhật
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteLessonModal" tabindex="-1" aria-labelledby="deleteLessonModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-gradient-danger text-white">
                <h5 class="modal-title" id="deleteLessonModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Xác nhận xóa bài học
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                    <h5 class="mb-3">Bạn có chắc chắn muốn xóa bài học này?</h5>
                    <p class="text-muted mb-4" id="delete_lesson_title"></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Cảnh báo:</strong> Hành động này không thể hoàn tác!
                    </div>
                </div>
                <input type="hidden" id="delete_lesson_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Hủy
                </button>
                <button type="button" class="btn btn-danger btn-lg" id="confirmDeleteLesson">
                    <i class="fas fa-trash me-2"></i>Xóa bài học
                </button>
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

    .bg-gradient-danger {
        background: linear-gradient(45deg, #e74a3b, #c82333);
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

    /* Modal Enhancements */
    .modal-content {
        border: none;
        box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
    }

    .btn-close-white {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    .form-control:focus {
        border-color: #4e73df;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }

    /* Loading spinner */
    .btn-loading {
        position: relative;
    }

    .btn-loading:disabled {
        opacity: 0.7;
    }

    .btn-loading::after {
        content: "";
        position: absolute;
        width: 16px;
        height: 16px;
        top: 50%;
        left: 50%;
        margin-left: -8px;
        margin-top: -8px;
        border: 2px solid transparent;
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .lesson-actions {
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    tr:hover .lesson-actions {
        opacity: 1;
    }
</style>

<!-- JavaScript -->
<script>
    // Simple global functions for onclick
    function editLesson(lessonId, title, youtubeUrl, orderNumber) {
        console.log('✅ Edit lesson clicked:', lessonId, title);

        // Check if elements exist
        const idInput = document.getElementById('edit_lesson_id');
        const titleInput = document.getElementById('edit_lesson_title');
        const urlInput = document.getElementById('edit_lesson_youtube_url');
        const orderInput = document.getElementById('edit_lesson_order');
        const modal = document.getElementById('editLessonModal');

        if (!idInput || !titleInput || !urlInput || !orderInput || !modal) {
            alert('❌ Không tìm thấy form chỉnh sửa!');
            console.error('Missing edit form elements');
            return;
        }

        // Fill form data
        idInput.value = lessonId;
        titleInput.value = title;
        urlInput.value = youtubeUrl || '';
        orderInput.value = orderNumber;

        // Show modal
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
    }

    function deleteLesson(lessonId, title) {
        console.log('✅ Delete lesson clicked:', lessonId, title);

        // Check if elements exist
        const idInput = document.getElementById('delete_lesson_id');
        const titleDisplay = document.getElementById('delete_lesson_title');
        const modal = document.getElementById('deleteLessonModal');

        if (!idInput || !titleDisplay || !modal) {
            alert('❌ Không tìm thấy form xóa!');
            console.error('Missing delete form elements');
            return;
        }

        // Fill data
        idInput.value = lessonId;
        titleDisplay.textContent = title;

        // Show modal
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
    }

    // DOM Content Loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('✅ Course detail page loaded');

        // Add lesson functionality
        const saveBtn = document.getElementById('saveLesson');
        if (saveBtn) {
            console.log('✅ Save button found');
            saveBtn.addEventListener('click', function() {
                console.log('📤 Adding lesson...');

                const form = document.getElementById('addLessonForm');
                if (!form) {
                    alert('❌ Không tìm thấy form thêm bài học!');
                    return;
                }

                const formData = new FormData(form);
                const title = formData.get('title')?.trim();

                if (!title) {
                    alert('❌ Vui lòng nhập tiêu đề bài học!');
                    return;
                }

                // Show loading
                const originalText = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang thêm...';

                fetch('lesson-actions.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('📡 Add response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('📊 Add response:', data);
                        if (data.success) {
                            alert('✅ ' + data.message);
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            alert('❌ ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('❌ Add error:', error);
                        alert('❌ Lỗi kết nối: ' + error.message);
                    })
                    .finally(() => {
                        this.disabled = false;
                        this.innerHTML = originalText;
                    });
            });
        } else {
            console.error('❌ Save button not found');
        }

        // Update lesson functionality
        const updateBtn = document.getElementById('updateLesson');
        if (updateBtn) {
            console.log('✅ Update button found');
            updateBtn.addEventListener('click', function() {
                console.log('📤 Updating lesson...');

                const form = document.getElementById('editLessonForm');
                if (!form) {
                    alert('❌ Không tìm thấy form chỉnh sửa!');
                    return;
                }

                const formData = new FormData(form);
                formData.set('action', 'edit_lesson');

                const title = formData.get('title')?.trim();
                if (!title) {
                    alert('❌ Vui lòng nhập tiêu đề bài học!');
                    return;
                }

                // Show loading
                const originalText = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang cập nhật...';

                fetch('lesson-actions.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('📡 Update response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('📊 Update response:', data);
                        if (data.success) {
                            alert('✅ ' + data.message);
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            alert('❌ ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('❌ Update error:', error);
                        alert('❌ Lỗi kết nối: ' + error.message);
                    })
                    .finally(() => {
                        this.disabled = false;
                        this.innerHTML = originalText;
                    });
            });
        } else {
            console.error('❌ Update button not found');
        }

        // Delete lesson functionality
        const confirmDeleteBtn = document.getElementById('confirmDeleteLesson');
        if (confirmDeleteBtn) {
            console.log('✅ Confirm delete button found');
            confirmDeleteBtn.addEventListener('click', function() {
                console.log('📤 Deleting lesson...');

                const lessonId = document.getElementById('delete_lesson_id')?.value;
                const courseId = <?php echo $course['id']; ?>;

                if (!lessonId) {
                    alert('❌ Không tìm thấy ID bài học!');
                    return;
                }

                // Show loading
                const originalText = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xóa...';

                fetch('lesson-actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'delete_lesson',
                            lesson_id: parseInt(lessonId),
                            course_id: parseInt(courseId)
                        })
                    })
                    .then(response => {
                        console.log('📡 Delete response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('📊 Delete response:', data);
                        if (data.success) {
                            alert('✅ ' + data.message);
                            bootstrap.Modal.getInstance(document.getElementById('deleteLessonModal')).hide();
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            alert('❌ ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('❌ Delete error:', error);
                        alert('❌ Lỗi kết nối: ' + error.message);
                    })
                    .finally(() => {
                        this.disabled = false;
                        this.innerHTML = originalText;
                    });
            });
        } else {
            console.error('❌ Confirm delete button not found');
        }
    });
</script>

<?php include 'includes/admin-footer.php'; ?>