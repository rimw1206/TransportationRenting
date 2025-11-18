<?php
// shared/classes/DatabaseManager.php - FIXED VERSION with getConnection() alias
class DatabaseManager {
    private static $instances = [];
    private static $configs = null;

    /**
     * Load database configs from environment
     */
    private static function loadConfigs() {
        if (self::$configs !== null) {
            return self::$configs;
        }

        $databases = ['customer', 'vehicle', 'rental', 'order', 'payment', 'notification'];
        self::$configs = [];
        
        foreach ($databases as $service) {
            $prefix = strtoupper($service);
            self::$configs[$service] = [
                'host' => $_ENV["{$prefix}_DB_HOST"] ?? 'localhost',
                'port' => (int)($_ENV["{$prefix}_DB_PORT"] ?? 3306),
                'dbname' => $_ENV["{$prefix}_DB_NAME"] ?? "{$service}_service_db",
                'username' => $_ENV["{$prefix}_DB_USER"] ?? 'root',
                'password' => $_ENV["{$prefix}_DB_PASS"] ?? '',
                'charset' => 'utf8mb4',
            ];
        }

        return self::$configs;
    }

    /**
     * Get PDO instance for a service (Singleton pattern)
     */
    public static function getInstance($serviceName) {
        if (!isset(self::$instances[$serviceName])) {
            $configs = self::loadConfigs();

            if (!isset($configs[$serviceName])) {
                throw new Exception("Service database '{$serviceName}' not found");
            }

            $config = $configs[$serviceName];

            try {
                $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";
                
                $pdo = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);
                
                self::$instances[$serviceName] = $pdo;
                
            } catch (PDOException $e) {
                error_log("Database connection error [{$serviceName}]: " . $e->getMessage());
                throw new Exception("Could not connect to {$serviceName} database");
            }
        }

        return self::$instances[$serviceName];
    }

    /**
     * Alias for getInstance() - for backward compatibility
     */
    public static function getConnection($serviceName) {
        return self::getInstance($serviceName);
    }

    /**
     * Execute a query (INSERT, UPDATE, DELETE)
     */
    public static function execute($serviceName, $sql, $params = []) {
        $pdo = self::getInstance($serviceName);
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Query multiple rows
     */
    public static function query($serviceName, $sql, $params = []) {
        $pdo = self::getInstance($serviceName);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Query single row
     */
    public static function queryOne($serviceName, $sql, $params = []) {
        $pdo = self::getInstance($serviceName);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Query single value
     */
    public static function queryValue($serviceName, $sql, $params = []) {
        $pdo = self::getInstance($serviceName);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Get last insert ID
     */
    public static function lastInsertId($serviceName) {
        return self::getInstance($serviceName)->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction($serviceName) {
        return self::getInstance($serviceName)->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit($serviceName) {
        return self::getInstance($serviceName)->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollback($serviceName) {
        return self::getInstance($serviceName)->rollBack();
    }

    /**
     * Check if in transaction
     */
    public static function inTransaction($serviceName) {
        return self::getInstance($serviceName)->inTransaction();
    }

    /**
     * Close all connections
     */
    public static function closeAll() {
        self::$instances = [];
    }

    /**
     * Close specific connection
     */
    public static function close($serviceName) {
        if (isset(self::$instances[$serviceName])) {
            unset(self::$instances[$serviceName]);
        }
    }

    /**
     * Test connection
     */
    public static function testConnection($serviceName) {
        try {
            $pdo = self::getInstance($serviceName);
            $pdo->query("SELECT 1");
            return [
                'success' => true,
                'message' => "Connection to {$serviceName} database successful"
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get database info
     */
    public static function getDatabaseInfo($serviceName) {
        $configs = self::loadConfigs();
        
        if (!isset($configs[$serviceName])) {
            return null;
        }

        $config = $configs[$serviceName];
        
        return [
            'service' => $serviceName,
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['dbname'],
            'username' => $config['username'],
            'charset' => $config['charset']
        ];
    }
}