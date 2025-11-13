<?php
// stop_service.php
/**
 * Usage: php stop_service.php <service>
 */

$services = [
    'gateway'      => 8000,
    'customer'     => 8001,
    'vehicle'      => 8002,
    'rental'       => 8003,
    'order'        => 8004,
    'payment'      => 8005,
    'notification' => 8006,
];

$serviceName = $argv[1] ?? null;

if (!$serviceName || !isset($services[$serviceName])) {
    echo "Usage: php stop_service.php <service>\n";
    echo "Available services: " . implode(', ', array_keys($services)) . "\n";
    exit(1);
}

$port = $services[$serviceName];

// Hàm lấy PID trên port
function getPortPID($port) {
    if (strtoupper(substr(PHP_OS,0,3))==='WIN') {
        $output = [];
        exec("netstat -ano | findstr :$port", $output);
        if (!empty($output)) {
            preg_match('/\s+(\d+)$/', $output[0], $matches);
            return $matches[1] ?? null;
        }
    } else {
        $output = [];
        exec("lsof -t -i :$port 2>/dev/null", $output);
        return $output[0] ?? null;
    }
    return null;
}

// Hàm kill process
function killProcess($pid) {
    if (!$pid) return false;
    if (strtoupper(substr(PHP_OS,0,3))==='WIN') {
        exec("taskkill /F /PID $pid");
    } else {
        exec("kill -9 $pid");
    }
    return true;
}

// Stop service
$pid = getPortPID($port);
if ($pid) {
    killProcess($pid);
    echo "✅ Service '$serviceName' stopped (PID: $pid, Port: $port)\n";
} else {
    echo "⚠️ Service '$serviceName' is not running on port $port\n";
}
