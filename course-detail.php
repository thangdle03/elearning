
<?php
require_once 'includes/config.php';

// Get course ID
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$course_id) {
    redirect(SITE_URL . '/courses.php');
}

// Get course details
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name,
           (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
    FROM courses c 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    redirect(SITE_URL . '/courses.php');
}

$page_title = $course['title'];

// Get course lessons
$stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_number ASC");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

// Check if user is enrolled
$is_enrolled = false;
if (isLoggedIn()) {
    $is_enrolled = isEnrolled($_SESSION['user_id'], $course_id, $pdo);
}

// Handle enrollment
$enrollment_message = '';
if ($_POST && isset($_POST['enroll']) && isLoggedIn()) {
    if (!$is_enrolled) {
        try {
            $stmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $course_id]);
            $is_enrolled = true;
            $enrollment_message = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Đăng ký khóa học thành công!</div>';
        } catch (Exception $e) {
            $enrollment_message = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Có lỗi xảy ra. Vui lòng thử lại!</div>';
        }
    }
}

// Get related courses
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name 
    FROM courses c 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    WHERE c.category_id = ? AND c.id != ? 
    ORDER BY RAND() 
    LIMIT 3
");
$stmt->execute([$course['category_id'], $course_id]);
$related_courses = $stmt->fetchAll();
?>

<?php include 'includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="bg-light py-3">
    <div class="container">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="<?php echo SITE_URL; ?>">Trang chủ</a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?php echo SITE_URL; ?>/courses.php">Khóa học</a>
            </li>
            <li class="breadcrumb-item active">
                <?php echo htmlspecialchars($course['title']); ?>
            </li>
        </ol>
    </div>
</nav>

