<?php
/**
 * ================================================
 * public/order-tracking.php
 * Xem chi tiết Order và tracking
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
$apiClient->setServiceUrl('order', 'http://localhost:8004');
$apiClient->setServiceUrl('rental', 'http://localhost:8003');

$order = null;
$rental = null;

try {
    // Get order by rental_id
    $response = $apiClient->get('order', "/orders/rental/{$rentalId}");
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['raw_response'], true);
        if ($data && $data['success']) {
            // Get full order with tracking
            $orderId = $data['data']['order_id'];
            
            $trackingResponse = $apiClient->get('order', "/orders/{$orderId}");
            if ($trackingResponse['status_code'] === 200) {
                $trackingData = json_decode($trackingResponse['raw_response'], true);
                if ($trackingData && $trackingData['success']) {
                    $order = $trackingData['data'];
                }
            }
        }
    }
    
    // Get rental details
    $rentalResponse = $apiClient->get('rental', "/rentals/{$rentalId}", [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($rentalResponse['status_code'] === 200) {
        $rentalData = json_decode($rentalResponse['raw_response'], true);
        if ($rentalData && $rentalData['success']) {
            $rental = $rentalData['data']['rental'] ?? $rentalData['data'];
        }
    }
    
} catch (Exception $e) {
    error_log('Order tracking error: ' . $e->getMessage());
}

function formatDateTime($dateString) {
    return date('d/m/Y H:i', strtotime($dateString));
}

function getStatusIcon($status) {
    $icons = [
        'Created' => '📝',
        'Confirmed' => '✅',
        'VehicleAssigned' => '🚗',
        'Delivered' => '🎉',
        'Completed' => '✔️',
        'Cancelled' => '❌'
    ];
    return $icons[$status] ?? '•';
}

function getStatusText($status) {
    $texts = [
        'Created' => 'Đơn được tạo',
        'Confirmed' => 'Đã xác nhận',
        'VehicleAssigned' => 'Xe đã được chỉ định',
        'Delivered' => 'Đã giao xe',
        'Completed' => 'Hoàn thành',
        'Cancelled' => 'Đã hủy'
    ];
    return $texts[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - #<?= $rentalId ?></title>
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
        
        .order-card {
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
        }
        
        .order-info {
            background: #f7fafc;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
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
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-confirmed {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-delivered {
            background: #d1fae5;
            color: #065f46;
        }
        
        .tracking-section {
            margin-top: 40px;
        }
        
        .tracking-section h2 {
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .timeline {
            position: relative;
            padding-left: 40px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, #667eea 0%, #e2e8f0 100%);
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 30px;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-icon {
            position: absolute;
            left: -40px;
            width: 32px;
            height: 32px;
            background: white;
            border: 3px solid #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            z-index: 1;
        }
        
        .timeline-content {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .timeline-content:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .timeline-status {
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
            font-size: 18px;
        }
        
        .timeline-time {
            color: #718096;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .timeline-note {
            color: #4a5568;
            line-height: 1.6;
        }
        
        .no-order {
            text-align: center;
            padding: 60px 20px;
        }
        
        .no-order i {
            font-size: 64px;
            color: #cbd5e0;
            margin-bottom: 20px;
        }
        
        .no-order h2 {
            color: #4a5568;
            margin-bottom: 10px;
        }
        
        .no-order p {
            color: #718096;
            margin-bottom: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="my-rentals.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Quay lại đơn thuê
        </a>
        
        <div class="order-card">
            <div class="header">
                <h1><i class="fas fa-truck"></i> Order Tracking</h1>
                <p>Theo dõi trạng thái giao xe của bạn</p>
            </div>
            
            <?php if ($order): ?>
                <!-- Order Info -->
                <div class="order-info">
                    <div class="info-row">
                        <span class="info-label">Order ID</span>
                        <span class="info-value">#<?= $order['order_id'] ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Rental ID</span>
                        <span class="info-value">#<?= $order['rental_id'] ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ngày tạo</span>
                        <span class="info-value"><?= formatDateTime($order['order_date']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Trạng thái</span>
                        <span class="info-value">
                            <?php
                            $statusClass = 'status-pending';
                            if ($order['delivery_status'] === 'Delivered') {
                                $statusClass = 'status-delivered';
                            } elseif ($order['delivery_status'] === 'Confirmed') {
                                $statusClass = 'status-confirmed';
                            }
                            ?>
                            <span class="status-badge <?= $statusClass ?>">
                                <?= $order['delivery_status'] ?>
                            </span>
                        </span>
                    </div>
                    
                    <?php if ($rental): ?>
                    <div class="info-row">
                        <span class="info-label">Địa điểm nhận xe</span>
                        <span class="info-value"><?= htmlspecialchars($rental['pickup_location']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Thời gian nhận xe</span>
                        <span class="info-value"><?= formatDateTime($rental['start_time']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tracking Timeline -->
                <?php if (!empty($order['tracking'])): ?>
                <div class="tracking-section">
                    <h2>
                        <i class="fas fa-history"></i>
                        Lịch sử theo dõi
                    </h2>
                    
                    <div class="timeline">
                        <?php foreach ($order['tracking'] as $track): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <?= getStatusIcon($track['status_update']) ?>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-status">
                                    <?= getStatusText($track['status_update']) ?>
                                </div>
                                <div class="timeline-time">
                                    <i class="fas fa-clock"></i>
                                    <?= formatDateTime($track['updated_at']) ?>
                                </div>
                                <?php if ($track['note']): ?>
                                <div class="timeline-note">
                                    <?= htmlspecialchars($track['note']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- No Order -->
                <div class="no-order">
                    <i class="fas fa-box-open"></i>
                    <h2>Chưa có Order</h2>
                    <p>Order sẽ được tạo tự động sau khi thanh toán thành công</p>
                    <a href="my-rentals.php" class="btn">Quay lại đơn thuê</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>