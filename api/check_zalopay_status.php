<?php
// filepath: d:\Xampp\htdocs\elearning\api\check_zalopay_status.php

require_once '../includes/config.php';
require_once '../payment/zalopay_config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$order_code = $_GET['order_code'] ?? '';
$app_trans_id = $_GET['app_trans_id'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$order_code && !$app_trans_id) {
    echo json_encode(['success' => false, 'message' => 'Missing order_code or app_trans_id']);
    exit;
}

try {
    // Tìm order
    if ($order_code) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? AND user_id = ?");
        $stmt->execute([$order_code, $user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE zalopay_app_trans_id = ? AND user_id = ?");
        $stmt->execute([$app_trans_id, $user_id]);
    }
    
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Nếu đã paid thì return luôn
    if ($order['status'] == 'paid') {
        echo json_encode([
            'success' => true,
            'status' => 'paid',
            'order' => [
                'order_code' => $order['order_code'],
                'amount' => $order['amount'],
                'paid_at' => $order['paid_at']
            ]
        ]);
        exit;
    }
    
    // Nếu pending và có zalopay_app_trans_id thì query ZaloPay
    if ($order['status'] == 'pending' && $order['zalopay_app_trans_id']) {
        $result = ZaloPayService::queryOrder($order['zalopay_app_trans_id']);
        
        if ($result && $result['return_code'] == 1) {
            // Payment thành công
            $pdo->beginTransaction();
            
            try {
                // Update order
                $stmt = $pdo->prepare("
                    UPDATE orders SET 
                        status = 'paid', 
                        paid_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$order['id']]);
                
                // Create enrollment
                $stmt = $pdo->prepare("
                    INSERT INTO enrollments (user_id, course_id, order_id, enrollment_type, enrolled_at) 
                    VALUES (?, ?, ?, 'paid', NOW())
                    ON DUPLICATE KEY UPDATE 
                        order_id = VALUES(order_id),
                        enrollment_type = 'paid',
                        enrolled_at = NOW()
                ");
                $stmt->execute([$order['user_id'], $order['course_id'], $order['id']]);
                
                // Log
                $stmt = $pdo->prepare("
                    INSERT INTO payment_logs (order_id, event_type, zalopay_response, return_code, return_message)
                    VALUES (?, 'paid', ?, 1, 'Payment successful via query')
                ");
                $stmt->execute([$order['id'], json_encode($result)]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Payment successful',
                    'order' => [
                        'order_code' => $order['order_code'],
                        'amount' => $order['amount'],
                        'paid_at' => date('Y-m-d H:i:s')
                    ]
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            
        } else if ($result && $result['return_code'] == 2) {
            // Payment failed
            $stmt = $pdo->prepare("UPDATE orders SET status = 'failed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$order['id']]);
            
            echo json_encode([
                'success' => true,
                'status' => 'failed',
                'message' => 'Payment failed'
            ]);
            
        } else {
            // Still pending hoặc lỗi query
            echo json_encode([
                'success' => true,
                'status' => 'pending',
                'message' => 'Payment still pending',
                'zalopay_query_result' => $result
            ]);
        }
        
    } else if (!$order['zalopay_app_trans_id']) {
        // Order chưa có app_trans_id (chưa gọi ZaloPay API thành công)
        echo json_encode([
            'success' => false,
            'message' => 'Order not processed by ZaloPay yet',
            'status' => $order['status']
        ]);
        
    } else {
        // Return current status
        echo json_encode([
            'success' => true,
            'status' => $order['status'],
            'order' => [
                'order_code' => $order['order_code'],
                'amount' => $order['amount'],
                'created_at' => $order['created_at']
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>