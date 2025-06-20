<?php
// filepath: d:\Xampp\htdocs\elearning\api\create_zalopay_payment.php

// Thêm error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test file tồn tại
file_put_contents('debug.log', "API file accessed at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

require_once '../includes/config.php';
require_once '../payment/zalopay_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Test basic response
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'status' => 'API is working',
        'time' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD']
    ]);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$course_id = (int)($input['course_id'] ?? 0);
$user_id = $_SESSION['user_id'];

try {
    // Kiểm tra khóa học
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        echo json_encode(['success' => false, 'message' => 'Khóa học không tồn tại']);
        exit;
    }
    
    if ($course['price'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Khóa học miễn phí không cần thanh toán']);
        exit;
    }
    
    // Kiểm tra đã đăng ký chưa
    $stmt = $pdo->prepare("SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$user_id, $course_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã đăng ký khóa học này']);
        exit;
    }
    
    // Kiểm tra order pending
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? AND course_id = ? AND status = 'pending' 
        AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$user_id, $course_id]);
    $existing_order = $stmt->fetch();
    
    if ($existing_order) {
        // Thay vì trả về lỗi, hãy sử dụng lại order cũ
        if ($existing_order['zalopay_app_trans_id']) {
            // Order đã có thông tin ZaloPay, trả về luôn
            echo json_encode([
                'success' => true,
                'order_url' => $existing_order['zalopay_order_url'],
                'zp_trans_token' => $existing_order['zalopay_order_token'],
                'order_code' => $existing_order['order_code'],
                'zalopay_app_trans_id' => $existing_order['zalopay_app_trans_id'],
                'amount' => (int)$existing_order['amount'],
                'course_title' => $course['title'],
                'qr_code' => $existing_order['zalopay_qr_code'],
                'is_existing' => true
            ]);
            exit;
        } else {
            // Order chưa có thông tin ZaloPay, tạo lại
            $order_code = $existing_order['order_code'];
            $amount = (int)$existing_order['amount'];
            $description = $existing_order['description'];
            
            // Skip tạo order mới, chỉ gọi ZaloPay
            $result = ZaloPayService::createOrder($order_code, $amount, $description, $user_id, $course_id);
            
            if ($result && $result['return_code'] == 1) {
                echo json_encode([
                    'success' => true,
                    'order_url' => $result['order_url'],
                    'zp_trans_token' => $result['zp_trans_token'] ?? null,
                    'order_code' => $order_code,
                    'zalopay_app_trans_id' => date("ymd") . "_" . $order_code,
                    'amount' => $amount,
                    'course_title' => $course['title'],
                    'qr_code' => $result['qr_code'] ?? null,
                    'is_existing' => true
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Không thể tạo đơn hàng ZaloPay: ' . ($result['sub_return_message'] ?? 'Lỗi không xác định'),
                    'zalopay_error' => $result
                ]);
            }
            exit;
        }
    }
    
    $pdo->beginTransaction();
    
    // Tạo order mới
    $order_code = 'ZLP_' . time() . '_' . $user_id . '_' . $course_id;
    $amount = (int)$course['price'];
    $description = "Thanh toan khoa hoc: " . $course['title'];
    
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            user_id, course_id, order_code, amount, description, 
            payment_method, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, 'zalopay', ?, ?)
    ");
    $stmt->execute([
        $user_id, 
        $course_id, 
        $order_code, 
        $amount, 
        $description,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    // Tạo đơn hàng ZaloPay
    $result = ZaloPayService::createOrder($order_code, $amount, $description, $user_id, $course_id);
    
    if ($result && $result['return_code'] == 1) {
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'order_url' => $result['order_url'],
            'zp_trans_token' => $result['zp_trans_token'] ?? null,
            'order_code' => $order_code,
            'zalopay_app_trans_id' => date("ymd") . "_" . $order_code,
            'amount' => $amount,
            'course_title' => $course['title'],
            'qr_code' => $result['qr_code'] ?? null
        ]);
        
    } else {
        $pdo->rollBack();
        
        echo json_encode([
            'success' => false, 
            'message' => 'Không thể tạo đơn hàng ZaloPay: ' . ($result['sub_return_message'] ?? 'Lỗi không xác định'),
            'zalopay_error' => $result
        ]);
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
?>