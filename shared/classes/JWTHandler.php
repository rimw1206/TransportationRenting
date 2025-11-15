<?php
/**
 * ============================================
 * shared/classes/JWTHandler.php
 * JWT Token Handler using HS256 (Hardened + Auto-loading)
 * ============================================
 */

class JWTHandler
{
    private $secret;
    private $algorithm = 'HS256';
    
    public function __construct()
    {
        // Ensure env-bootstrap is loaded
        if (!defined('ENV_BOOTSTRAP_LOADED')) {
            $bootstrap = __DIR__ . '/../../env-bootstrap.php';
            if (file_exists($bootstrap)) {
                require_once $bootstrap;
            }
        }
        
        // Get secret from environment (NO FALLBACK!)
        $this->secret = getenv('JWT_SECRET');
        
        if (!$this->secret) {
            throw new Exception('CRITICAL: JWT_SECRET not found in environment. Ensure env-bootstrap.php is loaded.');
        }
        
        if (strlen($this->secret) < 32) {
            throw new Exception('CRITICAL: JWT_SECRET too short (minimum 32 characters required).');
        }
    }
    
    /**
     * Encode payload to JWT token
     * @param array $payload
     * @return string|false
     */
    public function encode(array $payload)
    {
        try {
            // Add issued at time if not present
            if (!isset($payload['iat'])) {
                $payload['iat'] = time();
            }
            
            // Header
            $header = [
                'typ' => 'JWT',
                'alg' => $this->algorithm
            ];
            
            // Encode header and payload
            $headerEncoded = $this->base64UrlEncode(json_encode($header));
            $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
            
            // Create signature
            $signature = $this->sign($headerEncoded . '.' . $payloadEncoded);
            $signatureEncoded = $this->base64UrlEncode($signature);
            
            // Return JWT token
            return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
            
        } catch (Exception $e) {
            error_log('JWTHandler::encode error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decode and verify JWT token
     * @param string $token
     * @return array|false Payload if valid, false otherwise
     */
    public function decode($token)
    {
        try {
            // Split token
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                error_log('JWTHandler: Invalid token format');
                return false;
            }
            
            list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
            
            // Decode header FIRST to check algorithm before verifying signature
            $header = json_decode($this->base64UrlDecode($headerEncoded), true);
            
            if (!$header || !is_array($header)) {
                error_log('JWTHandler: Failed to decode header');
                return false;
            }
            
            // Verify algorithm BEFORE signature verification (security best practice)
            if (($header['alg'] ?? '') !== $this->algorithm) {
                error_log('JWTHandler: Algorithm mismatch');
                return false;
            }
            
            // Explicitly reject "none" algorithm
            if (strtolower($header['alg'] ?? '') === 'none') {
                error_log('JWTHandler: Algorithm "none" is not allowed');
                return false;
            }
            
            // Verify signature using constant-time comparison
            $signature = $this->base64UrlDecode($signatureEncoded);
            $expectedSignature = $this->sign($headerEncoded . '.' . $payloadEncoded);
            
            if (!hash_equals($expectedSignature, $signature)) {
                error_log('JWTHandler: Invalid signature');
                return false;
            }
            
            // Decode payload
            $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
            
            if (!$payload || !is_array($payload)) {
                error_log('JWTHandler: Failed to decode payload');
                return false;
            }
            
            // Check expiration
            if (isset($payload['exp'])) {
                if (!is_numeric($payload['exp']) || $payload['exp'] < time()) {
                    error_log('JWTHandler: Token expired');
                    return false;
                }
            }
            
            // Check not before
            if (isset($payload['nbf'])) {
                if (!is_numeric($payload['nbf']) || $payload['nbf'] > time()) {
                    error_log('JWTHandler: Token not yet valid');
                    return false;
                }
            }
            
            return $payload;
            
        } catch (Exception $e) {
            error_log('JWTHandler::decode error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify token without decoding full payload
     * @param string $token
     * @return bool
     */
    public function verify($token)
    {
        return $this->decode($token) !== false;
    }
    
    /**
     * Create signature using HMAC SHA256
     * @param string $data
     * @return string
     */
    private function sign($data)
    {
        return hash_hmac('sha256', $data, $this->secret, true);
    }
    
    /**
     * Base64 URL encode
     * @param string $data
     * @return string
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     * @param string $data
     * @return string
     */
    private function base64UrlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Get token expiration time
     * @param string $token
     * @return int|false Unix timestamp or false if no expiration
     */
    public function getExpiration($token)
    {
        $payload = $this->decode($token);
        if (!$payload) {
            return false;
        }
        return $payload['exp'] ?? false;
    }
    
    /**
     * Check if token is expired
     * @param string $token
     * @return bool
     */
    public function isExpired($token)
    {
        $exp = $this->getExpiration($token);
        if (!$exp) {
            return false; // No expiration means never expires
        }
        return $exp < time();
    }
    
    /**
     * Get payload from token without verification (for debugging)
     * WARNING: Do not use for authentication!
     * @param string $token
     * @return array|false
     */
    public function getPayloadUnsafe($token)
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }
            
            $payloadEncoded = $parts[1];
            $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
            
            return $payload ?: false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Generate a random JWT secret
     * @param int $length
     * @return string
     */
    public static function generateSecret($length = 64)
    {
        return bin2hex(random_bytes($length / 2));
    }
}