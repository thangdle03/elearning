<?php
// filepath: d:\Xampp\htdocs\elearning\admin\add-lesson.php
require_once '../includes/config.php';

// Check authentication
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Get course ID from URL
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Verify course exists
if ($course_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        $_SESSION['error'] = 'Khóa học không tồn tại.';
        header('Location: courses.php');
        exit;
    }
} else {
    // Get all courses for dropdown
    $stmt = $pdo->query("SELECT id, title FROM courses ORDER BY title ASC");
    $all_courses = $stmt->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $youtube_url = trim($_POST['youtube_url']);
    $order_number = (int)$_POST['order_number'];

    $errors = [];

    // Validate inputs
    if (empty($title)) {
        $errors[] = 'Tiêu đề bài học không được để trống.';
    }

    if ($course_id <= 0) {
        $errors[] = 'Vui lòng chọn khóa học.';
    }

    if ($order_number <= 0) {
        $errors[] = 'Thứ tự bài học phải lớn hơn 0.';
    }

    // Validate YouTube URL if provided
    if (!empty($youtube_url)) {
        if (!filter_var($youtube_url, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL YouTube không hợp lệ.';
        } elseif (!preg_match('/youtube\.com|youtu\.be/', $youtube_url)) {
            $errors[] = 'Vui lòng nhập URL YouTube hợp lệ.';
        }
    }

    // Check if course exists
    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $selected_course = $stmt->fetch();

    if (!$selected_course) {
        $errors[] = 'Khóa học được chọn không tồn tại.';
    }

    // Check if order number already exists for this course
    $stmt = $pdo->prepare("SELECT id FROM lessons WHERE course_id = ? AND order_number = ?");
    $stmt->execute([$course_id, $order_number]);
    if ($stmt->fetch()) {
        $errors[] = 'Thứ tự bài học đã tồn tại trong khóa học này.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO lessons (course_id, title, youtube_url, order_number) 
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([$course_id, $title, $youtube_url, $order_number]);

            $_SESSION['success'] = 'Thêm bài học thành công!';
            header('Location: course-detail.php?id=' . $course_id);
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Có lỗi xảy ra khi thêm bài học: ' . $e->getMessage();
        }
    }
}

