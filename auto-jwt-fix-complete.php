<?php
/**
 * ============================================
 * auto-jwt-fix-complete.php
 * ULTIMATE JWT Auto-Fix System
 * Tự động sửa mọi vấn đề về JWT, không cần can thiệp
 * ============================================
 */

class AutoJWTFixComplete
{
    private const MIN_SECRET_LENGTH = 64;
    private const FLAG_FILE = '.jwt_setup_complete';
    
    /**
     * Main: Auto-fix everything silently
     * @return array Result status
     */
    public static function autoFix()
    {
        $envFile = self::getEnvPath();
        $flagFile = self::getFlagPath();
        
        // Step 1: Ensure .env exists
        if (!file_exists($envFile)) {
            return self::createFullEnvironment();
        }
        
        // Step 2: Get current secret
        $currentSecret = self::getSecretFromEnv();
        
        // Step 3: Check if secret is valid
        $isValid = self::isSecretValid($currentSecret);
        
        // Step 4: Check flag file sync
        $flagExists = file_exists($flagFile);
        $flagData = $flagExists ? json_decode(file_get_contents($flagFile), true) : null;
        
        $needsRegeneration = false;
        $reason = '';
        
        // Decision tree
        if (!$currentSecret || $currentSecret === 'your-secret-key-change-in-production') {
            $needsRegeneration = true;
            $reason = 'No secret or default secret';
        } elseif (!$isValid) {
            $needsRegeneration = true;
            $reason = 'Secret too weak';
        } elseif (!$flagExists) {
            // Secret OK but flag missing - just recreate flag
            return self::recreateFlagOnly($currentSecret);
        } elseif (strlen($currentSecret) != $flagData['secret_length']) {
            $needsRegeneration = true;
            $reason = 'Length mismatch';
        } elseif (hash('sha256', $currentSecret) != $flagData['secret_hash']) {
            $needsRegeneration = true;
            $reason = 'Hash mismatch';
        }
        
        if ($needsRegeneration) {
            return self::regenerateSecret($reason);
        }
        
        // Everything is perfect
        return [
            'success' => true,
            'action' => 'none',
            'message' => 'JWT configuration is perfect',
            'secret_length' => strlen($currentSecret)
        ];
    }
    
    /**
     * Create complete .env + flag from scratch
     */
    private static function createFullEnvironment()
    {
        $newSecret = self::generateSecret();
        $envTemplate = self::getEnvTemplate($newSecret);
        
        if (!file_put_contents(self::getEnvPath(), $envTemplate)) {
            return ['success' => false, 'error' => 'Cannot create .env file'];
        }
        
        self::createFlagFile($newSecret);
        
        return [
            'success' => true,
            'action' => 'created_full_environment',
            'message' => '.env and flag file created from scratch',
            'secret_length' => strlen($newSecret)
        ];
    }
    
    /**
     * Regenerate secret (weak or mismatch)
     */
    private static function regenerateSecret($reason)
    {
        $newSecret = self::generateSecret();
        
        // Update .env
        $envFile = self::getEnvPath();
        $envContent = file_get_contents($envFile);
        
        if (preg_match('/JWT_SECRET\s*=\s*["\']?[^"\'\n]+["\']?/i', $envContent)) {
            // Replace existing
            $newContent = preg_replace(
                '/JWT_SECRET\s*=\s*["\']?[^"\'\n]+["\']?/i',
                'JWT_SECRET="' . $newSecret . '"',
                $envContent
            );
        } else {
            // Append new
            $newContent = rtrim($envContent) . "\n\n# JWT Secret (Auto-generated)\nJWT_SECRET=\"{$newSecret}\"\n";
        }
        
        if (!file_put_contents($envFile, $newContent)) {
            return ['success' => false, 'error' => 'Cannot update .env file'];
        }
        
        // Create/update flag
        self::createFlagFile($newSecret);
        
        // Clear sessions
        self::clearAllSessions();
        
        return [
            'success' => true,
            'action' => 'regenerated',
            'reason' => $reason,
            'message' => 'New strong secret generated',
            'secret_length' => strlen($newSecret),
            'note' => 'All users must re-login'
        ];
    }
    
    /**
     * Just recreate flag file (secret is OK)
     */
    private static function recreateFlagOnly($secret)
    {
        self::createFlagFile($secret);
        
        return [
            'success' => true,
            'action' => 'flag_recreated',
            'message' => 'Flag file recreated, secret unchanged',
            'secret_length' => strlen($secret)
        ];
    }
    
    /**
     * Generate cryptographically secure secret
     */
    private static function generateSecret()
    {
        return bin2hex(random_bytes(32)); // 64 chars
    }
    
