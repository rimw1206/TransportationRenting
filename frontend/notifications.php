<?php
/**
 * ================================================
 * frontend/notifications.php
 * Trang hiển thị thông báo của user
 * ================================================
 */

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'] ?? '';

require_once __DIR__ . '/../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('notification', 'http://localhost:8006');

// Fetch notifications
$notifications = [];
$stats = ['total' => 0, 'unread' => 0, 'today' => 0];

try {
    $response = $apiClient->get('notification', '/notifications', [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['raw_response'], true);
        if ($data && $data['success']) {
            $notifications = $data['data']['items'] ?? [];
            $stats = [
                'total' => count($notifications),
                'unread' => count(array_filter($notifications, fn($n) => $n['status'] === 'Sent')),  // ✅ Sửa
                'today' => count(array_filter($notifications, fn($n) => date('Y-m-d', strtotime($n['sent_at'])) === date('Y-m-d')))
            ];
        }
    }
} catch (Exception $e) {
    error_log('Error fetching notifications: ' . $e->getMessage());
}

function getNotificationIcon($type) {
    $icons = [
        'Email' => 'fa-envelope',
        'SMS' => 'fa-sms',
        'Push' => 'fa-bell',
        'System' => 'fa-info-circle',
        'Success' => 'fa-check-circle',
        'Warning' => 'fa-exclamation-triangle',
        'Error' => 'fa-times-circle'
    ];
    return $icons[$type] ?? 'fa-bell';
}

function getNotificationColor($type) {
    $colors = [
        'Email' => 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)',
        'SMS' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        'Push' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        'System' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'Success' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        'Warning' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        'Error' => 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)'
    ];
    return $colors[$type] ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
}

function formatTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Vừa xong';
    if ($diff < 3600) return floor($diff / 60) . ' phút trước';
    if ($diff < 86400) return floor($diff / 3600) . ' giờ trước';
    if ($diff < 604800) return floor($diff / 86400) . ' ngày trước';
    
    return date('d/m/Y H:i', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard_style.css">
    <style>
        .notifications-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .notifications-header {
            margin-bottom: 30px;
        }

        .notifications-header h1 {
            font-size: 32px;
            color: #1a1a1a;
            margin-bottom: 20px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }

        .stat-box h3 {
            font-size: 32px;
            color: #4F46E5;
            margin-bottom: 5px;
        }

        .stat-box p {
            color: #666;
            font-size: 14px;
        }

        .filters {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            color: #666;
        }

        .filter-btn:hover {
            border-color: #4F46E5;
            color: #4F46E5;
        }

        .filter-btn.active {
            background: #4F46E5;
            color: white;
            border-color: #4F46E5;
        }

        .notifications-list {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .notification-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s;
            cursor: pointer;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: #f9fafb;
        }

        .notification-item.unread {
            background: #f0f4ff;
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 8px;
        }

        .notification-title {
            font-weight: 600;
            color: #1a1a1a;
            font-size: 16px;
        }

        .notification-time {
            color: #999;
            font-size: 13px;
            white-space: nowrap;
        }

        .notification-message {
            color: #666;
            line-height: 1.5;
            font-size: 14px;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-mark-read {
            background: #e0e7ff;
            color: #4F46E5;
        }

        .btn-mark-read:hover {
            background: #4F46E5;
            color: white;
        }

        .btn-delete {
            background: #fee;
            color: #DC2626;
        }

        .btn-delete:hover {
            background: #DC2626;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
        }

        .bulk-actions {
            padding: 15px 20px;
            background: #f9fafb;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
        }

        .btn-bulk {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-bulk-primary {
            background: #4F46E5;
            color: white;
        }

        .btn-bulk-primary:hover {
            background: #4338CA;
        }

        .btn-bulk-secondary {
            background: white;
            color: #666;
            border: 1px solid #e0e0e0;
        }

        .btn-bulk-secondary:hover {
            background: #f9fafb;
        }

        @media (max-width: 768px) {
            .notification-item {
                flex-direction: column;
                gap: 15px;
            }

            .notification-header {
                flex-direction: column;
                gap: 5px;
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
                
                <a href="notifications.php" class="nav-icon-btn active" title="Thông báo">
                    <i class="fas fa-bell"></i>
                    <?php if ($stats['unread'] > 0): ?>
                        <span class="badge"><?= $stats['unread'] ?></span>
                    <?php endif; ?>
                </a>
                
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
        <div class="notifications-container">
            <div class="notifications-header">
                <h1><i class="fas fa-bell"></i> Thông báo</h1>
            </div>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-box">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Tổng thông báo</p>
                </div>
                <div class="stat-box">
                    <h3><?= $stats['unread'] ?></h3>
                    <p>Chưa đọc</p>
                </div>
                <div class="stat-box">
                    <h3><?= $stats['today'] ?></h3>
                    <p>Hôm nay</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <span style="font-weight: 600; color: #333;">Lọc:</span>
                <button class="filter-btn active" onclick="filterNotifications('all')">Tất cả</button>
                <button class="filter-btn" onclick="filterNotifications('unread')">Chưa đọc</button>
                <button class="filter-btn" onclick="filterNotifications('today')">Hôm nay</button>
                <button class="filter-btn" onclick="filterNotifications('email')">Email</button>
                <button class="filter-btn" onclick="filterNotifications('sms')">SMS</button>
            </div>

            <!-- Notifications List -->
            <?php if (empty($notifications)): ?>
                <div class="notifications-list">
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>Không có thông báo</h3>
                        <p>Bạn chưa có thông báo nào</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="notifications-list">
                    <div class="bulk-actions">
                        <button class="btn-bulk btn-bulk-primary" onclick="markAllRead()">
                            <i class="fas fa-check-double"></i> Đánh dấu tất cả đã đọc
                        </button>
                        <button class="btn-bulk btn-bulk-secondary" onclick="deleteAll()">
                            <i class="fas fa-trash"></i> Xóa tất cả
                        </button>
                    </div>

                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?= ($notif['status'] === 'Sent') ? 'unread' : '' ?>" 
                            data-id="<?= $notif['notification_id'] ?>"
                            data-type="<?= strtolower($notif['type']) ?>"
                            onclick="viewNotification(<?= $notif['notification_id'] ?>)">
                            <div class="notification-icon" style="background: <?= getNotificationColor($notif['type']) ?>;">
                                <i class="fas <?= getNotificationIcon($notif['type']) ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-header">
                                    <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                                    <div class="notification-time"><?= formatTimeAgo($notif['sent_at']) ?></div>
                                </div>
                                <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                                <div class="notification-actions" onclick="event.stopPropagation();">
                                    <?php if ($notif['status'] === 'Sent'): ?>
                                    <button class="btn-action btn-mark-read" onclick="markAsRead(<?= $notif['notification_id'] ?>)">
                                        <i class="fas fa-check"></i> Đánh dấu đã đọc
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn-action btn-delete" onclick="deleteNotification(<?= $notif['notification_id'] ?>)">
                                        <i class="fas fa-trash"></i> Xóa
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const AUTH_TOKEN = '<?= $token ?>';

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

        // Filter notifications
        function filterNotifications(filter) {
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // Filter items
            const items = document.querySelectorAll('.notification-item');
            const now = new Date().toISOString().split('T')[0];

            items.forEach(item => {
                const isUnread = item.classList.contains('unread');
                const type = item.dataset.type;
                const time = item.querySelector('.notification-time').textContent;
                
                let show = true;

                if (filter === 'unread' && !isUnread) show = false;
                if (filter === 'today' && !time.includes('phút') && !time.includes('giờ') && !time.includes('Vừa')) show = false;
                if (filter === 'email' && type !== 'email') show = false;
                if (filter === 'sms' && type !== 'sms') show = false;

                item.style.display = show ? 'flex' : 'none';
            });
        }

        // View notification
        function viewNotification(id) {
            markAsRead(id);
        }

        // Mark as read
        async function markAsRead(id) {
        // ✅ Chỉ update UI, không call API
        const item = document.querySelector(`[data-id="${id}"]`);
        if (item) {
            item.classList.remove('unread');
            const btn = item.querySelector('.btn-mark-read');
            if (btn) btn.remove();
        }
        updateUnreadCount();
        
        // Nếu muốn call API (nhưng API sẽ không làm gì)
        /*
        try {
            await fetch(`http://localhost:8006/notifications/${id}/read`, {
                method: 'PUT',
                headers: { 'Authorization': `Bearer ${AUTH_TOKEN}` }
            });
        } catch (error) {
            console.error('Error:', error);
        }
        */
    }

        // Mark all as read
        async function markAllRead() {
            if (!confirm('Đánh dấu tất cả thông báo đã đọc?')) return;
            
            // ✅ Chỉ update UI
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                const btn = item.querySelector('.btn-mark-read');
                if (btn) btn.remove();
            });
            updateUnreadCount();
        }

        // Delete notification
        async function deleteNotification(id) {
            if (!confirm('Xóa thông báo này?')) return;

            try {
                const response = await fetch(`http://localhost:8006/notifications/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`
                    }
                });

                if (response.ok) {
                    const item = document.querySelector(`[data-id="${id}"]`);
                    if (item) item.remove();
                    updateUnreadCount();
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Có lỗi xảy ra');
            }
        }

        // Delete all
        async function deleteAll() {
            if (!confirm('Xóa tất cả thông báo? Hành động này không thể hoàn tác.')) return;

            try {
                const response = await fetch('http://localhost:8006/notifications', {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`
                    }
                });

                if (response.ok) {
                    location.reload();
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Có lỗi xảy ra');
            }
        }

        // Update unread count
        function updateUnreadCount() {
            const unreadItems = document.querySelectorAll('.notification-item.unread');
            const badge = document.querySelector('.nav-icon-btn.active .badge');
            const count = unreadItems.length;

            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }

            // Update stats
            const statBoxes = document.querySelectorAll('.stat-box h3');
            if (statBoxes[1]) {
                statBoxes[1].textContent = count;
            }
        }
    </script>
</body>
</html>