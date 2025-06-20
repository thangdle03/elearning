<?php
// filepath: d:\Xampp\htdocs\elearning\payment\zalopay_callback.php

require_once '../includes/config.php';
require_once 'zalopay_config.php';

// Log callback for debugging
error_log('ZaloPay Callback: ' . file_get_contents('php://input'));

$result = [];

try {
    $postdata = json_decode(file_get_contents('php://input'), true);
    
    if (ZaloPayService::verifyCallback($postdata)) {
        $dataJson = json_decode($postdata['data'], true);
        $app_trans_id = $dataJson['app_trans_id'];
        $amount = $dataJson['amount'];
        
        // Find order
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE zalopay_trans_id = ?");
        $stmt->execute([$app_trans_id]);
        $order = $stmt->fetch();
        
        if ($order && $order['status'] == 'pending') {
            $pdo->beginTransaction();
            
            // Update order
            $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', vnp_pay_date = NOW() WHERE id = ?");
            $stmt->execute([$order['id']]);
            
            // Create enrollment
            $stmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, enrolled_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE enrolled_at = NOW()");
            $stmt->execute([$order['user_id'], $order['course_id']]);
            
            $pdo->commit();
            
            $result['return_code'] = 1;
            $result['return_message'] = 'success';
        } else {
            $result['return_code'] = 0;
            $result['return_message'] = 'order not found or already processed';
        }
    } else {
        $result['return_code'] = -1;
        $result['return_message'] = 'mac not equal';
    }
} catch (Exception $e) {
    $result['return_code'] = 0;
    $result['return_message'] = $e->getMessage();
}

echo json_encode($result);
?>