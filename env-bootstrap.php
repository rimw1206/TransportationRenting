<?php
/**
 * env-bootstrap.php - Auto-generated
 * Loads environment variables from .env file
 */

if (defined('ENV_BOOTSTRAP_LOADED')) return;
define('ENV_BOOTSTRAP_LOADED', true);

function loadEnvironmentVariables() {
    $currentDir = __DIR__;
    $maxLevels = 5;
    $level = 0;
    
    while ($level < $maxLevels) {
        $envFile = $currentDir . '/.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                        $value = $matches[2];
                    }
                    
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
            
            return true;
        }
        
        $parentDir = dirname($currentDir);
        if ($parentDir === $currentDir) break;
        $currentDir = $parentDir;
        $level++;
    }
    
    error_log('WARNING: .env file not found');
    return false;
}

loadEnvironmentVariables();