    /**
     * Check if secret is valid (strong enough)
     */
    private static function isSecretValid($secret)
    {
        if (!$secret || strlen($secret) < self::MIN_SECRET_LENGTH) {
            return false;
        }
        
        // Check for weak patterns
        $weakPatterns = [
            'your-secret-key', 'change-this', 'change-me', 
            'secret', 'password', '12345', 'test'
        ];
        
        $lower = strtolower($secret);
        foreach ($weakPatterns as $pattern) {
            if (strpos($lower, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get secret from .env file
     */
    private static function getSecretFromEnv()
    {
        $envFile = self::getEnvPath();
        
        if (!file_exists($envFile)) {
            return null;
        }
        
        $content = file_get_contents($envFile);
        if (preg_match('/JWT_SECRET\s*=\s*["\']?([^"\'\n]+)["\']?/i', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Create flag file
     */
    private static function createFlagFile($secret)
    {
        $data = [
            'setup_time' => date('Y-m-d H:i:s'),
            'secret_length' => strlen($secret),
            'secret_hash' => hash('sha256', $secret),
            'auto_fixed' => true
        ];
        
        file_put_contents(self::getFlagPath(), json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Clear all sessions
     */
    private static function clearAllSessions()
    {
        $sessionPath = session_save_path() ?: sys_get_temp_dir();
        
        if (is_dir($sessionPath)) {
            $files = glob($sessionPath . '/sess_*');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Get paths
     */
    private static function getEnvPath()
    {
        return __DIR__ . '/.env';
    }
    
    private static function getFlagPath()
    {
        return __DIR__ . '/' . self::FLAG_FILE;
    }
    
    /**
     * Get .env template
     */
    private static function getEnvTemplate($secret)
    {
        return <<<ENV
USE_ENV_CONFIG=true

# Database Configurations
CUSTOMER_DB_HOST=localhost
CUSTOMER_DB_PORT=3306
CUSTOMER_DB_NAME=customer_service_db
CUSTOMER_DB_USER=root
CUSTOMER_DB_PASS=

VEHICLE_DB_HOST=localhost
VEHICLE_DB_PORT=3306
VEHICLE_DB_NAME=vehicle_service_db
VEHICLE_DB_USER=root
VEHICLE_DB_PASS=

RENTAL_DB_HOST=localhost
RENTAL_DB_PORT=3306
RENTAL_DB_NAME=rental_service_db
RENTAL_DB_USER=root
RENTAL_DB_PASS=

ORDER_DB_HOST=localhost
ORDER_DB_PORT=3306
ORDER_DB_NAME=order_service_db
ORDER_DB_USER=root
ORDER_DB_PASS=

PAYMENT_DB_HOST=localhost
PAYMENT_DB_PORT=3306
PAYMENT_DB_NAME=payment_service_db
PAYMENT_DB_USER=root
PAYMENT_DB_PASS=

NOTIFICATION_DB_HOST=localhost
NOTIFICATION_DB_PORT=3306
NOTIFICATION_DB_NAME=notification_service_db
NOTIFICATION_DB_USER=root
NOTIFICATION_DB_PASS=

# JWT Secret (Auto-generated - DO NOT CHANGE)
JWT_SECRET="{$secret}"

# Redis Cache
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
CACHE_PREFIX=rental_app:
ENV;
    }
    
    /**
     * Verify current setup
     */
    public static function verify()
    {
        $result = ['checks' => []];
        
        // Check .env exists
        $result['checks']['env_exists'] = file_exists(self::getEnvPath());
        
        // Check secret
        $secret = self::getSecretFromEnv();
        $result['checks']['secret_set'] = !empty($secret);
        $result['checks']['secret_strong'] = self::isSecretValid($secret);
        $result['secret_length'] = strlen($secret ?? '');
        
        // Check flag
        $flagFile = self::getFlagPath();
        $result['checks']['flag_exists'] = file_exists($flagFile);
        
        if ($result['checks']['flag_exists']) {
            $flagData = json_decode(file_get_contents($flagFile), true);
            $result['checks']['length_match'] = strlen($secret) == $flagData['secret_length'];
            $result['checks']['hash_match'] = hash('sha256', $secret) == $flagData['secret_hash'];
        }
        
        // Overall status
        $result['success'] = !in_array(false, $result['checks'], true);
        
        return $result;
    }
}

// If run directly - execute auto-fix
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "🤖 Auto JWT Fix - Complete System\n";
    echo "===================================\n\n";
    
    echo "Running auto-fix...\n";
    $result = AutoJWTFixComplete::autoFix();
    
    if ($result['success']) {
        echo "✅ " . $result['message'] . "\n";
        echo "   Action: " . $result['action'] . "\n";
        echo "   Secret length: " . $result['secret_length'] . " chars\n";
        
        if (isset($result['note'])) {
            echo "\n⚠️  " . $result['note'] . "\n";
        }
        
        // Verify
        echo "\nVerifying...\n";
        $verify = AutoJWTFixComplete::verify();
        
        foreach ($verify['checks'] as $check => $status) {
            echo "   " . ($status ? '✓' : '✗') . " $check\n";
        }
        
        if ($verify['success']) {
            echo "\n🎉 JWT system is now 100% correct!\n";
        }
    } else {
        echo "❌ Auto-fix failed: " . ($result['error'] ?? 'Unknown error') . "\n";
        exit(1);
    }
}