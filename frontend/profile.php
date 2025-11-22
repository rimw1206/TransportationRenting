<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'] ?? '';

// ✅ FIX: Use Gateway API instead of direct ApiClient
$gatewayUrl = '/TransportationRenting/gateway/api';

// Fetch user profile
$profile = null;
$kycStatus = null;
$paymentMethods = [];
$rentalHistory = [];

/**
 * Helper function to call Gateway API
 */
function callGatewayAPI($endpoint, $token, $method = 'GET', $data = null) {
    global $gatewayUrl;
    
    $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $gatewayUrl . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status_code' => $httpCode,
        'raw_response' => $response,
        'data' => json_decode($response, true)
    ];
}

try {
    // Get profile
    $response = callGatewayAPI('/profile', $token);
    if ($response['status_code'] === 200) {
        $data = $response['data'];
        if ($data && isset($data['success']) && $data['success'] && isset($data['data'])) {
            $profile = $data['data'];
        } else {
            error_log("Profile fetch failed: " . ($data['message'] ?? 'Unknown error'));
        }
    } else {
        error_log("Profile API returned status: " . $response['status_code']);
        error_log("Response: " . $response['raw_response']);
    }
    
    // Get KYC status
    $response = callGatewayAPI('/kyc', $token);
    if ($response['status_code'] === 200) {
        $data = $response['data'];
        if ($data && isset($data['success']) && $data['success'] && isset($data['data'])) {
            $kycStatus = $data['data'];
        } else {
            $kycStatus = null;
            error_log("KYC not found or empty for user: " . $user['user_id']);
        }
    } else {
        error_log("KYC API returned status: " . $response['status_code']);
    }
    
    // Get payment methods - CRITICAL FIX
    $response = callGatewayAPI('/payment-methods', $token);
    error_log("Payment Methods Response: " . $response['raw_response']);
    
    if ($response['status_code'] === 200) {
        $data = $response['data'];
        if ($data && isset($data['success']) && $data['success']) {
            $paymentMethods = $data['data'] ?? [];
            error_log("✅ Found " . count($paymentMethods) . " payment methods");
        } else {
            $paymentMethods = [];
            error_log("Payment methods empty: " . json_encode($data));
        }
    } else {
        error_log("❌ Payment API error - Status: " . $response['status_code']);
        error_log("Response: " . $response['raw_response']);
        $paymentMethods = [];
    }
    
    // Get rental history
    $response = callGatewayAPI('/rental-history', $token);
    if ($response['status_code'] === 200) {
        $data = $response['data'];
        if ($data && isset($data['success']) && $data['success'] && isset($data['data'])) {
            $rentalHistory = $data['data'];
        } else {
            $rentalHistory = [];
            error_log("Rental history empty for user: " . $user['user_id']);
        }
    } else {
        error_log("Rental history API returned status: " . $response['status_code']);
    }
    
} catch (Exception $e) {
    error_log('Error fetching profile data: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
}

// Helper functions
function getStatusBadge($status) {
    $badges = [
        'Active' => '<span class="status-badge status-active">Đang hoạt động</span>',
        'Inactive' => '<span class="status-badge status-inactive">Không hoạt động</span>',
        'Pending' => '<span class="status-badge status-pending">Chờ xác nhận</span>',
        'Verified' => '<span class="status-badge status-verified">Đã xác thực</span>',
        'Rejected' => '<span class="status-badge status-rejected">Bị từ chối</span>'
    ];
    return $badges[$status] ?? $status;
}

function getPaymentIcon($type) {
    $icons = [
        'COD' => 'fa-money-bill-wave',
        'VNPayQR' => 'fa-qrcode'
    ];
    return $icons[$type] ?? 'fa-credit-card';
}

function getPaymentTypeName($type) {
    $names = [
        'COD' => 'Tiền mặt (COD)',
        'VNPayQR' => 'VNPay QR Code'
    ];
    return $names[$type] ?? $type;
}

function getPaymentTypeColor($type) {
    $colors = [
        'COD' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        'VNPayQR' => 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)'
    ];
    return $colors[$type] ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
}

