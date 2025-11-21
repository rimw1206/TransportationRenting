<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$catalogId = $_GET['id'] ?? null;

if (!$catalogId) {
    header('Location: vehicles.php');
    exit;
}

require_once __DIR__ . '/../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');
$apiClient->setServiceUrl('rental', 'http://localhost:8003');

// Fetch catalog details
$vehicle = null;
$error = null;

try {
    $response = $apiClient->get('vehicle', '/catalogs/' . $catalogId);
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['raw_response'], true);
        if ($data['success']) {
            $vehicle = $data['data'];
        }
    }
    
    if (!$vehicle) {
        $error = 'Không tìm thấy xe';
    }
} catch (Exception $e) {
    $error = 'Lỗi khi tải thông tin xe';
    error_log('Error: ' . $e->getMessage());
}

function getVehicleTypeName($type) {
    return ['Car' => 'Ô tô', 'Motorbike' => 'Xe máy', 'Bicycle' => 'Xe đạp', 'Electric_Scooter' => 'Xe điện'][$type] ?? $type;
}

function getVehicleImage($vehicle) {
    $type = strtolower($vehicle['type']);
    return [
        'car' => 'https://images.unsplash.com/photo-1590362891991-f776e747a588?w=800',
        'motorbike' => 'https://images.unsplash.com/photo-1558981852-426c6c22a060?w=800',
        'bicycle' => 'https://images.unsplash.com/photo-1571068316344-75bc76f77890?w=800',
        'electric_scooter' => 'https://images.unsplash.com/photo-1559311394-2e5e5e98aa6e?w=800'
    ][$type] ?? 'https://images.unsplash.com/photo-1590362891991-f776e747a588?w=800';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $vehicle ? htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) : 'Chi tiết xe' ?> - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard_style.css">
    <style>
        .vehicle-detail-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4F46E5;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .back-link:hover {
            color: #4338CA;
        }

        .vehicle-detail-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            margin-top: 20px;
        }

        .vehicle-hero-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .vehicle-header {
            margin-bottom: 30px;
        }

        .vehicle-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .vehicle-meta {
            display: flex;
            gap: 20px;
            color: #666;
        }

        .vehicle-specs {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 30px 0;
        }

        .spec-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .spec-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            font-size: 20px;
        }

        .spec-info h4 {
            margin: 0 0 5px 0;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        .spec-info p {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .booking-sidebar {
            position: sticky;
            top: 90px;
            height: fit-content;
        }

        .booking-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .price-section {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
        }

        .price-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .price-amount {
            font-size: 32px;
            font-weight: 700;
            color: #4F46E5;
        }

        .price-unit {
            font-size: 16px;
            color: #666;
        }

        .booking-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select {
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4F46E5;
        }

        .availability-checker {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .availability-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 10px;
        }

        .availability-status.available {
            background: #dcfce7;
            color: #166534;
        }

        .availability-status.unavailable {
            background: #fee;
            color: #991b1b;
        }

        .availability-status.checking {
            background: #fef3c7;
            color: #92400e;
        }

        .cost-breakdown {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .cost-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .cost-row:last-child {
            border-top: 2px solid #ddd;
            margin-top: 8px;
            padding-top: 12px;
            font-weight: 700;
            font-size: 18px;
            color: #4F46E5;
        }

        .btn-book {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-book:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-book:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #fee;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        @media (max-width: 768px) {
            .vehicle-detail-grid {
                grid-template-columns: 1fr;
            }

            .booking-sidebar {
                position: static;
            }

            .vehicle-specs {
                grid-template-columns: 1fr;
            }
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
                <a href="vehicles.php" class="nav-link active">
                    <i class="fas fa-car-side"></i> Xe có sẵn
                </a>
                <a href="my-rentals.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i> Đơn của tôi
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
                        <a href="my-rentals.php"><i class="fas fa-history"></i> Lịch sử thuê</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="vehicle-detail-container">
        <a href="vehicles.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Quay lại danh sách
        </a>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif ($vehicle): ?>
            <div class="vehicle-detail-grid">
                <div class="vehicle-main-content">
                    <img src="<?= getVehicleImage($vehicle) ?>" alt="<?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?>" class="vehicle-hero-image">
                    
                    <div class="vehicle-header">
                        <h1><?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?></h1>
                        <div class="vehicle-meta">
                            <span><i class="fas fa-tag"></i> <?= getVehicleTypeName($vehicle['type']) ?></span>
                            <span>
                                <i class="fas fa-warehouse"></i> 
                                <?= $vehicle['total_units'] ?? 0 ?> xe trong kho
                            </span>
                        </div>
                    </div>

                    <div class="vehicle-specs">
                        <div class="spec-item">
                            <div class="spec-icon"><i class="fas fa-calendar"></i></div>
                            <div class="spec-info">
                                <h4>Năm sản xuất</h4>
                                <p><?= $vehicle['year'] ?></p>
                            </div>
                        </div>

                        <?php if ($vehicle['seats']): ?>
                        <div class="spec-item">
                            <div class="spec-icon"><i class="fas fa-users"></i></div>
                            <div class="spec-info">
                                <h4>Số chỗ ngồi</h4>
                                <p><?= $vehicle['seats'] ?> chỗ</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($vehicle['transmission']): ?>
                        <div class="spec-item">
                            <div class="spec-icon"><i class="fas fa-cog"></i></div>
                            <div class="spec-info">
                                <h4>Hộp số</h4>
                                <p><?= $vehicle['transmission'] ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($vehicle['fuel_type']): ?>
                        <div class="spec-item">
                            <div class="spec-icon"><i class="fas fa-gas-pump"></i></div>
                            <div class="spec-info">
                                <h4>Nhiên liệu</h4>
                                <p><?= $vehicle['fuel_type'] ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="booking-sidebar">
                    <div class="booking-card">
                        <div class="price-section">
                            <div class="price-label">Giá thuê</div>
                            <div class="price-amount">
                                <?= number_format($vehicle['daily_rate']) ?>đ
                                <span class="price-unit">/ngày</span>
                            </div>
                        </div>

                        <?php if ($vehicle['total_units'] > 0): ?>
                            <form id="bookingForm" class="booking-form">
                                <div class="form-group">
                                    <label>Số lượng xe</label>
                                    <select id="quantity" name="quantity" required>
                                        <?php for ($i = 1; $i <= min(5, $vehicle['total_units']); $i++): ?>
                                            <option value="<?= $i ?>"><?= $i ?> xe</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Ngày bắt đầu</label>
                                    <input type="datetime-local" id="startTime" name="start_time" required>
                                </div>

                                <div class="form-group">
                                    <label>Ngày kết thúc</label>
                                    <input type="datetime-local" id="endTime" name="end_time" required>
                                </div>

                                <div class="form-group">
                                    <label>Địa điểm nhận xe</label>
                                    <select id="pickupLocation" name="pickup_location" required>
                                        <option value="">Chọn địa điểm</option>
                                        <option value="Quận 1, TP.HCM">Quận 1, TP.HCM</option>
                                        <option value="Quận 3, TP.HCM">Quận 3, TP.HCM</option>
                                        <option value="Quận 5, TP.HCM">Quận 5, TP.HCM</option>
                                        <option value="Quận 7, TP.HCM">Quận 7, TP.HCM</option>
                                        <option value="Quận 10, TP.HCM">Quận 10, TP.HCM</option>
                                    </select>
                                </div>

                                <!-- Real-time Availability Checker -->
                                <div class="availability-checker">
                                    <button type="button" id="checkAvailabilityBtn" class="btn-book" style="background: #3b82f6;">
                                        <i class="fas fa-search"></i> Kiểm tra xe khả dụng
                                    </button>
                                    <div id="availabilityResult"></div>
                                </div>

                                <div id="costBreakdown" class="cost-breakdown" style="display: none;">
                                    <div class="cost-row">
                                        <span>Số ngày thuê</span>
                                        <span id="rentalDays">0</span>
                                    </div>
                                    <div class="cost-row">
                                        <span>Số lượng</span>
                                        <span id="quantityDisplay">1 xe</span>
                                    </div>
                                    <div class="cost-row">
                                        <span>Giá/xe/ngày</span>
                                        <span><?= number_format($vehicle['daily_rate']) ?>đ</span>
                                    </div>
                                    <div class="cost-row">
                                        <span>Tổng cộng</span>
                                        <span id="totalCost">0đ</span>
                                    </div>
                                </div>

                                <div id="alertBox"></div>

                                <button type="submit" class="btn-book" id="addToCartBtn" disabled>
                                    <i class="fas fa-shopping-cart"></i> Thêm vào giỏ hàng
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Tạm hết xe. Vui lòng quay lại sau.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const catalogId = <?= json_encode($catalogId) ?>;
        const dailyRate = <?= json_encode($vehicle['daily_rate'] ?? 0) ?>;
        let availableUnits = [];
        let isAvailabilityChecked = false;

        document.getElementById('userBtn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            document.getElementById('userDropdown').classList.toggle('show');
        });
        
        document.addEventListener('click', () => {
            document.getElementById('userDropdown')?.classList.remove('show');
        });

        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('startTime').min = now.toISOString().slice(0, 16);
        document.getElementById('endTime').min = now.toISOString().slice(0, 16);

        function calculateCost() {
            const startTime = document.getElementById('startTime').value;
            const endTime = document.getElementById('endTime').value;
            const quantity = parseInt(document.getElementById('quantity').value);

            if (!startTime || !endTime) {
                document.getElementById('costBreakdown').style.display = 'none';
                return;
            }

            const start = new Date(startTime);
            const end = new Date(endTime);
            const diffDays = Math.max(1, Math.ceil((end - start) / (1000 * 60 * 60 * 24)));

            const totalCost = diffDays * dailyRate * quantity;

            document.getElementById('rentalDays').textContent = diffDays + ' ngày';
            document.getElementById('quantityDisplay').textContent = quantity + ' xe';
            document.getElementById('totalCost').textContent = totalCost.toLocaleString('vi-VN') + 'đ';
            document.getElementById('costBreakdown').style.display = 'block';
        }

        document.getElementById('startTime')?.addEventListener('change', () => {
            calculateCost();
            isAvailabilityChecked = false;
            document.getElementById('addToCartBtn').disabled = true;
        });
        
        document.getElementById('endTime')?.addEventListener('change', () => {
            calculateCost();
            isAvailabilityChecked = false;
            document.getElementById('addToCartBtn').disabled = true;
        });
        
        document.getElementById('quantity')?.addEventListener('change', () => {
            calculateCost();
            isAvailabilityChecked = false;
            document.getElementById('addToCartBtn').disabled = true;
        });
        
        document.getElementById('pickupLocation')?.addEventListener('change', () => {
            isAvailabilityChecked = false;
            document.getElementById('addToCartBtn').disabled = true;
        });

        // ✅ CRITICAL: Check time-based availability
        document.getElementById('checkAvailabilityBtn')?.addEventListener('click', async () => {
            const startTime = document.getElementById('startTime').value;
            const endTime = document.getElementById('endTime').value;
            const location = document.getElementById('pickupLocation').value;
            const quantity = parseInt(document.getElementById('quantity').value);

            if (!startTime || !endTime || !location) {
                showAvailabilityResult('warning', 'Vui lòng điền đầy đủ thông tin');
                return;
            }

            const resultDiv = document.getElementById('availabilityResult');
            resultDiv.innerHTML = `
                <div class="availability-status checking">
                    <i class="fas fa-spinner fa-spin"></i>
                    Đang kiểm tra...
                </div>
            `;

            try {
                // ✅ Check availability with time range
                const response = await fetch(
                    `http://localhost:8002/services/vehicle/units/available?` +
                    `catalog_id=${catalogId}&location=${encodeURIComponent(location)}` +
                    `&start=${encodeURIComponent(startTime)}&end=${encodeURIComponent(endTime)}`
                );

                const result = await response.json();

                if (result.success) {
                    availableUnits = result.data;
                    const availableCount = availableUnits.length;

                    if (availableCount >= quantity) {
                        showAvailabilityResult('available', 
                            `✓ Có ${availableCount} xe khả dụng tại ${location} cho khung giờ này`
                        );
                        isAvailabilityChecked = true;
                        document.getElementById('addToCartBtn').disabled = false;
                    } else {
                        showAvailabilityResult('unavailable', 
                            `✗ Chỉ còn ${availableCount} xe khả dụng cho khung giờ này, bạn cần ${quantity} xe`
                        );
                        isAvailabilityChecked = false;
                        document.getElementById('addToCartBtn').disabled = true;
                    }
                } else {
                    showAvailabilityResult('unavailable', 'Không thể kiểm tra, vui lòng thử lại');
                }
            } catch (error) {
                console.error('Check availability error:', error);
                showAvailabilityResult('unavailable', 'Lỗi kết nối');
            }
        });

        function showAvailabilityResult(status, message) {
            const resultDiv = document.getElementById('availabilityResult');
            const iconClass = status === 'available' ? 'check-circle' : 
                            status === 'unavailable' ? 'times-circle' : 'exclamation-circle';
            
            resultDiv.innerHTML = `
                <div class="availability-status ${status}">
                    <i class="fas fa-${iconClass}"></i>
                    ${message}
                </div>
            `;
        }

        document.getElementById('bookingForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!isAvailabilityChecked) {
                showAlert('error', 'Vui lòng kiểm tra xe khả dụng trước');
                return;
            }

            const formData = {
                catalog_id: parseInt(catalogId),
                start_time: document.getElementById('startTime').value,
                end_time: document.getElementById('endTime').value,
                pickup_location: document.getElementById('pickupLocation').value,
                quantity: parseInt(document.getElementById('quantity').value),
                available_units: availableUnits.slice(0, parseInt(document.getElementById('quantity').value))
            };

            const submitBtn = document.getElementById('addToCartBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang thêm...';

            try {
                const response = await fetch('api/cart-add.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('success', 'Đã thêm vào giỏ hàng!');
                    updateCartBadge(result.cart_count);
                    
                    setTimeout(() => {
                        window.location.href = 'cart.php';
                    }, 1000);
                } else {
                    showAlert('error', result.message || 'Có lỗi xảy ra');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> Thêm vào giỏ hàng';
                }
            } catch (error) {
                console.error('Cart add error:', error);
                showAlert('error', 'Lỗi kết nối');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> Thêm vào giỏ hàng';
            }
        });

        function showAlert(type, message) {
            const alertBox = document.getElementById('alertBox');
            alertBox.innerHTML = `
                <div class="alert alert-${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}
                </div>
            `;
        }

        function updateCartBadge(count) {
            const cartLink = document.querySelector('a[href="cart.php"]');
            if (cartLink) {
                let badge = cartLink.querySelector('.badge');
                if (count > 0) {
                    if (badge) {
                        badge.textContent = count;
                        badge.style.display = 'inline-block';
                    } else {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'badge';
                        newBadge.textContent = count;
                        cartLink.appendChild(newBadge);
                    }
                } else if (badge) {
                    badge.style.display = 'none';
                }
            }
        }

        async function loadCartCount() {
            try {
                const response = await fetch('api/cart-count.php');
                const result = await response.json();
                if (result.success) {
                    updateCartBadge(result.count);
                }
            } catch (error) {
                console.error('Failed to load cart count:', error);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadCartCount);
        } else {
            loadCartCount();
        }
    </script>
</body>
</html>