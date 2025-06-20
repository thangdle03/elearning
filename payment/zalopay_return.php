<?php
// filepath: d:\Xampp\htdocs\elearning\payment\zalopay_return.php

require_once '../includes/config.php';
require_once 'zalopay_config.php';

$order_code = $_GET['order_code'] ?? '';

if (!$order_code) {
    redirect(SITE_URL . '/courses.php');
}

// Lấy thông tin order
$stmt = $pdo->prepare("SELECT o.*, c.title as course_title FROM orders o LEFT JOIN courses c ON o.course_id = c.id WHERE o.order_code = ?");
$stmt->execute([$order_code]);
$order = $stmt->fetch();

if (!$order) {
    redirect(SITE_URL . '/courses.php');
}

$page_title = 'Kết quả thanh toán';
include '../includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <?php if ($order['status'] == 'paid'): ?>
                        <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                        <h3 class="text-success">Thanh toán thành công!</h3>
                        <p class="text-muted">Bạn đã được đăng ký vào khóa học</p>
                        <h5 class="text-primary"><?php echo htmlspecialchars($order['course_title']); ?></h5>
                        <p class="mb-4">Số tiền: <strong><?php echo number_format($order['amount']); ?>₫</strong></p>
                        
                        <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $order['course_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-play me-2"></i>Bắt đầu học
                        </a>
                        
                    <?php elseif ($order['status'] == 'failed'): ?>
                        <i class="fas fa-times-circle text-danger fa-4x mb-3"></i>
                        <h3 class="text-danger">Thanh toán thất bại!</h3>
                        <p class="text-muted">Giao dịch không thành công</p>
                        <h5 class="text-muted"><?php echo htmlspecialchars($order['course_title']); ?></h5>
                        
                        <a href="<?php echo SITE_URL; ?>/course-detail.php?id=<?php echo $order['course_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-redo me-2"></i>Thử lại
                        </a>
                        
                    <?php else: ?>
                        <i class="fas fa-clock text-warning fa-4x mb-3"></i>
                        <h3 class="text-warning">Đang xử lý thanh toán...</h3>
                        <p class="text-muted">Vui lòng đợi trong giây lát</p>
                        <h5 class="text-muted"><?php echo htmlspecialchars($order['course_title']); ?></h5>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Trang sẽ tự động cập nhật sau 5 giây
                        </div>
                        
                        <script>
                        // Auto refresh every 5 seconds to check status
                        setTimeout(() => {
                            location.reload();
                        }, 5000);
                        </script>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <small class="text-muted">
                            Mã đơn hàng: <?php echo htmlspecialchars($order['order_code']); ?><br>
                            Thời gian: <?php echo date('d/m/Y H:i:s', strtotime($order['created_at'])); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>