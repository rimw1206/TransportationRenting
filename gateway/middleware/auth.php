<?php
// gateway/middleware/auth.php - IMPROVED VERSION

require_once __DIR__ . '/../../shared/classes/JWTHandler.php';
require_once __DIR__ . '/../../shared/classes/Cache.php';

class AuthMiddleware
{
    // Rate limiting constants
    private const MAX_AUTH_ATTEMPTS = 20; // per minute
    private const RATE_LIMIT_WINDOW = 60; // seconds
    
    /**
     * Authenticate request using JWT token
     * 
     * @return array ['success' => bool, 'user_id' => int|null, 'role' => string|null, 'message' => string]
     */
    public static function authenticate()
    {
        // Rate limiting check BEFORE processing token
        if (!self::checkRateLimit()) {
            return [
                'success' => false,
                'message' => 'Too many authentication attempts. Please try again later.'
            ];
        }
        
        // Get Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader)) {
            return [
                'success' => false,
                'message' => 'Authorization header missing'
            ];
        }
        
        // Extract Bearer token with stricter validation
        if (!preg_match('/^Bearer\s+([A-Za-z0-9\-_=]+\.[A-Za-z0-9\-_=]+\.[A-Za-z0-9\-_=]+)$/i', $authHeader, $matches)) {
            return [
                'success' => false,
                'message' => 'Invalid authorization format'
            ];
        }
        
        $token = $matches[1];
        
        // Basic token length validation (prevent extremely long tokens)
        if (strlen($token) > 2048) {
            return [
                'success' => false,
                'message' => 'Token too long'
            ];
        }
        
        // Check token blacklist EARLY (before expensive JWT decode)
        if (self::isTokenBlacklisted($token)) {
            return [
                'success' => false,
                'message' => 'Token has been revoked'
            ];
        }
        
        try {
            // Verify JWT token
            $jwtHandler = new JWTHandler();
            $payload = $jwtHandler->decode($token);
            
            if (!$payload) {
                self::recordFailedAttempt();
                return [
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ];
            }
            
            // Validate required fields with type checking
            if (!isset($payload['user_id']) || !is_numeric($payload['user_id']) || 
                !isset($payload['exp']) || !is_numeric($payload['exp'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid token payload'
                ];
            }
            
            // Convert to proper types
            $userId = (int)$payload['user_id'];
            $exp = (int)$payload['exp'];
            
            // Additional validation
            if ($userId <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid user ID in token'
                ];
            }
            
            // Check expiration (already checked by JWT library, but double-check)
            if ($exp < time()) {
                return [
                    'success' => false,
                    'message' => 'Token has expired'
                ];
            }
            
            // Check if user still exists and is active
            $userStatus = self::checkUserActive($userId);
            if ($userStatus !== 'active') {
                return [
                    'success' => false,
                    'message' => $userStatus === 'not_found' 
                        ? 'User not found' 
                        : 'User account is inactive'
                ];
            }
            
            // Check for token reuse detection (optional but recommended)
            if (isset($payload['jti']) && self::isTokenUsedRecently($payload['jti'])) {
                return [
                    'success' => false,
                    'message' => 'Token reuse detected'
                ];
            }
            
            return [
                'success' => true,
                'user_id' => $userId,
                'role' => $payload['role'] ?? 'user',
                'email' => $payload['email'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log('Auth error: ' . $e->getMessage());
            self::recordFailedAttempt();
            return [
                'success' => false,
                'message' => 'Authentication failed'
            ];
        }
    }
    
    /**
     * Rate limiting check per IP
     */
    private static function checkRateLimit()
    {
        $cache = Cache::getInstance();
        if (!$cache->isAvailable()) {
            return true; // If cache is down, allow requests (or implement alternative)
        }
        
        $ip = self::getClientIP();
        $key = 'rate_limit:auth:' . $ip;
        
        $attempts = $cache->get($key);
        
        if ($attempts === null) {
            $cache->set($key, 1, self::RATE_LIMIT_WINDOW);
            return true;
        }
        
        if ($attempts >= self::MAX_AUTH_ATTEMPTS) {
            return false;
        }
        
        $cache->increment($key);
        return true;
    }
    
    /**
     * Record failed authentication attempt
     */
    private static function recordFailedAttempt()
    {
        $cache = Cache::getInstance();
        if (!$cache->isAvailable()) {
            return;
        }
        
        $ip = self::getClientIP();
        $key = 'failed_auth:' . $ip;
        
        $cache->increment($key);
        $cache->expire($key, 3600); // Track for 1 hour
    }
    
    /**
     * Get real client IP (considering proxies)
     */
    private static function getClientIP()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Take first IP if comma-separated
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Check if token is blacklisted
     * @param string $token
     * @return bool
     */
    private static function isTokenBlacklisted(string $token): bool
    {
        $cache = Cache::getInstance();
        if (!$cache->isAvailable()) {
            return false; // If cache is down, can't check blacklist
        }
        
        $tokenHash = hash('sha256', $token);
        $cacheKey = 'blacklist:token:' . $tokenHash;
        
        return $cache->exists($cacheKey);
    }
    
    /**
     * Check if user is still active
     * FIXED: Fail-closed instead of fail-open
     * @param int $userId
     * @return string 'active'|'inactive'|'not_found'
     */
    private static function checkUserActive(int $userId): string
    {
        $cache = Cache::getInstance();
        $cacheKey = 'user:active:' . $userId;
        
        // Check cache first (TTL: 5 minutes)
        if ($cache->isAvailable()) {
            $cachedStatus = $cache->get($cacheKey);
            if ($cachedStatus !== null && in_array($cachedStatus, ['active', 'inactive', 'not_found'])) {
                return $cachedStatus;
            }
        }
        
        // Query database
        try {
            $db = DatabaseManager::getConnection('customer'); // Customer service handles Users
            $stmt = $db->prepare("SELECT status FROM Users WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                if ($cache->isAvailable()) {
                    $cache->set($cacheKey, 'not_found', 300);
                }
                return 'not_found';
            }
            
            $status = ($row['status'] === 'Active') ? 'active' : 'inactive';
            
            // Cache result
            if ($cache->isAvailable()) {
                $cache->set($cacheKey, $status, 300);
            }
            
            return $status;
            
        } catch (Exception $e) {
            error_log('checkUserActive DB error: ' . $e->getMessage());
            
            // FAIL-CLOSED: Deny access if DB is down (security over availability)
            // Alternative: Check if we have recent cache data
            if ($cache->isAvailable()) {
                $cachedStatus = $cache->get($cacheKey);
                if ($cachedStatus !== null) {
                    return $cachedStatus;
                }
            }
            
            return 'inactive'; // Deny by default
        }
    }
    
