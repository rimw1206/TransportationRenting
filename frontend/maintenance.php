<script src="js/api-handler.js"></script>
<?php
// frontend/maintenance.php
header('HTTP/1.1 503 Service Unavailable');
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đang Bảo Trì</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 60px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 15px;
        }
        p {
            color: #666;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .status {
            display: inline-block;
            padding: 10px 20px;
            background: #f0f0f0;
            border-radius: 50px;
            color: #667eea;
            font-weight: 600;
        }
        .retry {
            margin-top: 30px;
            padding: 15px 40px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .retry:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Đang Bảo Trì</h1>
        <p>Hệ thống đang được nâng cấp để phục vụ bạn tốt hơn.<br>Vui lòng quay lại sau ít phút.</p>
        <div class="status">⏳ Service Unavailable</div>
        <button class="retry" onclick="location.reload()">Thử lại</button>
    </div>
</body>
</html>