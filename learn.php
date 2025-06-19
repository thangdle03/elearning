<?php
// filepath: d:\Xampp\htdocs\elearning\learn.php
require_once 'includes/config.php';

// ✅ THÊM DEBUG ĐỂ XEM POST DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<!-- DEBUG: POST method detected -->";
    echo "<!-- POST DATA: " . print_r($_POST, true) . " -->";
    
    if (isset($_POST['submit_review'])) {
        echo "<!-- REVIEW SUBMIT DETECTED -->";
    } else {
        echo "<!-- NO SUBMIT_REVIEW IN POST -->";
    }
}

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php');
}

// Get parameters
$course_id = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$lesson_id = isset($_GET['lesson']) ? (int)$_GET['lesson'] : 0;

if (!$course_id) {
    redirect(SITE_URL . '/my-courses.php');
}

// Check if user is enrolled
if (!isEnrolled($_SESSION['user_id'], $course_id, $pdo)) {
    redirect(SITE_URL . '/course-detail.php?id=' . $course_id);
}

// Initialize messages
$success_message = '';
$error_message = '';

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    echo "<script>console.log('Review form submitted!');</script>";
    
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    echo "<script>console.log('Rating: " . $rating . "');</script>";
    echo "<script>console.log('Comment: " . addslashes($comment) . "');</script>";
    echo "<script>console.log('User ID: " . $_SESSION['user_id'] . "');</script>";
    echo "<script>console.log('Course ID: " . $course_id . "');</script>";
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $error_message = "Vui lòng chọn từ 1 đến 5 sao! (Nhận được: " . $rating . ")";
        echo "<script>console.error('Invalid rating: " . $rating . "');</script>";
    } else {
        try {
            echo "<script>console.log('Starting database operations...');</script>";
            
            // Check if user already reviewed this course
            $stmt = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND course_id = ?");
            $stmt->execute([$_SESSION['user_id'], $course_id]);
            $existing_review = $stmt->fetch();
            
            if ($existing_review) {
                echo "<script>console.log('Updating existing review ID: " . $existing_review['id'] . "');</script>";
                
                $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, comment = ?, updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$rating, $comment, $existing_review['id']]);
                
                if ($result) {
                    $success_message = "Đánh giá của bạn đã được cập nhật thành công!";
                    echo "<script>console.log('Review updated successfully!');</script>";
                } else {
                    $error_message = "Có lỗi khi cập nhật đánh giá!";
                    echo "<script>console.error('Update failed');</script>";
                    $errorInfo = $stmt->errorInfo();
                    echo "<script>console.error('SQL Error: " . json_encode($errorInfo) . "');</script>";
                }
            } else {
                echo "<script>console.log('Creating new review...');</script>";
                
                $stmt = $pdo->prepare("INSERT INTO reviews (user_id, course_id, rating, comment, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                $result = $stmt->execute([$_SESSION['user_id'], $course_id, $rating, $comment]);
                
                if ($result) {
                    $success_message = "Cảm ơn bạn đã đánh giá! Đánh giá sẽ được hiển thị sau khi được duyệt.";
                    $new_id = $pdo->lastInsertId();
                    echo "<script>console.log('Review created successfully! New ID: " . $new_id . "');</script>";
                } else {
                    $error_message = "Có lỗi khi gửi đánh giá!";
                    echo "<script>console.error('Insert failed');</script>";
                    $errorInfo = $stmt->errorInfo();
                    echo "<script>console.error('SQL Error: " . json_encode($errorInfo) . "');</script>";
                }
            }
            
            // Verify the operation
            $stmt = $pdo->prepare("SELECT * FROM reviews WHERE user_id = ? AND course_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$_SESSION['user_id'], $course_id]);
            $verify_review = $stmt->fetch();
            
            if ($verify_review) {
                echo "<script>console.log('Verification: Review found in database');</script>";
                echo "<script>console.log('Review data: " . json_encode($verify_review) . "');</script>";
            } else {
                echo "<script>console.error('Verification: No review found in database!');</script>";
            }
            
        } catch (PDOException $e) {
            $error_message = "Lỗi hệ thống: " . $e->getMessage();
            echo "<script>console.error('PDO Exception: " . addslashes($e->getMessage()) . "');</script>";
        }
        
        // Comment out auto-reload for debugging
        /*
        echo "<script>
            setTimeout(function() {
                console.log('Auto reloading page to see changes...');
                window.location.reload();
            }, 3000);
        </script>";
        */
    }
}

