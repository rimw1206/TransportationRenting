<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$loginError = '';
$requiresVerification = false;
$userEmail = '';
$registerSuccessMessage = '';

// Check if redirected from registration
if (isset($_GET['register_success'])) {
    $registerSuccessMessage = 'Đăng ký thành công! Vui lòng kiểm tra email để xác thực tài khoản trước khi đăng nhập.';
    $userEmail = $_GET['email'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $loginError = 'Vui lòng nhập đầy đủ thông tin';
    } else {
        $apiUrl = "http://localhost/TransportationRenting/gateway/api/auth/login";
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'username' => $username,
            'password' => $password
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            $loginError = 'Không thể kết nối đến server. Vui lòng thử lại sau.';
        } elseif ($httpCode === 503) {
            $loginError = 'Hệ thống đang bảo trì. Vui lòng quay lại sau!';
        } else {
            $result = json_decode($response, true);
            
            if ($result && ($result['success'] ?? false)) {
                $_SESSION['user'] = $result['user'];
                $_SESSION['token'] = $result['token'] ?? '';
                header('Location: dashboard.php');
                exit;
            } else {
                $loginError = $result['message'] ?? 'Tên đăng nhập hoặc mật khẩu không đúng!';
                
                // Check if requires verification
                if (isset($result['requires_verification']) && $result['requires_verification']) {
                    $requiresVerification = true;
                    $userEmail = $result['email'] ?? '';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/login_style.css">
    <style>
        .success-message {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            animation: slideDown 0.4s ease;
            box-shadow: 0 8px 20px rgba(17, 153, 142, 0.3);
        }
        
        .success-message-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .success-message-header i {
            font-size: 28px;
        }
        
        .success-message-header h3 {
            font-size: 20px;
            margin: 0;
        }
        
        .success-message p {
            margin: 8px 0;
            font-size: 15px;
            line-height: 1.6;
        }
        
        .success-message strong {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 4px;
        }
        
        .success-message .email-info {
            background: rgba(255, 255, 255, 0.15);
            padding: 12px;
            border-radius: 8px;
            margin-top: 12px;
            font-size: 14px;
            border-left: 4px solid rgba(255, 255, 255, 0.5);
        }
        
        .verification-notice {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            animation: slideDown 0.3s ease;
        }
        
        .verification-notice h4 {
            margin-bottom: 10px;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .verification-notice p {
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .resend-btn {
            background: white;
            color: #667eea;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .resend-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .error-message {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="login-left-content">
                <h1>
                    <i class="fas fa-car"></i>
                    Transportation
                </h1>
                <p>Hệ thống quản lý cho thuê phương tiện giao thông hiện đại và tiện lợi</p>
                
                <div class="features">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Bảo mật tuyệt đối</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-clock"></i>
                        <span>Thuê xe 24/7</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-star"></i>
                        <span>Dịch vụ chất lượng cao</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="login-right">
            <form class="login-form" method="POST" action="">
                <h2>Đăng nhập</h2>
                <p>Nhập thông tin tài khoản để tiếp tục</p>
                
                <?php if ($registerSuccessMessage): ?>
                <div class="success-message">
                    <div class="success-message-header">
                        <i class="fas fa-check-circle"></i>
                        <h3>Đăng ký thành công!</h3>
                    </div>
                    <p><?= htmlspecialchars($registerSuccessMessage) ?></p>
                    <?php if ($userEmail): ?>
                    <div class="email-info">
                        <i class="fas fa-envelope"></i>
                        Email xác thực đã được gửi tới: <strong><?= htmlspecialchars($userEmail) ?></strong>
                        <br><br>
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Kiểm tra cả thư mục Spam nếu không thấy email. Link xác thực có hiệu lực trong 24 giờ.
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($requiresVerification): ?>
                <div class="verification-notice">
                    <h4><i class="fas fa-envelope-open-text"></i> Xác thực email</h4>
                    <p>Tài khoản của bạn chưa được xác thực. Vui lòng kiểm tra email để kích hoạt tài khoản.</p>
                    <button type="button" class="resend-btn" onclick="resendVerificationEmail('<?= htmlspecialchars($userEmail) ?>')">
                        <i class="fas fa-paper-plane"></i> Gửi lại email xác thực
                    </button>
                </div>
                <?php endif; ?>
                
                <?php if ($loginError && !$requiresVerification): ?>
                <div class="error-message" id="errorMessage">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($loginError) ?></span>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Tên đăng nhập
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            placeholder="Nhập tên đăng nhập"
                            required
                            autocomplete="username"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        >
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Mật khẩu
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Nhập mật khẩu"
                            required
                            autocomplete="current-password"
                        >
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>
                
                <button type="submit" class="login-btn" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span id="btnText">Đăng nhập</span>
                </button>

                <div class="divider">
                    <span>hoặc</span>
                </div>

                <div class="extra-links">
                    <a href="register.php">
                        <i class="fas fa-user-plus"></i> Đăng ký tài khoản
                    </a>
                    <span>|</span>
                    <a href="forgot-password.php">
                        <i class="fas fa-key"></i> Quên mật khẩu?
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const loginForm = document.querySelector('.login-form');
        const loginBtn = document.getElementById('loginBtn');
        const btnText = document.getElementById('btnText');
        const errorMessage = document.getElementById('errorMessage');

        loginForm.addEventListener('submit', function(e) {
            loginBtn.disabled = true;
            btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang đăng nhập...';
            if (errorMessage) {
                errorMessage.style.display = 'none';
            }
        });

        // Resend verification email
        function resendVerificationEmail(email) {
            if (!email) return;
            
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';
            
            fetch('http://localhost/TransportationRenting/gateway/api/auth/resend-verification', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(() => {
                alert('❌ Không thể gửi email. Vui lòng thử lại sau.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loginForm.submit();
            }
        });
    </script>
</body>
</html>