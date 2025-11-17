<?php
/**
 * ============================================
 * services/customer/services/AuthService.php
 * Service xử lý authentication
 * ============================================
 */

require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';
require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';
require_once __DIR__ . '/../../../shared/classes/Cache.php';

class AuthService
{
    private $db;
    private $jwtHandler;
    private $cache;
    
    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('customer');
        $this->jwtHandler = new JWTHandler();
        $this->cache = Cache::getInstance();
    }
    
    /**
     * Login user
     * @param string $username
     * @param string $password
     * @return array
     */
    public function login($username, $password)
    {
        try {
            // Validate input
            if (empty($username) || empty($password)) {
                return [
                    'success' => false,
                    'message' => 'Username và password không được để trống'
                ];
            }
            
            // Check rate limiting
            if (!$this->checkRateLimit($username)) {
                return [
                    'success' => false,
                    'message' => 'Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau 15 phút'
                ];
            }
            
            // Get user from database
            $stmt = $this->db->prepare("
                SELECT 
                    user_id, 
                    username, 
                    password, 
                    name, 
                    email, 
                    phone, 
                    status,
                    created_at
                FROM Users 
                WHERE username = ? 
                LIMIT 1
            ");
            
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if user exists
            if (!$user) {
                $this->recordFailedAttempt($username);
                return [
                    'success' => false,
                    'message' => 'Tên đăng nhập hoặc mật khẩu không đúng'
                ];
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                $this->recordFailedAttempt($username);
                return [
                    'success' => false,
                    'message' => 'Tên đăng nhập hoặc mật khẩu không đúng'
                ];
            }
            
            // Check user status
            // Check user status - Allow Active and Pending, block Inactive
            if ($user['status'] === 'Inactive') {
                return [
                    'success' => false,
                    'message' => 'Tài khoản đã bị khóa. Vui lòng liên hệ admin.'
                ];
            }

            // Optional: Add warning message for pending users in response
            $statusWarning = null;
            if ($user['status'] === 'Pending') {
                $statusWarning = 'Tài khoản đang chờ xác thực. Một số tính năng có thể bị giới hạn.';
            }
                        
            // Generate JWT token
            $payload = [
                'user_id' => (int)$user['user_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $this->getUserRole($user['user_id']),
                'iat' => time(),
                'exp' => time() + (24 * 60 * 60) // 24 hours
            ];
            
            $token = $this->jwtHandler->encode($payload);
            
            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'Không thể tạo token đăng nhập'
                ];
            }
            
            // Generate refresh token (optional)
            $refreshToken = $this->generateRefreshToken($user['user_id']);
            
            // Clear failed attempts
            $this->clearFailedAttempts($username);
            
            // Update last login
            $this->updateLastLogin($user['user_id']);
            
            // Remove password from response
            unset($user['password']);
            
            // Add role to user data
            $user['role'] = $payload['role'];
            
            return [
                'success' => true,
                'message' => 'Đăng nhập thành công',
                'user' => $user,
                'token' => $token,
                'refresh_token' => $refreshToken,
                'expires_in' => 86400 // 24 hours in seconds
            ];
            
        } catch (Exception $e) {
            error_log('AuthService::login error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra trong quá trình đăng nhập'
            ];
        }
    }
    
    /**
     * Get user role (admin or user)
     * @param int $userId
     * @return string
     */
    private function getUserRole($userId)
    {
        // Simple role detection: user_id 1 is admin
        // You can expand this with a proper roles table
        return ($userId === 1) ? 'admin' : 'user';
    }
    
    /**
     * Check rate limiting for login attempts
     * @param string $username
     * @return bool
     */
    private function checkRateLimit($username)
    {
        if (!$this->cache->isAvailable()) {
            return true; // Allow if cache is down
        }
        
        $key = 'login_attempts:' . $username;
        $attempts = $this->cache->get($key);
        
        if ($attempts === null) {
            return true;
        }
        
        // Max 5 attempts per 15 minutes
        return (int)$attempts < 5;
    }
    
    /**
     * Record failed login attempt
     * @param string $username
     */
    private function recordFailedAttempt($username)
    {
        if (!$this->cache->isAvailable()) {
            return;
        }
        
        $key = 'login_attempts:' . $username;
        $attempts = $this->cache->get($key);
        
        if ($attempts === null) {
            $this->cache->set($key, 1, 900); // 15 minutes
        } else {
            $this->cache->increment($key);
            $this->cache->expire($key, 900); // Reset TTL
        }
    }
    
    /**
     * Clear failed login attempts
     * @param string $username
     */
    private function clearFailedAttempts($username)
    {
        if (!$this->cache->isAvailable()) {
            return;
        }
        
        $key = 'login_attempts:' . $username;
        $this->cache->delete($key);
    }
    
    /**
     * Update last login timestamp
     * @param int $userId
     */
    private function updateLastLogin($userId)
    {
        try {
            // You can add a last_login column to Users table
            // For now, we'll skip this
            // $stmt = $this->db->prepare("UPDATE Users SET last_login = NOW() WHERE user_id = ?");
            // $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log('updateLastLogin error: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate refresh token
     * @param int $userId
     * @return string|null
     */
    private function generateRefreshToken($userId)
    {
        try {
            $refreshToken = bin2hex(random_bytes(32));
            
            if ($this->cache->isAvailable()) {
                $key = 'refresh_token:' . $userId;
                $this->cache->set($key, $refreshToken, 30 * 24 * 60 * 60); // 30 days
            }
            
            return $refreshToken;
        } catch (Exception $e) {
            error_log('generateRefreshToken error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Register new user
     * @param array $data
     * @return array
     */
    public function register($data)
    {
        try {
            // Validate required fields
            $required = ['username', 'password', 'name', 'email'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Trường {$field} không được để trống"
                    ];
                }
            }
            
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Email không hợp lệ'
                ];
            }
            
            // Check if username exists
            $stmt = $this->db->prepare("SELECT user_id FROM Users WHERE username = ? LIMIT 1");
            $stmt->execute([$data['username']]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Tên đăng nhập đã tồn tại'
                ];
            }
            
            // Check if email exists
            $stmt = $this->db->prepare("SELECT user_id FROM Users WHERE email = ? LIMIT 1");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Email đã được sử dụng'
                ];
            }
            
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO Users (username, password, name, email, phone, birthdate, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())
            ");
            
            $stmt->execute([
                $data['username'],
                $hashedPassword,
                $data['name'],
                $data['email'],
                $data['phone'] ?? null,
                $data['birthdate'] ?? null
            ]);
            
            $userId = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Đăng ký thành công. Vui lòng chờ admin kích hoạt tài khoản',
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            error_log('AuthService::register error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra trong quá trình đăng ký'
            ];
        }
    }
    
    /**
     * Logout user (blacklist token)
     * @param string $token
     * @return array
     */
    public function logout($token)
    {
        try {
            if (!$this->cache->isAvailable()) {
                return [
                    'success' => false,
                    'message' => 'Cache service không khả dụng'
                ];
            }
            
            // Decode token to get expiration
            $payload = $this->jwtHandler->decode($token);
            if (!$payload) {
                return [
                    'success' => false,
                    'message' => 'Token không hợp lệ'
                ];
            }
            
            // Blacklist token until it expires
            $tokenHash = hash('sha256', $token);
            $ttl = max(0, $payload['exp'] - time());
            
            $this->cache->set('blacklist:token:' . $tokenHash, time(), $ttl);
            
            return [
                'success' => true,
                'message' => 'Đăng xuất thành công'
            ];
            
        } catch (Exception $e) {
            error_log('AuthService::logout error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra khi đăng xuất'
            ];
        }
    }
    
    /**
     * Refresh access token using refresh token
     * @param int $userId
     * @param string $refreshToken
     * @return array
     */
    public function refreshToken($userId, $refreshToken)
    {
        try {
            if (!$this->cache->isAvailable()) {
                return [
                    'success' => false,
                    'message' => 'Cache service không khả dụng'
                ];
            }
            
            // Verify refresh token
            $key = 'refresh_token:' . $userId;
            $storedToken = $this->cache->get($key);
            
            if ($storedToken !== $refreshToken) {
                return [
                    'success' => false,
                    'message' => 'Refresh token không hợp lệ'
                ];
            }
            
            // Get user info
            $stmt = $this->db->prepare("
                SELECT user_id, username, email, name, status 
                FROM Users 
                WHERE user_id = ? AND status = 'Active'
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User không tồn tại hoặc không active'
                ];
            }
            
            // Generate new access token
            $payload = [
                'user_id' => (int)$user['user_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $this->getUserRole($user['user_id']),
                'iat' => time(),
                'exp' => time() + (24 * 60 * 60)
            ];
            
            $newToken = $this->jwtHandler->encode($payload);
            
            return [
                'success' => true,
                'token' => $newToken,
                'expires_in' => 86400
            ];
            
        } catch (Exception $e) {
            error_log('AuthService::refreshToken error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra khi refresh token'
            ];
        }
    }
}