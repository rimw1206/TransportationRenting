<?php
/**
 * ================================================
 * public/rental-detail.php - PAYMENT LOGIC FIXED
 * ✅ Payment button logic EXACTLY matches my-rentals.php
 * ✅ Show button ONLY when: VNPayQR + Pending + Ongoing
 * ✅ No crash on cancelled rentals
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
$apiClient->setServiceUrl('order', 'http://localhost:8004');

// Fetch rental details
$rental = null;
$vehicle = null;
$transaction = null;
$order = null;
$errorMessage = null;

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
            
            // Get vehicle - WITH ERROR HANDLING
            try {
                $vResponse = $apiClient->get('vehicle', '/units/' . $rental['vehicle_id']);
                if ($vResponse['status_code'] === 200) {
                    $vData = json_decode($vResponse['raw_response'], true);
                    if ($vData && $vData['success']) {
                        $vehicle = $vData['data'];
                    }
                }
            } catch (Exception $e) {
                error_log("Vehicle fetch error: " . $e->getMessage());
            }
            
            // Get order - WITH ERROR HANDLING
            try {
                $oResponse = $apiClient->get('order', "/orders/rental/{$rentalId}");
                if ($oResponse['status_code'] === 200) {
                    $oData = json_decode($oResponse['raw_response'], true);
                    if ($oData && $oData['success']) {
                        $order = $oData['data'];
                    }
                }
            } catch (Exception $e) {
                error_log("Order fetch error: " . $e->getMessage());
            }
            
            // Get FULL transaction data
            try {
                $pResponse = $apiClient->get('payment', "/payments/rental/{$rentalId}/transactions", [
                    'Authorization: Bearer ' . $token
                ]);
                
                if ($pResponse['status_code'] === 200) {
                    $pData = json_decode($pResponse['raw_response'], true);
                    if ($pData && $pData['success']) {
                        if (isset($pData['data']['transactions']) && !empty($pData['data']['transactions'])) {
                            $transaction = $pData['data']['transactions'][0];
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Payment fetch error: " . $e->getMessage());
            }
        }
    }
    
    if (!$rental) {
        $errorMessage = "Không tìm thấy đơn thuê hoặc đã bị xóa.";
    }
    
} catch (Exception $e) {
    error_log('Error fetching rental detail: ' . $e->getMessage());
    $errorMessage = "Có lỗi xảy ra khi tải thông tin đơn thuê.";
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

function getDeliveryStatusBadge($status) {
    $badges = [
        'Pending' => '<span class="badge" style="background:#fef3c7;color:#92400e;"><i class="fas fa-clock"></i> Chờ xác nhận</span>',
        'Confirmed' => '<span class="badge" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-check"></i> Đã xác nhận</span>',
        'InTransit' => '<span class="badge" style="background:#fce7f3;color:#9d174d;"><i class="fas fa-shipping-fast"></i> Đang giao</span>',
        'Delivered' => '<span class="badge" style="background:#d1fae5;color:#065f46;"><i class="fas fa-check-double"></i> Đã giao</span>',
        'Cancelled' => '<span class="badge" style="background:#fee2e2;color:#991b1b;"><i class="fas fa-times"></i> Đã hủy</span>'
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

$days = $rental ? calculateDays($rental['start_time'], $rental['end_time']) : 0;

// ✅ CRITICAL FIX: Payment button logic - EXACT SAME AS my-rentals.php
$showPaymentButton = false;
if ($rental && $transaction) {
    // Show button ONLY when ALL THREE conditions are met:
    // 1. Payment method = VNPayQR
    // 2. Payment status = Pending
    // 3. Rental status = Ongoing (admin already approved)
    $showPaymentButton = (
        $transaction['payment_method'] === 'VNPayQR' &&
        $transaction['status'] === 'Pending' &&
        $rental['status'] === 'Ongoing'
    );
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $rental ? "Chi tiết đơn #{$rentalId}" : "Lỗi" ?> - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard_style.css">
    <style>
        .detail-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; color: #4F46E5; text-decoration: none; margin-bottom: 20px; font-weight: 600; }
        .back-btn:hover { text-decoration: underline; }
        .error-card { background: white; border-radius: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 60px; text-align: center; }
        .error-card i { font-size: 80px; color: #DC2626; margin-bottom: 20px; }
        .error-card h2 { font-size: 28px; color: #1a1a1a; margin-bottom: 15px; }
        .error-card p { font-size: 16px; color: #666; margin-bottom: 30px; }
        .detail-card { background: white; border-radius: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden; }
        .detail-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; }
        .detail-header h1 { font-size: 32px; margin-bottom: 10px; }
        .detail-header-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 15px; }
        .detail-body { padding: 40px; }
        .section { margin-bottom: 40px; }
        .section-title { font-size: 20px; font-weight: 700; color: #1a1a1a; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .info-item { padding: 20px; background: #f8f9fa; border-radius: 12px; }
        .info-label { font-size: 13px; color: #666; font-weight: 600; text-transform: uppercase; margin-bottom: 8px; }
        .info-value { font-size: 16px; color: #1a1a1a; font-weight: 500; }
        .vehicle-showcase { display: grid; grid-template-columns: 300px 1fr; gap: 30px; align-items: start; }
        @media (max-width: 768px) { .vehicle-showcase { grid-template-columns: 1fr; } }
        .vehicle-image { width: 100%; height: 200px; object-fit: cover; border-radius: 15px; }
        .vehicle-specs { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .spec-item { display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8f9fa; border-radius: 10px; }
        .spec-item i { color: #4F46E5; font-size: 18px; }
        .payment-card { border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px; margin-bottom: 15px; }
        .payment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .payment-amount { font-size: 24px; font-weight: 700; color: #4F46E5; }
        .action-buttons { display: flex; gap: 15px; margin-top: 30px; flex-wrap: wrap; }
        .btn { padding: 14px 28px; border: none; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 15px; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #4F46E5; color: white; }
        .btn-primary:hover { background: #4338CA; transform: translateY(-2px); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-2px); }
        .btn-danger { background: white; color: #DC2626; border: 2px solid #DC2626; }
        .btn-danger:hover { background: #DC2626; color: white; }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn-secondary:hover { background: #cbd5e0; }
        .badge { padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-completed { background: #dbeafe; color: #1e40af; }
        .badge-danger { background: #fee; color: #991b1b; }
        .badge-info { background: #e0e7ff; color: #3730a3; }
        
        /* ✅ Payment alert styling */
        .payment-alert {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .payment-alert i { font-size: 24px; color: #f59e0b; }
        .payment-alert-content { flex: 1; }
        .payment-alert-title { font-weight: 700; color: #92400e; margin-bottom: 5px; }
        .payment-alert-text { color: #78350f; font-size: 14px; }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand"><i class="fas fa-car"></i><span>Transportation</span></div>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Trang chủ</a>
                <a href="vehicles.php" class="nav-link"><i class="fas fa-car-side"></i> Xe có sẵn</a>
                <a href="my-rentals.php" class="nav-link active"><i class="fas fa-calendar-check"></i> Đơn của tôi</a>
            </div>
            <div class="nav-actions">
                <div class="user-menu">
                    <button class="user-btn" id="userBtn">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=4F46E5&color=fff" alt="Avatar">
                        <span><?= htmlspecialchars($user['name']) ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="profile.php"><i class="fas fa-user"></i> Tài khoản</a>
                        <a href="my-rentals.php" class="active"><i class="fas fa-history"></i> Lịch sử thuê</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="main-container">
        <div class="detail-container">
            <a href="my-rentals.php" class="back-btn"><i class="fas fa-arrow-left"></i> Quay lại danh sách</a>

            <?php if ($errorMessage): ?>
                <div class="error-card">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Không thể tải thông tin</h2>
                    <p><?= htmlspecialchars($errorMessage) ?></p>
                    <a href="my-rentals.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Quay lại danh sách</a>
                </div>
            <?php else: ?>
                <div class="detail-card">
                    <div class="detail-header">
                        <h1><i class="fas fa-file-alt"></i> Đơn thuê #<?= $rentalId ?></h1>
                        <div class="detail-header-meta">
                            <div><i class="far fa-calendar"></i> Tạo lúc: <?= formatDate($rental['created_at']) ?></div>
                            <div><?= getStatusBadge($rental['status']) ?></div>
                        </div>
                    </div>

                    <div class="detail-body">
                        <!-- ✅ Payment Alert - Shows when conditions are met -->
                        <?php if ($showPaymentButton): ?>
                        <div class="payment-alert">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="payment-alert-content">
                                <div class="payment-alert-title">Vui lòng hoàn tất thanh toán</div>
                                <div class="payment-alert-text">Đơn thuê của bạn đã được duyệt. Vui lòng thanh toán để hoàn tất giao dịch.</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Vehicle Info -->
                        <div class="section">
                            <h2 class="section-title"><i class="fas fa-car"></i> Thông tin xe</h2>
                            <?php if ($vehicle): ?>
                            <div class="vehicle-showcase">
                                <img src="https://images.unsplash.com/photo-1590362891991-f776e747a588?w=400" alt="Vehicle" class="vehicle-image">
                                <div>
                                    <h3 style="font-size: 24px; margin-bottom: 15px;">
                                        <?= htmlspecialchars($vehicle['catalog']['brand'] . ' ' . $vehicle['catalog']['model']) ?>
                                    </h3>
                                    <div class="vehicle-specs">
                                        <div class="spec-item">
                                            <i class="fas fa-id-card"></i>
                                            <div><small style="color: #666;">Biển số</small><br><strong><?= htmlspecialchars($vehicle['license_plate']) ?></strong></div>
                                        </div>
                                        <div class="spec-item">
                                            <i class="fas fa-calendar"></i>
                                            <div><small style="color: #666;">Năm</small><br><strong><?= $vehicle['catalog']['year'] ?></strong></div>
                                        </div>
                                        <?php if (!empty($vehicle['catalog']['seats'])): ?>
                                        <div class="spec-item">
                                            <i class="fas fa-users"></i>
                                            <div><small style="color: #666;">Chỗ ngồi</small><br><strong><?= $vehicle['catalog']['seats'] ?> chỗ</strong></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="info-item"><div class="info-label">Xe</div><div class="info-value"><i class="fas fa-car"></i> Xe #<?= $rental['vehicle_id'] ?></div></div>
                            <?php endif; ?>
                        </div>

                        <!-- Rental Info -->
                        <div class="section">
                            <h2 class="section-title"><i class="fas fa-info-circle"></i> Thông tin thuê</h2>
                            <div class="info-grid">
                                <div class="info-item"><div class="info-label">Thời gian bắt đầu</div><div class="info-value"><i class="fas fa-calendar-check"></i> <?= formatDate($rental['start_time']) ?></div></div>
                                <div class="info-item"><div class="info-label">Thời gian kết thúc</div><div class="info-value"><i class="fas fa-calendar-times"></i> <?= formatDate($rental['end_time']) ?></div></div>
                                <div class="info-item"><div class="info-label">Số ngày thuê</div><div class="info-value"><i class="fas fa-clock"></i> <?= $days ?> ngày</div></div>
                                <div class="info-item"><div class="info-label">Địa điểm nhận xe</div><div class="info-value"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($rental['pickup_location']) ?></div></div>
                                <div class="info-item"><div class="info-label">Tổng chi phí</div><div class="info-value" style="font-size: 24px; color: #4F46E5;"><i class="fas fa-dollar-sign"></i> <?= number_format($rental['total_cost']) ?>đ</div></div>
                            </div>
                        </div>

                        <!-- Order Tracking -->
                        <?php if ($order): ?>
                        <div class="section">
                            <h2 class="section-title"><i class="fas fa-truck"></i> Trạng thái giao xe</h2>
                            <div class="info-item"><div class="info-label">Trạng thái hiện tại</div><div class="info-value"><?= getDeliveryStatusBadge($order['delivery_status']) ?></div></div>
                            <div style="margin-top: 15px;"><a href="order-tracking.php?rental_id=<?= $rentalId ?>" class="btn btn-primary"><i class="fas fa-map-marked-alt"></i> Theo dõi chi tiết</a></div>
                        </div>
                        <?php endif; ?>

                        <!-- Payment Info -->
                        <?php if ($transaction): ?>
                        <div class="section">
                            <h2 class="section-title"><i class="fas fa-credit-card"></i> Thông tin thanh toán</h2>
                            <div class="payment-card">
                                <div class="payment-header">
                                    <div>
                                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Mã giao dịch: <strong><?= htmlspecialchars($transaction['transaction_code'] ?? 'N/A') ?></strong></div>
                                        <div class="payment-amount"><?= number_format($transaction['amount'] ?? 0) ?>đ</div>
                                    </div>
                                    <div><?= getPaymentStatusBadge($transaction['status'] ?? 'Pending') ?></div>
                                </div>
                                <div style="display: flex; gap: 20px; font-size: 14px; color: #666; flex-wrap: wrap;">
                                    <div><i class="fas fa-credit-card"></i> <?= htmlspecialchars($transaction['payment_method'] ?? 'N/A') ?></div>
                                    <div><i class="far fa-calendar"></i> <?= formatDate($transaction['transaction_date'] ?? 'now') ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="action-buttons">
                            <?php 
                            // ✅ ONLY SHOW PAYMENT BUTTON WHEN:
                            // 1. Payment method = VNPayQR
                            // 2. Payment status = Pending
                            // 3. Rental status = Ongoing (admin approved)
                            if ($showPaymentButton): 
                            ?>
                                <a href="payment-page.php?transaction_id=<?= $transaction['transaction_id'] ?>" class="btn btn-success">
                                    <i class="fas fa-qrcode"></i> Thanh toán ngay
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                            // ✅ ONLY ALLOW CANCEL WHEN:
                            // - Status = Pending (not yet approved by admin)
                            if ($rental['status'] === 'Pending'): 
                            ?>
                                <button class="btn btn-danger" onclick="if(confirm('Bạn có chắc muốn hủy đơn này?')) window.location.href='api/cancel-rental.php?id=<?= $rentalId ?>'">
                                    <i class="fas fa-times"></i> Hủy đơn
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($transaction && $transaction['status'] === 'Success'): ?>
                                <a href="invoice.php?transaction_id=<?= $transaction['transaction_id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-file-invoice"></i> Xem hóa đơn
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> In chi tiết</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const userBtn = document.getElementById('userBtn');
        const userDropdown = document.getElementById('userDropdown');
        userBtn?.addEventListener('click', (e) => { e.stopPropagation(); userDropdown.classList.toggle('show'); });
        document.addEventListener('click', () => userDropdown?.classList.remove('show'));
    </script>
</body>
</html>