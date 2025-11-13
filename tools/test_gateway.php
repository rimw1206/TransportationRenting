<?php
/**
 * tools/test_gateway.php
 * Test if gateway is routing correctly
 */

echo "╔══════════════════════════════════════════════╗\n";
echo "║   Gateway Routing Test                       ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

// Test 1: Check if gateway files exist
echo "🔍 Test 1: Check Gateway Files\n";
echo str_repeat('-', 60) . "\n";

$files = [
    'gateway/api/index.php' => __DIR__ . '/../gateway/api/index.php',
    'gateway/api/.htaccess' => __DIR__ . '/../gateway/api/.htaccess',
    'gateway/config/services.php' => __DIR__ . '/../gateway/config/services.php',
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name exists\n";
    } else {
        echo "❌ $name MISSING!\n";
    }
}

// Test 2: Try accessing gateway directly
echo "\n🔍 Test 2: Test Gateway Direct Access\n";
echo str_repeat('-', 60) . "\n";

$urls = [
    'Gateway root' => 'http://localhost/TransportationRenting/gateway/api/',
    'Gateway test' => 'http://localhost/TransportationRenting/gateway/api/test.php',
    'Gateway index' => 'http://localhost/TransportationRenting/gateway/api/index.php',
];

foreach ($urls as $name => $url) {
    echo "Testing: $name\n";
    echo "URL: $url\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ Error: $error\n\n";
    } else {
        echo "Status: $httpCode\n";
        
        // Extract body
        $headerSize = strpos($response, "\r\n\r\n");
        $body = substr($response, $headerSize + 4);
        echo "Response: " . substr($body, 0, 200) . "\n\n";
    }
}

// Test 3: Test with auth/login path
echo "🔍 Test 3: Test Auth Login Path\n";
echo str_repeat('-', 60) . "\n";

$loginUrl = 'http://localhost/TransportationRenting/gateway/api/auth/login';
echo "URL: $loginUrl\n";

$postData = json_encode([
    'username' => 'admin',
    'password' => 'admin123'
]);

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Error: $error\n";
} else {
    echo "Status: $httpCode\n";
    
    // Extract headers and body
    $headerSize = strpos($response, "\r\n\r\n");
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize + 4);
    
    echo "\nHeaders:\n";
    $headerLines = explode("\n", $headers);
    foreach (array_slice($headerLines, 0, 5) as $line) {
        echo "  " . trim($line) . "\n";
    }
    
    echo "\nResponse Body:\n";
    $decoded = json_decode($body, true);
    if ($decoded) {
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo $body . "\n";
    }
}

// Test 4: Check if .htaccess is working
echo "\n🔍 Test 4: Check .htaccess Status\n";
echo str_repeat('-', 60) . "\n";

$htaccessPath = __DIR__ . '/../gateway/api/.htaccess';
if (file_exists($htaccessPath)) {
    echo "✅ .htaccess exists\n";
    echo "Content:\n";
    echo file_get_contents($htaccessPath);
} else {
    echo "❌ .htaccess NOT FOUND!\n";
    echo "\n⚠️  This is likely the problem!\n";
    echo "Creating .htaccess file...\n";
    
    $htaccessContent = <<<'HTACCESS'
# Enable Rewrite Engine
RewriteEngine On

# Set base path
RewriteBase /TransportationRenting/gateway/api/

# Don't rewrite if file exists
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Route all requests through index.php
RewriteRule ^(.*)$ index.php [QSA,L]

# Allow Authorization header
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
HTACCESS;
    
    $created = @file_put_contents($htaccessPath, $htaccessContent);
    if ($created) {
        echo "✅ .htaccess created!\n";
        echo "Please test again.\n";
    } else {
        echo "❌ Failed to create .htaccess\n";
    }
}

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║          Test Complete                       ║\n";
echo "╚══════════════════════════════════════════════╝\n";