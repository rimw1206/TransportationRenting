<?php
require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';
require_once __DIR__ . '/../../../shared/classes/Cache.php';

class AuthService {
    private $serviceName = 'customer';
    private $cache;
    
    public function __construct() {
        $this->cache = Cache::getInstance();
    }
    
    public function login($username, $password) {
        try {
            // Check rate limiting
            if (!$this->checkLoginAttempts($username)) {
                return [
                    'success' => false,
                    'message' => 'Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau 15 phút.'
                ];
            }
            
            // Find user
            $sql = "SELECT user_id, username, password, name, email, phone, status 
                    FROM Users WHERE username = ? LIMIT 1";
            
            $user = pdo_query_one($this->serviceName, $sql, $username);
            
            if (!$user) {
                $this->recordFailedAttempt($username);
                return ['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng'];
            }
            
            // Check status
            if ($user['status'] !== 'Active') {
                return ['success' => false, 'message' => 'Tài khoản chưa được kích hoạt'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                $this->recordFailedAttempt($username);
                return ['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng'];
            }
            
            // Reset login attempts
            $this->resetLoginAttempts($username);
            
            // Generate tokens
            $token = $this->generateToken($user);
            $refreshToken = $this->generateRefreshToken($user['user_id']);
            
            // Save refresh token to cache (30 days)
            $this->cache->set("refresh_token:{$user['user_id']}", $refreshToken, 30 * 24 * 60 * 60);
            
            // Update last login
            pdo_execute($this->serviceName, "UPDATE Users SET last_login = NOW() WHERE user_id = ?", $user['user_id']);
            
            // Remove password from response
            unset($user['password']);
            
            return [
                'success' => true,
                'user' => $user,
                'token' => $token,
                'refresh_token' => $refreshToken
            ];
            
        } catch (Exception $e) {
            error_log('AuthService Login Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Có lỗi xảy ra'];
        }
    }
    
    private function generateToken($user) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'name' => $user['name'],
            'iat' => time(),
            'exp' => time() + (60 * 60 * 2) // 2 hours
        ]);
        
        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->getJwtSecret(), true);
        $base64UrlSignature = $this->base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    private function generateRefreshToken($userId) {
        return bin2hex(random_bytes(32)) . '.' . $userId . '.' . time();
    }
    
    public function verifyToken($token) {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) return false;
            
            list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
            
            $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->getJwtSecret(), true);
            
            if ($this->base64UrlEncode($signature) !== $base64UrlSignature) return false;
            
            $payload = json_decode($this->base64UrlDecode($base64UrlPayload), true);
            
            if (isset($payload['exp']) && $payload['exp'] < time()) return false;
            
            return $payload;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function checkLoginAttempts($username) {
        $key = "login_attempts:{$username}";
        $attempts = $this->cache->get($key);
        return $attempts === null || $attempts < 5;
    }
    
    private function recordFailedAttempt($username) {
        $key = "login_attempts:{$username}";
        $attempts = $this->cache->get($key) ?? 0;
        $this->cache->set($key, $attempts + 1, 15 * 60); // 15 minutes
    }
    
    private function resetLoginAttempts($username) {
        $key = "login_attempts:{$username}";
        $this->cache->delete($key);
    }
    
    private function getJwtSecret() {
        return $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this-in-production';
    }
    
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}