    /**
     * Check for token reuse (optional - for high-security scenarios)
     * @param string $jti
     * @return bool
     */
    private static function isTokenUsedRecently(string $jti): bool
    {
        $cache = Cache::getInstance();
        if (!$cache->isAvailable()) {
            return false;
        }
        
        $key = 'token:jti:' . $jti;
        if ($cache->exists($key)) {
            return true;
        }
        
        // Mark as used for 5 seconds (adjust based on your needs)
        $cache->set($key, 1, 5);
        return false;
    }
    
    /**
     * Blacklist a token (for logout)
     * @param string $token
     * @param int $expirationTime
     * @return bool
     */
    public static function blacklistToken(string $token, int $expirationTime): bool
    {
        $cache = Cache::getInstance();
        if (!$cache->isAvailable()) {
            error_log('Cannot blacklist token: Cache unavailable');
            return false;
        }
        
        $tokenHash = hash('sha256', $token);
        $cacheKey = 'blacklist:token:' . $tokenHash;
        
        // Store until token would expire anyway
        $ttl = max(0, $expirationTime - time());
        return $cache->set($cacheKey, time(), $ttl);
    }
    
    /**
     * Check if user has required role
     * @param string|array $allowedRoles
     * @return array
     */
    public static function requireRole($allowedRoles): array
    {
        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        
        $authResult = self::authenticate();
        
        if (!$authResult['success']) {
            ApiResponse::unauthorized($authResult['message']);
        }
        
        $userRole = $authResult['role'];
        
        if (!in_array($userRole, $allowedRoles, true)) {
            ApiResponse::forbidden('Insufficient permissions');
        }
        
        return $authResult;
    }
    
    /**
     * Optional: Get auth stats for monitoring
     */
    public static function getAuthStats()
    {
        $cache = Cache::getInstance();
        if (!$cache->isAvailable()) {
            return null;
        }
        
        $ip = self::getClientIP();
        
        return [
            'rate_limit_remaining' => self::MAX_AUTH_ATTEMPTS - ($cache->get('rate_limit:auth:' . $ip) ?? 0),
            'failed_attempts' => $cache->get('failed_auth:' . $ip) ?? 0
        ];
    }
}