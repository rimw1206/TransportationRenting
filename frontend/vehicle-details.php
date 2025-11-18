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

// Fetch catalog details
$vehicle = null;
$error = null;

try {
    $response = $apiClient->get('vehicle', '/' . $catalogId);
    
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
    <link rel="stylesheet" href="assets/vehicle-detail_style.css">
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

    <!-- Main Content -->
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
                <!-- Main Content -->
                <div class="vehicle-main-content">
                    <img src="<?= getVehicleImage($vehicle) ?>" alt="<?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?>" class="vehicle-hero-image">
                    
                    <div class="vehicle-content">
                        <div class="vehicle-header">
                            <div class="vehicle-title">
                                <h1><?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?></h1>
                                <div class="vehicle-meta">
                                    <span><i class="fas fa-tag"></i> <?= getVehicleTypeName($vehicle['type']) ?></span>
                                    <span><i class="fas fa-car"></i> <?= $vehicle['available_count'] ?> xe có sẵn</span>
                                </div>
                            </div>
                        </div>

                        <!-- Specs -->
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

                        <!-- Description -->
                        <div class="vehicle-description">
                            <h3>Mô tả</h3>
                            <p>
                                <?= htmlspecialchars($vehicle['description'] ?? 
                                    $vehicle['brand'] . ' ' . $vehicle['model'] . ' là lựa chọn tuyệt vời cho những chuyến đi của bạn.') ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Booking Sidebar -->
                <div class="booking-sidebar">
                    <div class="booking-card">
                        <div class="price-section">
                            <div class="price-label">Giá thuê</div>
                            <div class="price-amount">
                                <?= number_format($vehicle['daily_rate']) ?>đ
                                <span class="price-unit">/ngày</span>
                            </div>
                        </div>

                        <?php if ($vehicle['available_count'] > 0): ?>
                            <form id="bookingForm" class="booking-form">
                                <div class="form-group">
                                    <label>Số lượng xe</label>
                                    <select id="quantity" name="quantity" required>
                                        <?php for ($i = 1; $i <= min(5, $vehicle['available_count']); $i++): ?>
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

                                <button type="submit" class="btn-book">
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

        // User dropdown
        document.getElementById('userBtn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            document.getElementById('userDropdown').classList.toggle('show');
        });
        
        document.addEventListener('click', () => {
            document.getElementById('userDropdown')?.classList.remove('show');
        });

        // Set minimum date
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('startTime').min = now.toISOString().slice(0, 16);
        document.getElementById('endTime').min = now.toISOString().slice(0, 16);

        // Calculate cost
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

        document.getElementById('startTime')?.addEventListener('change', calculateCost);
        document.getElementById('endTime')?.addEventListener('change', calculateCost);
        document.getElementById('quantity')?.addEventListener('change', calculateCost);

        // Add to cart
        document.getElementById('bookingForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = {
                catalog_id: parseInt(catalogId),
                start_time: document.getElementById('startTime').value,
                end_time: document.getElementById('endTime').value,
                pickup_location: document.getElementById('pickupLocation').value,
                quantity: parseInt(document.getElementById('quantity').value)
            };

            const submitBtn = e.target.querySelector('button[type="submit"]');
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
        
        // Load cart count
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
        
        // Load on page ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadCartCount);
        } else {
            loadCartCount();
        }
    </script>
</body>
</html>