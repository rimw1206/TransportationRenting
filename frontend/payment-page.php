<?php
/**
 * ================================================
 * public/payment-page.php - NO TIMEOUT VERSION
 * ✅ Không có countdown - QR code luôn valid
 * ✅ Không tự động hủy khi out ra
 * ✅ User có thể quay lại bất cứ lúc nào
 * ================================================
 */

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'] ?? '';

// Support both transaction_id and rental_id
$transactionId = $_GET['transaction_id'] ?? null;
$rentalId = $_GET['rental_id'] ?? null;

if (!$transactionId && !$rentalId) {
    $_SESSION['error'] = 'Thiếu thông tin giao dịch';
    header('Location: my-rentals.php');
    exit;
}

require_once __DIR__ . '/../shared/classes/ApiClient.php';
$apiClient = new ApiClient();
$apiClient->setServiceUrl('rental', 'http://localhost:8003');
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');
$apiClient->setServiceUrl('payment', 'http://localhost:8005');

$transaction = null;
$rentals = [];
$metadata = [];

try {
    // Case 1: Get by transaction_id directly
    if ($transactionId) {
        $tResponse = $apiClient->get('payment', "/payments/transactions/{$transactionId}", [
            'Authorization: Bearer ' . $token
        ]);
        
        if ($tResponse['status_code'] === 200) {
            $tData = json_decode($tResponse['raw_response'], true);
            if ($tData && $tData['success']) {
                $transaction = $tData['data'];
            }
        }
    }
    // Case 2: Get by rental_id
    elseif ($rentalId) {
        $tResponse = $apiClient->get('payment', "/payments/transactions?rental_id={$rentalId}", [
            'Authorization: Bearer ' . $token
        ]);
        
        if ($tResponse['status_code'] === 200) {
            $tData = json_decode($tResponse['raw_response'], true);
            if ($tData && $tData['success'] && !empty($tData['data']['items'])) {
                $transaction = $tData['data']['items'][0];
                $transactionId = $transaction['transaction_id'];
            }
        }
    }
    
    if (!$transaction) {
        $_SESSION['error'] = 'Không tìm thấy giao dịch';
        header('Location: my-rentals.php');
        exit;
    }
    
    // Check payment status
    if ($transaction['status'] === 'Success') {
        $_SESSION['success'] = 'Giao dịch này đã được thanh toán!';
        header('Location: my-rentals.php');
        exit;
    }
    
    // Check payment method
    if ($transaction['payment_method'] !== 'VNPayQR') {
        $_SESSION['error'] = 'Giao dịch này không phải thanh toán qua VNPay QR';
        header('Location: my-rentals.php');
        exit;
    }
    
    // Parse metadata
    $metadata = !empty($transaction['metadata']) ? json_decode($transaction['metadata'], true) : [];
    $rentalIds = $metadata['rental_ids'] ?? [];
    
    // Get all rentals in this transaction
    foreach ($rentalIds as $rId) {
        $rentalResponse = $apiClient->get('rental', "/rentals/{$rId}", [
            'Authorization: Bearer ' . $token
        ]);
        
        if ($rentalResponse['status_code'] === 200) {
            $rData = json_decode($rentalResponse['raw_response'], true);
            if ($rData && $rData['success']) {
                $rental = $rData['data']['rental'] ?? $rData['data'];
                
                // Get vehicle info
                $vehicleResponse = $apiClient->get('vehicle', '/units/' . $rental['vehicle_id']);
                $vehicle = null;
                
                if ($vehicleResponse['status_code'] === 200) {
                    $vData = json_decode($vehicleResponse['raw_response'], true);
                    if ($vData && $vData['success']) {
                        $vehicle = $vData['data'];
                    }
                }
                
                $rentals[] = [
                    'rental' => $rental,
                    'vehicle' => $vehicle
                ];
            }
        }
    }
    
} catch (Exception $e) {
    error_log('Payment page error: ' . $e->getMessage());
    $_SESSION['error'] = 'Lỗi tải thông tin thanh toán';
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

$originalAmount = $metadata['original_amount'] ?? $transaction['amount'];
$discountAmount = $metadata['discount_amount'] ?? 0;
$promoCode = $metadata['promo_code'] ?? null;
$isCartCheckout = $metadata['cart_checkout'] ?? false;
$rentalCount = count($rentals);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán VNPay - <?= $transaction['transaction_code'] ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 900px; margin: 0 auto; }
        
        .payment-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 28px; color: #2d3748; margin-bottom: 10px; }
        .header p { color: #718096; font-size: 16px; }
        
        .vnpay-logo {
            display: inline-block;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .transaction-info {
            background: #fff5f5;
            border: 2px dashed #fc8181;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .transaction-info .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }
        
        .transaction-info .label { color: #742a2a; font-weight: 600; }
        .transaction-info .value { color: #c53030; font-weight: 700; font-family: monospace; }
        
        .cart-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .rentals-summary {
            background: #f7fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .rentals-summary h3 {
            font-size: 16px;
            color: #2d3748;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .rental-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .rental-item:last-child { margin-bottom: 0; }
        
        .rental-item .vehicle-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .rental-item .vehicle-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .rental-item .vehicle-name { font-weight: 600; color: #2d3748; font-size: 14px; }
        .rental-item .vehicle-plate { font-size: 12px; color: #718096; }
        .rental-item .rental-cost { font-weight: 700; color: #667eea; }
        
        .price-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .price-row:last-child { border-bottom: none; }
        .price-row .label { color: #666; }
        .price-row .value { font-weight: 600; color: #2d3748; }
        .price-row.discount .value { color: #059669; }
        .price-row.total { font-size: 20px; }
        .price-row.total .value { color: #667eea; font-weight: 700; }
        
        .promo-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-left: 8px;
        }
        
        .total-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .total-section .label { font-size: 14px; opacity: 0.9; margin-bottom: 8px; }
        .total-section .amount { font-size: 42px; font-weight: 700; }
        
        .qr-section { text-align: center; padding: 20px 0; }
        
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
        
        /* ✅ REMOVED: Countdown section */
        
        .qr-container {
            background: white;
            border: 3px solid #667eea;
            border-radius: 20px;
            padding: 25px;
            margin: 0 auto 25px;
            max-width: 350px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .qr-code-img { max-width: 100%; height: auto; border-radius: 12px; }
        
        .qr-instructions {
            background: #f7fafc;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            text-align: left;
            margin-bottom: 20px;
        }
        
        .qr-instructions h3 { color: #2d3748; margin-bottom: 12px; font-size: 16px; }
        .qr-instructions ol { color: #4a5568; line-height: 1.8; padding-left: 20px; font-size: 14px; }
        .qr-instructions li { margin-bottom: 8px; }
        
        /* ✅ NEW: Info banner thay cho countdown */
        .info-banner {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .info-banner i { font-size: 20px; }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
            flex-wrap: wrap;
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
        
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn-secondary:hover { background: #cbd5e0; }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .back-link:hover { text-decoration: underline; }
        
        @media (max-width: 600px) {
            .payment-card { padding: 25px; }
            .total-section .amount { font-size: 32px; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="my-rentals.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Quay lại đơn thuê
        </a>
        
        <div class="payment-card">
            <div class="header">
                <div class="vnpay-logo">
                    <img src="https://vnpay.vn/s1/statics.vnpay.vn/2023/6/0oxhzjmxbksr1686814746087.png" 
                         alt="VNPay" style="height: 40px;">
                </div>
                <h1><i class="fas fa-qrcode"></i> Thanh toán VNPay</h1>
                <p>Quét mã QR để hoàn tất thanh toán</p>
            </div>
            
            <!-- Transaction Info -->
            <div class="transaction-info">
                <div class="info-row">
                    <span class="label">Mã giao dịch:</span>
                    <span class="value"><?= htmlspecialchars($transaction['transaction_code']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Số xe:</span>
                    <span class="value">
                        <?= $rentalCount ?> xe
                        <?php if ($isCartCheckout): ?>
                            <span class="cart-badge"><i class="fas fa-shopping-cart"></i> Thanh toán gộp</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <!-- Rentals Summary -->
            <?php if ($rentalCount > 0): ?>
            <div class="rentals-summary">
                <h3><i class="fas fa-car"></i> Chi tiết xe thuê</h3>
                <?php foreach ($rentals as $item): 
                    $rental = $item['rental'];
                    $vehicle = $item['vehicle'];
                    $days = calculateDays($rental['start_time'], $rental['end_time']);
                ?>
                <div class="rental-item">
                    <div class="vehicle-info">
                        <div class="vehicle-icon"><i class="fas fa-car"></i></div>
                        <div>
                            <div class="vehicle-name">
                                <?php if ($vehicle): ?>
                                    <?= htmlspecialchars($vehicle['catalog']['brand'] . ' ' . $vehicle['catalog']['model']) ?>
                                <?php else: ?>
                                    Xe #<?= $rental['vehicle_id'] ?>
                                <?php endif; ?>
                            </div>
                            <div class="vehicle-plate">
                                <?= $vehicle ? $vehicle['license_plate'] : 'N/A' ?> • <?= $days ?> ngày
                            </div>
                        </div>
                    </div>
                    <div class="rental-cost"><?= number_format($rental['total_cost']) ?>đ</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Price Summary -->
            <div class="price-summary">
                <div class="price-row">
                    <span class="label">Tổng tiền gốc:</span>
                    <span class="value"><?= number_format($originalAmount) ?>đ</span>
                </div>
                <?php if ($discountAmount > 0): ?>
                <div class="price-row discount">
                    <span class="label">
                        Giảm giá
                        <?php if ($promoCode): ?>
                            <span class="promo-badge"><i class="fas fa-tag"></i> <?= htmlspecialchars($promoCode) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="value">-<?= number_format($discountAmount) ?>đ</span>
                </div>
                <?php endif; ?>
                <div class="price-row total">
                    <span class="label">Tổng thanh toán:</span>
                    <span class="value"><?= number_format($transaction['amount']) ?>đ</span>
                </div>
            </div>
            
            <!-- Total Amount Highlight -->
            <div class="total-section">
                <div class="label">Số tiền cần thanh toán</div>
                <div class="amount"><?= number_format($transaction['amount']) ?>đ</div>
            </div>
            
            <!-- QR Code Section -->
            <div class="qr-section">
                <div class="payment-status">
                    <div class="status-indicator"></div>
                    <span>Đang chờ thanh toán</span>
                </div>
                
                <!-- ✅ REPLACED: Info banner thay countdown -->
                <div class="info-banner">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Bạn có thể quay lại thanh toán bất cứ lúc nào!</strong><br>
                        <small>Đơn hàng sẽ được giữ cho đến khi bạn hoàn tất thanh toán.</small>
                    </div>
                </div>
                
                <div class="qr-container">
                    <?php
                    $vnpayUrl = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?vnp_TxnRef=" . urlencode($transaction['transaction_code']);
                    $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=" . urlencode($vnpayUrl);
                    ?>
                    <img src="<?= $qrApiUrl ?>" class="qr-code-img" alt="VNPay QR Code">
                </div>
                
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
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="checkPaymentStatus()">
                        <i class="fas fa-sync"></i> Kiểm tra thanh toán
                    </button>
                    <a href="my-rentals.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại sau
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const TRANSACTION_ID = <?= $transactionId ?>;
        const AUTH_TOKEN = '<?= $token ?>';
        
        let autoCheckInterval = null;
        
        // ✅ REMOVED: countdown logic
        
        async function checkPaymentStatus() {
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xác thực...';
            
            try {
                const verifyResponse = await fetch(
                    'http://localhost:8005/payments/verify',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${AUTH_TOKEN}`
                        },
                        body: JSON.stringify({ transaction_id: TRANSACTION_ID })
                    }
                );
                
                const result = await verifyResponse.json();
                
                if (result.success) {
                    if (autoCheckInterval) clearInterval(autoCheckInterval);
                    
                    let message = '✅ Thanh toán thành công!\n\n';
                    message += 'Đơn thuê xe của bạn đã được xác nhận.';
                    
                    if (result.data && result.data.order_created) {
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
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }
        
        // ✅ Auto-check every 5 seconds (only while on page)
        function startAutoCheck() {
            autoCheckInterval = setInterval(async () => {
                try {
                    console.log('Auto-checking payment status...');
                    
                    const response = await fetch(
                        `http://localhost:8005/payments/transactions/${TRANSACTION_ID}`,
                        { headers: { 'Authorization': `Bearer ${AUTH_TOKEN}` } }
                    );
                    
                    const result = await response.json();
                    
                    if (result.success && result.data && result.data.status === 'Success') {
                        clearInterval(autoCheckInterval);
                        
                        console.log('Payment successful! Redirecting...');
                        alert('✅ Thanh toán thành công!\n\nĐơn thuê xe của bạn đã được xác nhận.');
                        window.location.href = 'my-rentals.php';
                    }
                } catch (error) {
                    console.error('Auto-check error:', error);
                }
            }, 5000); // Check every 5 seconds
        }
        
        startAutoCheck();
        
        // ✅ Clean up interval when leaving page
        window.addEventListener('beforeunload', () => {
            if (autoCheckInterval) clearInterval(autoCheckInterval);
        });
    </script>
</body>
</html>