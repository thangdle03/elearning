<?php
// filepath: d:\Xampp\htdocs\elearning\test_simulate_payment.php

require_once 'includes/config.php';

if (!isLoggedIn()) {
    echo "Please login first";
    exit;
}

$order_code = $_GET['order_code'] ?? '';

if ($order_code) {
    // Simulate payment success
    $stmt = $pdo->prepare("
        UPDATE orders SET 
            status = 'paid', 
            paid_at = NOW(),
            updated_at = NOW()
        WHERE order_code = ? AND user_id = ?
    ");
    $result = $stmt->execute([$order_code, $_SESSION['user_id']]);
    
    if ($result && $stmt->rowCount() > 0) {
        // Get order info
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ?");
        $stmt->execute([$order_code]);
        $order = $stmt->fetch();
        
        if ($order) {
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
            
            echo "✅ Payment simulated successfully for order: " . $order_code;
        }
    } else {
        echo "❌ Order not found or cannot be updated";
    }
} else {
    echo "Missing order_code parameter. Usage: ?order_code=ZLP_xxx";
}
?>