// Debug output
error_log("=== PROFILE.PHP DEBUG ===");
error_log("User ID: " . ($user['user_id'] ?? 'N/A'));
error_log("Token exists: " . (!empty($token) ? 'YES' : 'NO'));
error_log("Payment Methods Count: " . count($paymentMethods));
if (!empty($paymentMethods)) {
    error_log("Payment Methods: " . json_encode($paymentMethods));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản của tôi - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard_style.css">
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid rgba(255,255,255,0.3);
            overflow: hidden;
            background: white;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .profile-meta {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .profile-meta span {
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0.9;
        }
        
        .tabs-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .tabs-nav {
            display: flex;
            border-bottom: 2px solid #f0f0f0;
            padding: 0 20px;
            gap: 10px;
        }
        
        .tab-btn {
            padding: 18px 25px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            position: relative;
            top: 2px;
        }
        
        .tab-btn:hover {
            color: #4F46E5;
        }
        
        .tab-btn.active {
            color: #4F46E5;
            border-bottom-color: #4F46E5;
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .info-item label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item .value {
            font-size: 16px;
            color: #1a1a1a;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4F46E5;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-inactive {
            background: #fee;
            color: #991b1b;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-verified {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-rejected {
            background: #fee;
            color: #991b1b;
        }
        
        .kyc-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .kyc-card h3 {
            margin-bottom: 15px;
        }
        
        .kyc-unverified {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .payment-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .payment-card:hover {
            border-color: #4F46E5;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
        }
        
        .payment-card.default {
            border-color: #4F46E5;
            background: #f5f7ff;
        }
        
        .payment-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .payment-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .payment-details h4 {
            margin-bottom: 5px;
            color: #1a1a1a;
        }
        
        .payment-details p {
            color: #666;
            font-size: 14px;
        }
        
        .payment-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-icon {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .btn-icon.btn-edit {
            background: #EEF2FF;
            color: #4F46E5;
        }
        
        .btn-icon.btn-delete {
            background: #FEE;
            color: #DC2626;
        }
        
        .btn-icon:hover {
            transform: scale(1.1);
        }
        
        .rental-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .rental-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .rental-card-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .rental-info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
        }
        
        .rental-info-item i {
            color: #4F46E5;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: #4F46E5;
            border: 2px solid #4F46E5;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-secondary:hover {
            background: #4F46E5;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
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
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            font-size: 24px;
        }
        
        .btn-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .alert-error {
            background: #fee;
            color: #991b1b;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .alert-info {
    background: #dbeafe;
    color: #1e40af;
    padding: 12px 15px;
    border-radius: 8px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 14px;
}

.alert-info i {
    margin-top: 2px;
}

.help-text {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.help-text i {
    color: #4F46E5;
}

/* Payment icon colors for COD & VNPayQR */
.payment-icon.cod {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
}

.payment-icon.vnpay {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
}
    </style>
</head>
<body>
    <!-- Top Navigation -->
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
                <a href="my-rentals.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i> Đơn của tôi
                </a>
                <a href="promotions.php" class="nav-link">
                    <i class="fas fa-gift"></i> Khuyến mãi
                </a>
            </div>
            
            <div class="nav-actions">
                <!-- Cart Button -->
                <a href="cart.php" class="nav-icon-btn" title="Giỏ hàng" style="position: relative; text-decoration: none; color: inherit;">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                        <span class="badge"><?= count($_SESSION['cart']) ?></span>
                    <?php endif; ?>
                </a>
                <!-- Notification Button -->
                <a href="notifications.php" class="nav-icon-btn" title="Thông báo" style="position: relative; text-decoration: none; color: inherit;">
                    <i class="fas fa-bell"></i>
                    <span class="badge" id="notificationBadge" style="display: none;">0</span>
                </a>
                
                <div class="user-menu">
                    <button class="user-btn" id="userBtn">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=4F46E5&color=fff" alt="Avatar">
                        <span><?= htmlspecialchars($user['name']) ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="profile.php" class="active">
                            <i class="fas fa-user"></i> Tài khoản
                        </a>
                        <a href="my-rentals.php">
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
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($profile['name'] ?? $user['name']) ?>&background=4F46E5&color=fff&size=200" alt="Avatar">
                </div>
                <div class="profile-info">
                    <h1><?= htmlspecialchars($profile['name'] ?? $user['name']) ?></h1>
                    <div class="profile-meta">
                        <span>
                            <i class="fas fa-envelope"></i>
                            <?= htmlspecialchars($profile['email'] ?? '') ?>
                        </span>
                        <span>
                            <i class="fas fa-phone"></i>
                            <?= htmlspecialchars($profile['phone'] ?? 'Chưa cập nhật') ?>
                        </span>
                        <span>
                            <?= getStatusBadge($profile['status'] ?? 'Pending') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs-nav">
                    <button class="tab-btn active" onclick="switchTab('info')">
                        <i class="fas fa-user"></i> Thông tin cá nhân
                    </button>
                    <button class="tab-btn" onclick="switchTab('kyc')">
                        <i class="fas fa-id-card"></i> Xác thực KYC
                    </button>
                    <button class="tab-btn" onclick="switchTab('payment')">
                        <i class="fas fa-credit-card"></i> Thanh toán
                    </button>
                    <button class="tab-btn" onclick="switchTab('security')">
                        <i class="fas fa-lock"></i> Bảo mật
                    </button>
                </div>

                <!-- Tab: Personal Info -->
                <div class="tab-content active" id="tab-info">
                    <div id="alert-container"></div>
                    
                    <form id="profileForm" onsubmit="updateProfile(event)">
                        <div class="info-grid">
                            <div class="form-group">
                                <label>Họ và tên</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($profile['name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Số điện thoại</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Ngày sinh</label>
                                <input type="date" name="birthdate" value="<?= htmlspecialchars($profile['birthdate'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Tên đăng nhập</label>
                                <div class="value"><?= htmlspecialchars($profile['username'] ?? '') ?></div>
                            </div>
                            
                            <div class="info-item">
                                <label>Ngày tạo tài khoản</label>
                                <div class="value">
                                    <?= isset($profile['created_at']) ? date('d/m/Y', strtotime($profile['created_at'])) : 'N/A' ?>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <label>Trạng thái tài khoản</label>
                                <div class="value"><?= getStatusBadge($profile['status'] ?? 'Pending') ?></div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Lưu thay đổi
                        </button>
                    </form>
                </div>

                <!-- Tab: KYC -->
                <div class="tab-content" id="tab-kyc">
                    <?php if ($kycStatus && $kycStatus['verification_status'] === 'Verified'): ?>
                        <div class="kyc-card">
                            <h3><i class="fas fa-check-circle"></i> Tài khoản đã được xác thực</h3>
                            <p>Số CMND/CCCD: <?= htmlspecialchars($kycStatus['identity_number'] ?? '') ?></p>
                            <p>Xác thực lúc: <?= isset($kycStatus['verified_at']) ? date('d/m/Y H:i', strtotime($kycStatus['verified_at'])) : 'N/A' ?></p>
                        </div>
                    <?php elseif ($kycStatus && $kycStatus['verification_status'] === 'Pending'): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-clock"></i>
                            <span>KYC của bạn đang được xem xét. Vui lòng chờ xác nhận.</span>
                        </div>
                    <?php else: ?>
                        <div class="kyc-card kyc-unverified">
                            <h3><i class="fas fa-exclamation-triangle"></i> Chưa xác thực tài khoản</h3>
                            <p>Vui lòng hoàn thành xác thực KYC để sử dụng đầy đủ tính năng</p>
                        </div>
                        
                        <button class="btn-primary" onclick="showKYCModal()">
                            <i class="fas fa-id-card"></i> Bắt đầu xác thực
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Tab: Payment Methods -->
                <div class="tab-content" id="tab-payment">
                    <div style="margin-bottom: 20px;">
                        <button class="btn-primary" onclick="showPaymentModal()">
                            <i class="fas fa-plus"></i> Thêm phương thức thanh toán
                        </button>
                    </div>
                    
                    <?php if (empty($paymentMethods)): ?>
                        <!-- ✅ EMPTY STATE - Hiển thị khi chưa có payment method -->
                        <div class="empty-state">
                            <i class="fas fa-credit-card"></i>
                            <h3>Chưa có phương thức thanh toán</h3>
                            <p>Thêm phương thức thanh toán để thuê xe dễ dàng hơn</p>
                        </div>
                    <?php else: ?>
                        <!-- ✅ PAYMENT CARDS - Hiển thị khi có payment methods -->
                        <?php foreach ($paymentMethods as $method): ?>
                            <div class="payment-card <?= $method['is_default'] ? 'default' : '' ?>">
                                <div class="payment-info">
                                    <div class="payment-icon" style="background: <?= getPaymentTypeColor($method['type']) ?>;">
                                        <i class="fas <?= getPaymentIcon($method['type']) ?>"></i>
                                    </div>
                                    <div class="payment-details">
                                        <h4><?= getPaymentTypeName($method['type']) ?></h4>
                                        <?php if ($method['type'] === 'VNPayQR'): ?>
                                            <p>
                                                <i class="fas fa-qrcode"></i> 
                                                Thanh toán bằng QR Code VNPay
                                            </p>
                                        <?php elseif ($method['type'] === 'COD'): ?>
                                            <p>
                                                <i class="fas fa-hand-holding-usd"></i>
                                                Thanh toán khi nhận xe
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($method['is_default']): ?>
                                            <span class="status-badge status-verified">
                                                <i class="fas fa-star"></i> Mặc định
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="payment-actions">
                                    <?php if (!$method['is_default']): ?>
                                    <button class="btn-icon btn-edit" 
                                            onclick="setDefaultPayment(<?= $method['method_id'] ?>)" 
                                            title="Đặt làm mặc định">
                                        <i class="fas fa-star"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn-icon btn-delete" 
                                            onclick="deletePayment(<?= $method['method_id'] ?>)" 
                                            title="Xóa">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Tab: Security -->
                <div class="tab-content" id="tab-security">
                    <h3 style="margin-bottom: 20px;">Đổi mật khẩu</h3>
                    
                    <form id="passwordForm" onsubmit="changePassword(event)">
                        <div class="form-group">
                            <label>Mật khẩu hiện tại</label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Mật khẩu mới</label>
                            <input type="password" name="new_password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label>Xác nhận mật khẩu mới</label>
                            <input type="password" name="confirm_password" required minlength="6">
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-key"></i> Đổi mật khẩu
                        </button>
                    </form>
                    
                    <hr style="margin: 40px 0;">
                    
                    <h3 style="margin-bottom: 20px; color: #DC2626;">Vùng nguy hiểm</h3>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Xóa tài khoản sẽ không thể khôi phục. Hãy chắc chắn trước khi thực hiện.</span>
                    </div>
                    <button class="btn-secondary" onclick="deleteAccount()" style="border-color: #DC2626; color: #DC2626;">
                        <i class="fas fa-trash"></i> Xóa tài khoản
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- KYC Modal -->
    <div class="modal" id="kycModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Xác thực tài khoản (KYC)</h2>
                <button class="btn-close" onclick="closeModal('kycModal')">&times;</button>
            </div>
            <form id="kycForm" onsubmit="submitKYC(event)">
                <div class="form-group">
                    <label>Số CMND/CCCD</label>
                    <input type="text" name="identity_number" required>
                </div>
                
                <div class="form-group">
                    <label>Ảnh mặt trước CMND/CCCD</label>
                    <input type="file" name="id_card_front" accept="image/*" required>
                </div>
                
                <div class="form-group">
                    <label>Ảnh mặt sau CMND/CCCD</label>
                    <input type="file" name="id_card_back" accept="image/*" required>
                </div>
                
                <button type="submit" class="btn-primary" style="width: 100%;">
                    <i class="fas fa-paper-plane"></i> Gửi xác thực
                </button>
            </form>
        </div>
    </div>

    <!-- Payment Method Modal -->
    <div class="modal" id="paymentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Thêm phương thức thanh toán</h2>
                <button class="btn-close" onclick="closeModal('paymentModal')">&times;</button>
            </div>
            
            <!-- Info Box -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <span>Chọn phương thức thanh toán phù hợp với bạn</span>
            </div>
            
            <form id="paymentForm" onsubmit="addPayment(event)">
                <!-- Payment Type Selection -->
                <div class="form-group">
                    <label>Phương thức thanh toán</label>
                    <select name="type" id="paymentType" required onchange="updatePaymentFields(this.value)">
                        <option value="">-- Chọn phương thức --</option>
                        <option value="COD">💵 Tiền mặt (COD)</option>
                        <option value="VNPayQR">📱 VNPay QR Code</option>
                    </select>
                    <p class="help-text">
                        <i class="fas fa-lightbulb"></i>
                        <span id="typeHelpText">Chọn cách bạn muốn thanh toán</span>
                    </p>
                </div>
                
                <!-- COD Info -->
                <div class="form-group" id="codInfoGroup" style="display: none;">
                    <div class="alert alert-warning">
                        <i class="fas fa-money-bill-wave"></i>
                        <div>
                            <strong>Thanh toán tiền mặt khi nhận xe</strong>
                            <p style="margin: 5px 0 0 0; font-size: 13px;">
                                Bạn sẽ thanh toán bằng tiền mặt trực tiếp cho nhân viên khi nhận xe
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- VNPayQR Info -->
                <div class="form-group" id="vnpayInfoGroup" style="display: none;">
                    <div class="alert alert-info">
                        <i class="fas fa-qrcode"></i>
                        <div>
                            <strong>Thanh toán bằng VNPay QR Code</strong>
                            <p style="margin: 5px 0 0 0; font-size: 13px;">
                                Mã QR sẽ được tạo tự động khi bạn thanh toán. Chỉ cần quét mã để hoàn tất.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Set as Default -->
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: normal;">
                        <input type="checkbox" name="is_default" value="1">
                        <span>Đặt làm phương thức mặc định</span>
                    </label>
                </div>
                
                <button type="submit" class="btn-primary" style="width: 100%;">
                    <i class="fas fa-plus"></i> Thêm phương thức thanh toán
                </button>
            </form>
        </div>
    </div>

   <script>
        // Base API URL
        const API_BASE = '/TransportationRenting/gateway/api';
        const AUTH_TOKEN = '<?= $token ?>';
        
        console.log('=== Profile Page Loaded ===');
        console.log('Token:', AUTH_TOKEN ? 'Present' : 'Missing');
        console.log('Payment Methods:', <?= json_encode($paymentMethods) ?>);
        
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

        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            event.target.closest('.tab-btn').classList.add('active');
            document.getElementById(`tab-${tabName}`).classList.add('active');
        }

        // Update profile
        async function updateProfile(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData);
            
            if (!AUTH_TOKEN || AUTH_TOKEN.trim() === '') {
                showAlert('error', 'Không tìm thấy token xác thực. Vui lòng đăng nhập lại.');
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE}/profile`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${AUTH_TOKEN}`
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    showAlert('success', 'Cập nhật thông tin thành công!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('error', result.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showAlert('error', 'Không thể kết nối đến server');
            }
        }

        // Show alert
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    <i class="fas ${icon}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        // Modal functions
        function showKYCModal() {
            document.getElementById('kycModal').classList.add('show');
        }

        function showPaymentModal() {
            document.getElementById('paymentModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Submit KYC
        async function submitKYC(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            try {
                const response = await fetch(`${API_BASE}/kyc`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`
                    },
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Gửi KYC thành công! Vui lòng chờ xác nhận.');
                    closeModal('kycModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert(result.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Không thể kết nối đến server');
            }
        }

        // ✅ UPDATE PAYMENT FIELDS - FOR COD & VNPayQR
        function updatePaymentFields(type) {
            const codInfoGroup = document.getElementById('codInfoGroup');
            const vnpayInfoGroup = document.getElementById('vnpayInfoGroup');
            const typeHelpText = document.getElementById('typeHelpText');
            
            // Reset all fields
            if (codInfoGroup) codInfoGroup.style.display = 'none';
            if (vnpayInfoGroup) vnpayInfoGroup.style.display = 'none';
            
            if (type === 'VNPayQR') {
                // Show VNPay info
                if (vnpayInfoGroup) vnpayInfoGroup.style.display = 'block';
                
                if (typeHelpText) {
                    typeHelpText.textContent = 'Thanh toán nhanh bằng QR Code (tự động tạo khi thanh toán)';
                }
                
            } else if (type === 'COD') {
                // Show COD info
                if (codInfoGroup) codInfoGroup.style.display = 'block';
                
                if (typeHelpText) {
                    typeHelpText.textContent = 'Thanh toán bằng tiền mặt khi nhận xe';
                }
            } else {
                if (typeHelpText) {
                    typeHelpText.textContent = 'Chọn cách bạn muốn thanh toán';
                }
            }
        }

        // ✅ ADD PAYMENT METHOD - SIMPLIFIED
        async function addPayment(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = {
                type: formData.get('type'),
                is_default: formData.has('is_default')
            };
            
            console.log('=== ADD PAYMENT METHOD ===');
            console.log('Payment type:', data.type);
            
            // Validate type
            if (!data.type) {
                alert('Vui lòng chọn phương thức thanh toán');
                return;
            }
            
            // No additional data needed for both COD and VNPayQR
            // QR code will be generated at checkout
            
            console.log('Final data to send:', data);
            
            try {
                const response = await fetch(`${API_BASE}/payment-methods`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${AUTH_TOKEN}`
                    },
                    body: JSON.stringify(data)
                });
                
                console.log('Response status:', response.status);
                
                const result = await response.json();
                console.log('Response:', result);
                
                if (result.success) {
                    alert('Thêm phương thức thanh toán thành công!');
                    closeModal('paymentModal');
                    setTimeout(() => location.reload(), 500);
                } else {
                    alert(result.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Không thể kết nối đến server: ' + error.message);
            }
        }


        // Delete payment method
        async function deletePayment(methodId) {
            if (!confirm('Bạn có chắc muốn xóa phương thức thanh toán này?')) {
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE}/payment-methods/${methodId}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Xóa thành công!');
                    location.reload();
                } else {
                    alert(result.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Không thể kết nối đến server');
            }
        }

        // Set default payment
        async function setDefaultPayment(methodId) {
            try {
                const response = await fetch(`${API_BASE}/payment-methods/${methodId}/set-default`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Đã đặt làm phương thức mặc định!');
                    location.reload();
                } else {
                    alert(result.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Không thể kết nối đến server');
            }
        }

        // Change password
        async function changePassword(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData);
            
            if (data.new_password !== data.confirm_password) {
                showAlert('error', 'Mật khẩu mới không khớp!');
                return;
            }
            
            if (data.new_password.length < 6) {
                showAlert('error', 'Mật khẩu mới phải có ít nhất 6 ký tự!');
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE}/auth/change-password`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${AUTH_TOKEN}`
                    },
                    body: JSON.stringify({
                        current_password: data.current_password,
                        new_password: data.new_password
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Đổi mật khẩu thành công!');
                    event.target.reset();
                    
                    setTimeout(() => {
                        if (confirm('Mật khẩu đã được thay đổi. Bạn có muốn đăng nhập lại không?')) {
                            window.location.href = 'logout.php';
                        }
                    }, 1500);
                } else {
                    showAlert('error', result.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Không thể kết nối đến server');
            }
        }

        // Delete account
        async function deleteAccount() {
            const confirmation = prompt('Nhập "XÓA TÀI KHOẢN" để xác nhận xóa tài khoản:');
            
            if (confirmation !== 'XÓA TÀI KHOẢN') {
                alert('Xác nhận không đúng. Hủy thao tác.');
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE}/profile`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Tài khoản đã được xóa. Bạn sẽ được chuyển đến trang đăng nhập.');
                    window.location.href = 'logout.php';
                } else {
                    alert(result.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Không thể kết nối đến server');
            }
        }
    </script>
</body>
</html>