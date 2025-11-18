<?php
// frontend/promotions.php - Trang hiển thị khuyến mãi
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

require_once __DIR__ . '/../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('rental', 'http://localhost:8003');

// Fetch active promotions
$promotions = [];
try {
    $response = $apiClient->get('rental', '/promotions/active');
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['raw_response'], true);
        if ($data && isset($data['success']) && $data['success']) {
            $promotions = $data['data'];
        }
    }
} catch (Exception $e) {
    error_log('Error fetching promotions: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khuyến mãi - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard_style.css">
    <style>
        .promo-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .promo-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .promo-header h1 {
            font-size: 48px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
        }
        
        .promo-header p {
            color: #666;
            font-size: 18px;
        }
        
        .promo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .promo-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
            position: relative;
        }
        
        .promo-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .promo-banner {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
            position: relative;
            overflow: hidden;
        }
        
        .promo-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.3) 100%);
        }
        
        .promo-banner span {
            position: relative;
            z-index: 1;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .promo-banner-1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .promo-banner-2 {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .promo-banner-3 {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .promo-banner-4 {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .promo-content {
            padding: 25px;
        }
        
        .promo-code-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }
        
        .promo-code-box .code-label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .promo-code-box .code {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
        }
        
        .promo-description {
            color: #333;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .promo-details {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-top: 1px solid #f0f0f0;
            font-size: 13px;
            color: #666;
        }
        
        .promo-details span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .promo-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-copy {
            flex: 1;
            padding: 12px;
            background: white;
            color: #4F46E5;
            border: 2px solid #4F46E5;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-copy:hover {
            background: #4F46E5;
            color: white;
        }
        
        .btn-use {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-use:hover {
            transform: scale(1.05);
        }
        
        .no-promos {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .no-promos i {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .promo-tag {
            display: inline-block;
            padding: 4px 12px;
            background: #d1fae5;
            color: #065f46;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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
                <a href="promotions.php" class="nav-link active">
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
    <div class="promo-container">
        <div class="promo-header">
            <h1><i class="fas fa-gift"></i> Khuyến mãi đặc biệt</h1>
            <p>Sử dụng mã giảm giá để tiết kiệm chi phí thuê xe của bạn</p>
        </div>

        <?php if (empty($promotions)): ?>
            <div class="no-promos">
                <i class="fas fa-gift"></i>
                <h3>Chưa có khuyến mãi nào</h3>
                <p>Vui lòng quay lại sau để nhận ưu đãi tốt nhất!</p>
            </div>
        <?php else: ?>
            <div class="promo-grid">
                <?php foreach ($promotions as $index => $promo): ?>
                <div class="promo-card">
                    <div class="promo-banner promo-banner-<?= ($index % 4) + 1 ?>">
                        <span>-<?= $promo['discount_percent'] ?>%</span>
                    </div>
                    
                    <div class="promo-content">
                        <div class="promo-tag">
                            <i class="fas fa-check-circle"></i> Đang áp dụng
                        </div>
                        
                        <div class="promo-code-box">
                            <div class="code-label">MÃ KHUYẾN MÃI</div>
                            <div class="code" id="code-<?= $promo['promo_id'] ?>"><?= htmlspecialchars($promo['code']) ?></div>
                        </div>
                        
                        <div class="promo-description">
                            <?= htmlspecialchars($promo['description'] ?? 'Giảm giá đặc biệt cho tất cả xe') ?>
                        </div>
                        
                        <div class="promo-details">
                            <span>
                                <i class="fas fa-calendar"></i>
                                Từ <?= date('d/m/Y', strtotime($promo['valid_from'])) ?>
                            </span>
                            <span>
                                <i class="fas fa-calendar-times"></i>
                                Đến <?= date('d/m/Y', strtotime($promo['valid_to'])) ?>
                            </span>
                        </div>
                        
                        <div class="promo-actions">
                            <button class="btn-copy" onclick="copyCode('<?= $promo['code'] ?>', <?= $promo['promo_id'] ?>)">
                                <i class="fas fa-copy"></i> Sao chép
                            </button>
                            <button class="btn-use" onclick="usePromo('<?= $promo['code'] ?>')">
                                <i class="fas fa-shopping-cart"></i> Sử dụng ngay
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
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

        // Copy promo code
        function copyCode(code, promoId) {
            navigator.clipboard.writeText(code).then(() => {
                const btn = event.target.closest('.btn-copy');
                const originalHTML = btn.innerHTML;
                
                btn.innerHTML = '<i class="fas fa-check"></i> Đã sao chép!';
                btn.style.background = '#059669';
                btn.style.color = 'white';
                btn.style.borderColor = '#059669';
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.style.background = 'white';
                    btn.style.color = '#4F46E5';
                    btn.style.borderColor = '#4F46E5';
                }, 2000);
            }).catch(err => {
                alert('Không thể sao chép mã');
            });
        }

        // Use promo - redirect to cart or vehicles
        function usePromo(code) {
            // Save promo code to session/localStorage
            sessionStorage.setItem('pendingPromo', code);
            
            // Check if cart has items
            <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                window.location.href = 'cart.php';
            <?php else: ?>
                if (confirm('Giỏ hàng trống. Bạn có muốn đi tới trang xe để chọn xe không?')) {
                    window.location.href = 'vehicles.php';
                }
            <?php endif; ?>
        }
    </script>
</body>
</html>