<?php

class RateLimitMiddleware {
    private static $maxRequests = 1000; // requests per window
    private static $timeWindow = 30;   // seconds
    
    public static function handle() {
        $clientIp = self::getClientIp();
        $rateLimitFile = __DIR__ . '/../storage/rate_limits.json';
        
        if (!file_exists(dirname($rateLimitFile))) {
            mkdir(dirname($rateLimitFile), 0777, true);
        }
        
        $rateLimits = [];
        if (file_exists($rateLimitFile)) {
            $rateLimits = json_decode(file_get_contents($rateLimitFile), true) ?: [];
        }
        
        $now = time();
        $windowStart = $now - self::$timeWindow;
        
        // Xoá các entry quá hạn
        foreach ($rateLimits as $ip => $data) {
            if ($data['window_start'] < $windowStart) {
                unset($rateLimits[$ip]);
            }
        }
        
        // Tạo mới nếu IP chưa có
        if (!isset($rateLimits[$clientIp])) {
            $rateLimits[$clientIp] = [
                'count' => 0,
                'window_start' => $now
            ];
        }
        
        $clientData = &$rateLimits[$clientIp];
        
        // Reset window nếu hết hạn
        if ($clientData['window_start'] < $windowStart) {
            $clientData['count'] = 0;
            $clientData['window_start'] = $now;
        }
        
        $clientData['count']++;
        
        // Lưu lại file
        file_put_contents($rateLimitFile, json_encode($rateLimits));
        
        // Check quá limit
        if ($clientData['count'] > self::$maxRequests) {
            header('HTTP/1.1 429 Too Many Requests');
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Rate limit exceeded. Please try again later.',
                'status_code' => 429
            ]);
            exit;
        }
        
        // Thêm header rate limit
        header('X-RateLimit-Limit: ' . self::$maxRequests);
        header('X-RateLimit-Remaining: ' . (self::$maxRequests - $clientData['count']));
        header('X-RateLimit-Reset: ' . ($clientData['window_start'] + self::$timeWindow));
    }
    
    private static function getClientIp() {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', 
            'HTTP_X_FORWARDED_FOR', 
            'HTTP_X_FORWARDED', 
            'HTTP_FORWARDED_FOR', 
            'HTTP_FORWARDED', 
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
