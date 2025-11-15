<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'] ?? '';

// Get vehicle ID from URL
$vehicleId = $_GET['id'] ?? null;

if (!$vehicleId) {
    header('Location: vehicles.php');
    exit;
}

require_once __DIR__ . '/../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');
$apiClient->setServiceUrl('rental', 'http://localhost:8003');

// Fetch vehicle details
$vehicle = null;
$error = null;

try {
    $response = $apiClient->get('vehicle', '/' . $vehicleId);
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['raw_response'], true);
        if ($data['success']) {
            $vehicle = $data['data']['vehicle'] ?? null;
        }
    }
    
    if (!$vehicle) {
        $error = 'Không tìm thấy xe';
    }
} catch (Exception $e) {
    $error = 'Lỗi khi tải thông tin xe';
    error_log('Error fetching vehicle: ' . $e->getMessage());
}

// Helper functions
function getVehicleTypeName($type) {
    $types = [
        'Car' => 'Ô tô',
        'Motorbike' => 'Xe máy',
        'Bicycle' => 'Xe đạp',
        'Electric_Scooter' => 'Xe điện'
    ];
    return $types[$type] ?? $type;
}

function getVehicleImage($vehicle) {
    $type = strtolower($vehicle['type']);
    $defaultImages = [
        'car' => 'https://images.unsplash.com/photo-1590362891991-f776e747a588?w=800',
        'motorbike' => 'https://images.unsplash.com/photo-1558981852-426c6c22a060?w=800',
        'bicycle' => 'https://images.unsplash.com/photo-1571068316344-75bc76f77890?w=800',
        'electric_scooter' => 'https://images.unsplash.com/photo-1559311394-2e5e5e98aa6e?w=800'
    ];
    return $defaultImages[$type] ?? $defaultImages['car'];
}

function getStatusBadgeClass($status) {
    $classes = [
        'Available' => 'status-available',
        'Rented' => 'status-rented',
        'Maintenance' => 'status-maintenance',
        'Retired' => 'status-retired'
    ];
    return $classes[$status] ?? '';
}

