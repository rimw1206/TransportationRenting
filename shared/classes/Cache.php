<?php
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
                $this->redis->connect($_ENV['REDIS_HOST'] ?? '127.0.0.1', $_ENV['REDIS_PORT'] ?? 6379);
                if ($_ENV['REDIS_PASSWORD'] ?? null) {
                    $this->redis->auth($_ENV['REDIS_PASSWORD']);
                }
                $this->redis->ping();
            } catch (Exception $e) {
                $this->enabled = false;
            }
        }
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function get($key)
    {
        if (!$this->enabled) return null;
        
        try {
            $value = $this->redis->get($this->prefix . $key);
            if ($value === false) return null;
            
            $data = @unserialize($value);
            return $data !== false ? $data : $value;
        } catch (Exception $e) {
            return null;
        }
    }
    
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
            return false;
        }
    }
    
    public function delete($key)
    {
        if (!$this->enabled) return false;
        
        try {
            return $this->redis->del($this->prefix . $key) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function isAvailable()
    {
        return $this->enabled;
    }
}