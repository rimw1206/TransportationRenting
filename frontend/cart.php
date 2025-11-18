<?php
// public/cart.php - SHOPPING CART PAGE
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

foreach ($_SESSION['cart'] as $cartItem) {
    try {
        $response = $apiClient->get('vehicle', '/' . $cartItem['catalog_id']);
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
        
        .cart-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
        }
        
        @media (max-width: 968px) {
            .cart-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .cart-items {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .cart-item {
            display: flex;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .cart-item:last-child {
            border-bottom: none;
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
        
        .cart-summary {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            height: fit-content;
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
        /* Payment Method Styles */
        .payment-option {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .payment-option:hover {
            border-color: #4F46E5;
            background: #f5f7ff;
        }

        .payment-option.selected {
            border-color: #4F46E5;
            background: #f5f7ff;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .payment-option input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .payment-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .payment-details {
            flex: 1;
        }

        .payment-details .name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .payment-details .number {
            font-size: 13px;
            color: #666;
        }

        .payment-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .no-payment-methods {
            text-align: center;
            padding: 30px 15px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .no-payment-methods i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }

        .no-payment-methods p {
            color: #666;
            margin-bottom: 15px;
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
                <!-- Cart Button -->
                <a href="cart.php" class="nav-icon-btn" title="Giỏ hàng" style="position: relative; text-decoration: none; color: inherit;">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                        <span class="badge"><?= count($_SESSION['cart']) ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- Notification Button -->
                <button class="nav-icon-btn" title="Thông báo">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </button>
                
                <!-- User Menu -->
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

    <!-- Cart Content -->
    <div class="cart-container">
        <h1 style="margin-bottom: 30px;">
            <i class="fas fa-shopping-cart"></i> Giỏ hàng của bạn
        </h1>

        <?php if (empty($cartItems)): ?>
            <div class="cart-items">
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h2>Giỏ hàng trống</h2>
                    <p>Bạn chưa thêm xe nào vào giỏ hàng</p>
                    <a href="vehicles.php" class="continue-shopping">
                        <i class="fas fa-arrow-left"></i> Tiếp tục thuê xe
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="cart-grid">
                <!-- Cart Items -->
                <div class="cart-items">
                    <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item" data-catalog-id="<?= $item['cart_item']['catalog_id'] ?>">
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
                            
                            <div style="font-size: 13px; color: #888;">
                                Từ: <?= date('d/m/Y H:i', strtotime($item['cart_item']['start_time'])) ?><br>
                                Đến: <?= date('d/m/Y H:i', strtotime($item['cart_item']['end_time'])) ?><br>
                                Nhận tại: <?= htmlspecialchars($item['cart_item']['pickup_location']) ?>
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
                        <div class="promo-input-group">
                            <input type="text" 
                                   id="promoCode" 
                                   placeholder="Nhập mã khuyến mãi"
                                   style="flex: 1; padding: 12px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 14px;">
                            <button onclick="applyPromoCode()" 
                                    id="applyPromoBtn"
                                    style="padding: 12px 20px; background: #4F46E5; color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; margin-left: 10px;">
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
                    
                    <button class="checkout-btn" onclick="proceedCheckout()">
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
                    <!-- Payment Method Section -->
                    <div style="margin: 25px 0; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                        <div style="font-size: 16px; font-weight: 700; margin-bottom: 15px; color: #333;">
                            <i class="fas fa-credit-card"></i> Phương thức thanh toán
                        </div>
                        
                        <div id="paymentMethodsContainer">
                            <!-- Payment methods sẽ được load ở đây -->
                            <div style="text-align: center; padding: 20px; color: #999;">
                                <i class="fas fa-spinner fa-spin"></i> Đang tải...
                            </div>
                        </div>
                        
                        <!-- Add New Payment Button -->
                        <button onclick="showAddPaymentModal()" style="
                            width: 100%;
                            padding: 12px;
                            background: white;
                            color: #4F46E5;
                            border: 2px dashed #4F46E5;
                            border-radius: 10px;
                            cursor: pointer;
                            font-weight: 600;
                            margin-top: 10px;
                            transition: all 0.3s;
                        " onmouseover="this.style.background='#f5f7ff'" onmouseout="this.style.background='white'">
                            <i class="fas fa-plus"></i> Thêm phương thức mới
                        </button>
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

        // Proceed to checkout
        async function proceedCheckout() {
            if (!confirm('Xác nhận đặt tất cả xe trong giỏ hàng?')) return;
            
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
            
            try {
                const response = await fetch('api/cart-checkout.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'}
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Đặt xe thành công!');
                    window.location.href = 'my-rentals.php';
                } else {
                    alert(result.message || 'Có lỗi xảy ra');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Tiến hành đặt xe';
                }
            } catch (error) {
                alert('Lỗi kết nối');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Tiến hành đặt xe';
            }
        }

        // Promo code handling
        // ===== THAY THẾ TOÀN BỘ PHẦN PROMO CODE TRONG <SCRIPT> =====

        // Promo Code System
        let appliedPromo = null;
        const originalTotal = <?= $totalAmount ?>;

        // Quick apply promo
        function quickApplyPromo(code) {
            document.getElementById('promoCode').value = code;
            applyPromoCode();
        }

        // Apply promo code
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
                console.log('🔍 Validating promo code:', promoCode);
                
                const response = await fetch('api/promo-validate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ code: promoCode })
                });
                
                console.log('📡 Response status:', response.status);
                console.log('📡 Response ok:', response.ok);
                
                // Get response text first
                const text = await response.text();
                console.log('📄 Raw response:', text);
                
                // Try to parse JSON
                let result;
                try {
                    result = JSON.parse(text);
                    console.log('✅ Parsed result:', result);
                } catch (e) {
                    console.error('❌ JSON parse error:', e);
                    console.error('Response was:', text);
                    throw new Error('Server trả về dữ liệu không hợp lệ: ' + text.substring(0, 100));
                }
                
                if (result.success) {
                    appliedPromo = {
                        code: promoCode,
                        discount: parseFloat(result.discount)
                    };
                    
                    console.log('✅ Promo applied:', appliedPromo);
                    
                    updateCartTotals();
                    showAppliedPromo();
                    showPromoMessage(`Đã áp dụng mã ${promoCode} (-${result.discount}%)`, 'success');
                    
                    // Clear input
                    document.getElementById('promoCode').value = '';
                } else {
                    console.warn('⚠️ Promo validation failed:', result.message);
                    showPromoMessage(result.message || 'Mã không hợp lệ', 'error');
                }
            } catch (error) {
                console.error('❌ Fetch error:', error);
                showPromoMessage('Lỗi kết nối: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }

        // Remove promo code
        function removePromoCode() {
            appliedPromo = null;
            updateCartTotals();
            hideAppliedPromo();
            showPromoMessage('Đã xóa mã khuyến mãi', 'info');
        }

        // Update cart totals with promo
        function updateCartTotals() {
            const subtotal = originalTotal;
            let discount = 0;
            let finalTotal = subtotal;
            
            if (appliedPromo) {
                discount = Math.round(subtotal * appliedPromo.discount / 100);
                finalTotal = subtotal - discount;
                
                console.log('💰 Cart totals:', {
                    subtotal: subtotal,
                    discount: discount,
                    finalTotal: finalTotal,
                    discountPercent: appliedPromo.discount
                });
                
                // Show discount row
                document.getElementById('discountRow').style.display = 'flex';
                document.getElementById('discountPercent').textContent = appliedPromo.discount;
                document.getElementById('discountAmount').textContent = '-' + discount.toLocaleString('vi-VN') + 'đ';
            } else {
                // Hide discount row
                document.getElementById('discountRow').style.display = 'none';
            }
            
            document.getElementById('finalTotal').textContent = finalTotal.toLocaleString('vi-VN') + 'đ';
        }

        // Show applied promo badge
        function showAppliedPromo() {
            const badge = document.getElementById('appliedPromo');
            badge.style.display = 'block';
            document.getElementById('appliedPromoCode').textContent = appliedPromo.code;
            document.getElementById('appliedPromoPercent').textContent = appliedPromo.discount;
        }

        // Hide applied promo badge
        function hideAppliedPromo() {
            document.getElementById('appliedPromo').style.display = 'none';
        }

        // Show promo message
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

        // Update checkout to include promo
        async function proceedCheckout() {
            if (!confirm('Xác nhận đặt tất cả xe trong giỏ hàng?')) return;
            
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
            
            try {
                console.log('🛒 Proceeding to checkout with promo:', appliedPromo);
                
                const response = await fetch('api/cart-checkout.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        promo_code: appliedPromo ? appliedPromo.code : null
                    })
                });
                
                const result = await response.json();
                console.log('📦 Checkout result:', result);
                
                if (result.success) {
                    // Show success with discount info if applicable
                    let successMsg = 'Đặt xe thành công!';
                    if (result.data && result.data.promo_applied) {
                        successMsg += `\n\nĐã áp dụng mã ${result.data.promo_applied.code}`;
                        successMsg += `\nTiết kiệm: ${result.data.total_discount.toLocaleString('vi-VN')}đ`;
                    }
                    
                    alert(successMsg);
                    window.location.href = 'my-rentals.php';
                } else {
                    alert(result.message || 'Có lỗi xảy ra');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Tiến hành đặt xe';
                }
            } catch (error) {
                console.error('❌ Checkout error:', error);
                alert('Lỗi kết nối');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Tiến hành đặt xe';
            }
        }

        // Auto-apply pending promo from sessionStorage
        window.addEventListener('DOMContentLoaded', () => {
            const pendingPromo = sessionStorage.getItem('pendingPromo');
            
            if (pendingPromo) {
                console.log('🎁 Auto-applying pending promo:', pendingPromo);
                document.getElementById('promoCode').value = pendingPromo;
                sessionStorage.removeItem('pendingPromo');
                
                // Apply after a short delay
                setTimeout(() => {
                    applyPromoCode();
                }, 500);
            }
        });
        // ===== KẾT THÚC PHẦN PROMO CODE TRONG <SCRIPT> =====
        // Load payment methods
        let selectedPaymentMethod = null;
        const API_BASE = '/TransportationRenting/gateway/api';
        const AUTH_TOKEN = '<?= $_SESSION["token"] ?? "" ?>';

        // Load payment methods
        async function loadPaymentMethods() {
            try {
                const response = await fetch(`${API_BASE}/payment-methods`, {
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`
                    }
                });
                
                const result = await response.json();
                console.log('Payment methods:', result);
                
                const container = document.getElementById('paymentMethodsContainer');
                
                if (result.success && result.data && result.data.length > 0) {
                    const methods = result.data;
                    
                    // Auto-select default method
                    const defaultMethod = methods.find(m => m.is_default);
                    if (defaultMethod) {
                        selectedPaymentMethod = defaultMethod.method_id;
                    }
                    
                    container.innerHTML = methods.map(method => `
                        <div class="payment-option ${method.is_default ? 'selected' : ''}" 
                            onclick="selectPaymentMethod(${method.method_id})">
                            <input type="radio" 
                                name="payment_method" 
                                value="${method.method_id}" 
                                ${method.is_default ? 'checked' : ''}
                                onchange="selectPaymentMethod(${method.method_id})">
                            <div class="payment-icon">
                                <i class="fas ${getPaymentIcon(method.type)}"></i>
                            </div>
                            <div class="payment-details">
                                <div class="name">${escapeHtml(method.provider)}</div>
                                <div class="number">${escapeHtml(method.account_number)}</div>
                            </div>
                            ${method.is_default ? '<span class="payment-badge">Mặc định</span>' : ''}
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="no-payment-methods">
                            <i class="fas fa-credit-card"></i>
                            <p>Chưa có phương thức thanh toán</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading payment methods:', error);
                document.getElementById('paymentMethodsContainer').innerHTML = `
                    <div style="color: #DC2626; text-align: center; padding: 15px;">
                        <i class="fas fa-exclamation-circle"></i> Không thể tải phương thức thanh toán
                    </div>
                `;
            }
        }

        // Select payment method
        function selectPaymentMethod(methodId) {
            selectedPaymentMethod = methodId;
            
            // Update UI
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            console.log('Selected payment method:', methodId);
        }

        // Get payment icon
        function getPaymentIcon(type) {
            const icons = {
                'CreditCard': 'fa-credit-card',
                'DebitCard': 'fa-credit-card',
                'EWallet': 'fa-wallet',
                'BankTransfer': 'fa-university'
            };
            return icons[type] || 'fa-money-bill';
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Show add payment modal
        function showAddPaymentModal() {
            alert('Chức năng thêm phương thức thanh toán.\n\nVui lòng vào trang Tài khoản → Thanh toán để thêm.');
            // Hoặc redirect
            // window.location.href = 'profile.php#payment';
        }

        // Update checkout to include payment method
        async function proceedCheckout() {
            // Validate payment method
            if (!selectedPaymentMethod) {
                alert('Vui lòng chọn phương thức thanh toán!');
                return;
            }
            
            if (!confirm('Xác nhận đặt tất cả xe trong giỏ hàng?')) return;
            
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
            
            try {
                console.log('🛒 Checkout with:', {
                    promo: appliedPromo,
                    payment_method: selectedPaymentMethod
                });
                
                const response = await fetch('api/cart-checkout.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        promo_code: appliedPromo ? appliedPromo.code : null,
                        payment_method_id: selectedPaymentMethod
                    })
                });
                
                const result = await response.json();
                console.log('📦 Checkout result:', result);
                
                if (result.success) {
                    let successMsg = '✅ Đặt xe thành công!';
                    
                    if (result.data) {
                        if (result.data.promo_applied) {
                            successMsg += `\n\n🎁 Đã áp dụng mã ${result.data.promo_applied.code}`;
                            successMsg += `\n💰 Tiết kiệm: ${result.data.total_discount.toLocaleString('vi-VN')}đ`;
                        }
                        
                        if (result.data.total_final) {
                            successMsg += `\n\n📊 Tổng thanh toán: ${result.data.total_final.toLocaleString('vi-VN')}đ`;
                        }
                    }
                    
                    alert(successMsg);
                    window.location.href = 'my-rentals.php';
                } else {
                    alert('❌ ' + (result.message || 'Có lỗi xảy ra'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Tiến hành đặt xe';
                }
            } catch (error) {
                console.error('❌ Checkout error:', error);
                alert('❌ Lỗi kết nối');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Tiến hành đặt xe';
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