// Handle mark lesson complete (unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed'])) {
    $lesson_to_complete = (int)$_POST['lesson_id'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO progress (user_id, lesson_id, completed, completed_at) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE completed = 1, completed_at = NOW()");
        $stmt->execute([$_SESSION['user_id'], $lesson_to_complete]);
        
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Đã đánh dấu hoàn thành!']);
            exit;
        }
    } catch (PDOException $e) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra!']);
            exit;
        }
    }
}

// Rest of the PHP code remains unchanged (course details, lessons, progress, etc.)
$stmt = $pdo->prepare("SELECT c.*, cat.name as category_name FROM courses c LEFT JOIN categories cat ON c.category_id = cat.id WHERE c.id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    redirect(SITE_URL . '/my-courses.php');
}

$stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_number ASC");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

if (!$lesson_id && !empty($lessons)) {
    $lesson_id = $lessons[0]['id'];
}

$current_lesson = null;
foreach ($lessons as $lesson) {
    if ($lesson['id'] == $lesson_id) {
        $current_lesson = $lesson;
        break;
    }
}

if (!$current_lesson && !empty($lessons)) {
    $current_lesson = $lessons[0];
    $lesson_id = $current_lesson['id'];
}

$stmt = $pdo->prepare("SELECT lesson_id, completed FROM progress WHERE user_id = ? AND lesson_id IN (SELECT id FROM lessons WHERE course_id = ?)");
$stmt->execute([$_SESSION['user_id'], $course_id]);
$progress = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$total_lessons = count($lessons);
$completed_lessons = array_sum($progress);
$progress_percentage = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0;
$is_course_completed = $total_lessons > 0 && $completed_lessons == $total_lessons;

$stmt = $pdo->prepare("SELECT * FROM reviews WHERE user_id = ? AND course_id = ?");
$stmt->execute([$_SESSION['user_id'], $course_id]);
$user_review = $stmt->fetch();

$stmt = $pdo->prepare("SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.course_id = ? AND r.status = 'approved' ORDER BY r.created_at DESC LIMIT 5");
$stmt->execute([$course_id]);
$course_reviews = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE course_id = ? AND status = 'approved'");
$stmt->execute([$course_id]);
$rating_stats = $stmt->fetch();
$avg_rating = $rating_stats['avg_rating'] ? round($rating_stats['avg_rating'], 1) : 0;
$total_reviews = $rating_stats['total_reviews'];

