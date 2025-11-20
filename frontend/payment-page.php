<?php
/**
 * ================================================
 * public/payment-page.php - FIXED AUTO-RELOAD
 * Tự động reload khi thanh toán thành công
 * ================================================
 */

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'] ?? '';
$rentalId = $_GET['rental_id'] ?? null;

if (!$rentalId) {
    header('Location: my-rentals.php');
    exit;
}

require_once __DIR__ . '/../shared/classes/ApiClient.php';
$apiClient = new ApiClient();
$apiClient->setServiceUrl('rental', 'http://localhost:8003');
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');
$apiClient->setServiceUrl('payment', 'http://localhost:8005');

// Fetch rental details
$rental = null;
$vehicle = null;
$transaction = null;

try {
    // Get rental
    $response = $apiClient->get('rental', "/rentals/{$rentalId}", [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['raw_response'], true);
        if ($data && $data['success']) {
            $rental = $data['data']['rental'] ?? $data['data'];
            
            // Verify ownership
            if ($rental['user_id'] != $user['user_id']) {
                header('Location: my-rentals.php');
                exit;
            }
            
            // Check status
            if (!in_array($rental['status'], ['Ongoing'])) {
                $_SESSION['error'] = 'Rental must be Ongoing to make payment (Current: ' . $rental['status'] . ')';
                header('Location: my-rentals.php');
                exit;
            }
            
            // Get vehicle
            $vResponse = $apiClient->get('vehicle', '/units/' . $rental['vehicle_id']);
            if ($vResponse['status_code'] === 200) {
                $vData = json_decode($vResponse['raw_response'], true);
                if ($vData && $vData['success']) {
                    $vehicle = $vData['data'];
                }
            }
        }
    }
    
    if (!$rental) {
        header('Location: my-rentals.php');
        exit;
    }
    
    // Get EXISTING transaction
    $tResponse = $apiClient->get('payment', "/payments/transactions?rental_id={$rentalId}", [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($tResponse['status_code'] === 200) {
        $tData = json_decode($tResponse['raw_response'], true);
        if ($tData && $tData['success'] && !empty($tData['data']['items'])) {
            $transaction = $tData['data']['items'][0];
            
            // Check if already paid
            if ($transaction['status'] === 'Success') {
                $_SESSION['success'] = 'This rental has already been paid';
                header('Location: my-rentals.php');
                exit;
            }
            
            // Check payment method
            if ($transaction['payment_method'] !== 'VNPayQR') {
                $_SESSION['error'] = 'This transaction is not VNPayQR';
                header('Location: my-rentals.php');
                exit;
            }
        }
    }
    
    if (!$transaction) {
        $_SESSION['error'] = 'Transaction not found. Please wait for admin to verify your rental first.';
        header('Location: my-rentals.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log('Payment page error: ' . $e->getMessage());
    $_SESSION['error'] = 'Failed to load payment information';
    header('Location: my-rentals.php');
    exit;
}

function formatDate($dateString) {
    return date('d/m/Y H:i', strtotime($dateString));
}

function calculateDays($startTime, $endTime) {
    $start = new DateTime($startTime);
    $end = new DateTime($endTime);
    return max(1, $end->diff($start)->days);
}

$days = calculateDays($rental['start_time'], $rental['end_time']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán VNPay - Đơn #<?= $rentalId ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .payment-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
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
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 32px;
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #718096;
            font-size: 16px;
        }
        
        .rental-summary {
            background: #f7fafc;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-label {
            color: #718096;
            font-weight: 600;
        }
        
        .summary-value {
            color: #2d3748;
            font-weight: 700;
        }
        
        .total-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .total-section .label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .total-section .amount {
            font-size: 48px;
            font-weight: 700;
        }
        
        .qr-section {
            text-align: center;
            padding: 40px 20px;
        }
        
        .payment-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px;
            background: #e0f2fe;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #075985;
            font-weight: 600;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            background: #0ea5e9;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .transaction-info {
            background: #fff5f5;
            border: 2px dashed #fc8181;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .transaction-info .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .transaction-info .label {
            color: #742a2a;
            font-weight: 600;
        }
        
        .transaction-info .value {
            color: #c53030;
            font-weight: 700;
            font-family: monospace;
        }
        
        .countdown {
            background: #fefce8;
            border: 2px solid #fbbf24;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            color: #92400e;
            font-weight: 600;
            font-size: 16px;
        }
        
        .countdown i {
            color: #f59e0b;
        }
        
        .qr-container {
            background: white;
            border: 3px solid #667eea;
            border-radius: 20px;
            padding: 30px;
            margin: 0 auto 30px;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .qr-code-img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
        }
        
        .qr-instructions {
            background: #f7fafc;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            text-align: left;
            margin-bottom: 20px;
        }
        
        .qr-instructions h3 {
            color: #2d3748;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .qr-instructions ol {
            color: #4a5568;
            line-height: 1.8;
            padding-left: 20px;
        }
        
        .qr-instructions li {
            margin-bottom: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .vnpay-logo {
            display: inline-block;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="my-rentals.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Quay lại đơn thuê
        </a>
        
        <div class="payment-card">
            <!-- Header -->
            <div class="header">
                <div class="vnpay-logo">
                    <img src="https://vnpay.vn/s1/statics.vnpay.vn/2023/6/0oxhzjmxbksr1686814746087.png" 
                         alt="VNPay" 
                         style="height: 40px;">
                </div>
                <h1><i class="fas fa-qrcode"></i> Thanh toán VNPay</h1>
                <p>Quét mã QR để hoàn tất thanh toán</p>
            </div>
            
            <!-- Rental Summary -->
            <div class="rental-summary">
                <div class="summary-row">
                    <span class="summary-label">Mã đơn thuê</span>
                    <span class="summary-value">#<?= $rentalId ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Xe</span>
                    <span class="summary-value">
                        <?php if ($vehicle): ?>
                            <?= htmlspecialchars($vehicle['catalog']['brand'] . ' ' . $vehicle['catalog']['model']) ?>
                            <small style="color: #718096;">(<?= $vehicle['license_plate'] ?>)</small>
                        <?php else: ?>
                            Unit #<?= $rental['vehicle_id'] ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Thời gian thuê</span>
                    <span class="summary-value"><?= $days ?> ngày</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Địa điểm</span>
                    <span class="summary-value"><?= htmlspecialchars($rental['pickup_location']) ?></span>
                </div>
            </div>
            
            <!-- Total Amount -->
            <div class="total-section">
                <div class="label">Tổng thanh toán</div>
                <div class="amount"><?= number_format($rental['total_cost']) ?>đ</div>
            </div>
            
            <!-- QR Code Section -->
            <div class="qr-section">
                <!-- Payment Status -->
                <div class="payment-status">
                    <div class="status-indicator"></div>
                    <span>Đang chờ thanh toán</span>
                </div>
                
                <!-- Transaction Info -->
                <div class="transaction-info">
                    <div class="info-item">
                        <span class="label">Mã giao dịch:</span>
                        <span class="value"><?= htmlspecialchars($transaction['transaction_code']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">ID giao dịch:</span>
                        <span class="value"><?= $transaction['transaction_id'] ?></span>
                    </div>
                </div>
                
                <!-- Countdown Timer -->
                <div class="countdown">
                    <i class="fas fa-clock"></i> Mã QR có hiệu lực: <strong id="timeLeft">15:00</strong>
                </div>
                
                <!-- QR Code -->
                <div class="qr-container">
                    <?php
                    $vnpayUrl = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?vnp_TxnRef=" . urlencode($transaction['transaction_code']);
                    $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($vnpayUrl);
                    ?>
                    <img src="<?= $qrApiUrl ?>" class="qr-code-img" alt="VNPay QR Code">
                </div>
                
                <!-- Instructions -->
                <div class="qr-instructions">
                    <h3><i class="fas fa-info-circle"></i> Hướng dẫn thanh toán</h3>
                    <ol>
                        <li>Mở ứng dụng ngân hàng hoặc ví điện tử hỗ trợ VNPay</li>
                        <li>Chọn chức năng quét mã QR</li>
                        <li>Quét mã QR code phía trên</li>
                        <li>Xác nhận thông tin và hoàn tất thanh toán</li>
                        <li>Hệ thống sẽ tự động cập nhật sau khi thanh toán thành công</li>
                    </ol>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="checkPaymentStatus()">
                        <i class="fas fa-sync"></i> Kiểm tra thanh toán
                    </button>
                    <a href="my-rentals.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Hủy bỏ
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const TRANSACTION_ID = <?= $transaction['transaction_id'] ?>;
        const AUTH_TOKEN = '<?= $token ?>';
        
        let countdownInterval = null;
        let autoCheckInterval = null;
        let expiryTime = Date.now() + (15 * 60 * 1000); // 15 minutes
        
        // Start countdown timer
        function startCountdown() {
            countdownInterval = setInterval(() => {
                const remaining = expiryTime - Date.now();
                
                if (remaining <= 0) {
                    clearInterval(countdownInterval);
                    document.getElementById('timeLeft').textContent = '00:00';
                    alert('⏰ Mã QR đã hết hạn. Vui lòng làm mới trang.');
                    return;
                }
                
                const minutes = Math.floor(remaining / 60000);
                const seconds = Math.floor((remaining % 60000) / 1000);
                document.getElementById('timeLeft').textContent = 
                    `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                
            }, 1000);
        }
        
        // Check payment status and trigger verification
        async function checkPaymentStatus() {
            try {
                const btn = event.target;
                const originalHTML = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xác thực...';
                
                // Call verify endpoint to process payment
                const verifyResponse = await fetch(
                    'http://localhost:8005/payments/verify',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${AUTH_TOKEN}`
                        },
                        body: JSON.stringify({
                            transaction_id: TRANSACTION_ID
                        })
                    }
                );
                
                const result = await verifyResponse.json();
                
                if (result.success) {
                    // ✅ STOP all intervals before redirect
                    if (countdownInterval) clearInterval(countdownInterval);
                    if (autoCheckInterval) clearInterval(autoCheckInterval);
                    
                    let message = '✅ Thanh toán thành công!\n\n';
                    message += 'Đơn thuê xe của bạn đã được xác nhận.';
                    
                    if (result.data.order_created) {
                        message += `\n\n🚚 Order #${result.data.order_id} đã được tạo!`;
                    }
                    
                    alert(message);
                    window.location.href = 'my-rentals.php';
                    return;
                }
                
                alert('⏳ Chưa nhận được xác nhận thanh toán.\n\nVui lòng hoàn tất thanh toán và thử lại.');
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                
            } catch (error) {
                console.error('Check payment error:', error);
                alert('❌ Không thể kiểm tra trạng thái thanh toán');
                const btn = event.target;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync"></i> Kiểm tra thanh toán';
            }
        }
        
        // ✅ Auto-check payment status every 3 seconds
        function startAutoCheck() {
            autoCheckInterval = setInterval(async () => {
                try {
                    console.log('Auto-checking payment status...');
                    
                    const response = await fetch(
                        `http://localhost:8005/payments/transactions/${TRANSACTION_ID}`,
                        {
                            headers: {
                                'Authorization': `Bearer ${AUTH_TOKEN}`
                            }
                        }
                    );
                    
                    const result = await response.json();
                    
                    if (result.success && result.data) {
                        console.log('Transaction status:', result.data.status);
                        
                        if (result.data.status === 'Success') {
                            // ✅ STOP all intervals
                            clearInterval(countdownInterval);
                            clearInterval(autoCheckInterval);
                            
                            console.log('Payment successful! Redirecting...');
                            alert('✅ Thanh toán thành công!\n\nĐơn thuê xe của bạn đã được xác nhận.');
                            window.location.href = 'my-rentals.php';
                        }
                    }
                } catch (error) {
                    console.error('Auto-check error:', error);
                }
            }, 3000); // Check every 3 seconds
        }
        
        // Start on page load
        startCountdown();
        startAutoCheck();
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (countdownInterval) clearInterval(countdownInterval);
            if (autoCheckInterval) clearInterval(autoCheckInterval);
        });
    </script>
</body>
</html>