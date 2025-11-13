<?php
// stubs/Redis.php

class Redis
{
    public function connect(string $host, int $port = 6379, float $timeout = 0.0): bool { return true; }
    public function auth(string $password): bool { return true; }
    public function ping(): string { return '+PONG'; }
    public function set(string $key, mixed $value): bool { return true; }
    public function get(string $key): mixed { return null; }
    public function setex(string $key, int $ttl, mixed $value): bool { return true; }
    public function del(string|array $key): int { return 1; }
    public function exists(string $key): bool { return true; }
    public function incrBy(string $key, int $value): int { return $value; }
    public function decrBy(string $key, int $value): int { return $value; }
    public function expire(string $key, int $ttl): bool { return true; }
    public function keys(string $pattern): array { return []; }
    public function dbSize(): int { return 0; }
    public function info(): array { return []; }
}

