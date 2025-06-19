<?php
// filepath: d:\Xampp\htdocs\elearning\admin\get_review.php

require_once '../includes/config.php';

// Check admin authentication
if (!isAdmin()) {
    http_response_code(403);
    echo '<div class="alert alert-danger"><i class="fas fa-lock me-2"></i>Bạn không có quyền truy cập!</div>';
    exit;
}

$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$review_id) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>ID đánh giá không hợp lệ!</div>';
    exit;
}

try {
    // Get review details with user and course info
    $stmt = $pdo->prepare("
        SELECT r.*, 
               u.username, u.email, u.full_name, u.created_at as user_joined,
               c.title as course_title, c.description as course_desc,
               admin.username as admin_name, admin.full_name as admin_full_name
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        JOIN courses c ON r.course_id = c.id 
        LEFT JOIN users admin ON r.admin_id = admin.id
        WHERE r.id = ?
    ");
    $stmt->execute([$review_id]);
    $review = $stmt->fetch();

    if (!$review) {
        echo '<div class="alert alert-danger"><i class="fas fa-search me-2"></i>Không tìm thấy đánh giá!</div>';
        exit;
    }

    // Get user's other reviews for this course (if any)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_reviews, AVG(rating) as avg_rating 
        FROM reviews 
        WHERE user_id = ? AND status = 'approved'
    ");
    $stmt->execute([$review['user_id']]);
    $user_stats = $stmt->fetch();

    // Get course stats
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_reviews, AVG(rating) as avg_rating 
        FROM reviews 
        WHERE course_id = ? AND status = 'approved'
    ");
    $stmt->execute([$review['course_id']]);
    $course_stats = $stmt->fetch();

} catch (PDOException $e) {
    echo '<div class="alert alert-danger"><i class="fas fa-database me-2"></i>Lỗi cơ sở dữ liệu: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Status configuration
$status_config = [
    'pending' => ['class' => 'warning', 'icon' => 'clock', 'text' => 'Chờ duyệt'],
    'approved' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Đã duyệt'],
    'rejected' => ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'Từ chối']
];
$config = $status_config[$review['status']];
?>

<div class="container-fluid">
    <!-- Review Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge bg-<?php echo $config['class']; ?> me-3">
                                    <i class="fas fa-<?php echo $config['icon']; ?> me-1"></i>
                                    <?php echo $config['text']; ?>
                                </span>
                                <h5 class="mb-0">Đánh giá #<?php echo $review['id']; ?></h5>
                            </div>
                            <div class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                Tạo: <?php echo date('d/m/Y H:i', strtotime($review['created_at'])); ?>
                                <?php if ($review['updated_at'] && $review['updated_at'] != $review['created_at']): ?>
                                    | Cập nhật: <?php echo date('d/m/Y H:i', strtotime($review['updated_at'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="d-flex align-items-center justify-content-md-end">
                                <div class="text-warning me-2 fs-4">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $review['rating']) {
                                            echo '<i class="fas fa-star"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <span class="badge bg-primary fs-6"><?php echo $review['rating']; ?>/5</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column - User & Course Info -->
        <div class="col-lg-4">
            <!-- User Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-user me-2"></i>Thông tin người dùng
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="avatar-lg bg-primary text-white rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-user fs-2"></i>
                        </div>
                        <h6 class="fw-bold"><?php echo htmlspecialchars($review['full_name'] ?: $review['username']); ?></h6>
                        <small class="text-muted">@<?php echo htmlspecialchars($review['username']); ?></small>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <div class="fw-bold text-primary"><?php echo number_format($user_stats['total_reviews']); ?></div>
                                <small class="text-muted">Đánh giá</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold text-warning"><?php echo number_format($user_stats['avg_rating'], 1); ?></div>
                            <small class="text-muted">Điểm TB</small>
                        </div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="small">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Email:</span>
                            <span><?php echo htmlspecialchars($review['email']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Tham gia:</span>
                            <span><?php echo date('d/m/Y', strtotime($review['user_joined'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Information -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-book me-2"></i>Thông tin khóa học
                    </h6>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($review['course_title']); ?></h6>
                    
                    <?php if ($review['course_desc']): ?>
                    <p class="text-muted small mb-3">
                        <?php echo htmlspecialchars(substr($review['course_desc'], 0, 150)); ?>
                        <?php if (strlen($review['course_desc']) > 150): ?>...<?php endif; ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <div class="fw-bold text-info"><?php echo number_format($course_stats['total_reviews']); ?></div>
                                <small class="text-muted">Đánh giá</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold text-warning"><?php echo number_format($course_stats['avg_rating'], 1); ?></div>
                            <small class="text-muted">Điểm TB</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Review Content -->
        <div class="col-lg-8">
            <!-- Review Content -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">
                        <i class="fas fa-comment-alt me-2"></i>Nội dung đánh giá
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($review['comment']): ?>
                        <div class="bg-light p-4 rounded-3 border-start border-4 border-primary">
                            <div class="review-content" style="white-space: pre-line; line-height: 1.8; font-size: 1.1rem;">
                                <?php echo htmlspecialchars($review['comment']); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-comment-slash fa-3x mb-3"></i>
                            <p class="mb-0">Người dùng chỉ đánh giá sao mà không có nhận xét chi tiết</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Admin Response -->
            <?php if ($review['admin_response']): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-reply me-2"></i>Phản hồi từ Admin
                    </h6>
                </div>
                <div class="card-body">
                    <div class="bg-light p-4 rounded-3 border-start border-4 border-success">
                        <div style="white-space: pre-line; line-height: 1.6;">
                            <?php echo htmlspecialchars($review['admin_response']); ?>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3 text-muted small">
                        <span>
                            <i class="fas fa-user-shield me-1"></i>
                            Phản hồi bởi: <strong><?php echo htmlspecialchars($review['admin_name']); ?></strong>
                        </span>
                        <?php if ($review['admin_responded_at']): ?>
                        <span>
                            <i class="fas fa-clock me-1"></i>
                            <?php echo date('d/m/Y H:i', strtotime($review['admin_responded_at'])); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">
                        <i class="fas fa-cogs me-2"></i>Thao tác nhanh
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($review['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-success" onclick="approveReview(<?php echo $review['id']; ?>)">
                            <i class="fas fa-check me-1"></i>Phê duyệt
                        </button>
                        <button type="button" class="btn btn-warning" onclick="rejectReview(<?php echo $review['id']; ?>)">
                            <i class="fas fa-times me-1"></i>Từ chối
                        </button>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-primary" onclick="respondReview(<?php echo $review['id']; ?>)">
                            <i class="fas fa-reply me-1"></i>
                            <?php echo $review['admin_response'] ? 'Sửa phản hồi' : 'Phản hồi'; ?>
                        </button>
                        
                        <button type="button" class="btn btn-info" onclick="window.open('<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $review['course_id']; ?>', '_blank')">
                            <i class="fas fa-external-link-alt me-1"></i>Xem khóa học
                        </button>
                        
                        <button type="button" class="btn btn-outline-danger" onclick="deleteReview(<?php echo $review['id']; ?>)">
                            <i class="fas fa-trash me-1"></i>Xóa đánh giá
                        </button>
                    </div>
                    
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>
                            <strong>Lưu ý:</strong> Các thao tác sẽ được thực hiện trực tiếp và có thể làm refresh trang chính.
                            Phản hồi sẽ hiển thị công khai cho tất cả người dùng.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-lg {
    width: 80px;
    height: 80px;
}

.review-content {
    font-family: 'Segoe UI', system-ui, sans-serif;
}

.card {
    transition: transform 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.border-start {
    border-left-width: 4px !important;
}
</style>

<script>
// These functions will call the parent window's functions
function approveReview(reviewId) {
    if (confirm('Bạn có chắc muốn phê duyệt đánh giá này?')) {
        parent.submitAction(reviewId, 'approve');
        parent.bootstrap.Modal.getInstance(document.querySelector('#reviewModal')).hide();
    }
}

function rejectReview(reviewId) {
    if (confirm('Bạn có chắc muốn từ chối đánh giá này?')) {
        parent.submitAction(reviewId, 'reject');
        parent.bootstrap.Modal.getInstance(document.querySelector('#reviewModal')).hide();
    }
}

function deleteReview(reviewId) {
    if (confirm('Bạn có chắc muốn xóa đánh giá này? Hành động này không thể hoàn tác!')) {
        parent.submitAction(reviewId, 'delete');
        parent.bootstrap.Modal.getInstance(document.querySelector('#reviewModal')).hide();
    }
}

function respondReview(reviewId) {
    parent.respondReview(reviewId);
    parent.bootstrap.Modal.getInstance(document.querySelector('#reviewModal')).hide();
}
</script>