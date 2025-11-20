<?php
/**
 * ================================================
 * public/rental-detail.php
 * Chi tiết đơn thuê
 * ================================================
 */

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'] ?? '';

$rentalId = $_GET['id'] ?? null;

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
$payments = [];

try {
    // Get rental
    $response = $apiClient->get('rental', "/rentals/{$rentalId}", [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['raw_response'], true);
        if ($data && $data['success']) {
            $rentalData = $data['data'];
            $rental = $rentalData['rental'] ?? $rentalData;
            
            // Verify ownership
            if ($rental['user_id'] != $user['user_id']) {
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
            
            // Get payments
            $pResponse = $apiClient->get('payment', "/payments/transactions?rental_id={$rentalId}", [
                'Authorization: Bearer ' . $token
            ]);
            
            if ($pResponse['status_code'] === 200) {
                $pData = json_decode($pResponse['raw_response'], true);
                if ($pData && $pData['success']) {
                    $payments = $pData['data']['items'] ?? [];
                }
            }
        }
    }
    
    if (!$rental) {
        header('Location: my-rentals.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log('Error fetching rental detail: ' . $e->getMessage());
    header('Location: my-rentals.php');
    exit;
}

function getStatusBadge($status) {
    $badges = [
        'Pending' => '<span class="badge badge-warning"><i class="fas fa-clock"></i> Chờ xử lý</span>',
        'Ongoing' => '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Đang thuê</span>',
        'Completed' => '<span class="badge badge-completed"><i class="fas fa-check-double"></i> Hoàn thành</span>',
        'Cancelled' => '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Đã hủy</span>'
    ];
    return $badges[$status] ?? $status;
}

function getPaymentStatusBadge($status) {
    $badges = [
        'Pending' => '<span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> Chờ thanh toán</span>',
        'Success' => '<span class="badge badge-success"><i class="fas fa-check"></i> Đã thanh toán</span>',
        'Failed' => '<span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Thất bại</span>',
        'Refunded' => '<span class="badge badge-info"><i class="fas fa-undo"></i> Đã hoàn tiền</span>'
    ];
    return $badges[$status] ?? $status;
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
    <title>Chi tiết đơn #<?= $rentalId ?> - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard_style.css">
    <style>
        .detail-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4F46E5;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .back-btn:hover {
            text-decoration: underline;
        }
        
        .detail-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .detail-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        
        .detail-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .detail-header-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        
        .detail-body {
            padding: 40px;
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
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .info-label {
            font-size: 13px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .info-value {
            font-size: 16px;
            color: #1a1a1a;
            font-weight: 500;
        }
        
        .vehicle-showcase {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            align-items: start;
        }
        
        @media (max-width: 768px) {
            .vehicle-showcase {
                grid-template-columns: 1fr;
            }
        }
        
        .vehicle-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 15px;
        }
        
        .vehicle-specs {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .spec-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .spec-item i {
            color: #4F46E5;
            font-size: 18px;
        }
        
        .timeline {
            position: relative;
            padding-left: 40px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -34px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4F46E5;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #4F46E5;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }
        
        .timeline-date {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .timeline-text {
            font-size: 15px;
            color: #1a1a1a;
        }
        
        .payment-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .payment-amount {
            font-size: 24px;
            font-weight: 700;
            color: #4F46E5;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #4F46E5;
            color: white;
        }
        
        .btn-primary:hover {
            background: #4338CA;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: white;
            color: #DC2626;
            border: 2px solid #DC2626;
        }
        
        .btn-danger:hover {
            background: #DC2626;
            color: white;
        }
        
        .badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-completed { background: #dbeafe; color: #1e40af; }
        .badge-danger { background: #fee; color: #991b1b; }
        .badge-info { background: #e0e7ff; color: #3730a3; }
    </style>
</head>
<body>
    <!-- Navigation (same as my-rentals.php) -->
    <nav class="top-nav">
        <!-- Copy từ my-rentals.php -->
    </nav>

    <main class="main-container">
        <div class="detail-container">
            <a href="my-rentals.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Quay lại danh sách
            </a>

            <div class="detail-card">
                <!-- Header -->
                <div class="detail-header">
                    <h1><i class="fas fa-file-alt"></i> Đơn thuê #<?= $rentalId ?></h1>
                    <div class="detail-header-meta">
                        <div>
                            <i class="far fa-calendar"></i>
                            Tạo lúc: <?= formatDate($rental['created_at']) ?>
                        </div>
                        <div>
                            <?= getStatusBadge($rental['status']) ?>
                        </div>
                    </div>
                </div>

                <!-- Body -->
                <div class="detail-body">
                    <!-- Vehicle Info -->
                    <div class="section">
                        <h2 class="section-title">
                            <i class="fas fa-car"></i> Thông tin xe
                        </h2>
                        
                        <?php if ($vehicle): ?>
                        <div class="vehicle-showcase">
                            <img src="https://images.unsplash.com/photo-1590362891991-f776e747a588?w=400" 
                                 alt="<?= $vehicle['catalog']['brand'] ?>"
                                 class="vehicle-image">
                            
                            <div>
                                <h3 style="font-size: 24px; margin-bottom: 15px;">
                                    <?= htmlspecialchars($vehicle['catalog']['brand'] . ' ' . $vehicle['catalog']['model']) ?>
                                </h3>
                                
                                <div class="vehicle-specs">
                                    <div class="spec-item">
                                        <i class="fas fa-id-card"></i>
                                        <div>
                                            <small style="color: #666;">Biển số</small><br>
                                            <strong><?= $vehicle['license_plate'] ?></strong>
                                        </div>
                                    </div>
                                    
                                    <div class="spec-item">
                                        <i class="fas fa-calendar"></i>
                                        <div>
                                            <small style="color: #666;">Năm</small><br>
                                            <strong><?= $vehicle['catalog']['year'] ?></strong>
                                        </div>
                                    </div>
                                    
                                    <?php if ($vehicle['catalog']['seats']): ?>
                                    <div class="spec-item">
                                        <i class="fas fa-users"></i>
                                        <div>
                                            <small style="color: #666;">Chỗ ngồi</small><br>
                                            <strong><?= $vehicle['catalog']['seats'] ?> chỗ</strong>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($vehicle['catalog']['transmission']): ?>
                                    <div class="spec-item">
                                        <i class="fas fa-cog"></i>
                                        <div>
                                            <small style="color: #666;">Hộp số</small><br>
                                            <strong><?= $vehicle['catalog']['transmission'] ?></strong>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Rental Info -->
                    <div class="section">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i> Thông tin thuê
                        </h2>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Thời gian bắt đầu</div>
                                <div class="info-value">
                                    <i class="fas fa-calendar-check"></i>
                                    <?= formatDate($rental['start_time']) ?>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Thời gian kết thúc</div>
                                <div class="info-value">
                                    <i class="fas fa-calendar-times"></i>
                                    <?= formatDate($rental['end_time']) ?>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Số ngày thuê</div>
                                <div class="info-value">
                                    <i class="fas fa-clock"></i>
                                    <?= $days ?> ngày
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Địa điểm nhận xe</div>
                                <div class="info-value">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($rental['pickup_location']) ?>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Địa điểm trả xe</div>
                                <div class="info-value">
                                    <i class="fas fa-map-pin"></i>
                                    <?= htmlspecialchars($rental['dropoff_location']) ?>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Tổng chi phí</div>
                                <div class="info-value" style="font-size: 24px; color: #4F46E5;">
                                    <i class="fas fa-dollar-sign"></i>
                                    <?= number_format($rental['total_cost']) ?>đ
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($rental['promo_code']): ?>
                        <div style="margin-top: 20px; padding: 15px; background: #d1fae5; border-radius: 12px;">
                            <i class="fas fa-tag" style="color: #065f46;"></i>
                            <strong style="color: #065f46;">Đã áp dụng mã khuyến mãi:</strong>
                            <?= htmlspecialchars($rental['promo_code']) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Payment Info -->
                    <?php if (!empty($payments)): ?>
                    <div class="section">
                        <h2 class="section-title">
                            <i class="fas fa-credit-card"></i> Thông tin thanh toán
                        </h2>
                        
                        <?php foreach ($payments as $payment): ?>
                        <div class="payment-card">
                            <div class="payment-header">
                                <div>
                                    <div style="font-size: 13px; color: #666; margin-bottom: 5px;">
                                        Mã giao dịch: <strong><?= $payment['transaction_code'] ?></strong>
                                    </div>
                                    <div class="payment-amount">
                                        <?= number_format($payment['amount']) ?>đ
                                    </div>
                                </div>
                                <div>
                                    <?= getPaymentStatusBadge($payment['status']) ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 20px; font-size: 14px; color: #666;">
                                <div>
                                    <i class="fas fa-credit-card"></i>
                                    <?= $payment['payment_method'] ?>
                                </div>
                                <div>
                                    <i class="far fa-calendar"></i>
                                    <?= formatDate($payment['transaction_date']) ?>
                                </div>
                            </div>
                            
                            <?php if ($payment['qr_code_url']): ?>
                            <div style="margin-top: 15px;">
                                <img src="<?= htmlspecialchars($payment['qr_code_url']) ?>" 
                                     alt="QR Code"
                                     style="max-width: 200px; border-radius: 10px;">
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Timeline -->
                    <div class="section">
                        <h2 class="section-title">
                            <i class="fas fa-history"></i> Lịch sử
                        </h2>
                        
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="timeline-date">
                                        <?= formatDate($rental['created_at']) ?>
                                    </div>
                                    <div class="timeline-text">
                                        <i class="fas fa-plus-circle"></i>
                                        Đơn thuê được tạo
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($rental['status'] !== 'Pending'): ?>
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="timeline-date">
                                        <?= formatDate($rental['created_at']) ?>
                                    </div>
                                    <div class="timeline-text">
                                        <i class="fas fa-check-circle"></i>
                                        Đơn thuê được xác nhận
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($rental['status'] === 'Completed'): ?>
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="timeline-date">
                                        <?= formatDate($rental['end_time']) ?>
                                    </div>
                                    <div class="timeline-text">
                                        <i class="fas fa-check-double"></i>
                                        Đơn thuê hoàn thành
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($rental['status'] === 'Cancelled'): ?>
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="timeline-date">
                                        <?= date('d/m/Y H:i') ?>
                                    </div>
                                    <div class="timeline-text">
                                        <i class="fas fa-times-circle"></i>
                                        Đơn thuê đã bị hủy
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="action-buttons">
                        <?php if ($rental['status'] === 'Pending'): ?>
                        <button class="btn btn-danger" onclick="window.location.href='my-rentals.php'">
                            <i class="fas fa-times"></i> Hủy đơn
                        </button>
                        <?php endif; ?>
                        
                        <?php if (!empty($payments) && $payments[0]['status'] === 'Success'): ?>
                        <a href="invoice.php?rental_id=<?= $rentalId ?>" class="btn btn-primary">
                            <i class="fas fa-file-invoice"></i> Xem hóa đơn
                        </a>
                        <?php endif; ?>
                        
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print"></i> In chi tiết
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>