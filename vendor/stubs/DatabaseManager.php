<?php
// stubs/DatabaseManager.php

class DatabaseManager
{
    private static $connections = [];
    
    /**
     * @param string $serviceName
     * @return PDO
     */
    public static function getConnection(string $serviceName): PDO
    {
        // Stub implementation - just return a mock PDO
        if (!isset(self::$connections[$serviceName])) {
            self::$connections[$serviceName] = new PDO('sqlite::memory:');
        }
        return self::$connections[$serviceName];
    }
}