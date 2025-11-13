<?php
// stubs/JWTHandler.php

class JWTHandler {
    public function decode(string $token): ?array {
        // trả về null mặc định
        return null;
    }

    public function encode(array $payload, int $ttl = 3600): string {
        // trả về chuỗi rỗng mặc định
        return '';
    }
}
