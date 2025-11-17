<?php
/**
 * services/customer/services/AuthService.php
 * Full version with Redis cache support
 */

$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';
require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';
require_once __DIR__ . '/../../../shared/classes/Cache.php';
require_once __DIR__ . '/../../../shared/classes/EmailService.php';

class AuthService
{
    private $db;
    private $jwtHandler;
    private $cache;
    private $emailService;
    
    public function __construct()
    {
        $this->db = DatabaseManager::getConnection('customer');
        $this->jwtHandler = new JWTHandler();
        $this->cache = Cache::getInstance();
        
        try {
            $this->emailService = new EmailService();
        } catch (Exception $e) {
            error_log("Failed to initialize EmailService: " . $e->getMessage());
            $this->emailService = null;
        }
    }
    
    /**
     * Register new user - WITH TRANSACTION
     */
    public function register($data)
    {
        // Start transaction FIRST
        $this->db->beginTransaction();
        
        try {
            // Validate
            $required = ['username', 'password', 'name', 'email'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => "Trường {$field} không được để trống"];
                }
            }
            
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Email không hợp lệ'];
            }
            
            if (strlen($data['password']) < 6) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự'];
            }
            
            // Check username with row lock (FOR UPDATE)
            $stmt = $this->db->prepare("SELECT username FROM Users WHERE username = ? FOR UPDATE");
            $stmt->execute([$data['username']]);
            
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->db->rollBack();
                error_log("❌ Username exists: {$data['username']}");
                return ['success' => false, 'message' => 'Tên đăng nhập đã tồn tại'];
            }
            
            // Check email with row lock
            $stmt = $this->db->prepare("SELECT email FROM Users WHERE email = ? FOR UPDATE");
            $stmt->execute([$data['email']]);
            
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->db->rollBack();
                error_log("❌ Email exists: {$data['email']}");
                return ['success' => false, 'message' => 'Email đã được sử dụng'];
            }
            
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO Users (username, password, name, email, phone, birthdate, status, email_verified, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', FALSE, NOW())
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
            
            if (!$userId) {
                throw new Exception("Failed to get user ID");
            }
            
            // Create verification token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 24 * 60 * 60);
            
            $stmt = $this->db->prepare("
                INSERT INTO email_verifications (user_id, token, email, expires_at, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $token, $data['email'], $expiresAt]);
            
            // Commit transaction
            $this->db->commit();
            
            error_log("✅ User registered: ID={$userId}, Username={$data['username']}");
            
            // Cache user data
            if ($this->cache->isAvailable()) {
                $this->cache->set("user:active:{$userId}", 'active', 3600);
            }
            
            // Send email (outside transaction)
            $emailSent = false;
            try {
                $emailSent = $this->sendVerificationEmail($data['email'], $token, $data['name']);
            } catch (Exception $e) {
                error_log("⚠️ Email error: " . $e->getMessage());
            }
            
            return [
                'success' => true,
                'message' => 'Đăng ký thành công! Vui lòng kiểm tra email để xác thực tài khoản.',
                'user_id' => $userId,
                'email_sent' => $emailSent
            ];
            
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                error_log("❌ Duplicate: " . $e->getMessage());
                
                if (strpos($e->getMessage(), 'username') !== false) {
                    return ['success' => false, 'message' => 'Tên đăng nhập đã tồn tại'];
                }
                if (strpos($e->getMessage(), 'email') !== false) {
                    return ['success' => false, 'message' => 'Email đã được sử dụng'];
                }
            }
            
            error_log("❌ DB error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Có lỗi xảy ra trong quá trình đăng ký'];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('❌ Register error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Có lỗi xảy ra trong quá trình đăng ký'];
        }
    }
    
    /**
     * Verify email
     */
    public function verifyEmail($token)
    {
        try {
            if (empty($token)) {
                return ['success' => false, 'message' => 'Token không hợp lệ'];
            }
            
            $stmt = $this->db->prepare("
                SELECT id, user_id, email, expires_at, verified_at
                FROM email_verifications
                WHERE token = ?
            ");
            $stmt->execute([$token]);
            $verification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$verification) {
                return ['success' => false, 'message' => 'Token không tồn tại hoặc không hợp lệ'];
            }
            
            if ($verification['verified_at'] !== null) {
                return ['success' => false, 'message' => 'Email đã được xác thực trước đó'];
            }
            
            if (strtotime($verification['expires_at']) < time()) {
                return ['success' => false, 'message' => 'Token đã hết hạn'];
            }
            
            $this->db->beginTransaction();
            
            try {
                $stmt = $this->db->prepare("
                    UPDATE email_verifications 
                    SET verified_at = NOW() 
                    WHERE id = ? AND verified_at IS NULL
                ");
                $stmt->execute([$verification['id']]);
                
                $stmt = $this->db->prepare("
                    UPDATE Users 
                    SET email_verified = TRUE, status = 'Active'
                    WHERE user_id = ?
                ");
                $stmt->execute([$verification['user_id']]);
                
                $this->db->commit();
                
                // Update cache
                if ($this->cache->isAvailable()) {
                    $this->cache->set("user:active:{$verification['user_id']}", 'active', 3600);
                }
                
                return ['success' => true, 'message' => 'Xác thực email thành công!'];
                
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Verify error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Có lỗi xảy ra khi xác thực email'];
        }
    }
    
    /**
     * Login with rate limiting
     */
    public function login($username, $password)
    {
        try {
            if (empty($username) || empty($password)) {
                return ['success' => false, 'message' => 'Username và password không được để trống'];
            }
            
            // Rate limiting
            if (!$this->checkRateLimit($username)) {
                return ['success' => false, 'message' => 'Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau 15 phút'];
            }
            
            $stmt = $this->db->prepare("
                SELECT user_id, username, password, name, email, phone, 
                       status, email_verified, created_at
                FROM Users 
                WHERE username = ?
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password'])) {
                $this->recordFailedAttempt($username);
                return ['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng'];
            }
            
            if ($user['status'] === 'Inactive') {
                return ['success' => false, 'message' => 'Tài khoản đã bị khóa'];
            }
            
            if (!$user['email_verified']) {
                return [
                    'success' => false,
                    'message' => 'Vui lòng xác thực email trước khi đăng nhập',
                    'requires_verification' => true,
                    'email' => $user['email']
                ];
            }
            
            // Clear failed attempts
            $this->clearFailedAttempts($username);
            
            $payload = [
                'user_id' => (int)$user['user_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => ($user['user_id'] === 1) ? 'admin' : 'user',
                'iat' => time(),
                'exp' => time() + (24 * 60 * 60)
            ];
            
            $token = $this->jwtHandler->encode($payload);
            
            unset($user['password']);
            $user['role'] = $payload['role'];
            
            return [
                'success' => true,
                'message' => 'Đăng nhập thành công',
                'user' => $user,
                'token' => $token,
                'expires_in' => 86400
            ];
            
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Có lỗi xảy ra trong quá trình đăng nhập'];
        }
    }
    
    /**
     * Resend verification
     */
    public function resendVerification($email)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT user_id, name, email_verified 
                FROM Users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Email không tồn tại'];
            }
            
            if ($user['email_verified']) {
                return ['success' => false, 'message' => 'Email đã được xác thực'];
            }
            
            // Delete old tokens
            $stmt = $this->db->prepare("DELETE FROM email_verifications WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            
            // Create new token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 24 * 60 * 60);
            
            $stmt = $this->db->prepare("
                INSERT INTO email_verifications (user_id, token, email, expires_at, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user['user_id'], $token, $email, $expiresAt]);
            
            $emailSent = $this->sendVerificationEmail($email, $token, $user['name']);
            
            if (!$emailSent) {
                return ['success' => false, 'message' => 'Không thể gửi email'];
            }
            
            return ['success' => true, 'message' => 'Email xác thực đã được gửi lại'];
            
        } catch (Exception $e) {
            error_log('Resend error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Có lỗi xảy ra'];
        }
    }
    
    // Helper methods
    private function checkRateLimit($username)
    {
        if (!$this->cache->isAvailable()) {
            return true;
        }
        
        $key = 'login_attempts:' . $username;
        $attempts = $this->cache->get($key);
        
        return $attempts === null || (int)$attempts < 5;
    }
    
    private function recordFailedAttempt($username)
    {
        if (!$this->cache->isAvailable()) {
            return;
        }
        
        $key = 'login_attempts:' . $username;
        $attempts = $this->cache->get($key);
        
        if ($attempts === null) {
            $this->cache->set($key, 1, 900);
        } else {
            $this->cache->increment($key);
            $this->cache->expire($key, 900);
        }
    }
    
    private function clearFailedAttempts($username)
    {
        if (!$this->cache->isAvailable()) {
            return;
        }
        
        $this->cache->delete('login_attempts:' . $username);
    }
    
    private function sendVerificationEmail($email, $token, $name = '')
    {
        $verificationLink = "http://localhost/TransportationRenting/frontend/verify-email.php?token={$token}";
        
        error_log("📧 Verification link: {$verificationLink}");
        
        if ($this->emailService) {
            try {
                return $this->emailService->sendVerificationEmail($email, $token, $name);
            } catch (Exception $e) {
                error_log("Email error: " . $e->getMessage());
                return false;
            }
        }
        
        return true;
    }
    
    public function logout($token)
    {
        if ($this->cache->isAvailable()) {
            $payload = $this->jwtHandler->decode($token);
            if ($payload) {
                $tokenHash = hash('sha256', $token);
                $ttl = max(0, $payload['exp'] - time());
                $this->cache->set('blacklist:token:' . $tokenHash, time(), $ttl);
            }
        }
        
        return ['success' => true, 'message' => 'Đăng xuất thành công'];
    }
}