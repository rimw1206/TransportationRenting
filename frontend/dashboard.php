<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'] ?? '';

// Fetch dashboard data từ các services
$dashboardData = [
    'total_rentals' => 0,
    'active_rentals' => 0,
    'total_spent' => 0,
    'available_vehicles' => 0
];

// Mock data xe nổi bật (sẽ lấy từ API sau)
$featuredVehicles = [
    [
        'id' => 1,
        'name' => 'Toyota Vios 2023',
        'type' => 'Sedan',
        'price' => 500000,
        'image' => 'https://images.unsplash.com/photo-1590362891991-f776e747a588?w=400',
        'rating' => 4.8,
        'status' => 'Available'
    ],
    [
        'id' => 2,
        'name' => 'Honda City 2023',
        'type' => 'Sedan',
        'price' => 450000,
        'image' => 'https://images.unsplash.com/photo-1583267746897-ec2e9eb70922?w=400',
        'rating' => 4.6,
        'status' => 'Available'
    ],
    [
        'id' => 3,
        'name' => 'Yamaha Exciter 155',
        'type' => 'Motorbike',
        'price' => 150000,
        'image' => 'https://images.unsplash.com/photo-1558981852-426c6c22a060?w=400',
        'rating' => 4.9,
        'status' => 'Available'
    ],
    [
        'id' => 4,
        'name' => 'Honda Wave RSX',
        'type' => 'Motorbike',
        'price' => 100000,
        'image' => 'https://images.unsplash.com/photo-1568772585407-9361f9bf3a87?w=400',
        'rating' => 4.5,
        'status' => 'Available'
    ]
];
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
                        <select class="filter-select">
                            <option value="">Loại xe</option>
                            <option value="car">Ô tô</option>
                            <option value="motorbike">Xe máy</option>
                            <option value="bicycle">Xe đạp</option>
                        </select>
                        
                        <select class="filter-select">
                            <option value="">Giá</option>
                            <option value="0-200000">Dưới 200k</option>
                            <option value="200000-500000">200k - 500k</option>
                            <option value="500000+">Trên 500k</option>
                        </select>
                        
                        <button class="btn-search">
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
                    <h3>150+</h3>
                    <p>Xe có sẵn</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>5,000+</h3>
                    <p>Khách hàng</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3>4.8/5</h3>
                    <p>Đánh giá</p>
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
                <a href="vehicles.php?type=car" class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <h3>Ô tô</h3>
                    <p>50+ xe có sẵn</p>
                </a>
                
                <a href="vehicles.php?type=motorbike" class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                    <h3>Xe máy</h3>
                    <p>80+ xe có sẵn</p>
                </a>
                
                <a href="vehicles.php?type=bicycle" class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-bicycle"></i>
                    </div>
                    <h3>Xe đạp</h3>
                    <p>20+ xe có sẵn</p>
                </a>
                
                <a href="vehicles.php?type=scooter" class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-moped"></i>
                    </div>
                    <h3>Xe điện</h3>
                    <p>15+ xe có sẵn</p>
                </a>
            </div>
        </section>

        <!-- Featured Vehicles -->
        <section class="vehicles-section">
            <div class="section-header">
                <h2><i class="fas fa-fire"></i> Xe nổi bật</h2>
                <a href="vehicles.php" class="view-all-link">
                    Xem tất cả <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="vehicles-grid">
                <?php foreach ($featuredVehicles as $vehicle): ?>
                <div class="vehicle-card">
                    <div class="vehicle-image">
                        <img src="<?= $vehicle['image'] ?>" alt="<?= htmlspecialchars($vehicle['name']) ?>">
                        <span class="vehicle-badge"><?= $vehicle['type'] ?></span>
                        <button class="favorite-btn">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                    
                    <div class="vehicle-info">
                        <h3><?= htmlspecialchars($vehicle['name']) ?></h3>
                        
                        <div class="vehicle-rating">
                            <i class="fas fa-star"></i>
                            <span><?= $vehicle['rating'] ?></span>
                            <span class="rating-count">(120 đánh giá)</span>
                        </div>
                        
                        <div class="vehicle-features">
                            <span><i class="fas fa-gas-pump"></i> Xăng</span>
                            <span><i class="fas fa-cog"></i> Số tự động</span>
                            <span><i class="fas fa-users"></i> 4 chỗ</span>
                        </div>
                        
                        <div class="vehicle-footer">
                            <div class="vehicle-price">
                                <span class="price-label">Giá thuê/ngày</span>
                                <span class="price-amount"><?= number_format($vehicle['price']) ?>đ</span>
                            </div>
                            <button class="btn-rent" onclick="rentVehicle(<?= $vehicle['id'] ?>)">
                                <i class="fas fa-calendar-check"></i> Thuê ngay
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Promotions -->
        <section class="promotions-section">
            <h2><i class="fas fa-gift"></i> Ưu đãi đặc biệt</h2>
            
            <div class="promotions-grid">
                <div class="promo-card promo-primary">
                    <div class="promo-badge">-20%</div>
                    <i class="fas fa-gift promo-icon"></i>
                    <h3>Giảm 20% đơn đầu tiên</h3>
                    <p>Áp dụng cho tất cả loại xe, không giới hạn thời gian thuê</p>
                    <button class="btn-promo">Sử dụng ngay</button>
                </div>
                
                <div class="promo-card promo-secondary">
                    <div class="promo-badge">-15%</div>
                    <i class="fas fa-calendar-week promo-icon"></i>
                    <h3>Thuê tuần giảm 15%</h3>
                    <p>Thuê từ 7 ngày trở lên nhận ngay ưu đãi</p>
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

        <!-- Why Choose Us -->
        <section class="features-section">
            <h2><i class="fas fa-star"></i> Tại sao chọn chúng tôi?</h2>
            
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-shield-check"></i>
                    </div>
                    <h3>Bảo hiểm toàn diện</h3>
                    <p>Tất cả xe đều có bảo hiểm, an tâm khi thuê</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Giao xe nhanh 30 phút</h3>
                    <p>Đặt xe online, nhận xe tại chỗ trong 30 phút</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3>Thanh toán linh hoạt</h3>
                    <p>Hỗ trợ nhiều hình thức: tiền mặt, thẻ, ví điện tử</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>Hỗ trợ 24/7</h3>
                    <p>Đội ngũ hỗ trợ luôn sẵn sàng phục vụ bạn</p>
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
        
        // Rent vehicle function
        function rentVehicle(vehicleId) {
            window.location.href = `vehicle-details.php?id=${vehicleId}`;
        }
        
        // Search function
        document.querySelector('.btn-search')?.addEventListener('click', () => {
            const searchValue = document.getElementById('searchInput').value;
            window.location.href = `vehicles.php?search=${encodeURIComponent(searchValue)}`;
        });
        
        // Enter to search
        document.getElementById('searchInput')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                document.querySelector('.btn-search').click();
            }
        });
    </script>
</body>
</html>