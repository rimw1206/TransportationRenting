<?php
/**
 * ================================================
 * public/test-payment.php - ENHANCED
 * Test payment verification với auto-create Order
 * ================================================
 */

session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die('❌ Admin only');
}

$transactionId = $_GET['id'] ?? die('Usage: ?id=TRANSACTION_ID');

require_once __DIR__ . '/../shared/classes/ApiClient.php';
$apiClient = new ApiClient();
$apiClient->setServiceUrl('payment', 'http://localhost:8005');

// Call verify endpoint
$response = $apiClient->post(
    'payment', 
    '/payments/verify', 
    [
        'transaction_id' => $transactionId
    ], 
    ['Authorization: Bearer ' . $_SESSION['token']]
);

if ($response['status_code'] === 200) {
    $data = json_decode($response['raw_response'], true);
    
    // Debug log
    error_log("Verify response: " . print_r($data, true));
    
    // Extract transaction data properly
    $transaction = $data['data'] ?? [];
    $transactionId = $transaction['transaction_id'] ?? 'N/A';
    $rentalId = $transaction['rental_id'] ?? 'N/A';
    $amount = $transaction['amount'] ?? 0;
    $paymentMethod = $transaction['payment_method'] ?? 'N/A';
    $orderCreated = $transaction['order_created'] ?? false;
    $orderId = $transaction['order_id'] ?? null;
    $orderExists = $transaction['order_exists'] ?? false;
    
    echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Payment Verification Result</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .result-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease-out 0.3s both;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        .success-icon svg {
            width: 40px;
            height: 40px;
            stroke: white;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }
        
        h1 {
            text-align: center;
            color: #2d3748;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .subtitle {
            text-align: center;
            color: #718096;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .info-grid {
            background: #f7fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #718096;
            font-weight: 600;
        }
        
        .info-value {
            color: #2d3748;
            font-weight: 700;
            text-align: right;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .order-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
    </style>
</head>
<body>
    <div class='result-card'>
        <div class='success-icon'>
            <svg viewBox='0 0 24 24'>
                <polyline points='20 6 9 17 4 12'></polyline>
            </svg>
        </div>
        
        <h1>✅ Thanh toán thành công!</h1>
        <p class='subtitle'>Giao dịch đã được xác nhận</p>
        
        <div class='info-grid'>
            <div class='info-row'>
                <span class='info-label'>Mã giao dịch:</span>
                <span class='info-value'>{$data['data']['transaction_id']}</span>
            </div>
            <div class='info-row'>
                <span class='info-label'>Mã đơn thuê:</span>
                <span class='info-value'>#{$data['data']['rental_id']}</span>
            </div>
            <div class='info-row'>
                <span class='info-label'>Số tiền:</span>
                <span class='info-value'>" . number_format($data['data']['amount']) . "đ</span>
            </div>
            <div class='info-row'>
                <span class='info-label'>Phương thức:</span>
                <span class='info-value'>{$data['data']['payment_method']}</span>
            </div>
            <div class='info-row'>
                <span class='info-label'>Trạng thái:</span>
                <span class='info-value'>
                    <span class='status-badge status-success'>Thành công</span>
                </span>
            </div>";
    
    // Show order creation status
    if (isset($data['data']['order_created']) && $data['data']['order_created']) {
        echo "<div class='info-row'>
                <span class='info-label'>Order:</span>
                <span class='info-value'>
                    <div class='order-badge'>
                        🚚 Order #{$data['data']['order_id']} đã được tạo
                    </div>
                </span>
            </div>";
    } elseif (isset($data['data']['order_exists']) && $data['data']['order_exists']) {
        echo "<div class='info-row'>
                <span class='info-label'>Order:</span>
                <span class='info-value'>
                    <div class='order-badge'>
                        ℹ️ Order đã tồn tại
                    </div>
                </span>
            </div>";
    }
    
    echo "
        </div>
        
        <div class='actions'>
            <a href='my-rentals.php' class='btn btn-primary'>Xem đơn thuê</a>
            <a href='dashboard.php' class='btn btn-secondary'>Dashboard</a>
        </div>
    </div>
    
    <script>
        // Auto redirect after 3 seconds
        setTimeout(() => {
            window.location.href = 'my-rentals.php';
        }, 3000);
    </script>
</body>
</html>";
    
} else {
    echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Payment Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        h1 {
            color: #dc2626;
            margin-bottom: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #dc2626;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='error-card'>
        <h1>❌ Lỗi xác thực thanh toán</h1>
        <p>HTTP {$response['status_code']}</p>
        <pre>" . htmlspecialchars($response['raw_response']) . "</pre>
        <a href='my-rentals.php' class='btn'>Quay lại</a>
    </div>
</body>
</html>";
}
?>