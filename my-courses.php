<?php
// filepath: d:\Xampp\htdocs\elearning\my-courses.php

require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php?redirect=/my-courses.php');
}

$page_title = 'Khóa học của tôi';
$user_id = $_SESSION['user_id'];

// Debug: Check if user_id exists
if (!$user_id) {
    die('User ID not found in session');
}

try {
    // Get user info
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch();
    
    if (!$user_info) {
        die('User not found in database');
    }

    // Get enrolled courses with basic query first
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.title,
            c.description,
            c.price,
            c.thumbnail,
            e.enrolled_at,
            COALESCE(cat.name, 'Khóa học') as category_name
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$user_id]);
    $enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add lesson counts and progress for each course
    foreach ($enrolled_courses as &$course) {
        // Get total lessons
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM lessons WHERE course_id = ?");
        $stmt->execute([$course['id']]);
        $lesson_count = $stmt->fetch();
        $course['total_lessons'] = $lesson_count['total'] ?? 0;
        
        // Get completed lessons (check if progress table exists)
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as completed 
                FROM progress p 
                JOIN lessons l ON p.lesson_id = l.id 
                WHERE l.course_id = ? AND p.user_id = ? AND p.completed = 1
            ");
            $stmt->execute([$course['id'], $user_id]);
            $progress_count = $stmt->fetch();
            $course['completed_lessons'] = $progress_count['completed'] ?? 0;
        } catch (PDOException $e) {
            $course['completed_lessons'] = 0;
        }
        
        // Get next lesson
        try {
            $stmt = $pdo->prepare("
                SELECT l.id 
                FROM lessons l 
                LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ?
                WHERE l.course_id = ? 
                ORDER BY CASE WHEN p.completed IS NULL OR p.completed = 0 THEN 0 ELSE 1 END, l.id 
                LIMIT 1
            ");
            $stmt->execute([$user_id, $course['id']]);
            $next_lesson = $stmt->fetch();
            $course['next_lesson_id'] = $next_lesson['id'] ?? null;
        } catch (PDOException $e) {
            $course['next_lesson_id'] = null;
        }
    }

    // Get recent courses (simplified)
    $recent_courses = array_slice($enrolled_courses, 0, 3);

    // Calculate statistics
    $total_enrolled = count($enrolled_courses);
    $completed_courses = 0;
    $in_progress_courses = 0;
    $total_progress = 0;
    $total_study_time = 0;

    foreach ($enrolled_courses as $course) {
        if ($course['total_lessons'] > 0) {
            $progress = ($course['completed_lessons'] / $course['total_lessons']) * 100;
            $total_progress += $progress;
            
            if ($progress >= 100) {
                $completed_courses++;
            } elseif ($progress > 0) {
                $in_progress_courses++;
            }
            
            $total_study_time += $course['completed_lessons'] * 30;
        }
    }

    $average_progress = $total_enrolled > 0 ? round($total_progress / $total_enrolled) : 0;

} catch (PDOException $e) {
    error_log("Database error in my-courses.php: " . $e->getMessage());
    $enrolled_courses = [];
    $recent_courses = [];
    $total_enrolled = 0;
    $completed_courses = 0;
    $in_progress_courses = 0;
    $average_progress = 0;
    $total_study_time = 0;
    $user_info = ['username' => 'User', 'email' => ''];
}

// Handle unenroll request
if ($_POST && isset($_POST['unenroll_course'])) {
    $course_id = (int)$_POST['course_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM enrollments WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$user_id, $course_id]);
        
        $_SESSION['success_message'] = 'Đã hủy đăng ký khóa học thành công!';
        redirect(SITE_URL . '/my-courses.php');
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Có lỗi xảy ra khi hủy đăng ký!';
    }
}

// REMOVED: formatPrice() function - already declared in config.php

