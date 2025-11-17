<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$registerError = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birthdate = $_POST['birthdate'] ?? null;

    $formData = [
        'username' => $username,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'birthdate' => $birthdate
    ];

    // Validation
    if (!$username || !$password || !$name || !$email) {
        $registerError = 'Vui lòng nhập đầy đủ thông tin bắt buộc';
    } elseif ($password !== $confirmPassword) {
        $registerError = 'Mật khẩu xác nhận không khớp';
    } elseif (strlen($password) < 6) {
        $registerError = 'Mật khẩu phải có ít nhất 6 ký tự';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Email không hợp lệ';
    } else {
        $apiUrl = "http://localhost/TransportationRenting/gateway/api/auth/register";
        
        $postData = [
            'username' => $username,
            'password' => $password,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'birthdate' => $birthdate
        ];
        
        error_log("Registration attempt: " . json_encode($postData));
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("API Response Code: " . $httpCode);
        error_log("API Response: " . $response);
        
        if ($curlError) {
            error_log("CURL Error: " . $curlError);
            $registerError = 'Không thể kết nối đến server. Vui lòng thử lại sau.';
        } elseif ($httpCode === 503) {
            $registerError = 'Hệ thống đang bảo trì. Vui lòng quay lại sau!';
        } elseif ($response === false) {
            $registerError = 'Không nhận được phản hồi từ server';
        } else {
            $result = json_decode($response, true);
            
            if ($result === null) {
                error_log("JSON Decode Error: " . json_last_error_msg());
                $registerError = 'Lỗi xử lý dữ liệu từ server';
            } elseif ($result && ($result['success'] ?? false)) {
                // ✅ SUCCESS: Redirect to login with success message
                error_log("✅ Registration successful for: {$email}");
                header('Location: login.php?register_success=1&email=' . urlencode($email));
                exit;
            } else {
                $registerError = $result['message'] ?? 'Đăng ký thất bại. Vui lòng thử lại!';
                error_log("Registration failed: " . ($result['message'] ?? 'Unknown error'));
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
    <style>
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
                        <i class="fas fa-gift"></i>
                        <span>Ưu đãi cho thành viên mới</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="register-right">
            <form class="register-form" method="POST" action="" id="registerForm">
                <h2>Đăng ký</h2>
                <p>Tạo tài khoản mới để bắt đầu</p>
                
                <?php if ($registerError): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($registerError) ?></span>
                </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-user"></i> Tên đăng nhập *
                        </label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username"
                            autocomplete="username" 
                            placeholder="Nhập tên đăng nhập"
                            required
                            value="<?= htmlspecialchars($formData['username'] ?? '') ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="name">
                            <i class="fas fa-id-card"></i> Họ và tên *
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            autocomplete="name"
                            placeholder="Nhập họ và tên"
                            required
                            value="<?= htmlspecialchars($formData['name'] ?? '') ?>"
                        >
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email *
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            autocomplete="email"
                            placeholder="email@example.com"
                            required
                            value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">
                            <i class="fas fa-phone"></i> Số điện thoại
                        </label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            autocomplete="tel"
                            placeholder="0123456789"
                            value="<?= htmlspecialchars($formData['phone'] ?? '') ?>"
                        >
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> Mật khẩu *
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            autocomplete="new-password"
                            placeholder="Ít nhất 6 ký tự"
                            required
                            minlength="6"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i> Xác nhận mật khẩu *
                        </label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            autocomplete="new-password"
                            placeholder="Nhập lại mật khẩu"
                            required
                            minlength="6"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="birthdate">
                        <i class="fas fa-calendar"></i> Ngày sinh
                    </label>
                    <input 
                        type="date" 
                        id="birthdate" 
                        name="birthdate"
                        autocomplete="bday"
                        value="<?= htmlspecialchars($formData['birthdate'] ?? '') ?>"
                    >
                </div>
                
                <button type="submit" class="register-btn" id="registerBtn">
                    <i class="fas fa-user-plus"></i>
                    <span id="btnText">Đăng ký</span>
                </button>

                <div class="divider">
                    <span>hoặc</span>
                </div>

                <div class="extra-links">
                    <span>Đã có tài khoản?</span>
                    <a href="login.php">
                        <i class="fas fa-sign-in-alt"></i> Đăng nhập ngay
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const registerForm = document.getElementById('registerForm');
        const registerBtn = document.getElementById('registerBtn');
        const btnText = document.getElementById('btnText');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');

        // Real-time password match validation
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                if (this.value && password.value !== this.value) {
                    this.setCustomValidity('Mật khẩu không khớp');
                    this.style.borderColor = '#f44336';
                } else {
                    this.setCustomValidity('');
                    this.style.borderColor = '';
                }
            });

            password.addEventListener('input', function() {
                if (confirmPassword.value) {
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Mật khẩu không khớp');
                        confirmPassword.style.borderColor = '#f44336';
                    } else {
                        confirmPassword.setCustomValidity('');
                        confirmPassword.style.borderColor = '#4caf50';
                    }
                }
            });
        }

        // Form submit handler
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                if (password && confirmPassword) {
                    if (password.value !== confirmPassword.value) {
                        e.preventDefault();
                        alert('Mật khẩu xác nhận không khớp!');
                        confirmPassword.focus();
                        return false;
                    }
                }
                
                if (registerBtn && btnText) {
                    registerBtn.disabled = true;
                    btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                usernameInput.focus();
            }
        });
    </script>
</body>
</html>