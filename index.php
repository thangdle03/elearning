<?php

require_once 'includes/config.php';

$page_title = 'Trang chủ';

// Lấy một số khóa học mẫu
$stmt = $pdo->query("SELECT c.*, cat.name as category_name 
                     FROM courses c 
                     LEFT JOIN categories cat ON c.category_id = cat.id 
                     ORDER BY c.created_at DESC 
                     LIMIT 6");
$courses = $stmt->fetchAll();
?>

<?php include 'includes/header.php'; ?>

<!-- Hero Section -->
<div class="bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3">
                    Học lập trình online hiệu quả
                </h1>
                <p class="lead mb-4">
                    Khám phá hàng trăm khóa học lập trình từ cơ bản đến nâng cao. 
                    Học mọi lúc, mọi nơi với chất lượng cao nhất.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?php echo SITE_URL; ?>/courses.php" class="btn btn-light btn-lg">
                        <i class="bi bi-book me-2"></i>Xem khóa học
                    </a>
                    <?php if (!isLoggedIn()): ?>
                    <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-outline-light btn-lg">
                        <i class="bi bi-person-plus me-2"></i>Đăng ký ngay
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <i class="bi bi-laptop display-1"></i>
            </div>
        </div>
    </div>
</div>

<!-- Courses Section -->
<div class="container my-5">
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h2 class="fw-bold">Khóa học nổi bật</h2>
            <p class="text-muted">Những khóa học được yêu thích nhất</p>
        </div>
    </div>
    
    <div class="row">
        <?php foreach ($courses as $course): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card course-card h-100">
                <img src="<?php echo $course['thumbnail'] ?: 'https://via.placeholder.com/300x200?text=Course'; ?>" 
                     class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>">
                <div class="card-body d-flex flex-column">
                    <div class="mb-2">
                        <span class="badge bg-secondary"><?php echo $course['category_name'] ?: 'Chưa phân loại'; ?></span>
                    </div>
                    <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                    <p class="card-text text-muted flex-grow-1">
                        <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?>
                    </p>
                    <div class="d-flex justify-content-between align-items-center mt-auto">
                        <h6 class="text-primary mb-0"><?php echo formatPrice($course['price']); ?></h6>
                        <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $course['id']; ?>" 
                           class="btn btn-primary btn-sm">
                            Xem chi tiết
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (empty($courses)): ?>
    <div class="text-center py-5">
        <i class="bi bi-book display-1 text-muted"></i>
        <h3 class="mt-3">Chưa có khóa học nào</h3>
        <p class="text-muted">Vui lòng quay lại sau.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Features Section -->
<div class="bg-light py-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 text-center mb-4">
                <i class="bi bi-play-circle display-4 text-primary mb-3"></i>
                <h5>Video chất lượng cao</h5>
                <p class="text-muted">Học với video HD, âm thanh rõ ràng</p>
            </div>
            <div class="col-md-4 text-center mb-4">
                <i class="bi bi-clock display-4 text-primary mb-3"></i>
                <h5>Học mọi lúc mọi nơi</h5>
                <p class="text-muted">Truy cập 24/7 trên mọi thiết bị</p>
            </div>
            <div class="col-md-4 text-center mb-4">
                <i class="bi bi-award display-4 text-primary mb-3"></i>
                <h5>Chứng chỉ hoàn thành</h5>
                <p class="text-muted">Nhận chứng chỉ sau khi hoàn thành</p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>