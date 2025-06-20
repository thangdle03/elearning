<?php
// filepath: d:\Xampp\htdocs\elearning\payment\zalopay_config.php

// Bật error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ZaloPay Sandbox Configuration
define('ZALOPAY_APP_ID', 2553);
define('ZALOPAY_KEY1', 'PcY4iZIKFCIdgZvA6ueMcMHHUbRLYjPL');
define('ZALOPAY_KEY2', 'kLtgPl8HHhfvMuDHPwKfgfsY4Ydm9eIz');
define('ZALOPAY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/create');
define('ZALOPAY_QUERY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/query');

class ZaloPayService {
    
    /**
     * Tạo đơn hàng ZaloPay
     */
    public static function createOrder($orderCode, $amount, $description, $userId, $courseId) {
        global $pdo;
        
        error_log("=== ZaloPayService::createOrder ===");
        error_log("Order code: $orderCode");
        error_log("Amount: $amount");
        error_log("User ID: $userId");
        error_log("Course ID: $courseId");
        
        $appTransId = date("ymd") . "_" . $orderCode;
        error_log("App Trans ID: $appTransId");
        
        // Kiểm tra SITE_URL
        if (!defined('SITE_URL')) {
            error_log("SITE_URL not defined");
            return ['return_code' => 0, 'sub_return_message' => 'SITE_URL not configured'];
        }
        
        $order = [
            'app_id' => ZALOPAY_APP_ID,
            'app_trans_id' => $appTransId,
            'app_user' => "user_" . $userId,
            'app_time' => round(microtime(true) * 1000),
            'embed_data' => json_encode([
                'redirecturl' => SITE_URL . '/payment/zalopay_return.php?order_code=' . $orderCode,
                'course_id' => $courseId,
                'user_id' => $userId
            ]),
            'item' => json_encode([
                [
                    'itemid' => 'course_' . $courseId,
                    'itemname' => $description,
                    'itemprice' => $amount,
                    'itemquantity' => 1
                ]
            ]),
            'amount' => $amount,
            'description' => $description,
            'bank_code' => '',
            'callback_url' => SITE_URL . '/payment/zalopay_callback.php'
        ];

        // Tạo MAC
        $data = $order['app_id'] . "|" . $order['app_trans_id'] . "|" . $order['app_user'] 
              . "|" . $order['amount'] . "|" . $order['app_time'] . "|" . $order['embed_data'] 
              . "|" . $order['item'];
        $order['mac'] = hash_hmac('sha256', $data, ZALOPAY_KEY1);

        error_log("Order data prepared: " . json_encode($order));

        // Gọi API
        $result = self::callAPI(ZALOPAY_ENDPOINT, $order);
        error_log("ZaloPay API raw response: " . json_encode($result));
        
        if ($result && $result['return_code'] == 1) {
            // Cập nhật database
            try {
                $stmt = $pdo->prepare("
                    UPDATE orders SET 
                        zalopay_app_trans_id = ?, 
                        zalopay_order_token = ?, 
                        zalopay_order_url = ?,
                        zalopay_qr_code = ?
                    WHERE order_code = ?
                ");
                $updateResult = $stmt->execute([
                    $appTransId,
                    $result['zp_trans_token'] ?? null,
                    $result['order_url'] ?? null,
                    $result['qr_code'] ?? null,
                    $orderCode
                ]);
                error_log("Database update result: " . ($updateResult ? 'success' : 'failed'));
            } catch (Exception $e) {
                error_log("Database update error: " . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Kiểm tra trạng thái đơn hàng
     */
    public static function queryOrder($appTransId) {
        if (!$appTransId) {
            return ['return_code' => 0, 'sub_return_message' => 'Missing app_trans_id'];
        }
        
        $data = [
            'app_id' => ZALOPAY_APP_ID,
            'app_trans_id' => $appTransId
        ];
        
        // Tạo MAC cho query
        $mac_data = ZALOPAY_APP_ID . '|' . $appTransId . '|' . ZALOPAY_KEY1;
        $data['mac'] = hash_hmac('sha256', $mac_data, ZALOPAY_KEY1);
        
        error_log("Query ZaloPay with data: " . json_encode($data));
        
        $result = self::callAPI(ZALOPAY_QUERY_ENDPOINT, $data);
        
        error_log("Query ZaloPay result: " . json_encode($result));
        
        // Log query result
        self::logPayment($appTransId, 'query_response', $result);
        
        return $result;
    }

    /**
     * Xác thực callback
     */
    public static function verifyCallback($data) {
        $key2 = ZALOPAY_KEY2;
        $postdata = $data['data'];
        $postmac = $data['mac'];
        
        $mac = hash_hmac('sha256', $postdata, $key2);
        return $mac === $postmac;
    }

    /**
     * Gọi API ZaloPay
     */
    private static function callAPI($url, $data) {
        error_log("=== Calling ZaloPay API ===");
        error_log("URL: $url");
        error_log("Data: " . json_encode($data));
        
        // Sử dụng cURL thay vì file_get_contents
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("HTTP Code: $httpCode");
        error_log("cURL Error: $error");
        error_log("Response: $response");
        
        if ($error) {
            error_log("cURL Error: $error");
            return ['return_code' => 0, 'sub_return_message' => 'cURL Error: ' . $error];
        }
        
        if ($httpCode !== 200) {
            error_log("HTTP Error: $httpCode");
            return ['return_code' => 0, 'sub_return_message' => 'HTTP Error: ' . $httpCode];
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            error_log("JSON decode error: " . json_last_error_msg());
            return ['return_code' => 0, 'sub_return_message' => 'Invalid JSON response'];
        }
        
        return $result;
    }

    /**
     * Log payment activities
     */
    private static function logPayment($orderRef, $eventType, $data) {
        global $pdo;
        
        try {
            // Tìm order_id từ order_code hoặc app_trans_id
            if (strpos($orderRef, '_') !== false) {
                // Là app_trans_id, extract order_code
                $parts = explode('_', $orderRef);
                if (count($parts) >= 2) {
                    $orderCode = implode('_', array_slice($parts, 1)); // Bỏ phần date
                    $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_code = ?");
                } else {
                    $orderCode = $orderRef;
                    $stmt = $pdo->prepare("SELECT id FROM orders WHERE zalopay_app_trans_id = ?");
                }
            } else {
                $orderCode = $orderRef;
                $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_code = ?");
            }
            
            $stmt->execute([$orderCode]);
            $order = $stmt->fetch();
            
            if ($order) {
                $stmt = $pdo->prepare("
                    INSERT INTO payment_logs (order_id, event_type, zalopay_response, request_data, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order['id'],
                    $eventType,
                    json_encode($data),
                    json_encode($_REQUEST ?? []),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
            }
        } catch (Exception $e) {
            error_log('ZaloPay Log Error: ' . $e->getMessage());
        }
    }
}
?>