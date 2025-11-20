<?php
/**
 * ================================================
 * public/admin/rentals.php
 * Admin quản lý rentals - Approve/Reject
 * ================================================
 */

session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['username'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'];

require_once __DIR__ . '/../../shared/classes/ApiClient.php';
$apiClient = new ApiClient();
$apiClient->setServiceUrl('rental', 'http://localhost:8003');
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');

// Fetch pending rentals
$pendingRentals = [];

try {
    $response = $apiClient->get('rental', '/rentals?status=Pending', [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['raw_response'], true);
        if ($data && $data['success']) {
            foreach ($data['data'] as $rental) {
                // Get vehicle info
                $vResponse = $apiClient->get('vehicle', '/units/' . $rental['vehicle_id']);
                $vehicle = null;
                
                if ($vResponse['status_code'] === 200) {
                    $vData = json_decode($vResponse['raw_response'], true);
                    if ($vData && $vData['success']) {
                        $vehicle = $vData['data'];
                    }
                }
                
                $pendingRentals[] = [
                    'rental' => $rental,
                    'vehicle' => $vehicle
                ];
            }
        }
    }
} catch (Exception $e) {
    error_log('Error fetching pending rentals: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Rentals - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/dashboard_style.css">
    <style>
        .rental-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .rental-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .rental-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-approve {
            background: #10b981;
            color: white;
        }
        
        .btn-approve:hover {
            background: #059669;
        }
        
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        
        .btn-reject:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <div class="admin-container" style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
        <h1><i class="fas fa-clipboard-list"></i> Quản lý Đơn Thuê Pending</h1>
        
        <?php if (empty($pendingRentals)): ?>
            <div style="text-align: center; padding: 60px; background: white; border-radius: 16px;">
                <i class="fas fa-inbox" style="font-size: 64px; color: #ccc; margin-bottom: 20px;"></i>
                <h3>Không có đơn nào cần xử lý</h3>
            </div>
        <?php else: ?>
            <?php foreach ($pendingRentals as $item): 
                $rental = $item['rental'];
                $vehicle = $item['vehicle'];
            ?>
            <div class="rental-card">
                <div class="rental-header">
                    <div>
                        <h3>Đơn #<?= $rental['rental_id'] ?></h3>
                        <p style="color: #666; margin: 5px 0;">
                            <i class="far fa-calendar"></i>
                            <?= date('d/m/Y H:i', strtotime($rental['created_at'])) ?>
                        </p>
                    </div>
                    <div class="rental-actions">
                        <button class="btn btn-approve" onclick="verifyRental(<?= $rental['rental_id'] ?>, 'approve')">
                            <i class="fas fa-check"></i> Duyệt
                        </button>
                        <button class="btn btn-reject" onclick="verifyRental(<?= $rental['rental_id'] ?>, 'reject')">
                            <i class="fas fa-times"></i> Từ chối
                        </button>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div>
                        <strong>Xe:</strong><br>
                        <?php if ($vehicle): ?>
                            <?= htmlspecialchars($vehicle['catalog']['brand'] . ' ' . $vehicle['catalog']['model']) ?><br>
                            <small style="color: #666;"><?= $vehicle['license_plate'] ?></small>
                        <?php else: ?>
                            Unit #<?= $rental['vehicle_id'] ?>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <strong>Thời gian:</strong><br>
                        <?= date('d/m/Y', strtotime($rental['start_time'])) ?>
                        →
                        <?= date('d/m/Y', strtotime($rental['end_time'])) ?>
                    </div>
                    
                    <div>
                        <strong>Tổng tiền:</strong><br>
                        <span style="font-size: 20px; color: #4F46E5; font-weight: 700;">
                            <?= number_format($rental['total_cost']) ?>đ
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        async function verifyRental(rentalId, action) {
            const actionText = action === 'approve' ? 'duyệt' : 'từ chối';
            
            if (!confirm(`Bạn có chắc muốn ${actionText} đơn #${rentalId}?`)) {
                return;
            }
            
            try {
                const response = await fetch('../api/admin-verify-rental.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        rental_id: rentalId,
                        action: action
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`✅ ${result.message}`);
                    location.reload();
                } else {
                    alert(`❌ ${result.message}`);
                }
            } catch (error) {
                alert('❌ Lỗi kết nối');
            }
        }
    </script>
</body>
</html>