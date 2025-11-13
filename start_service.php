<?php
// start_service.php
/**
 * Usage: php start_service.php <service>
 */

$services = [
    'gateway'      => ['port'=>8000, 'folder'=>'gateway/public'],
    'customer'     => ['port'=>8001, 'folder'=>'services/customer/public'],
    'vehicle'      => ['port'=>8002, 'folder'=>'services/vehicle/public'],
    'rental'       => ['port'=>8003, 'folder'=>'services/rental/public'],
    'order'        => ['port'=>8004, 'folder'=>'services/order/public'],
    'payment'      => ['port'=>8005, 'folder'=>'services/payment/public'],
    'notification' => ['port'=>8006, 'folder'=>'services/notification/public'],
];

$serviceName = $argv[1] ?? null;

if (!$serviceName || !isset($services[$serviceName])) {
    echo "Usage: php start_service.php <service>\n";
    echo "Available services: " . implode(', ', array_keys($services)) . "\n";
    exit(1);
}

$info = $services[$serviceName];
$port = $info['port'];
$folder = __DIR__ . '/' . $info['folder'];

// Kiểm tra folder
if (!is_dir($folder)) {
    echo "❌ Folder not found: $folder\n";
    exit(1);
}

// Kiểm tra port
function isPortRunning($port) {
    if (strtoupper(substr(PHP_OS,0,3))==='WIN') {
        $output = [];
        exec("netstat -ano | findstr :$port", $output);
        return !empty($output);
    } else {
        $output = [];
        exec("lsof -i :$port 2>/dev/null", $output);
        return count($output) > 0;
    }
}

if (isPortRunning($port)) {
    echo "⚠️ Service '$serviceName' is already running on port $port\n";
    exit(0);
}

// Start service
if (strtoupper(substr(PHP_OS,0,3))==='WIN') {
    $cmd = "start \"$serviceName\" /MIN php -S localhost:$port -t \"$folder\"";
    pclose(popen($cmd,'r'));
} else {
    $cmd = "nohup php -S localhost:$port -t \"$folder\" > /dev/null 2>&1 &";
    exec($cmd);
}

sleep(1);

if (isPortRunning($port)) {
    echo "✅ Service '$serviceName' started on port $port\n";
} else {
    echo "❌ Failed to start service '$serviceName'\n";
}
