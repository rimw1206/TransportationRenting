<?php
/**
 * ================================================
 * public/my-rentals.php - INTEGRATED VERSION
 * ✅ Transaction-based view với Order Tracking
 * ✅ Hiển thị theo giao dịch (1 payment = 1 card)
 * ✅ Hỗ trợ cả single rental và cart checkout
 * ================================================
 */

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'] ?? '';

require_once __DIR__ . '/../shared/classes/ApiClient.php';
$apiClient = new ApiClient();
$apiClient->setServiceUrl('rental', 'http://localhost:8003');
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');
$apiClient->setServiceUrl('payment', 'http://localhost:8005');
$apiClient->setServiceUrl('order', 'http://localhost:8004');

// Get filter and view mode from query params
$statusFilter = $_GET['status'] ?? 'all';
$viewMode = $_GET['view'] ?? 'transaction'; // 'transaction' or 'rental'

$transactions = [];
$stats = [
    'total' => 0,
    'ongoing' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'pending' => 0
];

try {
    // Get all user transactions
    $response = $apiClient->get('payment', '/payments/transactions', [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['raw_response'], true);
        if ($data && $data['success']) {
            $transactionsData = $data['data']['items'] ?? [];
            
            foreach ($transactionsData as $txn) {
                $metadata = !empty($txn['metadata']) ? json_decode($txn['metadata'], true) : [];
                $rentalIds = $metadata['rental_ids'] ?? [];
                $rentalCount = count($rentalIds);
                
                if ($rentalCount == 0) continue;
                
                $rentals = [];
                $allCancelled = true;
                $hasOngoing = false;
                $hasCompleted = false;
                $hasPending = false;
                
                foreach ($rentalIds as $rentalId) {
                    $rentalResponse = $apiClient->get('rental', "/rentals/{$rentalId}", [
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
                            
                            // Get order info
                            $orderInfo = null;
                            try {
                                $orderResponse = $apiClient->get('order', '/orders/rental/' . $rental['rental_id']);
                                if ($orderResponse['status_code'] === 200) {
                                    $oData = json_decode($orderResponse['raw_response'], true);
                                    if ($oData && $oData['success']) {
                                        $orderInfo = $oData['data'];
                                    }
                                }
                            } catch (Exception $e) {
                                error_log("Order fetch error: " . $e->getMessage());
                            }
                            
                            $rentals[] = [
                                'rental' => $rental,
                                'vehicle' => $vehicle,
                                'order' => $orderInfo
                            ];
                            
                            if ($rental['status'] !== 'Cancelled') $allCancelled = false;
                            if ($rental['status'] === 'Ongoing') $hasOngoing = true;
                            if ($rental['status'] === 'Completed') $hasCompleted = true;
                            if ($rental['status'] === 'Pending') $hasPending = true;
                        }
                    }
                }
                
                // Determine overall status
                if ($allCancelled) {
                    $overallStatus = 'Cancelled';
                } elseif ($hasOngoing) {
                    $overallStatus = 'Ongoing';
                } elseif ($hasCompleted && !$hasPending && !$hasOngoing) {
                    $overallStatus = 'Completed';
                } else {
                    $overallStatus = 'Pending';
                }
                
                if ($statusFilter !== 'all' && strtolower($overallStatus) !== strtolower($statusFilter)) {
                    continue;
                }
                
                $originalAmount = $metadata['original_amount'] ?? $txn['amount'];
                $discountAmount = $metadata['discount_amount'] ?? 0;
                $finalAmount = $txn['amount'];
                $promoCode = $metadata['promo_code'] ?? null;
                
                $transactions[] = [
                    'transaction_id' => $txn['transaction_id'],
                    'transaction_code' => $txn['transaction_code'],
                    'transaction_date' => $txn['transaction_date'],
                    'payment_method' => $txn['payment_method'],
                    'payment_status' => $txn['status'],
                    'rentals' => $rentals,
                    'rental_count' => $rentalCount,
                    'original_amount' => $originalAmount,
                    'discount_amount' => $discountAmount,
                    'final_amount' => $finalAmount,
                    'promo_code' => $promoCode,
                    'overall_status' => $overallStatus,
                    'qr_code_url' => $txn['qr_code_url'] ?? null,
                    'is_cart_checkout' => $metadata['cart_checkout'] ?? false
                ];
                
                $stats['total']++;
                $statusKey = strtolower($overallStatus);
                if (isset($stats[$statusKey])) {
                    $stats[$statusKey]++;
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log('Error fetching transactions: ' . $e->getMessage());
}

// Helper functions
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

function getPaymentMethodBadge($method) {
    $badges = [
        'COD' => '<span class="badge" style="background:#dcfce7;color:#166534;"><i class="fas fa-money-bill-wave"></i> Tiền mặt</span>',
        'VNPayQR' => '<span class="badge" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-qrcode"></i> VNPay QR</span>'
    ];
    return $badges[$method] ?? $method;
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn thuê của tôi - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard_style.css">
    <style>
        .rentals-container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            width: 60px; height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-icon.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.ongoing { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.completed { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
        .stat-icon.cancelled { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        
        .stat-info h3 { font-size: 32px; font-weight: 700; margin-bottom: 5px; color: #1a1a1a; }
        .stat-info p { color: #666; font-size: 14px; margin: 0; }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-label { font-weight: 600; color: #333; }
        
        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            color: #666;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-btn:hover { border-color: #667eea; color: #667eea; background: #f5f7ff; }
        .filter-btn.active { background: #667eea; color: white; border-color: #667eea; }
        
        .transaction-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .transaction-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.12); }
        
        .transaction-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .transaction-id { font-weight: 700; color: #1a1a1a; font-size: 16px; }
        
        .vehicle-count-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }
        
        .cart-checkout-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }
        
        .transaction-body { padding: 25px; }
        
        .rentals-list { margin-bottom: 20px; }
        
        .rental-item {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .rental-item:hover { border-color: #667eea; }
        
        .rental-grid {
            display: grid;
            grid-template-columns: 150px 1fr auto;
            gap: 20px;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .rental-grid { grid-template-columns: 1fr; }
        }
        
        .vehicle-image {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
            background: #f0f0f0;
        }
        
        .vehicle-name { font-size: 16px; font-weight: 700; color: #1a1a1a; margin-bottom: 8px; }
        
        .detail-row {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .detail-row i { width: 16px; color: #667eea; }
        
        .rental-actions-mini { display: flex; gap: 8px; flex-wrap: wrap; }
        
        .transaction-summary {
            background: #f7fafc;
            border-radius: 12px;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .summary-item { text-align: center; }
        .summary-label { font-size: 13px; color: #666; margin-bottom: 5px; }
        .summary-value { font-size: 20px; font-weight: 700; color: #1a1a1a; }
        .summary-value.discount { color: #059669; }
        .summary-value.final { color: #667eea; font-size: 24px; }
        
        .promo-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
        }
        
        .payment-info {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn-sm { padding: 8px 14px; font-size: 12px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn-secondary:hover { background: #cbd5e0; }
        .btn-tracking { background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); color: white; }
        .btn-tracking:hover { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
        
        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
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
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 16px;
        }
        
        .empty-state i { font-size: 80px; color: #ddd; margin-bottom: 20px; }
        .empty-state h2 { color: #666; margin-bottom: 10px; }
        .empty-state p { color: #999; margin-bottom: 30px; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show { display: flex; }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .btn-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <i class="fas fa-car"></i>
                <span>Transportation</span>
            </div>
            
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Trang chủ</a>
                <a href="vehicles.php" class="nav-link"><i class="fas fa-car-side"></i> Xe có sẵn</a>
                <a href="my-rentals.php" class="nav-link active"><i class="fas fa-calendar-check"></i> Đơn của tôi</a>
                <a href="promotions.php" class="nav-link"><i class="fas fa-gift"></i> Khuyến mãi</a>
            </div>
            
            <div class="nav-actions">
                <a href="cart.php" class="nav-icon-btn" title="Giỏ hàng">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                        <span class="badge"><?= count($_SESSION['cart']) ?></span>
                    <?php endif; ?>
                </a>
                
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
        <div class="rentals-container">
            <h1 style="margin-bottom: 30px;">
                <i class="fas fa-calendar-check"></i> Đơn thuê của tôi
            </h1>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['total'] ?></h3>
                        <p>Tổng đơn</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon ongoing"><i class="fas fa-car"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['ongoing'] ?></h3>
                        <p>Đang thuê</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon completed"><i class="fas fa-check-double"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['completed'] ?></h3>
                        <p>Hoàn thành</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon cancelled"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['cancelled'] ?></h3>
                        <p>Đã hủy</p>
                    </div>
                </div>
            </div>

            <div class="filters">
                <span class="filter-label"><i class="fas fa-filter"></i> Lọc:</span>
                <a href="my-rentals.php?status=all" class="filter-btn <?= $statusFilter === 'all' ? 'active' : '' ?>">Tất cả</a>
                <a href="my-rentals.php?status=pending" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>"><i class="fas fa-clock"></i> Chờ xử lý</a>
                <a href="my-rentals.php?status=ongoing" class="filter-btn <?= $statusFilter === 'ongoing' ? 'active' : '' ?>"><i class="fas fa-car"></i> Đang thuê</a>
                <a href="my-rentals.php?status=completed" class="filter-btn <?= $statusFilter === 'completed' ? 'active' : '' ?>"><i class="fas fa-check"></i> Hoàn thành</a>
                <a href="my-rentals.php?status=cancelled" class="filter-btn <?= $statusFilter === 'cancelled' ? 'active' : '' ?>"><i class="fas fa-times"></i> Đã hủy</a>
            </div>

            <?php if (empty($transactions)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h2>Không có đơn thuê nào</h2>
                    <p>Bạn chưa có đơn thuê xe nào trong hệ thống</p>
                    <a href="vehicles.php" class="btn btn-primary"><i class="fas fa-car-side"></i> Thuê xe ngay</a>
                </div>
            <?php else: ?>
                <?php foreach ($transactions as $txn): ?>
                <div class="transaction-card">
                    <div class="transaction-header">
                        <div>
                            <span class="transaction-id">
                                <i class="fas fa-receipt"></i> Mã GD: <?= $txn['transaction_code'] ?>
                            </span>
                            <span class="vehicle-count-badge">
                                <i class="fas fa-car"></i> <?= $txn['rental_count'] ?> xe
                            </span>
                            <?php if ($txn['is_cart_checkout']): ?>
                                <span class="cart-checkout-badge"><i class="fas fa-shopping-cart"></i> Thanh toán gộp</span>
                            <?php endif; ?>
                            <span style="margin-left: 15px; color: #999; font-size: 14px;">
                                <i class="far fa-calendar"></i> <?= formatDate($txn['transaction_date']) ?>
                            </span>
                        </div>
                        <div><?= getStatusBadge($txn['overall_status']) ?></div>
                    </div>
                    
                    <div class="transaction-body">
                        <div class="rentals-list">
                            <?php foreach ($txn['rentals'] as $item): 
                                $rental = $item['rental'];
                                $vehicle = $item['vehicle'];
                                $order = $item['order'];
                                $days = calculateDays($rental['start_time'], $rental['end_time']);
                            ?>
                            <div class="rental-item">
                                <div class="rental-grid">
                                    <div>
                                        <?php if ($vehicle): ?>
                                        <img src="https://images.unsplash.com/photo-1590362891991-f776e747a588?w=400" 
                                             alt="<?= htmlspecialchars($vehicle['catalog']['brand'] . ' ' . $vehicle['catalog']['model']) ?>"
                                             class="vehicle-image">
                                        <?php else: ?>
                                        <div class="vehicle-image" style="display:flex;align-items:center;justify-content:center;color:#ccc;">
                                            <i class="fas fa-car" style="font-size:36px;"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div>
                                        <div class="vehicle-name">
                                            <?php if ($vehicle): ?>
                                                <?= htmlspecialchars($vehicle['catalog']['brand'] . ' ' . $vehicle['catalog']['model']) ?>
                                                <span style="font-size:13px;color:#666;font-weight:normal;">(<?= $vehicle['license_plate'] ?>)</span>
                                            <?php else: ?>
                                                Xe #<?= $rental['vehicle_id'] ?>
                                            <?php endif; ?>
                                            <?= getStatusBadge($rental['status']) ?>
                                        </div>
                                        
                                        <div class="detail-row">
                                            <i class="fas fa-calendar"></i>
                                            <?= formatDate($rental['start_time']) ?> → <?= formatDate($rental['end_time']) ?> (<?= $days ?> ngày)
                                        </div>
                                        <div class="detail-row">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?= htmlspecialchars($rental['pickup_location']) ?>
                                        </div>
                                        <div class="detail-row">
                                            <i class="fas fa-dollar-sign"></i>
                                            <strong><?= number_format($rental['total_cost']) ?>đ</strong>
                                        </div>
                                        <?php if ($order): ?>
                                        <div class="detail-row">
                                            <i class="fas fa-truck"></i>
                                            Giao xe: <?= getDeliveryStatusBadge($order['delivery_status']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="rental-actions-mini">
                                        <?php if ($order): ?>
                                            <a href="order-tracking.php?rental_id=<?= $rental['rental_id'] ?>" class="btn btn-sm btn-tracking">
                                                <i class="fas fa-truck"></i> Theo dõi
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="transaction-summary">
                            <div class="summary-item">
                                <div class="summary-label">Tổng tiền gốc</div>
                                <div class="summary-value"><?= number_format($txn['original_amount']) ?>đ</div>
                            </div>
                            
                            <?php if ($txn['discount_amount'] > 0): ?>
                            <div class="summary-item">
                                <div class="summary-label">Giảm giá</div>
                                <div class="summary-value discount">-<?= number_format($txn['discount_amount']) ?>đ</div>
                                <?php if ($txn['promo_code']): ?>
                                <div class="promo-badge"><i class="fas fa-tag"></i> <?= htmlspecialchars($txn['promo_code']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="summary-item">
                                <div class="summary-label">Tổng thanh toán</div>
                                <div class="summary-value final"><?= number_format($txn['final_amount']) ?>đ</div>
                            </div>
                        </div>
                        
                        <div class="payment-info">
                            <div>
                                <strong>Thanh toán:</strong>
                                <?= getPaymentMethodBadge($txn['payment_method']) ?>
                                <?= getPaymentStatusBadge($txn['payment_status']) ?>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="transaction-detail.php?id=<?= $txn['transaction_id'] ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i> Xem chi tiết
                            </a>
                            
                                                        <?php 
                            // ✅ LOGIC FIXED: CHỈ HIỆN NÚT THANH TOÁN KHI:
                            // 1. Payment method = VNPayQR
                            // 2. Payment status = Pending
                            // 3. Overall status = Ongoing (có ít nhất 1 rental Ongoing)
                            // 4. Không phải Cancelled
                            
                            if (
                                $txn['payment_method'] === 'VNPayQR' && 
                                $txn['payment_status'] === 'Pending' &&
                                $txn['overall_status'] === 'Ongoing'
                            ): 
                            ?>
                                <a href="payment-page.php?transaction_id=<?= $txn['transaction_id'] ?>" class="btn btn-success">
                                    <i class="fas fa-qrcode"></i> Thanh toán ngay
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                            // ✅ CHỈ CHO HỦY ĐƠN KHI:
                            // - Overall status = Pending (tất cả rental đều chưa admin duyệt)
                            // - Không phải Cancelled
                            if ($txn['overall_status'] === 'Pending' && $txn['overall_status'] !== 'Cancelled'): 
                            ?>
                                <button class="btn btn-danger" onclick="cancelTransaction(<?= $txn['transaction_id'] ?>)">
                                    <i class="fas fa-times"></i> Hủy đơn
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($txn['payment_status'] === 'Success'): ?>
                                <a href="invoice.php?transaction_id=<?= $txn['transaction_id'] ?>" class="btn btn-secondary">
                                    <i class="fas fa-file-invoice"></i> Hóa đơn
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Xác nhận hủy đơn</h2>
                <button class="btn-close" onclick="closeCancelModal()">&times;</button>
            </div>
            <p style="margin-bottom:20px;color:#666;">
                Bạn có chắc chắn muốn hủy <strong>toàn bộ đơn hàng</strong> này? 
                Tất cả các xe trong đơn sẽ bị hủy. Hành động này không thể hoàn tác.
            </p>
            <input type="hidden" id="cancelId" value="">
            <div style="display:flex;gap:10px;">
                <button class="btn btn-secondary" style="flex:1;" onclick="closeCancelModal()">Không, giữ lại</button>
                <button class="btn btn-danger" style="flex:1;" onclick="confirmCancel()"><i class="fas fa-check"></i> Có, hủy đơn</button>
            </div>
        </div>
    </div>

    <script>
        const userBtn = document.getElementById('userBtn');
        const userDropdown = document.getElementById('userDropdown');
        
        userBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', () => userDropdown?.classList.remove('show'));

        function cancelTransaction(transactionId) {
            document.getElementById('cancelId').value = transactionId;
            document.getElementById('cancelModal').classList.add('show');
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('show');
        }

        async function confirmCancel() {
            const transactionId = document.getElementById('cancelId').value;
            
            const modal = document.getElementById('cancelModal');
            const btn = modal.querySelector('.btn-danger');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang hủy...';
            
            try {
                const response = await fetch('api/transaction-cancel.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ transaction_id: transactionId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Hủy đơn thành công!');
                    location.reload();
                } else {
                    alert('❌ ' + (result.message || 'Có lỗi xảy ra'));
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Lỗi kết nối');
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }

        document.getElementById('cancelModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'cancelModal') closeCancelModal();
        });

        <?php if ($stats['ongoing'] > 0): ?>
        setInterval(() => location.reload(), 60000);
        <?php endif; ?>
    </script>
</body>
</html>