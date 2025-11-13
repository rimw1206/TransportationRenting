<?php

class CorsMiddleware {
    public static function handle() {
        // Cho phép mọi domain (trong production thì thay * bằng domain cụ thể)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // Cache preflight 24h
    }
}
