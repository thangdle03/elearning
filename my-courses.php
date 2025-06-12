<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=/my-courses.php');
}

$page_title = 'Khóa học của tôi';
$user_id = $_SESSION['user_id'];

// Get enrolled courses with progress
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        cat.name as category_name,
        e.enrolled_at,
        (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as total_lessons,
        (SELECT COUNT(*) FROM progress p 
         JOIN lessons l ON p.lesson_id = l.id 
         WHERE l.course_id = c.id AND p.user_id = ? AND p.completed = 1) as completed_lessons,
        (SELECT l.id FROM lessons l 
         LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ?
         WHERE l.course_id = c.id 
         ORDER BY CASE WHEN p.completed IS NULL OR p.completed = 0 THEN 0 ELSE 1 END, l.order_number 
         LIMIT 1) as next_lesson_id
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE e.user_id = ?
    ORDER BY e.enrolled_at DESC
");
$stmt->execute([$user_id, $user_id, $user_id]);
$enrolled_courses = $stmt->fetchAll();

// Get recently accessed courses (last 5)
$stmt = $pdo->prepare("
    SELECT DISTINCT
        c.*,
        cat.name as category_name,
        p.completed_at as last_accessed
    FROM progress p
    JOIN lessons l ON p.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    JOIN enrollments e ON c.id = e.course_id AND e.user_id = ?
    WHERE p.user_id = ?
    ORDER BY p.completed_at DESC
    LIMIT 5
");
$stmt->execute([$user_id, $user_id]);
$recent_courses = $stmt->fetchAll();

// Get completion statistics
$total_enrolled = count($enrolled_courses);
$completed_courses = 0;
$total_progress = 0;

foreach ($enrolled_courses as $course) {
    if ($course['total_lessons'] > 0) {
        $progress = ($course['completed_lessons'] / $course['total_lessons']) * 100;
        $total_progress += $progress;
        
        if ($progress >= 100) {
            $completed_courses++;
        }
    }
}

$average_progress = $total_enrolled > 0 ? round($total_progress / $total_enrolled) : 0;

// Handle unenroll request
if ($_POST && isset($_POST['unenroll_course'])) {
    $course_id = (int)$_POST['course_id'];
    
    try {
        // Check if user is enrolled
        $stmt = $pdo->prepare("SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$user_id, $course_id]);
        
        if ($stmt->fetch()) {
            // Remove enrollment
            $stmt = $pdo->prepare("DELETE FROM enrollments WHERE user_id = ? AND course_id = ?");
            $stmt->execute([$user_id, $course_id]);
            
            // Remove progress
            $stmt = $pdo->prepare("
                DELETE p FROM progress p 
                JOIN lessons l ON p.lesson_id = l.id 
                WHERE p.user_id = ? AND l.course_id = ?
            ");
            $stmt->execute([$user_id, $course_id]);
            
            $_SESSION['success_message'] = 'Đã hủy đăng ký khóa học thành công!';
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Có lỗi xảy ra khi hủy đăng ký!';
    }
    
    redirect(SITE_URL . '/my-courses.php');
}
?>

<?php include 'includes/header.php'; ?>

<!-- Page Header -->
<div class="bg-primary text-white py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 mb-0">
                    <i class="bi bi-bookmarks me-2"></i>Khóa học của tôi
                </h1>
                <p class="mb-0 mt-2">Quản lý và tiếp tục học tập</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-light">
                    <i class="bi bi-plus-circle me-2"></i>Tìm khóa học mới
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show m-3" role="alert">
    <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['error_message']); endif; ?>

<div class="container my-5">
    <!-- Statistics Cards -->
    <div class="row mb-5">
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-book display-4 text-primary mb-3"></i>
                    <h3 class="fw-bold"><?php echo $total_enrolled; ?></h3>
                    <p class="text-muted mb-0">Khóa học đã đăng ký</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-trophy display-4 text-success mb-3"></i>
                    <h3 class="fw-bold"><?php echo $completed_courses; ?></h3>
                    <p class="text-muted mb-0">Khóa học hoàn thành</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up display-4 text-warning mb-3"></i>
                    <h3 class="fw-bold"><?php echo $average_progress; ?>%</h3>
                    <p class="text-muted mb-0">Tiến độ trung bình</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-clock-history display-4 text-info mb-3"></i>
                    <h3 class="fw-bold"><?php echo count($recent_courses); ?></h3>
                    <p class="text-muted mb-0">Học gần đây</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recently Accessed Courses -->
    <?php if ($recent_courses): ?>
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold">
                <i class="bi bi-clock-history me-2"></i>Học gần đây
            </h3>
        </div>
        
        <div class="row">
            <?php foreach ($recent_courses as $course): ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card recent-course-card h-100">
                    <img src="<?php echo $course['thumbnail'] ?: 'https://via.placeholder.com/300x150?text=' . urlencode($course['title']); ?>" 
                         class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>"
                         style="height: 150px; object-fit: cover;">
                    <div class="card-body">
                        <span class="badge bg-secondary mb-2"><?php echo $course['category_name'] ?: 'Khóa học'; ?></span>
                        <h6 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h6>
                        <small class="text-muted">
                            <i class="bi bi-clock me-1"></i>
                            Học lần cuối: <?php echo date('d/m/Y H:i', strtotime($course['last_accessed'])); ?>
                        </small>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="<?php echo SITE_URL; ?>/learn.php?course=<?php echo $course['id']; ?>" 
                           class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-play-fill me-1"></i>Tiếp tục học
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- All Enrolled Courses -->
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold">
                <i class="bi bi-collection me-2"></i>Tất cả khóa học
            </h3>
            
            <!-- Filter/Sort Options -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-funnel me-1"></i>Sắp xếp
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="?sort=recent">Mới nhất</a></li>
                    <li><a class="dropdown-item" href="?sort=progress">Theo tiến độ</a></li>
                    <li><a class="dropdown-item" href="?sort=name">Theo tên A-Z</a></li>
                    <li><a class="dropdown-item" href="?sort=completed">Đã hoàn thành</a></li>
                </ul>
            </div>
        </div>
        
        <?php if ($enrolled_courses): ?>
        <div class="row">
            <?php foreach ($enrolled_courses as $course): ?>
            <?php
            $progress_percent = $course['total_lessons'] > 0 
                ? round(($course['completed_lessons'] / $course['total_lessons']) * 100) 
                : 0;
            $is_completed = $progress_percent >= 100;
            ?>
            
            <div class="col-lg-6 mb-4">
                <div class="card course-card h-100 <?php echo $is_completed ? 'border-success' : ''; ?>">
                    <div class="row g-0">
                        <div class="col-4">
                            <img src="<?php echo $course['thumbnail'] ?: 'https://via.placeholder.com/200x150?text=' . urlencode($course['title']); ?>" 
                                 class="img-fluid rounded-start h-100" alt="<?php echo htmlspecialchars($course['title']); ?>"
                                 style="object-fit: cover;">
                        </div>
                        <div class="col-8">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-secondary"><?php echo $course['category_name'] ?: 'Khóa học'; ?></span>
                                    <?php if ($is_completed): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i>Hoàn thành
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <h6 class="card-title mb-2">
                                    <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>" 
                                       class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </a>
                                </h6>
                                
                                <!-- Progress Bar -->
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>Tiến độ</span>
                                        <span><?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?></span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar <?php echo $is_completed ? 'bg-success' : 'bg-primary'; ?>" 
                                             style="width: <?php echo $progress_percent; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo $progress_percent; ?>% hoàn thành</small>
                                </div>
                                
                                <!-- Course Info -->
                                <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                                    <span>
                                        <i class="bi bi-calendar3 me-1"></i>
                                        Đăng ký: <?php echo date('d/m/Y', strtotime($course['enrolled_at'])); ?>
                                    </span>
                                    <span><?php echo formatPrice($course['price']); ?></span>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="d-flex gap-2">
                                    <?php if ($course['next_lesson_id']): ?>
                                    <a href="<?php echo SITE_URL; ?>/learn.php?course=<?php echo $course['id']; ?>&lesson=<?php echo $course['next_lesson_id']; ?>" 
                                       class="btn btn-primary btn-sm flex-grow-1">
                                        <i class="bi bi-play-fill me-1"></i>
                                        <?php echo $progress_percent > 0 ? 'Tiếp tục' : 'Bắt đầu'; ?>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" 
                                                type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" 
                                                   href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>">
                                                    <i class="bi bi-info-circle me-2"></i>Chi tiết khóa học
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" 
                                                   href="<?php echo SITE_URL; ?>/learn.php?course=<?php echo $course['id']; ?>">
                                                    <i class="bi bi-list-ul me-2"></i>Danh sách bài học
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item text-danger" 
                                                        onclick="confirmUnenroll(<?php echo $course['id']; ?>, '<?php echo addslashes($course['title']); ?>')">
                                                    <i class="bi bi-trash me-2"></i>Hủy đăng ký
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-5">
            <i class="bi bi-book display-1 text-muted"></i>
            <h3 class="mt-3">Chưa có khóa học nào</h3>
            <p class="text-muted mb-4">Bạn chưa đăng ký khóa học nào. Hãy khám phá các khóa học tuyệt vời của chúng tôi!</p>
            <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-primary">
                <i class="bi bi-search me-2"></i>Khám phá khóa học
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Unenroll Confirmation Modal -->
<div class="modal fade" id="unenrollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận hủy đăng ký</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="bi bi-exclamation-triangle display-4 text-warning"></i>
                </div>
                <p class="text-center">
                    Bạn có chắc chắn muốn hủy đăng ký khóa học<br>
                    <strong id="courseTitle"></strong>?
                </p>
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Lưu ý:</strong> Hành động này sẽ xóa toàn bộ tiến độ học tập của bạn và không thể hoàn tác.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form method="POST" id="unenrollForm" class="d-inline">
                    <input type="hidden" name="course_id" id="courseIdInput">
                    <button type="submit" name="unenroll_course" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i>Xác nhận hủy đăng ký
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.stats-card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
}

.course-card {
    transition: all 0.3s ease;
    border: 1px solid #e0e0e0;
}

.course-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.recent-course-card {
    border: 1px solid #e0e0e0;
    transition: all 0.3s ease;
}

.recent-course-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.progress {
    border-radius: 10px;
    background-color: #f8f9fa;
}

.progress-bar {
    border-radius: 10px;
}

@media (max-width: 768px) {
    .course-card .row {
        flex-direction: column;
    }
    
    .course-card .col-4,
    .course-card .col-8 {
        flex: none;
        width: 100%;
    }
    
    .course-card img {
        height: 200px !important;
        border-radius: 0.375rem 0.375rem 0 0 !important;
    }
}
</style>

<!-- JavaScript -->
<script>
// Confirm unenroll function
function confirmUnenroll(courseId, courseTitle) {
    document.getElementById('courseTitle').textContent = courseTitle;
    document.getElementById('courseIdInput').value = courseId;
    
    const modal = new bootstrap.Modal(document.getElementById('unenrollModal'));
    modal.show();
}

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

// Progress bar animation
document.addEventListener('DOMContentLoaded', function() {
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.transition = 'width 1s ease-in-out';
            bar.style.width = width;
        }, 100);
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + N = New course search
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        window.location.href = '<?php echo SITE_URL; ?>/courses.php';
    }
});
</script>

<?php include 'includes/footer.php'; ?>