function getStatusText($status) {
    $texts = [
        'Available' => 'Có sẵn',
        'Rented' => 'Đang cho thuê',
        'Maintenance' => 'Bảo trì',
        'Retired' => 'Ngừng hoạt động'
    ];
    return $texts[$status] ?? $status;
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
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: #4F46E5;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .back-link i {
            margin-right: 8px;
        }
        
        .vehicle-detail-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            margin-top: 20px;
        }
        
        @media (max-width: 992px) {
            .vehicle-detail-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .vehicle-main-content {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .vehicle-hero-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
        }
        
        .vehicle-content {
            padding: 30px;
        }
        
        .vehicle-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }
        
        .vehicle-title h1 {
            font-size: 32px;
            margin: 0 0 10px 0;
            color: #1a1a1a;
        }
        
        .vehicle-meta {
            display: flex;
            gap: 15px;
            align-items: center;
            color: #666;
            font-size: 14px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .status-available {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .status-rented {
            background: #FEE2E2;
            color: #991B1B;
        }
        
        .status-maintenance {
            background: #FEF3C7;
            color: #92400E;
        }
        
        .vehicle-specs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
            padding: 20px;
            background: #F9FAFB;
            border-radius: 8px;
        }
        
        .spec-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .spec-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 8px;
            color: #4F46E5;
        }
        
        .spec-info h4 {
            margin: 0;
            font-size: 13px;
            color: #666;
            font-weight: 400;
        }
        
        .spec-info p {
            margin: 4px 0 0 0;
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .vehicle-description {
            margin: 30px 0;
        }
        
        .vehicle-description h3 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #1a1a1a;
        }
        
        .vehicle-description p {
            color: #666;
            line-height: 1.6;
        }
        
        .booking-sidebar {
            position: sticky;
            top: 20px;
        }
        
        .booking-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        }
        
        .price-section {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #E5E7EB;
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
            font-weight: 400;
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
        }
        
        .form-group label {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #374151;
        }
        
        .form-group input,
        .form-group select {
            padding: 12px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4F46E5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .cost-breakdown {
            background: #F9FAFB;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .cost-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .cost-row:last-child {
            margin-bottom: 0;
            padding-top: 10px;
            border-top: 1px solid #E5E7EB;
            font-weight: 600;
            font-size: 16px;
        }
        
        .btn-book {
            width: 100%;
            padding: 14px;
            background: #4F46E5;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-book:hover {
            background: #4338CA;
        }
        
        .btn-book:disabled {
            background: #9CA3AF;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
        }
        
        .alert-warning {
            background: #FEF3C7;
            color: #92400E;
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
                                    <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($vehicle['location'] ?? 'TP.HCM') ?></span>
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($vehicle['license_plate']) ?></span>
                                </div>
                            </div>
                            <span class="status-badge <?= getStatusBadgeClass($vehicle['status']) ?>">
                                <?= getStatusText($vehicle['status']) ?>
                            </span>
                        </div>

                        <!-- Specs -->
                        <div class="vehicle-specs">
                            <div class="spec-item">
                                <div class="spec-icon">
                                    <i class="fas fa-car"></i>
                                </div>
                                <div class="spec-info">
                                    <h4>Loại xe</h4>
                                    <p><?= getVehicleTypeName($vehicle['type']) ?></p>
                                </div>
                            </div>

                            <div class="spec-item">
                                <div class="spec-icon">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="spec-info">
                                    <h4>Năm đăng ký</h4>
                                    <p><?= $vehicle['registration_date'] ? date('Y', strtotime($vehicle['registration_date'])) : 'N/A' ?></p>
                                </div>
                            </div>

                            <div class="spec-item">
                                <div class="spec-icon">
                                    <i class="fas fa-tachometer-alt"></i>
                                </div>
                                <div class="spec-info">
                                    <h4>Số km đã đi</h4>
                                    <p><?= number_format($vehicle['odo_km']) ?> km</p>
                                </div>
                            </div>

                            <?php if ($vehicle['type'] === 'Car'): ?>
                            <div class="spec-item">
                                <div class="spec-icon">
                                    <i class="fas fa-gas-pump"></i>
                                </div>
                                <div class="spec-info">
                                    <h4>Nhiên liệu</h4>
                                    <p><?= number_format($vehicle['fuel_level']) ?>%</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Description -->
                        <div class="vehicle-description">
                            <h3>Mô tả</h3>
                            <p>
                                <?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?> là lựa chọn tuyệt vời 
                                cho những chuyến đi của bạn. Xe được bảo dưỡng định kỳ, đảm bảo an toàn và chất lượng tốt nhất.
                                <?php if ($vehicle['type'] === 'Car'): ?>
                                    Xe ô tô rộng rãi, tiện nghi, phù hợp cho gia đình hoặc nhóm bạn.
                                <?php elseif ($vehicle['type'] === 'Motorbike'): ?>
                                    Xe máy linh hoạt, tiết kiệm nhiên liệu, dễ dàng di chuyển trong thành phố.
                                <?php endif; ?>
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

                        <?php if ($vehicle['status'] === 'Available'): ?>
                            <form id="bookingForm" class="booking-form">
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
                                        <span>Giá/ngày</span>
                                        <span><?= number_format($vehicle['daily_rate']) ?>đ</span>
                                    </div>
                                    <div class="cost-row">
                                        <span>Tổng cộng</span>
                                        <span id="totalCost">0đ</span>
                                    </div>
                                </div>

                                <div id="alertBox"></div>

                                <button type="submit" class="btn-book">
                                    <i class="fas fa-calendar-check"></i> Đặt xe ngay
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Xe này hiện không khả dụng để thuê.
                            </div>
                            <a href="vehicles.php" class="btn-book" style="display: block; text-align: center; text-decoration: none;">
                                Xem xe khác
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const vehicleId = <?= json_encode($vehicleId) ?>;
        const dailyRate = <?= json_encode($vehicle['daily_rate'] ?? 0) ?>;
        const token = '<?= $token ?>';

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

        // Set minimum date to now
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('startTime').min = now.toISOString().slice(0, 16);
        document.getElementById('endTime').min = now.toISOString().slice(0, 16);

        // Calculate cost when dates change
        function calculateCost() {
            const startTime = document.getElementById('startTime').value;
            const endTime = document.getElementById('endTime').value;

            if (!startTime || !endTime) {
                document.getElementById('costBreakdown').style.display = 'none';
                return;
            }

            const start = new Date(startTime);
            const end = new Date(endTime);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays < 1) {
                document.getElementById('costBreakdown').style.display = 'none';
                return;
            }

            const totalCost = diffDays * dailyRate;

            document.getElementById('rentalDays').textContent = diffDays + ' ngày';
            document.getElementById('totalCost').textContent = totalCost.toLocaleString('vi-VN') + 'đ';
            document.getElementById('costBreakdown').style.display = 'block';
        }

        document.getElementById('startTime')?.addEventListener('change', calculateCost);
        document.getElementById('endTime')?.addEventListener('change', calculateCost);

        // Handle booking form submission
        document.getElementById('bookingForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = {
                vehicle_id: parseInt(vehicleId),
                start_time: document.getElementById('startTime').value,
                end_time: document.getElementById('endTime').value,
                pickup_location: document.getElementById('pickupLocation').value
            };

            // Validate
            if (new Date(formData.end_time) <= new Date(formData.start_time)) {
                showAlert('error', 'Ngày kết thúc phải sau ngày bắt đầu');
                return;
            }

            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';

            try {
                const response = await fetch('/TransportationRenting/gateway/api/rentals', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('success', 'Đặt xe thành công! Đang chuyển hướng...');
                    setTimeout(() => {
                        window.location.href = 'my-rentals.php';
                    }, 1500);
                } else {
                    showAlert('error', result.message || 'Có lỗi xảy ra');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Đặt xe ngay';
                }
            } catch (error) {
                showAlert('error', 'Lỗi kết nối. Vui lòng thử lại');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Đặt xe ngay';
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
    </script>
</body>
</html>