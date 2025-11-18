<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'] ?? '';

require_once __DIR__ . '/../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');

// Fetch statistics từ Vehicle Service
$vehicleStats = ['total_vehicles' => 0, 'available' => 0];
try {
    $statsResponse = $apiClient->get('vehicle', '/stats');
    
    if ($statsResponse['status_code'] === 200) {
        $statsData = json_decode($statsResponse['raw_response'], true);
        if ($statsData && isset($statsData['success']) && $statsData['success']) {
            $vehicleStats = $statsData['data'];
        }
    }
} catch (Exception $e) {
    error_log('Error fetching vehicle stats: ' . $e->getMessage());
}

// Fetch featured vehicles
$featuredVehicles = [];
try {
    $vehiclesResponse = $apiClient->get('vehicle', '/available?limit=8');
    
    if ($vehiclesResponse['status_code'] === 200) {
        $vehiclesData = json_decode($vehiclesResponse['raw_response'], true);
        if ($vehiclesData && isset($vehiclesData['success']) && $vehiclesData['success']) {
            $featuredVehicles = $vehiclesData['data'];
        }
    }
} catch (Exception $e) {
    error_log('Error fetching vehicles: ' . $e->getMessage());
}

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
        'car' => 'https://images.unsplash.com/photo-1590362891991-f776e747a588?w=400',
        'motorbike' => 'https://images.unsplash.com/photo-1558981852-426c6c22a060?w=400',
        'bicycle' => 'https://images.unsplash.com/photo-1571068316344-75bc76f77890?w=400',
        'electric_scooter' => 'https://images.unsplash.com/photo-1559311394-2e5e5e98aa6e?w=400'
    ];
    return $defaultImages[$type] ?? $defaultImages['car'];
}

