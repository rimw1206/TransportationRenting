<?php
/**
 * ============================================
 * frontend/resend-verification.php
 * Trang gửi lại email verification
 * ============================================
 */

session_start();

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Vui lòng nhập email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ';
    } else {
        // Call API qua Gateway
        $apiUrl = "http://localhost/TransportationRenting/gateway/api/auth/resend-verification";
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false) {
            $result = json_decode($response, true);
            
            if ($result['success'] ?? false) {
                $success = true;
            } else {
                $error = $result['message'] ?? 'Có lỗi xảy ra';
            }
        } else {
            $error = 'Không thể kết nối đến server';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gửi lại email xác thực - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/register_style.css">
    <style>
        .success-box {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .success-box i {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .success-box h3 {
            margin: 10px 0;
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-left">
            <div class="register-left-content">
                <h1>
                    <i class="fas fa-envelope"></i>
                    Gửi lại Email
                </h1>
                <p>Nhập email để chúng tôi gửi lại link xác thực</p>
                
                <div class="features">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Bảo mật cao</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-bolt"></i>
                        <span>Gửi ngay lập tức</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-clock"></i>
                        <span>Có hiệu lực 24h</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="register-right">
            <form class="register-form" method="POST" action="">
                <h2>Gửi lại Email Xác Thực</h2>
                <p>Nhập địa chỉ email bạn đã đăng ký</p>
                
                <?php if ($success): ?>
                <div class="success-box">
                    <i class="fas fa-check-circle"></i>
                    <h3>Đã gửi thành công!</h3>
                    <p>Vui lòng kiểm tra email của bạn và click vào link xác thực.</p>
                    <p><small>Nếu không thấy email, hãy kiểm tra thư mục spam.</small></p>
                </div>
                <?php elseif ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!$success): ?>
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Địa chỉ Email
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email"
                        placeholder="email@example.com"
                        required
                        autofocus
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                </div>
                
                <button type="submit" class="register-btn">
                    <i class="fas fa-paper-plane"></i>
                    <span>Gửi email xác thực</span>
                </button>
                <?php endif; ?>

                <div class="divider">
                    <span>hoặc</span>
                </div>

                <div class="extra-links">
                    <a href="login.php">
                        <i class="fas fa-sign-in-alt"></i> Đăng nhập
                    </a>
                    <a href="register.php">
                        <i class="fas fa-user-plus"></i> Đăng ký mới
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>