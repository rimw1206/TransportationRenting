<?php
/**
 * check_api_structure.php
 * Place in: TransportationRenting/tools/
 * Check if API endpoints exist and are accessible
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>API Structure Checker</h1><hr>";

$projectRoot = dirname(__DIR__);

// Expected API endpoint locations
$apiEndpoints = [
    'Get Profile' => $projectRoot . '/customer-service/api/v1/endpoints/get_profile.php',
    'Get KYC' => $projectRoot . '/customer-service/api/v1/endpoints/get_kyc.php',
    'Get Payment Methods' => $projectRoot . '/customer-service/api/v1/endpoints/get_payment_methods.php',
    'Get Rental History' => $projectRoot . '/customer-service/api/v1/endpoints/get_rental_history.php',
];

echo "<h2>1. Checking API Endpoint Files</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Endpoint</th><th>File Path</th><th>Status</th></tr>";

$allExist = true;
foreach ($apiEndpoints as $name => $path) {
    $exists = file_exists($path);
    $status = $exists 
        ? "<span style='color:green'>✓ EXISTS</span>" 
        : "<span style='color:red'>✗ NOT FOUND</span>";
    
    echo "<tr>";
    echo "<td><strong>$name</strong></td>";
    echo "<td><small>$path</small></td>";
    echo "<td>$status</td>";
    echo "</tr>";
    
    if (!$exists) {
        $allExist = false;
    }
}
echo "</table>";

if (!$allExist) {
    echo "<div style='background:#fee;padding:20px;margin:20px 0;border-radius:10px;'>";
    echo "<h3 style='color:#991b1b'>⚠ Missing API Endpoint Files!</h3>";
    echo "<p>You need to create these API endpoint files. Here's the correct structure:</p>";
    echo "<pre>";
    echo "customer-service/\n";
    echo "└── api/\n";
    echo "    └── v1/\n";
    echo "        ├── endpoints/\n";
    echo "        │   ├── get_profile.php\n";
    echo "        │   ├── get_kyc.php\n";
    echo "        │   ├── get_payment_methods.php\n";
    echo "        │   └── get_rental_history.php\n";
    echo "        └── classes/\n";
    echo "            └── User.php\n";
    echo "</pre>";
    echo "</div>";
}

echo "<hr>";

// Check what files exist in customer-service
echo "<h2>2. Scanning customer-service Directory</h2>";
$customerServiceDir = $projectRoot . '/customer-service';

if (is_dir($customerServiceDir)) {
    echo "<p><strong>Found customer-service directory:</strong> $customerServiceDir</p>";
    
    // Recursively find all PHP files
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($customerServiceDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    $phpFiles = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $phpFiles[] = str_replace($projectRoot, '', $file->getPathname());
        }
    }
    
    if (!empty($phpFiles)) {
        echo "<p>PHP files found in customer-service:</p>";
        echo "<ul>";
        foreach ($phpFiles as $file) {
            echo "<li><code>$file</code></li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>No PHP files found in customer-service directory!</p>";
    }
} else {
    echo "<p style='color:red'>customer-service directory not found at: $customerServiceDir</p>";
}

echo "<hr>";

// Check ApiClient configuration
echo "<h2>3. Checking ApiClient</h2>";
$apiClientPath = $projectRoot . '/shared/classes/ApiClient.php';

if (file_exists($apiClientPath)) {
    echo "<span style='color:green'>✓ ApiClient found</span><br><br>";
    echo "<p>Check how ApiClient constructs URLs. It should call:</p>";
    echo "<code>http://localhost:8001/api/v1/endpoints/get_profile.php</code><br><br>";
    
    echo "<p><strong>In profile.php, you're calling:</strong></p>";
    echo "<code>\$apiClient->get('customer', '/profile', [], \$token)</code><br><br>";
    
    echo "<p>Make sure ApiClient builds the full URL correctly!</p>";
} else {
    echo "<span style='color:red'>✗ ApiClient not found at: $apiClientPath</span><br>";
}

echo "<hr>";

// Test direct PHP include
echo "<h2>4. Testing Direct Endpoint Access</h2>";

$testEndpoint = $projectRoot . '/customer-service/api/v1/endpoints/get_profile.php';

if (file_exists($testEndpoint)) {
    echo "<p>Endpoint exists. Content preview:</p>";
    $content = file_get_contents($testEndpoint);
    $preview = substr($content, 0, 500);
    echo "<pre style='background:#f5f5f5;padding:10px;'>" . htmlspecialchars($preview) . "...</pre>";
    
    echo "<p>Try accessing directly:</p>";
    echo "<code>http://localhost:8001/api/v1/endpoints/get_profile.php</code>";
} else {
    echo "<p style='color:red'>Cannot test - endpoint file doesn't exist</p>";
}

echo "<hr>";

// Recommendations
echo "<h2>5. Troubleshooting Steps</h2>";
echo "<ol>";
echo "<li><strong>Create missing endpoint files</strong> in <code>customer-service/api/v1/endpoints/</code></li>";
echo "<li><strong>Start API server</strong> on port 8001: <code>php -S localhost:8001 -t customer-service/api/v1/endpoints</code></li>";
echo "<li><strong>Test endpoint directly</strong> with curl or browser</li>";
echo "<li><strong>Check ApiClient URL building</strong> - it should create full paths</li>";
echo "<li><strong>Enable error logging</strong> in both profile.php and endpoints</li>";
echo "</ol>";

echo "<hr>";
echo "<p><em>If you need help creating the endpoint files, let me know!</em></p>";