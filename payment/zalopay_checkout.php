<?php
// filepath: d:\Xampp\htdocs\elearning\payment\zalopay_checkout.php

require_once '../includes/config.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php');
}

$course_id = $_GET['course'] ?? 0;

if (!$course_id) {
    redirect(SITE_URL . '/courses.php');
}

// Lấy thông tin khóa học
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND status = 'active'");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    redirect(SITE_URL . '/courses.php');
}

// Kiểm tra đã đăng ký chưa
$stmt = $pdo->prepare("SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ?");
$stmt->execute([$_SESSION['user_id'], $course_id]);
if ($stmt->fetch()) {
    redirect(SITE_URL . '/course-detail.php?id=' . $course_id);
}

$page_title = 'Thanh toán ZaloPay - ' . $course['title'];
include '../includes/header.php';
?>

<style>
.zalopay-container {
    background: linear-gradient(135deg, #0068FF 0%, #0052CC 100%);
    min-height: 100vh;
    padding: 2rem 0;
}

.payment-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 25px 50px rgba(0,0,0,0.1);
    overflow: hidden;
    max-width: 500px;
    margin: 0 auto;
}

.payment-header {
    background: linear-gradient(135deg, #0068FF 0%, #0052CC 100%);
    color: white;
    padding: 2rem;
    text-align: center;
}

.course-info {
    padding: 2rem;
    text-align: center;
}

.course-price {
    font-size: 2.5rem;
    font-weight: 800;
    color: #0068FF;
    margin: 1rem 0;
}

.payment-methods {
    padding: 0 2rem 2rem;
}

.payment-btn {
    background: linear-gradient(135deg, #0068FF 0%, #0052CC 100%);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 1rem;
    width: 100%;
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.payment-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 104, 255, 0.3);
}

.payment-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.qr-section {
    display: none;
    text-align: center;
    padding: 2rem;
    border-top: 1px solid #e5e7eb;
}

.app-download {
    background: #f8fafc;
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1rem;
}

.device-detection {
    background: #dbeafe;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    text-align: center;
}

.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="zalopay-container">
    <div class="container">
        <div class="payment-card">
            <!-- Header -->
            <div class="payment-header">
                <h1><i class="fab fa-zap me-2"></i>ZaloPay</h1>
                <p class="mb-0">Thanh toán nhanh chóng & an toàn</p>
            </div>
            
            <!-- Course Info -->
            <div class="course-info">
                <h4><?php echo htmlspecialchars($course['title']); ?></h4>
                <div class="course-price"><?php echo number_format($course['price'], 0, ',', '.'); ?>₫</div>
                <p class="text-muted">Thanh toán một lần, học trọn đời</p>
            </div>
            
            <!-- Device Detection -->
            <div class="device-detection" id="deviceInfo">
                <i class="fas fa-mobile-alt me-2"></i>
                <span id="deviceText">Đang phát hiện thiết bị...</span>
            </div>
            
            <!-- Payment Methods -->
            <div class="payment-methods">
                <!-- Mobile Payment -->
                <button class="payment-btn" id="mobilePayBtn" style="display: none;" onclick="payWithApp()">
                    <i class="fas fa-mobile-alt me-2"></i>
                    Mở ZaloPay App
                </button>
                
                <!-- Web Payment -->
                <button class="payment-btn" id="webPayBtn" onclick="payWithWeb()">
                    <i class="fas fa-globe me-2"></i>
                    Thanh toán trên Web
                </button>
                
                <!-- QR Payment -->
                <button class="payment-btn" onclick="payWithQR()">
                    <i class="fas fa-qrcode me-2"></i>
                    Quét mã QR
                </button>
            </div>
            
            <!-- QR Section -->
            <div class="qr-section" id="qrSection">
                <h5><i class="fas fa-qrcode me-2"></i>Thanh toán ZaloPay</h5>
                <div id="qrcode" class="mb-3">
                    <!-- QR sẽ được generate ở đây -->
                </div>
                <p class="text-muted small">Quét mã QR hoặc click link để thanh toán</p>
                
                <div class="app-download">
                    <h6>Chưa có ZaloPay?</h6>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="https://apps.apple.com/vn/app/zalopay/id1112986692" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="fab fa-apple me-1"></i>App Store
                        </a>
                        <a href="https://play.google.com/store/apps/details?id=vn.com.vng.zalopay" target="_blank" class="btn btn-outline-success btn-sm">
                            <i class="fab fa-google-play me-1"></i>Google Play
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Status Section -->
            <div id="statusSection" style="display: none;" class="p-3 border-top">
                <div class="text-center">
                    <div class="loading-spinner me-2"></div>
                    <span id="statusText">Đang chờ thanh toán...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary mb-3"></div>
                <p class="mb-0" id="loadingText">Đang tạo đơn hàng...</p>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="cancelLoading()">
                    Hủy
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let paymentData = null;
let statusInterval = null;
const courseId = <?php echo $course_id; ?>;

// Debug function
function debugLog(message, data = null) {
    console.log('[ZaloPay Debug]', message, data);
}

// Device Detection
function detectDevice() {
    const userAgent = navigator.userAgent.toLowerCase();
    const isMobile = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(userAgent);
    
    const deviceInfo = document.getElementById('deviceInfo');
    const deviceText = document.getElementById('deviceText');
    const mobilePayBtn = document.getElementById('mobilePayBtn');
    const webPayBtn = document.getElementById('webPayBtn');
    
    if (isMobile) {
        deviceText.innerHTML = `<i class="fas fa-mobile-alt me-2"></i>Thiết bị di động được phát hiện`;
        deviceInfo.className = 'device-detection bg-success text-white';
        mobilePayBtn.style.display = 'block';
        webPayBtn.innerHTML = '<i class="fas fa-external-link-alt me-2"></i>Mở trình duyệt';
    } else {
        deviceText.innerHTML = `<i class="fas fa-desktop me-2"></i>Máy tính - Khuyến nghị quét QR`;
        deviceInfo.className = 'device-detection bg-info text-white';
    }
    
    return { isMobile };
}

// Create ZaloPay Order với error handling tốt hơn
async function createZaloPayOrder() {
    debugLog('Creating ZaloPay order for course:', courseId);
    
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    try {
        // Sử dụng đường dẫn tuyệt đối
        const apiUrl = window.location.origin + '/elearning/api/create_zalopay_payment.php';
        debugLog('API URL:', apiUrl);
        
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                course_id: courseId
            })
        });
        
        debugLog('Response status:', response.status);
        debugLog('Response headers:', response.headers);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        debugLog('API Response:', data);
        
        loadingModal.hide();
        
        if (data.success) {
            paymentData = data;
            return data;
        } else {
            throw new Error(data.message || 'Unknown error from API');
        }
        
    } catch (error) {
        loadingModal.hide();
        debugLog('API Error:', error);
        
        // Show detailed error
        alert(`Lỗi tạo đơn hàng:\n${error.message}\n\nVui lòng kiểm tra Console (F12) để xem chi tiết.`);
        return null;
    }
}

