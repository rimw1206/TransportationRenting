<?php
/**
 * ================================================
 * public/admin/transactions.php - FIXED VERSION
 * ✅ Direct HTTP call to avoid ApiClient routing issues
 * ================================================
 */

session_start();

if (!isset($_SESSION['user']) || 
    ($_SESSION['user']['username'] !== 'admin' && 
     (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin'))) {
    header('Location: ../login.php');
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'];

// ✅ FIX: Direct HTTP call instead of ApiClient
function fetchAdminTransactions($token) {
    $url = 'http://localhost:8005/payments/transactions/admin';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log('CURL Error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    
    curl_close($ch);
    
    error_log("Admin Transactions API Response Code: " . $httpCode);
    error_log("Response Body: " . substr($response, 0, 500));
    
    if ($httpCode !== 200) {
        error_log("HTTP Error: " . $httpCode);
        return null;
    }
    
    return json_decode($response, true);
}

function fetchRental($rentalId, $token) {
    $url = "http://localhost:8003/rentals/{$rentalId}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data['data']['rental'] ?? $data['data'] ?? null;
    }
    
    return null;
}

function fetchVehicle($vehicleId) {
    $url = "http://localhost:8002/units/{$vehicleId}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data['data'] ?? null;
    }
    
    return null;
}

// Fetch all transactions
$pendingTransactions = [];

try {
    error_log("=== FETCHING ADMIN TRANSACTIONS ===");
    
    $response = fetchAdminTransactions($token);
    
    if (!$response) {
        error_log("❌ Failed to fetch transactions");
    } elseif (!$response['success']) {
        error_log("❌ API Error: " . ($response['message'] ?? 'Unknown error'));
    } else {
        $allTransactions = $response['data']['items'] ?? [];
        error_log("✅ Found " . count($allTransactions) . " transactions");
        
        foreach ($allTransactions as $txn) {
            $metadata = !empty($txn['metadata']) ? json_decode($txn['metadata'], true) : [];
            $rentalIds = $metadata['rental_ids'] ?? [];
            
            if (empty($rentalIds)) continue;
            
            $rentals = [];
            $hasActionableRental = false;
            $allRentalsCancelled = true;
            
            foreach ($rentalIds as $rentalId) {
                $rental = fetchRental($rentalId, $token);
                
                if ($rental) {
                    if (in_array($rental['status'], ['Pending', 'Ongoing'])) {
                        $hasActionableRental = true;
                    }
                    
                    if ($rental['status'] !== 'Cancelled') {
                        $allRentalsCancelled = false;
                    }
                    
                    $vehicle = fetchVehicle($rental['vehicle_id']);
                    
                    $rentals[] = [
                        'rental' => $rental,
                        'vehicle' => $vehicle
                    ];
                }
            }
            
            if ($hasActionableRental && !$allRentalsCancelled) {
                $pendingTransactions[] = [
                    'transaction' => $txn,
                    'rentals' => $rentals,
                    'rental_count' => count($rentals),
                    'metadata' => $metadata
                ];
            }
        }
        
        error_log("✅ Found " . count($pendingTransactions) . " actionable transactions");
    }
    
} catch (Exception $e) {
    error_log('❌ Exception: ' . $e->getMessage());
}

function formatDate($dateString) {
    return date('d/m/Y H:i', strtotime($dateString));
}

function getPaymentMethodBadge($method) {
    $badges = [
        'COD' => '<span class="badge" style="background:#dcfce7;color:#166534;"><i class="fas fa-money-bill-wave"></i> Tiền mặt</span>',
        'VNPayQR' => '<span class="badge" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-qrcode"></i> VNPay QR</span>'
    ];
    return $badges[$method] ?? $method;
}

function getStatusBadge($status) {
    $badges = [
        'Pending' => '<span class="badge badge-warning"><i class="fas fa-clock"></i> Chờ duyệt</span>',
        'Ongoing' => '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Đã duyệt</span>',
        'Completed' => '<span class="badge badge-completed"><i class="fas fa-check-double"></i> Hoàn thành</span>',
        'Cancelled' => '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Đã hủy</span>'
    ];
    return $badges[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Đơn Thanh Toán - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/dashboard_style.css">
    <style>
        .admin-container { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 32px;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .info-banner {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            color: #1e40af;
        }
        
        .info-banner strong { display: block; margin-bottom: 5px; }
        .info-banner ul { margin: 8px 0 0 20px; line-height: 1.6; }
        
        .transaction-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .transaction-card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .transaction-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .transaction-id {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
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
        }
        
        .cart-checkout-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .transaction-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            font-size: 13px;
            color: #666;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 15px;
            color: #1a1a1a;
            font-weight: 500;
        }
        
        .info-value.amount {
            font-size: 20px;
            color: #667eea;
            font-weight: 700;
        }
        
        .rentals-list { margin-bottom: 20px; }
        
        .rental-item {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 15px;
            align-items: center;
            transition: all 0.3s;
        }
        
        .rental-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .vehicle-image {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
        }
        
        .rental-details {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .vehicle-name {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .rental-detail-row {
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .rental-detail-row i {
            width: 16px;
            color: #667eea;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-completed { background: #dbeafe; color: #1e40af; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
        }
        
        .btn-approve {
            background: #10b981;
            color: white;
        }
        
        .btn-approve:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        
        .btn-reject:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
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
        
        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
        }
        
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
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <i class="fas fa-car"></i>
                <span>Transportation - Admin</span>
            </div>
            <div class="nav-actions">
                <a href="index.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 14px; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <div class="page-header">
            <h1>
                <i class="fas fa-receipt"></i>
                Quản lý Đơn Thanh Toán
            </h1>
        </div>
        
        <div class="info-banner">
            <strong><i class="fas fa-info-circle"></i> Lưu ý quan trọng:</strong>
            <ul>
                <li><strong>Duyệt theo Transaction:</strong> Mỗi payment transaction có thể chứa nhiều rentals (từ giỏ hàng)</li>
                <li><strong>Khi duyệt:</strong> TẤT CẢ rentals trong transaction sẽ chuyển sang "Ongoing"</li>
                <li><strong>Khi từ chối:</strong> TẤT CẢ rentals trong transaction sẽ bị hủy</li>
                <li><strong>COD:</strong> Tự động tạo orders sau khi duyệt</li>
                <li><strong>VNPayQR:</strong> Chờ user thanh toán trước khi tạo orders</li>
            </ul>
        </div>
        
        <?php if (empty($pendingTransactions)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Không có đơn nào cần duyệt</h3>
                <p>Tất cả các đơn thanh toán đã được xử lý</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingTransactions as $item): 
                $txn = $item['transaction'];
                $rentals = $item['rentals'];
                $metadata = $item['metadata'];
                $rentalCount = $item['rental_count'];
                
                $originalAmount = $metadata['original_amount'] ?? $txn['amount'];
                $discountAmount = $metadata['discount_amount'] ?? 0;
                $promoCode = $metadata['promo_code'] ?? null;
                $isCartCheckout = $metadata['cart_checkout'] ?? false;
            ?>
            <div class="transaction-card">
                <div class="transaction-header">
                    <div>
                        <div class="transaction-id">
                            <i class="fas fa-receipt"></i>
                            Mã GD: <?= htmlspecialchars($txn['transaction_code']) ?>
                            <span class="vehicle-count-badge">
                                <i class="fas fa-car"></i> <?= $rentalCount ?> xe
                            </span>
                            <?php if ($isCartCheckout): ?>
                                <span class="cart-checkout-badge">
                                    <i class="fas fa-shopping-cart"></i> Thanh toán gộp
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 8px; font-size: 14px; color: #666;">
                            <i class="far fa-calendar"></i> <?= formatDate($txn['transaction_date']) ?>
                        </div>
                    </div>
                    <div>
                        <?= getPaymentMethodBadge($txn['payment_method']) ?>
                    </div>
                </div>
                
                <div class="transaction-info">
                    <div class="info-item">
                        <div class="info-label">Khách hàng</div>
                        <div class="info-value">User #<?= $txn['user_id'] ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Tổng tiền gốc</div>
                        <div class="info-value"><?= number_format($originalAmount) ?>đ</div>
                    </div>
                    
                    <?php if ($discountAmount > 0): ?>
                    <div class="info-item">
                        <div class="info-label">Giảm giá</div>
                        <div class="info-value" style="color: #059669;">
                            -<?= number_format($discountAmount) ?>đ
                            <?php if ($promoCode): ?>
                                <span class="promo-badge">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($promoCode) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <div class="info-label">Tổng thanh toán</div>
                        <div class="info-value amount"><?= number_format($txn['amount']) ?>đ</div>
                    </div>
                </div>
                
                <div class="rentals-list">
                    <h4 style="margin-bottom: 15px; color: #1a1a1a;">
                        <i class="fas fa-car"></i> Chi tiết xe thuê (<?= count($rentals) ?>)
                    </h4>
                    
                    <?php foreach ($rentals as $rentalItem): 
                        $rental = $rentalItem['rental'];
                        $vehicle = $rentalItem['vehicle'];
                    ?>
                    <div class="rental-item">
                        <div class="vehicle-image">
                            <i class="fas fa-car"></i>
                        </div>
                        
                        <div class="rental-details">
                            <div class="vehicle-name">
                                <?php if ($vehicle): ?>
                                    <?= htmlspecialchars($vehicle['catalog']['brand'] . ' ' . $vehicle['catalog']['model']) ?>
                                    <small style="font-weight: normal; color: #666;">
                                        (<?= htmlspecialchars($vehicle['license_plate']) ?>)
                                    </small>
                                <?php else: ?>
                                    Xe #<?= $rental['vehicle_id'] ?>
                                <?php endif; ?>
                                <?= getStatusBadge($rental['status']) ?>
                            </div>
                            
                            <div class="rental-detail-row">
                                <i class="fas fa-calendar"></i>
                                <?= formatDate($rental['start_time']) ?> → <?= formatDate($rental['end_time']) ?>
                            </div>
                            
                            <div class="rental-detail-row">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($rental['pickup_location']) ?>
                            </div>
                            
                            <div class="rental-detail-row">
                                <i class="fas fa-dollar-sign"></i>
                                <strong><?= number_format($rental['total_cost']) ?>đ</strong>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-approve" onclick="verifyTransaction(<?= $txn['transaction_id'] ?>, 'approve')">
                        <i class="fas fa-check"></i> Duyệt toàn bộ (<?= $rentalCount ?> xe)
                    </button>
                    <button class="btn btn-reject" onclick="verifyTransaction(<?= $txn['transaction_id'] ?>, 'reject')">
                        <i class="fas fa-times"></i> Từ chối
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        async function verifyTransaction(transactionId, action) {
            const actionText = action === 'approve' ? 'duyệt' : 'từ chối';
            
            if (!confirm(`Bạn có chắc muốn ${actionText} TOÀN BỘ ĐƠN này?\n\nTất cả rentals trong transaction sẽ bị ảnh hưởng.`)) {
                return;
            }
            
            const btn = event.target;
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = action === 'approve' 
                ? '<i class="fas fa-spinner fa-spin"></i> Đang duyệt...'
                : '<i class="fas fa-spinner fa-spin"></i> Đang từ chối...';
            
            try {
                const response = await fetch('../api/admin-verify-transaction.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        transaction_id: transactionId,
                        action: action
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    let message = `✅ ${result.message}`;
                    
                    if (result.data) {
                        message += `\n\n📊 Chi tiết:`;
                        message += `\n• Rentals đã xử lý: ${result.data.rentals_updated || 0}`;
                        message += `\n• Payment method: ${result.data.payment_method || 'N/A'}`;
                        
                        if (result.data.orders_created) {
                            message += `\n• Orders đã tạo: ${result.data.orders_created}`;
                        } else if (result.data.payment_method === 'VNPayQR') {
                            message += `\n• Chờ user thanh toán VNPay để tạo orders`;
                        }
                    }
                    
                    alert(message);
                    location.reload();
                } else {
                    alert(`❌ ${result.message}`);
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
    </script>
</body>
</html>