// Get next order number for selected course
$next_order = 1;
if (isset($_GET['course_id']) && $course_id > 0) {
    $stmt = $pdo->prepare("SELECT MAX(order_number) as max_order FROM lessons WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $result = $stmt->fetch();
    $next_order = ($result['max_order'] ?? 0) + 1;
}

$page_title = 'Thêm bài học mới';
$current_page = 'courses';
?>

<?php include 'includes/admin-header.php'; ?>

<div class="container-fluid px-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a>
            </li>
            <li class="breadcrumb-item">
                <a href="courses.php"><i class="fas fa-book me-1"></i>Khóa học</a>
            </li>
            <?php if (isset($course)): ?>
                <li class="breadcrumb-item">
                    <a href="course-detail.php?id=<?php echo $course['id']; ?>">
                        <?php echo htmlspecialchars($course['title']); ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page">Thêm bài học</li>
        </ol>
    </nav>

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-plus-circle me-2"></i>Thêm bài học mới
        </h1>
        <div class="btn-group" role="group">
            <?php if (isset($course)): ?>
                <a href="course-detail.php?id=<?php echo $course['id']; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại khóa học
                </a>
            <?php else: ?>
                <a href="courses.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Display Errors -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Có lỗi xảy ra:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-edit me-2"></i>Thông tin bài học
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <!-- Course Selection -->
                        <?php if (isset($course)): ?>
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Khóa học</label>
                                <div class="alert alert-info">
                                    <i class="fas fa-book me-2"></i>
                                    <strong><?php echo htmlspecialchars($course['title']); ?></strong>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mb-4">
                                <label for="course_id" class="form-label fw-bold">
                                    Chọn khóa học <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="course_id" name="course_id" required onchange="updateOrderNumber()">
                                    <option value="">-- Chọn khóa học --</option>
                                    <?php foreach ($all_courses as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"
                                            <?php echo (isset($_POST['course_id']) && $_POST['course_id'] == $c['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Vui lòng chọn khóa học.
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Lesson Title -->
                        <div class="mb-4">
                            <label for="title" class="form-label fw-bold">
                                Tiêu đề bài học <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                class="form-control"
                                id="title"
                                name="title"
                                value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                placeholder="Nhập tiêu đề bài học..."
                                required>
                            <div class="invalid-feedback">
                                Vui lòng nhập tiêu đề bài học.
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Tiêu đề nên rõ ràng và mô tả nội dung bài học
                            </div>
                        </div>

                        <!-- YouTube URL -->
                        <div class="mb-4">
                            <label for="youtube_url" class="form-label fw-bold">
                                <i class="fab fa-youtube text-danger me-1"></i>
                                URL YouTube
                            </label>
                            <input type="url"
                                class="form-control"
                                id="youtube_url"
                                name="youtube_url"
                                value="<?php echo htmlspecialchars($_POST['youtube_url'] ?? ''); ?>"
                                placeholder="https://www.youtube.com/watch?v=...">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Nhập link video YouTube cho bài học này (tùy chọn)
                            </div>
                        </div>

                        <!-- Order Number -->
                        <div class="mb-4">
                            <label for="order_number" class="form-label fw-bold">
                                Thứ tự bài học <span class="text-danger">*</span>
                            </label>
                            <input type="number"
                                class="form-control"
                                id="order_number"
                                name="order_number"
                                value="<?php echo htmlspecialchars($_POST['order_number'] ?? $next_order); ?>"
                                min="1"
                                required>
                            <div class="invalid-feedback">
                                Vui lòng nhập thứ tự bài học (số nguyên dương).
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Thứ tự hiển thị của bài học trong khóa học
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Thêm bài học
                            </button>

                            <?php if (isset($course)): ?>
                                <a href="course-detail.php?id=<?php echo $course['id']; ?>" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Hủy
                                </a>
                            <?php else: ?>
                                <a href="courses.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Hủy
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Help Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-info">
                        <i class="fas fa-question-circle me-2"></i>Hướng dẫn
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary">
                            <i class="fas fa-lightbulb me-1"></i>Mẹo tạo bài học hiệu quả:
                        </h6>
                        <ul class="small text-muted mb-0">
                            <li>Đặt tiêu đề rõ ràng, dễ hiểu</li>
                            <li>Sắp xếp thứ tự logic từ cơ bản đến nâng cao</li>
                            <li>Sử dụng video YouTube chất lượng cao</li>
                            <li>Kiểm tra link video trước khi lưu</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-success">
                            <i class="fab fa-youtube me-1"></i>YouTube URL:
                        </h6>
                        <p class="small text-muted mb-2">Các định dạng được chấp nhận:</p>
                        <ul class="small text-muted mb-0">
                            <li>https://www.youtube.com/watch?v=VIDEO_ID</li>
                            <li>https://youtu.be/VIDEO_ID</li>
                        </ul>
                    </div>

                    <div class="alert alert-warning small">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Lưu ý:</strong> Thứ tự bài học phải là duy nhất trong mỗi khóa học.
                    </div>
                </div>
            </div>

            <!-- Course Info (if editing specific course) -->
            <?php if (isset($course)): ?>
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-success">
                            <i class="fas fa-book me-2"></i>Thông tin khóa học
                        </h6>
                    </div>
                    <div class="card-body">
                        <h6 class="text-primary mb-2"><?php echo htmlspecialchars($course['title']); ?></h6>

                        <?php if (!empty($course['description'])): ?>
                            <p class="small text-muted mb-3">
                                <?php echo htmlspecialchars(mb_substr($course['description'], 0, 150)); ?>
                                <?php if (mb_strlen($course['description']) > 150) echo '...'; ?>
                            </p>
                        <?php endif; ?>

                        <?php
                        // Get current lesson count
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
                        $stmt->execute([$course['id']]);
                        $lesson_count = $stmt->fetchColumn();
                        ?>

                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-end">
                                    <h5 class="text-info mb-0"><?php echo $lesson_count; ?></h5>
                                    <small class="text-muted">Bài học hiện có</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <h5 class="text-warning mb-0"><?php echo $next_order; ?></h5>
                                <small class="text-muted">Thứ tự tiếp theo</small>
                            </div>
                        </div>

                        <div class="mt-3">
                            <a href="course-detail.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                <i class="fas fa-eye me-1"></i>Xem chi tiết khóa học
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
    .card {
        border: none;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #4e73df;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }

    .btn-primary {
        background-color: #4e73df;
        border-color: #4e73df;
    }

    .btn-primary:hover {
        background-color: #2653d4;
        border-color: #2653d4;
    }

    .breadcrumb-item a {
        text-decoration: none;
        color: #5a5c69;
    }

    .breadcrumb-item a:hover {
        color: #4e73df;
    }

    .alert {
        border: none;
        border-radius: 0.35rem;
    }

    .form-label.fw-bold {
        color: #5a5c69;
    }

    .text-danger {
        color: #e74a3b !important;
    }

    .border-end {
        border-right: 1px solid #e3e6f0 !important;
    }
</style>

<!-- JavaScript -->
<script>
    // Form validation
    (function() {
        'use strict';
        window.addEventListener('load', function() {
            var forms = document.getElementsByClassName('needs-validation');
            var validation = Array.prototype.filter.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        }, false);
    })();

    // Update order number when course changes
    function updateOrderNumber() {
        const courseSelect = document.getElementById('course_id');
        const orderInput = document.getElementById('order_number');

        if (courseSelect.value) {
            // AJAX call to get next order number
            fetch('ajax/get-next-lesson-order.php?course_id=' + courseSelect.value)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        orderInput.value = data.next_order;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
    }

    // YouTube URL validation
    document.getElementById('youtube_url').addEventListener('blur', function() {
        const url = this.value.trim();
        if (url && !url.match(/youtube\.com|youtu\.be/)) {
            this.setCustomValidity('Vui lòng nhập URL YouTube hợp lệ');
        } else {
            this.setCustomValidity('');
        }
    });
</script>

<?php include 'includes/admin-footer.php'; ?>