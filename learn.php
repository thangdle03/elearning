<?php
// filepath: d:\Xampp\htdocs\elearning\learn.php

require_once 'includes/config.php';

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

// Initialize messages
$success_message = '';
$error_message = '';

// ✅ CHỈ XỬ LÝ ĐÁNH GIÁ MỚI - KHÔNG CHO SỬA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    try {
        // Check if user already reviewed this course
        $stmt = $pdo->prepare("SELECT * FROM reviews WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$_SESSION['user_id'], $course_id]);
        $existing_review = $stmt->fetch();

        if ($existing_review) {
            // ❌ ĐÃ ĐÁNH GIÁ RỒI - KHÔNG CHO ĐÁNH GIÁ LẠI
            $error_message = "⚠️ Bạn đã đánh giá khóa học này rồi. Mỗi học viên chỉ được đánh giá một lần để đảm bảo tính công bằng!";
        } else {
            // ✅ TẠO ĐÁNH GIÁ MỚI
            if ($rating < 1 || $rating > 5) {
                $error_message = "❌ Vui lòng chọn từ 1 đến 5 sao!";
            } elseif (!$comment) {
                $error_message = "❌ Vui lòng nhập nhận xét chi tiết!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO reviews (user_id, course_id, rating, comment, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                $stmt->execute([$_SESSION['user_id'], $course_id, $rating, $comment]);
                $success_message = "🎉 Cảm ơn bạn đã đánh giá! Đánh giá sẽ được hiển thị sau khi được duyệt.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "💥 Lỗi hệ thống: " . $e->getMessage();
    }
}

// Check if user is enrolled
if (!isEnrolled($_SESSION['user_id'], $course_id, $pdo)) {
    redirect(SITE_URL . '/course-detail.php?id=' . $course_id);
}

// Handle mark lesson complete
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

// Get course and lesson data
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

// Get progress
$stmt = $pdo->prepare("SELECT lesson_id, completed FROM progress WHERE user_id = ? AND lesson_id IN (SELECT id FROM lessons WHERE course_id = ?)");
$stmt->execute([$_SESSION['user_id'], $course_id]);
$progress = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$total_lessons = count($lessons);
$completed_lessons = array_sum($progress);
$progress_percentage = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0;
$is_course_completed = $total_lessons > 0 && $completed_lessons == $total_lessons;

// ✅ LẤY REVIEW CỦA USER
$stmt = $pdo->prepare("SELECT * FROM reviews WHERE user_id = ? AND course_id = ?");
$stmt->execute([$_SESSION['user_id'], $course_id]);
$user_review = $stmt->fetch();

// ✅ LẤY TẤT CẢ REVIEWS ĐÃ DUYỆT
$stmt = $pdo->prepare("
    SELECT r.*, u.username, u.full_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.course_id = ? AND r.status = 'approved' 
    ORDER BY r.created_at DESC
");
$stmt->execute([$course_id]);
$course_reviews = $stmt->fetchAll();

// ✅ TÍNH RATING TRUNG BÌNH
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
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
        }

        .learning-container {
            height: 100vh;
            overflow: hidden;
        }

        .sidebar {
            height: 100vh;
            overflow-y: auto;
            background: linear-gradient(135deg, #00051d 0%, #764ba2 100%);
            color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .lesson-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .lesson-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .lesson-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid #ffc107;
            transform: translateX(0);
        }

        .main-content {
            height: 100vh;
            overflow-y: auto;
            background: white;
        }

        .video-container {
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .stars {
            color: #ffc107;
        }

        .rating-options .form-check {
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #fff;
        }

        .rating-options .form-check:hover {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-color: #ffc107;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .rating-options .form-check input:checked+label {
            color: #0d6efd;
            font-weight: 600;
        }

        .rating-options .form-check-label {
            cursor: pointer;
            width: 100%;
            margin-bottom: 0;
        }

        .user-review-display {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            border: 2px solid #4caf50;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
        }

        .user-review-display::before {
            content: '✅';
            position: absolute;
            top: -10px;
            left: 20px;
            background: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 1.1em;
        }

        .review-content {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #4caf50;
            white-space: pre-line;
            line-height: 1.6;
        }

        .progress-ring {
            width: 60px;
            height: 60px;
        }

        .btn-glow {
            box-shadow: 0 0 20px rgba(255, 193, 7, 0.5);
            transition: box-shadow 0.3s ease;
        }

        .btn-glow:hover {
            box-shadow: 0 0 30px rgba(255, 193, 7, 0.8);
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                width: 300px;
                z-index: 1050;
                transition: left 0.3s;
            }

            .sidebar.show {
                left: 0;
            }
        }
    </style>
</head>

<body>
    <div class="learning-container">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-lg-3 sidebar" id="sidebar">
                <div class="p-3 border-bottom border-secondary">
                    <h5 class="mb-0 text-truncate">
                        <i class="bi bi-book me-2"></i>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </h5>

                    <!-- Progress Ring -->
                    <div class="d-flex align-items-center mt-3">
                        <div class="progress-ring me-3">
                            <svg width="60" height="60">
                                <circle cx="30" cy="30" r="25" stroke="rgba(255,255,255,0.3)" stroke-width="4" fill="none" />
                                <circle cx="30" cy="30" r="25" stroke="#ffc107" stroke-width="4" fill="none"
                                    stroke-dasharray="<?php echo 2 * 3.14159 * 25; ?>"
                                    stroke-dashoffset="<?php echo 2 * 3.14159 * 25 * (1 - $progress_percentage / 100); ?>"
                                    stroke-linecap="round" />
                                <text x="30" y="35" text-anchor="middle" fill="white" font-size="12" font-weight="bold">
                                    <?php echo $progress_percentage; ?>%
                                </text>
                            </svg>
                        </div>
                        <div>
                            <div class="fw-bold"><?php echo $completed_lessons; ?>/<?php echo $total_lessons; ?> bài học</div>
                            <small class="text-muted">
                                <?php if ($is_course_completed): ?>
                                    🎉 Hoàn thành!
                                <?php else: ?>
                                    Đang học tập
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>

                    <div class="mt-3 pt-2 border-top border-secondary">
                        <div class="d-flex align-items-center justify-content-between">
                            <small class="text-muted">Đánh giá khóa học</small>
                            <div>
                                <span class="stars"><?php echo str_repeat('★', round($avg_rating)) . str_repeat('☆', 5 - round($avg_rating)); ?></span>
                                <small class="text-muted ms-1"><?php echo $avg_rating; ?> (<?php echo $total_reviews; ?>)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lessons List -->
                <div class="lessons-list">
                    <?php foreach ($lessons as $index => $lesson): ?>
                        <div class="lesson-item <?php echo $lesson['id'] == $lesson_id ? 'active' : ''; ?>"
                            onclick="loadLesson(<?php echo $lesson['id']; ?>)">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <?php if (isset($progress[$lesson['id']]) && $progress[$lesson['id']]): ?>
                                        <i class="bi bi-check-circle-fill text-warning fs-5"></i>
                                    <?php else: ?>
                                        <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center"
                                            style="width: 30px; height: 30px; font-size: 0.8rem;">
                                            <?php echo $index + 1; ?>
                                        </div>
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

                <!-- Action Buttons -->
                <div class="p-3 border-top border-secondary">
                    <div class="d-grid gap-2">
                        <!-- ✅ LOGIC MỚI CHO NÚT ĐÁNH GIÁ -->
                        <?php if ($is_course_completed): ?>
                            <?php if ($user_review): ?>
                                <!-- ❌ ĐÃ ĐÁNH GIÁ - KHÔNG CHO ĐÁNH GIÁ LẠI -->
                                <div class="alert alert-success alert-sm p-2 text-center mb-2">
                                    <i class="bi bi-check-circle me-1"></i>
                                    <small>Bạn đã đánh giá khóa học này</small>
                                </div>
                            <?php else: ?>
                                <!-- ✅ CHƯA ĐÁNH GIÁ - CHO PHÉP ĐÁNH GIÁ -->
                                <button class="btn btn-warning btn-glow" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                    <i class="bi bi-star me-1"></i>Đánh giá khóa học
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-outline-light" disabled>
                                <i class="bi bi-lock me-1"></i>Hoàn thành khóa học để đánh giá
                            </button>
                        <?php endif; ?>

                        <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#reviewsModal">
                            <i class="bi bi-chat-dots me-1"></i>Xem đánh giá (<?php echo $total_reviews; ?>)
                        </button>

                        <a href="<?php echo SITE_URL; ?>/my-courses.php" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>Khóa học của tôi
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9 main-content">
                <!-- Header -->
                <div class="bg-white border-bottom p-3 video-container">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <button class="btn btn-outline-secondary me-3 d-lg-none" onclick="toggleSidebar()">
                                <i class="bi bi-list"></i>
                            </button>
                            <div>
                                <h4 class="mb-0"><?php echo $current_lesson ? htmlspecialchars($current_lesson['title']) : 'Chọn bài học'; ?></h4>
                                <small class="text-muted">
                                    Khóa học: <strong><?php echo htmlspecialchars($course['title']); ?></strong>
                                    <?php if ($is_course_completed): ?>
                                        <span class="badge bg-success ms-2">
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

                <!-- Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show m-3">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show m-3">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Video Content -->
                <div class="p-3">
                    <?php if ($current_lesson && $youtube_id): ?>
                        <div class="ratio ratio-16x9 mb-4 rounded overflow-hidden shadow">
                            <iframe src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>?rel=0"
                                title="<?php echo htmlspecialchars($current_lesson['title']); ?>"
                                allowfullscreen></iframe>
                        </div>
                    <?php else: ?>
                        <div class="ratio ratio-16x9 mb-4 bg-light d-flex align-items-center justify-content-center rounded">
                            <div class="text-center text-muted">
                                <i class="bi bi-play-circle display-1"></i>
                                <h4 class="mt-3">Chọn bài học để bắt đầu</h4>
                                <p>Hãy chọn một bài học từ danh sách bên trái</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Lesson Info -->
                    <?php if ($current_lesson): ?>
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($current_lesson['title']); ?></h5>
                                <p class="text-muted mb-3">
                                    Bài học trong khóa học: <strong><?php echo htmlspecialchars($course['title']); ?></strong>
                                </p>

                                <!-- ✅ HIỂN THỊ TRẠNG THÁI ĐÁNH GIÁ -->
                                <?php if ($is_course_completed): ?>
                                    <div class="border-top pt-3">
                                        <h6 class="text-primary mb-3">
                                            <i class="bi bi-star me-2"></i>Đánh giá khóa học
                                        </h6>

                                        <?php if ($user_review): ?>
                                            <!-- ✅ ĐÃ ĐÁNH GIÁ - CHỈ HIỂN THỊ -->
                                            <div class="user-review-display">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h6 class="text-success mb-0 fw-bold">
                                                        <i class="bi bi-star-fill me-1"></i>Đánh giá của bạn
                                                    </h6>
                                                    <div class="d-flex align-items-center">
                                                        <span class="text-warning fs-4 me-2">
                                                            <?php echo str_repeat('⭐', $user_review['rating']); ?>
                                                        </span>
                                                        <span class="badge bg-<?php echo $user_review['status'] == 'approved' ? 'success' : 'warning'; ?>">
                                                            <?php echo $user_review['status'] == 'approved' ? '✅ Đã duyệt' : '⏳ Chờ duyệt'; ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="review-content">
                                                    <?php echo htmlspecialchars($user_review['comment']); ?>
                                                </div>

                                                <div class="mt-3 d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="bi bi-calendar me-1"></i>
                                                        Đánh giá ngày: <?php echo date('d/m/Y H:i', strtotime($user_review['created_at'])); ?>
                                                    </small>
                                                    <small class="text-success fw-bold">
                                                        <i class="bi bi-shield-check me-1"></i>
                                                        Đánh giá đã được ghi nhận
                                                    </small>
                                                </div>

                                                <div class="alert alert-info mt-3 mb-0">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    <small>
                                                        <strong>Lưu ý:</strong> Để đảm bảo tính công bằng và chân thực, mỗi học viên chỉ được đánh giá một lần duy nhất.
                                                    </small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- ✅ CHƯA ĐÁNH GIÁ -->
                                            <div class="alert alert-success d-flex align-items-center justify-content-between">
                                                <div>
                                                    <i class="bi bi-trophy me-2"></i>
                                                    <strong>Chúc mừng!</strong> Bạn đã hoàn thành khóa học.
                                                </div>
                                                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                                    <i class="bi bi-star me-1"></i>Đánh giá ngay
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ✅ REVIEW MODAL - CHỈ CHO ĐÁNH GIÁ MỚI -->
    <?php if ($is_course_completed && !$user_review): ?>
        <div class="modal fade" id="reviewModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-gradient text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <h5 class="modal-title">
                            <i class="bi bi-star me-2"></i>Đánh giá khóa học
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="text-center mb-4">
                                <div class="bg-light rounded-pill d-inline-block px-4 py-2">
                                    <h6 class="mb-0 text-primary">
                                        <i class="bi bi-book me-1"></i>
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </h6>
                                </div>
                                <p class="text-muted small mt-2 mb-0">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Chia sẻ trải nghiệm học tập của bạn với cộng đồng.
                                    <strong class="text-warning">Lưu ý: Bạn chỉ có thể đánh giá một lần duy nhất!</strong>
                                </p>
                            </div>

                            <!-- ✅ FORM ĐÁNH GIÁ -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-star me-1"></i>
                                    Bạn đánh giá khóa học này bao nhiêu sao? <span class="text-danger">*</span>
                                </label>

                                <div class="rating-options">
                                    <?php
                                    $rating_labels = [
                                        1 => ['label' => 'Rất tệ', 'color' => 'danger'],
                                        2 => ['label' => 'Tệ', 'color' => 'warning'],
                                        3 => ['label' => 'Bình thường', 'color' => 'info'],
                                        4 => ['label' => 'Tốt', 'color' => 'primary'],
                                        5 => ['label' => 'Rất tốt', 'color' => 'success']
                                    ];
                                    ?>

                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rating"
                                                id="rating<?php echo $i; ?>" value="<?php echo $i; ?>" required>
                                            <label class="form-check-label d-flex justify-content-between align-items-center" for="rating<?php echo $i; ?>">
                                                <div>
                                                    <span class="text-warning fs-4 me-2">
                                                        <?php echo str_repeat('⭐', $i) . str_repeat('☆', 5 - $i); ?>
                                                    </span>
                                                    <span class="fw-semibold"><?php echo $rating_labels[$i]['label']; ?></span>
                                                    <small class="text-muted">({<?php echo $i; ?> sao)</small>
                                                </div>
                                                <span class="badge bg-<?php echo $rating_labels[$i]['color']; ?>"><?php echo $i; ?></span>
                                            </label>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="reviewComment" class="form-label fw-bold">
                                    <i class="bi bi-chat-dots me-1"></i>
                                    Nhận xét chi tiết <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" id="reviewComment" name="comment" rows="5" required
                                    style="resize: vertical;"
                                    placeholder="📖 Nội dung khóa học có hữu ích không?&#10;👨‍🏫 Giảng viên có dễ hiểu không?&#10;💯 Bạn có khuyến nghị khóa học này không?&#10;🎯 Điều gì bạn thích nhất về khóa học?&#10;🚀 Khóa học có giúp bạn đạt mục tiêu không?"></textarea>
                                <div class="form-text text-warning">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    <strong>Quan trọng:</strong> Hãy suy nghĩ kỹ trước khi gửi vì bạn không thể sửa đổi đánh giá sau này.
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle me-1"></i>Hủy bỏ
                            </button>
                            <button type="submit" name="submit_review" class="btn btn-primary btn-lg">
                                <i class="bi bi-send me-1"></i>🚀 Gửi đánh giá
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Reviews Display Modal không đổi -->
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
                        <!-- Rating Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-4 text-center">
                                <div class="bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                    <div>
                                        <div class="h2 mb-0"><?php echo $avg_rating; ?></div>
                                        <small>trung bình</small>
                                    </div>
                                </div>
                                <div class="stars fs-4 my-2">
                                    <?php echo str_repeat('★', round($avg_rating)) . str_repeat('☆', 5 - round($avg_rating)); ?>
                                </div>
                                <p class="text-muted"><?php echo $total_reviews; ?> đánh giá</p>
                            </div>
                            <div class="col-md-8">
                                <h6 class="mb-3">Phân bố đánh giá:</h6>
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
                                        <div class="progress flex-grow-1 me-2" style="height: 12px;">
                                            <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <small class="text-muted" style="min-width: 40px;"><?php echo $count; ?> lượt</small>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Reviews List -->
                        <div class="reviews-list" style="max-height: 500px; overflow-y: auto;">
                            <?php foreach ($course_reviews as $review): ?>
                                <div class="card mb-3 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                                    <i class="bi bi-person fs-4"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($review['username']); ?></h6>
                                                    <div class="stars text-warning fs-5">
                                                        <?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?>
                                                        <span class="ms-2 text-muted small"><?php echo $review['rating']; ?>/5 sao</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar me-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($review['created_at'])); ?>
                                            </small>
                                        </div>

                                        <div class="border-start border-3 border-primary ps-3 mb-3">
                                            <p class="mb-0" style="white-space: pre-line; line-height: 1.6;">
                                                <?php echo htmlspecialchars($review['comment']); ?>
                                            </p>
                                        </div>

                                        <?php if ($review['admin_response']): ?>
                                            <div class="alert alert-light border-start border-3 border-info mb-0">
                                                <div class="d-flex align-items-start">
                                                    <i class="bi bi-reply text-info fs-5 me-2 mt-1"></i>
                                                    <div class="flex-grow-1">
                                                        <small class="text-info fw-bold d-block mb-1">Phản hồi từ giảng viên:</small>
                                                        <p class="mb-1 small" style="white-space: pre-line;">
                                                            <?php echo htmlspecialchars($review['admin_response']); ?>
                                                        </p>
                                                        <?php if ($review['admin_responded_at']): ?>
                                                            <small class="text-muted">
                                                                <i class="bi bi-clock me-1"></i>
                                                                <?php echo date('d/m/Y H:i', strtotime($review['admin_responded_at'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
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
                            <?php if ($is_course_completed && !$user_review): ?>
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
        function loadLesson(lessonId) {
            window.location.href = `<?php echo SITE_URL; ?>/learn.php?course=<?php echo $course_id; ?>&lesson=${lessonId}`;
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        function markComplete(lessonId) {
            const btn = document.getElementById('markCompleteBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';
            btn.disabled = true;

            fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `mark_completed=1&lesson_id=${lessonId}&ajax=1`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Đã hoàn thành';
                        btn.className = 'btn btn-success btn-sm';
                        showToast('success', '🎉 Đã đánh dấu bài học hoàn thành!');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        showToast('error', '❌ ' + (data.message || 'Có lỗi xảy ra!'));
                    }
                })
                .catch(error => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    showToast('error', '💥 Có lỗi kết nối!');
                });
        }

        function showToast(type, message) {
            const toastDiv = document.createElement('div');
            toastDiv.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 position-fixed`;
            toastDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
            toastDiv.setAttribute('role', 'alert');
            toastDiv.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;

            document.body.appendChild(toastDiv);
            const toast = new bootstrap.Toast(toastDiv);
            toast.show();

            toastDiv.addEventListener('hidden.bs.toast', () => {
                toastDiv.remove();
            });
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth <= 768 && sidebar.classList.contains('show') && !sidebar.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    </script>

</body>

</html>