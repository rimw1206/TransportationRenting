<?php
class DatabaseManager 
{
    private static $connections = [];
    private static $configs = null;

    private static function loadConfigs(): array
    {
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

    public static function getConnection(string $serviceName): PDO
    {
        $configs = self::loadConfigs();

        if (!isset($configs[$serviceName])) {
            throw new Exception("Service [$serviceName] không tồn tại!");
        }

        if (!isset(self::$connections[$serviceName])) {
            $cfg = $configs[$serviceName];
            
            try {
                $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
                $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                
                self::$connections[$serviceName] = $pdo;
            } catch (PDOException $e) {
                throw new Exception("Could not connect to {$serviceName} database");
            }
        }

        return self::$connections[$serviceName];
    }
}

// Helper functions
function pdo_execute(string $service, string $sql, ...$params): bool {
    $conn = DatabaseManager::getConnection($service);
    $stmt = $conn->prepare($sql);
    return $stmt->execute($params);
}

function pdo_query(string $service, string $sql, ...$params): array {
    $conn = DatabaseManager::getConnection($service);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function pdo_query_one(string $service, string $sql, ...$params) {
    $conn = DatabaseManager::getConnection($service);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function pdo_query_value(string $service, string $sql, ...$params) {
    $conn = DatabaseManager::getConnection($service);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function pdo_last_insert_id(string $service): string {
    return DatabaseManager::getConnection($service)->lastInsertId();
}