// Function to get course image
function getCourseImage($course) {
    if (!empty($course['thumbnail'])) {
        if (filter_var($course['thumbnail'], FILTER_VALIDATE_URL)) {
            return $course['thumbnail'];
        } else {
            // Check if file exists
            $imagePath = SITE_URL . '/uploads/courses/' . $course['thumbnail'];
            return $imagePath;
        }
    }
    return 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=400&h=250&fit=crop&crop=center';
}
?>

<?php include 'includes/header.php'; ?>

<!-- Simplified Hero Section -->
<div class="hero-section bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-5 fw-bold mb-3">
                    Chào mừng trở lại, <?php echo htmlspecialchars($user_info['username']); ?>!
                </h1>
                <p class="lead">Tiếp tục hành trình học tập của bạn</p>
                
                <!-- Quick Stats -->
                <div class="row g-3 mt-4">
                    <div class="col-4">
                        <div class="text-center">
                            <h3 class="fw-bold"><?php echo $total_enrolled; ?></h3>
                            <small>Khóa học</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center">
                            <h3 class="fw-bold"><?php echo $completed_courses; ?></h3>
                            <small>Hoàn thành</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center">
                            <h3 class="fw-bold"><?php echo floor($total_study_time / 60); ?>h</h3>
                            <small>Học tập</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-light btn-lg me-2">
                    <i class="bi bi-plus-circle me-2"></i>Khám phá khóa học
                </a>
                <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-outline-light btn-lg">
                    <i class="bi bi-person-gear me-2"></i>Hồ sơ
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="container my-5">
    
    <!-- Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $_SESSION['error_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-collection fs-2 me-3"></i>
                        <div>
                            <h3 class="card-title"><?php echo $total_enrolled; ?></h3>
                            <p class="card-text">Khóa học đã đăng ký</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-trophy-fill fs-2 me-3"></i>
                        <div>
                            <h3 class="card-title"><?php echo $completed_courses; ?></h3>
                            <p class="card-text">Đã hoàn thành</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-graph-up-arrow fs-2 me-3"></i>
                        <div>
                            <h3 class="card-title"><?php echo $average_progress; ?>%</h3>
                            <p class="card-text">Tiến độ trung bình</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-clock-history fs-2 me-3"></i>
                        <div>
                            <h3 class="card-title"><?php echo floor($total_study_time / 60); ?>h</h3>
                            <p class="card-text">Thời gian học tập</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Course Filters -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-grid-3x3-gap-fill me-2"></i>Khóa học của tôi</h2>
        
        <div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="filter" id="all" autocomplete="off" checked>
            <label class="btn btn-outline-primary" for="all">Tất cả (<?php echo $total_enrolled; ?>)</label>
            
            <input type="radio" class="btn-check" name="filter" id="in-progress" autocomplete="off">
            <label class="btn btn-outline-warning" for="in-progress">Đang học (<?php echo $in_progress_courses; ?>)</label>
            
            <input type="radio" class="btn-check" name="filter" id="completed" autocomplete="off">
            <label class="btn btn-outline-success" for="completed">Hoàn thành (<?php echo $completed_courses; ?>)</label>
        </div>
    </div>
    
    <!-- Courses Grid -->
    <?php if ($enrolled_courses): ?>
    <div class="row g-4">
        <?php foreach ($enrolled_courses as $course): ?>
        <?php
        $progress_percent = $course['total_lessons'] > 0 
            ? round(($course['completed_lessons'] / $course['total_lessons']) * 100) 
            : 0;
        $is_completed = $progress_percent >= 100;
        $is_in_progress = $progress_percent > 0 && $progress_percent < 100;
        ?>
        
        <div class="col-lg-6 course-item" 
             data-status="<?php echo $is_completed ? 'completed' : ($is_in_progress ? 'in-progress' : 'not-started'); ?>">
            <div class="card h-100 <?php echo $is_completed ? 'border-success' : ''; ?>">
                <div class="position-relative">
                    <img src="<?php echo getCourseImage($course); ?>" 
                         class="card-img-top" 
                         alt="<?php echo htmlspecialchars($course['title']); ?>"
                         style="height: 200px; object-fit: cover;">
                    
                    <?php if ($is_completed): ?>
                    <div class="position-absolute top-0 end-0 m-2">
                        <span class="badge bg-success rounded-pill">
                            <i class="bi bi-check-circle-fill"></i> Hoàn thành
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="position-absolute bottom-0 start-0 m-2">
                        <div class="bg-dark bg-opacity-75 text-white px-2 py-1 rounded">
                            <small><?php echo $progress_percent; ?>%</small>
                        </div>
                    </div>
                </div>
                
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-primary"><?php echo $course['category_name']; ?></span>
                        <span class="text-success fw-bold"><?php echo formatPrice($course['price']); ?></span>
                    </div>
                    
                    <h5 class="card-title">
                        <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>" 
                           class="text-decoration-none text-dark">
                            <?php echo htmlspecialchars($course['title']); ?>
                        </a>
                    </h5>
                    
                    <p class="card-text text-muted small flex-grow-1">
                        <?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>...
                    </p>
                    
                    <!-- Progress Bar -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?> bài học</span>
                            <span><?php echo $progress_percent; ?>% hoàn thành</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar <?php echo $is_completed ? 'bg-success' : 'bg-primary'; ?>" 
                                 style="width: <?php echo $progress_percent; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="bi bi-calendar-plus me-1"></i>
                            <?php echo date('d/m/Y', strtotime($course['enrolled_at'])); ?>
                        </small>
                        
                        <div class="btn-group">
                            <?php if ($course['next_lesson_id']): ?>
                            <a href="<?php echo SITE_URL; ?>/learn.php?course=<?php echo $course['id']; ?>&lesson=<?php echo $course['next_lesson_id']; ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="bi bi-play-fill me-1"></i>
                                <?php echo $progress_percent > 0 ? 'Tiếp tục' : 'Bắt đầu'; ?>
                            </a>
                            <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>Xem chi tiết
                            </a>
                            <?php endif; ?>
                            
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" 
                                        data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" 
                                           href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>">
                                            <i class="bi bi-info-circle me-2"></i>Chi tiết
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
        <?php endforeach; ?>
    </div>
    
    <?php else: ?>
    <!-- Empty State -->
    <div class="text-center py-5">
        <i class="bi bi-mortarboard display-1 text-muted"></i>
        <h3 class="mt-4">Bạn chưa đăng ký khóa học nào</h3>
        <p class="text-muted">Khám phá các khóa học tuyệt vời và bắt đầu hành trình học tập của bạn!</p>
        <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-primary btn-lg">
            <i class="bi bi-search me-2"></i>Khám phá khóa học
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Unenroll Modal -->
<div class="modal fade" id="unenrollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    Xác nhận hủy đăng ký
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn hủy đăng ký khóa học:</p>
                <p class="fw-bold" id="courseTitle"></p>
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Lưu ý:</strong> Hành động này sẽ xóa toàn bộ tiến độ học tập và không thể hoàn tác.
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

<script>
// Course filtering
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('input[name="filter"]');
    const courseItems = document.querySelectorAll('.course-item');
    
    filterButtons.forEach(button => {
        button.addEventListener('change', function() {
            const filterValue = this.id;
            
            courseItems.forEach(item => {
                const status = item.dataset.status;
                
                if (filterValue === 'all') {
                    item.style.display = 'block';
                } else if (filterValue === 'completed' && status === 'completed') {
                    item.style.display = 'block';
                } else if (filterValue === 'in-progress' && status === 'in-progress') {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
});

// Confirm unenroll
function confirmUnenroll(courseId, courseTitle) {
    document.getElementById('courseTitle').textContent = courseTitle;
    document.getElementById('courseIdInput').value = courseId;
    
    const modal = new bootstrap.Modal(document.getElementById('unenrollModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>