<?php
/**
 * Diagnose timeout issues
 */

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║         Timeout Diagnosis Tool               ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

// Test 1: Check if port 8001 is listening
echo "📡 Test 1: Check if port 8001 is open\n";
echo str_repeat("-", 60) . "\n";

$connection = @fsockopen('localhost', 8001, $errno, $errstr, 2);
if ($connection) {
    echo "✅ Port 8001 is OPEN and accepting connections\n";
    fclose($connection);
} else {
    echo "❌ Port 8001 is CLOSED or not listening\n";
    echo "   Error: $errstr ($errno)\n";
    echo "\n   Solution:\n";
    echo "   1. Open new terminal\n";
    echo "   2. cd C:\\xampp\\htdocs\\TransportationRenting\\services\\customer\\public\n";
    echo "   3. php -S localhost:8001\n";
    echo "   4. Keep terminal open!\n";
}
echo "\n";

// Test 2: Check what's listening on port 8001
echo "📡 Test 2: What's on port 8001?\n";
echo str_repeat("-", 60) . "\n";
exec('netstat -ano | findstr :8001', $output);
if (empty($output)) {
    echo "❌ Nothing is listening on port 8001\n";
    echo "   Customer Service is NOT running!\n";
} else {
    echo "✅ Something is listening:\n";
    foreach ($output as $line) {
        echo "   $line\n";
    }
}
echo "\n";

// Test 3: Simple HTTP request with verbose output
echo "📡 Test 3: Make HTTP request with details\n";
echo str_repeat("-", 60) . "\n";

$ch = curl_init('http://localhost:8001/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Increased timeout
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Capture verbose output
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$start = microtime(true);
$response = curl_exec($ch);
$elapsed = microtime(true) - $start;

$info = curl_getinfo($ch);
$error = curl_error($ch);

curl_close($ch);

echo "Connection info:\n";
echo "  - Connect time: " . round($info['connect_time'] * 1000, 2) . " ms\n";
echo "  - Total time: " . round($elapsed * 1000, 2) . " ms\n";
echo "  - HTTP code: " . $info['http_code'] . "\n";
echo "  - Size downloaded: " . $info['size_download'] . " bytes\n";

if ($error) {
    echo "\n❌ Error: $error\n";
}

if ($response) {
    echo "\n✅ Response received:\n";
    echo substr($response, 0, 200) . "\n";
}

rewind($verbose);
$verboseLog = stream_get_contents($verbose);
echo "\nVerbose log:\n";
echo $verboseLog . "\n";

echo "\n";

// Test 4: Try accessing index.php directly
echo "📡 Test 4: Access index.php directly\n";
echo str_repeat("-", 60) . "\n";

$indexPath = __DIR__ . '/../services/customer/public/index.php';
if (file_exists($indexPath)) {
    echo "✅ index.php exists at: $indexPath\n";
    echo "   File size: " . filesize($indexPath) . " bytes\n";
    
    // Check if file is readable
    if (is_readable($indexPath)) {
        echo "✅ File is readable\n";
    } else {
        echo "❌ File is NOT readable\n";
    }
    
    // Check for syntax errors
    exec("php -l " . escapeshellarg($indexPath) . " 2>&1", $syntaxCheck, $returnCode);
    if ($returnCode === 0) {
        echo "✅ No syntax errors\n";
    } else {
        echo "❌ Syntax errors found:\n";
        foreach ($syntaxCheck as $line) {
            echo "   $line\n";
        }
    }
} else {
    echo "❌ index.php NOT FOUND at: $indexPath\n";
}
echo "\n";

// Test 5: Check PHP processes
echo "📡 Test 5: Check PHP processes\n";
echo str_repeat("-", 60) . "\n";
exec('tasklist | findstr php.exe', $processes);
if (empty($processes)) {
    echo "❌ No PHP processes running\n";
} else {
    echo "✅ Found PHP processes:\n";
    foreach ($processes as $proc) {
        echo "   $proc\n";
    }
}
echo "\n";

// Test 6: PHP configuration
echo "📡 Test 6: PHP Configuration\n";
echo str_repeat("-", 60) . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . " seconds\n";
echo "Default socket timeout: " . ini_get('default_socket_timeout') . " seconds\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "\n";

// Recommendations
echo "╔══════════════════════════════════════════════╗\n";
echo "║         Recommendations                      ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

if (!$connection) {
    echo "❌ CRITICAL: Customer Service is NOT running!\n\n";
    echo "To start it:\n";
    echo "1. Open NEW Command Prompt\n";
    echo "2. Run these commands:\n";
    echo "   cd C:\\xampp\\htdocs\\TransportationRenting\\services\\customer\\public\n";
    echo "   php -S localhost:8001\n";
    echo "3. You should see: 'PHP Development Server started'\n";
    echo "4. Keep that window OPEN\n";
    echo "5. Come back to this window and run tests again\n\n";
} else {
    echo "✅ Service appears to be running\n";
    if ($info['http_code'] === 0) {
        echo "⚠️  But it's not responding to HTTP requests\n";
        echo "   Possible issues:\n";
        echo "   - Service is starting up (wait a few seconds)\n";
        echo "   - Service crashed immediately after starting\n";
        echo "   - Firewall blocking connection\n";
        echo "   - PHP syntax error in index.php\n";
    }
}

echo "\n";