$page_title = $course['title'] . ' - Học tập';
$youtube_id = $current_lesson ? getYoutubeId($current_lesson['youtube_url']) : '';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .learning-container { height: 100vh; overflow: hidden; }
        .sidebar { height: 100vh; overflow-y: auto; background: #2c3e50; color: white; }
        .lesson-item { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.1); cursor: pointer; transition: all 0.3s; }
        .lesson-item:hover { background: rgba(255,255,255,0.1); }
        .lesson-item.active { background: rgba(52, 152, 219, 0.3); border-left: 4px solid #3498db; }
        .main-content { height: 100vh; overflow-y: auto; }
        .video-container { position: sticky; top: 0; z-index: 100; }
        .stars { color: #ffc107; }
        .star-rating { text-align: center; padding: 20px 0; }
        .star { font-size: 2.5rem; color: #ddd; cursor: pointer; margin: 0 5px; transition: all 0.2s ease; user-select: none; }
        .star:hover, .star.active { color: #ffc107; transform: scale(1.1); }
        .review-section { border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px; }
        .review-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -100%; top: 0; width: 300px; z-index: 1050; transition: left 0.3s; }
            .sidebar.show { left: 0; }
            .review-buttons { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="learning-container">
    <div class="row g-0">
        <!-- Sidebar (unchanged) -->
        <div class="col-lg-3 sidebar" id="sidebar">
            <div class="p-3 border-bottom border-secondary">
                <h5 class="mb-0 text-truncate">
                    <i class="bi bi-book me-2"></i>
                    <?php echo htmlspecialchars($course['title']); ?>
                </h5>
                
                <div class="mt-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Tiến độ</span>
                        <span><?php echo $completed_lessons; ?>/<?php echo $total_lessons; ?></span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $progress_percentage; ?>%"></div>
                    </div>
                    <small class="text-muted"><?php echo $progress_percentage; ?>% hoàn thành</small>
                    
                    <?php if ($is_course_completed): ?>
                    <div class="mt-2">
                        <span class="badge bg-success">
                            <i class="bi bi-trophy me-1"></i>Hoàn thành khóa học!
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mt-3 pt-2 border-top border-secondary">
                    <div class="d-flex align-items-center justify-content-between">
                        <small class="text-muted">Đánh giá</small>
                        <div>
                            <span class="stars"><?php echo str_repeat('★', round($avg_rating)) . str_repeat('☆', 5 - round($avg_rating)); ?></span>
                            <small class="text-muted ms-1"><?php echo $avg_rating; ?> (<?php echo $total_reviews; ?>)</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="lessons-list">
                <?php foreach ($lessons as $index => $lesson): ?>
                <div class="lesson-item <?php echo $lesson['id'] == $lesson_id ? 'active' : ''; ?>" 
                     onclick="loadLesson(<?php echo $lesson['id']; ?>)">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <?php if (isset($progress[$lesson['id']]) && $progress[$lesson['id']]): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo $index + 1; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><?php echo htmlspecialchars($lesson['title']); ?></h6>
                            <small class="text-muted">
                                <i class="bi bi-play-circle me-1"></i>Video bài học
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="p-3 border-top border-secondary">
                <div class="d-grid gap-2">
                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal">
                        <i class="bi bi-star me-1"></i>
                        <?php echo $user_review ? 'Sửa đánh giá' : 'Đánh giá khóa học'; ?>
                    </button>
                    
                    <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#reviewsModal">
                        <i class="bi bi-chat-dots me-1"></i>Xem đánh giá (<?php echo $total_reviews; ?>)
                    </button>
                    
                    <a href="<?php echo SITE_URL; ?>/my-courses.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Khóa học của tôi
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content (unchanged) -->
        <div class="col-lg-9 main-content">
            <div class="bg-white border-bottom p-3 sticky-top">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-outline-secondary me-3 d-lg-none" onclick="toggleSidebar()">
                            <i class="bi bi-list"></i>
                        </button>
                        <div>
                            <h4 class="mb-0"><?php echo $current_lesson ? htmlspecialchars($current_lesson['title']) : 'Chọn bài học'; ?></h4>
                            <small class="text-muted">
                                <?php if ($is_course_completed): ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-trophy me-1"></i>Hoàn thành
                                </span>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <?php if ($current_lesson): ?>
                        <button class="btn btn-success btn-sm" id="markCompleteBtn" 
                                <?php echo (isset($progress[$lesson_id]) && $progress[$lesson_id]) ? 'disabled' : ''; ?>
                                onclick="markComplete(<?php echo $lesson_id; ?>)">
                            <i class="bi bi-check-circle me-1"></i>
                            <?php echo (isset($progress[$lesson_id]) && $progress[$lesson_id]) ? 'Đã hoàn thành' : 'Đánh dấu hoàn thành'; ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show m-3">
                <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="p-3">
                <?php if ($current_lesson && $youtube_id): ?>
                <div class="ratio ratio-16x9 mb-4">
                    <iframe src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>?rel=0" 
                            title="<?php echo htmlspecialchars($current_lesson['title']); ?>"
                            allowfullscreen></iframe>
                </div>
                <?php else: ?>
                <div class="ratio ratio-16x9 mb-4 bg-light d-flex align-items-center justify-content-center">
                    <div class="text-center text-muted">
                        <i class="bi bi-play-circle display-1"></i>
                        <h4 class="mt-3">Chọn bài học để bắt đầu</h4>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($current_lesson): ?>
                <div class="card">
                    <div class="card-body">
                        <h5><?php echo htmlspecialchars($current_lesson['title']); ?></h5>
                        <p class="text-muted">
                            Bài học trong khóa học: <strong><?php echo htmlspecialchars($course['title']); ?></strong>
                        </p>
                        
                        <div class="review-section">
                            <h6 class="mb-3">
                                <i class="bi bi-star me-2"></i>Đánh giá khóa học
                            </h6>
                            
                            <?php if ($is_course_completed): ?>
                                <?php if (!$user_review): ?>
                                <div class="alert alert-success">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="bi bi-trophy me-2"></i>
                                            <strong>Chúc mừng!</strong> Bạn đã hoàn thành khóa học.
                                        </div>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                            <i class="bi bi-star me-1"></i>Đánh giá ngay
                                        </button>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="bi bi-check-circle me-2"></i>
                                            Bạn đã đánh giá: <strong><?php echo $user_review['rating']; ?> sao</strong>
                                            <small class="text-muted">(<?php echo $user_review['status'] == 'approved' ? 'Đã duyệt' : 'Chờ duyệt'; ?>)</small>
                                        </div>
                                        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                            <i class="bi bi-pencil me-1"></i>Sửa đánh giá
                                        </button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-info-circle me-2"></i>
                                Hoàn thành tất cả bài học để có thể đánh giá khóa học.
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar" style="width: <?php echo $progress_percentage; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $progress_percentage; ?>% hoàn thành</small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="review-buttons mt-3">
                                <?php if ($is_course_completed): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                    <i class="bi bi-star me-1"></i>
                                    <?php echo $user_review ? 'Sửa đánh giá của tôi' : 'Viết đánh giá'; ?>
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#reviewsModal">
                                    <i class="bi bi-chat-dots me-1"></i>
                                    Xem tất cả đánh giá (<?php echo $total_reviews; ?>)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal (unchanged HTML, updated JS below) -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-star me-2"></i>
                    <?php echo $user_review ? 'Chỉnh sửa đánh giá' : 'Đánh giá khóa học'; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <!-- ✅ FORM ĐơN GIẢN - KHÔNG DÙNG JAVASCRIPT -->
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <h6 class="text-primary"><?php echo htmlspecialchars($course['title']); ?></h6>
                        <p class="text-muted small">Chia sẻ trải nghiệm của bạn về khóa học này</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            Bạn đánh giá khóa học này bao nhiêu sao? <span class="text-danger">*</span>
                        </label>
                        
                        <!-- ✅ RADIO BUTTONS THAY VÌ STARS -->
                        <div class="rating-options">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rating" 
                                       id="rating<?php echo $i; ?>" value="<?php echo $i; ?>"
                                       <?php echo ($user_review && $user_review['rating'] == $i) ? 'checked' : ''; ?> required>
                                <label class="form-check-label" for="rating<?php echo $i; ?>">
                                    <span class="stars text-warning">
                                        <?php echo str_repeat('⭐', $i) . str_repeat('☆', 5-$i); ?>
                                    </span>
                                    <?php 
                                    $labels = ['', 'Rất tệ', 'Tệ', 'Bình thường', 'Tốt', 'Rất tốt'];
                                    echo $labels[$i] . ' (' . $i . ' sao)';
                                    ?>
                                </label>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reviewComment" class="form-label fw-bold">Nhận xét chi tiết</label>
                        <textarea class="form-control" id="reviewComment" name="comment" rows="4" 
                                  placeholder="• Nội dung khóa học có hữu ích không?&#10;• Giảng viên có dễ hiểu không?&#10;• Bạn có khuyến nghị khóa học này không?"><?php echo $user_review ? htmlspecialchars($user_review['comment']) : ''; ?></textarea>
                    </div>
                    
                    <?php if ($user_review): ?>
                    <div class="alert alert-info">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Đánh giá hiện tại:</strong> <?php echo $user_review['rating']; ?> sao - 
                            <?php echo $user_review['status'] == 'approved' ? 'Đã được duyệt' : 'Đang chờ duyệt'; ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Hủy
                    </button>
                    <button type="submit" name="submit_review" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>
                        <?php echo $user_review ? 'Cập nhật đánh giá' : 'Gửi đánh giá'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reviews Modal (unchanged) -->
<div class="modal fade" id="reviewsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-chat-dots me-2"></i>
                    Tất cả đánh giá về khóa học (<?php echo $total_reviews; ?>)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($total_reviews > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-4 text-center">
                        <h2 class="text-warning"><?php echo $avg_rating; ?></h2>
                        <div class="stars fs-4 mb-2">
                            <?php echo str_repeat('★', round($avg_rating)) . str_repeat('☆', 5 - round($avg_rating)); ?>
                        </div>
                        <p class="text-muted"><?php echo $total_reviews; ?> đánh giá</p>
                    </div>
                    <div class="col-md-8">
                        <h6>Phân bố đánh giá:</h6>
                        <?php 
                        $stmt = $pdo->prepare("SELECT rating, COUNT(*) as count FROM reviews WHERE course_id = ? AND status = 'approved' GROUP BY rating ORDER BY rating DESC");
                        $stmt->execute([$course_id]);
                        $rating_dist = $stmt->fetchAll();
                        $rating_counts = array_column($rating_dist, 'count', 'rating');
                        
                        for ($i = 5; $i >= 1; $i--):
                            $count = isset($rating_counts[$i]) ? $rating_counts[$i] : 0;
                            $percentage = $total_reviews > 0 ? round(($count / $total_reviews) * 100) : 0;
                        ?>
                        <div class="d-flex align-items-center mb-2">
                            <span class="me-2"><?php echo $i; ?> sao</span>
                            <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $count; ?></small>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="reviews-list" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($course_reviews as $review): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="bi bi-person-circle me-1"></i>
                                        <?php echo htmlspecialchars($review['username']); ?>
                                    </h6>
                                    <div class="stars text-warning">
                                        <?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?>
                                        <span class="ms-2 text-muted small"><?php echo $review['rating']; ?>/5 sao</span>
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></small>
                            </div>
                            <?php if ($review['comment']): ?>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($review['admin_response']): ?>
                            <div class="alert alert-light mt-2 mb-0">
                                <small class="text-primary fw-bold">
                                    <i class="bi bi-reply me-1"></i>Phản hồi từ giảng viên:
                                </small>
                                <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($review['admin_response'])); ?></p>
                                <?php if ($review['admin_responded_at']): ?>
                                <small class="text-muted"><?php echo date('d/m/Y', strtotime($review['admin_responded_at'])); ?></small>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-chat-dots display-1 text-muted"></i>
                    <h5 class="mt-3">Chưa có đánh giá nào</h5>
                    <p class="text-muted">Hãy là người đầu tiên đánh giá khóa học này!</p>
                    <?php if ($is_course_completed): ?>
                    <button class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#reviewModal">
                        <i class="bi bi-star me-1"></i>Viết đánh giá đầu tiên
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Global variables
let selectedRating = <?php echo $user_review ? $user_review['rating'] : 0; ?>;
const ratingTexts = ['', 'Rất tệ', 'Tệ', 'Bình thường', 'Tốt', 'Rất tốt'];

// Load lesson function (unchanged)
function loadLesson(lessonId) {
    window.location.href = `<?php echo SITE_URL; ?>/learn.php?course=<?php echo $course_id; ?>&lesson=${lessonId}`;
}

// Toggle sidebar (unchanged)
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

// Mark lesson complete (unchanged)
function markComplete(lessonId) {
    const btn = document.getElementById('markCompleteBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';
    btn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `mark_completed=1&lesson_id=${lessonId}&ajax=1`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Đã hoàn thành';
            btn.className = 'btn btn-success btn-sm';
            showAlert('success', 'Đã đánh dấu bài học hoàn thành!');
            setTimeout(() => location.reload(), 1000);
        } else {
            btn.innerHTML = originalText;
            btn.disabled = false;
            showAlert('danger', 'Có lỗi xảy ra!');
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        showAlert('danger', 'Có lỗi xảy ra!');
    });
}

// Show alert function (unchanged)
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 4000);
}

// Close sidebar when clicking outside (unchanged)
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth <= 768 && sidebar.classList.contains('show') && !sidebar.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});
</script>

</body>
</html>