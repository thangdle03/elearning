<?php
// filepath: d:\Xampp\htdocs\elearning\api\cancel_pending_order.php

require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_code = $input['order_code'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$order_code) {
    echo json_encode(['success' => false, 'message' => 'Missing order_code']);
    exit;
}

try {
    // Chỉ cho phép hủy order pending của chính user đó
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status = 'cancelled', updated_at = NOW() 
        WHERE order_code = ? AND user_id = ? AND status = 'pending'
    ");
    $result = $stmt->execute([$order_code, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found or cannot be cancelled']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>