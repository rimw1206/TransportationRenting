<?php
// public/cart.php - INDIVIDUAL PAYMENT METHOD PER CART ITEM (COMPLETE VERSION)
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

require_once __DIR__ . '/../shared/classes/ApiClient.php';
$apiClient = new ApiClient();
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');

// Get cart items details
$cartItems = [];
$totalAmount = 0;

foreach ($_SESSION['cart'] as $index => $cartItem) {
    try {
        $response = $apiClient->get('vehicle', '/catalogs/' . $cartItem['catalog_id']);
        if ($response['status_code'] === 200) {
            $data = json_decode($response['raw_response'], true);
            if ($data['success']) {
                $vehicle = $data['data'];
                
                // Calculate rental days
                $start = new DateTime($cartItem['start_time']);
                $end = new DateTime($cartItem['end_time']);
                $days = $end->diff($start)->days;
                if ($days < 1) $days = 1;
                
                $itemTotal = $days * $vehicle['daily_rate'] * $cartItem['quantity'];
                
                $cartItems[] = [
                    'index' => $index,
                    'cart_item' => $cartItem,
                    'vehicle' => $vehicle,
                    'days' => $days,
                    'item_total' => $itemTotal
                ];
                
                $totalAmount += $itemTotal;
            }
        }
    } catch (Exception $e) {
        error_log('Error loading cart item: ' . $e->getMessage());
    }
}