<!-- Course Header -->
<div class="bg-primary text-white py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="mb-3">
                    <span class="badge bg-light text-dark">
                        <?php echo $course['category_name'] ?: 'Chưa phân loại'; ?>
                    </span>
                </div>
                
                <h1 class="display-5 fw-bold mb-3">
                    <?php echo htmlspecialchars($course['title']); ?>
                </h1>
                
                <p class="lead mb-4">
                    <?php echo htmlspecialchars($course['description']); ?>
                </p>
                
                <!-- Course Stats -->
                <div class="row text-center mb-4">
                    <div class="col-4">
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="bi bi-play-circle me-2 fs-4"></i>
                            <div>
                                <div class="fw-bold"><?php echo $course['lesson_count']; ?></div>
                                <small>Bài học</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="bi bi-people me-2 fs-4"></i>
                            <div>
                                <div class="fw-bold"><?php echo $course['student_count']; ?></div>
                                <small>Học viên</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="bi bi-clock me-2 fs-4"></i>
                            <div>
                                <div class="fw-bold">∞</div>
                                <small>Trọn đời</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <img src="<?php echo $course['thumbnail'] ?: 'https://via.placeholder.com/400x250?text=' . urlencode($course['title']); ?>" 
                         class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>">
                    <div class="card-body text-center">
                        <h3 class="text-primary fw-bold mb-3">
                            <?php echo formatPrice($course['price']); ?>
                        </h3>
                        
                        <?php echo $enrollment_message; ?>
                        
                        <?php if (isLoggedIn()): ?>
                            <?php if ($is_enrolled): ?>
                                <a href="<?php echo SITE_URL; ?>/learn.php?course=<?php echo $course_id; ?>" 
                                   class="btn btn-success btn-lg w-100 mb-2">
                                    <i class="bi bi-play-fill me-2"></i>Tiếp tục học
                                </a>
                                <small class="text-muted">
                                    <i class="bi bi-check-circle me-1"></i>Đã đăng ký khóa học
                                </small>
                            <?php else: ?>
                                <form method="POST" class="d-inline">
                                    <button type="submit" name="enroll" class="btn btn-primary btn-lg w-100 mb-2">
                                        <i class="bi bi-plus-circle me-2"></i>
                                        <?php echo $course['price'] > 0 ? 'Mua khóa học' : 'Đăng ký miễn phí'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-primary btn-lg w-100 mb-2">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập để học
                            </a>
                            <small class="text-muted">
                                Cần đăng nhập để truy cập khóa học
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Course Content -->
<div class="container my-5">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Course Description -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>Mô tả khóa học
                    </h4>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                </div>
            </div>
            
            <!-- Course Curriculum -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>Nội dung khóa học
                    </h4>
                    <span class="badge bg-primary"><?php echo count($lessons); ?> bài học</span>
                </div>
                <div class="card-body p-0">
                    <?php if ($lessons): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($lessons as $index => $lesson): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-secondary me-3"><?php echo $index + 1; ?></span>
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($lesson['title']); ?></h6>
                                    <?php if ($is_enrolled || !isLoggedIn()): ?>
                                    <small class="text-muted">
                                        <i class="bi bi-play-circle me-1"></i>Video bài học
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center">
                                <?php if ($is_enrolled): ?>
                                    <a href="<?php echo SITE_URL; ?>/learn.php?course=<?php echo $course_id; ?>&lesson=<?php echo $lesson['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-play-fill me-1"></i>Xem
                                    </a>
                                <?php elseif (isLoggedIn()): ?>
                                    <i class="bi bi-lock text-muted" title="Cần đăng ký khóa học"></i>
                                <?php else: ?>
                                    <i class="bi bi-lock text-muted" title="Cần đăng nhập"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-folder2-open display-4 mb-3"></i>
                        <p class="mb-0">Chưa có bài học nào trong khóa học này.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- What You'll Learn -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="bi bi-lightbulb me-2"></i>Bạn sẽ học được gì?
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Nắm vững kiến thức cơ bản
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Thực hành qua các dự án thực tế
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Áp dụng vào công việc ngay lập tức
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Hỗ trợ từ cộng đồng học viên
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Cập nhật kiến thức mới nhất
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Chứng chỉ hoàn thành khóa học
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Course Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Thông tin khóa học</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Giá:</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-primary fw-bold"><?php echo formatPrice($course['price']); ?></span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Danh mục:</strong>
                        </div>
                        <div class="col-6">
                            <span class="badge bg-secondary"><?php echo $course['category_name'] ?: 'Chưa phân loại'; ?></span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Ngày tạo:</strong>
                        </div>
                        <div class="col-6">
                            <?php echo date('d/m/Y', strtotime($course['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <strong>Cập nhật:</strong>
                        </div>
                        <div class="col-6">
                            <?php echo date('d/m/Y', strtotime($course['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Share Course -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Chia sẻ khóa học</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="shareToFacebook()">
                            <i class="bi bi-facebook me-2"></i>Facebook
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="shareToTwitter()">
                            <i class="bi bi-twitter me-2"></i>Twitter
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="copyLink()">
                            <i class="bi bi-link-45deg me-2"></i>Sao chép link
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Related Courses -->
    <?php if ($related_courses): ?>
    <div class="mt-5">
        <h3 class="mb-4">Khóa học liên quan</h3>
        <div class="row">
            <?php foreach ($related_courses as $related): ?>
            <div class="col-md-4 mb-4">
                <div class="card course-card h-100">
                    <img src="<?php echo $related['thumbnail'] ?: 'https://via.placeholder.com/300x200?text=' . urlencode($related['title']); ?>" 
                         class="card-img-top" alt="<?php echo htmlspecialchars($related['title']); ?>"
                         style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <div class="mb-2">
                            <span class="badge bg-secondary"><?php echo $related['category_name'] ?: 'Chưa phân loại'; ?></span>
                        </div>
                        <h6 class="card-title">
                            <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $related['id']; ?>" 
                               class="text-decoration-none">
                                <?php echo htmlspecialchars($related['title']); ?>
                            </a>
                        </h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-primary fw-bold"><?php echo formatPrice($related['price']); ?></span>
                            <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $related['id']; ?>" 
                               class="btn btn-sm btn-outline-primary">Xem</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript -->
<script>
function shareToFacebook() {
    const url = encodeURIComponent(window.location.href);
    window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank', 'width=600,height=400');
}

function shareToTwitter() {
    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent('<?php echo htmlspecialchars($course['title']); ?>');
    window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`, '_blank', 'width=600,height=400');
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(function() {
        alert('Đã sao chép link khóa học!');
    });
}
</script>

<?php include 'includes/footer.php'; ?>