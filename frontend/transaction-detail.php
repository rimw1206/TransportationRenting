<?php
/**
 * ================================================
 * public/transaction-detail.php
 * ✅ Show all vehicles in a transaction
 * ================================================
 */

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'] ?? '';

$transactionId = $_GET['id'] ?? null;

if (!$transactionId) {
    header('Location: my-rentals.php');
    exit;
}

require_once __DIR__ . '/../shared/classes/ApiClient.php';
$apiClient = new ApiClient();
$apiClient->setServiceUrl('rental', 'http://localhost:8003');
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');
$apiClient->setServiceUrl('payment', 'http://localhost:8005');

// Fetch transaction details
$transaction = null;
$rentals = [];

try {
    // Get transaction
    $response = $apiClient->get('payment', "/payments/transactions/{$transactionId}", [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['raw_response'], true);
        if ($data && $data['success']) {
            $transaction = $data['data'];
            
            // Parse metadata
            $metadata = !empty($transaction['metadata']) ? json_decode($transaction['metadata'], true) : [];
            $rentalIds = $metadata['rental_ids'] ?? [];
            
            // Get each rental detail
            foreach ($rentalIds as $rentalId) {
                $rentalResponse = $apiClient->get('rental', "/rentals/{$rentalId}", [
                    'Authorization: Bearer ' . $token
                ]);
                
                if ($rentalResponse['status_code'] === 200) {
                    $rData = json_decode($rentalResponse['raw_response'], true);
                    if ($rData && $rData['success']) {
                        $rental = $rData['data']['rental'] ?? $rData['data'];
                        
                        // Get vehicle
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
            
            // Add parsed metadata to transaction
            $transaction['parsed_metadata'] = $metadata;
        }
    }
    
    if (!$transaction) {
        header('Location: my-rentals.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log('Error fetching transaction detail: ' . $e->getMessage());
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

$metadata = $transaction['parsed_metadata'];
$originalAmount = $metadata['original_amount'] ?? $transaction['amount'];
$discountAmount = $metadata['discount_amount'] ?? 0;
$promoCode = $metadata['promo_code'] ?? null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng - <?= $transaction['transaction_code'] ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4F46E5;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .detail-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        
        .card-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .card-header p {
            opacity: 0.9;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .payment-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
        }
        
        .summary-row.discount {
            color: #059669;
        }
        
        .promo-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }
        
        .rental-item {
            border: 2px solid #e0e0e0;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .rental-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .rental-grid {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .rental-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .vehicle-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 12px;
        }
        
        .vehicle-name {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        
        .detail-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 14px;
            color: #666;
        }
        
        .detail-row i {
            width: 18px;
            color: #667eea;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-ongoing {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-completed {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-cancelled {
            background: #fee;
            color: #991b1b;
        }
        
        .payment-method {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="my-rentals.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Quay lại danh sách
        </a>
        
        <div class="detail-card">
            <div class="card-header">
                <h1>
                    <i class="fas fa-receipt"></i>
                    Chi tiết đơn hàng
                </h1>
                <p>Mã giao dịch: <strong><?= $transaction['transaction_code'] ?></strong></p>
                <p>Ngày tạo: <?= formatDate($transaction['transaction_date']) ?></p>
            </div>
            
            <div class="card-body">
                <!-- Payment Summary -->
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-calculator"></i>
                        Tổng quan thanh toán
                    </h2>
                    
                    <div class="payment-summary">
                        <div class="summary-row">
                            <span>Tổng tiền gốc (<?= count($rentals) ?> xe):</span>
                            <span><?= number_format($originalAmount) ?>đ</span>
                        </div>
                        
                        <?php if ($discountAmount > 0): ?>
                        <div class="summary-row discount">
                            <span>
                                <i class="fas fa-tag"></i> Giảm giá
                                <?php if ($promoCode): ?>
                                    <span class="promo-badge">
                                        <?= htmlspecialchars($promoCode) ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <span>-<?= number_format($discountAmount) ?>đ</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="summary-row">
                            <span>Tổng thanh toán:</span>
                            <span><?= number_format($transaction['amount']) ?>đ</span>
                        </div>
                    </div>
                    
                    <div class="payment-method">
                        <div>
                            <strong>Phương thức thanh toán:</strong>
                            <span style="margin-left: 10px;">
                                <?= $transaction['payment_method'] === 'COD' ? 'Tiền mặt (COD)' : 'VNPay QR' ?>
                            </span>
                        </div>
                        <div>
                            <strong>Trạng thái:</strong>
                            <span style="margin-left: 10px;">
                                <?= $transaction['status'] === 'Success' ? '✅ Đã thanh toán' : '⏳ Chờ thanh toán' ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Vehicles List -->
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-car"></i>
                        Danh sách xe (<?= count($rentals) ?>)
                    </h2>
                    
                    <?php foreach ($rentals as $index => $item): 
                        $rental = $item['rental'];
                        $vehicle = $item['vehicle'];
                        $days = calculateDays($rental['start_time'], $rental['end_time']);
                    ?>
                    <div class="rental-item">
                        <div class="rental-grid">
                            <div>
                                <img src="https://images.unsplash.com/photo-1590362891991-f776e747a588?w=400" 
                                     alt="Vehicle"
                                     class="vehicle-image">
                            </div>
                            
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                    <div class="vehicle-name">
                                        <?php if ($vehicle): ?>
                                            <?= htmlspecialchars($vehicle['catalog']['brand'] . ' ' . $vehicle['catalog']['model']) ?>
                                            <span style="font-size: 14px; color: #666; font-weight: normal;">
                                                (<?= $vehicle['license_plate'] ?>)
                                            </span>
                                        <?php else: ?>
                                            Xe #<?= $rental['vehicle_id'] ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <span class="status-badge status-<?= strtolower($rental['status']) ?>">
                                        <?= $rental['status'] ?>
                                    </span>
                                </div>
                                
                                <div class="detail-row">
                                    <i class="fas fa-calendar"></i>
                                    <strong>Thời gian:</strong>
                                    <?= formatDate($rental['start_time']) ?> → <?= formatDate($rental['end_time']) ?>
                                    (<?= $days ?> ngày)
                                </div>
                                
                                <div class="detail-row">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <strong>Địa điểm:</strong>
                                    <?= htmlspecialchars($rental['pickup_location']) ?>
                                </div>
                                
                                <div class="detail-row">
                                    <i class="fas fa-dollar-sign"></i>
                                    <strong>Chi phí xe này:</strong>
                                    <span style="font-size: 16px; font-weight: 700; color: #667eea;">
                                        <?= number_format($rental['total_cost']) ?>đ
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Actions -->
                <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px;">
                    <?php if ($transaction['status'] === 'Pending' && $transaction['payment_method'] === 'VNPayQR'): ?>
                        <a href="payment-page.php?transaction_id=<?= $transactionId ?>" 
                           style="padding: 14px 28px; background: #10b981; color: white; border-radius: 12px; text-decoration: none; font-weight: 600;">
                            <i class="fas fa-qrcode"></i> Thanh toán ngay
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($transaction['status'] === 'Success'): ?>
                        <a href="invoice.php?transaction_id=<?= $transactionId ?>" 
                           style="padding: 14px 28px; background: #667eea; color: white; border-radius: 12px; text-decoration: none; font-weight: 600;">
                            <i class="fas fa-file-invoice"></i> Xem hóa đơn
                        </a>
                    <?php endif; ?>
                    
                    <button onclick="window.print()" 
                            style="padding: 14px 28px; background: #e2e8f0; color: #4a5568; border: none; border-radius: 12px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-print"></i> In chi tiết
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>