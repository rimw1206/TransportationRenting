<?php
session_start();

// Nếu đã login, chuyển sang dashboard
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

// Biến lưu lỗi và thành công
$registerError = '';
$registerSuccess = '';

// Xử lý POST register
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birthdate = $_POST['birthdate'] ?? '';

    // Validate dữ liệu
    if (!$username || !$password || !$confirmPassword || !$name || !$email) {
        $registerError = 'Vui lòng nhập đầy đủ thông tin bắt buộc';
    } elseif ($password !== $confirmPassword) {
        $registerError = 'Mật khẩu xác nhận không khớp';
    } elseif (strlen($password) < 6) {
        $registerError = 'Mật khẩu phải có ít nhất 6 ký tự';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Email không hợp lệ';
    } else {
        // Gọi API register qua gateway
        $apiUrl = "http://localhost/TransportationRenting/gateway/api/auth/register";
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'username' => $username,
            'password' => $password,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'birthdate' => $birthdate
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $registerError = 'Không thể kết nối đến server. Vui lòng thử lại sau.';
        } elseif ($httpCode === 503) {
            $registerError = 'Hệ thống đang bảo trì. Vui lòng quay lại sau!';
        } else {
            $result = json_decode($response, true);
            
            if ($result && ($result['success'] ?? false)) {
                $registerSuccess = 'Đăng ký thành công! Đang chuyển đến trang đăng nhập...';
                // Chuyển hướng sau 2 giây
                header("refresh:2;url=login.php");
            } else {
                $registerError = $result['message'] ?? 'Đăng ký thất bại. Vui lòng thử lại!';
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
    <title>Đăng ký - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/register_style.css">
</head>
<body>
    <div class="register-container">
        <div class="register-left">
            <div class="register-left-content">
                <h1>
                    <i class="fas fa-car"></i>
                    Transportation
                </h1>
                <p>Đăng ký tài khoản để trải nghiệm dịch vụ cho thuê phương tiện giao thông hiện đại</p>
                
                <div class="features">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Bảo mật thông tin tuyệt đối</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-clock"></i>
                        <span>Đăng ký nhanh chóng</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-star"></i>
                        <span>Ưu đãi cho thành viên mới</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="register-right">
            <form class="register-form" method="POST" action="">
                <h2>Đăng ký</h2>
                <p>Tạo tài khoản mới để bắt đầu</p>
                
                <div class="error-message <?= !empty($registerError) ? 'show' : '' ?>" id="errorMessage">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($registerError) ?></span>
                </div>

                <div class="success-message <?= !empty($registerSuccess) ? 'show' : '' ?>" id="successMessage">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($registerSuccess) ?></span>
                </div>

                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Username <span class="required">*</span>
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
                    <label for="name">
                        <i class="fas fa-id-card"></i> Họ và tên <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            placeholder="Nhập họ và tên đầy đủ"
                            required
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                        >
                        <i class="fas fa-id-card input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Nhập địa chỉ email"
                            required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        >
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">
                        <i class="fas fa-phone"></i> Số điện thoại
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            placeholder="Nhập số điện thoại"
                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                        >
                        <i class="fas fa-phone input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="birthdate">
                        <i class="fas fa-calendar"></i> Ngày sinh
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="date" 
                            id="birthdate" 
                            name="birthdate"
                            value="<?= htmlspecialchars($_POST['birthdate'] ?? '') ?>"
                        >
                        <i class="fas fa-calendar input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Mật khẩu <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Nhập mật khẩu (tối thiểu 6 ký tự)"
                            required
                            autocomplete="new-password"
                        >
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-lock"></i> Xác nhận mật khẩu <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Nhập lại mật khẩu"
                            required
                            autocomplete="new-password"
                        >
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>
                
                <button type="submit" class="register-btn" id="registerBtn">
                    <i class="fas fa-user-plus"></i>
                    <span id="btnText">Đăng ký</span>
                </button>

                <div class="divider">
                    <span>hoặc</span>
                </div>

                <div class="login-link">
                    <p>Đã có tài khoản? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Đăng nhập ngay</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Xử lý form submit với loading state
        const registerForm = document.querySelector('.register-form');
        const registerBtn = document.getElementById('registerBtn');
        const btnText = document.getElementById('btnText');
        const errorMessage = document.getElementById('errorMessage');
        const successMessage = document.getElementById('successMessage');

        registerForm.addEventListener('submit', function(e) {
            // Kiểm tra mật khẩu trước khi submit
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                errorMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Mật khẩu xác nhận không khớp</span>';
                errorMessage.style.display = 'flex';
                return;
            }

            registerBtn.disabled = true;
            btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang đăng ký...';
            errorMessage.style.display = 'none';
        });

        // Auto focus vào username khi load trang
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Validation real-time cho confirm password
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '#e0e0e0';
            }
        });
    </script>
</body>
</html>