<?php
// tools/diagnose_routing.php
echo "═══════════════════════════════════════════════════════════\n";
echo "  Customer Service Routing Diagnostic\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$baseDir = __DIR__ . '/..';

// Try multiple possible directory names
$possibleDirs = [
    $baseDir . '/services/customer',
    $baseDir . '/customer',
    $baseDir . '/Customer-service',
];

$customerServiceDir = null;
foreach ($possibleDirs as $dir) {
    if (is_dir($dir)) {
        $customerServiceDir = $dir;
        break;
    }
}

// 1. Check if Customer service directory exists
echo "1️⃣  Checking Customer Service Directory:\n";
if ($customerServiceDir) {
    echo "   ✅ Found: $customerServiceDir\n\n";
} else {
    echo "   ❌ Directory NOT found!\n";
    echo "   Tried:\n";
    foreach ($possibleDirs as $dir) {
        echo "   - $dir\n";
    }
    echo "\n";
    exit(1);
}

// 2. Check for index.php files
echo "2️⃣  Looking for index.php files:\n";
$possiblePaths = [
    $customerServiceDir . '/public/index.php',
    $customerServiceDir . '/index.php',
];

$foundIndexFiles = [];
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        echo "   ✅ Found: $path\n";
        $foundIndexFiles[] = $path;
    } else {
        echo "   ❌ Not found: $path\n";
    }
}
echo "\n";

if (empty($foundIndexFiles)) {
    echo "❌ No index.php found in Customer service!\n";
    exit(1);
}

// 3. Check the ACTUAL content of the active index.php
echo "3️⃣  Checking Content of Active index.php:\n";
$activeIndexPath = $customerServiceDir . '/public/index.php';

if (file_exists($activeIndexPath)) {
    echo "   File: $activeIndexPath\n";
    echo "   Size: " . filesize($activeIndexPath) . " bytes\n";
    echo "   Last modified: " . date("Y-m-d H:i:s", filemtime($activeIndexPath)) . "\n\n";
    
    $content = file_get_contents($activeIndexPath);
    
    // Check if it has the fix
    if (strpos($content, "strpos(\$uri, '/auth') === 0") !== false) {
        echo "   ✅ CODE FIX IS PRESENT!\n";
        echo "   The /auth prefix stripping code exists.\n\n";
    } else {
        echo "   ❌ CODE FIX IS MISSING!\n";
        echo "   The /auth prefix stripping code is NOT in the file.\n\n";
    }
    
    // Show relevant lines
    echo "   📄 First 60 lines of the file:\n";
    echo "   " . str_repeat("-", 70) . "\n";
    $lines = explode("\n", $content);
    for ($i = 0; $i < min(60, count($lines)); $i++) {
        printf("   %3d | %s\n", $i + 1, $lines[$i]);
    }
    echo "   " . str_repeat("-", 70) . "\n\n";
} else {
    echo "   ❌ File not found: $activeIndexPath\n\n";
}

// 4. Check if service is running on port 8001
echo "4️⃣  Checking if Customer Service is Running:\n";
$connection = @fsockopen('localhost', 8001, $errno, $errstr, 2);
if ($connection) {
    fclose($connection);
    echo "   ✅ Service is running on port 8001\n\n";
} else {
    echo "   ❌ Service NOT running on port 8001\n";
    echo "   Error: $errstr ($errno)\n\n";
}

// 5. Test the actual routing logic
echo "5️⃣  Testing Route Parsing Logic:\n";

$testCases = [
    ['/auth/login', 'POST'],
    ['/login', 'POST'],
    ['/users/123', 'GET'],
    ['/profile', 'GET'],
    ['/health', 'GET'],
];

foreach ($testCases as $test) {
    $uri = parse_url($test[0], PHP_URL_PATH);
    $original = $uri;
    
    // Apply the fix logic
    if (strpos($uri, '/auth') === 0) {
        $uri = substr($uri, strlen('/auth'));
        if ($uri === '') $uri = '/';
    }
    
    echo "   Test: {$test[1]} {$original}\n";
    echo "   After strip: $uri\n";
    
    // Check what route it would match
    if ($uri === '/login' && $test[1] === 'POST') {
        echo "   ✅ Would match /login route\n";
    } elseif ($uri === '/health' && $test[1] === 'GET') {
        echo "   ✅ Would match /health route\n";
    } elseif ($uri === '/profile' && $test[1] === 'GET') {
        echo "   ✅ Would match /profile route\n";
    } elseif (preg_match('#^/users/(\d+)$#', $original)) {
        echo "   ✅ Would match /users/{id} route\n";
    } else {
        echo "   ⚠️  Would NOT match any route\n";
    }
    echo "\n";
}

// 6. Make actual HTTP test
echo "6️⃣  Making Actual HTTP Requests:\n";

$tests = [
    ['url' => 'http://localhost:8001/health', 'method' => 'GET'],
    ['url' => 'http://localhost:8001/auth/login', 'method' => 'POST', 'data' => ['username' => 'test', 'password' => 'test']],
    ['url' => 'http://localhost:8001/login', 'method' => 'POST', 'data' => ['username' => 'test', 'password' => 'test']],
];

foreach ($tests as $test) {
    echo "   Testing: {$test['method']} {$test['url']}\n";
    
    $ch = curl_init($test['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    if ($test['method'] === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (isset($test['data'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test['data']));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   Status: $httpCode\n";
    if ($response) {
        $decoded = json_decode($response, true);
        echo "   Response: " . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "   Response: (empty)\n";
    }
    
    if ($httpCode === 200) {
        echo "   ✅ Request succeeded\n";
    } elseif ($httpCode === 404) {
        echo "   ❌ Route not found (404)\n";
    } else {
        echo "   ⚠️  Unexpected status code\n";
    }
    echo "\n";
}

// 7. Summary and recommendations
echo "═══════════════════════════════════════════════════════════\n";
echo "  SUMMARY & RECOMMENDATIONS\n";
echo "═══════════════════════════════════════════════════════════\n\n";

if (file_exists($activeIndexPath)) {
    $content = file_get_contents($activeIndexPath);
    $hasAuthFix = strpos($content, "strpos(\$uri, '/auth') === 0") !== false;
    
    if (!$hasAuthFix) {
        echo "❌ ACTION REQUIRED:\n\n";
        echo "1. Edit this file:\n";
        echo "   $activeIndexPath\n\n";
        echo "2. Add this code after line ~10 (after \$uri = parse_url...):\n\n";
        echo "   // Strip /auth prefix if present\n";
        echo "   if (strpos(\$uri, '/auth') === 0) {\n";
        echo "       \$uri = substr(\$uri, strlen('/auth'));\n";
        echo "       if (\$uri === '') \$uri = '/';\n";
        echo "   }\n\n";
        echo "3. Save the file\n\n";
        echo "4. Restart the service or run: php run.php\n\n";
    } else {
        echo "✅ Code fix is present!\n\n";
        echo "If tests still fail:\n";
        echo "1. Restart the service: killall php (Linux/Mac) or taskkill /F /IM php.exe (Windows)\n";
        echo "2. Start again: php run.php\n";
        echo "3. Check if port 8001 is really serving the updated code\n\n";
    }
}

echo "═══════════════════════════════════════════════════════════\n";