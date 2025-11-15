<?php
/**
 * ============================================
 * shared/classes/Cache.php
 * Redis Cache Wrapper - Complete Implementation
 * ============================================
 */

class Cache
{
    private static $instance = null;
    private $redis;
    private $enabled;
    private $prefix;
    
    private function __construct()
    {
        $this->enabled = extension_loaded('redis');
        $this->prefix = $_ENV['CACHE_PREFIX'] ?? 'rental_app:';
        
        if ($this->enabled) {
            try {
                $this->redis = new Redis();
                $this->redis->connect(
                    $_ENV['REDIS_HOST'] ?? '127.0.0.1', 
                    $_ENV['REDIS_PORT'] ?? 6379
                );
                
                if ($_ENV['REDIS_PASSWORD'] ?? null) {
                    $this->redis->auth($_ENV['REDIS_PASSWORD']);
                }
                
                $this->redis->ping();
            } catch (Exception $e) {
                error_log('Redis connection failed: ' . $e->getMessage());
                $this->enabled = false;
            }
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get value from cache
     * @param string $key
     * @return mixed|null
     */
    public function get($key)
    {
        if (!$this->enabled) return null;
        
        try {
            $value = $this->redis->get($this->prefix . $key);
            if ($value === false) return null;
            
            $data = @unserialize($value);
            return $data !== false ? $data : $value;
        } catch (Exception $e) {
            error_log('Cache get error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Set value in cache
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time to live in seconds (default: 1 hour)
     * @return bool
     */
    public function set($key, $value, $ttl = 3600)
    {
        if (!$this->enabled) return false;
        
        try {
            $fullKey = $this->prefix . $key;
            if (is_array($value) || is_object($value)) {
                $value = serialize($value);
            }
            
            if ($ttl > 0) {
                return $this->redis->setex($fullKey, $ttl, $value);
            }
            return $this->redis->set($fullKey, $value);
        } catch (Exception $e) {
            error_log('Cache set error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete key from cache
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        if (!$this->enabled) return false;
        
        try {
            return $this->redis->del($this->prefix . $key) > 0;
        } catch (Exception $e) {
            error_log('Cache delete error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if key exists
     * @param string $key
     * @return bool
     */
    public function exists($key)
    {
        if (!$this->enabled) return false;
        
        try {
            return $this->redis->exists($this->prefix . $key) > 0;
        } catch (Exception $e) {
            error_log('Cache exists error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Increment numeric value
     * @param string $key
     * @param int $amount
     * @return int|false New value or false on failure
     */
    public function increment($key, $amount = 1)
    {
        if (!$this->enabled) return false;
        
        try {
            $fullKey = $this->prefix . $key;
            if ($amount === 1) {
                return $this->redis->incr($fullKey);
            }
            return $this->redis->incrBy($fullKey, $amount);
        } catch (Exception $e) {
            error_log('Cache increment error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decrement numeric value
     * @param string $key
     * @param int $amount
     * @return int|false New value or false on failure
     */
    public function decrement($key, $amount = 1)
    {
        if (!$this->enabled) return false;
        
        try {
            $fullKey = $this->prefix . $key;
            if ($amount === 1) {
                return $this->redis->decr($fullKey);
            }
            return $this->redis->decrBy($fullKey, $amount);
        } catch (Exception $e) {
            error_log('Cache decrement error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set expiration time on key
     * @param string $key
     * @param int $ttl Time to live in seconds
     * @return bool
     */
    public function expire($key, $ttl)
    {
        if (!$this->enabled) return false;
        
        try {
            return $this->redis->expire($this->prefix . $key, $ttl);
        } catch (Exception $e) {
            error_log('Cache expire error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get time to live for key
     * @param string $key
     * @return int|false TTL in seconds or false if key doesn't exist
     */
    public function ttl($key)
    {
        if (!$this->enabled) return false;
        
        try {
            return $this->redis->ttl($this->prefix . $key);
        } catch (Exception $e) {
            error_log('Cache ttl error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get multiple keys at once
     * @param array $keys
     * @return array Associative array of key => value
     */
    public function mget(array $keys)
    {
        if (!$this->enabled || empty($keys)) return [];
        
        try {
            $prefixedKeys = array_map(function($key) {
                return $this->prefix . $key;
            }, $keys);
            
            $values = $this->redis->mGet($prefixedKeys);
            
            $result = [];
            foreach ($keys as $index => $key) {
                if ($values[$index] !== false) {
                    $data = @unserialize($values[$index]);
                    $result[$key] = $data !== false ? $data : $values[$index];
                } else {
                    $result[$key] = null;
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log('Cache mget error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Set multiple keys at once
     * @param array $items Associative array of key => value
     * @param int $ttl Time to live (applied to all keys)
     * @return bool
     */
    public function mset(array $items, $ttl = 3600)
    {
        if (!$this->enabled || empty($items)) return false;
        
        try {
            $prefixedItems = [];
            foreach ($items as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = serialize($value);
                }
                $prefixedItems[$this->prefix . $key] = $value;
            }
            
            $result = $this->redis->mSet($prefixedItems);
            
            // Set expiration for each key
            if ($result && $ttl > 0) {
                foreach (array_keys($prefixedItems) as $fullKey) {
                    $this->redis->expire($fullKey, $ttl);
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log('Cache mset error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete all keys matching pattern
     * @param string $pattern Pattern with * wildcard
     * @return int Number of keys deleted
     */
    public function deletePattern($pattern)
    {
        if (!$this->enabled) return 0;
        
        try {
            $iterator = null;
            $deleted = 0;
            
            while ($keys = $this->redis->scan($iterator, $this->prefix . $pattern, 100)) {
                foreach ($keys as $key) {
                    $deleted += $this->redis->del($key);
                }
            }
            
            return $deleted;
        } catch (Exception $e) {
            error_log('Cache deletePattern error: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Flush all keys in current database
     * WARNING: Use with caution!
     * @return bool
     */
    public function flush()
    {
        if (!$this->enabled) return false;
        
        try {
            return $this->redis->flushDB();
        } catch (Exception $e) {
            error_log('Cache flush error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if cache is available
     * @return bool
     */
    public function isAvailable()
    {
        return $this->enabled;
    }
    
    /**
     * Get cache statistics
     * @return array|null
     */
    public function getStats()
    {
        if (!$this->enabled) return null;
        
        try {
            $info = $this->redis->info();
            return [
                'connected' => true,
                'used_memory' => $info['used_memory_human'] ?? 'N/A',
                'total_keys' => $this->redis->dbSize(),
                'uptime_days' => isset($info['uptime_in_days']) ? $info['uptime_in_days'] : 'N/A'
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}