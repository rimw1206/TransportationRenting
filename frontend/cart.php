<?php
// public/cart.php - SINGLE PAYMENT OPTION (Updated UI)
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
        /* [Previous CSS remains the same until payment section] */
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
        
        /* ✅ NEW: Single Payment Section */
        .payment-section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 30px 0 20px;
            text-align: center;
        }
        
        .payment-section-header h3 {
            margin: 0 0 8px 0;
            font-size: 20px;
        }
        
        .payment-section-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .single-payment-selector {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .payment-selector-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .payment-method-card {
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .payment-method-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .payment-method-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f5f7ff 0%, #e8eaff 100%);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .payment-method-card input[type="radio"] {
            display: none;
        }
        
        .payment-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 12px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }
        
        .payment-icon.cod {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .payment-icon.vnpay {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        .payment-name {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 5px;
            color: #1a1a1a;
        }
        
        .payment-desc {
            font-size: 12px;
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
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            padding: 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .alert-info i {
            font-size: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation (same as before) -->
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
                
                <div class="user-menu">
                    <button class="user-btn" id="userBtn">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=4F46E5&color=fff" alt="Avatar">
                        <span><?= htmlspecialchars($user['name']) ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="profile.php"><i class="fas fa-user"></i> Tài khoản</a>
                        <a href="order-tracking.php"><i class="fas fa-history"></i> Lịch sử thuê</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

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
                <i class="fas fa-check-circle"></i>
                <span><strong>Thanh toán đơn giản!</strong> Chọn một phương thức thanh toán cho tất cả xe trong giỏ hàng</span>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 380px; gap: 30px;">
                <!-- Cart Items -->
                <div>
                    <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item">
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
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- ✅ SINGLE PAYMENT SELECTION -->
                    <div class="payment-section-header">
                        <h3><i class="fas fa-credit-card"></i> Phương thức thanh toán</h3>
                        <p>Chọn một phương thức cho tất cả xe (<?= count($cartItems) ?> xe)</p>
                    </div>
                    
                    <div class="single-payment-selector">
                        <div class="payment-selector-title">
                            <i class="fas fa-wallet"></i>
                            Chọn phương thức thanh toán
                        </div>
                        
                        <div class="payment-methods-grid" id="paymentMethodsContainer">
                            <div style="grid-column: 1/-1; text-align: center; padding: 20px; color: #999;">
                                <i class="fas fa-spinner fa-spin"></i> Đang tải phương thức thanh toán...
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cart Summary -->
                <div class="cart-summary">
                    <div class="summary-title">Tổng đơn hàng</div>
                    
                    <div class="summary-row">
                        <span>Số lượng xe</span>
                        <span><?= count($cartItems) ?> xe</span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Tạm tính</span>
                        <span id="subtotal"><?= number_format($totalAmount) ?>đ</span>
                    </div>
                    
                    <!-- Promo Code (same as before) -->
                    <div style="margin: 20px 0;">
                        <div style="display: flex; gap: 10px;">
                            <input type="text" 
                                   id="promoCode" 
                                   placeholder="Nhập mã khuyến mãi"
                                   style="flex: 1; padding: 12px; border: 2px solid #e0e0e0; border-radius: 10px;">
                            <button onclick="applyPromoCode()" 
                                    id="applyPromoBtn"
                                    style="padding: 12px 20px; background: #4F46E5; color: white; border: none; border-radius: 10px; cursor: pointer;">
                                Áp dụng
                            </button>
                        </div>
                        <div id="promoMessage" style="margin-top: 10px; font-size: 13px;"></div>
                        
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
                </div>
            </div>
        <?php endif; ?>
    </div>

 <script>
        const API_BASE = '/TransportationRenting/gateway/api';
        const AUTH_TOKEN = '<?= $_SESSION["token"] ?? "" ?>';
        
        let selectedPaymentMethod = null;
        let appliedPromo = null;
        const originalTotal = <?= $totalAmount ?>;

        // Load payment methods
        async function loadPaymentMethods() {
            try {
                const response = await fetch(`${API_BASE}/payment-methods`, {
                    headers: { 'Authorization': `Bearer ${AUTH_TOKEN}` }
                });
                
                const result = await response.json();
                
                if (result.success && result.data && result.data.length > 0) {
                    renderPaymentMethods(result.data);
                } else {
                    document.getElementById('paymentMethodsContainer').innerHTML = `
                        <div style="grid-column: 1/-1; text-align: center; padding: 20px; background: #fee2e2; border-radius: 8px; color: #991b1b;">
                            <i class="fas fa-exclamation-circle"></i> Chưa có phương thức thanh toán. 
                            <a href="profile.php#payment" style="color: #991b1b; text-decoration: underline;">Thêm ngay</a>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading payment methods:', error);
            }
        }

        function renderPaymentMethods(methods) {
            const container = document.getElementById('paymentMethodsContainer');
            const defaultMethod = methods.find(m => m.is_default);
            
            if (defaultMethod) {
                selectedPaymentMethod = defaultMethod.method_id;
                console.log('✅ Default payment method selected:', selectedPaymentMethod);
            }
            
            container.innerHTML = methods.map(method => `
                <label class="payment-method-card ${method.is_default ? 'selected' : ''}" 
                       data-method-id="${method.method_id}">
                    <input type="radio" 
                           name="payment_method" 
                           value="${method.method_id}" 
                           ${method.is_default ? 'checked' : ''}
                           onchange="selectPaymentMethod(${method.method_id})">
                    <div class="payment-icon ${method.type.toLowerCase()}">
                        <i class="fas ${method.type === 'COD' ? 'fa-money-bill-wave' : 'fa-qrcode'}"></i>
                    </div>
                    <div class="payment-name">${method.type === 'COD' ? 'Tiền mặt (COD)' : 'QR VNPay'}</div>
                    <div class="payment-desc">${method.type === 'COD' ? 'Thanh toán khi nhận xe' : 'Quét mã QR thanh toán'}</div>
                </label>
            `).join('');
        }

        function selectPaymentMethod(methodId) {
            selectedPaymentMethod = methodId;
            
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            const selectedCard = document.querySelector(`[data-method-id="${methodId}"]`);
            if (selectedCard) {
                selectedCard.classList.add('selected');
            }
            
            console.log('✅ Selected payment method:', methodId);
        }

        async function proceedCheckout() {
            console.log('=== CHECKOUT START ===');
            console.log('Selected payment method:', selectedPaymentMethod);
            console.log('Applied promo:', appliedPromo); // ✅ Check promo
            
            if (!selectedPaymentMethod) {
                alert('❌ Vui lòng chọn phương thức thanh toán!');
                return;
            }
            
            if (!confirm('Xác nhận đặt tất cả xe trong giỏ hàng?')) {
                return;
            }
            
            const btn = document.getElementById('checkoutBtn');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
            
            try {
                // ✅ FIX: Send promo_code để backend tính discount
                const payload = {
                    payment_method_id: selectedPaymentMethod,
                    promo_code: appliedPromo ? appliedPromo.code : null // ✅ Gửi promo code
                };
                
                console.log('📤 Sending payload:', payload);
                
                const response = await fetch('api/cart-checkout-single-payment.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                
                console.log('📥 Response status:', response.status);
                
                const result = await response.json();
                console.log('📥 Response data:', result);
                
                if (result.success) {
                    const payment = result.data.payment;
                    const summary = result.data.summary;
                    
                    // ✅ Hiển thị thông tin discount nếu có
                    let message = `✅ Đặt xe thành công!\n\n`;
                    message += `📦 Số đơn thuê: ${summary.total_rentals}\n`;
                    message += `💳 Mã giao dịch: ${payment.transaction_code}\n`;
                    
                    if (summary.discount_amount > 0) {
                        message += `💰 Giảm giá: ${summary.discount_amount.toLocaleString('vi-VN')}đ\n`;
                    }
                    
                    message += `💰 Tổng thanh toán: ${summary.final_amount.toLocaleString('vi-VN')}đ\n`;
                    message += `📋 Trạng thái: ${payment.status}`;
                    
                    alert(message);
                    
                    // Redirect based on payment method
                    if (payment.payment_method === 'VNPayQR' && payment.qr_code_url) {
                        console.log('🔄 Redirecting to QR payment page...');
                        window.location.href = `my-rentals.php`;
                    } else {
                        console.log('🔄 Redirecting to rentals page...');
                        window.location.href = 'my-rentals.php';
                    }
                } else {
                    console.error('❌ Checkout failed:', result.message);
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


        // Promo code functions
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
                
                const result = await response.json();
                
                if (result.success) {
                    appliedPromo = {
                        code: promoCode,
                        discount: parseFloat(result.discount)
                    };
                    
                    updateCartTotals(); // ✅ Update UI
                    showAppliedPromo();
                    showPromoMessage(`✅ Áp dụng thành công! Giảm ${result.discount}%`, 'success');
                    document.getElementById('promoCode').value = '';
                    
                    console.log('✅ Promo applied:', appliedPromo);
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
            
            const subtotalEl = document.getElementById('subtotal');
            const discountRow = document.getElementById('discountRow');
            const discountPercentEl = document.getElementById('discountPercent');
            const discountAmountEl = document.getElementById('discountAmount');
            const finalTotalEl = document.getElementById('finalTotal');
            
            if (appliedPromo) {
                // ✅ Calculate discount (round down like backend)
                discount = Math.floor(subtotal * appliedPromo.discount / 100);
                finalTotal = subtotal - discount;
                
                // ✅ Show discount row
                discountRow.style.display = 'flex';
                discountPercentEl.textContent = appliedPromo.discount;
                discountAmountEl.textContent = '-' + discount.toLocaleString('vi-VN') + 'đ';
                
                // ✅ Highlight final total
                finalTotalEl.style.color = '#10b981';
                
                console.log('💰 Discount calculated:', {
                    original: subtotal,
                    discount: discount,
                    final: finalTotal,
                    percent: appliedPromo.discount
                });
            } else {
                // ✅ Hide discount row
                discountRow.style.display = 'none';
                finalTotalEl.style.color = '#667eea';
            }
            
            // ✅ Update display
            subtotalEl.textContent = subtotal.toLocaleString('vi-VN') + 'đ';
            finalTotalEl.textContent = finalTotal.toLocaleString('vi-VN') + 'đ';
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
            const colors = { success: '#059669', error: '#DC2626', info: '#0284c7' };
            const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
            
            msgDiv.innerHTML = `<i class="fas fa-${icons[type]}"></i> ${message}`;
            msgDiv.style.color = colors[type] || colors.info;
            msgDiv.style.fontWeight = '600';
            
            setTimeout(() => { msgDiv.innerHTML = ''; }, 5000);
        }

        function removeFromCart(catalogId) {
            if (confirm('Xác nhận xóa xe khỏi giỏ hàng?')) {
                window.location.href = `api/cart-remove.php?catalog_id=${catalogId}`;
            }
        }

        // Initialize
        window.addEventListener('DOMContentLoaded', () => {
            console.log('Initializing cart page...');
            loadPaymentMethods();
        });

        // User dropdown
        const userBtn = document.getElementById('userBtn');
        const userDropdown = document.getElementById('userDropdown');
        
        if (userBtn && userDropdown) {
            userBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });
            
            document.addEventListener('click', () => {
                userDropdown.classList.remove('show');
            });
        }
    </script>
</body>
</html>