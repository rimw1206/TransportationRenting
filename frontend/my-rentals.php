<?php
/**
 * ================================================
 * public/my-rentals.php - FIXED PAYMENT METHOD DISPLAY
 * Hiển thị đúng payment method cho từng đơn
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

// Get filter from query params
$statusFilter = $_GET['status'] ?? 'all';

// Fetch user rentals
$rentals = [];
$stats = [
    'total' => 0,
    'ongoing' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'pending' => 0
];

try {
    // Get rentals from Rental Service
    $filters = [];
    if ($statusFilter !== 'all') {
        $filters['status'] = ucfirst($statusFilter);
    }
    
    $queryString = http_build_query($filters);
    $response = $apiClient->get('rental', '/rentals?' . $queryString, [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['raw_response'], true);
        if ($data && $data['success']) {
            $rentalsData = $data['data'];
            
            // Enrich rentals with vehicle info
            foreach ($rentalsData as $rental) {
                // Get vehicle unit details
                $vehicleResponse = $apiClient->get('vehicle', '/units/' . $rental['vehicle_id']);
                
                $vehicleInfo = null;
                if ($vehicleResponse['status_code'] === 200) {
                    $vData = json_decode($vehicleResponse['raw_response'], true);
                    if ($vData && $vData['success']) {
                        $vehicleInfo = $vData['data'];
                    }
                }
                
// Get payment info if exists
$paymentInfo = null;
try {
    $paymentResponse = $apiClient->get('payment', '/payments/transactions?rental_id=' . $rental['rental_id'], [
        'Authorization: Bearer ' . $token
    ]);
    
    // ✅ Log raw response
    error_log("=== RENTAL #{$rental['rental_id']} ===");
    error_log("Payment API Status: " . $paymentResponse['status_code']);
    error_log("Payment API Raw Response: " . $paymentResponse['raw_response']);
    
    if ($paymentResponse['status_code'] === 200) {
        $pData = json_decode($paymentResponse['raw_response'], true);
        
        // ✅ Log parsed data
        error_log("Parsed Payment Data: " . json_encode($pData, JSON_PRETTY_PRINT));
        
        if ($pData && $pData['success'] && isset($pData['data']['items']) && !empty($pData['data']['items'])) {
            $paymentInfo = $pData['data']['items'][0];
            
            // ✅ Log payment method specifically
            error_log("Payment Method: " . ($paymentInfo['payment_method'] ?? 'MISSING'));
            error_log("Payment Status: " . ($paymentInfo['status'] ?? 'MISSING'));
            
            // ✅ Validate payment method exists
            if (!isset($paymentInfo['payment_method'])) {
                error_log("⚠️ WARNING: payment_method missing for rental #{$rental['rental_id']}");
                $paymentInfo['payment_method'] = 'Unknown';
            }
        } else {
            error_log("⚠️ No payment items found or invalid response structure");
        }
    } else {
        error_log("❌ Payment API returned non-200 status");
    }
} catch (Exception $e) {
    error_log("❌ Payment fetch error for rental #{$rental['rental_id']}: " . $e->getMessage());
}

// ✅ Final payment info check
error_log("Final Payment Info: " . json_encode($paymentInfo));
error_log("===================\n");
                
                $rentals[] = [
                    'rental' => $rental,
                    'vehicle' => $vehicleInfo,
                    'payment' => $paymentInfo
                ];
                
                // Update stats
                $stats['total']++;
                $status = strtolower($rental['status']);
                if (isset($stats[$status])) {
                    $stats[$status]++;
                    // Sau line 105 trong my-rentals.php
                    error_log("Rental #{$rental['rental_id']} - Payment info: " . json_encode($paymentInfo));
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log('Error fetching rentals: ' . $e->getMessage());
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
    // ✅ Handle null/empty
    if (empty($method)) {
        return '<span class="badge" style="background: #f3f4f6; color: #6b7280;"><i class="fas fa-question"></i> Chưa rõ</span>';
    }
    
    $badges = [
        'COD' => '<span class="badge" style="background: #dcfce7; color: #166534;"><i class="fas fa-money-bill-wave"></i> Tiền mặt (COD)</span>',
        'VNPayQR' => '<span class="badge" style="background: #dbeafe; color: #1e40af;"><i class="fas fa-qrcode"></i> VNPay QR</span>'
    ];
    return $badges[$method] ?? '<span class="badge" style="background: #f3f4f6; color: #6b7280;">' . htmlspecialchars($method) . '</span>';
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
        /* Copy all existing styles from document 9 */
        .rentals-container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
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
            width: 60px;
            height: 60px;
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
        
        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #1a1a1a;
        }
        
        .stat-info p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        
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
        
        .filter-label {
            font-weight: 600;
            color: #333;
        }
        
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
        
        .filter-btn:hover {
            border-color: #4F46E5;
            color: #4F46E5;
            background: #f5f7ff;
        }
        
        .filter-btn.active {
            background: #4F46E5;
            color: white;
            border-color: #4F46E5;
        }
        
        .rental-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .rental-card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .rental-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .rental-id {
            font-weight: 700;
            color: #1a1a1a;
            font-size: 16px;
        }
        
        .rental-body {
            padding: 25px;
        }
        
        .rental-grid {
            display: grid;
            grid-template-columns: 200px 1fr 250px;
            gap: 25px;
            align-items: start;
        }
        
        @media (max-width: 968px) {
            .rental-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .vehicle-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 12px;
            background: #f0f0f0;
        }
        
        .rental-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .vehicle-name {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        
        .detail-row {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 14px;
        }
        
        .detail-row i {
            width: 20px;
            color: #4F46E5;
        }
        
        .rental-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .price-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .price-label {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .price-amount {
            font-size: 28px;
            font-weight: 700;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            display: inline-block;
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
        
        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-completed {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-danger {
            background: #fee;
            color: #991b1b;
        }
        
        .badge-info {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 16px;
        }
        
        .empty-state i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h2 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
            margin-bottom: 30px;
        }
        
        .promo-tag {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
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
    <!-- Navigation -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <i class="fas fa-car"></i>
                <span>Transportation</span>
            </div>
            
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Trang chủ
                </a>
                <a href="vehicles.php" class="nav-link">
                    <i class="fas fa-car-side"></i> Xe có sẵn
                </a>
                <a href="my-rentals.php" class="nav-link active">
                    <i class="fas fa-calendar-check"></i> Đơn của tôi
                </a>
                <a href="promotions.php" class="nav-link">
                    <i class="fas fa-gift"></i> Khuyến mãi
                </a>
            </div>
            
            <div class="nav-actions">
                <a href="cart.php" class="nav-icon-btn" title="Giỏ hàng" style="position: relative; text-decoration: none; color: inherit;">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                        <span class="badge"><?= count($_SESSION['cart']) ?></span>
                    <?php endif; ?>
                </a>
                
                <button class="nav-icon-btn" title="Thông báo">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </button>
                
                <div class="user-menu">
                    <button class="user-btn" id="userBtn">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=4F46E5&color=fff" alt="Avatar">
                        <span><?= htmlspecialchars($user['name']) ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="profile.php">
                            <i class="fas fa-user"></i> Tài khoản
                        </a>
                        <a href="my-rentals.php" class="active">
                            <i class="fas fa-history"></i> Lịch sử thuê
                        </a>
                        <?php if (($user['role'] ?? 'user') === 'admin'): ?>
                        <div class="dropdown-divider"></div>
                        <a href="admin/dashboard.php">
                            <i class="fas fa-cog"></i> Quản trị
                        </a>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-container">
        <div class="rentals-container">
            <h1 style="margin-bottom: 30px;">
                <i class="fas fa-calendar-check"></i> Đơn thuê của tôi
            </h1>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total'] ?></h3>
                        <p>Tổng đơn</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon ongoing">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['ongoing'] ?></h3>
                        <p>Đang thuê</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon completed">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['completed'] ?></h3>
                        <p>Hoàn thành</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon cancelled">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['cancelled'] ?></h3>
                        <p>Đã hủy</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <span class="filter-label">
                    <i class="fas fa-filter"></i> Lọc:
                </span>
                <a href="my-rentals.php?status=all" class="filter-btn <?= $statusFilter === 'all' ? 'active' : '' ?>">
                    Tất cả
                </a>
                <a href="my-rentals.php?status=pending" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> Chờ xử lý
                </a>
                <a href="my-rentals.php?status=ongoing" class="filter-btn <?= $statusFilter === 'ongoing' ? 'active' : '' ?>">
                    <i class="fas fa-car"></i> Đang thuê
                </a>
                <a href="my-rentals.php?status=completed" class="filter-btn <?= $statusFilter === 'completed' ? 'active' : '' ?>">
                    <i class="fas fa-check"></i> Hoàn thành
                </a>
                <a href="my-rentals.php?status=cancelled" class="filter-btn <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">
                    <i class="fas fa-times"></i> Đã hủy
                </a>
            </div>

            <!-- Rentals List -->
            <?php if (empty($rentals)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h2>Không có đơn thuê nào</h2>
                    <p>Bạn chưa có đơn thuê xe nào trong hệ thống</p>
                    <a href="vehicles.php" class="btn btn-primary">
                        <i class="fas fa-car-side"></i> Thuê xe ngay
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($rentals as $item): 
                    $rental = $item['rental'];
                    $vehicle = $item['vehicle'];
                    $payment = $item['payment'];
                    $days = calculateDays($rental['start_time'], $rental['end_time']);
                ?>
                <div class="rental-card">
                    <div class="rental-header">
                        <div>
                            <span class="rental-id">Đơn #<?= $rental['rental_id'] ?></span>
                            <span style="margin-left: 15px; color: #999; font-size: 14px;">
                                <i class="far fa-calendar"></i> 
                                <?= formatDate($rental['created_at']) ?>
                            </span>
                        </div>
                        <div>
                            <?= getStatusBadge($rental['status']) ?>
                        </div>
                    </div>
                    
                    <div class="rental-body">
                        <div class="rental-grid">
                            <!-- Vehicle Image -->
                            <div>
                                <?php if ($vehicle): ?>
                                <img src="https://images.unsplash.com/photo-1590362891991-f776e747a588?w=400" 
                                     alt="<?= htmlspecialchars($vehicle['catalog']['brand'] . ' ' . $vehicle['catalog']['model']) ?>"
                                     class="vehicle-image">
                                <?php else: ?>
                                <div class="vehicle-image" style="display: flex; align-items: center; justify-content: center; color: #ccc;">
                                    <i class="fas fa-car" style="font-size: 48px;"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Rental Details -->
                            <div class="rental-details">
                                <?php if ($vehicle): ?>
                                <div class="vehicle-name">
                                    <?= htmlspecialchars($vehicle['catalog']['brand'] . ' ' . $vehicle['catalog']['model']) ?>
                                    <span style="font-size: 14px; color: #666; font-weight: normal;">
                                        (<?= $vehicle['license_plate'] ?>)
                                    </span>
                                </div>
                                <?php else: ?>
                                <div class="vehicle-name">
                                    Xe #<?= $rental['vehicle_id'] ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="detail-row">
                                    <i class="fas fa-calendar"></i>
                                    <strong>Thời gian:</strong> 
                                    <?= formatDate($rental['start_time']) ?> 
                                    → 
                                    <?= formatDate($rental['end_time']) ?>
                                    (<?= $days ?> ngày)
                                </div>
                                
                                <div class="detail-row">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <strong>Nhận xe:</strong> 
                                    <?= htmlspecialchars($rental['pickup_location']) ?>
                                </div>
                                
                                <div class="detail-row">
                                    <i class="fas fa-map-pin"></i>
                                    <strong>Trả xe:</strong> 
                                    <?= htmlspecialchars($rental['dropoff_location']) ?>
                                </div>
                                
                                <?php if ($rental['promo_code']): ?>
                                <div class="detail-row">
                                    <i class="fas fa-tag"></i>
                                    <span class="promo-tag">
                                        <i class="fas fa-gift"></i>
                                        <?= htmlspecialchars($rental['promo_code']) ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($payment && isset($payment['payment_method'])): ?>
                                <div class="detail-row">
                                    <i class="fas fa-credit-card"></i>
                                    <strong>Thanh toán:</strong> 
                                    <?= getPaymentMethodBadge($payment['payment_method']) ?>
                                    <?= getPaymentStatusBadge($payment['status'] ?? 'Unknown') ?>
                                </div>
                                <?php endif; ?>
                            </div>
                                                        
                            <!-- Actions -->
                            <div class="rental-actions">
                                <div class="price-box">
                                    <div class="price-label">Tổng tiền</div>
                                    <div class="price-amount">
                                        <?= number_format($rental['total_cost']) ?>đ
                                    </div>
                                </div>
                                
                                <?php 
                                // ✅ Chỉ hiển thị nút hủy nếu status = Pending
                                if ($rental['status'] === 'Pending'): 
                                ?>
                                    <button class="btn btn-danger" onclick="cancelRental(<?= $rental['rental_id'] ?>)">
                                        <i class="fas fa-times"></i> Hủy đơn
                                    </button>
                                <?php endif; ?>
                                
                               <?php 
                                // ✅ UPDATED: Chỉ hiển thị nút thanh toán khi:
                                // - Có payment info
                                // - Payment method là VNPayQR
                                // - Payment status là Pending
                                // - Rental status là Ongoing (đã được admin verify)
                                // - Rental status KHÔNG phải Cancelled
                                if ($payment 
                                    && $payment['payment_method'] === 'VNPayQR' 
                                    && $payment['status'] === 'Pending'
                                    && $rental['status'] === 'Ongoing'
                                    && $rental['status'] !== 'Cancelled'
                                ):
                                ?>
                                    <a href="payment-page.php?rental_id=<?= $rental['rental_id'] ?>" class="btn btn-success">
                                        <i class="fas fa-qrcode"></i> Thanh toán ngay
                                    </a>
                                <?php elseif ($payment 
                                    && $payment['payment_method'] === 'VNPayQR' 
                                    && $payment['status'] === 'Pending'
                                    && $rental['status'] === 'Pending'
                                ): ?>
                                    <div style="padding: 12px; background: #fef3c7; border-radius: 10px; font-size: 13px; color: #92400e; text-align: center;">
                                        <i class="fas fa-clock"></i> Đợi admin xác nhận để thanh toán
                                    </div>
                                <?php endif; ?>
                                
                                <?php 
                                // ✅ Chỉ hiển thị nút xem hóa đơn nếu payment status = Success
                                if ($payment && $payment['status'] === 'Success'): 
                                ?>
                                    <a href="invoice.php?rental_id=<?= $rental['rental_id'] ?>" class="btn btn-secondary">
                                        <i class="fas fa-file-invoice"></i> Xem hóa đơn
                                    </a>
                                <?php endif; ?>
                                
                                <button class="btn btn-primary" onclick="viewDetails(<?= $rental['rental_id'] ?>)">
                                    <i class="fas fa-info-circle"></i> Chi tiết
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Cancel Modal -->
    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Xác nhận hủy đơn</h2>
                <button class="btn-close" onclick="closeCancelModal()">&times;</button>
            </div>
            <p style="margin-bottom: 20px; color: #666;">
                Bạn có chắc chắn muốn hủy đơn thuê này? Hành động này không thể hoàn tác.
            </p>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-secondary" style="flex: 1;" onclick="closeCancelModal()">
                    Không, giữ lại
                </button>
                <button class="btn btn-danger" style="flex: 1;" onclick="confirmCancel()">
                    <i class="fas fa-check"></i> Có, hủy đơn
                </button>
            </div>
        </div>
    </div>

    <script>
        // User dropdown
        const userBtn = document.getElementById('userBtn');
        const userDropdown = document.getElementById('userDropdown');
        
        userBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', () => {
            userDropdown?.classList.remove('show');
        });

        // Cancel rental
        let rentalIdToCancel = null;

        function cancelRental(rentalId) {
            rentalIdToCancel = rentalId;
            document.getElementById('cancelModal').classList.add('show');
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('show');
            rentalIdToCancel = null;
        }

        async function confirmCancel() {
            if (!rentalIdToCancel) return;
            
            const modal = document.getElementById('cancelModal');
            const btn = modal.querySelector('.btn-danger');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang hủy...';
            
            try {
                const response = await fetch(`api/rental-cancel.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        rental_id: rentalIdToCancel
                    })
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

        // View rental details
        function viewDetails(rentalId) {
            window.location.href = `rental-detail.php?id=${rentalId}`;
        }

        // Close modal on outside click
        document.getElementById('cancelModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'cancelModal') {
                closeCancelModal();
            }
        });

        // Auto-refresh for ongoing rentals (every 30 seconds)
        <?php if ($stats['ongoing'] > 0): ?>
        setInterval(() => {
            const currentFilter = '<?= $statusFilter ?>';
            if (currentFilter === 'all' || currentFilter === 'ongoing') {
                location.reload();
            }
        }, 30000); // 30 seconds
        <?php endif; ?>
    </script>
</body>
</html>