<?php
session_start();

// Nếu đã login, chuyển sang dashboard
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

// Biến lưu lỗi
$loginError = '';

// Xử lý POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $loginError = 'Vui lòng nhập đầy đủ thông tin';
    } else {
        // Gọi API login qua gateway
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
        $curlError = curl_error($ch);
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
                
                <?php if ($loginError): ?>
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

                <div class="demo-accounts">
                    <h4>
                        <i class="fas fa-info-circle"></i>
                        Tài khoản demo
                    </h4>
                    <p><strong>Admin:</strong> <code>admin</code> / <code>admin123</code></p>
                    <p><strong>Khách hàng:</strong> <code>user</code> / <code>user123</code></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Xử lý form submit với loading state
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

        // Auto focus vào username khi load trang
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Enter ở password field sẽ submit form
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loginForm.submit();
            }
        });
    </script>
</body>
</html>