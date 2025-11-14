<?php
/**
 * ============================================
 * shared/classes/JWTHandler.php
 * JWT Token Handler using HS256
 * ============================================
 */

class JWTHandler
{
    private $secret;
    private $algorithm = 'HS256';
    
    public function __construct()
    {
        // Get secret from environment or use default (change in production!)
        $this->secret = getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production-2024';
        
        if (strlen($this->secret) < 32) {
            error_log('WARNING: JWT_SECRET is too short. Should be at least 32 characters.');
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
            
            // Verify signature
            $signature = $this->base64UrlDecode($signatureEncoded);
            $expectedSignature = $this->sign($headerEncoded . '.' . $payloadEncoded);
            
            if (!hash_equals($expectedSignature, $signature)) {
                error_log('JWTHandler: Invalid signature');
                return false;
            }
            
            // Decode header and payload
            $header = json_decode($this->base64UrlDecode($headerEncoded), true);
            $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
            
            if (!$header || !$payload) {
                error_log('JWTHandler: Failed to decode header or payload');
                return false;
            }
            
            // Verify algorithm
            if (($header['alg'] ?? '') !== $this->algorithm) {
                error_log('JWTHandler: Algorithm mismatch');
                return false;
            }
            
            // Check expiration
            if (isset($payload['exp'])) {
                if ($payload['exp'] < time()) {
                    error_log('JWTHandler: Token expired');
                    return false;
                }
            }
            
            // Check not before
            if (isset($payload['nbf'])) {
                if ($payload['nbf'] > time()) {
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
?>