// Pay with Mobile App
async function payWithApp() {
    debugLog('Starting mobile app payment');
    
    if (!paymentData) {
        paymentData = await createZaloPayOrder();
        if (!paymentData) return;
    }
    
    const deepLink = `zalopay://paymentv2?order_token=${paymentData.zp_trans_token}`;
    debugLog('Deep link:', deepLink);
    
    // Try to open app
    window.location.href = deepLink;
    
    // Show status and start monitoring
    showStatus();
    monitorPaymentStatus();
}

// Pay with Web
async function payWithWeb() {
    debugLog('Starting web payment');
    
    if (!paymentData) {
        paymentData = await createZaloPayOrder();
        if (!paymentData) return;
    }
    
    debugLog('Opening payment URL:', paymentData.order_url);
    
    // Open in new window
    window.open(paymentData.order_url, '_blank');
    
    // Show status and start monitoring
    showStatus();
    monitorPaymentStatus();
}

// Pay with QR Code
async function payWithQR() {
    debugLog('Starting QR payment');
    
    if (!paymentData) {
        paymentData = await createZaloPayOrder();
        if (!paymentData) return;
    }
    
    const qrSection = document.getElementById('qrSection');
    const qrCodeDiv = document.getElementById('qrcode');
    
    // Generate QR với Google API
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(paymentData.order_url)}&color=0068FF&bgcolor=FFFFFF`;
    
    qrCodeDiv.innerHTML = `
        <div class="text-center">
            <img src="${qrUrl}" 
                 alt="QR Code thanh toán" 
                 class="img-fluid mb-3"
                 style="border: 3px solid #0068FF; border-radius: 12px; max-width: 200px; box-shadow: 0 4px 12px rgba(0,104,255,0.3);">
            <br>
            <a href="${paymentData.order_url}" target="_blank" class="btn btn-primary">
                <i class="fas fa-external-link-alt me-2"></i>Mở trang thanh toán
            </a>
        </div>
    `;
    
    // Show QR section
    qrSection.style.display = 'block';
    
    // Show status and start monitoring
    showStatus();
    monitorPaymentStatus();
    
    // Scroll to QR
    qrSection.scrollIntoView({ behavior: 'smooth' });
    
    debugLog('QR Code generated with Google API:', qrUrl);
}

// Show Status Section
function showStatus() {
    const statusSection = document.getElementById('statusSection');
    statusSection.style.display = 'block';
}

// Monitor Payment Status
function monitorPaymentStatus() {
    if (!paymentData) return;
    
    debugLog('Starting payment status monitoring for:', paymentData.order_code);
    
    const statusText = document.getElementById('statusText');
    
    statusInterval = setInterval(async () => {
        try {
            const apiUrl = window.location.origin + '/elearning/api/check_zalopay_status.php';
            const response = await fetch(`${apiUrl}?order_code=${paymentData.order_code}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            debugLog('Status check result:', result);
            
            if (result.success) {
                if (result.status === 'paid') {
                    clearInterval(statusInterval);
                    
                    statusText.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-2"></i>Thanh toán thành công!</span>';
                    
                    // Show success message
                    setTimeout(() => {
                        alert('Thanh toán thành công! Chuyển đến khóa học...');
                        window.location.href = `${window.location.origin}/elearning/course-detail.php?id=${courseId}`;
                    }, 2000);
                    
                } else if (result.status === 'failed') {
                    clearInterval(statusInterval);
                    statusText.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-2"></i>Thanh toán thất bại</span>';
                } else {
                    statusText.innerHTML = '<span class="text-warning"><i class="fas fa-clock me-2"></i>Đang chờ thanh toán...</span>';
                }
            } else {
                debugLog('Status check failed:', result.message);
            }
        } catch (error) {
            debugLog('Status check error:', error);
        }
    }, 5000);
    
    // Stop monitoring after 10 minutes
    setTimeout(() => {
        if (statusInterval) {
            clearInterval(statusInterval);
            statusText.innerHTML = '<span class="text-muted"><i class="fas fa-clock me-2"></i>Hết thời gian chờ</span>';
            debugLog('Payment monitoring timeout');
        }
    }, 600000);
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    debugLog('Page loaded, initializing...');
    detectDevice();
    
    // Auto show QR for desktop
    const device = detectDevice();
    if (!device.isMobile) {
        debugLog('Desktop detected, auto showing QR after 2 seconds');
        setTimeout(() => {
            payWithQR();
        }, 2000);
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (statusInterval) {
        clearInterval(statusInterval);
        debugLog('Cleaned up status interval');
    }
});

// Global error handler
window.addEventListener('error', function(e) {
    debugLog('Global error:', e.error);
});

function cancelLoading() {
    const loadingModal = bootstrap.Modal.getInstance(document.getElementById('loadingModal'));
    if (loadingModal) {
        loadingModal.hide();
    }
}
</script>

<?php include '../includes/footer.php'; ?>