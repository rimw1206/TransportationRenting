<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

require_once __DIR__ . '/../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');

// Lấy tham số tìm kiếm từ URL
$searchQuery = $_GET['search'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$priceFilter = $_GET['price'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query parameters
$queryParams = [];
if ($searchQuery) $queryParams['search'] = $searchQuery;
if ($typeFilter) $queryParams['type'] = $typeFilter;
if ($priceFilter) {
    if ($priceFilter === '0-200000') {
        $queryParams['max_price'] = 200000;
    } elseif ($priceFilter === '200000-500000') {
        $queryParams['min_price'] = 200000;
        $queryParams['max_price'] = 500000;
    } elseif ($priceFilter === '500000-1000000') {
        $queryParams['min_price'] = 500000;
        $queryParams['max_price'] = 1000000;
    } elseif ($priceFilter === '1000000+') {
        $queryParams['min_price'] = 1000000;
    }
}
$queryParams['limit'] = $limit;
$queryParams['offset'] = $offset;

// Fetch vehicles
$vehicles = [];
$totalVehicles = 0;
try {
    $endpoint = '/available?' . http_build_query($queryParams);
    $response = $apiClient->get('vehicle', $endpoint);
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['raw_response'], true);
        if ($data && isset($data['success']) && $data['success']) {
            $vehicles = $data['data'];
            $totalVehicles = $data['total'] ?? count($vehicles);
        }
    }
} catch (Exception $e) {
    error_log('Error fetching vehicles: ' . $e->getMessage());
}

$totalPages = ceil($totalVehicles / $limit);

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

