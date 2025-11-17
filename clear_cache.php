<?php
/**
 * Temporary script to clear ALL cache
 * Run: php clear_cache.php
 */

require_once __DIR__ . '/shared/classes/Cache.php';

try {
    $cache = Cache::getInstance();
    
    if (!$cache->isAvailable()) {
        echo "❌ Cache is not available\n";
        exit(1);
    }
    
    echo "🔄 Clearing cache...\n";
    
    // Get all keys
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    // Clear specific patterns
    $patterns = [
        'user:*',
        'rate_limit:*',
        'login_attempts:*',
        'blacklist:*',
        'refresh_token:*',
        'user:active:*'
    ];
    
    foreach ($patterns as $pattern) {
        $keys = $redis->keys($pattern);
        if ($keys) {
            foreach ($keys as $key) {
                $redis->del($key);
                echo "  ✅ Deleted: $key\n";
            }
        }
    }
    
    echo "\n✅ Cache cleared successfully!\n";
    
    // Show stats
    $info = $redis->info();
    echo "\n📊 Redis Stats:\n";
    echo "  - Used memory: " . ($info['used_memory_human'] ?? 'N/A') . "\n";
    echo "  - Keys: " . ($redis->dbSize()) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}