function getVehicleTypeName($type) {
    $types = ['Car' => 'Ô tô', 'Motorbike' => 'Xe máy', 'Bicycle' => 'Xe đạp', 'Electric_Scooter' => 'Xe điện'];
    return $types[$type] ?? $type;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard_style.css">
    <style>
        .cart-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .cart-item {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .cart-item-grid {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .cart-item-image {
            width: 120px;
            height: 90px;
            border-radius: 12px;
            object-fit: cover;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a1a1a;
        }
        
        .cart-item-details {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .cart-item-price {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        
        .item-price {
            font-size: 20px;
            font-weight: 700;
            color: #4F46E5;
        }
        
        .remove-btn {
            background: #fee;
            color: #c00;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .remove-btn:hover {
            background: #fcc;
        }
        
        /* Payment Method Selection per Item */
        .item-payment-section {
            border-top: 2px solid #f0f0f0;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .item-payment-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
        }
        
        .payment-method-option {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .payment-method-option:hover {
            border-color: #4F46E5;
            background: #f5f7ff;
        }
        
        .payment-method-option.selected {
            border-color: #4F46E5;
            background: #f5f7ff;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }
        
        .payment-method-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .payment-method-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }
        
        .payment-method-icon.cod {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .payment-method-icon.vnpay {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        .payment-method-info {
            flex: 1;
        }
        
        .payment-method-name {
            font-weight: 600;
            font-size: 13px;
            color: #333;
        }
        
        .payment-method-desc {
            font-size: 11px;
            color: #666;
        }
        
        .cart-summary {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 100px;
        }
        
        .summary-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .summary-total {
            display: flex;
            justify-content: space-between;
            padding: 20px 0;
            font-size: 24px;
            font-weight: 700;
            color: #4F46E5;
        }
        
        .checkout-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .checkout-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
        }
        
        .empty-cart i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .continue-shopping {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: #4F46E5;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .continue-shopping:hover {
            background: #4338CA;
        }
        
        .promo-tag {
            background: #e0e7ff;
            color: #4338ca;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .promo-tag:hover {
            background: #4F46E5;
            color: white;
            transform: scale(1.05);
        }

        .quick-promo-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
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
            margin-bottom: 15px;
        }
        
        .validation-warning {
            background: #fef3c7;
            color: #92400e;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 10px;
            display: none;
        }
        
        .validation-warning.show {
            display: block;
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
                <a href="my-rentals.php" class="nav-link">
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
                        <a href="order-tracking.php">
                            <i class="fas fa-history"></i> Lịch sử thuê
                        </a>
                        <?php if (($user['role'] ?? 'user') === 'admin'): ?>
                        <div class="dropdown-divider"></div>
                        <a href="admin/rentals.php">
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

    <!-- Cart Content -->
    <div class="cart-container">
        <h1 style="margin-bottom: 30px;">
            <i class="fas fa-shopping-cart"></i> Giỏ hàng của bạn
        </h1>

        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Giỏ hàng trống</h2>
                <p>Bạn chưa thêm xe nào vào giỏ hàng</p>
                <a href="vehicles.php" class="continue-shopping">
                    <i class="fas fa-arrow-left"></i> Tiếp tục thuê xe
                </a>
            </div>
        <?php else: ?>
            <div class="alert-info">
                <i class="fas fa-info-circle"></i>
                <span><strong>Linh hoạt thanh toán!</strong> Bạn có thể chọn phương thức thanh toán riêng cho từng xe trong giỏ hàng</span>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 380px; gap: 30px;">
                <!-- Cart Items -->
                <div>
                    <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item" data-item-index="<?= $item['index'] ?>">
                        <div class="cart-item-grid">
                            <img src="https://images.unsplash.com/photo-1590362891991-f776e747a588?w=400" 
                                 alt="<?= htmlspecialchars($item['vehicle']['brand'] . ' ' . $item['vehicle']['model']) ?>"
                                 class="cart-item-image">
                            
                            <div class="cart-item-info">
                                <div class="cart-item-title">
                                    <?= htmlspecialchars($item['vehicle']['brand'] . ' ' . $item['vehicle']['model']) ?>
                                </div>
                                
                                <div class="cart-item-details">
                                    <span><i class="fas fa-tag"></i> <?= getVehicleTypeName($item['vehicle']['type']) ?></span>
                                    <span><i class="fas fa-calendar"></i> <?= $item['days'] ?> ngày</span>
                                    <span><i class="fas fa-car"></i> x<?= $item['cart_item']['quantity'] ?></span>
                                </div>
                                
                                <div style="font-size: 13px; color: #888; margin-top: 8px;">
                                    <div>Từ: <?= date('d/m/Y H:i', strtotime($item['cart_item']['start_time'])) ?></div>
                                    <div>Đến: <?= date('d/m/Y H:i', strtotime($item['cart_item']['end_time'])) ?></div>
                                    <div>Nhận tại: <?= htmlspecialchars($item['cart_item']['pickup_location']) ?></div>
                                </div>
                                
                                <div class="cart-item-price">
                                    <div class="item-price">
                                        <?= number_format($item['item_total']) ?>đ
                                    </div>
                                    <button class="remove-btn" onclick="removeFromCart(<?= $item['cart_item']['catalog_id'] ?>)">
                                        <i class="fas fa-trash"></i> Xóa
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Method Selection for This Item -->
                        <div class="item-payment-section">
                            <div class="item-payment-title">
                                <i class="fas fa-credit-card"></i>
                                Phương thức thanh toán cho xe này
                            </div>
                            
                            <div class="payment-methods-grid" id="payment-methods-<?= $item['index'] ?>">
                                <div style="grid-column: 1/-1; text-align: center; padding: 15px; color: #999;">
                                    <i class="fas fa-spinner fa-spin"></i> Đang tải...
                                </div>
                            </div>
                            
                            <div class="validation-warning" id="warning-<?= $item['index'] ?>">
                                <i class="fas fa-exclamation-triangle"></i> Vui lòng chọn phương thức thanh toán
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Cart Summary -->
                <div class="cart-summary">
                    <div class="summary-title">Tổng đơn hàng</div>
                    
                    <div class="summary-row">
                        <span>Tạm tính</span>
                        <span id="subtotal"><?= number_format($totalAmount) ?>đ</span>
                    </div>
                    
                    <!-- Promo Code Section -->
                    <div style="margin: 20px 0;">
                        <div style="display: flex; gap: 10px;">
                            <input type="text" 
                                   id="promoCode" 
                                   placeholder="Nhập mã khuyến mãi"
                                   style="flex: 1; padding: 12px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px;">
                            <button onclick="applyPromoCode()" 
                                    id="applyPromoBtn"
                                    style="padding: 12px 20px; background: #4F46E5; color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600;">
                                Áp dụng
                            </button>
                        </div>
                        <div id="promoMessage" style="margin-top: 10px; font-size: 13px;"></div>
                        
                        <!-- Applied Promo Display -->
                        <div id="appliedPromo" style="display: none; margin-top: 15px; padding: 12px; background: #d1fae5; border-radius: 10px; color: #065f46;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>
                                    <i class="fas fa-tag"></i> 
                                    <strong id="appliedPromoCode"></strong> (-<span id="appliedPromoPercent"></span>%)
                                </span>
                                <button onclick="removePromoCode()" 
                                        style="background: none; border: none; color: #065f46; cursor: pointer; font-size: 18px;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="summary-row" id="discountRow" style="display: none; color: #059669;">
                        <span>Giảm giá (<span id="discountPercent">0</span>%)</span>
                        <span id="discountAmount">-0đ</span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Phí dịch vụ</span>
                        <span>0đ</span>
                    </div>
                    
                    <div class="summary-total">
                        <span>Tổng cộng</span>
                        <span id="finalTotal"><?= number_format($totalAmount) ?>đ</span>
                    </div>
                    
                    <button class="checkout-btn" onclick="proceedCheckout()" id="checkoutBtn">
                        <i class="fas fa-check-circle"></i> Tiến hành đặt xe
                    </button>
                    
                    <a href="vehicles.php" style="display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Tiếp tục thuê xe
                    </a>
                    
                    <!-- Quick Promo Links -->
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 10px;">
                            <i class="fas fa-gift"></i> Mã khuyến mãi có sẵn:
                        </div>
                        <div class="quick-promo-tags">
                            <button class="promo-tag" onclick="quickApplyPromo('FIRST20')">
                                FIRST20 (-20%)
                            </button>
                            <button class="promo-tag" onclick="quickApplyPromo('WEEK15')">
                                WEEK15 (-15%)
                            </button>
                            <button class="promo-tag" onclick="quickApplyPromo('NEW10')">
                                NEW10 (-10%)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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

        // Remove from cart
        async function removeFromCart(catalogId) {
            if (!confirm('Bạn có chắc muốn xóa xe này khỏi giỏ hàng?')) return;
            
            try {
                const response = await fetch('api/cart-remove.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({catalog_id: catalogId})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                alert('Lỗi kết nối');
            }
        }

        // Payment Method Management
        const API_BASE = '/TransportationRenting/gateway/api';
        const AUTH_TOKEN = '<?= $_SESSION["token"] ?? "" ?>';
        const cartItemPaymentMethods = {}; // Store selected payment for each item

        // Load payment methods for all items
        async function loadPaymentMethods() {
            try {
                const response = await fetch(`${API_BASE}/payment-methods`, {
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`
                    }
                });
                
                const result = await response.json();
                
                if (result.success && result.data && result.data.length > 0) {
                    const methods = result.data;
                    
                    // Render payment methods for each cart item
                    <?php foreach ($cartItems as $item): ?>
                    renderPaymentMethods(<?= $item['index'] ?>, methods);
                    <?php endforeach; ?>
                } else {
                    console.warn('No payment methods available');
                    <?php foreach ($cartItems as $item): ?>
                    document.getElementById('payment-methods-<?= $item['index'] ?>').innerHTML = `
                        <div style="grid-column: 1/-1; text-align: center; padding: 15px; background: #fee2e2; border-radius: 8px; color: #991b1b;">
                            <i class="fas fa-exclamation-circle"></i> Chưa có phương thức thanh toán. 
                            <a href="profile.php#payment" style="color: #991b1b; text-decoration: underline;">Thêm ngay</a>
                        </div>
                    `;
                    <?php endforeach; ?>
                }
            } catch (error) {
                console.error('Error loading payment methods:', error);
            }
        }

        // Render payment methods for specific item
        function renderPaymentMethods(itemIndex, methods) {
            const container = document.getElementById(`payment-methods-${itemIndex}`);
            const defaultMethod = methods.find(m => m.is_default);
            
            // Auto-select default method
            if (defaultMethod) {
                cartItemPaymentMethods[itemIndex] = defaultMethod.method_id;
            }
            
            container.innerHTML = methods.map(method => `
                <div class="payment-method-option ${method.is_default ? 'selected' : ''}" 
                    onclick="selectPaymentForItem(${itemIndex}, ${method.method_id}, event)">
                    <input type="radio" 
                        name="payment_${itemIndex}" 
                        value="${method.method_id}" 
                        ${method.is_default ? 'checked' : ''}
                        onchange="selectPaymentForItem(${itemIndex}, ${method.method_id}, event)">
                    <div class="payment-method-icon ${method.type.toLowerCase()}">
                        <i class="fas ${method.type === 'COD' ? 'fa-money-bill-wave' : 'fa-qrcode'}"></i>
                    </div>
                    <div class="payment-method-info">
                        <div class="payment-method-name">${escapeHtml(method.type === 'COD' ? 'COD' : 'VNPay QR')}</div>
                        <div class="payment-method-desc">${escapeHtml(method.type === 'COD' ? 'Tiền mặt' : 'QR Code')}</div>
                    </div>
                </div>
            `).join('');
        }

        // Select payment method for specific item
        function selectPaymentForItem(itemIndex, methodId, event) {
            event.stopPropagation();
            
            cartItemPaymentMethods[itemIndex] = methodId;
            
            // Update UI
            const container = document.getElementById(`payment-methods-${itemIndex}`);
            container.querySelectorAll('.payment-method-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // Hide warning
            document.getElementById(`warning-${itemIndex}`).classList.remove('show');
            
            console.log(`✅ Item ${itemIndex} → Payment Method ${methodId}`);
            console.log('Current selections:', cartItemPaymentMethods);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Promo code handling
        let appliedPromo = null;
        const originalTotal = <?= $totalAmount ?>;

        function quickApplyPromo(code) {
            document.getElementById('promoCode').value = code;
            applyPromoCode();
        }

        async function applyPromoCode() {
            const promoCode = document.getElementById('promoCode').value.trim().toUpperCase();
            
            if (!promoCode) {
                showPromoMessage('Vui lòng nhập mã khuyến mãi', 'error');
                return;
            }
            
            const btn = document.getElementById('applyPromoBtn');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            try {
                const response = await fetch('api/promo-validate.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ code: promoCode })
                });
                
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('Server trả về dữ liệu không hợp lệ');
                }
                
                if (result.success) {
                    appliedPromo = {
                        code: promoCode,
                        discount: parseFloat(result.discount)
                    };
                    
                    updateCartTotals();
                    showAppliedPromo();
                    showPromoMessage(`Đã áp dụng mã ${promoCode} (-${result.discount}%)`, 'success');
                    document.getElementById('promoCode').value = '';
                } else {
                    showPromoMessage(result.message || 'Mã không hợp lệ', 'error');
                }
            } catch (error) {
                showPromoMessage('Lỗi kết nối: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }

        function removePromoCode() {
            appliedPromo = null;
            updateCartTotals();
            hideAppliedPromo();
            showPromoMessage('Đã xóa mã khuyến mãi', 'info');
        }

        function updateCartTotals() {
            const subtotal = originalTotal;
            let discount = 0;
            let finalTotal = subtotal;
            
            if (appliedPromo) {
                discount = Math.round(subtotal * appliedPromo.discount / 100);
                finalTotal = subtotal - discount;
                
                document.getElementById('discountRow').style.display = 'flex';
                document.getElementById('discountPercent').textContent = appliedPromo.discount;
                document.getElementById('discountAmount').textContent = '-' + discount.toLocaleString('vi-VN') + 'đ';
            } else {
                document.getElementById('discountRow').style.display = 'none';
            }
            
            document.getElementById('finalTotal').textContent = finalTotal.toLocaleString('vi-VN') + 'đ';
        }

        function showAppliedPromo() {
            const badge = document.getElementById('appliedPromo');
            badge.style.display = 'block';
            document.getElementById('appliedPromoCode').textContent = appliedPromo.code;
            document.getElementById('appliedPromoPercent').textContent = appliedPromo.discount;
        }

        function hideAppliedPromo() {
            document.getElementById('appliedPromo').style.display = 'none';
        }

        function showPromoMessage(message, type) {
            const msgDiv = document.getElementById('promoMessage');
            const colors = {
                success: '#059669',
                error: '#DC2626',
                info: '#0284c7'
            };
            
            msgDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}`;
            msgDiv.style.color = colors[type] || colors.info;
            msgDiv.style.fontWeight = '600';
            
            setTimeout(() => {
                msgDiv.innerHTML = '';
            }, 5000);
        }

        // ✅ CHECKOUT with individual payment methods
        async function proceedCheckout() {
            // Validate all items have payment method selected
            const cartIndexes = <?= json_encode(array_column($cartItems, 'index')) ?>;
            let allValid = true;
            let invalidItems = [];
            
            for (const index of cartIndexes) {
                if (!cartItemPaymentMethods[index]) {
                    document.getElementById(`warning-${index}`).classList.add('show');
                    allValid = false;
                    invalidItems.push(index + 1);
                }
            }
            
            if (!allValid) {
                alert(`❌ Vui lòng chọn phương thức thanh toán cho xe #${invalidItems.join(', #')}!`);
                // Scroll to first invalid item
                const firstInvalid = cartIndexes.find(idx => !cartItemPaymentMethods[idx]);
                if (firstInvalid !== undefined) {
                    document.querySelector(`[data-item-index="${firstInvalid}"]`).scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                }
                return;
            }
            
            // Count unique payment methods
            const uniqueMethods = new Set(Object.values(cartItemPaymentMethods));
            const confirmMsg = uniqueMethods.size > 1 
                ? `Bạn đang sử dụng ${uniqueMethods.size} phương thức thanh toán khác nhau.\nXác nhận đặt tất cả xe?`
                : 'Xác nhận đặt tất cả xe trong giỏ hàng?';
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            const btn = document.getElementById('checkoutBtn');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
            
            try {
                console.log('🛒 Checkout with individual payment methods:', {
                    promo: appliedPromo,
                    item_payments: cartItemPaymentMethods
                });
                
                const response = await fetch('api/cart-checkout-individual.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        promo_code: appliedPromo ? appliedPromo.code : null,
                        item_payment_methods: cartItemPaymentMethods
                    })
                });
                
                const result = await response.json();
                console.log('📦 Checkout result:', result);
                
                if (result.success) {
                    let successMsg = '✅ Đặt xe thành công!';
                    
                    if (result.data) {
                        successMsg += `\n\n📊 Tổng số đơn: ${result.data.rentals.length}`;
                        
                        if (result.data.promo_applied) {
                            successMsg += `\n🎁 Mã KM: ${result.data.promo_applied.code}`;
                            successMsg += `\n💰 Tiết kiệm: ${result.data.total_discount.toLocaleString('vi-VN')}đ`;
                        }
                        
                        successMsg += `\n\n💵 Tổng thanh toán: ${result.data.total_final.toLocaleString('vi-VN')}đ`;
                        successMsg += `\n\n💳 Mỗi xe đã được gán phương thức thanh toán riêng`;
                    }
                    
                    if (result.warnings && result.warnings.length > 0) {
                        successMsg += '\n\n⚠️ Lưu ý:\n' + result.warnings.join('\n');
                    }
                    
                    alert(successMsg);
                    window.location.href = 'my-rentals.php';
                } else {
                    alert('❌ ' + (result.message || 'Có lỗi xảy ra'));
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            } catch (error) {
                console.error('❌ Checkout error:', error);
                alert('❌ Lỗi kết nối: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }

        // Load payment methods on page load
        window.addEventListener('DOMContentLoaded', () => {
            loadPaymentMethods();
            
            // Auto-apply promo if pending
            const pendingPromo = sessionStorage.getItem('pendingPromo');
            if (pendingPromo) {
                console.log('🎁 Auto-applying pending promo:', pendingPromo);
                document.getElementById('promoCode').value = pendingPromo;
                sessionStorage.removeItem('pendingPromo');
                setTimeout(() => applyPromoCode(), 500);
            }
        });
    </script>
</body>
</html>