function buildUrl($params) {
    return 'vehicles.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tìm kiếm xe - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard_style.css">
    <link rel="stylesheet" href="assets/vehicles_style.css">
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
    <main class="main-container">
        <!-- Search Results Header -->
        <div class="search-results-header">
            <h1>
                <?php if ($searchQuery): ?>
                    Kết quả tìm kiếm: "<?= htmlspecialchars($searchQuery) ?>"
                <?php elseif ($typeFilter): ?>
                    <?= getVehicleTypeName($typeFilter) ?>
                <?php else: ?>
                    Tất cả xe có sẵn
                <?php endif; ?>
            </h1>
            
            <div class="search-info">
                <span><strong><?= number_format($totalVehicles) ?></strong> xe được tìm thấy</span>
            </div>
            
            <?php if ($searchQuery || $typeFilter || $priceFilter): ?>
            <div class="active-filters">
                <?php if ($searchQuery): ?>
                <div class="filter-tag">
                    <span>Từ khóa: <?= htmlspecialchars($searchQuery) ?></span>
                    <i class="fas fa-times" onclick="removeFilter('search')"></i>
                </div>
                <?php endif; ?>
                
                <?php if ($typeFilter): ?>
                <div class="filter-tag">
                    <span>Loại: <?= getVehicleTypeName($typeFilter) ?></span>
                    <i class="fas fa-times" onclick="removeFilter('type')"></i>
                </div>
                <?php endif; ?>
                
                <?php if ($priceFilter): ?>
                <div class="filter-tag">
                    <span>Giá: <?= htmlspecialchars($priceFilter) ?></span>
                    <i class="fas fa-times" onclick="removeFilter('price')"></i>
                </div>
                <?php endif; ?>
                
                <button class="filter-tag" onclick="clearAllFilters()" style="background: #fee; color: #c00; border: none; cursor: pointer;">
                    <i class="fas fa-trash"></i> Xóa tất cả
                </button>
            </div>
            <?php endif; ?>
        </div>

        <div class="results-container">
            <!-- Filters Sidebar -->
            <aside class="filters-sidebar">
                <h3><i class="fas fa-filter"></i> Bộ lọc</h3>
                
                <form method="GET" action="vehicles.php" id="filterForm">
                    <?php if ($searchQuery): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery) ?>">
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <label>Loại xe</label>
                        <div class="filter-options">
                            <label class="filter-option">
                                <input type="radio" name="type" value="" <?= !$typeFilter ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>Tất cả</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="type" value="Car" <?= $typeFilter === 'Car' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>Ô tô</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="type" value="Motorbike" <?= $typeFilter === 'Motorbike' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>Xe máy</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="type" value="Bicycle" <?= $typeFilter === 'Bicycle' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>Xe đạp</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="type" value="Electric_Scooter" <?= $typeFilter === 'Electric_Scooter' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>Xe điện</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>Khoảng giá (mỗi ngày)</label>
                        <div class="filter-options">
                            <label class="filter-option">
                                <input type="radio" name="price" value="" <?= !$priceFilter ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>Tất cả</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="price" value="0-200000" <?= $priceFilter === '0-200000' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>Dưới 200k</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="price" value="200000-500000" <?= $priceFilter === '200000-500000' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>200k - 500k</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="price" value="500000-1000000" <?= $priceFilter === '500000-1000000' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>500k - 1tr</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="price" value="1000000+" <?= $priceFilter === '1000000+' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span>Trên 1tr</span>
                            </label>
                        </div>
                    </div>
                </form>
            </aside>

            <!-- Results Main -->
            <div class="results-main">
                <div class="sort-bar">
                    <span style="color: #666; font-size: 14px;">
                        Hiển thị <?= count($vehicles) ?> / <?= $totalVehicles ?> xe
                    </span>
                    <select onchange="sortResults(this.value)">
                        <option value="default">Sắp xếp mặc định</option>
                        <option value="price_asc">Giá: Thấp đến cao</option>
                        <option value="price_desc">Giá: Cao đến thấp</option>
                        <option value="newest">Mới nhất</option>
                    </select>
                </div>

                <?php if (empty($vehicles)): ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h3>Không tìm thấy kết quả</h3>
                        <p>Không có xe nào phù hợp với tiêu chí tìm kiếm của bạn.</p>
                        <button onclick="clearAllFilters()" class="btn-search">
                            <i class="fas fa-redo"></i> Xóa bộ lọc
                        </button>
                    </div>
                <?php else: ?>
                    <div class="vehicles-grid">
                        <?php foreach ($vehicles as $vehicle): ?>
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

                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php
                        $params = $_GET;
                        
                        if ($page > 1):
                            $params['page'] = $page - 1;
                        ?>
                            <a href="<?= buildUrl($params) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1):
                            $params['page'] = 1;
                        ?>
                            <a href="<?= buildUrl($params) ?>">1</a>
                            <?php if ($startPage > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++):
                            $params['page'] = $i;
                        ?>
                            <?php if ($i === $page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= buildUrl($params) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php
                        if ($endPage < $totalPages):
                            if ($endPage < $totalPages - 1):
                        ?>
                                <span>...</span>
                            <?php endif;
                            $params['page'] = $totalPages;
                        ?>
                            <a href="<?= buildUrl($params) ?>"><?= $totalPages ?></a>
                        <?php endif; ?>
                        
                        <?php
                        if ($page < $totalPages):
                            $params['page'] = $page + 1;
                        ?>
                            <a href="<?= buildUrl($params) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        const userBtn = document.getElementById('userBtn');
        const userDropdown = document.getElementById('userDropdown');
        
        userBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', () => {
            userDropdown?.classList.remove('show');
        });
        
        // FIX: Use catalog_id instead of vehicle_id
        function rentVehicle(catalogId) {
            window.location.href = `vehicle-details.php?id=${catalogId}`;
        }
        
        function removeFilter(filterName) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterName);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }
        
        function clearAllFilters() {
            window.location.href = 'vehicles.php';
        }
        
        function sortResults(sortBy) {
            const url = new URL(window.location.href);
            url.searchParams.set('sort', sortBy);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }
    </script>
</body>
</html>