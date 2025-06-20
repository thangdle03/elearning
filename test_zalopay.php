<?php
// filepath: d:\Xampp\htdocs\elearning\test_zalopay.php
require_once 'includes/config.php';
// Không cần require zalopay_config ở đây vì sẽ được gọi trong API

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php');
}

// Lấy khóa học để test
$stmt = $pdo->prepare("SELECT * FROM courses LIMIT 5");
$stmt->execute();
$courses = $stmt->fetchAll();

$page_title = 'Test ZaloPay Integration';
include 'includes/header.php';
?>

<div class="container my-5">
    <h1>🧪 Test ZaloPay Integration</h1>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Chọn khóa học để test thanh toán</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($courses as $course): ?>
                    <div class="course-item border p-3 mb-3 rounded">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6><?php echo htmlspecialchars($course['title']); ?></h6>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($course['description']); ?></p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="price mb-2">
                                    <strong class="text-success"><?php echo number_format($course['price']); ?>₫</strong>
                                </div>
                                <button class="btn btn-primary" onclick="testPayment(<?php echo $course['id']; ?>)">
                                    <i class="fab fa-zap me-2"></i>Test ZaloPay
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6>📊 Thống kê orders</h6>
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT status, COUNT(*) as count, SUM(amount) as total 
                        FROM orders 
                        WHERE user_id = ? 
                        GROUP BY status
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $stats = $stmt->fetchAll();
                    ?>
                    
                    <?php foreach ($stats as $stat): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="badge bg-<?php 
                            echo $stat['status'] == 'paid' ? 'success' : 
                                ($stat['status'] == 'pending' ? 'warning' : 'danger'); 
                        ?>">
                            <?php echo ucfirst($stat['status']); ?>
                        </span>
                        <span><?php echo $stat['count']; ?> đơn - <?php echo number_format($stat['total'] ?? 0); ?>₫</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h6>📝 Orders gần đây</h6>
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT o.*, c.title as course_title 
                        FROM orders o
                        LEFT JOIN courses c ON o.course_id = c.id
                        WHERE o.user_id = ? 
                        ORDER BY o.created_at DESC 
                        LIMIT 5
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $recent_orders = $stmt->fetchAll();
                    ?>
                    
                    <?php foreach ($recent_orders as $order): ?>
                    <div class="small mb-2 pb-2 border-bottom">
                        <div class="d-flex justify-content-between">
                            <span><?php echo htmlspecialchars($order['course_title']); ?></span>
                            <span class="badge bg-<?php 
                                echo $order['status'] == 'paid' ? 'success' : 
                                    ($order['status'] == 'pending' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo $order['status']; ?>
                            </span>
                        </div>
                        <div class="text-muted">
                            <?php echo number_format($order['amount']); ?>₫ - 
                            <?php echo date('d/m H:i', strtotime($order['created_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ZaloPay Payment Test</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="paymentContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3"></div>
                        <p>Đang tạo đơn hàng...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
let paymentData = null;
let statusInterval = null;

function testPayment(courseId) {
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
    
    fetch('/api/create_zalopay_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            course_id: courseId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            paymentData = data;
            showPaymentOptions(data);
            startStatusCheck(data.order_code);
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        showError('Có lỗi xảy ra: ' + error.message);
    });
}

function showPaymentOptions(data) {
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    document.getElementById('paymentContent').innerHTML = `
        <div class="text-center">
            <h5 class="text-primary mb-3">${data.course_title}</h5>
            <div class="alert alert-success">
                <h4 class="mb-0">${new Intl.NumberFormat('vi-VN').format(data.amount)}₫</h4>
            </div>
            
            <div class="payment-options mb-4">
                ${isMobile ? `
                <button class="btn btn-primary btn-lg mb-2 w-100" onclick="openZaloPayApp()">
                    <i class="fab fa-zap me-2"></i>Mở ZaloPay App
                </button>
                ` : ''}
                
                <button class="btn btn-info btn-lg mb-2 w-100" onclick="openWebPayment()">
                    <i class="fas fa-external-link-alt me-2"></i>Thanh toán trên Web
                </button>
                
                <button class="btn btn-success btn-lg mb-2 w-100" onclick="showQRCode()">
                    <i class="fas fa-qrcode me-2"></i>Hiển thị mã QR
                </button>
            </div>
            
            <div id="qrSection" style="display: none;">
                <h6>Quét mã QR để thanh toán:</h6>
                <div id="qrcode" class="mb-3"></div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Order Code: <code>${data.order_code}</code>
            </div>
            
            <div id="statusSection" class="alert alert-warning">
                <i class="fas fa-clock me-2"></i>
                Trạng thái: <span id="paymentStatus">Đang chờ thanh toán...</span>
            </div>
        </div>
    `;
}

function openZaloPayApp() {
    if (paymentData && paymentData.zp_trans_token) {
        const deepLink = `zalopay://paymentv2?order_token=${paymentData.zp_trans_token}`;
        window.location.href = deepLink;
        
        // Fallback after 3 seconds
        setTimeout(() => {
            alert('Không thể mở ZaloPay app. Vui lòng tải app ZaloPay từ App Store/Google Play');
        }, 3000);
    }
}

function openWebPayment() {
    if (paymentData && paymentData.order_url) {
        window.open(paymentData.order_url, '_blank');
    }
}

function showQRCode() {
    const qrSection = document.getElementById('qrSection');
    const qrDiv = document.getElementById('qrcode');
    
    qrDiv.innerHTML = '';
    
    new QRCode(qrDiv, {
        text: paymentData.order_url,
        width: 200,
        height: 200,
        colorDark: "#0068FF",
        colorLight: "#ffffff"
    });
    
    qrSection.style.display = 'block';
}

function startStatusCheck(orderCode) {
    statusInterval = setInterval(() => {
        fetch(`/api/check_zalopay_status.php?order_code=${orderCode}`)
            .then(response => response.json())
            .then(data => {
                const statusElement = document.getElementById('paymentStatus');
                
                if (data.success) {
                    if (data.status === 'paid') {
                        clearInterval(statusInterval);
                        statusElement.innerHTML = '<span class="text-success">✅ Thanh toán thành công!</span>';
                        
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                        
                    } else if (data.status === 'failed') {
                        clearInterval(statusInterval);
                        statusElement.innerHTML = '<span class="text-danger">❌ Thanh toán thất bại</span>';
                    } else {
                        statusElement.innerHTML = '<span class="text-warning">⏳ Đang chờ thanh toán...</span>';
                    }
                }
            })
            .catch(error => {
                console.error('Error checking status:', error);
            });
    }, 5000);
}

function showError(message) {
    document.getElementById('paymentContent').innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
        </div>
    `;
}

// Clean up interval when modal is closed
document.getElementById('paymentModal').addEventListener('hidden.bs.modal', function () {
    if (statusInterval) {
        clearInterval(statusInterval);
    }
});
</script>

<?php include 'includes/footer.php'; ?>