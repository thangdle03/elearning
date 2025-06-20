<?php
// filepath: d:\Xampp\htdocs\elearning\test_api.php

require_once 'includes/config.php';

if (!isLoggedIn()) {
    echo "Please login first";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test ZaloPay API</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .btn { padding: 10px 15px; margin: 5px; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; border: none; }
        .btn-danger { background: #dc3545; color: white; border: none; }
        .btn-success { background: #28a745; color: white; border: none; }
        #result { margin-top: 20px; padding: 15px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>Test ZaloPay API</h1>
    
    <button class="btn btn-primary" onclick="testAPI()">Test Create Payment</button>
    <button class="btn btn-danger" onclick="cancelOrder()">Cancel Pending Orders</button>
    <button class="btn btn-success" onclick="checkStatus()">Check Status</button>
    
    <div id="result"></div>
    
    <script>
    let lastOrderCode = null;
    
    function testAPI() {
        $('#result').html('Creating payment...');
        
        $.ajax({
            url: '/elearning/api/create_zalopay_payment.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                course_id: 20
            }),
            success: function(response) {
                console.log('Success:', response);
                
                if (response.success) {
                    lastOrderCode = response.order_code;
                    $('#result').html(`
                        <h3>✅ Payment Created Successfully!</h3>
                        <p><strong>Order Code:</strong> ${response.order_code}</p>
                        <p><strong>Amount:</strong> ${response.amount.toLocaleString()}₫</p>
                        <p><strong>Course:</strong> ${response.course_title}</p>
                        ${response.is_existing ? '<p><em>(Using existing order)</em></p>' : ''}
                        <br>
                        <a href="${response.order_url}" target="_blank" class="btn btn-primary">Open Payment Page</a>
                        <button class="btn btn-success" onclick="checkStatus('${response.order_code}')">Check Status</button>
                    `);
                } else {
                    if (response.existing_order) {
                        lastOrderCode = response.existing_order.order_code;
                        $('#result').html(`
                            <h3>⚠️ Existing Order Found</h3>
                            <p><strong>Order Code:</strong> ${response.existing_order.order_code}</p>
                            <p><strong>Amount:</strong> ${parseFloat(response.existing_order.amount).toLocaleString()}₫</p>
                            <p><strong>Created:</strong> ${response.existing_order.created_at}</p>
                            <br>
                            <button class="btn btn-danger" onclick="cancelOrder('${response.existing_order.order_code}')">Cancel This Order</button>
                            <button class="btn btn-success" onclick="checkStatus('${response.existing_order.order_code}')">Check Status</button>
                        `);
                    } else {
                        $('#result').html('<h3>❌ Error</h3><p>' + response.message + '</p>');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.log('Error:', error);
                $('#result').html('Error: ' + error + '<br>Response: <pre>' + xhr.responseText + '</pre>');
            }
        });
    }
    
    function cancelOrder(orderCode = null) {
        if (!orderCode && !lastOrderCode) {
            alert('No order to cancel');
            return;
        }
        
        const code = orderCode || lastOrderCode;
        $('#result').html('Cancelling order...');
        
        $.ajax({
            url: '/elearning/api/cancel_pending_order.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                order_code: code
            }),
            success: function(response) {
                if (response.success) {
                    $('#result').html('<h3>✅ Order Cancelled</h3><p>You can now create a new payment.</p>');
                    lastOrderCode = null;
                } else {
                    $('#result').html('<h3>❌ Cancel Failed</h3><p>' + response.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $('#result').html('Cancel Error: ' + error);
            }
        });
    }
    
    function checkStatus(orderCode = null) {
        const code = orderCode || lastOrderCode;
        if (!code) {
            alert('No order to check');
            return;
        }
        
        $('#result').html('Checking status...');
        
        $.ajax({
            url: '/elearning/api/check_zalopay_status.php?order_code=' + code,
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const statusColor = response.status === 'paid' ? 'green' : 
                                      response.status === 'failed' ? 'red' : 'orange';
                    $('#result').html(`
                        <h3>📊 Order Status</h3>
                        <p><strong>Status:</strong> <span style="color: ${statusColor}">${response.status.toUpperCase()}</span></p>
                        <p><strong>Order Code:</strong> ${code}</p>
                        ${response.order ? `<p><strong>Amount:</strong> ${parseFloat(response.order.amount).toLocaleString()}₫</p>` : ''}
                        ${response.order && response.order.paid_at ? `<p><strong>Paid At:</strong> ${response.order.paid_at}</p>` : ''}
                    `);
                } else {
                    $('#result').html('<h3>❌ Status Check Failed</h3><p>' + response.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $('#result').html('Status Check Error: ' + error);
            }
        });
    }
    </script>
</body>
</html>