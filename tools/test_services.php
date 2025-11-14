<?php
/**
 * Test All Services
 */

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║         Service Health Check                 ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

$services = [
    'Customer Service' => 'http://localhost:8001/health',
    'Vehicle Service' => 'http://localhost:8002/health',
    'Rental Service' => 'http://localhost:8003/health',
    'Order Service' => 'http://localhost:8004/health',
    'Payment Service' => 'http://localhost:8005/health',
    'Notification Service' => 'http://localhost:8006/health',
];

$allRunning = true;

foreach ($services as $name => $url) {
    echo "Testing $name...\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "  ✅ Running on " . parse_url($url, PHP_URL_HOST) . ":" . parse_url($url, PHP_URL_PORT) . "\n";
        
        $data = json_decode($response, true);
        if ($data && isset($data['service'])) {
            echo "     Service: " . $data['service'] . "\n";
            echo "     Status: " . ($data['status'] ?? 'unknown') . "\n";
        }
    } else {
        echo "  ❌ NOT RUNNING\n";
        if ($error) {
            echo "     Error: $error\n";
        }
        $allRunning = false;
    }
    echo "\n";
}

echo str_repeat("=", 60) . "\n\n";

if ($allRunning) {
    echo "✅ All services are running!\n\n";
    echo "You can now test the login:\n";
    echo "  php tools\\test_login_detailed.php\n\n";
    echo "Or open in browser:\n";
    echo "  http://localhost/TransportationRenting/frontend/login.php\n";
} else {
    echo "⚠️  Some services are not running.\n";
    echo "Please start them using: setup_and_start.bat\n";
}

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║         Check Complete                       ║\n";
echo "╚══════════════════════════════════════════════╝\n";