<?php
/**
 * ============================================
 * index.php - Main entry point
 * Tự động setup database, start services và redirect
 * ============================================
 */

// Tắt output buffering để hiển thị realtime
if (ob_get_level()) ob_end_clean();

// Bật error reporting nhưng log vào file
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Tạo thư mục logs
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0777, true);
}
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Kiểm tra có đang chạy setup hay đã xong
$setupFlagFile = __DIR__ . '/.db_setup_complete';
$isFirstRun = !file_exists($setupFlagFile);

if ($isFirstRun) {
    // ===== CHẠY SETUP LẦN ĐẦU =====
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setting up Transportation Renting...</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .container {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
            max-width: 600px;
            width: 90%;
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            animation: fadeIn 0.5s ease-in;
        }
        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: spin 2s linear infinite;
        }
        p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        .setup-log {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            max-height: 400px;
            overflow-y: auto;
            line-height: 1.6;
        }
        .setup-log::-webkit-scrollbar {
            width: 8px;
        }
        .setup-log::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
        }
        .setup-log::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }
        .success { color: #4ade80; }
        .warning { color: #fbbf24; }
        .info { color: #60a5fa; }
        .redirect-btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-size: 1.1rem;
            margin-top: 1rem;
            transition: transform 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        .redirect-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚀</div>
        <h1>Transportation Renting</h1>
        <p>Đang thiết lập hệ thống lần đầu tiên...</p>
        
        <div class="setup-log" id="setupLog">
            <div class="info">⏳ Bắt đầu setup database...</div>
        </div>
        
        <div id="redirectSection" style="display: none;">
            <p class="success">✅ Setup hoàn tất!</p>
            <a href="frontend/login.php" class="redirect-btn">Đi đến trang đăng nhập</a>
        </div>
    </div>

    <script>
        // Auto scroll log
        function scrollToBottom() {
            const log = document.getElementById('setupLog');
            log.scrollTop = log.scrollHeight;
        }
        
        // Add log entry
        function addLog(message, type = 'info') {
            const log = document.getElementById('setupLog');
            const entry = document.createElement('div');
            entry.className = type;
            entry.textContent = message;
            log.appendChild(entry);
            scrollToBottom();
        }
        
        // Simulate setup progress (sẽ được thay bằng real output)
        setTimeout(() => scrollToBottom(), 100);
    </script>
</body>
</html>
    <?php
    flush();
    
    // Capture output from run.php
    ob_start();
    require_once __DIR__ . '/run.php';
    $output = ob_get_clean();
    
    // Parse output và display
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $type = 'info';
        if (strpos($line, '✅') !== false) $type = 'success';
        elseif (strpos($line, '⚠️') !== false || strpos($line, '❌') !== false) $type = 'warning';
        
        $escapedLine = htmlspecialchars($line);
        echo "<script>addLog(" . json_encode($escapedLine) . ", '{$type}');</script>";
        flush();
        usleep(50000); // 50ms delay for visual effect
    }
    
    // Show redirect button
    echo "<script>";
    echo "document.getElementById('redirectSection').style.display = 'block';";
    echo "setTimeout(() => { window.location.href = 'frontend/login.php'; }, 3000);";
    echo "</script>";
    exit;
    
} else {
    // ===== ĐÃ SETUP RỒI - REDIRECT THẲNG =====
    
    // Đảm bảo services đang chạy
    require_once __DIR__ . '/run.php';
    
    // Check xem user đã login chưa
    session_start();
    if (isset($_SESSION['user'])) {
        // Đã login -> Dashboard
        header('Location: frontend/dashboard.php');
    } else {
        // Chưa login -> Login page
        header('Location: frontend/login.php');
    }
    exit;
}