function getVehicleRating($vehicle) {
    return number_format(4.5 + (rand(0, 5) / 10), 1);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard_style.css">
</head>
<body>

    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <i class="fas fa-car"></i>
                <span>Transportation</span>
            </div>
            
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link active">
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

    <!-- Main Content -->
    <main class="main-container">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-content">
                <h1>Chào mừng trở lại, <?= htmlspecialchars($user['name']) ?>! 👋</h1>
                <p>Tìm kiếm và thuê xe dễ dàng, nhanh chóng với giá tốt nhất</p>
                
                <!-- Search Box -->
                <div class="search-box">
                    <div class="search-input-group">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Tìm xe (VD: Honda, Toyota, Yamaha...)" id="searchInput">
                    </div>
                    
                    <div class="search-filters">
                        <select class="filter-select" id="typeFilter">
                            <option value="">Loại xe</option>
                            <option value="Car">Ô tô</option>
                            <option value="Motorbike">Xe máy</option>
                            <option value="Bicycle">Xe đạp</option>
                            <option value="Electric_Scooter">Xe điện</option>
                        </select>
                        
                        <select class="filter-select" id="priceFilter">
                            <option value="">Giá</option>
                            <option value="0-200000">Dưới 200k</option>
                            <option value="200000-500000">200k - 500k</option>
                            <option value="500000-1000000">500k - 1tr</option>
                            <option value="1000000+">Trên 1tr</option>
                        </select>
                        
                        <button class="btn-search" onclick="searchVehicles()">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats-section">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-car"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($vehicleStats['available'] ?? 0) ?></h3>
                    <p>Xe có sẵn</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($vehicleStats['total_vehicles'] ?? 0) ?></h3>
                    <p>Tổng xe</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-car-side"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($vehicleStats['rented'] ?? 0) ?></h3>
                    <p>Đang thuê</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="stat-info">
                    <h3>24/7</h3>
                    <p>Hỗ trợ</p>
                </div>
            </div>
        </section>

        <!-- Vehicle Categories -->
        <section class="categories-section">
            <h2><i class="fas fa-th-large"></i> Danh mục xe</h2>
            
            <div class="categories-grid">
                <a href="vehicles.php?type=Car" class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <h3>Ô tô</h3>
                    <p><?= number_format($vehicleStats['cars'] ?? 0) ?> xe có sẵn</p>
                </a>
                
                <a href="vehicles.php?type=Motorbike" class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                    <h3>Xe máy</h3>
                    <p><?= number_format($vehicleStats['motorbikes'] ?? 0) ?> xe có sẵn</p>
                </a>
                
                <a href="vehicles.php?type=Bicycle" class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-bicycle"></i>
                    </div>
                    <h3>Xe đạp</h3>
                    <p><?= number_format($vehicleStats['bicycles'] ?? 0) ?> xe có sẵn</p>
                </a>
                
                <a href="vehicles.php?type=Electric_Scooter" class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-moped"></i>
                    </div>
                    <h3>Xe điện</h3>
                    <p><?= number_format($vehicleStats['scooters'] ?? 0) ?> xe có sẵn</p>
                </a>
            </div>
        </section>

        <!-- Featured Vehicles -->
        <section class="vehicles-section">
            <div class="section-header">
                <h2><i class="fas fa-fire"></i> Xe nổi bật (<?= count($featuredVehicles) ?>)</h2>
                <a href="vehicles.php" class="view-all-link">
                    Xem tất cả <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if (empty($featuredVehicles)): ?>
                <div class="no-data">
                    <i class="fas fa-car-side"></i>
                    <p>Chưa có xe nào khả dụng.</p>
                </div>
            <?php else: ?>
                <div class="vehicles-grid">
                    <?php foreach ($featuredVehicles as $vehicle): ?>
                    <div class="vehicle-card">
                        <div class="vehicle-image">
                            <img src="<?= getVehicleImage($vehicle) ?>" alt="<?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?>">
                            <span class="vehicle-badge"><?= getVehicleTypeName($vehicle['type']) ?></span>
                        </div>
                        
                        <div class="vehicle-info">
                            <h3><?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?></h3>
                            
                            <div class="vehicle-rating">
                                <i class="fas fa-star"></i>
                                <span><?= getVehicleRating($vehicle) ?></span>
                                <span class="rating-count">(<?= rand(50, 200) ?> đánh giá)</span>
                            </div>
                            
                            <div class="vehicle-features">
                                <span><i class="fas fa-calendar"></i> <?= $vehicle['year'] ?></span>
                                <span><i class="fas fa-car"></i> <?= $vehicle['available_count'] ?> xe</span>
                            </div>
                            
                            <div class="vehicle-footer">
                                <div class="vehicle-price">
                                    <span class="price-label">Giá thuê/ngày</span>
                                    <span class="price-amount"><?= number_format($vehicle['daily_rate']) ?>đ</span>
                                </div>
                                <button class="btn-rent" onclick="rentVehicle(<?= $vehicle['catalog_id'] ?>)">
                                    <i class="fas fa-calendar-check"></i> Thuê ngay
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Promotions -->
        <section class="promotions-section">
            <h2><i class="fas fa-gift"></i> Ưu đãi đặc biệt</h2>
            
            <div class="promotions-grid">
                <div class="promo-card promo-primary">
                    <div class="promo-badge">-20%</div>
                    <i class="fas fa-gift promo-icon"></i>
                    <h3>Giảm 20% đơn đầu tiên</h3>
                    <p>Áp dụng cho tất cả loại xe</p>
                    <button class="btn-promo">Sử dụng ngay</button>
                </div>
                
                <div class="promo-card promo-secondary">
                    <div class="promo-badge">-15%</div>
                    <i class="fas fa-calendar-week promo-icon"></i>
                    <h3>Thuê tuần giảm 15%</h3>
                    <p>Thuê từ 7 ngày trở lên</p>
                    <button class="btn-promo">Chi tiết</button>
                </div>
                
                <div class="promo-card promo-tertiary">
                    <div class="promo-badge">Free</div>
                    <i class="fas fa-gas-pump promo-icon"></i>
                    <h3>Miễn phí xăng 50km</h3>
                    <p>Cho khách hàng thuê xe từ 3 ngày</p>
                    <button class="btn-promo">Tìm hiểu</button>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="features-section">
            <h2><i class="fas fa-star"></i> Tại sao chọn chúng tôi?</h2>
            
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-shield-check"></i>
                    </div>
                    <h3>Bảo hiểm toàn diện</h3>
                    <p>Tất cả xe đều có bảo hiểm</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Giao xe nhanh 30 phút</h3>
                    <p>Đặt xe online, nhận xe nhanh</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3>Thanh toán linh hoạt</h3>
                    <p>Nhiều hình thức thanh toán</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>Hỗ trợ 24/7</h3>
                    <p>Luôn sẵn sàng phục vụ</p>
                </div>
            </div>
        </section>
    </main>

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
        
        // Rent vehicle
        function rentVehicle(catalogId) {
            window.location.href = `vehicle-details.php?id=${catalogId}`;
        }
        
        // Search vehicles
        function searchVehicles() {
            const searchValue = document.getElementById('searchInput').value;
            const typeFilter = document.getElementById('typeFilter').value;
            const priceFilter = document.getElementById('priceFilter').value;
            
            let url = 'vehicles.php?';
            const params = [];
            
            if (searchValue) params.push(`search=${encodeURIComponent(searchValue)}`);
            if (typeFilter) params.push(`type=${typeFilter}`);
            if (priceFilter) params.push(`price=${priceFilter}`);
            
            window.location.href = url + params.join('&');
        }
        
        // Enter to search
        document.getElementById('searchInput')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchVehicles();
            }
        });
        
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