<?php
/**
 * ============================================
 * frontend/verify-email.php
 * DEBUG VERSION - Với logging chi tiết
 * ============================================
 */

session_start();

$verificationStatus = '';
$message = '';
$success = false;
$debugInfo = [];

// Get token from URL
$token = $_GET['token'] ?? '';

$debugInfo['token_received'] = !empty($token);
$debugInfo['token_length'] = strlen($token);

if ($token) {
    // Call API verify QUA GATEWAY
    $apiUrl = "http://localhost/TransportationRenting/gateway/api/auth/verify-email";
    
    $debugInfo['api_url'] = $apiUrl;
    $debugInfo['request_time'] = date('Y-m-d H:i:s');
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $token]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $debugInfo['http_code'] = $httpCode;
    $debugInfo['curl_error'] = $curlError;
    $debugInfo['response_received'] = !empty($response);
    
    // Log to PHP error log
    error_log("=== EMAIL VERIFICATION DEBUG ===");
    error_log("Token: " . substr($token, 0, 10) . "...");
    error_log("HTTP Code: " . $httpCode);
    error_log("Response: " . $response);
    error_log("================================");

    if ($response !== false) {
        $result = json_decode($response, true);
        $success = $result['success'] ?? false;
        $message = $result['message'] ?? 'Có lỗi xảy ra';
        
        $debugInfo['response_decoded'] = $result !== null;
        $debugInfo['success'] = $success;
        $debugInfo['message'] = $message;
    } else {
        $message = 'Không thể kết nối đến server';
        $debugInfo['connection_failed'] = true;
    }
} else {
    $message = 'Token không hợp lệ';
    $debugInfo['no_token'] = true;
}

// Log debug info
error_log("Verification Debug Info: " . json_encode($debugInfo));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác thực Email - Transportation Renting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verify-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 50px 40px;
            text-align: center;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .icon-wrapper {
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            animation: pulse 2s infinite;
        }

        .icon-wrapper.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .icon-wrapper.error {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .message {
            color: #555;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            flex-direction: column;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #555;
            border: 2px solid #e0e0e0;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            border-color: #ccc;
        }

        /* Debug info box */
        .debug-box {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .debug-box h3 {
            margin-bottom: 10px;
            color: #667eea;
            font-size: 14px;
        }

        .debug-box pre {
            background: white;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <?php if ($success): ?>
            <div class="icon-wrapper success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Xác thực thành công!</h1>
            <p class="message"><?= htmlspecialchars($message) ?></p>
            <div class="btn-group">
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Đăng nhập ngay
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i>
                    Về trang chủ
                </a>
            </div>
        <?php else: ?>
            <div class="icon-wrapper error">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1>Xác thực thất bại</h1>
            <p class="message"><?= htmlspecialchars($message) ?></p>
            <div class="btn-group">
                <a href="resend-verification.php" class="btn btn-primary">
                    <i class="fas fa-envelope"></i>
                    Gửi lại email xác thực
                </a>
                <a href="register.php" class="btn btn-secondary">
                    <i class="fas fa-user-plus"></i>
                    Đăng ký tài khoản mới
                </a>
            </div>
        <?php endif; ?>

        <!-- Debug Info Box (chỉ hiển thị khi có lỗi) -->
        <?php if (!$success && !empty($debugInfo)): ?>
        <div class="debug-box">
            <h3><i class="fas fa-bug"></i> Debug Information</h3>
            <pre><?= json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
            <p style="margin-top: 10px; color: #999; font-size: 11px;">
                <strong>Hướng dẫn:</strong> Nếu bạn là developer, hãy kiểm tra PHP error log để xem chi tiết lỗi.
            </p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>